<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Command;

use MoorlCrossSelling\Core\Service\CrossSellingService;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('moorl:cross-selling:update-products')]
class CrossSellingUpdateProductsCommand extends Command
{
    public function __construct(
        private readonly CrossSellingService $crossSellingService
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $context = Context::createCLIContext();

        $io->title('Updating products');

        $io->newLine(2);

        $count = $this->crossSellingService->countProducts($context);
        $io->writeln(sprintf("Updating %d products...", $count));
        $io->newLine();
        $progressBar = $io->createProgressBar($count);
        $page = 0;
        do {
            $page++;
            $step = $this->crossSellingService->updateProductsTask($context, $page);
            $progressBar->advance($step->getTotal());
        } while ($step->getIds());
        $progressBar->finish();
        $io->newLine(3);
        $io->success(sprintf('Successfully updated %d products.', $count));

        return self::SUCCESS;
    }
}
