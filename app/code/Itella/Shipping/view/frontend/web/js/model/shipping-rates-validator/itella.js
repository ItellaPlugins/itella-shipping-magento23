/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
/*global define*/
define(
    [
        'jquery',
        'mageUtils',
        'Magento_Ui/js/model/messageList',
        'mage/translate'
    ],
    function ($, utils, messageList, $t) {
        'use strict';

        return {
            validate: function () {
                
                console.log($('#itella-map .itella-chosen-point').length );
                if ($('#itella-map .itella-chosen-point').length > 0 && !$('#itella-map .itella-chosen-point').attr('data-point-id')){
                    var message = $t('Select parcel terminal');
                    messageList.addErrorMessage({ message: message});
                }
                return false;
            }
        };
    }
);
