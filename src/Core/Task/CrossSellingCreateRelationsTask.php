<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Task;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CrossSellingCreateRelationsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'moorl_cross_selling.create_relations';
    }

    public static function getDefaultInterval(): int
    {
        return 60 * 5; // 5min
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
