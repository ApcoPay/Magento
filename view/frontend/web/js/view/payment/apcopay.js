
/*browser:true*/
/*global define*/
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'apcopay',
                component: 'Apcopay_Magento/js/view/payment/method-renderer/apcopay-method'
            }
        );
        /** Add view logic here if needed */
        return Component.extend({});
    }
);