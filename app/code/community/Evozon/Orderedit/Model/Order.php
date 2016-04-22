<?php

/**
 *
 * Mage Core functionalities extended for perfect mermorials
 *
 *
 * @package      Evozon
 * @category     Evozon_Core_Model_Sales_Service_Quote
 * @copyright    Copyright (c) 2013 Perfect Memorials (http://www.perfectmemorials.com)
 * @author       Ciprian Bucur <ciprian.bucur@evozon.com>
 * @author       Bogdan Florian <contact@bogdan-florian.com>
 */
class TinyBrick_OrderEdit_Model_Order extends Mage_Sales_Model_Order
{
    const STATUS_PENDING = 'pending';
    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_PAYMENT_PROCESSING = 'payment_processing';

    const STATUS_PROCESSING = 'processing';
    const STATUS_APPROVED = 'approved';
    const STATUS_COMPLETE = 'complete';

    const STATUS_CLOSED = 'closed';

    const STATUS_CANCEL_TRACKING = 'cancel_tracking';
    const STATUS_PARTIALLY_SHIPPED = 'partially_shipped';


    public $invoice = null;
    public $creditmemo = null;
    public $shipment = null;
    protected $origData = array();

    protected $_addresses = null;
    protected $_nitems;
    protected $_creditmemoData = [];
    protected $_transactionObjects = [];

    /**
     * @return bool
     */
    public function isVirtual()
    {
        $isVirtual  = true;
        $countItems = 0;
        foreach ($this->getItemsCollection() as $_item) {

            if ($_item->isDeleted() || $_item->getParentItemId()) {
                continue;
            }
            $countItems++;
            if (!$_item->getProduct()->getIsVirtual()) {
                $isVirtual = false;
            }
        }

        return $countItems == 0 ? false : $isVirtual;
    }

    /**
     * @return float|mixed
     */
    public function getCustomerTaxClassId()
    {
        /**
         *  tax class can vary at any time. so instead of using the value from session,
         * we need to retrieve from db everytime to get the correct tax class
         */

        $classId = Mage::getModel('customer/group')->getTaxClassId($this->getCustomerGroupId());
        $this->setCustomerTaxClassId($classId);

        return $this->getData('customer_tax_class_id');
    }

    /**
     * Add product to quote
     *
     * return error message if product type instance can't prepare product
     *
     * @param   mixed $product
     *
     * @return  Mage_Sales_Model_Quote_Item || string
     */
    public function addProduct(Mage_Catalog_Model_Product $product, $request = null)
    {

        if ($request === null) {
            $request = 1;
        }
        if (is_numeric($request)) {
            $request = new Varien_Object(array('qty' => $request));
        }
        if (!($request instanceof Varien_Object)) {
            Mage::throwException(Mage::helper('sales')->__('Invalid request for adding product to quote'));
        }

        $cartCandidates = $product->getTypeInstance(true)
            ->prepareForCart($request, $product);

        if (is_string($cartCandidates)) {
            return $cartCandidates;
        }

        /**
         * If prepare process return one object
         */
        if (!is_array($cartCandidates)) {
            $cartCandidates = array($cartCandidates);
        }

        $parentItem = null;
        $errors     = array();
        foreach ($cartCandidates as $candidate) {
            $item = $this->_addCatalogProduct($candidate, $candidate->getCartQty());

            /**
             * As parent item we should always use the item of first added product
             */
            if (!$parentItem) {
                $parentItem = $item;
            }
            if ($parentItem && $candidate->getParentProductId()) {
                $item->setParentItem($parentItem);
            }

            /**
             * We specify qty after we know about parent (for stock)
             */
            $item->addQty($candidate->getCartQty());

            // collect errors instead of throwing first one
            if ($item->getHasError()) {
                $errors[] = $item->getMessage();
            }
        }
        if (!empty($errors)) {
            Mage::throwException(implode("\n", $errors));
        }

        return $item;
    }

    /**
     * @param array $filterByTypes
     * @param bool  $nonChildrenOnly
     *
     * @return null
     */
    public function getItemsCollection($filterByTypes = array(), $nonChildrenOnly = false)
    {
        if (is_null($this->_items)) {
            $this->_items = Mage::getResourceModel('sales/order_item_collection')
                ->setOrderFilter($this);
            if ($filterByTypes) {
                $this->_items->filterByTypes($filterByTypes);
            }
            if ($nonChildrenOnly) {
                $this->_items->filterByParent();
            }
            if ($this->getId()) {
                foreach ($this->_items as $item) {
                    $item->setOrder($this);
                    /**
                     * we set on every item an object named quote but who has the order object so that
                     * we can edit the shipping methods from the edit order in the admin area
                     */
                    $item->setQuote($this);
                }
            }
        }

        return $this->_items;
    }

    /**
     * Collect totals
     *
     * @return Mage_Sales_Model_Quote
     */
    public function collectTotals()
    {
        Mage::dispatchEvent(
            $this->_eventPrefix . '_collect_totals_before',
            array(
                $this->_eventObject => $this
            )
        );
        $this->setExTotal($this->getGrandTotal());
        $this->setNewPromotion(new Varien_Object());

        $this->setSubtotal(0);
        $this->setBaseSubtotal(0);

        $this->setSubtotalWithDiscount(0);
        $this->setBaseSubtotalWithDiscount(0);

        $this->setGrandTotal(0);
        $this->setBaseGrandTotal(0);
        $ics = 0;

        foreach ($this->getAllAddresses() as $address) {

            $address->setSubtotal(0);
            $address->setStoreId($this->getStoreId());
            $address->setBaseSubtotal(0);

            $address->setSubtotalWithDiscount(0);
            $address->setBaseSubtotalWithDiscount(0);

            $address->setGrandTotal(0);
            $address->setBaseGrandTotal(0);
            if ($ics == 0) {
                // collect totals per address
                $address->collectTotals();
                $ics++;
            }

            $this->setSubtotal((float) $this->getSubtotal() + $address->getSubtotal());
            $this->setBaseSubtotal((float) $this->getBaseSubtotal() + $address->getBaseSubtotal());

            $this->setSubtotalWithDiscount(
                (float) $this->getSubtotalWithDiscount() + $address->getSubtotalWithDiscount()
            );
            $this->setBaseSubtotalWithDiscount(
                (float) $this->getBaseSubtotalWithDiscount() + $address->getBaseSubtotalWithDiscount()
            );

            $this->setGrandTotal((float) $this->getGrandTotal() + $address->getGrandTotal());
            $this->setBaseGrandTotal((float) $this->getBaseGrandTotal() + $address->getBaseGrandTotal());
        }

        $this->addThreadingComment();

        $this->roundTotals();
        $this->calcInclTax();

        Mage::helper('orderedit')->checkQuoteAmount($this, $this->getGrandTotal());
        Mage::helper('orderedit')->checkQuoteAmount($this, $this->getBaseGrandTotal());

        foreach ($this->getAllItems() as $item) {

            if ($item->getParentItem()) {
                continue;
            }

            if (($children = $item->getChildren()) && $item->isShipSeparately()) {
                foreach ($children as $child) {
                    if ($child->getProduct()->getIsVirtual()) {
                        $this->setVirtualItemsQty($this->getVirtualItemsQty() + $child->getQty() * $item->getQty());
                    }
                }
            }

            $this->setItemsCount($this->getItemsCount() + 1);
            $this->setItemsQty((float) $this->getItemsQty() + $item->getQtyOrdered());
        }
        $this->setTotalQtyOrdered($this->getItemsQty());
        $this->setData('trigger_recollect', 0);

        if (!$this->isBeforeCapture()) {
            $this->editDependancies();
        }
        $this->_validateCouponCode();

        return $this;
    }

    public function isBeforeCapture()
    {
        $beforeCaptureStatuses = [
            self::STATUS_PENDING,
            self::STATUS_PENDING_PAYMENT,
            self::STATUS_PAYMENT_PROCESSING,
        ];

        if (in_array($this->getStatus(), $beforeCaptureStatuses)) {
            return true;
        }

        return false;
    }

    /**
     * here we will edit: shipments, invoices, creditmemos
     *
     * @return $this
     */
    public function editDependancies()
    {
        if ($this->getGrandTotalEdit() < 0) {
            $this->saveCreditmemo();
        }

        if ($this->getGrandTotalEdit() > 0 and $this->getState() != self::STATE_COMPLETE) {
            $this->saveInvoice();
        }

        if ($this->getState() != self::STATE_COMPLETE) {
            $this->saveShipment();
        }

        return $this;
    }

    /**
     * @return $this
     */
    protected function _validateCouponCode()
    {
        $code = $this->_getData('coupon_code');
        if ($code) {
            $addressHasCoupon = false;
            $addresses        = $this->getAllAddresses();
            if (count($addresses) > 0) {
                foreach ($addresses as $address) {
                    if ($address->hasCouponCode()) {
                        $addressHasCoupon = true;
                    }
                }
                if (!$addressHasCoupon) {
                    $this->setCouponCode('');
                }
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getAllAddresses()
    {
        $addresses = array();
        foreach ($this->getAddressesCol() as $address) {
            if (!$address->isDeleted()) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    /**
     * @return null
     */
    public function getAddressesCol()
    {
        $this->_addresses = null;
        if (is_null($this->_addresses)) {
            if (version_compare('1.4.1.0', Mage::getVersion(), '<=')) {
                $this->_addresses = Mage::getResourceModel('orderedit/order_address_collection')
                    ->setOrderFilter($this->getId());
            } else {
                $this->_addresses = Mage::getResourceModel('orderedit/order_address_collection')
                    ->addAttributeToSelect('*')
                    ->setOrderFilter($this->getId());
            }
            if ($this->getId()) {
                foreach ($this->_addresses as $address) {
                    $address->setOrder($this);
                }
            }
        }

        return $this->_addresses;
    }

    /**
     * Get all quote totals (sorted by priority)
     *
     * @return array
     */
    public function getTotals()
    {
        $totals = $this->getShippingAddress()->getTotals();
        foreach ($this->getBillingAddress()->getTotals() as $code => $total) {
            if (isset($totals[$code])) {
                $totals[$code]->setValue($totals[$code]->getValue() + $total->getValue());
            } else {
                $totals[$code] = $total;
            }
        }

        $sortedTotals = array();
        foreach ($this->getBillingAddress()->getTotalModels() as $total) {
            /* @var $total Mage_Sales_Model_Quote_Address_Total_Abstract */
            if (isset($totals[$total->getCode()])) {
                $sortedTotals[$total->getCode()] = $totals[$total->getCode()];
            }
        }

        return $sortedTotals;
    }

    /**
     * @param array $filterByTypes
     * @param bool  $nonChildrenOnly
     *
     * @return null
     */
    public function getItemsCol($filterByTypes = array(), $nonChildrenOnly = false)
    {
        if (is_null($this->_items)) {

            $this->_items = Mage::getModel('orderedit/order_item')->getCollection()->addFieldToFilter(
                'order_id', $this->getId()
            );

            if ($filterByTypes) {
                $this->_items->filterByTypes($filterByTypes);
            }
            if ($nonChildrenOnly) {
                $this->_items->filterByParent();
            }

            if ($this->getId()) {
                foreach ($this->_items as $item) {
                    $item->setOrder($this);
                    /**
                     *  we set on every item an object named quote but who has the order object so that
                     *  we can edit the shipping methods from the edit order in the admin area
                     */
                    $item->setQuote($this);
                }
            }
        }

        return $this->_items;
    }

    /**
     * @return array
     */
    public function getOrderItems()
    {
        $items = array();
        foreach ($this->getItemsCol() as $item) {
            if (!$item->isDeleted()) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param $itemId
     *
     * @return mixed
     */
    public function getNewItemById($itemId)
    {
        return $this->getItemsCol()->getNewItemById($itemId);
    }

    /**
     * Send email with order update information
     *
     * @param bool   $notifyCustomer
     * @param string $comment
     * @param null   $templateMask
     * @param null   $fileAttachements
     * @param array  $vars
     *
     * @return $this
     */
    public function sendOrderUpdateEmail(
        $notifyCustomer = true, $comment = '', $templateMask = null, $fileAttachements = null, $vars = []
    )
    {
        $storeId = $this->getStore()->getId();

        if (!Mage::helper('sales')->canSendOrderCommentEmail($storeId)) {
            return $this;
        }
        // Get the destination email addresses to send copies to
        $copyTo     = $this->_getEmails(self::XML_PATH_UPDATE_EMAIL_COPY_TO);
        $copyMethod = Mage::getStoreConfig(self::XML_PATH_UPDATE_EMAIL_COPY_METHOD, $storeId);
        // Check if at least one recepient is found
        if (!$notifyCustomer && !$copyTo) {
            return $this;
        }


        $templateId = Mage::getStoreConfig(self::XML_PATH_UPDATE_EMAIL_TEMPLATE, $storeId);
        if ($templateMask) {
            $templateId = $templateMask;
        } elseif ($this->getCustomerIsGuest()) {
            $templateId = Mage::getStoreConfig(self::XML_PATH_UPDATE_EMAIL_GUEST_TEMPLATE, $storeId);
        }

        $customerName = $this->getCustomerName();
        if ($this->getCustomerIsGuest()) {
            $customerName = $this->getBillingAddress()->getName();
        }

        $mailer = Mage::getModel('core/email_template_mailer');
        if ($notifyCustomer) {
            $emailInfo = Mage::getModel('core/email_info');
            $emailInfo->addTo($this->getCustomerEmail(), $customerName);
            if ($copyTo && $copyMethod == 'bcc') {
                // Add bcc to customer email
                foreach ($copyTo as $email) {
                    $emailInfo->addBcc($email);
                }
            }
            $mailer->addEmailInfo($emailInfo);
        }

        // Email copies are sent as separated emails if their copy method is
        // 'copy' or a customer should not be notified
        if ($copyTo && ($copyMethod == 'copy' || !$notifyCustomer)) {
            foreach ($copyTo as $email) {
                $emailInfo = Mage::getModel('core/email_info');
                $emailInfo->addTo($email);
                $mailer->addEmailInfo($emailInfo);
            }
        }

        // Set all required params and send emails
        $mailer->setSender(Mage::getStoreConfig(self::XML_PATH_UPDATE_EMAIL_IDENTITY, $storeId));
        $mailer->setStoreId($storeId);
        $mailer->setTemplateId($templateId);
        $csrName = 'Api Comment';
        if ($adminUser = Mage::getSingleton('admin/session')->getUser()) {
            $csrName = $adminUser->getName();
        }

        $mailer->setTemplateParams(
            array(
                'order'   => $this, 'comment' => nl2br($comment),
                'billing' => $this->getBillingAddress(), 'csr' => $csrName,
            ) + $vars
        );
        $mailer->send($fileAttachements);

        return $this;
    }

    /**
     * Retrieve order total due value
     *
     * @return float
     */
    public function getTotalDue()
    {
        $total = ($this->getGrandTotal() - $this->getTotalPaid()) + $this->getTotalRefundedByShipping();
        $total = Mage::app()->getStore($this->getStoreId())->roundPrice($total);

        return max($total, 0);
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     *
     * @return $this
     */
    public function setCreditmemo(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $this->creditmemo = $creditmemo;

        return $this;
    }

    /**
     * @return bool
     */
    public function saveCreditmemo()
    {
        if ($this->getCreditmemo() !== null) {
            $creditmemo = $this->creditmemo;
            $creditmemo->setAdditionalValues()
                ->saveRefundTransactions()
                ->sendEmail(false, 'Refund order');
            $this->addRelatedObject($creditmemo);

            $message = Mage::helper('sales')->__(
                'Refunded amount of %s online.', $creditmemo->getGrandTotal()
            );
            $this->setOrderEditState(self::STATE_PROCESSING, self::STATE_PROCESSING, $message);

        }

        return $this;
    }

    /**
     * @param $orderRefund
     * @param $baseOrderRefund
     *
     * @return $this
     */
    public function setRefundValues($orderRefund, $baseOrderRefund)
    {
        $creditmemo = $this->getCreditmemo();

        $this->setBaseTotalRefunded($baseOrderRefund);
        $this->setTotalRefunded($orderRefund);

        $this->setBaseSubtotalRefunded($this->getBaseSubtotalRefunded() + $creditmemo->getBaseSubtotal());
        $this->setSubtotalRefunded($this->getSubtotalRefunded() + $creditmemo->getSubtotal());

        $this->setBaseTaxRefunded($this->getBaseTaxRefunded() + $creditmemo->getBaseTaxAmount());
        $this->setTaxRefunded($this->getTaxRefunded() + $creditmemo->getTaxAmount());
        $this->setBaseHiddenTaxRefunded($this->getBaseHiddenTaxRefunded() + $creditmemo->getBaseHiddenTaxAmount());
        $this->setHiddenTaxRefunded($this->getHiddenTaxRefunded() + $creditmemo->getHiddenTaxAmount());

        $this->setBaseShippingRefunded($this->getBaseShippingRefunded() + $creditmemo->getBaseShippingAmount());
        $this->setShippingRefunded($this->getShippingRefunded() + $creditmemo->getShippingAmount());

        $this->setBaseShippingTaxRefunded(
            $this->getBaseShippingTaxRefunded() + $creditmemo->getBaseShippingTaxAmount()
        );
        $this->setShippingTaxRefunded($this->getShippingTaxRefunded() + $creditmemo->getShippingTaxAmount());

        $this->setAdjustmentPositive($this->getAdjustmentPositive() + $creditmemo->getAdjustmentPositive());
        $this->setBaseAdjustmentPositive($this->getBaseAdjustmentPositive() + $creditmemo->getBaseAdjustmentPositive());

        $this->setAdjustmentNegative($this->getAdjustmentNegative() + $creditmemo->getAdjustmentNegative());
        $this->setBaseAdjustmentNegative($this->getBaseAdjustmentNegative() + $creditmemo->getBaseAdjustmentNegative());

        $this->setDiscountRefunded($this->getDiscountRefunded() + $creditmemo->getDiscountAmount());
        $this->setBaseDiscountRefunded($this->getBaseDiscountRefunded() + $creditmemo->getBaseDiscountAmount());

        return $this;
    }


    /**
     * @return bool
     */
    public function saveInvoice()
    {
        if ($this->getGrandTotalEdit() > 0) {
            $invoice = Mage::getModel('sales/service_order', $this)->prepareOrdereditInvoice();
            $invoice->setOrder($this)
                ->setAdditionalValues();
            $this->addRelatedObject($invoice);
            $message                    = Mage::helper('sales')->__(
                'Invoice amount of %s :', $invoice->getGrandTotal()
            );
            $this->setOrderEditState(self::STATE_PROCESSING, self::STATE_PROCESSING, $message);
        }

        return $this;
    }

    /**
     * make sure that the order state remains in complete if is the case
     *
     * @param        $state
     * @param bool   $status
     * @param string $comment
     * @param null   $isCustomerNotified
     *
     * @return $this
     */
    public function setOrderEditState($state, $status = false, $comment = '', $isCustomerNotified = null)
    {
        if ($this->getState() != self::STATE_COMPLETE) {
            $this->_setState($state, $status, $comment, $isCustomerNotified, false);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function saveShipment()
    {
        $shipment = $this->getShipment();
        if ($shipment !== null and $this->getGrandTotalEdit() != 0) {
            $shipment->setOrder($this)->setAdditionalValues();
            $this->addRelatedObject($shipment);
        }

        return $this;
    }

    /**
     * round all totals to precision 2
     *
     * @return bool
     */
    public function roundTotals()
    {
        $store = $this->getStore();

        $this->setSubtotal($store->roundPrice($this->getSubtotal()));
        $this->setBaseSubtotal($store->roundPrice($this->getBaseSubtotal()));
        $this->setSubtotalWithDiscount($store->roundPrice($this->getSubtotalWithDiscount()));
        $this->setBaseSubtotalWithDiscount($store->roundPrice($this->getBaseSubtotalWithDiscount()));
        $this->setGrandTotal($store->roundPrice($this->getGrandTotal()));
        $this->setBaseGrandTotal($store->roundPrice($this->getGrandTotal()));

        return $this;
    }

    /**
     *
     * add on the order tax included fields
     *
     * @return bool
     */
    public function calcInclTax()
    {
        $this->setBaseTaxAmount($this->getTaxAmount());
        $this->setSubtotalInclTax($this->getSubtotal() + $this->getTaxAmount());
        $this->setBaseSubtotalInclTax($this->getBaseSubtotal() + $this->getTaxAmount());

        if ($this->getGrandTotalEdit() > 0) {
            $this->setTotalPaid(
                (float) $this->getTotalPaid() + (float) $this->getTaxAmount() - (float) $this->getOrigData('tax_amount')
            );
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function getGrandTotalEdit()
    {
        $grandTotal = $this->getGrandTotal() - $this->getOrigData('grand_total');

        return $grandTotal;
    }

    /**
     * here we establish if we do a reinvoice or we create a credit memo
     *
     * @return string
     */
    public function getThreadingComment()
    {
        $textHelper = Mage::helper('evozon_unirgy_helper');
        /**
         * amount that has been changed on a order edit
         */
        if ($this->getGrandTotalEdit() == 0) {
            return $this;
        }
        $formatedTotalChanged = Mage::helper('core')->formatPrice(abs($this->getGrandTotalEdit()), false);

        if ($this->getGrandTotalEdit() > 0) {
            $comment = '<br/>' . $textHelper->__('Customer Owes') . ": " . $formatedTotalChanged;

            return $comment;
        }

        $comment = '<br/>' . $textHelper->__('Refund Amount') . ": " . $formatedTotalChanged;

        return $comment;

    }

    /**
     * this will return a creditmemo based on the order data
     *
     * @return false|Mage_Core_Model_Abstract|mixed
     */
    public function getCreditmemo()
    {
        if ($this->creditmemo == null) {
            $service          = Mage::getModel('sales/service_order', $this);
            $this->creditmemo = $service->prepareCreditmemo($this->getCreditmemoData(), $this->getOnlyshipping());
        }

        return $this->creditmemo;
    }

    /**
     * here we create a controlled array of the data from the order that will influence the creditmemo
     *
     * @return array
     */
    protected function getCreditmemoData()
    {
        if (empty($this->_creditmemoData)) {
            $this->_creditmemoData = array_merge($this->_creditmemoData, $this->getRefundQtys());
        }

        return $this->_creditmemoData;
    }

    /**
     * if there is no data (qtys) on the form input from order edit, the creditmemo will be created only with the
     * the shipping amount
     *
     * @return bool
     */
    protected function getOnlyshipping()
    {
        if (empty($this->getCreditmemoData())) {
            return true;
        }

        return false;
    }

    /**
     * if the invoice is not set we grab create a new one
     *
     * @return null
     */
    public function getInvoice()
    {
        if ($this->invoice == null) {
            $this->invoice = $this->getInvoiceCollection()->getFirstItem();
        }

        return $this->invoice;
    }

    /**
     * if the shiopment is not set we grab the first item
     * teoretic it should be set by the item shipment parent in the item class
     *
     * @return null
     */
    public function getShipment()
    {
        if ($this->shipment == null) {
            $this->shipment = $this->getShipmentsCollection()->getFirstItem();
        }

        return $this->shipment;
    }

    /**
     * @return bool
     */
    public function addThreadingComment()
    {
        if ($this->getOrigData('grand_total') != $this->getGrandTotal() and $this->hasInvoices()) {
            $user = Mage::getSingleton('admin/session')->getUser();
            // add threading comment with "waiting for credit" flag
            $post["order_id"]  = $this->getId();
            $post["flag"]      = 4;
            $post["parent_id"] = null;
            $post["author"]    = $user->getFirstname() . ' ' . $user->getLastname();
            $post["comment"]   = Mage::helper('evozon_unirgy_helper')->__(
                'The grand total of the order has changed'
            );
            $post["comment"] .= $this->getThreadingComment();
            Mage::getModel('evozon_perfectmemorials/data_threaded')->threadSubmission($post);

        }

        return $this;
    }

    /**
     * @return bool|float
     */
    public function getTotalRefundedByShipping()
    {
        if ($this->getId()) {
            $creditMemos   = Mage::getResourceModel('sales/order_creditmemo_collection')
                ->setOrderFilter($this)->load();
            $totalRefunded = 0;
            foreach ($creditMemos as $creditmemo) {
                $totalRefunded += $creditmemo->getGrandTotal();
            }

            return Mage::app()->getStore($this->getStoreId())->roundPrice($totalRefunded);
        } else {
            return false;
        }
    }

    /**
     * Retrieve order total due value
     *
     * @return float
     */
    public function getBaseTotalDue()
    {
        $total = ($this->getBaseGrandTotal() - $this->getBaseTotalPaid()) + $this->getTotalRefundedByShipping();
        $total = Mage::app()->getStore($this->getStoreId())->roundPrice($total);

        return max($total, 0);
    }


    /**
     * Retrieve order cancel availability
     *
     * @return bool
     */
    public function canCancel()
    {

        $shipment = $this->getShipment();
        if ($shipment and in_array($shipment->getStatus(), $shipment->getAfterDamageStatuses())) {
            /**
             * if the status of the shipment is (Printed, PrintedWarnig or Shipped)
             * you first have to make a creditmemo and the total refunded items + the broken items = ordered items
             * and the refund sum was all given back
             */
            $refundedTotal = 0;
            foreach ($this->getCreditmemosCollection() as $creditMemo) {
                $refundedTotal += $creditMemo->getGrandTotal();
            }

            if ($this->getGrandTotal() > $refundedTotal) {
                $difference = $this->getGrandTotal() - $refundedTotal;
                Mage::throwException(
                    Mage::helper('sales')->__('You need to refund: ' . $difference . ' before cancel.')
                );

                return false;
            }

            foreach ($this->getAllItems() as $item) {
                $refunded = $item->getQtyRefunded();
                $invoiced = $item->getQtyInvoiced();

                if ($invoiced > $refunded) {
                    Mage::throwException(Mage::helper('sales')->__('Some of the products are not refunded.'));

                    return false;
                }
            }
        }

        if ($this->canUnhold()) { // $this->isPaymentReview()
            return false;
        }

        if ($this->isCanceled()) {
            return false;
        }

        if ($this->getActionFlag(self::ACTION_FLAG_CANCEL) === false) {
            return false;
        }

        return true;
    }

    /**
     * Prepare order totals to cancellation
     *
     * @param string $comment
     * @param bool   $graceful
     *
     * @return Mage_Sales_Model_Order
     * @throws Mage_Core_Exception
     */
    public function registerCancellation($comment = '', $graceful = true)
    {
        if ($this->canCancel()) {

            $cancelState  = self::STATE_CANCELED;
            $cancelStatus = self::STATE_CANCELED;
            if ($this->getStatus() == self::STATE_COMPLETE) {
                /**
                 * cancel has been done after the shipping has been sent and traking info sent
                 */
                $cancelStatus = self::STATUS_CANCEL_TRACKING;
            }

            foreach ($this->getAllItems() as $item) {
                $item->cancel();
                $item->setIsCanceled(true);
            }

            $this->setSubtotalCanceled($this->getSubtotal() - $this->getSubtotalInvoiced());
            $this->setBaseSubtotalCanceled($this->getBaseSubtotal() - $this->getBaseSubtotalInvoiced());

            $this->setTaxCanceled($this->getTaxAmount() - $this->getTaxInvoiced());
            $this->setBaseTaxCanceled($this->getBaseTaxAmount() - $this->getBaseTaxInvoiced());

            $this->setShippingCanceled($this->getShippingAmount() - $this->getShippingInvoiced());
            $this->setBaseShippingCanceled($this->getBaseShippingAmount() - $this->getBaseShippingInvoiced());

            $this->setDiscountCanceled(abs($this->getDiscountAmount()) - $this->getDiscountInvoiced());
            $this->setBaseDiscountCanceled(abs($this->getBaseDiscountAmount()) - $this->getBaseDiscountInvoiced());

            $this->setTotalCanceled($this->getGrandTotal() - $this->getTotalPaid());
            $this->setBaseTotalCanceled($this->getBaseGrandTotal() - $this->getBaseTotalPaid());

            $this->_setState($cancelState, $cancelStatus, $comment);
        } elseif (!$graceful) {
            Mage::throwException(Mage::helper('sales')->__('Order does not allow to be canceled.'));
        }

        return $this;
    }

    /**
     * @param string $comment
     *
     * @return Mage_Sales_Model_Order
     */
    public function cancel($comment = '')
    {
        if ($this->canCancel()) {
            /**
             * Revert the inventory based on the pm_bom_related_history
             */
            $this->_getResource()->beginTransaction();
            try {
                if ($this->getShipment()) {
                    /**
                     * cancel Shipment
                     */
                    $this->getShipment()->cancel()->save();

                    $revertStock = Mage::getModel("billofmaterials/revert");
                    $revertStock->revertOrderStock($this);

                    /**
                     * create a full credit memo with what remains on the order
                     */
                    $this->createCancelCreditmemo();
                    /**
                     * cancel invoice
                     */
                    foreach ($this->getInvoiceCollection() as $invoice) {
                        $invoice->cancel()->save();
                    }
                }
                $this->getPayment()->cancel();
                $this->registerCancellation($comment);

                Mage::dispatchEvent('order_cancel_after', ['order' => $this]);

                $this->_getResource()->commit();
            }
            catch (Exception $e) {
                $this->_getResource()->rollBack();
                throw $e;
            }

        }

        return $this;
    }

    /**
     * create's a full creditmemo from the items/shipment left in the current order
     *
     * @return bool
     */
    public function createCancelCreditmemo()
    {

        $service    = Mage::getModel('sales/service_order', $this);
        $creditmemo = $service->prepareCreditmemo();
        /**
         * an orderedit creditmemo reflects on the grand total, so should the cancel one
         */
        $this->setGrandTotal(0);
        $this->setBaseGrandTotal(0);

        foreach ($this->getAllItems() as $item) {
            $item->setIsCancel(true);
        }

        $creditmemo->setCancelValues()->save();
        $creditmemo->saveRefundTransactions()
            ->sendEmail(false, 'Refund on cancel order');

        return $this;
    }

    /**
     * @return bool
     */
    public function isCanceled()
    {
        return ($this->getState() === self::STATE_CANCELED || $this->getState() === self::STATUS_CANCEL_TRACKING);
    }

    protected function _checkState()
    {
        if (!$this->getId()) {
            return $this;
        }

        $userNotification = $this->hasCustomerNoteNotify() ? $this->getCustomerNoteNotify() : null;

        if ($this->getState() == self::STATE_NEW && $this->getIsInProcess()) {
            $this->setState(self::STATE_PROCESSING, true, '', $userNotification);
        }

        return $this;
    }

    /**
     * @param $edits
     *
     * @return $this
     * @throws Exception
     */
    public function edit($edits)
    {
        $this->_getResource()->beginTransaction();
        try {
            $editTypes = ['shipping', 'billing', 'shippingmethod', 'eitems', 'nitems', 'accountinfo'];
            foreach ($edits as $edit) {
                if ($edit['type'] and in_array($edit['type'], $editTypes)) {
                    $model = Mage::getModel('orderedit/edit_updater_type_' . $edit['type']);
                    if (!$changes[] = $model->edit($this, $edit)) {
                        Mage::throwException(
                            "Error updating " . $edit['type']
                            . " .There was an error saving information, please try again."
                        );
                    }
                }
            }
            $this->setOrderedit(true);
            $this->_getResource()->commit();
        }
        catch (Exception $e) {
            $this->_getResource()->rollBack();
            throw $e;
        }

        return $this;
    }
}