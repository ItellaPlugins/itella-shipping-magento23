<?php

namespace Itella\Shipping\Setup;
 
use Magento\Framework\Setup\UninstallInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
 
class Uninstall implements UninstallInterface
{
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
 
        $setup->getConnection()->dropColumn($setup->getTable('quote_address'), 'itella_parcel_terminal');
        $setup->getConnection()->dropColumn($setup->getTable('sales_order_address'), 'itella_parcel_terminal');
        $setup->getConnection()->dropColumn($setup->getTable('sales_order'), 'manifest_generation_date');
 
        $setup->endSetup();
    }
}