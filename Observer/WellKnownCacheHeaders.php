<?php

namespace Violet\VioletConnect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Overrides Magento's default no-cache headers for the /.well-known/ucp response.
 *
 * Magento's page cache system sets Cache-Control: no-store, no-cache on
 * non-layout responses. The UCP spec requires Cache-Control: public, max-age=60
 * and explicitly forbids no-store/no-cache. This observer fires on
 * controller_front_send_response_before (after all other processing) to
 * set the correct headers.
 */
class WellKnownCacheHeaders implements ObserverInterface
{
    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $request = $observer->getEvent()->getData('request');
        $response = $observer->getEvent()->getData('response');

        if ($request === null || $response === null) {
            return;
        }

        $path = trim($request->getPathInfo(), '/');
        if ($path !== '.well-known/ucp') {
            return;
        }

        $response->setHeader('Cache-Control', 'public, max-age=60', true);
        $response->setHeader('Pragma', 'cache', true);
        $response->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + 60) . ' GMT', true);
    }
}
