<?php
namespace Violet\VioletConnect\Model\ResourceModel;

use Violet\VioletConnect\Api\VioletCustomDiscountRepositoryInterface;
use Violet\VioletConnect\Model\Data\CustomDiscount;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Violet VioletCustomDiscountRepository
 */
class VioletCustomDiscountRepository implements VioletCustomDiscountRepositoryInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        LoggerInterface $logger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->logger = $logger;
    }

    /**
     * Apply custom discount to guest cart
     * 
     * @param string $cartId
     * @param CustomDiscount $customDiscount
     * @return bool
     */
    public function applyCustomDiscount($cartId, $customDiscount)
    {
        try {
            $this->logger->info('Applying custom discount to cart', [
                'cart_id' => $cartId,
                'discount_code' => $customDiscount->getCode() ?: 'N/A',
                'discount_amount' => $customDiscount->getAmountTotal()
            ]);

            // Load quote by masked ID
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            if (!$quoteIdMask->getQuoteId()) {
                $this->logger->error('Quote not found for cart ID: ' . $cartId);
                return false;
            }

            $quote = $this->quoteRepository->get($quoteIdMask->getQuoteId());
            if (!$quote->getId()) {
                $this->logger->error('Quote entity not found for ID: ' . $quoteIdMask->getQuoteId());
                return false;
            }

            // Convert discount amount from cents to currency units (divide by 100)
            $discountAmount = $customDiscount->getAmountTotal() / 100;
            
            // Apply negative discount amount (discount reduces total)
            $discountAmount = -abs($discountAmount);

            // Store custom discount information on quote for reference
            $quote->setData('violet_custom_discount_code', $customDiscount->getCode() ?: 'Custom Discount');
            $quote->setData('violet_custom_discount_amount', $discountAmount);

            // Apply discount to shipping address (this is where Magento stores cart-level discounts)
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setDiscountAmount($discountAmount);
            $shippingAddress->setBaseDiscountAmount($discountAmount);
            
            // Set discount description for display purposes
            $discountDescription = $customDiscount->getCode() ? 
                'Custom Discount: ' . $customDiscount->getCode() : 
                'Custom Discount';
            $shippingAddress->setDiscountDescription($discountDescription);

            // For virtual products or when no shipping is needed, also apply to billing address
            if ($quote->isVirtual() || !$quote->getShippingAddress()->getCountryId()) {
                $billingAddress = $quote->getBillingAddress();
                $billingAddress->setDiscountAmount($discountAmount);
                $billingAddress->setBaseDiscountAmount($discountAmount);
                $billingAddress->setDiscountDescription($discountDescription);
            }

            // Collect totals to recalculate with the discount
            $quote->collectTotals();
            
            // Save the quote
            $this->quoteRepository->save($quote);

            $this->logger->info('Custom discount applied successfully', [
                'cart_id' => $cartId,
                'discount_amount' => $discountAmount,
                'quote_grand_total' => $quote->getGrandTotal()
            ]);

            return true;

        } catch (NoSuchEntityException $e) {
            $this->logger->error('Cart not found: ' . $e->getMessage(), [
                'cart_id' => $cartId,
                'exception' => $e
            ]);
            return false;
        } catch (LocalizedException $e) {
            $this->logger->error('Error applying custom discount: ' . $e->getMessage(), [
                'cart_id' => $cartId,
                'exception' => $e
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error applying custom discount: ' . $e->getMessage(), [
                'cart_id' => $cartId,
                'exception' => $e
            ]);
            return false;
        }
    }
}