define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component,
              rendererList) {
        'use strict';
        rendererList.push(
            {
                type: 'netgiro',
                component: 'netgiro_gateway/js/view/payment/method-renderer/netgiro-method'
            }
        );

        return Component.extend({});
    }
);