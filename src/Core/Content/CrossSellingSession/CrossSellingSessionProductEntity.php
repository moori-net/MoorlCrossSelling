<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\CrossSellingSession;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CrossSellingSessionProductEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId = "";
    protected int $count = 0;
    protected int $lastVisit = 0;

    public function getLastVisit(): int
    {
        return $this->lastVisit;
    }

    public function setLastVisit(int $lastVisit): void
    {
        $this->lastVisit = $lastVisit;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): void
    {
        $this->count = $count;
    }
}
