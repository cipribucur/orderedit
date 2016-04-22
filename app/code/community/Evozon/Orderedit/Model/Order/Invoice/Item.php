<?php
/**
 * Class
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Model_Order_Invoice_Item
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */

class TinyBrick_OrderEdit_Model_Order_Invoice_Item extends Mage_Sales_Model_Order_Invoice_Item
{
    /**
     * Declare order item instance
     *
     * @param   Mage_Sales_Model_Order_Item $item
     * @return  Mage_Sales_Model_Order_Invoice_Item
     */
    public function setOrderItem(Mage_Sales_Model_Order_Item $item)
    {
        if (is_null($item)) {
            return $this;
        }

        $this->_orderItem = $item;
        $this->setOrderItemId($item->getId());
        return $this;
    }


    /**
     * Cancelling invoice item
     *
     * @return Mage_Sales_Model_Order_Invoice_Item
     */
    public function cancel()
    {
        $orderItem = $this->getOrderItem();

        if (empty($orderItem)) {
            return $this;
        }

        $orderItem->setQtyInvoiced($orderItem->getQtyInvoiced()-$this->getQty());

        $orderItem->setTaxInvoiced($orderItem->getTaxInvoiced()-$this->getTaxAmount());
        $orderItem->setBaseTaxInvoiced($orderItem->getBaseTaxInvoiced()-$this->getBaseTaxAmount());
        $orderItem->setHiddenTaxInvoiced($orderItem->getHiddenTaxInvoiced()-$this->getHiddenTaxAmount());
        $orderItem->setBaseHiddenTaxInvoiced($orderItem->getBaseHiddenTaxInvoiced()-$this->getBaseHiddenTaxAmount());


        $orderItem->setDiscountInvoiced($orderItem->getDiscountInvoiced()-$this->getDiscountAmount());
        $orderItem->setBaseDiscountInvoiced($orderItem->getBaseDiscountInvoiced()-$this->getBaseDiscountAmount());

        $orderItem->setRowInvoiced($orderItem->getRowInvoiced()-$this->getRowTotal());
        $orderItem->setBaseRowInvoiced($orderItem->getBaseRowInvoiced()-$this->getBaseRowTotal());
        return $this;
    }
}