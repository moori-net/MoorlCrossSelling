<?php declare(strict_types=1);

namespace MoorlCrossSelling\Core\Content\Customer;

use MoorlCrossSelling\Core\Service\CrossSellingService;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerSubscriber implements EventSubscriberInterface
{
    private ?string $cachedToken = null;

    public function __construct(private readonly CrossSellingService $crossSellingService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerBeforeLoginEvent::class => ['onCustomerBeforeLogin'],
            CustomerLoginEvent::class => ['onCustomerLogin'],
        ];
    }

    public function onCustomerBeforeLogin(CustomerBeforeLoginEvent $event): void
    {
        $this->cachedToken = $event->getSalesChannelContext()->getToken();
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {

    }
}
