<?php
namespace Violet\VioletConnect\Model\ResourceModel;

use Violet\VioletConnect\Api\VioletGuestCartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Registry;
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
     * @param CartRepositoryInterface $quoteRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteManagement $quoteManagement
     * @param Registry $registry
     */
    public function __construct(
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Framework\Registry $registry
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteManagement = $quoteManagement;
        $this->registry = $registry;
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
            $quote->save();

            return $this->quoteManagement->placeOrder($quoteIdMask->getQuoteId(), $paymentMethod);
        } finally {
            $this->registry->unregister(Violet::VIOLET_API_CONTEXT_FLAG);
        }
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