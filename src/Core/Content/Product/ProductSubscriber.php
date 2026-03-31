<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\Product;

use MoorlCrossSelling\Core\Service\CrossSellingService;
use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly CrossSellingService $crossSellingService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => ['onProductPageLoaded'],
            ProductIndexerEvent::class => ['onProductIndexer'],
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $this->crossSellingService->addSeenProduct(
            $event->getPage()->getProduct()->getId(),
            $event->getSalesChannelContext()
        );
    }

    public function onProductIndexer(ProductIndexerEvent $event): void
    {
        foreach ($event->getIds() as $productId) {
            $this->crossSellingService->updateProduct($productId, $event->getContext());
        }
    }
}
