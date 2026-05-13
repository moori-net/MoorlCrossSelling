<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\Product\Cms;

use MoorlCrossSelling\Core\Content\CrossSellingRelation\CrossSellingRelationDefinition;
use MoorlCrossSelling\Core\Service\CrossSellingService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Product\Cms\ProductSliderCmsElementResolver;

class CrossSellingHistoryCmsElementResolver extends ProductSliderCmsElementResolver
{
    private const TYPE = 'moorl-cross-selling-history';

    public function __construct(
        iterable $processors,
        LoggerInterface $logger,
        private readonly CrossSellingService $crossSellingService
    ) {
        parent::__construct($processors, $logger);
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

        $titleConfig = $config->get('title');
        $titleConfig->assign([
            'source' => FieldConfig::SOURCE_STATIC,
            'value' => $this->crossSellingService->getProductCrossSellingTranslation(
                CrossSellingRelationDefinition::TYPE_HISTORY,
                $resolverContext->getSalesChannelContext()->getLanguageId()
            ),
        ]);

        return parent::collect($slot, $resolverContext);
    }
}
