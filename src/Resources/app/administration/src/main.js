function onMoorlFoundationReady(callback) {
    if (window.MoorlFoundation) {
        callback();
    } else {
        window.addEventListener('MoorlFoundationReady', callback, { once: true });
    }
}

onMoorlFoundationReady(() => {
    MoorlFoundation.ModuleHelper.registerModule({
        name: 'moorl-cs-relation',
        icon: 'regular-megaphone',
        entity: 'moorl_cs_relation',
        properties: [
            {name: 'productA.productNumber', visibility: 20},
            {name: 'productB.productNumber', visibility: 20},
            {name: 'type', visibility: 20},
            {name: 'score', visibility: 50}
        ],
        position: 200,
        entityMapping: {},
        cmsElements: []
    });

    MoorlFoundation.ModuleHelper.registerModule({
        cmsElements: [
            {
                plugin: 'MoorlCrossSelling',
                name: 'moorl-cross-selling-history',
                icon: 'regular-filter',
                component: 'sw-cms-el-product-slider',
                configComponent: 'sw-cms-el-config-product-slider',
                cmsElementMapping: {
                    products: {
                        source: 'static',
                        value: []
                    },
                    title: {
                        source: 'static',
                        value: '',
                    },
                    displayMode: {
                        source: 'static',
                        value: 'standard',
                    },
                    boxLayout: {
                        source: 'static',
                        value: 'standard',
                    },
                    navigationArrows: {
                        source: 'static',
                        value: 'outside',
                    },
                    rotate: {
                        source: 'static',
                        value: false,
                    },
                    autoplayTimeout: {
                        source: 'static',
                        value: 5000,
                    },
                    speed: {
                        source: 'static',
                        value: 300,
                    },
                    border: {
                        source: 'static',
                        value: false,
                    },
                    elMinWidth: {
                        source: 'static',
                        value: '300px',
                    },
                    verticalAlign: {
                        source: 'static',
                        value: null,
                    },
                    productStreamSorting: {
                        source: 'static',
                        value: 'name:ASC',
                    },
                    productStreamLimit: {
                        source: 'static',
                        value: 10,
                    },
                }
            },
        ]
    });
});
