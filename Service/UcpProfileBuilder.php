<?php

namespace Violet\VioletConnect\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Violet\VioletConnect\Model\VioletEntityFactory;

/**
 * Builds the UCP well-known profile JSON for this merchant.
 *
 * The profile tells AI shopping agents where to find UltraViolet's
 * REST and MCP endpoints for this merchant, and what capabilities
 * are supported.
 */
class UcpProfileBuilder
{
    private const UCP_VERSION = '2026-04-08';
    private const UCP_SPEC_BASE = 'https://ucp.dev/' . self::UCP_VERSION;
    private const CONFIG_PATH_ULTRAVIOLET_URL = 'violet/ucp/ultraviolet_url';

    /**
     * @var VioletEntityFactory
     */
    private $violetEntityFactory;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        VioletEntityFactory $violetEntityFactory,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->violetEntityFactory = $violetEntityFactory;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * Build the UCP profile array.
     *
     * @return array|null Profile data, or null if merchant is not configured.
     */
    public function build(): ?array
    {
        $merchantId = $this->getMerchantId();
        if ($merchantId === null) {
            $this->logger->warning('UCP profile requested but no merchant ID configured');
            return null;
        }

        $ultravioletUrl = $this->getUltravioletUrl();
        if (empty($ultravioletUrl)) {
            $this->logger->warning('UCP profile requested but no UltraViolet URL configured');
            return null;
        }

        $ultravioletUrl = rtrim($ultravioletUrl, '/');
        $restBase = $ultravioletUrl . '/v1/ucp/merchants/' . $merchantId;

        return [
            'ucp' => [
                'version' => self::UCP_VERSION,
                'services' => $this->buildServices($ultravioletUrl, $restBase),
                'capabilities' => $this->buildCapabilities($restBase),
                'payment_handlers' => new \stdClass(),
            ],
            'signing_keys' => [],
        ];
    }

    /**
     * @return array Service entries keyed by service name.
     */
    private function buildServices(string $ultravioletUrl, string $restBase): array
    {
        return [
            'dev.ucp.shopping' => [
                [
                    'version' => self::UCP_VERSION,
                    'spec' => self::UCP_SPEC_BASE . '/specification/overview',
                    'schema' => self::UCP_SPEC_BASE . '/services/shopping/rest.openapi.json',
                    'transport' => 'rest',
                    'endpoint' => $restBase,
                ],
                [
                    'version' => self::UCP_VERSION,
                    'spec' => self::UCP_SPEC_BASE . '/specification/overview',
                    'schema' => self::UCP_SPEC_BASE . '/services/shopping/mcp.openrpc.json',
                    'transport' => 'mcp',
                    'endpoint' => $ultravioletUrl . '/mcp/sse',
                    'config' => [
                        'transport_type' => 'sse',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array Capability entries keyed by capability name.
     */
    private function buildCapabilities(string $restBase): array
    {
        return [
            'dev.ucp.shopping.checkout' => [[
                'version' => self::UCP_VERSION,
                'spec' => self::UCP_SPEC_BASE . '/specification/checkout',
                'schema' => self::UCP_SPEC_BASE . '/schemas/shopping/checkout.json',
            ]],
            'dev.ucp.shopping.catalog.search' => [[
                'version' => self::UCP_VERSION,
                'spec' => self::UCP_SPEC_BASE . '/specification/catalog',
                'schema' => self::UCP_SPEC_BASE . '/schemas/shopping/catalog.json',
            ]],
            'dev.ucp.shopping.checkout.events' => [[
                'version' => self::UCP_VERSION,
                'spec' => self::UCP_SPEC_BASE . '/specification/checkout/events',
                'schema' => self::UCP_SPEC_BASE . '/schemas/shopping/checkout-events.json',
                'config' => [
                    'endpoint_pattern' => $restBase . '/checkout-sessions/{id}/stream',
                    'transport' => 'sse',
                    'description' => 'SSE stream for checkout session events. Connect after '
                        . 'presenting continue_url to receive push notification when payment '
                        . 'completes. Emits: connected, completed, timeout, error events.',
                ],
            ]],
        ];
    }

    /**
     * @return int|null Violet merchant ID from the violetconnect table.
     */
    private function getMerchantId(): ?int
    {
        try {
            $violetEntity = $this->violetEntityFactory->create()->load(1);
            $merchantId = $violetEntity->getMerchantId();
            return $merchantId ? (int) $merchantId : null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to load merchant ID for UCP profile: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return string|null UltraViolet server base URL from admin config.
     */
    private function getUltravioletUrl(): ?string
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_ULTRAVIOLET_URL,
            ScopeInterface::SCOPE_STORE
        );
    }
}
