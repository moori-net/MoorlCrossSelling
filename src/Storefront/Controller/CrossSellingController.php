<?php declare(strict_types=1);

namespace MoorlCrossSelling\Storefront\Controller;

use MoorlCrossSelling\Core\Service\CrossSellingService;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class CrossSellingController extends StorefrontController
{
    public function __construct(private readonly CrossSellingService $crossSellingService)
    {
    }

    #[Route(
        path: '/moorl-cross-selling/track/{productId}',
        name: 'frontend.moorl.cross-selling.track',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => false,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function track(string $productId, SalesChannelContext $salesChannelContext): Response
    {
        $this->crossSellingService->addProductView($productId, $salesChannelContext);

        $response = new NoContentResponse();
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $response;
    }
}
