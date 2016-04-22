<?php

/**
 * Observer
 *
 * @category    Evozon
 * @package     TinyBrick_OrderEdit_Model_Observer
 * @author      Ciprian Bucur <ciprian.bucur@evozon.com>
 */
class TinyBrick_OrderEdit_Model_Observer
{

    const ORDER_EDIT_TEMPLATE               = 'sales_email_order_edit_template';
    const ORDER_EDIT_GUEST_TEMPLATE         = 'sales_email_order_edit_guest_template';
    const XML_PATH_EMAIL_IDENTITY           = 'sales_email/order/identity';
    const CONFIG_PATH_STORE_NAME            = 'general/store_information/name';


    /**
     * Substract values /inventory.
     *
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function substractTinyEdit(Varien_Event_Observer $observer)
    {
        $data = $observer->getEvent()->getConfigData();

        /**
         * Configured the quantity to substract.
         */
        $qtyToSubstr = (int)($data["nqty"] - $data["lqty"]);

        /**
         * Initialize container.
         */
        $productId = $data["pid"];
        $items = Mage::helper("billofmaterials")->processArrayRequest(
            array($productId => array("qty" => $qtyToSubstr)), $data["store"]
        );

        /**
         * Register product sale.
         */
        try {
            Mage::getSingleton("cataloginventory/stock")->registerSale($items, $data["store"]);
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'order_edit_stock');
            Mage::throwException('The quantity cannot be subtracted from the stock.');
        }

        return $this;
    }

    /**
     * Revert values /inventory.
     *
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function revertTinyEdit(Varien_Event_Observer $observer)
    {
        $data = $observer->getEvent()->getConfigData();

        /**
         * Configures the quantity to revert.
         */
        $qtyToRev = (int)($data["lqty"] - $data["nqty"]);

        /**
         * Initialize container.
         */
        $productId = $data["pid"];
        $items = Mage::helper("billofmaterials")->processArrayRequest(
            array($productId => array("qty" => $qtyToRev)), $data["store"]
        );

        /**
         * Revert product sale.
         */
        try {
            Mage::getSingleton("cataloginventory/stock")->revertSale($items, $data["store"]);
        } catch (Exception $e) {
            Mage::log($e->getMessage(), null, 'order_edit_stock');
            Mage::throwException('The quantity cannot be added.');
        }

        return $this;
    }

    /**
     * Send a mail to client each time an order has been edited with the invoice attachment
     * @param Varien_Event_Observer $observer
     */
    public function clientMail(Varien_Event_Observer $observer)
    {
        $order   = $observer->getEvent()->getOrder();
        $this->sendEmail($order);
    }


    /**
     * send an email to the client each time the order has been edited
     * @param $order
     *
     * @return $this
     * @throws Exception
     */
    public function sendEmail($order)
    {

        $storeId = $order->getStore()->getId();

        // Start store emulation process
        $appEmulation = Mage::getSingleton('core/app_emulation');
        $initialEnvironment = $appEmulation->startEnvironmentEmulation($storeId);

        try {
            // Retrieve specified view block from appropriate design package (depends on emulated store)
            $paymentBlock = Mage::helper('payment')->getInfoBlock($order->getPayment())
                ->setIsSecureMode(true);
            $paymentBlock->getMethod()->setStore($storeId);
            $paymentBlockHtml = $paymentBlock->toHtml();
        } catch (Exception $exception) {
            // Stop store emulation process
            $appEmulation->stopEnvironmentEmulation($initialEnvironment);
            throw $exception;
        }

        // Stop store emulation process
        $appEmulation->stopEnvironmentEmulation($initialEnvironment);

        // Retrieve corresponding email template id and customer name
        if ($order->getCustomerIsGuest()) {
            $templateId = self::ORDER_EDIT_GUEST_TEMPLATE;
            $customerName = $order->getBillingAddress()->getName();
        } else {
            $templateId = self::ORDER_EDIT_TEMPLATE;
            $customerName = $order->getCustomerName();
        }

        $mailer = Mage::getModel('core/email_template_mailer');
        $emailInfo = Mage::getModel('core/email_info');
        $emailInfo->addTo($order->getCustomerEmail(), $customerName);
        $mailer->addEmailInfo($emailInfo);

        // Set all required params and send emails
        $mailer->setSender(Mage::getStoreConfig(self::XML_PATH_EMAIL_IDENTITY, $storeId));
        $mailer->setStoreId($storeId);
        $mailer->setTemplateId($templateId);
        $mailer->setTemplateParams(array(
                'order'        => $order,
                'billing'      => $order->getBillingAddress(),
                'payment_html' => $paymentBlockHtml
            )
        );
        $mailer->send();

        return $this;
    }
} 