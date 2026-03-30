<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\CrossSellingSession;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void            add(CrossSellingSessionProductEntity $entity)
 * @method void            set(string $key, CrossSellingSessionProductEntity $entity)
 * @method CrossSellingSessionProductEntity[]    getIterator()
 * @method CrossSellingSessionProductEntity[]    getElements()
 * @method CrossSellingSessionProductEntity|null get(string $key)
 * @method CrossSellingSessionProductEntity|null first()
 * @method CrossSellingSessionProductEntity|null last()
 */
class CrossSellingSessionProductCollection extends EntityCollection
{
    public function getProductIds(): array
    {
        return $this->map(fn(CrossSellingSessionProductEntity $entity) => $entity->getProductId());
    }

    protected function getExpectedClass(): string
    {
        return CrossSellingSessionProductEntity::class;
    }
}
