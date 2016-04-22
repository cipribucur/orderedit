<?php
/**
 * Class
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_Payment
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */
class TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_Payment extends Mage_Adminhtml_Block_Template
{
    /**
     * Retrieve required options from parent
     */
    protected function _beforeToHtml()
    {
        if (!$this->getParentBlock()) {
            Mage::throwException(Mage::helper('adminhtml')->__('Invalid parent block for this block'));
        }
        $this->setPayment($this->getParentBlock()->getOrder()->getPayment());
        parent::_beforeToHtml();
    }

    public function setPayment($payment)
    {
        $method = Mage::getModel('paygate/authorizenet');
        $method->setInfoInstance($payment);
        $paymentFormBlock = $this->getLayout()->createBlock('payment/form_cc')
            ->setTemplate('payment/form/cc_invoice.phtml')
            ->setMethod($method);
        $this->setChild('form', $paymentFormBlock);
        $this->setData('payment', $payment);
        $this->setStoreId($payment->getOrder()->getStoreId());
        return $this;
    }

    protected function _toHtml()
    {
        return $this->getChildHtml('form');
    }

}
