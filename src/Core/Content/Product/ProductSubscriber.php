<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\Product;

use MoorlCrossSelling\Core\Service\CrossSellingService;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly CrossSellingService $crossSellingService
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductIndexerEvent::class => 'onProductIndexer'
        ];
    }

    public function onProductIndexer(ProductIndexerEvent $event): void
    {
        if ($this->systemConfigService->get('MoorlCrossSelling.config.updateProductsStrategy') === 'disabled') {
            return;
        }

        foreach ($event->getIds() as $productId) {
            $this->crossSellingService->updateProduct($productId, $event->getContext());
        }
    }
}
