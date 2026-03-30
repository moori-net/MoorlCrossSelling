import template from './index.html.twig';

Shopware.Component.register('sw-customer-detail-moorl-cs-relation', {
    template,

    props: {
        customer: {
            type: Object,
            required: true,
        },

        customerEditMode: {
            type: Boolean,
            required: true,
            default: false,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    }
});
