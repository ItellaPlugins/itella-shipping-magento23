/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/shipping-rates-validator',
        'Magento_Checkout/js/model/shipping-rates-validation-rules',
        'Itella_Shipping/js/model/shipping-rates-validator',
        'Itella_Shipping/js/model/shipping-rates-validation-rules'
    ],
    function (
        Component,
        defaultShippingRatesValidator,
        defaultShippingRatesValidationRules,
        ItellaShippingRatesValidator,
        ItellaShippingRatesValidationRules
    ) {
        'use strict';
        defaultShippingRatesValidator.registerValidator('Itella', ItellaShippingRatesValidator);
        defaultShippingRatesValidationRules.registerRules('Itella', ItellaShippingRatesValidationRules);
        return Component;
    }
);
