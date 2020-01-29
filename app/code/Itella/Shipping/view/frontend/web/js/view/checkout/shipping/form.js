define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Itella_Shipping/js/view/checkout/shipping/parcel-terminal-service',
    'mage/translate',
    'Itella_Shipping/js/leaflet',
    'Itella_Shipping/js/itella-mapping',
], function ($, ko, Component, quote, shippingService, parcelTerminalService, t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Itella_Shipping/checkout/shipping/form'
        },

        initialize: function (config) {
            this.parcelTerminals = ko.observableArray();
            this.selectedParcelTerminal = ko.observable();
            this._super();
            
            
            
        },
        hideSelect: function () {
            var method = quote.shippingMethod();
            var selectedMethod = method != null ? method.carrier_code + '_' + method.method_code : null;
            if (selectedMethod == 'Itella_PARCEL_TERMINAL') {
                $('.itella-shipping-container').first().show();
            } else {
                $('.itella-shipping-container').first().hide();
            }
        },
        moveSelect: function () {
          $('#checkout-shipping-method-load input:radio:not(.bound)').addClass('bound').bind('click', this.hideSelect());
              
          /*
            if ($('#onepage-checkout-shipping-method-additional-load .parcel-terminal-list').length > 0){
                $('#checkout-shipping-method-load input:radio:not(.bound)').addClass('bound').bind('click', this.hideSelect());
                if ($('#checkout-shipping-method-load .parcel-terminal-list').html() !=  $('#onepage-checkout-shipping-method-additional-load .parcel-terminal-list').html()){
                    $('#terminal-select-location').remove();
                }
                
                if ($('#checkout-shipping-method-load .parcel-terminal-list').length == 0){
                    var terminal_list = $('#onepage-checkout-shipping-method-additional-load .Itella-parcel-terminal-list-wrapper div');
                    var row = $.parseHTML('<tr><td colspan = "4" style = "border-top: none; padding-top: 0px"></td></tr>');
                    if ($('#s_method_Itella_PARCEL_TERMINAL').length > 0){
                        var move_after = $('#s_method_Itella_PARCEL_TERMINAL').parents('tr'); 
                    } else if ($('#label_method_PARCEL_TERMINAL_Itella').length > 0){
                        var move_after = $('#label_method_PARCEL_TERMINAL_Itella').parents('tr'); 
                    }
                    var cloned =  terminal_list.clone(true);
                    if ($('#terminal-select-location').length == 0){
                        $('<tr id = "terminal-select-location" ><td colspan = "4" style = "border-top: none; padding-top: 0px"></td></tr>').insertAfter(move_after);
                    }
                    cloned.appendTo($('#terminal-select-location td'));
                }
            }
            
            if ($('#terminal-select-location select').val() != last_selected_terminal){
                $('#terminal-select-location select').val(last_selected_terminal);
            }
            */
            if ($('#s_method_Itella_PARCEL_TERMINAL').length > 0){
                  var move_after = $('#s_method_Itella_PARCEL_TERMINAL').parents('tr'); 
              } else if ($('#label_method_PARCEL_TERMINAL_Itella').length > 0){
                  var move_after = $('#label_method_PARCEL_TERMINAL_Itella').parents('tr'); 
              }
              
              var terminals = this.parcelTerminals();
            if (terminals.length && move_after !== undefined && $('.itella-shipping-container').length == 0){
                
              $('<tr id = "terminal-select-location" ><td colspan = "4" style = "border-top: none; padding-top: 0px"><div id = "itella-map"></div></td></tr>').insertAfter(move_after);
             
              var itella = new itellaMapping(document.getElementById('itella-map'));
              itella
              // set base url where images are placed
              .setImagesUrl('images/')
              // configure translation
              .setStrings({nothing_found: 'Nieko nerasta', modal_header: 'Taškai'})
              // build HTML and register event handlers
              .init()

              // for search to work properly country iso2 code must be set (defaults to LT), empty string would allow global search
              .setCountry(quote.shippingAddress().countryId)
              // configure pickup points data (must adhere to pickup point data from itella-api)
              .setLocations(terminals, true)
              // to register function that does something when point is selected
              .registerCallback(function (manual) {
                // this gives full access to itella class
                console.log('is manual', manual); // tells if it was human interaction
                // selected point information, null if nothing is selected
                console.log(this.selectedPoint); 
                if (quote.shippingAddress().extensionAttributes == undefined) {
                    quote.shippingAddress().extensionAttributes = {};
                }
                quote.shippingAddress().extensionAttributes.itella_parcel_terminal = this.selectedPoint.id;
              });
              if (quote.shippingAddress().extensionAttributes !== undefined && quote.shippingAddress().extensionAttributes.itella_parcel_terminal !== undefined){
                itella.setSelection(quote.shippingAddress().extensionAttributes.itella_parcel_terminal);
              }
            }
        },
        initObservable: function () {
            this._super();
            this.showParcelTerminalSelection = ko.computed(function() {
                this.moveSelect();
                return this.parcelTerminals().length != 0
            }, this);

            this.selectedMethod = ko.computed(function() {
                this.moveSelect();
                var method = quote.shippingMethod();
                var selectedMethod = method != null ? method.carrier_code + '_' + method.method_code : null;
                return selectedMethod;
            }, this);

            quote.shippingMethod.subscribe(function(method) {
                this.moveSelect();
                var selectedMethod = method != null ? method.carrier_code + '_' + method.method_code : null;
                if (selectedMethod == 'Itella_PARCEL_TERMINAL') {
                    this.reloadParcelTerminals();
                }
            }, this);

            this.selectedParcelTerminal.subscribe(function(terminal) {
                /*
                //not needed on one step checkout, is done from overide
                if (quote.shippingAddress().extensionAttributes == undefined) {
                    quote.shippingAddress().extensionAttributes = {};
                }
                quote.shippingAddress().extensionAttributes.itella_parcel_terminal = terminal;
                */
            });
            
            return this;
        },

        setParcelTerminalList: function(list) {
            this.parcelTerminals(list);
            this.moveSelect();
        },
        
        reloadParcelTerminals: function() {
            parcelTerminalService.getParcelTerminalList(quote.shippingAddress(), this, 1);
            this.moveSelect();
        },

        getParcelTerminal: function() {
            var parcelTerminal;
            if (this.selectedParcelTerminal()) {
                for (var i in this.parcelTerminals()) {
                    var m = this.parcelTerminals()[i];
                    if (m.name == this.selectedParcelTerminal()) {
                        parcelTerminal = m;
                    }
                }
            }
            else {
                parcelTerminal = this.parcelTerminals()[0];
            }

            return parcelTerminal;
        },

        initSelector: function() {
            var startParcelTerminal = this.getParcelTerminal();
        }
    });
});