<?php

namespace Violet\VioletConnect\Router;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;

/**
 * Custom router that intercepts /.well-known/ucp requests.
 *
 * Registered in etc/frontend/di.xml with a sortOrder before the standard
 * router, so it matches the non-standard URL path before Magento's default
 * frontName/controller/action routing kicks in.
 */
class WellKnownRouter implements RouterInterface
{
    /**
     * @var ActionFactory
     */
    private $actionFactory;

    public function __construct(ActionFactory $actionFactory)
    {
        $this->actionFactory = $actionFactory;
    }

    /**
     * @param RequestInterface $request
     * @return \Magento\Framework\App\ActionInterface|null
     */
    public function match(RequestInterface $request)
    {
        $path = trim($request->getPathInfo(), '/');

        if ($path !== '.well-known/ucp') {
            return null;
        }

        $request->setModuleName('violetconnect');
        $request->setControllerName('wellknown');
        $request->setActionName('ucp');

        return $this->actionFactory->create(
            \Violet\VioletConnect\Controller\WellKnown\Ucp::class
        );
    }
}
