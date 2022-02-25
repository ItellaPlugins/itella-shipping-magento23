define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote'
], function ($, wrapper, quote) {
    'use strict';

    return function (setShippingInformationAction) {

        return wrapper.wrap(setShippingInformationAction, function (originalAction) {
            /**
             * Chance to modify shipping addres data
             */
            var shippingAddressData = quote.shippingAddress();

            if ($('#itella-map .itella-chosen-point').length > 0) {
                if (quote.shippingAddress().extensionAttributes === undefined) {
                    quote.shippingAddress().extensionAttributes = {};
                }           
                quote.shippingAddress().extensionAttributes.itella_parcel_terminal = $('#itella-map .itella-chosen-point').first().attr('data-point-id');
            }

            var result = originalAction();


            return result;
        });
    };
});