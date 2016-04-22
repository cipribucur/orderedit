<?php

/**
 * Mage Core functionalities extended for perfect mermorials
 *
 * @package Evozon
 * @category Evozon_Perfectmemorials_Adminhtml_Sales_Order_CreditmemoController
 * @copyright    Copyright (c) 2013 Perfect Memorials (http://www.perfectmemorials.com)
 * @author Bogdan Florian <contact@bogdan-florian.com>
 * @author Ciprian Bucur <ciprian.bucur@evozon.com>
 */
require_once 'Mage/Adminhtml/controllers/Sales/Order/InvoiceController.php';

class TinyBrick_OrderEdit_Adminhtml_Sales_Order_InvoiceController
    extends Mage_Adminhtml_Sales_Order_InvoiceController
{

    /**
     * Capture invoice action
     */
    public function captureAction()
    {
        if ($invoice = $this->_initInvoice()) {
            try {
                /**
                 * add data from block
                 */
                if ($data = $this->getRequest()->getPost('payment', false)) {
                    $data['method'] = 'authorizenet';
                    $payment = $invoice->getOrder()->getPayment();
                    $payment->importData($data);
                    $payment->placeInvoice($invoice);
                } else {
                    $invoice->capture();
                }
                $this->_saveInvoice($invoice);
                $this->_getSession()->addSuccess($this->__('The invoice has been captured.'));
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError($this->__('Invoice capturing error.'));
            }
            $this->_redirect('*/*/view', array('invoice_id'=>$invoice->getId()));
        } else {
            $this->_forward('noRoute');
        }
    }
}
