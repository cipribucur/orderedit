<?php
/**
 * Class
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_View_Tab_Transactions
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */

class TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_View_Tab_Transactions
    extends Mage_Adminhtml_Block_Sales_Order_View_Tab_Transactions
{
    protected function _prepareColumns()
    {
        $this->addColumn('amount', array(
            'header'    => Mage::helper('sales')->__('Amount'),
            'index'     => 'amount',
            'type'      => 'text'
        ));

        $this->addColumn('refunded', array(
            'header'    => Mage::helper('sales')->__('Refunded'),
            'index'     => 'refunded',
            'type'      => 'text'
        ));

        return parent::_prepareColumns();
    }
}