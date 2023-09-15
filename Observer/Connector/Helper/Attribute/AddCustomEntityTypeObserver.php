<?php

declare(strict_types=1);

namespace Smile\CustomEntityAkeneo\Observer\Connector\Helper\Attribute;

use Akeneo\Connector\Helper\Config;
use Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Smile\CustomEntityProductLink\Model\Entity\Attribute\Frontend\CustomEntity;
use Smile\CustomEntityProductLink\Model\Entity\Attribute\Source\CustomEntity as Source;

/**
 * Attribute import job observer.
 */
class AddCustomEntityTypeObserver implements ObserverInterface
{
    protected Config $configHelper;

    public function __construct(Config $configHelper)
    {
        $this->configHelper = $configHelper;
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        if (!$this->configHelper->isReferenceEntitiesEnabled()) {
            /** @var DataObject $response */
            $response = $observer->getData('response');
            $types = $response->getTypes() && is_array($response->getTypes()) ? $response->getTypes() : [];
            $additionalTypes = array_merge(
                $types,
                [
                    'smile_custom_entity' => [
                        'backend_type' => 'text',
                        'frontend_input' => 'smile_custom_entity',
                        'backend_model' => ArrayBackend::class,
                        'source_model' => Source::class,
                        'frontend_model' => CustomEntity::class,
                    ],
                ]
            );
            $response->setTypes($additionalTypes);
        }
    }
}
