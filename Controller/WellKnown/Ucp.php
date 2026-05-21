<?php

namespace Violet\VioletConnect\Controller\WellKnown;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Violet\VioletConnect\Service\UcpProfileBuilder;

/**
 * Serves the UCP well-known profile at /.well-known/ucp.
 *
 * Returns the merchant's UCP profile JSON so AI shopping agents
 * can discover UltraViolet's endpoints for this store.
 *
 * Uses RawFactory instead of JsonFactory to avoid Magento's default
 * no-cache headers overriding our Cache-Control.
 */
class Ucp implements ActionInterface
{
    private const JSON_OPTIONS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;

    /**
     * @var RawFactory
     */
    private $rawFactory;

    /**
     * @var UcpProfileBuilder
     */
    private $profileBuilder;

    public function __construct(
        RawFactory $rawFactory,
        UcpProfileBuilder $profileBuilder
    ) {
        $this->rawFactory = $rawFactory;
        $this->profileBuilder = $profileBuilder;
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $profile = $this->profileBuilder->build();

        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'application/json', true);
        $result->setHeader('Cache-Control', 'public, max-age=60', true);

        if ($profile === null) {
            $result->setHttpResponseCode(404);
            $result->setContents(json_encode(
                ['error' => 'UCP profile not available. Merchant is not configured.'],
                self::JSON_OPTIONS
            ));
            return $result;
        }

        $result->setHttpResponseCode(200);
        $result->setContents(json_encode($profile, self::JSON_OPTIONS));

        return $result;
    }
}
