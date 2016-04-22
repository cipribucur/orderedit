<?php

/**
 * Class
 *
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_Invoice_View
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */
class TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_Invoice_View
    extends Mage_Adminhtml_Block_Sales_Order_Invoice_View
{
    public function __construct()
    {
        $this->_objectId   = 'invoice_id';
        $this->_controller = 'sales_order_invoice';
        $this->_mode       = 'view';
        $this->_session    = Mage::getSingleton('admin/session');

        parent::__construct();

        $this->_removeButton('save');
        $this->_removeButton('cancel');
        $this->_removeButton('reset');
        $this->_removeButton('delete');
        $this->_removeButton('capture');
        $this->_removeButton('print');

        if ($this->_isAllowedAction('capture') && $this->getInvoice()->canCapture()) {
            $this->_addButton(
                'capture',
                [
                    'label'   => Mage::helper('sales')->__('Capture'),
                    'class'   => 'save',
                    'onclick' => 'capture(\'' . $this->getCaptureUrl() . '\')'
                ]
            );
        }

    }
}