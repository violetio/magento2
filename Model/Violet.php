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
     * This payment method is not available for selection in any checkout or admin context.
     * It is only used programmatically by Violet's own API endpoints, which set the payment
     * method directly on the quote object, bypassing this availability check.
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return false;
    }
}
