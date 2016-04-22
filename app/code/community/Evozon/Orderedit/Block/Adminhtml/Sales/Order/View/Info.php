<?php

/**
 * Description of Info
 *
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_View_Info
 * @author         Ciprian Bucur<ciprian.bucur@evozon.com>
 * @copyright   Copyright (c) 2013 Perfect Memorials (http://www.perfectmemorials.com)
 */
class TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_View_Info extends Mage_Adminhtml_Block_Sales_Order_View_Info
{

    public function getFedexAddress()
    {
        $shipping = $this->getOrder()->getShippingAddress();

        $addressValidation = Mage::helper('evozon_perfectmemorials')->getAddressValidation(
            $shipping->getData('street'), $shipping->getRegionId(),
            $shipping->getCity(), $shipping->getPostcode(), $shipping->getCountryId()
        );

        return $addressValidation;
    }

    public function getSuggestedFedexAddress()
    {
        return $this->getFedexAddress();
    }

    public function hasDhlPromotion()
    {
        return true;
    }

    protected function _toHtml()
    {
        $str = Mage::app()->getFrontController()->getRequest()->getPathInfo();
        if (strpos($str, '/sales_order/view/')) {
            $this->setTemplate('sales/order/view/edit.phtml');
        }
        if (!$this->getTemplate()) {
            return '';
        }
        $html = $this->renderView();

        return $html;
    }

    public function getCountryList()
    {
        $storeId = $this->getOrder()->getStoreId();
        $storeCountries = Mage::getStoreConfig('general/country/allow', $storeId);
        $countryArray   = Mage::getResourceModel('directory/country_collection')
            ->addFieldToFilter('country_id', array('in' => explode(",", $storeCountries)))
            ->toOptionArray();

        return $countryArray;
    }

    public function getStateList()
    {
        $storeId = $this->getOrder()->getStoreId();
        $storeCountries = Mage::getStoreConfig('general/country/allow', $storeId);
        $states = Mage::getResourceModel('directory/region_collection')
            ->addFieldToFilter('country_id', array('in' => explode(",", $storeCountries)))
            ->setOrder('country_id', 'DESC')
            ->setOrder('default_name', 'ASC')
            ->load();
        $states = $states->getData();

        return $states;
    }

}