define(
    [
        'Itella_Shipping/js/view/checkout/shipping/model/resource-url-manager',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'mage/storage',
        'Magento_Checkout/js/model/shipping-service',
        'Itella_Shipping/js/view/checkout/shipping/model/parcel-terminal-registry',
        'Magento_Checkout/js/model/error-processor'
    ],
    function (resourceUrlManager, quote, customer, storage, shippingService, parcelTerminalRegistry, errorProcessor) {
        'use strict';

        return {
            /**
             * Get nearest machine list for specified address
             * @param {Object} address
             */
            getParcelTerminalList: function (address, form,group = 0) {
                shippingService.isLoading(true);
                var cacheKey = address.getCacheKey(),
                    cache = parcelTerminalRegistry.get(cacheKey),
                    serviceUrl = resourceUrlManager.getUrlForParcelTerminalList(quote,group);

                if (cache) {
                    form.setParcelTerminalList(cache);
                    shippingService.isLoading(false);
                } else {
                    storage.get(
                        serviceUrl, false
                    ).done(
                        function (result) {
                            parcelTerminalRegistry.set(cacheKey, result);
                            form.setParcelTerminalList(result);
                        }
                    ).fail(
                        function (response) {
                            errorProcessor.process(response);
                        }
                    ).always(
                        function () {
                            shippingService.isLoading(false);
                        }
                    );
                }
            }
        };
    }
);