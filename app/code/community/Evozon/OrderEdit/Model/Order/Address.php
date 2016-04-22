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
class TinyBrick_OrderEdit_Model_Order_Address extends Mage_Sales_Model_Order_Address
{
	const TYPE_BILLING  = 'billing';
    const TYPE_SHIPPING = 'shipping';
    const RATES_FETCH = 1;
    const RATES_RECALCULATE = 2;

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * Quote object
     *
     * @var Mage_Sales_Model_Quote
     */
    protected $_items = null;

    /**
     * Quote object
     *
     * @var Mage_Sales_Model_Quote
     */
    protected $_order = null;

    /**
     * Sales Quote address rates
     *
     * @var Mage_Sales_Model_Quote_Address_Rate
     */
    protected $_rates = null;

    /**
     * Total models array
     *
     * @var array
     */
    protected $_totalModels;

    /**
     * Total data as array
     *
     * @var array
     */
    protected $_totals = array();
 
	
    protected function _beforeSave()
    {
        parent::_beforeSave();
        if ($this->getOrder()) {
            $orderId = $this->getOrder()->getId();
            if ($orderId) {
                $this->setOrderId($orderId);
            }
            else {
                $this->_dataSaveAllowed = false;
            }
        }
        return $this;
    }

    protected function _afterSave()
    {
        parent::_afterSave();
        if (null !== $this->_items) {
            $this->getItemsCollection()->save();
        }
        if (null !== $this->_rates) {
            $this->getShippingRatesCollection()->save();
        }
        return $this;
    }

    public function getOrder()
    {
        return $this->_order;
    }

    public function importCustomerAddress(Mage_Customer_Model_Address $address)
    {
        Mage::helper('core')->copyFieldset('customer_address', 'to_quote_address', $address, $this);
        $this->setEmail($address->hasEmail() ? $address->getEmail() : $address->getCustomer()->getEmail());
        return $this;
    }

    public function importOrderAddress(TinyBrick_OrderEdit_Model_Order_Address $address)
    {
        $this->setAddressType($address->getAddressType())
            ->setCustomerId($address->getCustomerId())
            ->setCustomerAddressId($address->getCustomerAddressId())
            ->setEmail($address->getEmail());

        Mage::helper('core')->copyFieldset('sales_convert_order_address', 'to_quote_address', $address, $this);

        return $this;
    }

    public function getAllItems()
    {
        $orderItems = $this->getOrder()->getAllItems();
        $items = array();

        foreach ($orderItems as $qItem) {
            if ($qItem->isDeleted()) {
                continue;
            }
           	$items[] = $qItem;
        }
		
        return $items;
    }

    public function getAllVisibleItems()
    {
        $items = array();
        foreach ($this->getAllItems() as $item) {
            if (!$item->getParentItemId()) {
                $items[] = $item;
            }
        }
        return $items;
    }

    public function getItemQty($itemId = 0)
    {
        if ($this->hasData('item_qty')) {
            return $this->getData('item_qty');
        }

        $qty = 0;
        if ($itemId == 0) {
            foreach ($this->getAllItems() as $item) {
                $qty += $item->getQty();
            }
        } else {
            if ($item = $this->getItemById($itemId)) {
                $qty = $item->getQty();
            }
        }
        return $qty;
    }

    public function hasItems()
    {
        return sizeof($this->getAllItems())>0;
    }

    public function getItemById($itemId)
    {
        foreach ($this->getOrder()->getItemsCollection() as $item) {
            if ($item->getId()==$itemId) {
                return $item;
            }
        }
        return false;
    }

    public function getItemByOrderItemId($itemId)
    {
        foreach ($this->getItemsCollection() as $item) {
            if ($item->getOrderItemId()==$itemId) {
                return $item;
            }
        }
        return false;
    }

    public function removeItem($itemId)
    {
        if ($item = $this->getItemById($itemId)) {
            $item->isDeleted(true);
        }
        return $this;
    }

    public function addItem(TinyBrick_OrderEdit_Model_Order_Item_Abstract $item, $qty=null)
    {
        if ($item instanceof TinyBrick_OrderEdit_Model_Order_Item) {
            if ($item->getParentItemId()) {
                return $this;
            }
            $addressItem = Mage::getModel('orderedit/order_address_item')
                ->setAddress($this)
                ->importQuoteItem($item);
            $this->getItemsCollection()->addItem($addressItem);

            if ($item->getHasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $addressChildItem = Mage::getModel('orderedit/order_address_item')
                        ->setAddress($this)
                        ->importQuoteItem($child)
                        ->setParentItem($addressItem);
                    $this->getItemsCollection()->addItem($addressChildItem);
                }
            }
        }
        else {
            $addressItem = $item;
            $addressItem->setAddress($this);
            if (!$addressItem->getId()) {
                $this->getItemsCollection()->addItem($addressItem);
            }
        }

        if ($qty) {
            $addressItem->setQty($qty);
        }
        return $this;
    }

    /**
     * Retrieve collection of quote shipping rates
     *
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    public function getShippingRatesCollection()
    {
        return Mage::getModel('orderedit/order_address_rate')->getCollection();
    }

    /**
     * Retrieve all address shipping rates
     *
     * @return array
     */
    public function getAllShippingRates()
    {
        return Mage::getModel('orderedit/order_address_rate')->getCollection();
    }

    /**
     * Retrieve all grouped shipping rates
     *
     * @return array
     */
    public function getGroupedAllShippingRates()
    {
        $rates = array();
        foreach ($this->getShippingRatesCollection() as $rate) {
            if (!$rate->isDeleted() && $rate->getCarrierInstance()) {
                if (!isset($rates[$rate->getCarrier()])) {
                    $rates[$rate->getCarrier()] = array();
                }

                $rates[$rate->getCarrier()][] = $rate;
                $rates[$rate->getCarrier()][0]->carrier_sort_order = $rate->getCarrierInstance()->getSortOrder();
            }
        }
        uasort($rates, array($this, '_sortRates'));
        return $rates;
    }

    /**
     * Sort rates recursive callback
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    protected function _sortRates($a, $b)
    {
        if ((int)$a[0]->carrier_sort_order < (int)$b[0]->carrier_sort_order) {
            return -1;
        }
        elseif ((int)$a[0]->carrier_sort_order > (int)$b[0]->carrier_sort_order) {
            return 1;
        }
        else {
            return 0;
        }
    }

    /**
     * Retrieve shipping rate by identifier
     *
     * @param   int $rateId
     * @return  Mage_Sales_Model_Quote_Address_Rate | false
     */
    public function getShippingRateById($rateId)
    {
        foreach ($this->getShippingRatesCollection() as $rate) {
            if ($rate->getId()==$rateId) {
                return $rate;
            }
        }
        return false;
    }

    /**
     * Retrieve shipping rate by code
     *
     * @param   string $code
     * @return  Mage_Sales_Model_Quote_Address_Rate
     */
    public function getShippingRateByCode($code)
    {
        foreach ($this->getShippingRatesCollection() as $rate) {
            if ($rate->getCode()==$code) {
                return $rate;
            }
        }
        return false;
    }

    /**
     * Mark all shipping rates as deleted
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function removeAllShippingRates()
    {
        foreach ($this->getShippingRatesCollection() as $rate) {
            $rate->isDeleted(true);
        }
        return $this;
    }

    /**
     * Add shipping rate
     *
     * @param Mage_Sales_Model_Quote_Address_Rate $rate
     * @return Mage_Sales_Model_Quote_Address
     */
    public function addShippingRate($rate)
    {
        $rate->setAddress($this);
        $this->getShippingRatesCollection()->addItem($rate);
        return $this;
    }

    public function recalculateShippingRates($order, $params = null)
    {
        if ($order->getIsVirtual()) {
            return $this;
        }

        $this->_order                = $order;
        $this->_quote                = $order;

        $this->_orderWeight          = $order->getWeight();
        $this->_orderShippingAddress = $order->getShippingAddress();

        if (isset($params['shipping'])) {
            $params = Zend_Json::decode($params['shipping']);

            //For temporarily setting items in the order
            $oldStreet    = $this->_orderShippingAddress->getStreet();
            $oldCity      = $this->_orderShippingAddress->setCity();
            $oldRegion    = $this->_orderShippingAddress->setRegion();
            $oldPostcode  = $this->_orderShippingAddress->setPostcode();
            $oldCountryId = $this->_orderShippingAddress->setCountry_id();

            $this->_orderShippingAddress->setStreet($params['street1']);
            $this->_orderShippingAddress->setCity($params['city']);
            $this->_orderShippingAddress->setRegion($params['region']);
            $this->_orderShippingAddress->setPostcode($params['postcode']);
            $this->_orderShippingAddress->setCountry_id($params['country_id']);
        }

        $request = Mage::getModel('shipping/rate_request');
        $request->setAllItems($this->getQuoteAllItems());
        $request->setPromotion($this->getPromotion());
        $request->setDestCountryId($this->_orderShippingAddress->getCountryId());

        $request->setDestRegionId($this->_orderShippingAddress->getRegionId());
        $request->setDestRegionCode($this->_orderShippingAddress->getRegionCode());
        /**
         * need to call getStreet with -1
         * to get data in string instead of array
         */
        $request->setDestStreet($this->_orderShippingAddress->getStreet(-1));
        $request->setDestCity($this->_orderShippingAddress->getCity());
        $request->setDestPostcode($this->_orderShippingAddress->getPostcode());

        // this is the request send to unirgy protected file to get the rates so we have to add this values
        $request->setPackageWeight((float) $this->_orderWeight);
        $request->setFreeMethodWeight((float) $this->_orderWeight);

        $request->setStoreId($order->getStoreId());

        //as the udropship_shipping_website holds value of CA for Flat and Flat Plus
        //website with value CA must be assigned otherwise the shipping values are not available
        //$request->setWebsiteId(Mage::app()->getStore(true)->getWebsiteId());

        //
        $store = Mage::getModel("core/store")->load($order->getStoreId());
        $request->setWebsiteId($store->getWebsiteId());

        /**
         * todo: cipribucur make this quiker
         */
        $result = Mage::getModel('shipping/shipping')
            ->collectRates($request)
            ->getResult();

        if ($result) {
            //Remove any old rates
            $oldRates = Mage::getModel('orderedit/order_address_rate')->getCollection()->addFieldToFilter(
                'order_id',
                $this->_order->getId()
            );
            foreach ($oldRates as $oldRate) {
                $oldRate->delete();
            }

            //Add new rates
            $shippingRates = $result->getAllRates();

            foreach ($shippingRates as $shippingRate) {
                $rate = Mage::getModel('orderedit/order_address_rate')
                    ->importShippingRate($shippingRate, $this->_order->getId(), $this->_orderShippingAddress->getId());
                $this->addShippingRate($rate);
            }
        }

        if (isset($params['shipping'])) {
            //Set things back to the way they were to be in the order
            $this->_orderShippingAddress->setStreet($oldStreet);
            $this->_orderShippingAddress->setCity($oldCity);
            $this->_orderShippingAddress->setRegion($oldRegion);
            $this->_orderShippingAddress->setPostcode($oldPostcode);
            $this->_orderShippingAddress->setCountry_id($oldCountryId);
        }

        return $this;
    }

    /**
     * Retrieve total models
     *
     * @return array
     */
    public function getTotalModels()
    {
        if (!$this->_totalModels) {
            $totalsConfig = Mage::getConfig()->getNode('global/orderedit/order/totals');

            $models = array();
            foreach ($totalsConfig->children() as $totalCode=>$totalConfig) {
                $sort = Mage::getStoreConfig('sales/totals_sort/'.$totalCode);
                while (isset($models[$sort])) {
                    $sort++;
                }
                $class = $totalConfig->getClassName();

                if ($class && ($model = Mage::getModel($class))) {
                    $models[$sort] = $model->setCode($totalCode);
                }
            }
            
            ksort($models);
            $this->_totalModels = $models;
        }
        
        return $this->_totalModels;
    }

    /**
     * Collect address totals
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function collectTotals()
    {
        // get's all TinyBrick_OrderEdit_Model_Order_Address_Total_* models
        // and calls the collect Method
        $totalModels = $this->getTotalModels();
        foreach ($totalModels as $model) {

            if (is_callable(array($model, 'collect'))) {
                $model->collect($this);
            }
        }
        return $this;
    }

    /**
     * Retrieve totals as array
     *
     * @return array
     */
    public function getTotals()
    {
        foreach ($this->getTotalModels() as $model) {
            if (is_callable(array($model, 'fetch'))) {
                $model->fetch($this);
            }
        }
        return $this->_totals;
    }

    /**
     * Add total data or model
     *
     * @param Mage_Sales_Model_Quote_Total|array $total
     * @return Mage_Sales_Model_Quote_Address
     */
    public function addTotal($total)
    {
        if (is_array($total)) {
            $totalInstance = Mage::getModel('orderedit/order_address_total')
                ->setData($total);
        } elseif ($total instanceof TinyBrick_OrderEdit_Model_Order_Total) {
            $totalInstance = $total;
        }
        $this->_totals[$totalInstance->getCode()] = $totalInstance;
        return $this;
    }

    /**
     * Rewrite clone method
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function __clone()
    {
        $this->setId(null);
    }

    /**
     * Validate minimum amount
     *
     * @return bool
     */
    public function validateMinimumAmount()
    {
        $storeId = $this->getOrder()->getStoreId();
        if (!Mage::getStoreConfigFlag('sales/minimum_order/active', $storeId)) {
            return true;
        }

        $amount = Mage::getStoreConfig('sales/minimum_order/amount', $storeId);
        if ($this->getBaseSubtotalWithDiscount() < $amount) {
            return false;
        }
        return true;
    }

    /**
     * Retrieve applied taxes
     *
     * @return array
     */
    public function getAppliedTaxes()
    {
        return unserialize($this->getData('applied_taxes'));
    }

    /**
     * Set applied taxes
     *
     * @param array $data
     * @return Mage_Sales_Model_Quote_Address
     */
    public function setAppliedTaxes($data)
    {
        return $this->setData('applied_taxes', serialize($data));
    }

    /**
     * Set shipping amount
     *
     * @param float $value
     * @param bool $alreadyExclTax
     * @return Mage_Sales_Model_Quote_Address
     */
    public function setShippingAmount($value, $alreadyExclTax = false)
    {
        if (Mage::helper('tax')->shippingPriceIncludesTax()) {
            $includingTax = Mage::helper('tax')->getShippingPrice($value, true, $this, $this->getOrder()->getCustomerTaxClassId());
            if (!$alreadyExclTax) {
                $value = Mage::helper('tax')->getShippingPrice($value, false, $this, $this->getOrder()->getCustomerTaxClassId());
            }
            $this->setShippingTaxAmount($includingTax - $value);
        }
        return $this->setData('shipping_amount', $value);
    }

    /**
     * Set base shipping amount
     *
     * @param float $value
     * @param bool $alreadyExclTax
     * @return Mage_Sales_Model_Quote_Address
     */
    public function setBaseShippingAmount($value, $alreadyExclTax = false)
    {
        if (Mage::helper('tax')->shippingPriceIncludesTax()) {
            $includingTax = Mage::helper('tax')->getShippingPrice($value, true, $this, $this->getOrder()->getCustomerTaxClassId());
            if (!$alreadyExclTax) {
                $value = Mage::helper('tax')->getShippingPrice($value, false, $this, $this->getOrder()->getCustomerTaxClassId());
            }
            $this->setBaseShippingTaxAmount($includingTax - $value);
        }
        return $this->setData('base_shipping_amount', $value);
    }

    /**
     * @return mixed
     */
    public function getQuote()
    {
        return $this->_quote;
    }

    /**
     * @return array
     */
    public function getQuoteAllItems()
    {
        $orderItems = $this->getQuote()->getAllItems();
        $items      = array();

        foreach ($orderItems as $qItem) {
            if ($qItem->isDeleted()) {
                continue;
            }
            $qItem->setQty((int) $qItem->getQtyOrdered());
            $items[] = $qItem;
        }

        return $items;
    }


    /**
     * @return false|Mage_Core_Model_Abstract
     */
    public function getPromotion()
    {
        $promotion = Mage::getModel('evozon_perfectmemorials/shipping_promotion_simpletoexpress');
        $promotion->setSubtotal(0);
        if ($this->getSubtotal()) {
            $promotion->setSubtotal($this->getSubtotal());
            $promotion->setStoreId($this->getStoreId());
        } elseif ($this->getOrder()) {
            $promotion->setSubtotal($this->getOrder()->getSubtotal());
            $promotion->setStoreId($this->getOrder()->getStoreId());
        }

        return $promotion;
    }

    /**
     * @return array|bool
     * @throws Exception
     * @throws Zend_Validate_Exception
     */
    public function validate()
    {
        $errors = array();
        $this->implodeStreetAddress();
        if (!Zend_Validate::is($this->getFirstname(), 'NotEmpty')) {
            $errors[] = Mage::helper('customer')->__('Please enter the first name.');
        }

        if (!Zend_Validate::is($this->getLastname(), 'NotEmpty')) {
            $errors[] = Mage::helper('customer')->__('Please enter the last name.');
        }

        if (!Zend_Validate::is($this->getStreet(1), 'NotEmpty')) {
            $errors[] = Mage::helper('customer')->__('Please enter the street.');
        }

        if (!Zend_Validate::is($this->getCity(), 'NotEmpty')) {
            $errors[] = Mage::helper('customer')->__('Please enter the city.');
        }

        $_havingOptionalZip = Mage::helper('directory')->getCountriesWithOptionalZip();
        if (!in_array($this->getCountryId(), $_havingOptionalZip)
            && !Zend_Validate::is($this->getPostcode(), 'NotEmpty')
        ) {
            $errors[] = Mage::helper('customer')->__('Please enter the zip/postal code.');
        }

        if (!Zend_Validate::is($this->getCountryId(), 'NotEmpty')) {
            $errors[] = Mage::helper('customer')->__('Please enter the country.');
        }

        if ($this->getCountryModel()->getRegionCollection()->getSize()
            && !Zend_Validate::is($this->getRegionId(), 'NotEmpty')
            && Mage::helper('directory')->isRegionRequired($this->getCountryId())
        ) {
            $errors[] = Mage::helper('customer')->__('Please enter the state/province.');
        }

        if (empty($errors) || $this->getShouldIgnoreValidation()) {
            return true;
        }

        return $errors;
    }
}