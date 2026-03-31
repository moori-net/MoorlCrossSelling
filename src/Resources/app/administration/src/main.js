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
});
