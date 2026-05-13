<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Command;

use MoorlCrossSelling\Core\Service\CrossSellingService;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        parent::configure();

        $this
            ->addOption('truncate', 't', InputOption::VALUE_NONE, 'Truncate target table')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force update');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $context = Context::createCLIContext();

        if ($input->getOption('truncate')) {
            $io->warning([
                'The truncate option is active.',
                'Target table `moorl_cs_relation` data will be deleted before the update runs.',
                'This action can be destructive and should not be used accidentally.',
            ]);

            if (!$io->confirm('Do you really want to continue?', false)) {
                $io->note('Command aborted.');
                return self::SUCCESS;
            }

            $context->addState(CrossSellingService::TRUNCATE_TABLE_STATE);
        }

        if ($input->getOption('force')) {
            $context->addState(CrossSellingService::FORCE_UPDATE_STATE);
        }

        $io->title('Creating relations');

        $io->newLine(2);

        $count = $this->crossSellingService->countRelationsFromSession($context);
        $io->writeln(sprintf("Creating relations from %d customer sessions...", $count));
        $io->newLine();
        $progressBar = $io->createProgressBar($count);
        $page = 0;
        do {
            $page++;
            $step = $this->crossSellingService->createRelationsFromSessionTask($context, $page);
            $progressBar->advance($step->getTotal());
        } while ($step->getIds());
        $progressBar->finish();
        $io->newLine(3);
        $io->success(sprintf('Successfully created %d relations.', $count));

        $io->newLine(2);

        $count = $this->crossSellingService->countRelationsFromOrder($context);
        $io->writeln(sprintf("Creating relations from %d orders...", $count));
        $io->newLine();
        $progressBar = $io->createProgressBar($count);
        $page = 0;
        do {
            $page++;
            $step = $this->crossSellingService->createRelationsFromOrderTask($context, $page);
            $progressBar->advance($step->getTotal());
        } while ($step->getIds());
        $progressBar->finish();
        $io->newLine(3);
        $io->success(sprintf('Successfully created %d relations.', $count));

        return self::SUCCESS;
    }
}
