<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Violet\VioletConnect\Model;

/**
 * Violet Payment Model
 */
class Violet extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'violet';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;

    /**
     * Registry flag set by Violet's own API repositories around order placement.
     * The method is only considered available while this flag is set, which keeps
     * it hidden from storefront, admin, and third-party checkout listings while
     * still allowing Violet-originated orders to import the payment.
     */
    const VIOLET_API_CONTEXT_FLAG = 'violet_payment_api_context';

    /**
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (!$this->_registry->registry(self::VIOLET_API_CONTEXT_FLAG)) {
            return false;
        }
        return parent::isAvailable($quote);
    }
}
