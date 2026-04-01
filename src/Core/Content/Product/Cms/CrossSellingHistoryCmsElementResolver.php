<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\Product\Cms;

use MoorlCrossSelling\Core\Service\CrossSellingService;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Product\Cms\ProductSliderCmsElementResolver;

class CrossSellingHistoryCmsElementResolver extends ProductSliderCmsElementResolver
{
    private const TYPE = 'moorl-cross-selling-history';

    private readonly CrossSellingService $crossSellingService;

    public function __construct() {
        $args = func_get_args();
        $this->crossSellingService = array_pop($args);

        parent::__construct(...$args);
    }
    public function getType(): string
    {
        return self::TYPE;
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $productConfig = $config->get('products');

        $productConfig->assign([
            'source' => FieldConfig::SOURCE_STATIC,
            'value' => $this->crossSellingService->getProductHistoryIds($resolverContext->getSalesChannelContext()),
        ]);

        return parent::collect($slot, $resolverContext);
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        parent::enrich($slot, $resolverContext, $result);
    }
}
