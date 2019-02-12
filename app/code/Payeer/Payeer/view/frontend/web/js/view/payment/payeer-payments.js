define([
	'uiComponent',
	'Magento_Checkout/js/model/payment/renderer-list'
],
function(Component, rendererList){
	'use strict';
	
	rendererList.push({
		type: 'payeer',
		component: 'Payeer_Payeer/js/view/payment/method-renderer/payeer-method'
	});

	return Component.extend({});
});