<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\CrossSellingRelation;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CrossSellingRelationEntity extends Entity
{
    use EntityIdTrait;

    protected int $score = 0;

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): void
    {
        $this->score = $score;
    }
}
