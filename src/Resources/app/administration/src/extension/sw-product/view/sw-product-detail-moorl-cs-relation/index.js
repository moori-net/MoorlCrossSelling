import template from './index.html.twig';

Shopware.Component.register('sw-product-detail-moorl-cs-relation', {
    template,

    computed: {
        product() {
            const product = Shopware.Store.get('swProductDetail').product;
            if (!product.customFields) {
                product.customFields = {};
            }
            return product;
        },

        isLoading() {
            return Shopware.Store.get('swProductDetail').isLoading;
        }
    }
});
