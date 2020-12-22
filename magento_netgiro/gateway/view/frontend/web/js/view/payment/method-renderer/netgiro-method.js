define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        //'Magento_Customer/js/customer-data',
        'netgiro_gateway/js/form_builder',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/full-screen-loader',
    ],
    //customerData
    function ($, Component, url, formBuilder, errorProcessor, fullScreenLoader) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'netgiro_gateway/payment/netgiro',
                redirectAfterPlaceOrder: false
            },
            getCode: function() {
                return 'netgiro';
            },

            afterPlaceOrder: function (data, event) {
                var custom_controller_url = url.build('payment/form/index');
                $.post(custom_controller_url, 'json')
                    .done(function (response) {
                        //customerData.invalidate(['cart']);
                        formBuilder(response).submit();
                    })
                    .fail(function (response) {
                        errorProcessor.process(response, this.messageContainer);
                    })
                    .always(function () {
                        fullScreenLoader.stopLoader();
                    });
            },
        });
    }
);