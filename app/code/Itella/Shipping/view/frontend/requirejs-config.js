var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/action/set-shipping-information': {
                'Itella_Shipping/js/action/set-shipping-information-mixin': true
            }
        }
    },
    paths: {
        leaflet: 'https://unpkg.com/leaflet@1.6.0/dist/leaflet',
        leafletmarkercluster: 'https://unpkg.com/leaflet.markercluster@1.3.0/dist/leaflet.markercluster'
    },
    shim: {
        leaflet: {
            exports: 'L'
        },
        leafletmarkercluster: {
            deps: ['leaflet']
        }
    }
};