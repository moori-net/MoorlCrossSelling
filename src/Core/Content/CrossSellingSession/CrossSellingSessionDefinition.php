<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\CrossSellingSession;

use MoorlFoundation\Core\Framework\DataAbstractionLayer\Collection\FieldEntityCollection;
use MoorlFoundation\Core\Framework\DataAbstractionLayer\Collection\FieldMultiEntityCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class CrossSellingSessionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'moorl_cs_session';
    final public const PROPERTY_NAME = 'session';
    final public const COLLECTION_NAME = 'sessions';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CrossSellingSessionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CrossSellingSessionCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection(array_merge(
            FieldEntityCollection::getFieldItems(localClass: self::class),
            [
                (new StringField('session_id', 'sessionId'))->addFlags(),
                (new BoolField('processed', 'processed'))->addFlags()
            ],
            FieldMultiEntityCollection::getOneToManyFieldItems(
                localClass: self::class,
                references: [CrossSellingSessionProductDefinition::class]
            ),
            FieldMultiEntityCollection::getManyToOneFieldItems(
                references: [
                    [SalesChannelDefinition::class, [new Required()], []],
                    [CustomerDefinition::class, [], []],
                ],
            ),
        ));
    }
}
