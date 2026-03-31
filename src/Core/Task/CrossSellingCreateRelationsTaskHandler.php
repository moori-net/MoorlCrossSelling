<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Task;

use MoorlCrossSelling\Core\Service\CrossSellingService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: CrossSellingCreateRelationsTask::class)]
class CrossSellingCreateRelationsTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        private readonly CrossSellingService $crossSellingService
    )
    {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $this->crossSellingService->createRelationsTask(new Context(new SystemSource()));
    }
}
