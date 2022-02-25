define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messageList',
    'mage/translate',
    'Itella_Shipping/js/itella-data'
], function($, wrapper, quote, globalMessageList, $t, $itellaData) {
    'use strict';

    return function(shippingInformationAction) {

        return wrapper.wrap(
            shippingInformationAction,
            function(originalAction) {
                let selectedShippingMethod = quote.shippingMethod();
                let shippingAddress = quote.shippingAddress();
                
                
                if (selectedShippingMethod.carrier_code !== 'itella') {
                    return originalAction();
                }
                
                let terminal = $itellaData.getPickupPoint();
                
                if (selectedShippingMethod.method_code === 'PARCEL_TERMINAL' &&
                    !terminal) {
                    globalMessageList.addErrorMessage(
                        {message: $t('Select Itella parcel terminal!')});
                    jQuery(window).scrollTop(0);
                    return originalAction();
                }
                
                if (shippingAddress.extensionAttributes === undefined) {
                    shippingAddress.extensionAttributes = {};
                }
                
                shippingAddress.extensionAttributes.itella_parcel_terminal = terminal;

                return originalAction();
            });
    };
});