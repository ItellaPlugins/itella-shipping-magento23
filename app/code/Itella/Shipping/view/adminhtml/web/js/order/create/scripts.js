define([
    'jquery',
    'Magento_Sales/order/create/scripts'
], function (jQuery) {
    'use strict';


    AdminOrder.prototype.setParcelTerminal  = function(pickup_point) {
              var data = {};
              data['order[shipping_method]'] = 'itella_PARCEL_TERMINAL';
              data['order[itella_parcel_terminal]'] = pickup_point;
              this.loadArea(['shipping_method', 'totals', 'billing_method'], true, data);
            };
});