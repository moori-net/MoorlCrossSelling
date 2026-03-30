import './page/sw-product-detail';
import './view/sw-product-detail-moorl-cs-relation';

Shopware.Module.register('sw-product-detail-moorl-cs-relation-tab', {
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.product.detail') {
            currentRoute.children.push({
                name: 'sw.product.detail.moorl.cs-relation',
                path: '/sw/product/detail/:id/moorl-cs-relation',
                component: 'sw-product-detail-moorl-cs-relation',
                meta: {
                    parentPath: "sw.product.index"
                }
            });
        }
        next(currentRoute);
    }
});
