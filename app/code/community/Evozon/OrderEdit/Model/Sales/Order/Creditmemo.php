<?php

/**
 * Class
 *
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Model_Sales_Order_Creditmemo
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */
class TinyBrick_OrderEdit_Model_Sales_Order_Creditmemo extends Mage_Sales_Model_Order_Creditmemo
{

    protected $_refundTransactions = [];
    protected $transactionAmount = 0;

    /**
     * Catch the error from transaction but still make the credit memo
     *
     * @return $this
     * @throws ExceptionO
     * @throws Mage_Core_Exception
     */
    public function refund()
    {
        $orderRefund     = Mage::app()->getStore()->roundPrice(
            $this->getOrder()->getTotalRefunded() + $this->getGrandTotal()
        );
        $baseOrderRefund = Mage::app()->getStore()->roundPrice(
            $this->getOrder()->getBaseTotalRefunded() + $this->getBaseGrandTotal()
        );

        if ($baseOrderRefund > Mage::app()->getStore()->roundPrice($this->getOrder()->getBaseTotalPaid())) {

            $baseAvailableRefund = $this->getOrder()->getBaseTotalPaid() - $this->getOrder()->getBaseTotalRefunded();

            Mage::throwException(
                Mage::helper('sales')->__(
                    'Maximum amount available to refund is %s', $this->getOrder()->formatBasePrice($baseAvailableRefund)
                )
            );
        }
        $order = $this->getOrder()->setCreditmemo($this)->setRefundValues($orderRefund, $baseOrderRefund);

        if ($this->getInvoice()) {
            $this->getInvoice()->setIsUsedForRefund(true);
            $this->getInvoice()->setBaseTotalRefunded(
                $this->getInvoice()->getBaseTotalRefunded() + $this->getBaseGrandTotal()
            );
            $this->setInvoiceId($this->getInvoice()->getId());
        }

        if (!$this->getPaymentRefundDisallowed()) {
            try {
                $order->getPayment()->refund($this);
                if ($this->getBaseAmountToRefund() > 0) {
                    $orderFlag = Evozon_Perfectmemorials_Model_Comments::ORDER_FLAG_REFUND_FAILUARE;
                    Mage::helper('evozon_perfectmemorials')->flagOrder(
                        $order->getId(), $orderFlag, 'Refund not finished'
                    );
                }
            }
            catch (Mage_Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        Mage::dispatchEvent('sales_order_creditmemo_refund', [$this->_eventObject => $this]);

        return $this;
    }

    /**
     * subtract the already given amount from the creditmemo grand total
     *
     * @return float
     */
    public function getRefundAmount()
    {
        $refundAmount = $this->getBaseGrandTotal();
        if ($this->getId()) {
            $refundAmount = $refundAmount - $this->getTransactionAmount();
        }

        return $refundAmount;
    }

    /**
     * get the payment amount that has been already given back, if any
     *
     * @return mixed
     */
    public function getTransactionAmount()
    {
        if (!$this->transactionAmount) {
            $this->transactionAmount = Mage::getModel('sales/order_payment_transaction')
                ->getCreditmemoRefundAmount($this);
        }

        return $this->transactionAmount;
    }

    /**
     * @param $transaction
     */
    public function addRefundTransaction($transaction)
    {
        $this->_refundTransactions[] = $transaction;
    }

    /**
     * @return array
     */
    public function getRefundTransactions()
    {
        return $this->_refundTransactions;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function saveRefundTransactions()
    {
        $this->_getResource()->beginTransaction();
        try {
            foreach ($this->getRefundTransactions() as $transaction) {
                $transaction->setDocumentType(
                    TinyBrick_OrderEdit_Model_Sales_Order_Payment_Transaction::SALES_DOCUMENT_CREDITMEMO
                );
                $transaction->setDocumentId(
                    $this->getId()
                );
                $transaction->save();
            }
            $this->_getResource()->commit();
        } catch (Exception $e) {
            $this->_getResource()->rollBack();
            throw $e;
        }

        return $this;
    }

    /**
     * this ar the values for a creditmemo when the order is canceled
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function setCancelValues()
    {
        if (!$order = $this->getOrder()) {
            Mage::throwException(Mage::helper('sales')->__('Creditmemo cannot be updated'));
        }

        $this->setSubtotal($this->getSubtotal() - $order->getBaseTotalRefunded());
        $this->setBaseSubtotal($this->getBaseSubtotal() - $order->getBaseTotalRefunded());
        $this->setGrandTotal($this->getGrandTotal() - $order->getBaseTotalRefunded());
        $this->setBaseGrandTotal($this->getBaseGrandTotal() - $order->getBaseTotalRefunded());

        $this->register();
        $this->setEmailSent(true);
        $this->addComment('Refund on cancel order', false);

        return $this;
    }

    /**
     * on a creditmemo set the order values
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function setAdditionalValues()
    {
        if (!$order = $this->getOrder()) {
            Mage::throwException(Mage::helper('sales')->__('Creditmemo cannot be updated'));
        }
        $additionalFields = [
            'shipping_amount', 'base_shipping_amount', 'base_subtotal_incl_tax',
            'subtotal_incl_tax', 'base_subtotal', 'subtotal', 'discount_amount', 'shipping_incl_tax',
            'base_shipping_incl_tax', 'grand_total', 'base_grand_total'
        ];

        foreach ($additionalFields as $field) {
            /**
             * here we make the difference between the old values and the new values to populate the creditmemo values
             */
            $this->setData($field, $order->getOrigData($field) - $order->getData($field));
        }

        $this->setState(Mage_Sales_Model_Order_Creditmemo::STATE_OPEN);
        $this->setRefundRequested(true);

        $this->register();

        $this->setEmailSent(true);
        $this->addComment('Refund order', false);

        return $this;
    }
}