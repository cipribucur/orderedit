<?php

/**
 * Class
 *
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Model_Paygate_Authorizenet
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */
class TinyBrick_OrderEdit_Model_Paygate_Authorizenet extends Mage_Paygate_Model_Authorizenet
{

    protected $baseAmountToRefund = 0;

    public function getBaseAmountToRefund()
    {
        return $this->baseAmountToRefund;
    }

    protected function setBaseAmountToRefund($amount)
    {
        $this->baseAmountToRefund = $amount;
    }

    protected function validateAmount($payment, $requestedAmount, $capture)
    {
        if ($this->_formatAmount($capture->getAmount() - $payment->getRefunded()) < $requestedAmount
        ) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for refund.'));
        }
    }

    /**
     * Refund the amount with transaction id
     *
     * @param Mage_Payment_Model_Info $payment
     * @param decimal                 $amount
     *
     * @return Mage_Paygate_Model_Authorizenet
     * @throws Mage_Core_Exception
     */
    public function refund(Varien_Object $payment, $requestedAmount, $capture, $creditmemo)
    {
        //        $this->validateAmount($payment, $requestedAmount, $capture);

        $messages     = [];
        $this->setBaseAmountToRefund(0);
        $leftToRefund = $requestedAmount;

        if ($requestedAmount > 0) {
            $amountForRefund = $this->_formatAmount($capture->getAmount());

            $refund = $requestedAmount;
            if ($amountForRefund < $requestedAmount) {
                $refund = $amountForRefund;
            }

            try {
                /**
                 * here we try to create a refund transaction object based on the capabilities of the capture
                 * transaction object that we have for this order
                 */
                $payment->setIsRefunded(false);
                $payment->setSkipTransactionCreation(false);
                $refundTransaction = $this->_refundCaptureTransaction($payment, $refund, $capture);
                $messages[]     = $refundTransaction->getMessage();
                if ($refundTransaction->getIsRefundOk()) {
                    $payment->setIsRefunded(true);
                    $capture->setAmount($refund);
                    $creditmemo->addRefundTransaction($refundTransaction);
                    $leftToRefund = $this->_formatAmount($requestedAmount - $refund);
                }
            }
            catch (Exception $e) {
                $messages[] = $e->getMessage();
            }
        }

        $this->setBaseAmountToRefund($leftToRefund);
        $payment->setSkipTransactionCreation(true);
        return $leftToRefund;
    }

    /**
     * Refund the card transaction through gateway
     *
     * @param Mage_Payment_Model_Info $payment
     * @param Varien_Object           $card
     *
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function _refundCardTransaction($payment, $amount, $card)
    {
        /**
         * Card has last transaction with type "refund" when all captured amount is refunded.
         * Until this moment card has last transaction with type "capture".
         */
        $captureTransactionId     = $card->getLastTransId();
        $captureTransaction       = $payment->getTransaction($captureTransactionId);
        $realCaptureTransactionId = $captureTransaction->getAdditionalInformation($this->_realTransactionIdKey);

        $payment->setAnetTransType(self::REQUEST_TYPE_CREDIT);
        $payment->setXTransId($realCaptureTransactionId);
        $payment->setAmount($amount);

        $request = $this->_buildRequest($payment);
        $request->setXCardNum($card->getCcLast4());
        $result = $this->_postRequest($request);
        switch ($result->getResponseCode()) {
            case self::RESPONSE_CODE_APPROVED:
                if ($result->getResponseReasonCode() == self::RESPONSE_REASON_CODE_APPROVED) {
                    $refundTransactionId           = $result->getTransactionId() . '-refund';
                    $shouldCloseCaptureTransaction = 0;
                    /**
                     * If it is last amount for refund, transaction with type "capture" will be closed
                     * and card will has last transaction with type "refund"
                     */
                    if ($this->_formatAmount($card->getCapturedAmount() - $card->getRefundedAmount()) == $amount) {
                        $card->setLastTransId($refundTransactionId);
                        $shouldCloseCaptureTransaction = 1;
                    }

                    return $this->_addTransaction(
                        $payment,
                        $refundTransactionId,
                        Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND,
                        [
                            'is_transaction_closed'           => 1,
                            'should_close_parent_transaction' => $shouldCloseCaptureTransaction,
                            'parent_transaction_id'           => $captureTransactionId
                        ],
                        [$this->_realTransactionIdKey => $result->getTransactionId()],
                        Mage::helper('paygate')->getTransactionMessage(
                            $payment, self::REQUEST_TYPE_CREDIT, $result->getTransactionId(), $card, $amount
                        )
                    );
                }
                $exceptionMessage = $this->_wrapGatewayError($result->getResponseReasonText());
                break;
            case self::RESPONSE_CODE_DECLINED:
            case self::RESPONSE_CODE_ERROR:
                $exceptionMessage = $this->_wrapGatewayError($result->getResponseReasonText());
                break;
            default:
                $exceptionMessage = Mage::helper('paygate')->__('Payment refunding error.');
                break;
        }

        $exceptionMessage = Mage::helper('paygate')->getTransactionMessage(
            $payment, self::REQUEST_TYPE_CREDIT, $realCaptureTransactionId, $card, $amount, $exceptionMessage
        );
        Mage::throwException($exceptionMessage);
    }

    /**
     * Refund the card transaction through gateway
     *
     * @param Mage_Payment_Model_Info $payment
     * @param Varien_Object           $card
     *
     * @return Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function _refundCaptureTransaction($payment, $amount, $captureTransaction)
    {
        /**
         * Card has last transaction with type "refund" when all captured amount is refunded.
         * Until this moment card has last transaction with type "capture".
         */
        $realCaptureTransactionId = $captureTransaction->getAdditionalInformation($this->_realTransactionIdKey);

        $payment->setAnetTransType(self::REQUEST_TYPE_CREDIT);
        $payment->setXTransId($realCaptureTransactionId);
        $payment->setAmount($amount);

        $request = $this->_buildRequest($payment);
        $card    = $captureTransaction->getInfo()->getCard();
        $request->setXCardNum($card->getCcLast4());
        $request->setXDuplicateWindow(0);
        /**
         * here we get the response from the gateway with our request
         * to refund a particular capture for a given amount
         */
        $result = $this->_postRequest($request);
        switch ($result->getResponseCode()) {
            case self::RESPONSE_CODE_APPROVED:
                if ($result->getResponseReasonCode() == self::RESPONSE_REASON_CODE_APPROVED) {
                    $refundTransactionId           = $result->getTransactionId() . '-refund';
                    $shouldCloseCaptureTransaction = 0;
                    /**
                     * If it is last amount for refund, transaction with type "capture" will be closed
                     * and card will has last transaction with type "refund"
                     */
                    if ($this->_formatAmount($captureTransaction->getAmount() - $captureTransaction->getRefunded())
                        == $amount
                    ) {
                        $card->setLastTransId($refundTransactionId);
                        $shouldCloseCaptureTransaction = 1;
                    }
                    $payment->setSkipTransactionCreation(false);
                    $refundTransaction = $this->_addTransaction(
                        $payment,
                        $refundTransactionId,
                        Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND,
                        [
                            'is_transaction_closed'           => 1,
                            'should_close_parent_transaction' => $shouldCloseCaptureTransaction,
                            'parent_transaction_id'           => $captureTransaction->getTxnId()
                        ],
                        [$this->_realTransactionIdKey => $result->getTransactionId()],
                        Mage::helper('paygate')->getTransactionMessage(
                            $payment, self::REQUEST_TYPE_CREDIT, $result->getTransactionId(), $card, $amount
                        )
                    );
                    if ($refundTransaction instanceof Mage_Sales_Model_Order_Payment_Transaction) {
                        $refundTransaction->setIsRefundOk(true);
                    } else {
                        Mage::throwException('Refund Transaction has not been saved');
                    }

                    return $refundTransaction;
                }
                $exceptionMessage = $this->_wrapGatewayError($result->getResponseReasonText());
                break;
            case self::RESPONSE_CODE_DECLINED:
            case self::RESPONSE_CODE_ERROR:
//                $transactionId = rand(10000, 99999);
//                $refundTransactionId           = $transactionId . '-refund-false';

                $this->_wrapGatewayError($result->getResponseReasonText());
                break;
            default:
                Mage::helper('paygate')->__('Payment refunding error.');
                break;
        }

        $exceptionMessage = Mage::helper('paygate')->getTransactionMessage(
            $payment, self::REQUEST_TYPE_CREDIT, $realCaptureTransactionId, $card, $amount, $exceptionMessage
        );
        Mage::throwException($exceptionMessage);
    }

    /**
     * Send request with new payment to gateway
     *
     * @param Mage_Payment_Model_Info $payment
     * @param decimal                 $amount
     * @param string                  $requestType
     *
     * @return Mage_Paygate_Model_Authorizenet
     * @throws Mage_Core_Exception
     */
    protected function _place($payment, $amount, $requestType)
    {
        $payment->setAnetTransType($requestType);
        $payment->setAmount($amount);
        $request = $this->_buildRequest($payment);
        $result  = $this->_postRequest($request);
        switch ($requestType) {
            case self::REQUEST_TYPE_AUTH_ONLY:
                $newTransactionType      = Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH;
                $defaultExceptionMessage = Mage::helper('paygate')->__('Payment authorization error.');
                break;
            case self::REQUEST_TYPE_AUTH_CAPTURE:
                $newTransactionType      = Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
                $defaultExceptionMessage = Mage::helper('paygate')->__('Payment capturing error.');
                break;
        }

        switch ($result->getResponseCode()) {
            case self::RESPONSE_CODE_APPROVED:
                $card = $this->_registerCard($result, $payment);
                $this->_addTransaction(
                    $payment,
                    $card->getLastTransId(),
                    $newTransactionType,
                    ['is_transaction_closed' => 0],
                    [$this->_realTransactionIdKey => $card->getLastTransId(), 'card' => $card->toArray()],
                    Mage::helper('paygate')->getTransactionMessage(
                        $payment, $requestType, $card->getLastTransId(), $card, $amount
                    )
                );
                if ($requestType == self::REQUEST_TYPE_AUTH_CAPTURE) {
                    $card->setCapturedAmount($card->getProcessedAmount());
                    $this->getCardsStorage($payment)->updateCard($card);
                }

                return $this;
            case self::RESPONSE_CODE_HELD:
                if ($result->getResponseReasonCode() == self::RESPONSE_REASON_CODE_PENDING_REVIEW_AUTHORIZED
                    || $result->getResponseReasonCode() == self::RESPONSE_REASON_CODE_PENDING_REVIEW
                ) {
                    $card = $this->_registerCard($result, $payment);
                    $this->_addTransaction(
                        $payment,
                        $card->getLastTransId(),
                        $newTransactionType,
                        ['is_transaction_closed' => 0],
                        [
                            $this->_realTransactionIdKey => $card->getLastTransId(),
                            $this->_isTransactionFraud   => true,
                            'card'                       => $card->toArray()
                        ],
                        Mage::helper('paygate')->getTransactionMessage(
                            $payment, $requestType, $card->getLastTransId(), $card, $amount
                        )
                    );
                    if ($requestType == self::REQUEST_TYPE_AUTH_CAPTURE) {
                        $card->setCapturedAmount($card->getProcessedAmount());
                        $this->getCardsStorage()->updateCard($card);
                    }
                    $payment
                        ->setIsTransactionPending(true)
                        ->setIsFraudDetected(true);

                    return $this;
                }
                if ($result->getResponseReasonCode() == self::RESPONSE_REASON_CODE_PARTIAL_APPROVE) {
                    $checksum = $this->_generateChecksum($request, $this->_partialAuthorizationChecksumDataKeys);
                    $this->_getSession()->setData($this->_partialAuthorizationChecksumSessionKey, $checksum);
                    if ($this->_processPartialAuthorizationResponse($result, $payment)) {
                        return $this;
                    }
                }
                Mage::throwException($defaultExceptionMessage);
            case self::RESPONSE_CODE_DECLINED:
            case self::RESPONSE_CODE_ERROR:
                Mage::throwException($this->_wrapGatewayError($result->getResponseReasonText()));
            default:
                Mage::throwException($defaultExceptionMessage);
        }

        return $this;
    }

    public function canCapture()
    {
        if ($this->_isGatewayActionsLocked($this->getInfoInstance())) {
            return false;
        }
        if ($this->_isPreauthorizeCapture($this->getInfoInstance())) {
            return true;
        }


        return true;
    }

    /**
     * Add payment transaction
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param string                         $transactionId
     * @param string                         $transactionType
     * @param array                          $transactionDetails
     * @param array                          $transactionAdditionalInfo
     *
     * @return null|Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function _addTransaction(
        Mage_Sales_Model_Order_Payment $payment, $transactionId, $transactionType,
        array $transactionDetails = array(), array $transactionAdditionalInfo = array(), $message = false
    )
    {
        $payment->setTransactionId($transactionId);
        foreach ($transactionDetails as $key => $value) {
            $payment->setData($key, $value);
        }
        foreach ($transactionAdditionalInfo as $key => $value) {
            $payment->setTransactionAdditionalInfo($key, $value);
        }
        $transaction = $payment->addTransaction($transactionType, null, false , $message);
        foreach ($transactionDetails as $key => $value) {
            $payment->unsetData($key);
        }
        $payment->unsLastTransId();

        /**
         * It for self using
         */
        $transaction->setMessage($message);

        return $transaction;
    }

    /**
     * Check refund availability
     *
     * @return bool
     */
    public function canRefund()
    {
        if ($this->_isGatewayActionsLocked($this->getInfoInstance())
            || $this->getCardsStorage()->getCardsCount() <= 0
        ) {
            return false;
        }

        return true;
    }
}