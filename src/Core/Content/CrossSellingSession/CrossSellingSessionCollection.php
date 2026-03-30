<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\CrossSellingSession;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void            add(CrossSellingSessionEntity $entity)
 * @method void            set(string $key, CrossSellingSessionEntity $entity)
 * @method CrossSellingSessionEntity[]    getIterator()
 * @method CrossSellingSessionEntity[]    getElements()
 * @method CrossSellingSessionEntity|null get(string $key)
 * @method CrossSellingSessionEntity|null first()
 * @method CrossSellingSessionEntity|null last()
 */
class CrossSellingSessionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CrossSellingSessionEntity::class;
    }
}
