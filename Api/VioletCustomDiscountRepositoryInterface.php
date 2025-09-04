<?php
namespace Violet\VioletConnect\Api;

/**
 * Interface VioletCustomDiscountRepositoryInterface
 */
interface VioletCustomDiscountRepositoryInterface
{
    /**
     * Apply custom discount to guest cart
     * 
     * @param string $cartId
     * @param \Violet\VioletConnect\Model\Data\CustomDiscount $customDiscount
     * @return bool
     */
    public function applyCustomDiscount($cartId, $customDiscount);
}