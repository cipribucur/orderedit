<?php
/**
 * TinyBrick Commercial Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the TinyBrick Commercial Extension License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://store.delorumcommerce.com/license/commercial-extension
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@tinybrick.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this package to newer
 * versions in the future. 
 *
 * @category   TinyBrick
 * @package    TinyBrick_OrderEdit
 * @copyright  Copyright (c) 2010 TinyBrick Inc. LLC
 * @license    http://store.delorumcommerce.com/license/commercial-extension
 */
class TinyBrick_OrderEdit_Model_Order_Address_Total_Shipping extends TinyBrick_OrderEdit_Model_Order_Address_Total_Abstract
{
    /**
     * @param TinyBrick_OrderEdit_Model_Order_Address $address
     *
     * @return $this
     */
    public function collect(TinyBrick_OrderEdit_Model_Order_Address $address)
    {
        $this->setAddress($address);
        $this->setOrder($address->getOrder());
        $order = $this->getOrder();
        /**
         * we use orderItems and not getAllItems so that we can have on each item's object the quote
         * object  so that we can edit the shipping methods and use udropship carrier
         */
        $this->setRowWeight();
        $order->setItemQty(0);
        $order->setWeight(0);

        $this->checkPromotion();

        // recalculate all shipping rates after all the changes were made on the order before save
        $address->recalculateShippingRates($order);

        // here is saved, based on the method code the new shipping amount from the shipping rate collection
        $this->setShippingOrderRate();

        $address->setGrandTotal($address->getGrandTotal() + $order->getShippingAmount());
        $address->setBaseGrandTotal($address->getBaseGrandTotal() + $order->getShippingAmount());

        return $this;
    }

    public function fetch(TinyBrick_OrderEdit_Model_Order_Address $address)
    {
        $amount = $address->getOrder()->getShippingAmount();
        if ($amount!=0 || $address->getOrder()->getShippingDescription()) {
            $address->getOrder()->addTotal(array(
                'code'=>$this->getCode(),
                'title'=>Mage::helper('sales')->__('Shipping & Handling').' ('.$address->getOrder()->getShippingDescription().')',
                'value'=>$address->getOrder()->getShippingAmount()
            ));
        }
        return $this;
    }

    public function setRowWeight()
    {
        $items            = $this->getOrder()->getOrderItems();
        $freeAddress      = $this->getOrder()->getFreeShipping();
        $freeMethodWeight = $this->getOrder()->getFreeMethodWeight();
        $this->setAddressWeight(0);

        foreach ($items as $item) {

            // $itemWeight = $item->getWeight();
            // Generate dimensional weight and set the greater one (actual
            // weight or dimensional weight)
            $itemWeight = Mage::helper('evozon_perfectmemorials')->generateShippingWeight($item);

            $rowWeight = $itemWeight * $item->getQtyOrdered();
            $this->setAddressWeight($this->getAddressWeight() + $rowWeight);
            if ($freeAddress || $item->getFreeShipping() === true) {
                $rowWeight = 0;
            } elseif (is_numeric($item->getFreeShipping())) {
                $freeQty   = $item->getFreeShipping();
                $rowWeight = 0;
                if ($item->getQtyOrdered() > $freeQty) {
                    $rowWeight = $itemWeight * ($item->getQtyOrdered() - $freeQty);
                }
            }
            $freeMethodWeight += $rowWeight;
            $item->setRowWeight($rowWeight);
        }

        $this->getOrder()->setFreeMethodWeight($freeMethodWeight);
    }

    /**
     * it saves the new rate on the shipping object
     */
    public function setShippingOrderRate()
    {
        $order    = $this->getOrder();
        $address    = $this->getaddress();

        $allRates = $this->getShippingRates($order->getShippingAddress(), $order->getId());
        if ($order->getShippingMethod() and !$order->getCustomCharge()) {
            foreach ($allRates as $rate) {
                if ($rate->getCode() == $order->getShippingMethod()) {
                    $this->setShippingAmount(
                        $order->getStore()->convertPrice($rate->getPrice(), false)
                    );
                    $rate->setShippingAmount($this->getShippingAmount());
                    $this->calcOrderShipping($rate);
                    break;
                }
            }
        }
        $address->setRates($allRates);
    }

    /**
     * @return bool
     */
    public function checkPromotion()
    {
        $order = $this->getOrder();

        $oldRates = $this->getShippingRates($order->getShippingAddress(), $order->getId());
        $dhlRate  = $this->getRateByMethod($oldRates, 'dhl_express');

        $isPromotion = Mage::getStoreConfig('promo/rate_promotion/enabled', $order->getStoreId());
        $promotionSum = Mage::getStoreConfig('promo/rate_promotion/cart_subtotal', $order->getStoreId());


        if ($dhlRate and $isPromotion and
            $this->getAddress()->getSubtotal() < $promotionSum and $dhlRate->getIsPromotion() and
            $order->getShippingMethod() == $dhlRate->getCode()
        ) {
            $method = 'udropship_' . Evozon_Dhl_Helper_Data::MATRIXRATE_DELIVERY_TYPE_FLAT;
            $order->setShippingMethod($method);
            $order->setShippingDescription('Perfect Memorials Shipping - ' . $method);
            $order->setPromotionDhlExpressPopUp(true);
        }

        return true;
    }

    /**
     * @param $rates
     * @param $method
     *
     * @return mixed
     */
    public function getRateByMethod($rates, $method)
    {
        foreach ($rates as $rate) {
            if ($rate->getMethod() == $method) {
                return $rate;
            }
        }

        return null;
    }

    /**
     * here we add to invoice the shipping if it was changed and on shipping as well
     *
     * @param $rate
     */
    public function calcOrderShipping($rate)
    {
        $order = $this->getOrder();

        $order->setBaseShippingAmount($this->getShippingAmount());
        $order->setShippingAmount($this->getShippingAmount());
        $order->setShippingDescription(
            $rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle()
        );

        if ($order->hasShipments()) {
            $this->calcOrderShipments();
        }
    }

    /**
     * get Shipping rates collection
     *
     * @param $shippingAddress
     * @param $orderId
     *
     * @return mixed
     */
    protected function getShippingRates($shippingAddress, $orderId)
    {
        $rates = Mage::getModel('orderedit/order_address_rate')->getCollection()
            ->addFieldToFilter('address_id', $shippingAddress->getId())
            ->addFieldToFilter('order_id', $orderId);

        return $rates;
    }

    /**
     * After the order is edit, if there are shipments, those should be updated as well
     *
     * @return bool
     */
    public function calcOrderShipments()
    {
        $order = $this->getOrder();
        /**
         * if an order has shipments and is obviosly invoiced, we need to put,
         * when edit the shipping methods, the new amount on the already
         * created shipments
         */
        $shipment = $order->getShipment();
        $shipment->setBaseShippingAmount($order->getBaseShippingAmount());
        $shipment->setShippingAmount($order->getShippingAmount());
        $shipment->setUdropshipMethod($order->getShippingMethod());
        $shipment->setUdropshipMethodDescription($order->getShippingDescription());

        return true;
    }
}
