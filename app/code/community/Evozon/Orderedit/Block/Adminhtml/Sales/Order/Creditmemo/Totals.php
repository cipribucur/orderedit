<?php

/**
 * Class
 *
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_Creditmemo_Totals
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */
class TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_Creditmemo_Totals
    extends Mage_Adminhtml_Block_Sales_Totals
{
    protected $_creditmemo;

    public function getCreditmemo()
    {
        if ($this->_creditmemo === null) {
            if ($this->hasData('creditmemo')) {
                $this->_creditmemo = $this->_getData('creditmemo');
            } elseif (Mage::registry('current_creditmemo')) {
                $this->_creditmemo = Mage::registry('current_creditmemo');
            } elseif ($this->getParentBlock() && $this->getParentBlock()->getCreditmemo()) {
                $this->_creditmemo = $this->getParentBlock()->getCreditmemo();
            }
        }

        return $this->_creditmemo;
    }

    public function getSource()
    {
        return $this->getCreditmemo();
    }

    /**
     * Initialize creditmemo totals array
     *
     * @return Mage_Sales_Block_Order_Totals
     */
    protected function _initTotals()
    {
        parent::_initTotals();
        $this->addTotal(
            new Varien_Object(
                array(
                    'code'       => 'adjustment_positive',
                    'value'      => $this->getSource()->getAdjustmentPositive(),
                    'base_value' => $this->getSource()->getBaseAdjustmentPositive(),
                    'label'      => $this->helper('sales')->__('Adjustment Refund')
                )
            )
        );
        $this->addTotal(
            new Varien_Object(
                array(
                    'code'       => 'adjustment_negative',
                    'value'      => $this->getSource()->getAdjustmentNegative(),
                    'base_value' => $this->getSource()->getBaseAdjustmentNegative(),
                    'label'      => $this->helper('sales')->__('Adjustment Fee')
                )
            )
        );
        $this->addTotal(
            new Varien_Object(
                array(
                    'code'  => 'transaction_amount',
                    'value' => $this->getSource()->getTransactionAmount(),
                    'label' => $this->helper('sales')->__('Transaction Amount')
                )
            )
        );
        $leftToRefund = $this->getSource()->getBaseGrandTotal() - $this->getSource()->getTransactionAmount();
        $this->addTotal(
            new Varien_Object(
                array(
                    'code'  => 'left_to_refund',
                    'value' => $leftToRefund,
                    'label' => $this->helper('sales')->__('Left to Refund')
                )
            )
        );

        return $this;
    }
}