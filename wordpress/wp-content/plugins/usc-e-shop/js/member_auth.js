jQuery(document).ready( function($) {
	let member_auth = true;
	const urlParams = new URLSearchParams(window.location.search);
	if( urlParams.has('usces_page') ) {
		const usces_page = urlParams.get('usces_page');
		if( 'msa_setting' === usces_page ) {
			member_auth = false;
		} else if( 'mda_setting' === usces_page ) {
			member_auth = false;
		}
	}
	if( member_auth && $('#memberinfo form').has('table.customer_form') ) {
		$('#memberinfo form').has('table.customer_form').hide();
		$('#memberinfo h2 a[name="edit"]').parent().hide();
		$('#memberinfo h3 a[name="edit"]').parent().hide();
		$('#memberinfo #edit').hide();
		$('#memberinfo .error_message').hide();
		// $('#memberinfo').append('<div class="send"><input name="top" class="top" type="button" value="'+member_params.label.go2top+'" onclick="location.href=\''+member_params.url.go2top+'\'" /></div>');
		if( $('.member_submenu').length > 0 ) {
			if( $('.member_submenu .edit_member').length > 0 ) {
				$('.member_submenu .edit_member').each(function() {
					$(this).addClass('member-edit');
				});
				$('.member-edit a').attr('href',member_params.url.edit);
			} else if( $('.member_submenu .member-edit').length === 0 ) {
				$('.member_submenu').prepend('<li class="member-edit"><a href="'+member_params.url.edit+'">'+member_params.label.edit+'</a></li>');
			} else {
				$('.member-edit a').attr('href',member_params.url.edit);
			}
		} else if( $('.member-submenu').length > 0 ) {
			if( $('.member-submenu .member-edit').length === 0 ) {
				$('.member-submenu').prepend('<li class="member-edit"><a href="'+member_params.url.edit+'">'+member_params.label.edit+'</a></li>');
			} else {
				$('.member-edit a').attr('href',member_params.url.edit);
			}
		}
		if( $('.p-wc-mypage').find('h2.p-wc-headline').length > 0 ) {
			$('.p-wc-headline').hide();
			$('.p-wc-error_message').hide();
			$('form').has('table.p-wc-customer_form').hide();
			if( $('.p-wc-member_submenu').length > 0 ) {
				if( $('.p-wc-member_submenu .member-edit').length === 0 ) {
					$('.p-wc-member_submenu').prepend('<li class="member-edit"><a href="'+member_params.url.edit+'">'+member_params.label.edit+'</a></li>');
				} else if( $('.member-edit').length > 0 ) {
					$('.member-edit a').attr('href',member_params.url.edit);
				} else {
					$('.p-wc-member-edit a').attr('href',member_params.url.edit);
				}
			}
		}
	}

	$('.member-edit').on('click', function(event) {
		if( member_params.edit_auth.length == 0 ) {
			event.preventDefault();
			let userConfirmed = confirm(member_params.message.edit);
			if (userConfirmed) {
				window.location.href = member_params.url.edit;
			}
		}
	});

	$('.settlement-update').on('click', function(event) {
		if( member_params.edit_auth.length == 0 ) {
			event.preventDefault();
			let userConfirmed = confirm(member_params.message.card_upd);
			if (userConfirmed) {
				window.location.href = member_params.url.card_upd;
			}
		}
	});

	$('.settlement-register').on('click', function(event) {
		if( member_params.edit_auth.length == 0 ) {
			event.preventDefault();
			let userConfirmed = confirm(member_params.message.card_reg);
			if (userConfirmed) {
				window.location.href = member_params.url.card_reg;
			}
		}
	});
});

document.addEventListener('DOMContentLoaded', (event) => {
	const passwordField1 = document.getElementById('password1');
	const passwordField2 = document.getElementById('password2');
	if( passwordField1 ) {
		passwordField1.addEventListener('focus', (e) => {
			passwordField1.removeAttribute('readonly');
		});
	}
	if( passwordField2 ) {
		passwordField2.addEventListener('focus', (e) => {
			passwordField2.removeAttribute('readonly');
		});
	}
});

jQuery.event.add( window, 'load', function() {
	const update = document.querySelector('#memberedit #update');
	if( update ) {
		if( update.value === 'update' ) {
			const errorDiv = document.querySelector('#memberedit .error_message');
			const hasContent = errorDiv.textContent.trim() !== '';
			if( ! hasContent ) {
				alert(member_params.message.done);
			}
		}
	}
});
