<?php

namespace Violet\VioletConnect\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Psr\Log\LoggerInterface;

/**
 * Handles the checkout redirect for API-created carts.
 *
 * Accepts a masked quote ID (cart_token), loads the corresponding quote
 * into the customer's checkout session, and redirects to Magento's
 * native checkout page.
 *
 * URL: /ultraviolet/checkout?cart_token={masked_quote_id}
 */
class Index implements ActionInterface
{
    private RequestInterface $request;
    private RedirectFactory $redirectFactory;
    private QuoteIdMaskFactory $quoteIdMaskFactory;
    private CartRepositoryInterface $cartRepository;
    private CheckoutSession $checkoutSession;
    private MessageManagerInterface $messageManager;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        CheckoutSession $checkoutSession,
        MessageManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->redirectFactory = $redirectFactory;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    public function execute()
    {
        $cartToken = $this->request->getParam('cart_token');

        if (empty($cartToken)) {
            $this->logger->warning('UltraViolet checkout redirect: missing cart_token parameter');
            $this->messageManager->addErrorMessage(__('Invalid checkout link.'));
            return $this->redirectFactory->create()->setPath('/');
        }

        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartToken, 'masked_id');
            $quoteId = (int) $quoteIdMask->getQuoteId();

            if ($quoteId === 0) {
                $this->logger->warning('UltraViolet checkout redirect: unknown cart_token', [
                    'cart_token' => $cartToken,
                ]);
                $this->messageManager->addErrorMessage(__('Cart not found or has expired.'));
                return $this->redirectFactory->create()->setPath('checkout/cart');
            }

            $quote = $this->cartRepository->getActive($quoteId);

            if (!$quote->hasItems()) {
                $this->logger->warning('UltraViolet checkout redirect: quote has no items', [
                    'quote_id' => $quoteId,
                ]);
                $this->messageManager->addErrorMessage(__('Cart is empty.'));
                return $this->redirectFactory->create()->setPath('checkout/cart');
            }

            $this->checkoutSession->replaceQuote($quote);

            $ucpSessionId = $this->request->getParam('ucp_session_id');
            if (!empty($ucpSessionId)) {
                $this->checkoutSession->setData('ucp_session_id', $ucpSessionId);
            }

            $this->logger->info('UltraViolet checkout redirect: quote loaded into session', [
                'quote_id' => $quoteId,
                'item_count' => $quote->getItemsCount(),
                'ucp_session_id' => $ucpSessionId,
            ]);

            return $this->redirectFactory->create()->setUrl('/checkout/#payment');
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->logger->warning('UltraViolet checkout redirect: quote not found or inactive', [
                'cart_token' => $cartToken,
                'error' => $e->getMessage(),
            ]);
            $this->messageManager->addErrorMessage(__('Cart is no longer available.'));
            return $this->redirectFactory->create()->setPath('checkout/cart');
        } catch (\Exception $e) {
            $this->logger->error('UltraViolet checkout redirect: unexpected error', [
                'cart_token' => $cartToken,
                'error' => $e->getMessage(),
            ]);
            $this->messageManager->addErrorMessage(__('Something went wrong loading your cart. Please try again.'));
            return $this->redirectFactory->create()->setPath('checkout/cart');
        }
    }
}
