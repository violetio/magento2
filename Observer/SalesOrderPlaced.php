<?php
namespace Violet\VioletConnect\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Violet After Order Placed
 *
 * @copyright  2019 Violet.io, Inc.
 * @since      1.0.3
 */
class SalesOrderPlaced implements ObserverInterface
{
    private $objectManager;
    private $vClient;
    private CheckoutSession $checkoutSession;
    private LoggerInterface $logger;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Violet\VioletConnect\Helper\Client $vClient,
        CheckoutSession $checkoutSession,
        LoggerInterface $logger
    ) {
        $this->objectManager = $objectManager;
        $this->vClient = $vClient;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $order = $observer->getOrder();
            if ($order == null) return;

            $payment = $order->getPayment();

            if ($payment != null && $payment->getMethod() == 'violet') {

                $invoice = $this->objectManager->create('Magento\Sales\Model\Service\InvoiceService')
                ->prepareInvoice($order);// Register as invoice item

                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoice->register();

                $order->setTotalPaid($order->getGrandTotal())
                ->save();
            }

            $orderItems = $order->getAllItems();
            if ($orderItems !== null) {
                foreach ($orderItems as $item) {
                    if ($item !== null && $item->getTypeId() != "configurable") {
                        $this->vClient->productUpdated($item->getSku());
                    }
                }
            }

            // Notify UltraViolet when an order is placed for a UltraViolet-initiated cart
            $ucpSessionId = $this->checkoutSession->getData('ucp_session_id');
            if (!empty($ucpSessionId)) {
                $this->logger->info('UltraViolet: notifying order placed for ucp_session_id=' . $ucpSessionId);
                $this->vClient->notifyOrderPlaced($ucpSessionId);
                $this->checkoutSession->unsetData('ucp_session_id');
            }

        } catch (\Exception $e) {
            $this->logger->error('SalesOrderPlaced observer error: ' . $e->getMessage());
        }
    }
}
