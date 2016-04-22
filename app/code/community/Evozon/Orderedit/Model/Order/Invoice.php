<?php

/**
 * Class
 *
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Model_Order_Invoice
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */
class TinyBrick_OrderEdit_Model_Order_Invoice
    extends Mage_Sales_Model_Order_Invoice
{
    /**
     * Cancel invoice action
     *
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function cancel()
    {
        $order = $this->getOrder();
        $order->getPayment()->cancelInvoice($this);
        foreach ($this->getAllItems() as $item) {
            $item->cancel();
        }

        /**
         * Unregister order totals only for invoices in state PAID
         */
        $order->setTotalInvoiced($order->getTotalInvoiced() - $this->getGrandTotal());
        $order->setBaseTotalInvoiced($order->getBaseTotalInvoiced() - $this->getBaseGrandTotal());

        $order->setSubtotalInvoiced($order->getSubtotalInvoiced() - $this->getSubtotal());
        $order->setBaseSubtotalInvoiced($order->getBaseSubtotalInvoiced() - $this->getBaseSubtotal());

        $order->setTaxInvoiced($order->getTaxInvoiced() - $this->getTaxAmount());
        $order->setBaseTaxInvoiced($order->getBaseTaxInvoiced() - $this->getBaseTaxAmount());

        $order->setHiddenTaxInvoiced($order->getHiddenTaxInvoiced() - $this->getHiddenTaxAmount());
        $order->setBaseHiddenTaxInvoiced($order->getBaseHiddenTaxInvoiced() - $this->getBaseHiddenTaxAmount());

        $order->setShippingTaxInvoiced($order->getShippingTaxInvoiced() - $this->getShippingTaxAmount());
        $order->setBaseShippingTaxInvoiced($order->getBaseShippingTaxInvoiced() - $this->getBaseShippingTaxAmount());

        $order->setShippingInvoiced($order->getShippingInvoiced() - $this->getShippingAmount());
        $order->setBaseShippingInvoiced($order->getBaseShippingInvoiced() - $this->getBaseShippingAmount());

        $order->setDiscountInvoiced($order->getDiscountInvoiced() - $this->getDiscountAmount());
        $order->setBaseDiscountInvoiced($order->getBaseDiscountInvoiced() - $this->getBaseDiscountAmount());
        $order->setBaseTotalInvoicedCost($order->getBaseTotalInvoicedCost() - $this->getBaseCost());

        $this->setState(self::STATE_CANCELED);

        Mage::dispatchEvent('sales_order_invoice_cancel', array($this->_eventObject => $this));

        return $this;
    }

    /**
     * set the rest of a invoice details taken from the order
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function setAdditionalValues()
    {
        if (!$order = $this->getOrder()) {
            Mage::throwException(Mage::helper('sales')->__('Invoice cannot be updated'));
        }

        $this->setBaseGrandTotal(
            $order->getBaseGrandTotal() - $order->getBaseTotalInvoiced() + $order->getBaseTotalRefunded()
        );
        $this->setGrandTotal(
            $order->getGrandTotal() - $order->getTotalInvoiced() + $order->getTotalRefunded()
        );
        $this->setTaxAmount(
            $order->getTaxAmount() - $order->getTaxInvoiced() + $order->getTaxRefunded()
        );
        $this->setBaseTaxAmount(
            $order->getBaseTaxAmount() - $order->getBaseTaxInvoiced() + $order->getBaseTaxInvoiced()
        );
        $this->setSubtotalInclTax(
            $order->getSubtotalInclTax() - $order->getSubtotalInvoiced() + $order->getSubtotalRefunded() +
            $order->getTaxAmount()
        );
        $this->setBaseSubtotalInclTax(
            $order->getBaseSubtotalInclTax() - $order->getBaseSubtotalInvoiced() + $order->getTaxAmount()
        );
        $this->setSubtotal(
            $order->getSubtotal() - $order->getSubtotalInvoiced() + $order->getSubtotalRefunded()
        );
        $this->setBaseSubtotal(
            $order->getBaseSubtotal() - $order->getBaseSubtotalInvoiced() + $order->getBaseSubtotalRefunded()
        );

        $this->setBaseShippingAmount(
            $order->getBaseShippingAmount() - $order->getBaseShippingInvoiced() + $order->getBaseShippingRefunded()
        );
        $this->setShippingAmount(
            $order->getShippingAmount() - $order->getShippingInvoiced() + $order->getShippingRefunded()
        );
        $this->setShippingTaxAmount(
            $order->getShippingTaxAmount()
        );
        $this->register();

        return $this;
    }
}