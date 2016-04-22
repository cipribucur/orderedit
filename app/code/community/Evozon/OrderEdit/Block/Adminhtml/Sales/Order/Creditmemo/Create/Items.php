<?php

/**
 * Class
 *
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_Creditmemo_Create_Items
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */
class TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_Creditmemo_Create_Items
    extends Mage_Adminhtml_Block_Sales_Order_Creditmemo_Create_Items
{
    protected function _prepareLayout()
    {
        $onclick = "submitAndReloadArea($('creditmemo_item_container'),'" . $this->getUpdateUrl() . "')";
        $this->setChild(
            'update_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData(
                [
                    'label'   => Mage::helper('sales')->__('Update Qty\'s'),
                    'class'   => 'update-button',
                    'onclick' => $onclick,
                ]
            )
        );

        if ($this->getCreditmemo()->canRefund()) {
            $this->setChild(
                'submit_button',
                $this->getLayout()->createBlock('adminhtml/widget_button')->setData(
                    [
                        'label'   => Mage::helper('sales')->__('Refund'),
                        'class'   => 'save submit-button',
                        'onclick' => 'disableElements(\'submit-button\');submitCreditMemo()',
                    ]
                )
            );
            $this->setChild(
                'submit_offline',
                $this->getLayout()->createBlock('adminhtml/widget_button')->setData(
                    [
                        'label'   => Mage::helper('sales')->__('Refund Offline'),
                        'class'   => 'save submit-button',
                        'onclick' => 'disableElements(\'submit-button\');submitCreditMemoOffline()',
                    ]
                )
            );

        } else {
            $this->setChild(
                'submit_button',
                $this->getLayout()->createBlock('adminhtml/widget_button')->setData(
                    [
                        'label'   => Mage::helper('sales')->__('Refund Offline'),
                        'class'   => 'save submit-button',
                        'onclick' => 'disableElements(\'submit-button\');submitCreditMemoOffline()',
                    ]
                )
            );
        }

        return $this;
    }
}