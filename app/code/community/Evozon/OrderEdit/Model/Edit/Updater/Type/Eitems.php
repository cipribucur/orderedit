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
class TinyBrick_OrderEdit_Model_Edit_Updater_Type_Eitems extends TinyBrick_OrderEdit_Model_Edit_Updater_Type_Abstract
{
    protected $_refundQtys = [];

    /**
     * @param Mage_Sales_Model_Order $order
     * @param array                  $data
     *
     * @return bool|string
     * @throws Mage_Core_Exception
     */
    public function edit(Mage_Sales_Model_Order $order, $data = [])
    {
        $this->setOrder($order);
        $this->setOrderEdit($data);
        $order->setRefundQtys([]);
        foreach ($data['id'] as $key => $itemId) {
            $item = $order->getItemById($itemId);
            if (is_null($item)) {
                continue;
            }
            if ($data['remove'][$key]) {
                $this->removeItem($item);
            } else {
                $this->setQtyBackToStock($item, $key);
                $this->editItem($item, $key);
            }
        }
        $order->setRefundQtys($this->getRefundQtys());
        $this->setComment("Edited items:<br />" . $this->getComment() . "<br />");

        return $this->getComment();
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     *
     * @throws Mage_Core_Exception
     */
    protected function removeItem(Mage_Sales_Model_Order_Item $item)
    {
        $comment = $this->getComment() . " Removed Item(SKU): " . $item->getSku() . "<br />";
        $this->setComment($comment);
        $order = $this->getOrder();
        $item->isDeleted(true);
        // so that we know what items go's to creditmemo
        $this->setRefundQtys($item);
        //add to inventory the removed qty from order
        $this->processInventorySetup(
            [
                "oid"   => $order->getIncrementId(),
                "lqty"  => $item->getStockQty(),
                "nqty"  => 0,
                "pid"   => $item->getProductId(),
                "store" => $order->getStore(),
                "item"  => $item
            ]
        );
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param                             $key
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function setQtyBackToStock(Mage_Sales_Model_Order_Item $item, $key)
    {
        $data        = $this->getOrderEdit();
        $backToStock = (int) $data['qty_back_to_stock'][$key];

        if ($backToStock <= 0) {
            return $this;
        }

        $oldStock = $item->getStockQty();
        $item->setQtyBackToStock($item->getQtyBackToStock() + $backToStock);
        $newStock = $item->getStockQty();

        if ($item->isStockExceeded()) {
            Mage::throwException('You can only return to stock: ' . $item->getQtyOrdered());
        }

        $this->processInventorySetup(
            [
                "oid"   => $this->getOrder()->getIncrementId(),
                "lqty"  => $oldStock,
                "nqty"  => $newStock,
                "pid"   => $item->getProductId(),
                "store" => $this->getOrder()->getStore(),
                "item"  => $item
            ]
        );

        $comment = $this->getComment() . " Back to stock for (SKU): " . $item->getSku()
            . " qty: " . $backToStock . "<br />";
        $this->setComment($comment);

        /**
         * delete order flag
         */
        Mage::helper('evozon_perfectmemorials')->deleteOrderFlag(
            $this->getOrder()->getId(),
            [Evozon_Perfectmemorials_Model_Comments::ORDER_FLAG_RETURN]
        );

        return $this;
    }

    /**
     * all the items that has been  edited from this order
     *
     * @param Mage_Sales_Model_Order_Item $item
     * @param                             $key
     *
     * @throws Mage_Core_Exception
     */
    protected function editItem(Mage_Sales_Model_Order_Item $item, $key)
    {
        $data = $this->getOrderEdit();
        $broken = (int) $data['qty_broken'][$key];
        $ordered = (int) $data['qty'][$key];
        $this->validateEdit($item, $ordered, $broken);

        $this->setItemValues($item, $key);
        $this->setItemOptions($item, $key);
        $item->setDiscountAmount($data['discount'][$key]);
        $item->setQtyOrdered($ordered);
        $this->setQtyBroken($item, $broken);
        $this->setRefundQtys($item);

        $this->returnToStock($item, $broken);

        $itemWeight = Mage::helper('evozon_perfectmemorials')->generateShippingWeight($item);
        $rowWeight  = $itemWeight * $item->getQtyOrdered();
        $item->setRowWeight($rowWeight);
        $comment = $this->getOrderComment($item);
        $this->setComment($comment);
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param                             $ordered
     * @param                             $broken
     *
     * @return Mage_Sales_Model_Order_Item
     * @throws Mage_Core_Exception
     */
    protected function validateEdit(Mage_Sales_Model_Order_Item $item, $ordered, $broken)
    {
        $difference = $item->getOrigData('qty_ordered') - (int) $ordered;

        if ($broken < 0) {
            Mage::throwException('Broken can not be negative ');
        }

        if ($broken > 0 and $broken > $difference) {
            Mage::throwException('Too many broken.');
        }

        if ( $difference > 0 and
            ($item->getStockQty() - $difference < 0)
        ) {
            Mage::throwException('Exceeded stock to refund.');
        }

        return $item;
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param                             $broken
     *
     * @return Mage_Sales_Model_Order_Item
     * @throws Mage_Core_Exception
     */
    protected function returnToStock(Mage_Sales_Model_Order_Item $item, $broken)
    {
        $difference = (int) $item->getOrigData('qty_ordered') - (int) $item->getQtyOrdered() - $broken;

        if ($difference > 0) {
            // add to stock
            $oldQty = $difference;
            $newQty = 0;
        } else if ($difference < 0) {
            // subtract from stock
            $oldQty = 0;
            $newQty = abs($difference);
        } else {
            return $item;
        }
        //get out from the inventory or add
        $this->processInventorySetup(
            [
                "oid"   => $this->getOrder()->getIncrementId(),
                "lqty"  => $oldQty,
                "nqty"  => $newQty,
                "pid"   => $item->getProductId(),
                "store" => $this->getOrder()->getStore(),
                "item"  => $item
            ]
        );
    }

    /**
     * this is a history comment for each order edit
     *
     * @param Mage_Sales_Model_Order_Item $item
     *
     * @return string
     */
    protected function getOrderComment(Mage_Sales_Model_Order_Item $item)
    {
        $edits      = ['price', 'discount_amount', 'qty', 'qty_broken'];
        $itemIsEdit = false;
        $comment    = $this->getComment();
        foreach ($edits as $edit) {
            if ($item->getData($edit) != $item->getOrigData($edit)) {
                $itemIsEdit = true;
                $comment .= ucfirst($edit) . " FROM: " . $item->getOrigData($edit)
                    . " TO: " . $item->getData($edit) . "<br />";
            }
        }
        if ($itemIsEdit) {
            $comment = "Edited item " . $item->getSku() . ": " . $comment . ' </br>';
        }

        return $comment;
    }

    /**
     * seting the broken qtys on each item to know what has to go back to
     * stock and which are not, and flag those as broken
     *
     * @param Mage_Sales_Model_Order_Item $item
     * @param                             $qtyBroken
     *
     * @return bool|mixed
     */
    protected function setQtyBroken(Mage_Sales_Model_Order_Item $item, $qtyBroken)
    {
        if (empty($qtyBroken)) {
            return false;
        }
        if ($item->getQtyOrdered() < $item->getOrigData('qty_ordered')) {
            $qtyDifference = (int) $item->getOrigData('qty_ordered') - (int) $item->getQtyOrdered();
            $qtyBroken     = min($qtyDifference, $qtyBroken);
            $item->setQtyBroken($item->getQtyBroken() + $qtyBroken);
        }

        return $qtyBroken;
    }

    /**
     * here are the item custom options saved in a request
     *
     * @param $infoBuyRequest
     * @param $newOptions
     *
     * @return array
     */
    protected function _getInfoBuyRequest($infoBuyRequest, $newOptions)
    {
        if (is_array($infoBuyRequest['options'])) {
            foreach ($infoBuyRequest['options'] as $optionId => $optionValue) {
                foreach ($newOptions as $newOption) {
                    if ($newOption['option_id'] == $optionId and $newOption['option_type'] != 'file') {
                        $infoBuyRequest['options'][$optionId] = $newOption['option_value'];
                        break;
                    }
                }
            }

            return $infoBuyRequest;
        }

        return [];
    }

    /**
     * we format the options so that we can easily access it by nr of item (data[id])
     *
     * @param $data
     *
     * @return array|string
     */
    protected function formatOptions($data)
    {
        if (empty($data)) {
            return '';
        }
        $newData = [];
        foreach ($data as $value) {
            if (is_numeric($value['optionId'])) {
                $newData[$value['optionId']] = $value['optionValue'];
            }
        }

        return $newData;
    }

    /**
     * we get the order item serialized product_options and we modfy it with the new edited values
     * and the we set it on the order item back
     *
     * @param Mage_Sales_Model_Order_Item $item
     * @param                             $key
     *
     * @return array
     */
    protected function getNewOptions(Mage_Sales_Model_Order_Item $item, $key)
    {
        $data            = $this->getOrderEdit();
        $superOptions    = $item->getProductOptions();
        $newSuperOptions = $superOptions['options'];
        $options         = $item->getProduct()->getOptions();
        $dataOptFormated = $this->formatOptions($data['option'][$key]);
        foreach ($dataOptFormated as $optionId => $val) {
            $opt = $options[$optionId];
            if (!$opt instanceof Mage_Catalog_Model_Product_Option) {
                continue;
            }
            switch ($opt->getType()) {
                case 'drop_down':
                    $values          = $opt->getValues();
                    $value           = $values[$val];
                    $newSuperOptions = $this->changeProductOptions(
                        $value->getTitle(), $opt->getTitle(), $optionId, $opt->getType(), $value->getOptionTypeId(),
                        $newSuperOptions
                    );
                    break;
                case 'field':
                    $newSuperOptions = $this->changeProductOptions(
                        $val, $opt->getTitle(), $optionId, $opt->getType(), $val, $newSuperOptions
                    );
                    break;
            }
        }

        return $newSuperOptions;
    }

    /**
     * @param $value
     * @param $label
     * @param $optionId
     * @param $optionType
     * @param $optionValue
     * @param $newOptions
     *
     * @return array
     */
    protected function changeProductOptions($value, $label, $optionId, $optionType, $optionValue, $newOptions)
    {
        $arr = [];
        foreach ($newOptions as $key => $exValue) {
            if ($exValue['option_id'] == $optionId) {
                $newOptions[$key]['value']        = $value;
                $newOptions[$key]['print_value']  = $value;
                $newOptions[$key]['option_value'] = $optionValue;
                if (empty($optionValue)) {
                    unset($newOptions[$key]);
                }

                return $newOptions;
            }
        }
        if (!empty($value)) {
            $arr['label']        = $label;
            $arr['value']        = $value;
            $arr['print_value']  = $value;
            $arr['option_id']    = $optionId;
            $arr['option_type']  = $optionType;
            $arr['option_value'] = $optionValue;
            $arr['custom_view']  = 0;
            $newOptions[]        = $arr;
        }

        return $newOptions;
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param                             $data
     * @param                             $key
     *
     * @return mixed
     */
    protected function getVendorId(Mage_Sales_Model_Order_Item $item, $data, $key)
    {
        $vendorId = $item->getUdropshipVendor();
        if (is_array($data['option'][$key])) {
            foreach ($data['option'][$key] as $key => $value) {
                if ($value['optionId'] == 'Vendor') {
                    $vendorId = $value['optionValue'];
                }
            }
        }

        return $vendorId;
    }

    /**
     * Process the inventory data.
     *
     * @param array $data
     *
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function processInventorySetup($data = [])
    {
        $legacyQty = (int) $data["lqty"];
        $newQty    = (int) $data["nqty"];
        $item      = $data["item"];

        if ($newQty !== $legacyQty) {
            if ($newQty < 0) {
                throw new Mage_Core_Exception(
                    Mage::helper("evozon_purchaseorders")->__(
                        "The value cannot be less then 0"
                    )
                );
            }
            if ($newQty > $legacyQty) {
                //subtract by $newQty - $legacyQty
                /**
                 * this is going to create an adjuntment feed for osCommerce
                 */
                Mage::helper('orderedit')->stockAdjustment(
                    $item->getOrder(),
                    $item,
                    ($newQty - $legacyQty),
                    TinyBrick_OrderEdit_Helper_Data::ADJUSTMENT_SUBTRACT
                );
                Mage::dispatchEvent("oe_substract_inventory_event", array("config_data" => $data));

                return true;
            }
            //revert by $legacyQty - $newQty
            /**
             * this is going to create an adjuntment feed for osCommerce
             */
            Mage::helper('orderedit')->stockAdjustment(
                $item->getOrder(),
                $item,
                ($legacyQty - $newQty),
                TinyBrick_OrderEdit_Helper_Data::ADJUSTMENT_ADD
            );
            Mage::dispatchEvent("oe_revert_inventory_event", array("config_data" => $data));
        }

        return true;
    }

    /**
     * @param     Mage_Sales_Model_Order_Item $item
     * @param                                 $key
     * @param int                             $qty
     *
     * @return mixed
     */
    protected function setItemValues(Mage_Sales_Model_Order_Item $item, $key, $qty = 0)
    {
        $data  = $this->getOrderEdit();
        $price = $data['price'][$key];
        if ($qty == 0) {
            $qty = $data['qty'][$key];
        }
        $item->setUdropshipVendor(
            $this->getVendorId($item, $data, $key)
        );
        $item->setAdditionalValues($qty, $price);

        return $item;
    }

    /**
     * this gets all the items that will be on the credit memo
     *
     * @param Mage_Sales_Model_Order_Item $item
     *
     * @return mixed
     */
    public function setRefundQtys(Mage_Sales_Model_Order_Item $item)
    {
        $itemRefundQty = 0;
        // get all the items qtys that will be refunded
        $refundQtys = $this->getRefundQtys();
        if ($item->isDeleted()) {
            //if deleted add full qty
            $itemRefundQty = $item->getQtyOrdered() - $item->getQtyBroken();
        } else {
            //if edit get how many are removed per item
            $difference = $item->getOrigData('qty_ordered') - $item->getQtyOrdered();
            //make sure that there are removed not
            if ($difference > 0) {
                $itemRefundQty = $difference - ($item->getQtyBroken() - $item->getOrigData('qty_broken'));
            }
        }
        if ($itemRefundQty > 0) {
            $refundQtys['qtys'][$item->getId()] = $itemRefundQty;
            $this->_refundQtys                  = $refundQtys;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getRefundQtys()
    {
        if (empty($this->_refundQtys)) {
            $this->_refundQtys = $this->getOrder()->getRefundQtys();
        }

        return $this->_refundQtys;
    }

    /**
     * all the custom options that are set on the item has to be reformated
     *
     * @param $item
     * @param $key
     */
    public function setItemOptions(Mage_Sales_Model_Order_Item $item, $key)
    {
        $data           = $this->getOrderEdit();
        $productOptions = $item->getProductOptions();
        if (isset($data['option'][$key]) and is_array($productOptions['options'])) {
            $newOptions = $this->getNewOptions($item, $key);
        }
        if (!empty($newOptions)) {
            $infoByRequest = $this->_getInfoBuyRequest($productOptions['info_buyRequest'], $newOptions);
            $item->setProductOptions(
                [
                    'info_buyRequest' => $infoByRequest,
                    'options'         => $newOptions
                ]
            );
        }
    }

}