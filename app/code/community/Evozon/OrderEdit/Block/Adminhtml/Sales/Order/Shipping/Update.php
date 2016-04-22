<?php
/**
 * Description of Update
 *
 * @author       Bucur Ciprian <ciprian.bucur@evozon.com>
 * @package      Evozon
 * @category     Evozon_TinyBrickOrderEdit_Block_Adminhtml_Sales_Order_Shipping_Update
 * @copyright    Copyright (c) 2013 Perfect Memorials (http://www.perfectmemorials.com)
 */
class TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_Shipping_Update extends Mage_Adminhtml_Block_Sales_Order_Create_Shipping_Method_Form
{

    /**
     * @var array
     */
    public $customCharge
        = [
            'free'     => TinyBrick_OrderEdit_Model_Edit_Updater_Type_Shippingmethod::ORDER_EDIT_CUSTOM_CHARGE_FREE,
            'noChange' => TinyBrick_OrderEdit_Model_Edit_Updater_Type_Shippingmethod::ORDER_EDIT_CUSTOM_CHARGE_NO_CHANGE,
            'custom'   => TinyBrick_OrderEdit_Model_Edit_Updater_Type_Shippingmethod::ORDER_EDIT_CUSTOM_CHARGE_CUSTOM
        ];

    /**
     * @return mixed
     */
    public function getCustomPrice()
    {
        return $this->getOrder()->getShippingAmount();
    }

    /**
     * @param $type
     *
     * @return string
     */
    public function getCustomChargeSelected($type)
    {
        if ($this->getOrder()->getCustomCharge() == $this->customCharge[$type]) {
            return 'selected="selected"';
        } else {
            return '';
        }
    }

    /**
     * @return bool
     */
    public function isCustomCharge()
    {
        if ($this->getOrder()->getCustomCharge() == $this->customCharge['custom']) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $type
     *
     * @return string
     */
    public function getCustomMethodSelected($type)
    {
        if ($this->getOrder()->getShippingMethod() == $type) {
            return 'selected="selected"';
        } else {
            return '';
        }
    }

	public function getOrder()
	{
		$orderId = $this->getRequest()->getParam('order_id');
		$order = Mage::getModel('sales/order')->load($orderId);
		return $order;
	}
	
	public function getOrderStatus(){
		$order = $this->getOrder();
		$status = $order->getStatus();
		return $status;
	}
	
	public function getShippingRateCollection()
	{
		$rateCollection = Mage::getModel('orderedit/order_address_rate')->getCollection()->addFieldToFilter('order_id',$this->getOrder()->getId());
		$sortedRates = array();
		foreach($rateCollection as $rate){
			$sortedRates[$rate->getCarrierTitle()][] = array('rate_id' => $rate->getRateId(),'carrier' => $rate->getCarrier(), 'carrier_title' => $rate->getCarrierTitle(), 'code' => $rate->getCode(), 'method' => $rate->getMethod(), 'method_title' => $rate->getMethodTitle(), 'price' => $rate->getPrice());
		}
		return $sortedRates;
	}
	
	public function getStores()
	{
		return Mage::getModel('storelocator/storeLocator')->getCollection()->addFieldToFilter('status',1)->setOrder('title','asc');
	}
	
	public function getShippingRates()
	{
		$shippingRates = $this->getShippingRateCollection();
		if(count($shippingRates)==0){
			Mage::getModel('orderedit/order_address')->recalculateShippingRates($this->getOrder());
			$shippingRates = $this->getShippingRateCollection();
		}		
		return $shippingRates;
	}
	
	public function getShippingAddressRates($params)
	{
		//Remove any old rates that exist
		$oldRates = Mage::getModel('orderedit/order_address_rate')->getCollection()->addFieldToFilter('order_id',$this->getOrder()->getId());
		foreach($oldRates as $oldRate){$oldRate->delete();}
		
		//Get new rates
		Mage::getModel('orderedit/order_address')->recalculateShippingRates($this->getOrder(),$params);
		$shippingRates = $this->getShippingRateCollection();

		return $shippingRates;
	}
	
	public function getFormattedPrice($price)
	{
        $store = Mage::getModel('core/store')->load($this->getOrder()->getStoreId());
		$formatPrice = Mage::helper('core')->currencyByStore($price, $store);
		return $formatPrice;
	}

    public function getOrderCurrencySymbol()
	{
        $currency = $this->getOrder()->getOrderCurrencyCode();
        return Mage::app()->getLocale()->currency($currency)->getSymbol();
	}
	
}