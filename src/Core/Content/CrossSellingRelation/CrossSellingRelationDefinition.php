<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\CrossSellingRelation;

use MoorlFoundation\Core\Framework\DataAbstractionLayer\Collection\FieldEntityCollection;
use MoorlFoundation\Core\Framework\DataAbstractionLayer\Collection\FieldMultiEntityCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class CrossSellingRelationDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'moorl_cs_relation';
    final public const TYPE_SEEN = 'seen';
    final public const TYPE_VIEWED = 'viewed';
    final public const TYPE_SIMILAR = 'similar';
    final public const TYPE_BOUGHT = 'bought';
    final public const TYPE_CUSTOM = 'custom';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CrossSellingRelationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CrossSellingRelationCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(array_merge(
            FieldEntityCollection::getFieldItems(localClass: self::class),
            [
                (new FkField('product_a_id', 'productAId', ProductDefinition::class))->addFlags(new Required()),
                (new ReferenceVersionField(ProductDefinition::class, 'product_a_version_id'))->addFlags(new Required()),
                (new FkField('product_b_id', 'productBId', ProductDefinition::class))->addFlags(new Required()),
                (new ReferenceVersionField(ProductDefinition::class, 'product_b_version_id'))->addFlags(new Required()),
                (new StringField('type', 'type'))->addFlags(new Required()),
                (new IntField('score', 'score'))->addFlags(),
                (new ManyToOneAssociationField('productA', 'product_a_id', ProductDefinition::class))->addFlags(),
                (new ManyToOneAssociationField('productB', 'product_b_id', ProductDefinition::class))->addFlags(),
            ],
            FieldMultiEntityCollection::getManyToOneFieldItems(
                references: [
                    [SalesChannelDefinition::class, [new Required()], []]
                ],
            ),
        ));
    }
}
