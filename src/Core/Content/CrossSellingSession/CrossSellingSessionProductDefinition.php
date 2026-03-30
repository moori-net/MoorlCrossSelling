<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\CrossSellingSession;

use MoorlFoundation\Core\Framework\DataAbstractionLayer\Collection\FieldEntityCollection;
use MoorlFoundation\Core\Framework\DataAbstractionLayer\Collection\FieldMultiEntityCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CrossSellingSessionProductDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'moorl_cs_session_product';
    final public const PROPERTY_NAME = 'sessionProduct';
    final public const COLLECTION_NAME = 'sessionProducts';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CrossSellingSessionProductEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CrossSellingSessionProductCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(array_merge(
            FieldEntityCollection::getFieldItems(localClass: self::class),
            [
                (new IntField('count', 'count'))->addFlags()
            ],
            FieldMultiEntityCollection::getManyToOneFieldItems(
                references: [
                    [ProductDefinition::class, [new Required()], []],
                    [CrossSellingSessionDefinition::class, [new Required()], []],
                ],
            ),
        ));
    }
}
