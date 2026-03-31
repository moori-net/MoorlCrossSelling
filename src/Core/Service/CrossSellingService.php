<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Service;

use Doctrine\DBAL\Connection;
use MoorlCrossSelling\Core\Content\CrossSellingRelation\CrossSellingRelationDefinition;
use MoorlCrossSelling\Core\Content\CrossSellingRelation\CrossSellingRelationEntity;
use MoorlCrossSelling\Core\Content\CrossSellingSession\CrossSellingSessionEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $crossSellingSessionRepository,
        private readonly EntityRepository $crossSellingSessionProductRepository,
        private readonly EntityRepository $crossSellingRelationRepository,
        private readonly EntityRepository $orderRepository,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger
    )
    {
    }

    public function createRelationsTask(Context $context, int $limit = 100): void
    {
        $createdAt = (new \DateTimeImmutable())
            ->modify('-24 hours')
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::LTE => $createdAt]));
        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('customFields.' . self::ORDER_PROCESSED, 1)
        ]));

        $orderIds = $this->orderRepository->searchIds($criteria, $context)->getIds();
        foreach ($orderIds as $orderId) {
            $this->createRelationsFromOrder($orderId, $context);
        }

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::LTE => $createdAt]));
        $criteria->addFilter(new EqualsFilter('processed', false));

        $sessionIds = $this->crossSellingSessionRepository->searchIds($criteria, $context)->getIds();
        foreach ($sessionIds as $sessionId) {
            $this->createRelationsFromSession($sessionId, $context);
        }
    }

    public function addSeenProduct(string $productId, SalesChannelContext $salesChannelContext): void
    {
        if (!$this->systemConfigService->getBool('MoorlCrossSelling.config.active', $salesChannelContext->getSalesChannelId())) {
            return;
        }

        $context = $salesChannelContext->getContext();
        $session = $this->getOrCreateActiveSession($salesChannelContext);
        $sessionProduct = $session->getSessionProducts()?->get($productId);
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        if ($sessionProduct) {
            $this->crossSellingSessionProductRepository->upsert([[
                'id' => $sessionProduct->getId(),
                'count' => $sessionProduct->getCount() + 1,
                'updatedAt' => $now,
            ]], $context);
        } else {
            $this->crossSellingSessionProductRepository->upsert([[
                'id' => Uuid::randomHex(),
                'sessionId' => $session->getId(),
                'productId' => $productId,
                'count' => 1,
                'updatedAt' => $now,
            ]], $context);
        }

        $this->crossSellingSessionRepository->upsert([[
            'id' => $session->getId(),
            'updatedAt' => $now,
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
        $criteria->addFilter(new EqualsFilter('processed', false));
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
            CrossSellingRelationDefinition::TYPE_SEEN,
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

        if ($order->getCustomFieldsValue(self::ORDER_PROCESSED)) {
            $this->logger->debug(sprintf('Order with ID %s skipped', $orderId));
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

    public function createRelations(
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

    public function createRelation(
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

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->crossSellingSessionRepository->upsert([[
            'id' => Uuid::randomHex(),
            'sessionId' => $salesChannelContext->getToken(),
            'customerId' => $salesChannelContext->getCustomerId(),
            'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            'processed' => false,
            'updatedAt' => $now,
        ]], $salesChannelContext->getContext());

        return $this->getActiveSession($salesChannelContext);
    }

    private function getActiveSession(SalesChannelContext $salesChannelContext): ?CrossSellingSessionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelContext->getSalesChannelId()));
        $criteria->addFilter(new EqualsFilter('processed', false));
        $criteria->addSorting(new FieldSorting('updatedAt', FieldSorting::DESCENDING));
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

    private function getSessionProductIds(string $sessionId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('sessionId', $sessionId));
        $criteria->addSorting(new FieldSorting('updatedAt', FieldSorting::DESCENDING));

        $sessionProducts = $this->crossSellingSessionProductRepository->search($criteria, $context)->getEntities();

        $productIds = [];

        foreach ($sessionProducts as $sessionProduct) {
            $productId = $sessionProduct->get('productId');

            if (!$productId) {
                continue;
            }

            $productIds[] = $productId;
        }

        return array_values(array_unique($productIds));
    }
}