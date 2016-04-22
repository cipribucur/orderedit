<?php
/**
 * Class
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Model_Sales_Order_Payment_Transaction
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */

class TinyBrick_OrderEdit_Model_Sales_Order_Payment_Transaction extends  Mage_Sales_Model_Order_Payment_Transaction
{
    const   SALES_DOCUMENT_CREDITMEMO = 'creditmemo';
    const   SALES_DOCUMENT_INVOICE = 'invoice';

    /**
     * get the amount that has been refunded for a particular creditmemo
     * @param $creditmemo
     *
     * @return int
     */
    public function getCreditmemoRefundAmount($creditmemo)
    {
        $amount = 0;
        $collection = $this->getResourceCollection();
        $collection->getSelect()
            ->columns(
                ['amount' => new Zend_Db_Expr('SUM(amount)')]
            )
            ->where('document_type =?', self::SALES_DOCUMENT_CREDITMEMO)
            ->where('document_id =?', (int) $creditmemo->getId());

//        $petru = $collection->load()->getSelect()->__toString();

        if ($firstItem = $collection->getFirstItem()) {
            $amount = $firstItem->getAmount();
        }

        return $amount;
    }
    /**
     * @return Varien_Object
     */
    public function getInfo()
    {
        $info = $this->getAdditionalInformation();
        $additionalInformation = new Varien_Object();
        $additionalInformation->addData($info);

        if (isset($info['card']) and is_array($info['card'])) {
            $infoCard = new Varien_Object();
            $infoCard->addData($info['card']);
            $additionalInformation->setCard($infoCard);
        }

        return $additionalInformation;
    }
}