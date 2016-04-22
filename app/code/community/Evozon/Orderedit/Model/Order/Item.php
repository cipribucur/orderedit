<?php

/**
 * We extended this class so we can add the store id on the product on each order item in the backend
 *
 * @category    Evozon
 * @package     Evozon_TinyBrickOrderEdit_Model_Sales_Order_Item
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */
class TinyBrick_OrderEdit_Model_Order_Item extends Mage_Sales_Model_Order_Item
{

    public $itemInvoiceItem = null;
    public $itemInvoiceParent = null;
    public $itemShipmentItem = null;
    public $itemShipmentParent = null;


    protected function _construct()
    {
        $this->_init('orderedit/order_item');
    }

    /**
     * Retrieve product and add the store id to products to have the propper tax class id
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        if (!$this->getData('product')) {
            if ($this->getStoreId()) {
                $product = Mage::getModel('catalog/product')
                    ->setStoreId($this->getStoreId())
                    ->load($this->getProductId());
                $this->setProduct($product);

                return $this->getData('product');
            }
            $product = Mage::getModel('catalog/product')->load($this->getProductId());
            $this->setProduct($product);
        }

        return $this->getData('product');
    }


    /**
     * @return $this
     * @throws Exception
     */
    public function calcRowTotal()
    {
        $qty = $this->getQtyOrdered();

        if ($this->getParentItem()) {
            $qty = $qty * $this->getParentItem()->getQtyOrdered();
        }

        if ($rowTotal = $this->getRowTotalExcTax()) {
            $baseTotal = $rowTotal;
            $total     = $this->getStore()->convertPrice($baseTotal);
        } else {
            $total     = $this->getCalculationPrice() * $qty;
            $baseTotal = $this->getBaseCalculationPrice() * $qty;
        }

        $this->setRowTotal($this->getStore()->roundPrice($total));
        $this->setBaseRowTotal($this->getStore()->roundPrice($baseTotal));
        return $this;

    }

    /**
     * @return $this
     */
    public function calcTaxAmount()
    {
        $store = $this->getStore();

        if (!Mage::helper('tax')->priceIncludesTax($store)) {
            if (Mage::helper('tax')->applyTaxAfterDiscount($store)) {
                $rowTotal     = $this->getRowTotalWithDiscount();
                $rowBaseTotal = $this->getBaseRowTotalWithDiscount();
            } else {
                $rowTotal     = $this->getRowTotal();
                $rowBaseTotal = $this->getBaseRowTotal();
            }

            $taxPercent = $this->getTaxPercent() / 100;

            $this->setTaxAmount($store->roundPrice($rowTotal * $taxPercent));
            $this->setBaseTaxAmount($store->roundPrice($rowBaseTotal * $taxPercent));

            $rowTotal     = $this->getRowTotal();
            $rowBaseTotal = $this->getBaseRowTotal();
            $this->setTaxBeforeDiscount($store->roundPrice($rowTotal * $taxPercent));
            $this->setBaseTaxBeforeDiscount($store->roundPrice($rowBaseTotal * $taxPercent));
        } else {
            if (Mage::helper('tax')->applyTaxAfterDiscount($store)) {
                $totalBaseTax = $this->getBaseTaxAmount();
                $totalTax     = $this->getTaxAmount();

                if ($totalTax && $totalBaseTax) {
                    $totalTax -= $this->getDiscountAmount() * ($this->getTaxPercent() / 100);
                    $totalBaseTax -= $this->getBaseDiscountAmount() * ($this->getTaxPercent() / 100);

                    $this->setBaseTaxAmount($store->roundPrice($totalBaseTax));
                    $this->setTaxAmount($store->roundPrice($totalTax));
                }
            }
        }

        $this->setAllTax();

        if (Mage::helper('tax')->discountTax($store) && !Mage::helper('tax')->applyTaxAfterDiscount($store)) {
            if ($this->getDiscountPercent()) {
                $baseTaxAmount = $this->getBaseTaxBeforeDiscount();
                $taxAmount     = $this->getTaxBeforeDiscount();

                $baseDiscount = $baseTaxAmount / 100 * $this->getDiscountPercent();
                $discount     = $taxAmount / 100 * $this->getDiscountPercent();

                $this->setDiscountAmount($this->getDiscountAmount() + $discount);
                $this->setBaseDiscountAmount($this->getBaseDiscountAmount() + $baseDiscount);
            }
        }

        return $this;
    }

    public function getStore()
    {
        return $this->getOrder()->getStore();
    }

    public function getCalculationPrice()
    {
        if ($this->getProductType() != 'package') {
            $price = $this->getData('calculation_price');
            if (is_null($price)) {
                if ($this->hasCustomPrice()) {
                    $price = $this->getCustomPrice();
                } else {
                    $price = $this->getOriginalPrice();
                }
                $this->setData('calculation_price', $price);
            }
        } else {
            $price = $this->getPrice();
        }

        return $price;
    }

    public function getBaseCalculationPrice()
    {
        if (!$this->hasBaseCalculationPrice()) {
            if ($this->hasCustomPrice()) {
                if ($price = (float) $this->getCustomPrice()) {
                    $rate  = $this->getStore()->convertPrice($price) / $price;
                    $price = $price / $rate;
                } else {
                    $price = $this->getCustomPrice();
                }
            } else {

                $price = $this->getPrice();
            }
            $this->setBaseCalculationPrice($price);
        }

        return $this->getData('base_calculation_price');
    }

    /**
     * @return float
     */
    public function getTaxChangeAmount()
    {
        return (float) $this->getTaxAmount() - (float) $this->getOrigData('tax_amount');
    }

    /**
     * @return bool
     */
    public function isTaxChanged()
    {
        if ($this->getTaxChangeAmount() == 0) {
            return false;
        }

        return true;
    }

    /**
     * set all missed taxes on the item object
     *
     * @return $this
     */
    public function setAllTax()
    {
        $store = $this->getStore();

        $this->setRowTotalInclTax(
            $store->roundPrice($this->getRowTotal() + $this->getTaxAmount())
        );
        $this->setBaseRowTotalInclTax(
            $store->roundPrice($this->getBaseRowTotal() + $this->getTaxAmount())
        );

        $this->setPriceInclTax(
            $store->roundPrice($this->getPrice() + $this->getTaxPerItem())
        );
        $this->setBasePriceInclTax(
            $store->roundPrice($this->getBasePrice() + $this->getTaxPerItem())
        );

        if ($this->getTaxChangeAmount() > 0) {
            $this->reinvoiceTax();
        } elseif ($this->getTaxChangeAmount() < 0) {
            $this->creditMemoTax();
        }

        return $this;
    }

    /**
     * @return float
     */
    public function getTaxPerItem()
    {
        return $this->getPrice() * $this->getTaxPercent() / 100;
    }

    /**
     *
     */
    public function reinvoiceTax()
    {
        if ($this->getItemInvoice()) {

            $this->setQtyInvoiced(
                $this->getQtyInvoiced() + ($this->getQtyOrdered() - $this->getOrigData('qty_ordered'))
            );
            $this->setTaxInvoiced($this->getTaxAmount());
            $this->setBaseTaxInvoiced($this->getBaseTaxAmount());
            $this->setRowInvoiced($this->getRowTotal());
            $this->setBaseRowInvoiced($this->getBaseRowTotal());

            $invoiceItem = $this->getItemInvoice();
            $invoiceItem->setTotalQty($this->getQtyInvoiced());
            $invoiceItem->setRowTotalInclTax($this->getRowTotalInclTax());
            $invoiceItem->setBaseRowTotalInclTax($this->getBaseRowTotalInclTax());
            $invoiceItem->setTaxAmount($this->getTaxAmount());
            $invoiceItem->setBaseTaxAmount($this->getBaseTaxAmount());
            $invoiceItem->setPriceInclTax($this->getPriceInclTax());
            $invoiceItem->setBasePriceInclTax($this->getBasePriceInclTax());
            $invoiceItem->save();

        }
    }

    /**
     * @return bool
     */
    public function creditMemoTax()
    {
        $order = $this->getOrder();
        $diff  = $this->getTaxChangeAmount();
        //        $refund = $diff * $this->getQtyOrdered();
        $refund = $diff;
        //create order credit memo
        if (!$order->canCreditmemo()) {
            return false;
        }

        $creditmemo = $order->getCreditmemo();
        $creditmemo->setAdjustmentPositive($creditmemo->getAdjustmentPositive() + abs($refund));

        return true;
    }


    /**
     *
     * each order item, if invoiced has a invoiced item, this will return
     *
     * @return mixed
     */
    public function getItemInvoice()
    {
        if ($this->itemInvoiceItem == null) {
            $this->itemInvoiceItem = Mage::getModel('sales/order_invoice_item')->getCollection()->addFieldToFilter(
                'order_item_id', $this->getId()
            )->getFirstItem();
        }

        return $this->itemInvoiceItem;
    }

    /**
     * get invoice of the item
     *
     * @return null
     */
    public function getItemInvoiceParent()
    {
        if ($this->itemInvoiceParent == null) {
            $this->itemInvoiceParent = $this->getOrder()->getInvoice();
        }

        return $this->itemInvoiceParent;
    }

    /**
     * each order item, has a shipment that has a shipment item, this will return
     *
     * @return mixed
     */
    public function getItemShipment()
    {
        if ($this->itemShipmentItem == null) {
            $this->itemShipmentItem = Mage::getModel('sales/order_shipment_item')->getCollection()->addFieldToFilter(
                'order_item_id', $this->getId()
            )->getFirstItem();
        }

        return $this->itemShipmentItem;
    }

    /**
     * returns the shipment item parent
     *
     * @return null
     */
    public function getItemShipmentParent()
    {
        if ($this->itemShipmentParent == null) {
            $this->itemShipmentParent = $this->getOrder()->getShipment();
        }

        return $this->itemShipmentParent;
    }

    /**
     * @return bool
     */
    public function hasShipment()
    {
        if ($this->getItemShipment()) {
            return true;
        }

        return false;
    }

    /**
     * we separate if the case is for a reinvoice or a credit memo
     *
     * @return bool
     */
    public function calcItem()
    {
        if ((float) $this->getPrice() > (float) $this->getOrigData('price')
            or $this->getQtyOrdered() > $this->getOrigData('qty_ordered')
        ) {
            $this->reinvoice();
        } elseif ((float) $this->getPrice() < (float) $this->getOrigData('price')) {
            $this->creditMemo();
        }

        return false;

    }

    /**
     * create a credit memo for the changes done on the item
     * the sum is added as a positive adjustment
     *
     * @return bool
     */
    public function creditMemo()
    {
        $order  = $this->getOrder();
        $diff   = (float) $this->getOrigData('price') - (float) $this->getPrice();
        $refund = $diff * $this->getQtyOrdered();
        //create order credit memo
        if (!$order->canCreditmemo()) {
            return false;
        }

        $creditmemo = $order->getCreditmemo();
        $adjust     = $creditmemo->getAdjustmentPositive() + $refund;
        $creditmemo->setAdjustmentPositive($adjust);

        return true;
    }

    /**
     * modify the invoice item as well
     *
     * @return $this
     */
    public function reinvoice()
    {
        $invoiceItem = $this->getItemInvoice();

        $invoiceItem->setData('qty', $this->getQtyOrdered());
        $invoiceItem->setBaseRowTotal($this->getBaseRowTotal());
        $invoiceItem->setRowTotal($this->getRowTotal());
        $invoiceItem->setPrice($this->getPrice());
        $invoiceItem->setBasePrice($this->getBasePrice());
        $invoiceItem->save();

        $this->setQtyInvoiced($this->getQtyOrdered());
        $this->setRowInvoiced($invoiceItem->getRowTotal());
        $this->setBaseRowInvoiced($invoiceItem->getBaseRowTotal());

        return $this;
    }

    /**
     * Retrieve item qty available for ship
     *
     * @return float|integer
     */
    public function getSimpleQtyToShip()
    {
        $qty = $this->getQtyOrdered()
            - $this->getQtyShipped();
        return max($qty, 0);
    }

    /**
     * remove from order _items
     *
     * @return Mage_Core_Model_Abstract
     * @throws Exception
     */
    public function delete()
    {
        $this->isDeleted(true);
        return parent::delete();
    }

    /**
     * based on the qty, reset all the other values
     * @param int $qty
     * @param int $price
     *
     * @return $this
     */
    public function setAdditionalValues($qty = 0, $price = 0)
    {
        if ($this->isDeleted()) {
            return $this;
        }

        if ($qty == 0) {
            $qty = $this->getQtyOrdered();
        }
        if ($price == 0) {
            $price = $this->getPrice();
        }
        $store = Mage::app()->getStore($this->getOrder()->getStoreId());
        $price = $store->roundPrice($price);
        $this->setPrice($price);
        $this->setQtyOrdered($qty);
        $priceTax = $price + $this->getTaxAmount();
        $this->setBasePrice($price);
        $this->setBaseOriginalPrice($price);
        $this->setOriginalPrice($price);
        $this->setBaseRowTotal($price * $qty);
        $this->setRowTotal($price * $qty);
        $this->setBaseRowTotalInclTax($priceTax * $qty);
        $this->setRowTotalInclTax($priceTax * $qty);
        $this->setRowWeight($this->getWeight() * $qty);
        return $this;
    }

    /**
     * when cancel we have to take in consideration that qty_ordered only decreases in creditmemos
     * but after complete it stays the same, while qty_back_to_stock in both cases increases
     *
     * doing a cancel after complete or while processing have to take in consideration the qty_refunded
     *
     * @return float|int
     */
    public function getCancelBackToStock()
    {
        $qty = $this->getQtyOrdered();
        $absReturned = $this->getQtyBackToStock() - $this->getQtyRefunded();
        $backToStockQty = $qty - $absReturned - $this->getQtyBrocken();

        return $backToStockQty;
    }

    /**
     * @return bool
     */
    public function isStockExceeded()
    {
        if (
            ($this->getQtyOrdered() + $this->getQtyRefunded()) >=
            ($this->getQtyBackToStock() + $this->getQtyBroken())
        ) {
            return false;
        }

        return true;
    }

    /**
     * beforeEdit stock value
     *
     * @return mixed
     */
    public function getStockQty()
    {
        $stock = (
                $this->getQtyOrdered() +
                $this->getQtyRefunded()
            ) -
            (
                $this->getQtyBroken() +
                $this->getQtyBackToStock()
            );

        return $stock;
    }
}