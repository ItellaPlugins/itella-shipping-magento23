/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/additional-validators',
        '../../model/shipping-rates-validator/itella'
    ],
    function (
        Component,
        additionalValidators,
        itellaShippingRatesValidator
    ) {
        'use strict';
        //console.log('test');
        additionalValidators.registerValidator('itella', itellaShippingRatesValidator);
        return Component;
    }
);