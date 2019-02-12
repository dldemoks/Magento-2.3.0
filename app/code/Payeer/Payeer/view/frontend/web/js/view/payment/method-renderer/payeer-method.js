define([
	'ko',
	'Magento_Checkout/js/view/payment/default',
	'mage/url'
],
function(ko, Component, url){
	'use strict';

	return Component.extend({
		defaults: {
			template: 'Payeer_Payeer/payment/payeer',
			redirectAfterPlaceOrder: false
		},
		getInstructions: function(){},
		afterPlaceOrder: function(){
			window.location.replace(url.build('payeer/url/redirect/'));
		}
	});
});
