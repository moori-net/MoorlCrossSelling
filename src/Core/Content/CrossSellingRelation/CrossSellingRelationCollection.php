<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\CrossSellingRelation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void            add(CrossSellingRelationEntity $entity)
 * @method void            set(string $key, CrossSellingRelationEntity $entity)
 * @method CrossSellingRelationEntity[]    getIterator()
 * @method CrossSellingRelationEntity[]    getElements()
 * @method CrossSellingRelationEntity|null get(string $key)
 * @method CrossSellingRelationEntity|null first()
 * @method CrossSellingRelationEntity|null last()
 */
class CrossSellingRelationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CrossSellingRelationEntity::class;
    }
}
