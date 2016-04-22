<?php

/**
 * Class
 *
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_Creditmemo_View
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */
class TinyBrick_OrderEdit_Block_Adminhtml_Sales_Order_Creditmemo_View
    extends Mage_Adminhtml_Block_Sales_Order_Creditmemo_View
{
    /**
     * Retrieve void url
     *
     * @return string
     */
    public function getRefundUrl()
    {
        return $this->getUrl('*/*/refund', ['creditmemo_id' => $this->getCreditmemo()->getId()]);
    }
}