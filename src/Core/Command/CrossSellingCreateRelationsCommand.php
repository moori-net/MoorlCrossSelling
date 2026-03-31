<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Command;

use MoorlCrossSelling\Core\Service\CrossSellingService;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('moorl:cross-selling:create-relations')]
class CrossSellingCreateRelationsCommand extends Command
{
    public function __construct(
        private readonly CrossSellingService $crossSellingService
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createCLIContext();
        $io = new ShopwareStyle($input, $output);
        $io->title('Creating relations');

        $io->newLine(2);

        $count = $this->crossSellingService->countRelationsFromSession($context);
        $io->writeln(sprintf("Creating relations from %d customer sessions...", $count));
        $io->newLine();
        $progressBar = $io->createProgressBar($count);
        do {
            $step = $this->crossSellingService->createRelationsFromSessionTask($context);
            $progressBar->advance($step);
        } while ($step);
        $progressBar->finish();
        $io->newLine(3);
        $io->success(sprintf('Successfully created %d relations.', $count));

        $io->newLine(2);

        $count = $this->crossSellingService->countRelationsFromOrder($context);
        $io->writeln(sprintf("Creating relations from %d orders...", $count));
        $io->newLine();
        $progressBar = $io->createProgressBar($count);
        do {
            $step = $this->crossSellingService->createRelationsFromOrderTask($context);
            $progressBar->advance($step);
        } while ($step);
        $progressBar->finish();
        $io->newLine(3);
        $io->success(sprintf('Successfully created %d relations.', $count));

        return self::SUCCESS;
    }
}
