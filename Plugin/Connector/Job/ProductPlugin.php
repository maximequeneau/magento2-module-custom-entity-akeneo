<?php

declare(strict_types=1);

namespace Smile\CustomEntityAkeneo\Plugin\Connector\Job;

use Akeneo\Connector\Helper\Config;
use Akeneo\Connector\Helper\Import\Entities;
use Akeneo\Connector\Job\Product;
use Smile\CustomEntityProductLink\Model\ResourceModel\CustomEntityProductLinkManagement;

/**
 * Product job plugin.
 */
class ProductPlugin
{
    protected Entities $entitiesHelper;
    protected CustomEntityProductLinkManagement $customEntityProductLinkManagement;
    protected Config $configHelper;

    public function __construct(
        Entities $entitiesHelper,
        CustomEntityProductLinkManagement $customEntityProductLinkManagement,
        Config $configHelper
    ) {
        $this->configHelper = $configHelper;
        $this->customEntityProductLinkManagement = $customEntityProductLinkManagement;
        $this->entitiesHelper = $entitiesHelper;
    }

    /**
     * Set custom entities product links.
     */
    public function afterSetValues(Product $productJob): void
    {
        if (!$this->configHelper->isReferenceEntitiesEnabled()) {
            $connection = $this->entitiesHelper->getConnection();
            $productTmpTable = $this->entitiesHelper->getTableName('product');
            $eavAttributeTable = $this->entitiesHelper->getTable('eav_attribute');
            $entityTable = $this->entitiesHelper->getTable('akeneo_connector_entities');

            $selectAttributes = $connection->select()
                ->from(['eav' => $eavAttributeTable], ['attribute_code', 'attribute_id'])
                ->joinLeft(
                    ['cea' => $this->entitiesHelper->getTable('catalog_eav_attribute')],
                    'eav.attribute_id = cea.attribute_id',
                    []
                )->joinLeft(
                    ['ace' => $entityTable],
                    'cea.custom_entity_attribute_set_id = ace.entity_id',
                    ['code']
                )->where(
                    'ace.import = "smile_custom_entity"'
                )->where('eav.frontend_input = "smile_custom_entity"');

            $customEntityAttributes = $connection->fetchAssoc($selectAttributes);

            $columns = array_keys($connection->describeTable($productTmpTable));
            foreach ($columns as $column) {
                if (
                    !key_exists($column, $customEntityAttributes)
                    || !$connection->tableColumnExists($productTmpTable, $column)
                ) {
                    continue;
                }
                try {
                    $select = $connection->select()->from($productTmpTable, ['_entity_id', $column]);
                    $products = $connection->fetchAssoc($select);
                    $customEntityCode = $customEntityAttributes[$column]['code'];
                    foreach ($products as $id => $productData) {
                        $valueIds = [];
                        if ($productData[$column] && is_string($productData[$column])) {
                            $values = preg_filter(
                                '/^/',
                                "$customEntityCode-",
                                explode(",", $productData[$column])
                            );
                            $valueIdSelect = $connection->select()
                                ->from($entityTable, ['entity_id'])
                                ->where('code IN (?)', $values)
                                ->where('import = "smile_custom_entity_record"');
                            $valueIds = $connection->fetchCol($valueIdSelect);
                        }
                        $this->customEntityProductLinkManagement->saveLinks(
                            $id,
                            $customEntityAttributes[$column]['attribute_id'],
                            $valueIds
                        );
                    }
                } catch (\Exception $e) {
                    $productJob->getLogger()->error($e->getMessage());
                }
            }
        }
    }
}
