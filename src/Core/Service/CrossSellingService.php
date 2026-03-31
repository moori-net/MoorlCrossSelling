<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Service;

use Doctrine\DBAL\Connection;
use MoorlCrossSelling\Core\Content\CrossSellingRelation\CrossSellingRelationCollection;
use MoorlCrossSelling\Core\Content\CrossSellingRelation\CrossSellingRelationDefinition;
use MoorlCrossSelling\Core\Content\CrossSellingRelation\CrossSellingRelationEntity;
use MoorlCrossSelling\Core\Content\CrossSellingSession\CrossSellingSessionEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CrossSellingService
{
    private const ORDER_PROCESSED = 'moorl_cross_selling_processed';
    private const MIN_AGE = '-24 hours';
    private const LIMIT = 25;
    private const FALLBACK_TRANSLATIONS = [
        CrossSellingRelationDefinition::TYPE_BOUGHT => 'Frequently bought together',
        CrossSellingRelationDefinition::TYPE_VIEWED => 'Frequently viewed together',
        CrossSellingRelationDefinition::TYPE_SEEN => 'Recently viewed',
        CrossSellingRelationDefinition::TYPE_SIMILAR => 'Similar products',
        CrossSellingRelationDefinition::TYPE_CUSTOM => 'To go with this',
    ];
    public const FORCE_UPDATE_STATE = 'force_update';
    public const TRUNCATE_TABLE_STATE = 'truncate_table';

    private array $productCrossSellingTranslations = [];

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $crossSellingSessionRepository,
        private readonly EntityRepository $crossSellingSessionProductRepository,
        private readonly EntityRepository $crossSellingRelationRepository,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $productCrossSellingRepository,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger
    )
    {
    }

    public function createRelationsFromSessionTask(Context $context, int $page = 1): int
    {
        $this->truncateTable(
            $context,
            $page,
            CrossSellingRelationDefinition::ENTITY_NAME,
            CrossSellingRelationDefinition::TYPE_VIEWED
        );

        $idSearchResult = $this->crossSellingSessionRepository->searchIds(
            $this->relationsFromSessionCriteria($context, $page),
            $context
        );
        foreach ($idSearchResult->getIds() as $sessionId) {
            $this->createRelationsFromSession($sessionId, $context);
        }
        return $idSearchResult->getTotal();
    }

    public function createRelationsFromOrderTask(Context $context, int $page = 1): int
    {
        $this->truncateTable(
            $context,
            $page,
            CrossSellingRelationDefinition::ENTITY_NAME,
            CrossSellingRelationDefinition::TYPE_BOUGHT
        );

        $idSearchResult = $this->orderRepository->searchIds(
            $this->relationsFromOrderCriteria($context, $page),
            $context
        );
        foreach ($idSearchResult->getIds() as $orderId) {
            $this->createRelationsFromOrder($orderId, $context);
        }
        return $idSearchResult->getTotal();
    }

    public function updateProductsTask(Context $context, int $page = 1): int
    {
        $this->truncateTable(
            $context,
            $page,
            ProductCrossSellingDefinition::ENTITY_NAME
        );

        $criteria = $this->getPaginatedCriteria($page);
        $idSearchResult = $this->productRepository->searchIds($criteria, $context);
        foreach ($idSearchResult->getIds() as $productId) {
            $this->updateProduct($productId, $context);
        }
        return $idSearchResult->getTotal();
    }

    public function updateProduct(string $productId, Context $context): void
    {
        $itemLimit = $this->systemConfigService->getInt('MoorlCrossSelling.config.itemLimit');

        $types = [
            CrossSellingRelationDefinition::TYPE_VIEWED,
            CrossSellingRelationDefinition::TYPE_BOUGHT,
        ];

        foreach ($types as $type) {
            $criteria = new Criteria();
            $criteria->setLimit($itemLimit);
            $criteria->addFilter(new EqualsFilter('type', $type));
            $criteria->addFilter(new OrFilter([
                new EqualsFilter('productAId', $productId),
                new EqualsFilter('productBId', $productId),
            ]));
            $criteria->addSorting(new FieldSorting('score', FieldSorting::DESCENDING));

            $crossSellingRelations = $this->crossSellingRelationRepository->search($criteria, $context)->getEntities();
            if (!$crossSellingRelations instanceof CrossSellingRelationCollection || $crossSellingRelations->count() === 0) {
                continue;
            }

            $crossSellingId = md5(sprintf("%s|%s", $type, $productId));

            $payload = [
                'id' => $crossSellingId,
                'productId' => $productId,
                'active' => true,
                'type' => ProductCrossSellingDefinition::TYPE_PRODUCT_LIST,
                'assignedProducts' => $crossSellingRelations->fetchAssignedProductsPayload($productId),
                'translations' => $this->getProductCrossSellingTranslations($type),
            ];

            $this->productCrossSellingRepository->upsert([$payload], $context);
        }
    }

    private function getProductCrossSellingTranslations(string $type): array
    {
        if (isset($this->productCrossSellingTranslations[$type])) {
            return $this->productCrossSellingTranslations[$type];
        }

        $config = $this->systemConfigService->get('MoorlCrossSelling.config.' . $type);

        if (!isset($config[Defaults::LANGUAGE_SYSTEM]) || empty($config[Defaults::LANGUAGE_SYSTEM])) {
            $config[Defaults::LANGUAGE_SYSTEM] = self::FALLBACK_TRANSLATIONS[$type];
        }

        $this->productCrossSellingTranslations[$type] = [];

        foreach ($config as $languageId => $value) {
            if (!Uuid::isValid($languageId) || empty($value)) {
                continue;
            }

            $this->productCrossSellingTranslations[$type][$languageId] = ['name' => $value];
        }

        return $this->productCrossSellingTranslations[$type];
    }

    private function relationsFromSessionCriteria(Context $context, int $page = 1): Criteria
    {
        if (!$context->hasState(self::FORCE_UPDATE_STATE)) {
            $criteria = $this->getPaginatedCriteria();
            $createdAt = (new \DateTimeImmutable())
                ->modify(self::MIN_AGE)
                ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::LTE => $createdAt]));
            $criteria->addFilter(new EqualsFilter('processed', false));
        } else {
            $criteria = $this->getPaginatedCriteria($page);
        }

        return $criteria;
    }

    private function relationsFromOrderCriteria(Context $context, int $page = 1): Criteria
    {
        if (!$context->hasState(self::FORCE_UPDATE_STATE)) {
            $criteria = $this->getPaginatedCriteria();
            $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_OR, [
                new EqualsFilter('customFields.' . self::ORDER_PROCESSED, 1)
            ]));
        } else {
            $criteria = $this->getPaginatedCriteria($page);
        }

        return $criteria;
    }

    public function countProducts(Context $context): int
    {
        $criteria = new Criteria();
        $criteria->addAggregation(new CountAggregation('count', 'id'));
        $aggregation = $this->productRepository->aggregate($criteria, $context)->get('count');
        return $aggregation instanceof CountResult ? $aggregation->getCount() : 0;
    }

    public function countRelationsFromSession(Context $context): int
    {
        $criteria = $this->relationsFromSessionCriteria($context);
        $criteria->addAggregation(new CountAggregation('count', 'id'));
        $aggregation = $this->crossSellingSessionRepository->aggregate($criteria, $context)->get('count');
        return $aggregation instanceof CountResult ? $aggregation->getCount() : 0;
    }

    public function countRelationsFromOrder(Context $context): int
    {
        $criteria = $this->relationsFromOrderCriteria($context);
        $criteria->addAggregation(new CountAggregation('count', 'id'));
        $aggregation = $this->orderRepository->aggregate($criteria, $context)->get('count');
        return $aggregation instanceof CountResult ? $aggregation->getCount() : 0;
    }

    public function addSeenProduct(string $productId, SalesChannelContext $salesChannelContext): void
    {
        if (!$this->systemConfigService->getBool('MoorlCrossSelling.config.active', $salesChannelContext->getSalesChannelId())) {
            return;
        }

        $context = $salesChannelContext->getContext();
        $session = $this->getOrCreateActiveSession($salesChannelContext);
        $sessionProduct = $session->getSessionProducts()?->getByProductId($productId);

        if ($sessionProduct) {
            $this->crossSellingSessionProductRepository->upsert([[
                'id' => $sessionProduct->getId(),
                'count' => $sessionProduct->getCount() + 1,
                'lastVisit' => time()
            ]], $context);
        } else {
            $this->crossSellingSessionProductRepository->upsert([[
                'id' => Uuid::randomHex(),
                'sessionId' => $session->getId(),
                'productId' => $productId,
                'count' => 1,
                'lastVisit' => time()
            ]], $context);
        }

        $this->crossSellingSessionRepository->upsert([[
            'id' => $session->getId()
        ]], $context);
    }

    public function getSeenProductIds(SalesChannelContext $salesChannelContext): array
    {
        $session = $this->getActiveSession($salesChannelContext);
        if (!$session instanceof CrossSellingSessionEntity) {
            return [];
        }

        return $session->getSessionProducts()->getProductIds();
    }

    public function createRelationsFromSession(string $sessionId, Context $context): void
    {
        $criteria = new Criteria([$sessionId]);
        $criteria->addAssociation('sessionProducts');

        $session = $this->crossSellingSessionRepository->search($criteria, $context)->first();
        if (!$session instanceof CrossSellingSessionEntity) {
            $this->logger->warning(sprintf('Session with ID %s not found', $sessionId));
            return;
        }
        
        $productIds = [];

        foreach ($session->getSessionProducts() as $sessionProduct) {
            if (!$sessionProduct->getProductId()) {
                continue;
            }

            $productIds[] = $sessionProduct->getProductId();
        }

        $this->createRelations(
            $productIds,
            CrossSellingRelationDefinition::TYPE_VIEWED,
            $session->getSalesChannelId(),
            $context
        );

        $this->crossSellingSessionRepository->upsert([[
            'id' => $session->getId(),
            'processed' => true
        ]], $context);
    }
    
    public function createRelationsFromOrder(string $orderId, Context $context): void
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');

        $order = $this->orderRepository->search($criteria, $context)->first();
        if (!$order instanceof OrderEntity) {
            $this->logger->warning(sprintf('Order with ID %s not found', $orderId));
            return;
        }

        $productIds = [];

        foreach ($order->getLineItems() as $lineItem) {
            if (!$lineItem->getProductId()) {
                continue;
            }

            $productIds[] = $lineItem->getProductId();
        }

        $this->createRelations(
            $productIds,
            CrossSellingRelationDefinition::TYPE_BOUGHT,
            $order->getSalesChannelId(),
            $context
        );

        $this->orderRepository->upsert([[
            'id' => $order->getId(),
            'customFields' => [
                self::ORDER_PROCESSED => 1
            ]
        ]], $context);
    }

    private function createRelations(
        array $productIds,
        string $type,
        string $salesChannelId,
        Context $context
    ): void
    {
        $productIds = array_values(array_unique($productIds));
        $count = count($productIds);

        if ($count < 2) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $this->createRelation(
                    $productIds[$i],
                    $productIds[$j],
                    $type,
                    $salesChannelId,
                    $context
                );
            }
        }
    }

    private function createRelation(
        string $productAId,
        string $productBId,
        string $type,
        string $salesChannelId,
        Context $context
    ): void
    {
        if ($productAId === $productBId) {
            return;
        }

        if (strcmp($productAId, $productBId) > 0) {
            [$productAId, $productBId] = [$productBId, $productAId];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productAId', $productAId));
        $criteria->addFilter(new EqualsFilter('productBId', $productBId));
        $criteria->addFilter(new EqualsFilter('type', $type));
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        $crossSellingRelation = $this->crossSellingRelationRepository->search($criteria, $context)->first();

        if ($crossSellingRelation instanceof CrossSellingRelationEntity) {
            $this->crossSellingRelationRepository->upsert([[
                'id' => $crossSellingRelation->getId(),
                'score' => $crossSellingRelation->getScore() + 1
            ]], $context);

            return;
        }

        $this->crossSellingRelationRepository->upsert([[
            'id' => Uuid::randomHex(),
            'type' => $type,
            'salesChannelId' => $salesChannelId,
            'productAId' => $productAId,
            'productBId' => $productBId,
            'score' => 1,
        ]], $context);
    }

    private function getOrCreateActiveSession(SalesChannelContext $salesChannelContext): CrossSellingSessionEntity
    {
        $session = $this->getActiveSession($salesChannelContext);
        if ($session instanceof CrossSellingSessionEntity) {
            return $session;
        }

        $this->crossSellingSessionRepository->upsert([[
            'id' => Uuid::randomHex(),
            'sessionId' => $salesChannelContext->getToken(),
            'customerId' => $salesChannelContext->getCustomerId(),
            'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            'processed' => false
        ]], $salesChannelContext->getContext());

        return $this->getActiveSession($salesChannelContext);
    }

    private function getActiveSession(SalesChannelContext $salesChannelContext): ?CrossSellingSessionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannelId()));
        $criteria->addFilter(new EqualsFilter('processed', false));
        $criteria->setLimit(1);
        $criteria->addAssociation('sessionProducts');

        if ($salesChannelContext->getCustomerId()) {
            $criteria->addFilter(new OrFilter([
                new EqualsFilter('sessionId', $salesChannelContext->getToken()),
                new EqualsFilter('customerId', $salesChannelContext->getCustomerId()),
            ]));
        } else {
            $criteria->addFilter(new EqualsFilter('sessionId', $salesChannelContext->getToken()));
        }

        $session = $this->crossSellingSessionRepository->search($criteria, $salesChannelContext->getContext())->first();
        return $session instanceof CrossSellingSessionEntity ? $session : null;
    }

    private function getPaginatedCriteria(int $page = 1): Criteria
    {
        $criteria = new Criteria();
        $criteria->setLimit(self::LIMIT);
        $criteria->setOffset(($page - 1) * self::LIMIT);

        return $criteria;
    }

    private function truncateTable(Context $context, int $page, string $entity, ?string $type = null): void
    {
        if ($page === 1 && $context->hasState(self::TRUNCATE_TABLE_STATE)) {
            $sql = $type ?
                sprintf("DELETE FROM `%s` WHERE `type` = '%s';", $entity, $type) :
                sprintf("DELETE FROM `%s`;", $entity);

            $this->connection->executeStatement($sql);
        }
    }
}
