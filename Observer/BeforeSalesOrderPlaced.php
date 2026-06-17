<?php
namespace Violet\VioletConnect\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderAddressInterfaceFactory;
use Magento\Sales\Model\Order;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteRepository;
use Psr\Log\LoggerInterface;

/**
 * Violet Before Order Placed
 *
 * @copyright  2024 Violet.io, Inc.
 * @since      1.2.0
 */
class BeforeSalesOrderPlaced implements ObserverInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var OrderAddressInterfaceFactory
     */
    private $orderAddressFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
      \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
      \Magento\Sales\Api\Data\OrderAddressInterfaceFactory $orderAddressFactory,
      LoggerInterface $logger
    ) {
            $this->quoteRepository = $quoteRepository;
            $this->orderAddressFactory = $orderAddressFactory;
            $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            // obtain the order interceptor from the observer
            $order = $observer->getOrder();

            // if this is a violet sourced order perform additional checks
            if ($this->isVioletSourcedOrder($order)) {

                // Ensure the order has a billing address before payment validation runs.
                // If billing is missing or has no country, fall back to the shipping address
                // so AbstractMethod::validate() doesn't blow up on null->getCountryId().
                $this->ensureBillingAddress($order);

                // load the quote using the ID
                $quote = $this->quoteRepository->get($order->getQuoteId());

                // if external shipping info was applied to the quote
                if ($this->quoteHasExtShippingInfo($quote)) {
                    $shippingInfo = json_decode($quote->getExtShippingInfo());

                    $_hasShippingPrice = $this->hasShippingPrice($shippingInfo);
                    $_hasTaxAmount = $this->hasTaxAmount($shippingInfo);
        
                    // override the shipping price if a custom price has been provided
                    if ($_hasShippingPrice || $_hasTaxAmount) {

                        // apply custom price to shipping price fields
                        if ($_hasShippingPrice) {
                            $order->setBaseShippingAmount($shippingInfo->shipping_price);
                            $order->setBaseShippingInclTax($shippingInfo->shipping_price);
                            $order->setShippingInclTax($shippingInfo->shipping_price);
                            $order->setShippingAmount($shippingInfo->shipping_price);
                        }
            
                        // apply custom tax amount if present
                        if ($_hasTaxAmount) {
                            $order->setTaxAmount($shippingInfo->tax_amount);
                            $order->setBaseTaxAmount($shippingInfo->tax_amount);
                        }

                        // calculate the new grand total
                        $grandTotal = $this->sumGrandTotal($order);
            
                        // apply grand total
                        $order->setGrandTotal($grandTotal);
                        $order->setBaseGrandTotal($grandTotal);
                        $order->setBaseTotalDue($grandTotal);
                        $order->setTotalDue($grandTotal);
                    }

                    // override the shipping description if a custom description has been provided
                    if ($this->hasShippingDescription($shippingInfo)) {
                        $order->setShippingDescription($shippingInfo->shipping_description);
                    }
                }

                // preserve custom discount information from quote to order
                $this->preserveCustomDiscountData($quote, $order);
            }
        } catch (\Exception $e) {}
    }

    /**
     * Ensure the order has a usable billing address. If the address row is
     * missing, copy shipping data; if the row exists but has no country, copy
     * only the shipping country. This prevents AbstractMethod::validate()
     * from reading a null billing country.
     *
     * @param Order $order
     * @return void
     */
    private function ensureBillingAddress(Order $order) {
        if ($order->getIsVirtual()) {
            return;
        }

        $shipping = $order->getShippingAddress();
        if ($shipping === null || !$shipping->getCountryId()) {
            return;
        }

        $billing = $order->getBillingAddress();
        if ($billing !== null && $billing->getCountryId()) {
            return;
        }

        if ($billing === null) {
            $shippingData = $shipping->getData();
            unset($shippingData['entity_id'], $shippingData['parent_id'], $shippingData['address_type']);

            $newBilling = $this->orderAddressFactory->create();
            $newBilling->addData($shippingData);
            $order->setBillingAddress($newBilling);

            $this->logger->info(
                'Violet sales_order_place_before: copied shipping address to order billing address (quoteId='
                . $order->getQuoteId() . ')'
            );
        } else {
            $billing->setCountryId($shipping->getCountryId());
            $billing->setAddressType(OrderAddressInterface::ADDRESS_TYPE_BILLING);

            $this->logger->info(
                'Violet sales_order_place_before: copied shipping country to order billing address (quoteId='
                . $order->getQuoteId() . ')'
            );
        }
    }

    /**
     * @return boolean
     */
    private function isVioletSourcedOrder($order) {
        if (is_null($order)) return false;
        if (is_null($order->getPayment())) return false;
        if (is_null($order->getPayment()->getMethod())) return false;
        return (strcmp($order->getPayment()->getMethod(), "violet") === 0);
    }

    /**
     * @return boolean
     */
    private function quoteHasExtShippingInfo($quote) {
        $_shippingInfo = $quote->getExtShippingInfo();
        return (isset($_shippingInfo) && (strlen(trim($_shippingInfo)) > 0));
    }

    /**
     * @return boolean
     */
    private function hasShippingPrice($shippingInfo) {
        return (isset($shippingInfo) && isset($shippingInfo->shipping_price) && is_numeric($shippingInfo->shipping_price));
    }

    /**
     * @return boolean
     */
    private function hasShippingDescription($shippingInfo) {
        return (isset($shippingInfo) && isset($shippingInfo->shipping_description));
    }

    /**
     * @return boolean
     */
    private function hasTaxAmount($shippingInfo) {
        return (isset($shippingInfo) && isset($shippingInfo->tax_amount) && is_numeric($shippingInfo->tax_amount));
    }

    /**
     * @return double
     */
    private function sumGrandTotal($orderTotals) {
        return $orderTotals->getShippingAmount() + $orderTotals->getSubtotal() + $orderTotals->getTaxAmount() + $orderTotals->getDiscountAmount();
    }

    /**
     * Preserve custom discount data from quote to order
     * 
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Sales\Model\Order $order
     * @return void
     */
    private function preserveCustomDiscountData($quote, $order) {
        // Check if quote has custom discount data
        $customDiscountCode = $quote->getData('violet_custom_discount_code');
        $customDiscountAmount = $quote->getData('violet_custom_discount_amount');
        
        if ($customDiscountCode && $customDiscountAmount) {
            // Store custom discount information on the order for reference
            $order->setData('violet_custom_discount_code', $customDiscountCode);
            $order->setData('violet_custom_discount_amount', $customDiscountAmount);
            
            // Ensure discount description is preserved on order
            $discountDescription = ($customDiscountCode && $customDiscountCode !== 'Custom Discount') ? 
                'Custom Discount: ' . $customDiscountCode : 
                'Custom Discount';
            if (!$order->getDiscountDescription()) {
                $order->setDiscountDescription($discountDescription);
            }
        }
    }
}
