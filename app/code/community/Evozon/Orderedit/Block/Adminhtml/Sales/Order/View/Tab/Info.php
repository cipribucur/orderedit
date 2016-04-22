<?php

class TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_View_Tab_Info
    extends Evozon_Core_Block_Adminhtml_Sales_Order_View_Tab_Info
{
    protected function _toHtml()
    {
        $str = Mage::app()->getFrontController()->getRequest()->getPathInfo();
        if (strpos($str, '/sales_order/view/')) {
            $this->setTemplate('sales/order/view/tab/info-edit.phtml');
        }

        if ($str == '/admin/sales_order/view/') {
            $this->setTemplate('sales/order/view/tab/info-edit.phtml');
        }
        if (!$this->getTemplate()) {
            return '';
        }
        $html = $this->renderView();

        return $html;
    }

    public function canEditOrder($status)
    {
        if (!Mage::getStoreConfig('toe/orderedit/active')) {
            return false;
        }
        $configStatus = Mage::getStoreConfig('toe/orderedit/statuses');
        $arrStatus    = explode(",", $configStatus);
        if (in_array($status, $arrStatus)) {
            return true;
        }

        return false;
    }

    public function isPromotion()
    {
        $_order               = $this->getOrder();
        $isEnabled            = Mage::getStoreConfig('promo/rate_promotion/enabled', $_order->getStoreId());
        $isRateUnderPromotion = $this->isRateUnderPromotion();

        return ($isEnabled and $isRateUnderPromotion);
    }

    public function isRateUnderPromotion()
    {
        $collection = Mage::getModel('orderedit/order_address_rate')->getCollection()
            ->addFieldToFilter('order_id', $this->getOrder()->getId())
            ->addFieldToFilter('is_promotion', 1);

        return $collection->count();
    }
}