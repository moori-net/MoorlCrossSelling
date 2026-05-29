<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\CrossSellingSession;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CrossSellingSessionEntity extends Entity
{
    use EntityIdTrait;

    protected string $token = "";
    protected ?string $customerId = null;
    protected string $salesChannelId = "";
    protected ?CrossSellingSessionProductCollection $sessionProducts = null;

    public function getSessionProducts(): ?CrossSellingSessionProductCollection
    {
        return $this->sessionProducts;
    }

    public function setSessionProducts(?CrossSellingSessionProductCollection $sessionProducts): void
    {
        $this->sessionProducts = $sessionProducts;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
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
