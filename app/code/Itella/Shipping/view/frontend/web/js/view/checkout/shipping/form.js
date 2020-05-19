define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Itella_Shipping/js/view/checkout/shipping/parcel-terminal-service',
    'mage/translate',
    'mage/url',
    'Itella_Shipping/js/leaflet',
    'Itella_Shipping/js/itella-mapping',
], function ($, ko, Component, quote, shippingService, parcelTerminalService, t, url) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Itella_Shipping/checkout/shipping/form'
        },

        initialize: function (config) {
            this.parcelTerminals = ko.observableArray();
            this.selectedParcelTerminal = ko.observable();
            this._super();
            this.itella = null;
            
        },
        hideSelect: function () {
            var method = quote.shippingMethod();
            var selectedMethod = method != null ? method.carrier_code + '_' + method.method_code : null;
            //console.log(selectedMethod);
            if (selectedMethod == 'itella_PARCEL_TERMINAL') {
                $('#terminal-select-location').show();
            } else {
                $('#terminal-select-location').hide();
                //console.log('hide');
            }
        },
        
        setLogos: function(){
            var img = '<img src = "'+require.toUrl('Itella_Shipping/images/')+'/logo.png" style = "height:30px;"/>';
            if ($('#label_carrier_PARCEL_TERMINAL_itella') !== undefined && $('#label_carrier_PARCEL_TERMINAL_itella img').length === 0){
                $('#label_carrier_PARCEL_TERMINAL_itella').html(img);
            }
            if ($('#label_carrier_COURIER_itella') !== undefined && $('#label_carrier_COURIER_itella img').length === 0){
                $('#label_carrier_COURIER_itella').html(img);
            }
        },
        
        moveSelect: function () {
            this.setLogos();
          //$('#checkout-shipping-method-load input:radio:not(.bound)').addClass('bound').bind('click', this.hideSelect());
            
          
            if ($('#s_method_itella_PARCEL_TERMINAL').length > 0){
                  var move_after = $('#s_method_itella_PARCEL_TERMINAL').parents('tr'); 
              } else if ($('#label_method_PARCEL_TERMINAL_itella').length > 0){
                  var move_after = $('#label_method_PARCEL_TERMINAL_itella').parents('tr'); 
              }
            //$('#terminal-select-location').remove();
            
            var terminals = this.parcelTerminals();
            if ( terminals.length && move_after !== undefined && $('.itella-shipping-container').length == 0){
                
              //$('#itella-helper-container .terminal-select-location').insertAfter(move_after);
              let row = $('<tr/>');
              let col = $('<td/>', {colspan: "4"});
              let div = $('<div/>', {id: "itella-map" });
              col.append(div);
              row.append(col);
              //row.after(move_after);
              let map_container = $('.terminal-select-location.helper').clone();
              map_container.removeClass('helper').attr('id','itella-map-container').find('.itella-map').attr('id','itella-map');
              map_container.insertAfter(move_after);
              
              var btn = $('#shipping-method-buttons-container button.continue');
              this.itella = new itellaMapping(document.getElementById('itella-map'));
              this.itella
              // set base url where images are placed
              .setImagesUrl(require.toUrl('Itella_Shipping/images/'))
              // configure translation
              .setStrings({
                  modal_header: $.mage.__('Pickup points'),
                  selector_header: $.mage.__('Pickup point'),
                  workhours_header: $.mage.__('Workhours'),
                  contacts_header: $.mage.__('Contacts'),
                  search_placeholder: $.mage.__('Enter postcode/address'),
                  select_pickup_point: $.mage.__('Select a pickup point'),
                  no_pickup_points: $.mage.__('No points to select'),
                  select_btn: $.mage.__('select'),
                  back_to_list_btn: $.mage.__('reset search'),
                  nothing_found: $.mage.__('Nothing found'),
                  select_pickup_point_btn: $.mage.__('Select pickup point'),
                  no_information: $.mage.__('No information'),
                  error_leaflet: $.mage.__('Leaflet is required for Itella-Mapping'),
                  error_missing_mount_el: $.mage.__('No mount supplied to itellaShipping')
              })
              // build HTML and register event handlers
              .init()

              // for search to work properly country iso2 code must be set (defaults to LT), empty string would allow global search
              .setCountry(quote.shippingAddress().countryId)
              // configure pickup points data (must adhere to pickup point data from itella-api)
              .setLocations(terminals, true)
              // to register function that does something when point is selected
              .registerCallback(function (manual) {
                // this gives full access to itella class
                //console.log('is manual', manual); // tells if it was human interaction
                // selected point information, null if nothing is selected
                //console.log(this.selectedPoint); 
                if (!this.selectedPoint){
                  btn.addClass('disabled');
                  } else {
                     btn.removeClass('disabled'); 
                  }
                if (quote.shippingAddress().extensionAttributes == undefined) {
                    quote.shippingAddress().extensionAttributes = {};
                }
                quote.shippingAddress().extensionAttributes.itella_parcel_terminal = this.selectedPoint.pupCode;
              });
              if (quote.shippingAddress().extensionAttributes !== undefined && quote.shippingAddress().extensionAttributes.itella_parcel_terminal !== undefined){
                this.itella.setSelection(quote.shippingAddress().extensionAttributes.itella_parcel_terminal);
              }
              if (!this.itella.selectedPoint){
                  btn.addClass('disabled');
              } else {
                 btn.removeClass('disabled'); 
              }
            } else {
                console.log("Itella map not loaded");
            }
        },
        initObservable: function () {
            this._super();
            this.showParcelTerminalSelection = ko.computed(function() {
                //this.moveSelect();
                return this.parcelTerminals().length != 0
            }, this);

            this.selectedMethod = ko.computed(function() {
                //this.moveSelect();
                var method = quote.shippingMethod();
                var selectedMethod = method != null ? method.carrier_code + '_' + method.method_code : null;
                if (selectedMethod != 'itella_PARCEL_TERMINAL') {
                    $('#shipping-method-buttons-container button.continue.disabled').removeClass('disabled');
                    $('#itella-map-container').hide();
                } else if (this.itella){
                    $('#itella-map-container').show();
                    if (!this.itella.selectedPoint){
                        $('#shipping-method-buttons-container button.continue').addClass('disabled');
                    }
                }
                return selectedMethod;
                
            }, this);

            quote.shippingMethod.subscribe(function(method) {
                this.moveSelect();
                var selectedMethod = method != null ? method.carrier_code + '_' + method.method_code : null;
                if (selectedMethod == 'itella_PARCEL_TERMINAL') {
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