<?php

$installer = $this;

$installer->startSetup();

$installer->run("
    ALTER TABLE `{$this->getTable('sales_payment_transaction')}`
    ADD COLUMN `amount` DECIMAL(12,2) DEFAULT 0 NOT NULL COMMENT 'Transaction Amount' AFTER `is_closed`;
");

$installer->run("
ALTER TABLE `{$this->getTable('sales_payment_transaction')}`
ADD COLUMN `document_type` INT(10) UNSIGNED NULL COMMENT 'Creditmemo or Invoice' AFTER `amount`;
");

$installer->run("
ALTER TABLE `{$this->getTable('sales_payment_transaction')}`
ADD COLUMN `document_id` INT(10) UNSIGNED NULL COMMENT 'CreditmemoId or InvoiceId' AFTER `document_type`;
");

$installer->endSetup(); 