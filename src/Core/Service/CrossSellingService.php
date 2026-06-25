<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Service;

use MoorlCrossSelling\Core\Content\CrossSellingRelation\CrossSellingRelationCollection;
use MoorlCrossSelling\Core\Content\CrossSellingRelation\CrossSellingRelationDefinition;
use MoorlCrossSelling\Core\Content\CrossSellingRelation\CrossSellingRelationEntity;
use MoorlCrossSelling\Core\Content\CrossSellingSession\CrossSellingSessionDefinition;
use MoorlCrossSelling\Core\Content\CrossSellingSession\CrossSellingSessionEntity;
use MoorlCrossSelling\Core\Content\CrossSellingSession\CrossSellingSessionProductCollection;
use MoorlCrossSelling\Core\Content\CrossSellingSession\CrossSellingSessionProductDefinition;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class CrossSellingService
{
    private const ISSUE_SET_LIMIT_SOLVED = false;
    private const ORDER_PROCESSED = 'moorl_cross_selling_processed';
    private const LIMIT = 25;
    private const FALLBACK_TRANSLATIONS = [
        CrossSellingRelationDefinition::TYPE_BOUGHT => 'Frequently bought together',
        CrossSellingRelationDefinition::TYPE_VIEWED => 'Frequently viewed together',
        CrossSellingRelationDefinition::TYPE_HISTORY => 'Recently viewed',
        CrossSellingRelationDefinition::TYPE_SIMILAR => 'Similar products',
        CrossSellingRelationDefinition::TYPE_CUSTOM => 'To go with this',
    ];

    private array $productCrossSellingTranslations = [];

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $crossSellingSessionRepository,
        private readonly EntityRepository $crossSellingSessionProductRepository,
        private readonly EntityRepository $crossSellingRelationRepository,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $productCrossSellingRepository,
        private readonly LoggerInterface $logger
    )
    {
    }

    public function createRelationsFromSessionTask(Context $context): IdSearchResult
    {
        $idSearchResult = $this->crossSellingSessionRepository->searchIds(
            $this->relationsFromSessionCriteria(),
            $context
        );

        $payload = $this->createEmptyPayload();
        foreach ($idSearchResult->getIds() as $token) {
            $this->createRelationsFromSession($payload, $token, $context);
        }
        $this->processPayload($payload, $context);

        return $idSearchResult;
    }

    public function createRelationsFromOrderTask(Context $context): IdSearchResult
    {
        $idSearchResult = $this->orderRepository->searchIds(
            $this->relationsFromOrderCriteria(),
            $context
        );

        $payload = $this->createEmptyPayload();
        foreach ($idSearchResult->getIds() as $orderId) {
            $this->createRelationsFromOrder($payload, $orderId, $context);
        }
        $this->processPayload($payload, $context);

        return $idSearchResult;
    }

    public function updateProductsTask(Context $context, int $page = 1): IdSearchResult
    {
        $criteria = $this->getPaginatedCriteria($page);
        $idSearchResult = $this->productRepository->searchIds($criteria, $context);

        $payload = $this->createEmptyPayload();
        foreach ($idSearchResult->getIds() as $productId) {
            $this->updateProduct($payload, $productId, $context);
        }
        $this->processPayload($payload, $context);

        return $idSearchResult;
    }

    public function updateProduct(array &$payload, string $productId, Context $context): void
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

            $payload[ProductCrossSellingDefinition::ENTITY_NAME][$crossSellingId] = [
                'id' => $crossSellingId,
                'productId' => $productId,
                'active' => true,
                'type' => ProductCrossSellingDefinition::TYPE_PRODUCT_LIST,
                'assignedProducts' => $crossSellingRelations->fetchAssignedProductsPayload($productId),
                'translations' => $this->getProductCrossSellingTranslations($type),
            ];
        }
    }

    public function getProductCrossSellingTranslation(string $type, string $languageId): string
    {
        $config = $this->systemConfigService->get('MoorlCrossSelling.config.' . $type);
        if (!is_array($config)) {
            $config = [];
        }

        if (empty($config[$languageId])) {
            return self::FALLBACK_TRANSLATIONS[$type];
        }

        return $config[$languageId];
    }

    private function getProductCrossSellingTranslations(string $type): array
    {
        if (isset($this->productCrossSellingTranslations[$type])) {
            return $this->productCrossSellingTranslations[$type];
        }

        $config = $this->systemConfigService->get('MoorlCrossSelling.config.' . $type);
        if (!is_array($config)) {
            $config = [];
        }

        if (empty($config[Defaults::LANGUAGE_SYSTEM])) {
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

    private function relationsFromSessionCriteria(): Criteria
    {
        $criteria = $this->getPaginatedCriteria();
        $updatedAt = (new \DateTimeImmutable())
            ->modify($this->getMinAge())
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $criteria->addFilter(new RangeFilter('updatedAt', [RangeFilter::LTE => $updatedAt]));

        return $criteria;
    }

    private function relationsFromOrderCriteria(): Criteria
    {
        $criteria = $this->getPaginatedCriteria();
        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('customFields.' . self::ORDER_PROCESSED, 1)
        ]));

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
        $criteria = $this->relationsFromSessionCriteria();
        $criteria->addAggregation(new CountAggregation('count', 'id'));
        $aggregation = $this->crossSellingSessionRepository->aggregate($criteria, $context)->get('count');
        return $aggregation instanceof CountResult ? $aggregation->getCount() : 0;
    }

    public function countRelationsFromOrder(Context $context): int
    {
        $criteria = $this->relationsFromOrderCriteria();
        $criteria->addAggregation(new CountAggregation('count', 'id'));
        $aggregation = $this->orderRepository->aggregate($criteria, $context)->get('count');
        return $aggregation instanceof CountResult ? $aggregation->getCount() : 0;
    }

    public function addProductView(
        string $productId,
        SalesChannelContext $salesChannelContext,
        int $quantity = 1,
    ): void
    {
        if (!$this->systemConfigService->getBool('MoorlCrossSelling.config.active', $salesChannelContext->getSalesChannelId())) {
            return;
        }

        $context = $salesChannelContext->getContext();

        $criteria = new Criteria([$productId]);
        $criteria->setLimit(1);

        if ($this->productRepository->searchIds($criteria, $context)->getTotal() === 0) {
            return;
        }

        $session = $this->getOrCreateActiveSession($salesChannelContext);
        $sessionProduct = $session->getSessionProducts()?->getByProductId($productId);

        if ($sessionProduct) {
            $this->crossSellingSessionProductRepository->upsert([[
                'id' => $sessionProduct->getId(),
                'count' => $sessionProduct->getCount() + $quantity,
                'lastVisit' => time()
            ]], $context);
        } else {
            $this->crossSellingSessionProductRepository->upsert([[
                'id' => Uuid::randomHex(),
                'sessionId' => $session->getId(),
                'productId' => $productId,
                'count' => $quantity,
                'lastVisit' => time()
            ]], $context);
        }

        // Trigger "updatedAt"
        $this->crossSellingSessionRepository->upsert([[
            'id' => $session->getId()
        ]], $context);
    }

    public function getProductHistoryIds(SalesChannelContext $salesChannelContext): array
    {
        $session = $this->getActiveSession($salesChannelContext);
        if (!$session instanceof CrossSellingSessionEntity) {
            return [];
        }

        return $session->getSessionProducts()->getProductIds();
    }

    public function createRelationsFromSession(array &$payload, string $token, Context $context): void
    {
        $criteria = new Criteria([$token]);
        $criteria->addAssociation('sessionProducts');

        $session = $this->crossSellingSessionRepository->search($criteria, $context)->first();
        if (!$session instanceof CrossSellingSessionEntity) {
            $this->logger->warning(sprintf('Session with ID %s not found', $token));
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
            $payload,
            $productIds,
            CrossSellingRelationDefinition::TYPE_VIEWED,
            $session->getSalesChannelId()
        );

        $payload[CrossSellingSessionDefinition::ENTITY_NAME][] = [
            'id' => $session->getId()
        ];
    }
    
    public function createRelationsFromOrder(array &$payload, string $orderId, Context $context): void
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
            $payload,
            $productIds,
            CrossSellingRelationDefinition::TYPE_BOUGHT,
            $order->getSalesChannelId()
        );

        $payload[OrderDefinition::ENTITY_NAME][] = [
            'id' => $order->getId(),
            'customFields' => [
                self::ORDER_PROCESSED => 1
            ]
        ];
    }

    private function createRelations(
        array &$payload,
        array $productIds,
        string $type,
        string $salesChannelId
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
                    $payload,
                    $productIds[$i],
                    $productIds[$j],
                    $type,
                    $salesChannelId
                );
            }
        }
    }

    private function createRelation(
        array &$payload,
        string $productAId,
        string $productBId,
        string $type,
        string $salesChannelId
    ): void
    {
        if ($productAId === $productBId) {
            return;
        }

        if (strcmp($productAId, $productBId) > 0) {
            [$productAId, $productBId] = [$productBId, $productAId];
        }

        $relationId = md5(sprintf("%s|%s|%s|%s", $productAId, $productBId, $type, $salesChannelId));

        if (isset($payload[CrossSellingRelationDefinition::ENTITY_NAME][$relationId])) {
            $payload[CrossSellingRelationDefinition::ENTITY_NAME][$relationId]['score']++;
        } else {
            $payload[CrossSellingRelationDefinition::ENTITY_NAME][$relationId] = [
                'id' => $relationId,
                'type' => $type,
                'salesChannelId' => $salesChannelId,
                'productAId' => $productAId,
                'productBId' => $productBId,
                'score' => 1,
            ];
        }
    }

    private function getOrCreateActiveSession(SalesChannelContext $salesChannelContext): CrossSellingSessionEntity
    {
        $session = $this->getActiveSession($salesChannelContext);
        if ($session instanceof CrossSellingSessionEntity) {
            return $session;
        }

        $this->crossSellingSessionRepository->upsert([[
            'id' => Uuid::randomHex(),
            'token' => $salesChannelContext->getToken(),
            'customerId' => $salesChannelContext->getCustomerId(),
            'salesChannelId' => $salesChannelContext->getSalesChannelId()
        ]], $salesChannelContext->getContext());

        return $this->getActiveSession($salesChannelContext);
    }

    public function updateActiveSession(?CrossSellingSessionEntity $session, SalesChannelContext $salesChannelContext): void
    {
        if (!$session instanceof CrossSellingSessionEntity) {
            return;
        }
        if (!$session->getSessionProducts() instanceof CrossSellingSessionProductCollection) {
            return;
        }

        $existingSession = $this->getActiveSession($salesChannelContext);

        if ($existingSession instanceof CrossSellingSessionEntity) {
            // Merge with existing session and delete after
            $payload = [];

            foreach ($session->getSessionProducts() as $sessionProduct) {
                $payloadItem = [
                    'id' => $sessionProduct->getId(),
                    'lastVisit' => $sessionProduct->getLastVisit(),
                    'sessionId' => $existingSession->getId(),
                ];

                $existingSessionProduct = $existingSession->getSessionProducts()->getByProductId(
                    $sessionProduct->getProductId()
                );

                if ($existingSessionProduct) {
                    $payloadItem['id'] = $existingSessionProduct->getId();
                    $payloadItem['count'] = $existingSessionProduct->getCount() + $sessionProduct->getCount();
                }

                $payload[] = $payloadItem;
            }

            $this->crossSellingSessionProductRepository->upsert($payload, $salesChannelContext->getContext());

            $this->crossSellingSessionRepository->delete([[
                'id' => $session->getId()
            ]], $salesChannelContext->getContext());
        } else {
            // Update token and customer ID
            $this->crossSellingSessionRepository->upsert([[
                'id' => $session->getId(),
                'token' => $salesChannelContext->getToken(),
                'customerId' => $salesChannelContext->getCustomerId(),
            ]], $salesChannelContext->getContext());
        }
    }

    public function getActiveSession(SalesChannelContext $salesChannelContext): ?CrossSellingSessionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannelId()));
        $criteria->setLimit(1);
        $sessionProductsCriteria = $criteria->getAssociation('sessionProducts');
        $sessionProductsCriteria->addSorting(new FieldSorting('lastVisit', FieldSorting::DESCENDING));

        if (self::ISSUE_SET_LIMIT_SOLVED) {
            $itemLimit = $this->systemConfigService->getInt('MoorlCrossSelling.config.itemLimit');
            $sessionProductsCriteria->setLimit($itemLimit);
        }

        if ($salesChannelContext->getCustomerId()) {
            $criteria->addFilter(new EqualsFilter('customerId', $salesChannelContext->getCustomerId()));
        } else {
            $criteria->addFilter(new EqualsFilter('token', $salesChannelContext->getToken()));
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

    private function getMinAge(): string
    {
        $minAge = $this->systemConfigService->getInt('MoorlCrossSelling.config.minAge');

        return sprintf('-%d days', $minAge);
    }

    public function createEmptyPayload(): array
    {
        return [
            CrossSellingSessionDefinition::ENTITY_NAME => [],
            CrossSellingSessionProductDefinition::ENTITY_NAME => [],
            CrossSellingRelationDefinition::ENTITY_NAME => [],
            OrderDefinition::ENTITY_NAME => [],
            ProductDefinition::ENTITY_NAME => [],
            ProductCrossSellingDefinition::ENTITY_NAME => [],
        ];
    }

    public function processPayload(array &$payload, Context $context): void
    {
        if (count($payload[CrossSellingSessionDefinition::ENTITY_NAME]) > 0) {
            $this->crossSellingSessionRepository->delete($payload[CrossSellingSessionDefinition::ENTITY_NAME], $context);
        }
        if (count($payload[CrossSellingSessionProductDefinition::ENTITY_NAME]) > 0) {
            $this->crossSellingSessionProductRepository->upsert($payload[CrossSellingSessionProductDefinition::ENTITY_NAME], $context);
        }
        if (count($payload[CrossSellingRelationDefinition::ENTITY_NAME]) > 0) {
            $criteria = new Criteria(array_keys($payload[CrossSellingRelationDefinition::ENTITY_NAME]));
            $existingRelations = $this->crossSellingRelationRepository->search($criteria, $context)->getEntities();

            foreach ($existingRelations as $existingRelation) {
                if (!$existingRelation instanceof CrossSellingRelationEntity) {
                    continue;
                }

                $relationId = $existingRelation->getId();
                if (!isset($payload[CrossSellingRelationDefinition::ENTITY_NAME][$relationId])) {
                    continue;
                }

                $payload[CrossSellingRelationDefinition::ENTITY_NAME][$relationId]['score'] += $existingRelation->getScore();
            }

            $this->crossSellingRelationRepository->upsert(array_values($payload[CrossSellingRelationDefinition::ENTITY_NAME]), $context);
        }
        if (count($payload[OrderDefinition::ENTITY_NAME]) > 0) {
            $this->orderRepository->upsert($payload[OrderDefinition::ENTITY_NAME], $context);
        }
        if (count($payload[ProductDefinition::ENTITY_NAME]) > 0) {
            $this->productRepository->upsert($payload[ProductDefinition::ENTITY_NAME], $context);
        }
        if (count($payload[ProductCrossSellingDefinition::ENTITY_NAME]) > 0) {
            $this->productCrossSellingRepository->delete(
                array_map(
                    static fn (string $id): array => ['id' => $id],
                    array_keys($payload[ProductCrossSellingDefinition::ENTITY_NAME])
                ),
                $context
            );

            $this->productCrossSellingRepository->upsert(array_values($payload[ProductCrossSellingDefinition::ENTITY_NAME]), $context);
        }

        $payload = $this->createEmptyPayload();
    }
}
