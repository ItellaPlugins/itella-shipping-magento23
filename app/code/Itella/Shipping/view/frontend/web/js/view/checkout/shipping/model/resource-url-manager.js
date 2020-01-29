define(
    [
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mageUtils'
    ],
    function(customer, quote, urlBuilder, utils) {
        "use strict";
        return {
            getUrlForParcelTerminalList: function(quote,group = 0, limit) {
                var params = {group: group, city: quote.shippingAddress().city, country: quote.shippingAddress().countryId };
                var urls = {
                    'default': '/module/get-parcel-terminal-list/:group/:city/:country'
                };
                return this.getUrl(urls, params);
            },

            /** Get url for service */
            getUrl: function(urls, urlParams) {
                var url;

                if (utils.isEmpty(urls)) {
                    return 'Provided service call does not exist.';
                }

                if (!utils.isEmpty(urls['default'])) {
                    url = urls['default'];
                } else {
                    url = urls[this.getCheckoutMethod()];
                }
                return urlBuilder.createUrl(url, urlParams);
            },

            getCheckoutMethod: function() {
                return customer.isLoggedIn() ? 'customer' : 'guest';
            }
        };
    }
);