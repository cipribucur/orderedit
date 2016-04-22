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
class TinyBrick_OrderEdit_Model_Edit_Updater_Type_Shippingmethod extends TinyBrick_OrderEdit_Model_Edit_Updater_Type_Abstract
{
    const ORDER_EDIT_CUSTOM_CHARGE_NONE = 0;
    const ORDER_EDIT_CUSTOM_CHARGE_FREE = 1;
    const ORDER_EDIT_CUSTOM_CHARGE_NO_CHANGE = 2;
    const ORDER_EDIT_CUSTOM_CHARGE_CUSTOM = 3;

    public function edit(TinyBrick_OrderEdit_Model_Order $order, $data = array())
    {
        $array = array();

        // this are hardcoded as well in shipping-form
        $customMethodTitle = array(
            'udropship_freeground'         => 'Free Ground',
            'udropship_twoday'             => 'Two Day',
            'udropship_standard_overnight' => 'Standard Overnight'
        );
        $oldMethod         = $order->getShippingDescription() . " - $" . ((float) $order->getShippingAmount());
        if ($data['rateid'][0] == 'custom') {
            // update all order fields regarding shipments not only the visible ones
            $order->setShippingMethod($data['customMethod']);
            $order->setShippingDescription(
                'Perfect Memorials Shipping Custom' . " - " . $customMethodTitle[$data['customMethod']]
            );
            if ($data['customCharge'] == 'free') {
                $order->setCustomCharge(self::ORDER_EDIT_CUSTOM_CHARGE_FREE);
                $order->setShippingAmount(0);
                $order->setBaseShippingAmount(0);
            } else {
                if ($data['customCharge'] == 'custom') {
                    $order->setCustomCharge(self::ORDER_EDIT_CUSTOM_CHARGE_CUSTOM);
                    $order->setShippingAmount((float) $data['customPrice']);
                    $order->setBaseShippingAmount((float) $data['customPrice']);
                } else {
                    $order->setCustomCharge(self::ORDER_EDIT_CUSTOM_CHARGE_NO_CHANGE);
                }
            }

        } else {
            // update all order fields regarding shipments not only the visible ones
            $ratesPrice = $this->getShippingRatesPrice($order);
            $order->setCustomCharge(self::ORDER_EDIT_CUSTOM_CHARGE_NONE);
            $order->setShippingMethod($data['rateid'][0]);
            $order->setShippingDescription('Perfect Memorials Shipping - ' . $customMethodTitle[$data['rateid'][0]]);
            $order->setShippingAmount($ratesPrice[$data['rateid'][0]]);
        }
        try {
            $newMethod = $order->getShippingDescription() . " - $" . ((float) $order->getShippingAmount());
            $results   = strcmp($oldMethod, $newMethod);
            if ($results != 0) {
                $comment = "Changed shipping method:<br />";
                $comment .= "Changed FROM: " . $oldMethod . " TO: " . $newMethod . "<br /><br />";

                return $comment;
            }

            return true;
        }
        catch (Exception $e) {
            $array['status'] = 'error';
            $array['msg']    = "Error updating shipping method";

            return false;
        }
    }

    public function getShippingRatesPrice($order)
    {
        $rateCollection = Mage::getModel('orderedit/order_address_rate')
            ->getCollection()
            ->addFieldToFilter('order_id', $order->getId());
        $rates          = array();
        foreach ($rateCollection as $rate) {
            $rates[$rate->getCode()] = $rate->getPrice();
        }

        return $rates;
    }
}