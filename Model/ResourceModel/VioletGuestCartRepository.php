<?php
namespace Violet\VioletConnect\Model\ResourceModel;

use Violet\VioletConnect\Api\VioletGuestCartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Registry;
use Psr\Log\LoggerInterface;
use Violet\VioletConnect\Model\Violet;

/**
 * Violet VioletGuestCartRepository
 *
 * @author     Rhen Zabel <rhen@violet.io>
 * @copyright  2024 Violet.io, Inc.
 * @since      1.2.0
 */
class VioletGuestCartRepository implements VioletGuestCartRepositoryInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
     /**
     * @var QuoteManagement
     */
    private $quoteManagement;
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;
    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteManagement $quoteManagement
     * @param Registry $registry
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Framework\Registry $registry,
        LoggerInterface $logger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteManagement = $quoteManagement;
        $this->registry = $registry;
        $this->logger = $logger;
    }

    /**
     * @api
     * @param string $cartId The cart ID.
     * @return int incrementId
     */
    public function placeOrder($cartId, \Magento\Quote\Api\Data\PaymentInterface $paymentMethod = null)
    {
        if (!$cartId) {
            throw new InputException(
                __('"%fieldName" is required. Enter and try again.', ['fieldName' => 'quoteId'])
            );
        }

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');

        // load the quote using the unmasked ID
        $quote = $this->quoteRepository->get($quoteIdMask->getQuoteId());

        // Mark this request as a Violet-originated payment so the Violet payment method's
        // isAvailable() check passes for importData and placeOrder.
        $this->registry->register(Violet::VIOLET_API_CONTEXT_FLAG, true, true);
        try {
            // any usage of this endpoint must use the violet payment method
            $quote->setPaymentMethod('violet'); //payment method
            $quote->getPayment()->importData(['method' => 'violet']);

            // Defensive: if the quote's billing address is missing or has no country,
            // copy from the shipping address before submitting. This prevents the order
            // from ever being constructed without a billing country, which is the
            // failure mode behind AbstractMethod::validate() raising on null.
            $this->ensureQuoteBillingAddress($quote);

            $quote->save();

            return $this->quoteManagement->placeOrder($quoteIdMask->getQuoteId(), $paymentMethod);
        } finally {
            $this->registry->unregister(Violet::VIOLET_API_CONTEXT_FLAG);
        }
    }

    /**
     * Repair an existing billing address that is missing a country by copying
     * only the shipping country. If the billing address row is missing entirely,
     * the order-level fallback will handle creating one from shipping data.
     *
     * @param Quote $quote
     * @return void
     */
    private function ensureQuoteBillingAddress(Quote $quote)
    {
        if ($quote->getIsVirtual()) {
            return;
        }

        $shipping = $quote->getShippingAddress();
        if (!$shipping || !$shipping->getCountryId()) {
            return;
        }

        $billing = $quote->getBillingAddress();
        // If there is no billing address row at all we can't safely populate one
        // here without an address factory. The order-level fallback in
        // BeforeSalesOrderPlaced::ensureBillingAddress will catch that case.
        if (!$billing || $billing->getCountryId()) {
            return;
        }

        $billing->setCountryId($shipping->getCountryId());
        $billing->setAddressType(Address::TYPE_BILLING);

        if (!$quote->getCustomerEmail() && $shipping->getEmail()) {
            $quote->setCustomerEmail($shipping->getEmail());
        }

        $this->logger->info(
            'Violet placeOrder: copied shipping country to quote billing address (quoteId='
            . $quote->getId() . ')'
        );
    }

    /**
     * @api
     * @param string $cartId The cart ID.
     * @return int cartId
     */
    public function deleteCart($cartId)
    {
        if (!$cartId) {
            throw new InputException(
                __('"%fieldName" is required. Enter and try again.', ['fieldName' => 'quoteId'])
            );
        }

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');

        // load the quote using the unmasked ID
        $quote = $this->quoteRepository->get($quoteIdMask->getQuoteId());

        // delete the quote
        $this->quoteRepository->delete($quote);

        return $cartId;
    }

}
