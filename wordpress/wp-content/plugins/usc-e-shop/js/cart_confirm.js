// JavaScript
jQuery(function($) {

	var wc_check = 'same';
	var intervalId = setInterval(function(){
		if (typeof uscesL10n === "undefined" || typeof uscesL10n.cart_url === "undefined") {
			clearInterval(intervalId);
			return false;
		}

		if( 'same' != wc_check ){
			if( 'different' == wc_check || 'timeover' == wc_check || 'entrydiff' == wc_check ){
				alert( uscesL10n.check_mes );
				location.href = uscesL10n.cart_url;
			}else{
				return false;
			}
		}
		wc2confirm.check();

	}, 10000);


	wc2confirm = {
		settings: {
			url: uscesL10n.ajaxurl,
			type: 'POST',
			cache: false,
			data: {}
		},
		
		check : function() {
			if (typeof uscesL10n === "undefined" || typeof uscesL10n.cart_url === "undefined") {
				return false;
			}
			var s = wc2confirm.settings;
			s.data = { 
				'action' : 'welcart_confirm_check',
				'uscesid' : uscesL10n.uscesid,
				'wc_condition' : uscesL10n.condition,
				'wc_nonce' : uscesL10n.wc_nonce
			};
			$.ajax( s ).done(function( response ){
				data = response.data.replace(/(^\s+)|(\s+$)|(^\r\n)|(\r\n$)|(^\n+)|(\n+$)/g, "");
				wc_check = data;
			}).fail(function( msg ){
				console.log( msg );
			});
			return false;
		}
	};
});

