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
    public function fetchAssignedProductsPayload(string $productId): array
    {
        $payload = [];
        $position = 0;

        foreach ($this->getElements() as $entity) {
            $relatedProductId = $entity->getProductAId() === $productId ? $entity->getProductBId() : $entity->getProductAId();
            $position++;

            $payload[] = [
                'id' => md5(sprintf("%s|%s|%s", $entity->getType(), $productId, $relatedProductId)),
                'productId' => $relatedProductId,
                'position' => $position
            ];
        }

        return $payload;
    }

    protected function getExpectedClass(): string
    {
        return CrossSellingRelationEntity::class;
    }
}
