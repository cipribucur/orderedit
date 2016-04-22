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
class TinyBrick_OrderEdit_Helper_Data extends Mage_Core_Helper_Data
{
	const MAXIMUM_AVAILABLE_NUMBER = 99999999;
	const ADJUSTMENT_ADD = 'add';
	const ADJUSTMENT_SUBTRACT = 'subtract';

	public function checkQuoteAmount(Mage_Sales_Model_Order $order, $amount)
	{
		if (!$order->getHasError() && ($amount>=self::MAXIMUM_AVAILABLE_NUMBER)) {
			$order->setHasError(true);
			$order->addMessage(
		    $this->__('Some items have quantities exceeding allowed quantities. Please select a lower quantity to checkout.')
		);
	}
		return $this;
	}

    /**
     * we create a adjustment feed so that it reflects on osCommerce
     * @param Mage_Sales_Model_Order $order
     * @param                        $item
     * @param                        $qty
     * @param                        $state
     *
     * @return bool
     */
    public function stockAdjustment(Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Item $item, $qty, $state)
    {
        if ($qty <= 0) {
            return false;
        }
        $adjustmentItems = [];
        $orderAdjust = new Varien_Object();
        $orderAdjust->setOrder($order);

        $adjustmentItems[] = $this->getAdjustmentItem($item, $qty, $state);

        $orderAdjust->setAdjustmentItems($adjustmentItems);

        try {
            $feed = Mage::getModel('evozon_oscommerce/feed_run_order_adjustment');
            $feed->submitAdjustmentToRemote($orderAdjust);
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return true;
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param                             $qty
     * @param                             $state
     *
     * @return array
     */
    protected function getAdjustmentItem(Mage_Sales_Model_Order_Item $item, $qty, $state)
    {
        return [
            'item' => $item,
            'adjustment_reason' => $state,
            'adjustment_quantity' => $qty
        ];
    }
}

