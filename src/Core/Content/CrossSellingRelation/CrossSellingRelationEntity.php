<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\CrossSellingRelation;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CrossSellingRelationEntity extends Entity
{
    use EntityIdTrait;

    protected int $score = 0;
    protected string $productAId = "";
    protected string $productBId = "";
    protected string $type = "";

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): void
    {
        $this->score = $score;
    }

    public function getProductAId(): string
    {
        return $this->productAId;
    }

    public function setProductAId(string $productAId): void
    {
        $this->productAId = $productAId;
    }

    public function getProductBId(): string
    {
        return $this->productBId;
    }

    public function setProductBId(string $productBId): void
    {
        $this->productBId = $productBId;
    }
}
