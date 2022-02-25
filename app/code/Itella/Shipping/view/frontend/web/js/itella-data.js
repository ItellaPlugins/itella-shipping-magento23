define([
    'jquery',
    'Magento_Customer/js/customer-data'
], function ($, storage) {
    'use strict';

    let getEmptyObject = function () {
        return {
            'selectedPickupPoint': null,
        };
    };

    let cacheKey = 'itella-data',
            saveData = function (data) {
                storage.set(cacheKey, data);
            },
            getData = function () {
                let data = storage.get(cacheKey)();

                if ($.isEmptyObject(data)) {
                    data = getEmptyObject();
                    saveData(data);
                }

                return data;
            };

    return {

        getPickupPoint: function () {
            return getData().selectedPickupPoint;
        },

        setPickupPoint: function (data) {
            let obj = getData();

            obj.selectedPickupPoint = data;

            saveData(obj);
        }

    };
});