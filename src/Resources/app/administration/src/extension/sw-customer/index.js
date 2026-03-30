import './page/sw-customer-detail';
import './view/sw-customer-detail-moorl-cs-session';

Shopware.Module.register('sw-customer-detail-moorl-cs-session-tab', {
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.customer.detail') {
            currentRoute.children.push({
                name: 'sw.customer.detail.moorl.cs-session',
                path: '/sw/customer/detail/:id/moorl-cs-session',
                component: 'sw-customer-detail-moorl-cs-session',
                meta: {
                    parentPath: "sw.customer.index"
                }
            });
        }
        next(currentRoute);
    }
});
