<?php
namespace Violet\VioletConnect\Model\Data;

/**
 * Class CustomDiscount
 */
class CustomDiscount extends \Magento\Framework\DataObject
{
    /**
     * Get discount code (optional for custom discounts)
     * 
     * @return string|null
     */
    public function getCode()
    {
        return $this->getData('code');
    }

    /**
     * Set discount code (optional for custom discounts)
     * 
     * @param string|null $code
     * @return $this
     */
    public function setCode($code)
    {
        return $this->setData('code', $code);
    }

    /**
     * Get discount amount total
     * 
     * @return float
     */
    public function getAmountTotal()
    {
        return (float) $this->getData('amount_total');
    }

    /**
     * Set discount amount total
     * 
     * @param float $amountTotal
     * @return $this
     */
    public function setAmountTotal($amountTotal)
    {
        return $this->setData('amount_total', $amountTotal);
    }

    /**
     * Get discount type
     * 
     * @return string
     */
    public function getType()
    {
        return $this->getData('type');
    }

    /**
     * Set discount type
     * 
     * @param string $type
     * @return $this
     */
    public function setType($type)
    {
        return $this->setData('type', $type);
    }

    /**
     * Get discount status
     * 
     * @return string
     */
    public function getStatus()
    {
        return $this->getData('status');
    }

    /**
     * Set discount status
     * 
     * @param string $status
     * @return $this
     */
    public function setStatus($status)
    {
        return $this->setData('status', $status);
    }
}