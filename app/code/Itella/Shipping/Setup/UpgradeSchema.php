<?php

namespace Itella\Shipping\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
 
class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        /*
        $installer->getConnection()->addColumn(
            $installer->getTable('sales_order'),
            'manifest_generation_date',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => 20,
                'nullable' => true,
                'comment' => 'Manifest generation date',
            ]
        );
        */
        
        if(version_compare($context->getVersion(), '1.2.7', '<')) {

            $installer->getConnection()->modifyColumn(
                $installer->getTable( 'sales_order' ),
               'itella_services',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'comment' => 'Smartposti Extra services',
                ]

            );
        }
  

        $setup->endSetup();
    }
}