<?php
/**
 * Editing account info
 *
 *
 * @author       Bucur Ciprian <ciprian.bucur@evozon.com>
 * @package      Evozon
 * @category     TinyBrick_OrderEdit_Model_Edit_Updater_Type_Accountinfo
 * @copyright    Copyright (c) 2013 Perfect Memorials (http://www.perfectmemorials.com)
 */
class TinyBrick_OrderEdit_Model_Edit_Updater_Type_Accountinfo extends
    TinyBrick_OrderEdit_Model_Edit_Updater_Type_Abstract
{
	public function edit(TinyBrick_OrderEdit_Model_Order $order, $data = array())
	{
        $comment = '';
        $order->setCustomerEmail($data['customer_email']);
        if ($order->getCustomerEmail() != $order->getOrigData('customer_email')) {
            $comment .= 'CustomerEmail From: '.$order->getOrigData('customer_email') . ' To: '.
                $order->getCustomerEmail();
            return $comment;
        }
        return true;
	}
}