<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\CrossSellingSession;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CrossSellingSessionEntity extends Entity
{
    use EntityIdTrait;

    protected string $sessionId = "";
    protected ?string $customerId = null;
    protected string $salesChannelId = "";
    protected bool $processed = false;
    protected ?CrossSellingSessionProductCollection $sessionProducts = null;

    public function getSessionProducts(): ?CrossSellingSessionProductCollection
    {
        return $this->sessionProducts;
    }

    public function setSessionProducts(?CrossSellingSessionProductCollection $sessionProducts): void
    {
        $this->sessionProducts = $sessionProducts;
    }

    public function getProcessed(): bool
    {
        return $this->processed;
    }

    public function setProcessed(bool $processed): void
    {
        $this->processed = $processed;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }
}
