<?php

$installer = $this;

$installer->startSetup();

$installer->run("
    ALTER TABLE `{$this->getTable('sales_flat_order')}`
    ADD COLUMN `orderedit` TINYINT(4) DEFAULT 0 NOT NULL COMMENT 'was order edited' AFTER `custom_charge`;
");

$installer->endSetup(); 