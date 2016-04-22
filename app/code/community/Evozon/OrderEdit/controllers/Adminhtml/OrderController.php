<?php

/**
 * Description of OrderController
 *
 * @package      Evozon
 * @category     TinyBrick_OrderEdit_OrderController
 * @copyright    Copyright (c) 2013 Perfect Memorials (http://www.perfectmemorials.com)
 * @author       Bucur Ciprian <ciprian.bucur@evozon.com>
 * @todo         Change the way the order id value is being passed to BoM components.
 * Under session is highly not recommended.
 */
class TinyBrick_OrderEdit_Adminhtml_OrderController extends Mage_Adminhtml_Controller_Action
{

    protected function _isAllowed()
    {
        return true;
    }

    public function editAction()
    {
        $changes   = [];
        $order = $this->_initOrder();
        try {
            $edits = $this->getEdits();
            $order->edit($edits);
            $order->collectTotals();
            $order->save();

            //auth for more if the total has increased and configured to do so
            if ($this->reauthorizeOrder($order)) {
                Mage::throwException("There was an error re-authorizing payment.");
            }
            //fire event and log changes
            Mage::dispatchEvent('orderedit_edit', ['order' => $order]);

            $this->_logChanges(
                $order, $this->getRequest()->getParam('comment'), $changes
            );
            $this->_getSession()->addSuccess($this->__('The order has been updated.'));
            $response = ['success' => true];
            if ($order->getPromotionDhlExpressPopUp()){
                $response['dhlExpressPopUp'] = true;
            }
            echo json_encode($response);
        }
        catch (Exception $e) {
            $this->_getSession()->addError($this->__('The order has not been updated.'));
            $response = ['error' => true, 'message' => $e->getMessage()];
            echo json_encode($response);
        }

        return $this;
    }

    /**
     * @param $order
     *
     * @return bool
     */
    public function reauthorizeOrder($order)
    {
        if (Mage::getStoreConfig('toe/orderedit/auth')) {
            if ((float)$order->getGrandTotal() > (float)$order->getOrigData('grand_total')) {
                $payment     = $order->getPayment();
                $orderMethod = $payment->getMethod();
                if ($orderMethod != 'free' && $orderMethod != 'checkmo' && $orderMethod != 'purchaseorder') {
                    if (!$payment->authorize(1, $order->getGrandTotal())) {
                        return true;
                    }
                }
            }
        }
        return false;
    }


    /**
     * @return array
     * @throws Zend_Json_Exception
     */
    public function getEdits()
    {
        $edits = [];

        foreach ($this->getRequest()->getParams() as $param) {
            if (substr($param, 0, 1) == '{' and $param = Zend_Json::decode($param)) {
                $edits[] = $param;
            }
        }

        return $edits;
    }

    protected function _logChanges($order, $comment, $array = array())
    {
        $user = Mage::getSingleton('admin/session')->getUser();
        $logComment = $user->getUsername() . " made changes to this order. <br /><br />";
        foreach ($array as $change) {
            if ($change != 1) {
                $logComment .= $change;
            }
        }
        $logComment .= "<br />User comment: " . $comment;
        $status = $order->getStatus();
        $notify = 0;
        $order->addStatusToHistory($status, $logComment, $notify);
        $order->save();
    }

    protected function _initOrder()
    {
        $id    = $this->getRequest()->getParam('order_id');
        $order = Mage::getModel('orderedit/order')->load($id);

        if (!$order->getId()) {
            $this->_getSession()->addError($this->__('This order no longer exists.'));
            $this->_redirect('*/*/');
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);

            return false;
        }
        Mage::register('sales_order', $order);
        Mage::register('current_order', $order);

        return $order;
    }

    public function updateCommentAction()
    {
        if ($order = $this->_initOrder()) {
            echo $this->getLayout()->createBlock('adminhtml/sales_order_view_history')->setTemplate(
                'sales/order/view/history.phtml'
            )->toHtml();
        }
    }

    public function recalcAction()
    {
        echo $this->getLayout()->createBlock('orderedit/adminhtml_sales_order_shipping_update')->setTemplate(
            'sales/order/view/tab/shipping-form.phtml'
        )->toHtml();
    }

    /*
      public function newItemAction()
      {
      echo $this->getLayout()->createBlock('adminhtml/sales_order_create_search_grid')->toHtml();
      }
     */

    public function newItemAction()
    {
        echo $this->getLayout()->createBlock('orderedit/adminhtml_sales_order_view_items_add')->setTemplate(
            'sales/order/view/items/add.phtml'
        )->toHtml();
    }

    public function getQtyAndDescAction()
    {
        $sku            = $this->getRequest()->getParam('sku');
        $product        = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('sku', $sku)
            ->getFirstItem();
        $return         = array();
        $return['name'] = $product->getName();

        if ($product->getSpecialPrice()) {
            $return['price'] = round($product->getSpecialPrice(), 2);
        } else {
            $return['price'] = round($product->getPrice(), 2);
        }

        if ($product->getManageStock()) {
            $qty = $product->getQty();
        } else {
            $qty = 10;
        }
        $select = "<select class='n-item-qty'>";
        $x      = 1;
        while ($x <= $qty) {
            $select .= "<option value='" . $x . "'>" . $x . "</option>";
            $x++;
        }
        $select .= "</select>";
        $return['select'] = $select;
        echo Zend_Json::encode($return);
    }

}