<?php
/**
 * SBペイメントサービス
 *
 * @package  Welcart
 * @author   Collne Inc.
 * @version  1.0.0
 * @since    1.9.16
 */
class SBPS_SETTLEMENT extends SBPS_MAIN {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * 自動継続課金処理結果メール
	 *
	 * @var array
	 */
	protected $continuation_charging_mail;

	/**
	 * Construct.
	 */
	public function __construct() {

		$this->acting_name        = 'SBPS';
		$this->acting_formal_name = 'SBペイメントサービス';

		$this->acting_card    = 'sbps_card';
		$this->acting_conv    = 'sbps_conv';
		$this->acting_payeasy = 'sbps_payeasy';
		$this->acting_wallet  = 'sbps_wallet';
		$this->acting_mobile  = 'sbps_mobile';
		$this->acting_paypay  = 'sbps_paypay';

		$this->acting_flg_card    = 'acting_sbps_card';
		$this->acting_flg_conv    = 'acting_sbps_conv';
		$this->acting_flg_payeasy = 'acting_sbps_payeasy';
		$this->acting_flg_wallet  = 'acting_sbps_wallet';
		$this->acting_flg_mobile  = 'acting_sbps_mobile';
		$this->acting_flg_paypay  = 'acting_sbps_paypay';

		$this->pay_method = array(
			'acting_sbps_card',
			'acting_sbps_conv',
			'acting_sbps_payeasy',
			'acting_sbps_wallet',
			'acting_sbps_mobile',
			'acting_sbps_paypay',
		);

		parent::__construct( 'sbps' );

		if ( $this->is_activate_card() || $this->is_activate_paypay() ) {
			add_action( 'usces_after_cart_instant', array( $this, 'acting_notice' ) );
			if ( is_admin() ) {
				add_action( 'usces_action_admin_ajax', array( $this, 'admin_ajax' ) );
				add_filter( 'usces_filter_orderlist_detail_value', array( $this, 'orderlist_settlement_status' ), 10, 4 );
				add_action( 'usces_action_order_edit_form_status_block_middle', array( $this, 'settlement_status' ), 10, 3 );
				add_action( 'usces_action_order_edit_form_settle_info', array( $this, 'settlement_information' ), 10, 2 );
				add_action( 'usces_action_endof_order_edit_form', array( $this, 'settlement_dialog' ), 10, 2 );
			}
		}

		if ( $this->is_validity_acting( 'card' ) ) {
			add_filter( 'usces_filter_delete_member_check', array( $this, 'delete_member_check' ), 10, 2 );
			add_filter( 'usces_filter_delivery_secure_form_howpay', array( $this, 'delivery_secure_form_howpay' ) );
			add_filter( 'usces_filter_template_redirect', array( $this, 'member_update_settlement' ), 1 );
			add_action( 'usces_action_member_submenu_list', array( $this, 'e_update_settlement' ) );
			add_filter( 'usces_filter_member_submenu_list', array( $this, 'update_settlement' ), 10, 2 );

			/* WCEX DL Seller */
			if ( defined( 'WCEX_DLSELLER' ) ) {
				add_filter( 'usces_filter_the_continue_payment_method', array( $this, 'continuation_payment_method' ) );
				add_filter( 'dlseller_filter_first_charging', array( $this, 'first_charging_date' ), 9, 5 );
				add_filter( 'dlseller_filter_the_payment_method_restriction', array( $this, 'payment_method_restriction' ), 10, 2 );
				add_filter( 'dlseller_filter_continue_member_list_condition', array( $this, 'continue_member_list_condition' ), 10, 4 );
				add_action( 'dlseller_action_continue_member_list_page', array( $this, 'continue_member_list_page' ) );
				add_action( 'dlseller_action_do_continuation_charging', array( $this, 'auto_continuation_charging' ), 10, 4 );
				add_action( 'dlseller_action_do_continuation', array( $this, 'do_auto_continuation' ), 10, 2 );
				add_filter( 'dlseller_filter_reminder_mail_body', array( $this, 'reminder_mail_body' ), 10, 3 );
				add_filter( 'dlseller_filter_contract_renewal_mail_body', array( $this, 'contract_renewal_mail_body' ), 10, 3 );
			}

			/* WCEX Auto Delivery */
			if ( defined( 'WCEX_AUTO_DELIVERY' ) ) {
				add_filter( 'wcad_filter_shippinglist_acting', array( $this, 'set_shippinglist_acting' ) );
				add_filter( 'wcad_filter_available_regular_payment_method', array( $this, 'available_regular_payment_method' ) );
				add_filter( 'wcad_filter_the_payment_method_restriction', array( $this, 'payment_method_restriction' ), 10, 2 );
				add_action( 'wcad_action_reg_auto_orderdata', array( $this, 'register_auto_orderdata' ) );
			}
		}

		$this->initialize_data();
	}

	/**
	 * Return an instance of this class.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize
	 */
	public function initialize_data() {
		$options = get_option( 'usces', array() );

		$options['acting_settings']['sbps']['merchant_id']          = ( isset( $options['acting_settings']['sbps']['merchant_id'] ) ) ? $options['acting_settings']['sbps']['merchant_id'] : '';
		$options['acting_settings']['sbps']['service_id']           = ( isset( $options['acting_settings']['sbps']['service_id'] ) ) ? $options['acting_settings']['sbps']['service_id'] : '';
		$options['acting_settings']['sbps']['hash_key']             = ( isset( $options['acting_settings']['sbps']['hash_key'] ) ) ? $options['acting_settings']['sbps']['hash_key'] : '';
		$options['acting_settings']['sbps']['ope']                  = ( isset( $options['acting_settings']['sbps']['ope'] ) ) ? $options['acting_settings']['sbps']['ope'] : '';
		$options['acting_settings']['sbps']['send_url']             = ( isset( $options['acting_settings']['sbps']['send_url'] ) ) ? $options['acting_settings']['sbps']['send_url'] : '';
		$options['acting_settings']['sbps']['send_url_check']       = ( isset( $options['acting_settings']['sbps']['send_url_check'] ) ) ? $options['acting_settings']['sbps']['send_url_check'] : '';
		$options['acting_settings']['sbps']['send_url_test']        = ( isset( $options['acting_settings']['sbps']['send_url_test'] ) ) ? $options['acting_settings']['sbps']['send_url_test'] : '';
		$options['acting_settings']['sbps']['card_activate']        = ( isset( $options['acting_settings']['sbps']['card_activate'] ) ) ? $options['acting_settings']['sbps']['card_activate'] : 'off';
		$options['acting_settings']['sbps']['3d_secure']            = ( isset( $options['acting_settings']['sbps']['3d_secure'] ) ) ? $options['acting_settings']['sbps']['3d_secure'] : 'off';
		$options['acting_settings']['sbps']['cust_manage']          = ( isset( $options['acting_settings']['sbps']['cust_manage'] ) ) ? $options['acting_settings']['sbps']['cust_manage'] : 'off';
		$options['acting_settings']['sbps']['sales']                = ( isset( $options['acting_settings']['sbps']['sales'] ) ) ? $options['acting_settings']['sbps']['sales'] : 'manual';
		$options['acting_settings']['sbps']['sales_dlseller']       = ( isset( $options['acting_settings']['sbps']['sales_dlseller'] ) ) ? $options['acting_settings']['sbps']['sales_dlseller'] : 'manual';
		$options['acting_settings']['sbps']['auto_settlement_mail'] = ( isset( $options['acting_settings']['sbps']['auto_settlement_mail'] ) ) ? $options['acting_settings']['sbps']['auto_settlement_mail'] : 'off';
		$options['acting_settings']['sbps']['3des_key']             = ( isset( $options['acting_settings']['sbps']['3des_key'] ) ) ? $options['acting_settings']['sbps']['3des_key'] : '';
		$options['acting_settings']['sbps']['3desinit_key']         = ( isset( $options['acting_settings']['sbps']['3desinit_key'] ) ) ? $options['acting_settings']['sbps']['3desinit_key'] : '';
		$options['acting_settings']['sbps']['basic_id']             = ( isset( $options['acting_settings']['sbps']['basic_id'] ) ) ? $options['acting_settings']['sbps']['basic_id'] : '';
		$options['acting_settings']['sbps']['basic_password']       = ( isset( $options['acting_settings']['sbps']['basic_password'] ) ) ? $options['acting_settings']['sbps']['basic_password'] : '';
		$options['acting_settings']['sbps']['conv_activate']        = ( isset( $options['acting_settings']['sbps']['conv_activate'] ) ) ? $options['acting_settings']['sbps']['conv_activate'] : 'off';
		$options['acting_settings']['sbps']['conv_limit']           = ( isset( $options['acting_settings']['sbps']['conv_limit'] ) ) ? $options['acting_settings']['sbps']['conv_limit'] : '';
		$options['acting_settings']['sbps']['payeasy_activate']     = ( isset( $options['acting_settings']['sbps']['payeasy_activate'] ) ) ? $options['acting_settings']['sbps']['payeasy_activate'] : 'off';
		$options['acting_settings']['sbps']['wallet_yahoowallet']   = ( isset( $options['acting_settings']['sbps']['wallet_yahoowallet'] ) ) ? $options['acting_settings']['sbps']['wallet_yahoowallet'] : 'off';
		$options['acting_settings']['sbps']['wallet_rakuten']       = ( isset( $options['acting_settings']['sbps']['wallet_rakuten'] ) ) ? $options['acting_settings']['sbps']['wallet_rakuten'] : 'off';
		$options['acting_settings']['sbps']['wallet_rakutenv2']     = ( isset( $options['acting_settings']['sbps']['wallet_rakutenv2'] ) ) ? $options['acting_settings']['sbps']['wallet_rakutenv2'] : 'off';
		$options['acting_settings']['sbps']['wallet_paypal']        = ( isset( $options['acting_settings']['sbps']['wallet_paypal'] ) ) ? $options['acting_settings']['sbps']['wallet_paypal'] : 'off';
		$options['acting_settings']['sbps']['wallet_netmile']       = 'off';
		$options['acting_settings']['sbps']['wallet_alipay']        = ( isset( $options['acting_settings']['sbps']['wallet_alipay'] ) ) ? $options['acting_settings']['sbps']['wallet_alipay'] : 'off';
		$options['acting_settings']['sbps']['wallet_activate']      = ( isset( $options['acting_settings']['sbps']['wallet_activate'] ) ) ? $options['acting_settings']['sbps']['wallet_activate'] : 'off';
		$options['acting_settings']['sbps']['mobile_docomo']        = ( isset( $options['acting_settings']['sbps']['mobile_docomo'] ) ) ? $options['acting_settings']['sbps']['mobile_docomo'] : 'off';
		$options['acting_settings']['sbps']['mobile_auone']         = ( isset( $options['acting_settings']['sbps']['mobile_auone'] ) ) ? $options['acting_settings']['sbps']['mobile_auone'] : 'off';
		$options['acting_settings']['sbps']['mobile_mysoftbank']    = 'off';
		$options['acting_settings']['sbps']['mobile_softbank2']     = ( isset( $options['acting_settings']['sbps']['mobile_softbank2'] ) ) ? $options['acting_settings']['sbps']['mobile_softbank2'] : 'off';
		$options['acting_settings']['sbps']['mobile_activate']      = ( isset( $options['acting_settings']['sbps']['mobile_activate'] ) ) ? $options['acting_settings']['sbps']['mobile_activate'] : 'off';
		$options['acting_settings']['sbps']['paypay_activate']      = ( isset( $options['acting_settings']['sbps']['paypay_activate'] ) ) ? $options['acting_settings']['sbps']['paypay_activate'] : 'off';
		$options['acting_settings']['sbps']['paypay_sales']         = ( isset( $options['acting_settings']['sbps']['paypay_sales'] ) ) ? $options['acting_settings']['sbps']['paypay_sales'] : 'manual';
		update_option( 'usces', $options );

		$available_settlement = get_option( 'usces_available_settlement', array() );
		if ( ! in_array( 'sbps', $available_settlement ) ) {
			$available_settlement['sbps'] = $this->acting_formal_name;
			update_option( 'usces_available_settlement', $available_settlement );
		}

		$noreceipt_status = get_option( 'usces_noreceipt_status', array() );
		if ( ! in_array( 'acting_sbps_conv', $noreceipt_status ) || ! in_array( 'acting_sbps_payeasy', $noreceipt_status ) ) {
			$noreceipt_status[] = 'acting_sbps_conv';
			$noreceipt_status[] = 'acting_sbps_payeasy';
			update_option( 'usces_noreceipt_status', $noreceipt_status );
		}

		$this->unavailable_method = array( 'acting_dsk_card', 'acting_dsk_conv', 'acting_dsk_payeasy' );
	}

	/**
	 * Admin script.
	 * admin_print_footer_scripts
	 */
	public function admin_scripts() {
		global $usces;

		$admin_page = ( isset( $_GET['page'] ) ) ? wp_unslash( $_GET['page'] ) : '';
		switch ( $admin_page ) :
			case 'usces_settlement':
				$settlement_selected = get_option( 'usces_settlement_selected', array() );
				if ( in_array( $this->paymod_id, (array) $settlement_selected ) ) :
					$acting_opts = $this->get_acting_settings();
					?>
<script type="text/javascript">
jQuery( document ).ready( function( $ ) {
	var sbps_card_activate = "<?php echo esc_js( $acting_opts['card_activate'] ); ?>";
	var conv_activate = "<?php echo esc_js( $acting_opts['conv_activate'] ); ?>";
	var sbps_paypay_activate = "<?php echo esc_js( $acting_opts['paypay_activate'] ); ?>";
	if ( "token" == sbps_card_activate ) {
		$( ".card_link_sbps" ).css( "display", "none" );
		$( ".card_link_token_sbps" ).css( "display", "" );
		$( ".card_token_sbps" ).css( "display", "" );
	} else if ( "on" == sbps_card_activate ) {
		$( ".card_link_sbps" ).css( "display", "" );
		$( ".card_link_token_sbps" ).css( "display", "" );
		$( ".card_token_sbps" ).css( "display", "none" );
	} else {
		$( ".card_link_sbps" ).css( "display", "none" );
		$( ".card_link_token_sbps" ).css( "display", "none" );
		$( ".card_token_sbps" ).css( "display", "none" );
	}

	$( document ).on( "change", ".card_activate_sbps", function() {
		if ( "token" == $( this ).val() ) {
			$( ".card_link_sbps" ).css( "display", "none" );
			$( ".card_link_token_sbps" ).css( "display", "" );
			$( ".card_token_sbps" ).css( "display", "" );
		} else if ( "on" == $( this ).val() ) {
			$( ".card_link_sbps" ).css( "display", "" );
			$( ".card_link_token_sbps" ).css( "display", "" );
			$( ".card_token_sbps" ).css( "display", "none" );
		} else {
			$( ".card_link_sbps" ).css( "display", "none" );
			$( ".card_link_token_sbps" ).css( "display", "none" );
			$( ".card_token_sbps" ).css( "display", "none" );
		}
	});

	if ( "on" == conv_activate ) {
		$( ".conv_sbps" ).css( "display", "" );
	} else {
		$( ".conv_sbps" ).css( "display", "none" );
	}

	$( document ).on( "change", ".conv_activate_sbps", function() {
		if( "on" == $( this ).val() ) {
			$( ".conv_sbps" ).css( "display", "" );
		} else {
			$( ".conv_sbps" ).css( "display", "none" );
		}
	});

	if ( "on" == sbps_paypay_activate ) {
		$( ".paypay_sbps" ).css( "display", "" );
	} else {
		$( ".paypay_sbps" ).css( "display", "none" );
	}

	$( document ).on( "change", ".paypay_activate_sbps", function() {
		if ( "on" == $( this ).val() ) {
			$( ".paypay_sbps" ).css( "display", "" );
		} else {
			$( ".paypay_sbps" ).css( "display", "none" );
		}
	});
});
</script>
					<?php
				endif;
				break;

			case 'usces_orderlist':
			case 'usces_continue':
				$acting_flg   = '';
				$dialog_title = '';
				$order_id     = '';
				$order_data   = array();

				$order_action    = ( isset( $_GET['order_action'] ) ) ? wp_unslash( $_GET['order_action'] ) : '';
				$continue_action = ( isset( $_GET['continue_action'] ) ) ? wp_unslash( $_GET['continue_action'] ) : '';

				/* 受注編集画面・継続課金会員詳細画面 */
				if ( ( 'usces_orderlist' === $admin_page && ( 'edit' === $order_action || 'editpost' === $order_action || 'newpost' === $order_action ) ) ||
					( 'usces_continue' === $admin_page && 'settlement_sbps_card' === $continue_action ) ) {
					$order_id = ( isset( $_REQUEST['order_id'] ) ) ? wp_unslash( $_REQUEST['order_id'] ) : '';
					if ( ! empty( $order_id ) ) {
						$order_data = $usces->get_order_data( $order_id, 'direct' );
						$payment    = usces_get_payments_by_name( $order_data['order_payment_name'] );
						if ( isset( $payment['settlement'] ) ) {
							$acting_flg = $payment['settlement'];
						}
						if ( isset( $payment['name'] ) ) {
							$dialog_title = $payment['name'];
						}
					}
				}
				$args = compact( 'order_id', 'acting_flg', 'admin_page', 'order_data' );

				if ( 'acting_sbps_card' === $acting_flg || 'acting_sbps_paypay' === $acting_flg ) :
					$acting_opts = $this->get_acting_settings();
					?>
<script type="text/javascript">
jQuery( document ).ready( function( $ ) {
	adminOrderEdit = {
					<?php
					/* クレジットカード */
					if ( 'acting_sbps_card' === $acting_flg ) :
						?>
		getSettlementInfoCard : function() {
			$( "#settlement-response" ).html( "" );
			$( "#settlement-response-loading" ).html( '<img src="' + uscesL10n.USCES_PLUGIN_URL + '/images/loading.gif" />' );
			var mode = ( "" != $( "#error" ).val() ) ? "error_sbps_card" : "get_sbps_card";
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					action: "usces_admin_ajax",
					mode: mode,
					order_id: $( "#order_id" ).val(),
					tracking_id: $( "#tracking_id" ).val(),
					member_id: $( "#member_id" ).val(),
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				$( "#settlement-response" ).html( retVal.result );
				if ( $( "#refund-settlement" ).length ) {
					$( "#refund-settlement" ).prop( "disabled", true );
				}
				$( "#settlement-response-loading" ).html( "" );
			}).fail( function( jqXHR, textStatus, errorThrown ) {
				console.log( textStatus );
				console.log( jqXHR.status );
				console.log( errorThrown.message );
				$( "#settlement-response-loading" ).html( "" );
			});
			return false;
		},
		manualSettlementCard : function( amount, trans_id ) {
			$( "#settlement-response" ).html( "" );
			$( "#settlement-response-loading" ).html( '<img src="' + uscesL10n.USCES_PLUGIN_URL + '/images/loading.gif" />' );
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					action: "usces_admin_ajax",
					mode: "manual_sbps_card",
					order_id: $( "#order_id" ).val(),
					order_num: $( "#order_num" ).val(),
					member_id: $( "#member_id" ).val(),
					amount: amount,
					trans_id: trans_id,
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				$( "#settlement-response" ).html( retVal.result );
				if ( "OK" == retVal.status && 0 < retVal.acting_status.length ) {
					$( "#settlement-status" ).html( retVal.acting_status );
					if ( undefined != $( "#settlement-status-"+$( "#order_num" ).val() ) ) {
						$( "#settlement-status-"+$( "#order_num" ).val() ).html( retVal.acting_status );
					}
					if ( undefined != $( "#settlement-amount-"+$( "#order_num" ).val() ) ) {
						$( "#settlement-amount-"+$( "#order_num" ).val() ).html( amount );
					}
				}
				if ( $( "#refund-settlement" ).length ) {
					$( "#refund-settlement" ).prop( "disabled", true );
				}
				if ( 0 < retVal.tracking_id.length ) {
					$( "#tracking_id" ).val( retVal.tracking_id );
					if ( undefined != $( "#settlement-tracking_id-"+$( "#order_num" ).val() ) ) {
						$( "#settlement-tracking_id-"+$( "#order_num" ).val() ).html( retVal.tracking_id );
					}
					if ( undefined != $( "#settlement-information-"+trans_id ) ) {
						$( "#settlement-information-"+trans_id ).attr( "data-tracking_id", retVal.tracking_id );
						$( "#settlement-information-"+trans_id ).attr( "id", "settlement-information-"+retVal.tracking_id );
					}
				}
				$( "#settlement-response-loading" ).html( "" );
			}).fail( function( jqXHR, textStatus, errorThrown ) {
				console.log( textStatus );
				console.log( jqXHR.status );
				console.log( errorThrown.message );
				$( "#settlement-response-loading" ).html( "" );
			});
			return false;
		},
		salesSettlementCard : function( amount ) {
			$( "#settlement-response" ).html( "" );
			$( "#settlement-response-loading" ).html( '<img src="' + uscesL10n.USCES_PLUGIN_URL + '/images/loading.gif" />' );
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					action: "usces_admin_ajax",
					mode: "sales_sbps_card",
					order_id: $( "#order_id" ).val(),
					tracking_id: $( "#tracking_id" ).val(),
					member_id: $( "#member_id" ).val(),
					amount: amount,
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				$( "#settlement-response" ).html( retVal.result );
				if ( "OK" == retVal.status && 0 < retVal.acting_status.length ) {
					$( "#settlement-status" ).html( retVal.acting_status );
					if ( undefined != $( "#settlement-status-"+$( "#order_num" ).val() ) ) {
						$( "#settlement-status-"+$( "#order_num" ).val() ).html( retVal.acting_status );
					}
				}
				if ( $( "#refund-settlement" ).length ) {
					$( "#refund-settlement" ).prop( "disabled", true );
				}
				$( "#settlement-response-loading" ).html( "" );
			}).fail( function( jqXHR, textStatus, errorThrown ) {
				console.log( textStatus );
				console.log( jqXHR.status );
				console.log( errorThrown.message );
				$( "#settlement-response-loading" ).html( "" );
			});
			return false;
		},
		cancelSettlementCard : function() {
			$( "#settlement-response" ).html( "" );
			$( "#settlement-response-loading" ).html( '<img src="' + uscesL10n.USCES_PLUGIN_URL + '/images/loading.gif" />' );
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					action: "usces_admin_ajax",
					mode: "cancel_sbps_card",
					order_id: $( "#order_id" ).val(),
					tracking_id: $( "#tracking_id" ).val(),
					member_id: $( "#member_id" ).val(),
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				$( "#settlement-response" ).html( retVal.result );
				if ( "OK" == retVal.status && 0 < retVal.acting_status.length ) {
					$( "#settlement-status" ).html( retVal.acting_status );
					if ( undefined != $( "#settlement-status-"+$( "#order_num" ).val() ) ) {
						$( "#settlement-status-"+$( "#order_num" ).val() ).html( retVal.acting_status );
					}
				}
				if ( $( "#refund-settlement" ).length ) {
					$( "#refund-settlement" ).prop( "disabled", true );
				}
				$( "#settlement-response-loading" ).html( "" );
			}).fail( function( jqXHR, textStatus, errorThrown ) {
				console.log( textStatus );
				console.log( jqXHR.status );
				console.log( errorThrown.message );
				$( "#settlement-response-loading" ).html( "" );
			});
			return false;
		},
		refundSettlementCard : function( amount ) {
			$( "#settlement-response" ).html( "" );
			$( "#settlement-response-loading" ).html( '<img src="' + uscesL10n.USCES_PLUGIN_URL + '/images/loading.gif" />' );
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					action: "usces_admin_ajax",
					mode: "refund_sbps_card",
					order_id: $( "#order_id" ).val(),
					tracking_id: $( "#tracking_id" ).val(),
					member_id: $( "#member_id" ).val(),
					amount: amount,
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				$( "#settlement-response" ).html( retVal.result );
				if ( "OK" == retVal.status && 0 < retVal.acting_status.length ) {
					$( "#settlement-status" ).html( retVal.acting_status );
					if ( undefined != $( "#settlement-status-"+$( "#order_num" ).val() ) ) {
						$( "#settlement-status-"+$( "#order_num" ).val() ).html( retVal.acting_status );
					}
				}
				if ( $( "#refund-settlement" ).length ) {
					$( "#refund-settlement" ).prop( "disabled", true );
				}
				$( "#settlement-response-loading" ).html( "" );
			}).fail( function( jqXHR, textStatus, errorThrown ) {
				console.log( textStatus );
				console.log( jqXHR.status );
				console.log( errorThrown.message );
				$( "#settlement-response-loading" ).html( "" );
			});
			return false;
		},
						<?php
					/* PayPay */
					elseif ( 'acting_sbps_paypay' === $acting_flg ) :
						?>
		getSettlementInfoPayPay : function() {
			$( "#settlement-response" ).html( "" );
			$( "#settlement-response-loading" ).html( '<img src="' + uscesL10n.USCES_PLUGIN_URL + '/images/loading.gif" />' );
			var mode = ( "" != $( "#error" ).val() ) ? "error_sbps_paypay" : "get_sbps_paypay";
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					action: "usces_admin_ajax",
					mode: mode,
					order_id: $( "#order_id" ).val(),
					tracking_id: $( "#tracking_id" ).val(),
					member_id: $( "#member_id" ).val(),
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				$( "#settlement-response" ).html( retVal.result );
				if ( $( "#refund-settlement" ).length ) {
					$( "#refund-settlement" ).prop( "disabled", true );
				}
				if ( $( "#increase-settlement" ).length ) {
					$( "#increase-settlement" ).prop( "disabled", true );
				}
				$( "#settlement-response-loading" ).html( "" );
			}).fail( function( jqXHR, textStatus, errorThrown ) {
				console.log( textStatus );
				console.log( jqXHR.status );
				console.log( errorThrown.message );
				$( "#settlement-response-loading" ).html( "" );
			});
			return false;
		},
						<?php
						/* 指定売上 */
						if ( 'manual' === $acting_opts['paypay_sales'] ) :
							?>
		salesSettlementPayPay : function( amount ) {
			$( "#settlement-response" ).html( "" );
			$( "#settlement-response-loading" ).html( '<img src="' + uscesL10n.USCES_PLUGIN_URL + '/images/loading.gif" />' );
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					action: "usces_admin_ajax",
					mode: "sales_sbps_paypay",
					order_id: $( "#order_id" ).val(),
					tracking_id: $( "#tracking_id" ).val(),
					member_id: $( "#member_id" ).val(),
					amount: amount,
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				$( "#settlement-response" ).html( retVal.result );
				if ( "OK" == retVal.status && 0 < retVal.acting_status.length ) {
					$( "#settlement-status" ).html( retVal.acting_status );
				}
				if ( $( "#refund-settlement" ).length ) {
					$( "#refund-settlement" ).prop( "disabled", true );
				}
				if ( $( "#increase-settlement" ).length ) {
					$( "#increase-settlement" ).prop( "disabled", true );
				}
				$( "#settlement-response-loading" ).html( "" );
			}).fail( function( jqXHR, textStatus, errorThrown ) {
				console.log( textStatus );
				console.log( jqXHR.status );
				console.log( errorThrown.message );
				$( "#settlement-response-loading" ).html( "" );
			});
			return false;
		},
							<?php
							endif;
						?>
		cancelSettlementPayPay : function() {
			$( "#settlement-response" ).html( "" );
			$( "#settlement-response-loading" ).html( '<img src="' + uscesL10n.USCES_PLUGIN_URL + '/images/loading.gif" />' );
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					action: "usces_admin_ajax",
					mode: "cancel_sbps_paypay",
					order_id: $( "#order_id" ).val(),
					tracking_id: $( "#tracking_id" ).val(),
					member_id: $( "#member_id" ).val(),
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				$( "#settlement-response" ).html( retVal.result );
				if ( "OK" == retVal.status && 0 < retVal.acting_status.length ) {
					$( "#settlement-status" ).html( retVal.acting_status );
				}
				if ( $( "#refund-settlement" ).length ) {
					$( "#refund-settlement" ).prop( "disabled", true );
				}
				if ( $( "#increase-settlement" ).length ) {
					$( "#increase-settlement" ).prop( "disabled", true );
				}
				$( "#settlement-response-loading" ).html( "" );
			}).fail( function( jqXHR, textStatus, errorThrown ) {
				console.log( textStatus );
				console.log( jqXHR.status );
				console.log( errorThrown.message );
				$( "#settlement-response-loading" ).html( "" );
			});
			return false;
		},
		refundSettlementPayPay : function( amount ) {
			$( "#settlement-response" ).html( "" );
			$( "#settlement-response-loading" ).html( '<img src="' + uscesL10n.USCES_PLUGIN_URL + '/images/loading.gif" />' );
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					action: "usces_admin_ajax",
					mode: "refund_sbps_paypay",
					order_id: $( "#order_id" ).val(),
					tracking_id: $( "#tracking_id" ).val(),
					member_id: $( "#member_id" ).val(),
					amount: amount,
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				$( "#settlement-response" ).html( retVal.result );
				if ( "OK" == retVal.status && 0 < retVal.acting_status.length ) {
					$( "#settlement-status" ).html( retVal.acting_status );
				}
				if ( $( "#refund-settlement" ).length ) {
					$( "#refund-settlement" ).prop( "disabled", true );
				}
				if ( $( "#increase-settlement" ).length ) {
					$( "#increase-settlement" ).prop( "disabled", true );
				}
				$( "#settlement-response-loading" ).html( "" );
			}).fail( function( jqXHR, textStatus, errorThrown ) {
				console.log( textStatus );
				console.log( jqXHR.status );
				console.log( errorThrown.message );
				$( "#settlement-response-loading" ).html( "" );
			});
			return false;
		},
		increaseSettlementPayPay : function( amount ) {
			$( "#settlement-response" ).html( "" );
			$( "#settlement-response-loading" ).html( '<img src="' + uscesL10n.USCES_PLUGIN_URL + '/images/loading.gif" />' );
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					action: "usces_admin_ajax",
					mode: "increase_sbps_paypay",
					order_id: $( "#order_id" ).val(),
					tracking_id: $( "#tracking_id" ).val(),
					member_id: $( "#member_id" ).val(),
					amount: amount,
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				$( "#settlement-response" ).html( retVal.result );
				if ( ( "OK" == retVal.status || "AC" == retVal.status ) && 0 < retVal.acting_status.length ) {
					$( "#settlement-status" ).html( retVal.acting_status );
				}
				if ( $( "#refund-settlement" ).length ) {
					$( "#refund-settlement" ).prop( "disabled", true );
				}
				if ( $( "#increase-settlement" ).length ) {
					$( "#increase-settlement" ).prop( "disabled", true );
				}
				$( "#settlement-response-loading" ).html( "" );
			}).fail( function( jqXHR, textStatus, errorThrown ) {
				console.log( textStatus );
				console.log( jqXHR.status );
				console.log( errorThrown.message );
				$( "#settlement-response-loading" ).html( "" );
			});
			return false;
		},
						<?php
					endif;
					?>
	};

	$( "#settlement_dialog" ).dialog({
		bgiframe: true,
		autoOpen: false,
		height: "auto",
		width: 800,
		resizable: true,
		modal: true,
		buttons: {
			"<?php esc_html_e( 'Close' ); ?>": function() {
				$( this ).dialog( "close" );
			}
		},
		open: function() {
					<?php
					if ( 'acting_sbps_card' === $acting_flg ) :
						?>
			adminOrderEdit.getSettlementInfoCard();
						<?php
					elseif ( 'acting_sbps_paypay' === $acting_flg ) :
						?>
			adminOrderEdit.getSettlementInfoPayPay();
						<?php
					endif;
					?>
		},
		close: function() {
			<?php do_action( 'usces_action_sbps_settlement_dialog_close', $args ); ?>
		}
	});

	$( document ).on( "click", ".settlement-information", function() {
		var tracking_id = $( this ).attr( "data-tracking_id" );
		var order_num = $( this ).attr( "data-num" );
		$( "#tracking_id" ).val( tracking_id );
		$( "#order_num" ).val( order_num );
		$( "#settlement_dialog" ).dialog( "option", "title", "<?php echo esc_js( $dialog_title ); ?>" );
		$( "#settlement_dialog" ).dialog( "open" );
	});

					<?php
					if ( 'acting_sbps_card' === $acting_flg ) :
						?>
	$( document ).on( "click", "#manual-settlement", function() {
		var amount_change = parseInt( $( "#amount_change" ).val() ) || 0;
		var trans_id = $( this ).attr( "data-trans_id" );
		if ( 0 >= amount_change ) {
			alert( "金額が不正です。" );
			return;
		}
		if ( ! confirm( amount_change + "円の与信処理を実行します。よろしいですか？" ) ) {
			return;
		}
		adminOrderEdit.manualSettlementCard( amount_change, trans_id );
	});

	$( document ).on( "click", "#sales-settlement", function() {
		var amount_original = parseInt( $( "#amount_original" ).val() ) || 0;
		var amount_change = parseInt( $( "#amount_change" ).val() ) || 0;
		if ( 0 >= amount_change ) {
			alert( "金額が不正です。" );
			return;
		}
		if ( amount_change > amount_original ) {
			alert( "与信金額を超える金額は売上計上できません。" );
			return;
		}
		if ( amount_change < amount_original ) {
			if ( ! confirm( amount_change + "円に減額して売上処理を実行します。よろしいですか？" ) ) {
				return;
			}
		} else if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to execute sales accounting processing?', 'usces' ); ?>" ) ) {
			return;
		}
		adminOrderEdit.salesSettlementCard( amount_change );
	});

	$( document ).on( "click", "#cancel-settlement", function() {
		if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to cancellation processing?', 'usces' ); ?>" ) ) {
			return;
		}
		adminOrderEdit.cancelSettlementCard();
	});

	$( document ).on( "click", "#refund-settlement", function() {
		var amount_original = parseInt( $( "#amount_original" ).val() ) || 0;
		var amount_change = parseInt( $( "#amount_change" ).val() ) || 0;
		if ( amount_change == amount_original ) {
			return;
		}
		if ( amount_change > amount_original ) {
			alert( "売上金額を超える金額は返金できません。" );
			return;
		}
		if ( 0 == amount_change ) {
			if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to cancellation processing?', 'usces' ); ?>" ) ) {
				return;
			}
			adminOrderEdit.cancelSettlementCard( amount_original );
		} else {
			var amount = amount_original - amount_change;
			if ( ! confirm( amount + "円の返金処理を実行します。よろしいですか？" ) ) {
				return;
			}
			adminOrderEdit.refundSettlementCard( amount );
		}
	});
						<?php
					elseif ( 'acting_sbps_paypay' === $acting_flg ) :
						if ( 'manual' === $acting_opts['paypay_sales'] ) :
							?>
	$( document ).on( "click", "#sales-settlement", function() {
		var amount_original = parseInt( $( "#amount_original" ).val() ) || 0;
		var amount_change = parseInt( $( "#amount_change" ).val() ) || 0;
		if ( amount_change > amount_original ) {
			alert( "与信金額を超える金額は売上計上できません。" );
			return;
		}
		if ( amount_change < amount_original ) {
			if ( ! confirm( amount_change + "円に減額して売上処理を実行します。よろしいですか？" ) ) {
				return;
			}
		} else if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to execute sales accounting processing?', 'usces' ); ?>" ) ) {
			return;
		}
		adminOrderEdit.salesSettlementPayPay( amount_change );
	});
							<?php
						endif;
						?>

	$( document ).on( "click", "#cancel-settlement", function() {
		if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to cancellation processing?', 'usces' ); ?>" ) ) {
			return;
		}
		adminOrderEdit.cancelSettlementPayPay();
	});

	$( document ).on( "click", "#refund-settlement", function() {
		var amount_original = parseInt( $( "#amount_original" ).val() ) || 0;
		var amount_change = parseInt( $( "#amount_change" ).val() ) || 0;
		if ( amount_change == amount_original ) {
			return;
		}
		if ( amount_change > amount_original ) {
			alert( "返金できません。" );
			return;
		}
		if ( 0 == amount_change ) {
			if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to cancellation processing?', 'usces' ); ?>" ) ) {
				return;
			}
			adminOrderEdit.cancelSettlementPayPay( amount_original );
		} else {
			var amount = amount_original - amount_change;
			if ( ! confirm( amount + "円の返金処理を実行します。よろしいですか？" ) ) {
				return;
			}
			adminOrderEdit.refundSettlementPayPay( amount );
		}
	});

	$( document ).on( "click", "#increase-settlement", function() {
		var amount_original = parseInt( $( "#amount_original" ).val() ) || 0;
		var amount_change = parseInt( $( "#amount_change" ).val() ) || 0;
		if ( amount_change == amount_original ) {
			return;
		}
		if ( amount_change < amount_original ) {
			alert( "増額できません。" );
			return;
		}
		var amount = amount_change - amount_original;
		if ( ! confirm( amount + "円の増額売上処理を実行します。よろしいですか？" ) ) {
			return;
		}
		var amount = $( "#amount_change" ).val();
		adminOrderEdit.increaseSettlementPayPay( amount_change );
	});
						<?php
					endif;
					?>

	$( document ).on( "keydown", "#amount_change", function( e ) {
		var halfVal = $( this ).val().replace( /[！-～]/g,
			function( tmpStr ) {
				return String.fromCharCode( tmpStr.charCodeAt(0) - 0xFEE0 );
			}
		);
		$( this ).val( halfVal.replace( /[^0-9]/g, '' ) );
	});

	$( document ).on( "keyup", "#amount_change", function() {
		this.value = this.value.replace( /[^0-9]+/i, '' );
		this.value = Number( this.value ) || 0;
		var amount_original = Number( $( "#amount_original" ).val() ) || 0;
		if ( this.value > amount_original ) {
			$( "#refund-settlement" ).prop( "disabled", true );
					<?php
					if ( 'acting_sbps_paypay' === $acting_flg ) :
						?>
			$( "#increase-settlement" ).prop( "disabled", false );
						<?php
					endif;
					?>
		} else if ( this.value < amount_original ) {
			$( "#refund-settlement" ).prop( "disabled", false );
					<?php
					if ( 'acting_sbps_paypay' === $acting_flg ) :
						?>
			$( "#increase-settlement" ).prop( "disabled", true );
						<?php
					endif;
					?>
		} else {
			$( "#refund-settlement" ).prop( "disabled", true );
					<?php
					if ( 'acting_sbps_paypay' === $acting_flg ) :
						?>
			$( "#increase-settlement" ).prop( "disabled", true );
						<?php
					endif;
					?>
		}
	});

	$( document ).on( "blur", "#amount_change", function() {
		this.value = this.value.replace( /[^0-9]+/i, '' );
	});
					<?php if ( 'usces_continue' === $admin_page ) : ?>
	adminContinuation = {
		update : function() {
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					action: "usces_admin_ajax",
					mode: "continuation_update",
					member_id: $( "#member_id" ).val(),
					order_id: $( "#order_id" ).val(),
					contracted_year: $( "#contracted-year option:selected" ).val(),
					contracted_month: $( "#contracted-month option:selected" ).val(),
					contracted_day: $( "#contracted-day option:selected" ).val(),
					charged_year: $( "#charged-year option:selected" ).val(),
					charged_month: $( "#charged-month option:selected" ).val(),
					charged_day: $( "#charged-day option:selected" ).val(),
					price: $( "#price" ).val(),
					status: $( "#dlseller-status" ).val(),
					wc_nonce: $( "#wc_nonce" ).val()
				}
			}).done( function( retVal, dataType ) {
				if ( "OK" == retVal.status ) {
					adminOperation.setActionStatus( "success", "<?php esc_html_e( 'The update was completed.', 'usces' ); ?>" );
				} else {
					var message = ( retVal.message != "" ) ? retVal.message : "<?php esc_html_e( 'failure in update', 'usces' ); ?>";
					adminOperation.setActionStatus( "error", message );
				}
			}).fail( function( jqXHR, textStatus, errorThrown ) {
				console.log( textStatus );
				console.log( jqXHR.status );
				console.log( errorThrown.message );
				adminOperation.setActionStatus( "error", "<?php esc_html_e( 'failure in update', 'usces' ); ?>" );
			});
			return false;
		}
	};

	$( document ).on( "click", "#continuation-update", function() {
		var status = $( "#dlseller-status option:selected" ).val();
		if ( "continuation" == status ) {
			var year = $( "#charged-year option:selected" ).val();
			var month = $( "#charged-month option:selected" ).val();
			var day = $( "#charged-day option:selected" ).val();
			if ( 0 == year || 0 == month || 0 == day ) {
				alert( "<?php esc_html_e( 'Data have deficiency.', 'usces' ); ?>" );
				$( "#charged-year" ).focus();
				return;
			}
			if ( "" == $( "#price" ).val() || 0 == parseFloat( $( "#price" ).val() ) ) {
				alert( "<?php printf( __( 'Input the %s', 'usces' ), __( 'Amount', 'dlseller' ) ); ?>" );
				$( "#price" ).focus();
				return;
			}
		}
		if ( ! confirm( "<?php esc_html_e( 'Are you sure you want to update the settings?', 'usces' ); ?>" ) ) {
			return;
		}
		adminContinuation.update();
	});
					<?php endif; ?>
});
</script>
					<?php
				endif;
				break;
		endswitch;
	}

	/**
	 * 決済オプション登録・更新
	 * usces_action_admin_settlement_update
	 */
	public function settlement_update() {
		global $usces;

		if ( 'sbps' !== wp_unslash( $_POST['acting'] ) ) {
			return;
		}

		$this->error_mes = '';
		$options         = get_option( 'usces', array() );
		$payment_method  = usces_get_system_option( 'usces_payment_method', 'settlement' );
		$post_data       = wp_unslash( $_POST );

		unset( $options['acting_settings']['sbps'] );
		$options['acting_settings']['sbps']['merchant_id']          = ( isset( $post_data['merchant_id'] ) ) ? trim( $post_data['merchant_id'] ) : '';
		$options['acting_settings']['sbps']['service_id']           = ( isset( $post_data['service_id'] ) ) ? trim( $post_data['service_id'] ) : '';
		$options['acting_settings']['sbps']['hash_key']             = ( isset( $post_data['hash_key'] ) ) ? trim( $post_data['hash_key'] ) : '';
		$options['acting_settings']['sbps']['ope']                  = ( isset( $post_data['ope'] ) ) ? $post_data['ope'] : '';
		$options['acting_settings']['sbps']['card_activate']        = ( isset( $post_data['card_activate'] ) ) ? $post_data['card_activate'] : 'off';
		$options['acting_settings']['sbps']['3d_secure']            = ( isset( $post_data['3d_secure'] ) ) ? $post_data['3d_secure'] : 'off';
		$options['acting_settings']['sbps']['cust_manage']          = ( isset( $post_data['cust_manage'] ) ) ? $post_data['cust_manage'] : 'off';
		$options['acting_settings']['sbps']['sales']                = ( isset( $post_data['sales'] ) ) ? $post_data['sales'] : 'manual';
		$options['acting_settings']['sbps']['sales_dlseller']       = ( isset( $post_data['sales_dlseller'] ) ) ? $post_data['sales_dlseller'] : 'manual';
		$options['acting_settings']['sbps']['auto_settlement_mail'] = ( isset( $post_data['auto_settlement_mail'] ) ) ? $post_data['auto_settlement_mail'] : 'off';
		$options['acting_settings']['sbps']['3des_key']             = ( isset( $post_data['3des_key'] ) ) ? trim( $post_data['3des_key'] ) : '';
		$options['acting_settings']['sbps']['3desinit_key']         = ( isset( $post_data['3desinit_key'] ) ) ? trim( $post_data['3desinit_key'] ) : '';
		$options['acting_settings']['sbps']['basic_id']             = ( isset( $post_data['basic_id'] ) ) ? trim( $post_data['basic_id'] ) : '';
		$options['acting_settings']['sbps']['basic_password']       = ( isset( $post_data['basic_password'] ) ) ? trim( $post_data['basic_password'] ) : '';
		$options['acting_settings']['sbps']['conv_activate']        = ( isset( $post_data['conv_activate'] ) ) ? $post_data['conv_activate'] : 'off';
		$options['acting_settings']['sbps']['conv_limit']           = ( isset( $post_data['conv_limit'] ) ) ? $post_data['conv_limit'] : '';
		$options['acting_settings']['sbps']['payeasy_activate']     = ( isset( $post_data['payeasy_activate'] ) ) ? $post_data['payeasy_activate'] : 'off';
		$options['acting_settings']['sbps']['wallet_yahoowallet']   = ( isset( $post_data['wallet_yahoowallet'] ) ) ? $post_data['wallet_yahoowallet'] : 'off';
		$options['acting_settings']['sbps']['wallet_rakuten']       = ( isset( $post_data['wallet_rakuten'] ) ) ? $post_data['wallet_rakuten'] : 'off';
		$options['acting_settings']['sbps']['wallet_rakutenv2']     = ( isset( $post_data['wallet_rakutenv2'] ) ) ? $post_data['wallet_rakutenv2'] : 'off';
		$options['acting_settings']['sbps']['wallet_paypal']        = ( isset( $post_data['wallet_paypal'] ) ) ? $post_data['wallet_paypal'] : 'off';
		$options['acting_settings']['sbps']['wallet_netmile']       = 'off';
		$options['acting_settings']['sbps']['wallet_alipay']        = ( isset( $post_data['wallet_alipay'] ) ) ? $post_data['wallet_alipay'] : 'off';
		$options['acting_settings']['sbps']['wallet_activate']      = ( isset( $post_data['wallet_activate'] ) ) ? $post_data['wallet_activate'] : 'off';
		$options['acting_settings']['sbps']['mobile_docomo']        = ( isset( $post_data['mobile_docomo'] ) ) ? $post_data['mobile_docomo'] : 'off';
		$options['acting_settings']['sbps']['mobile_auone']         = ( isset( $post_data['mobile_auone'] ) ) ? $post_data['mobile_auone'] : 'off';
		$options['acting_settings']['sbps']['mobile_mysoftbank']    = 'off';
		$options['acting_settings']['sbps']['mobile_softbank2']     = ( isset( $post_data['mobile_softbank2'] ) ) ? $post_data['mobile_softbank2'] : 'off';
		$options['acting_settings']['sbps']['mobile_activate']      = ( isset( $post_data['mobile_activate'] ) ) ? $post_data['mobile_activate'] : 'off';
		$options['acting_settings']['sbps']['paypay_activate']      = ( isset( $post_data['paypay_activate'] ) ) ? $post_data['paypay_activate'] : 'off';
		$options['acting_settings']['sbps']['paypay_sales']         = ( isset( $post_data['paypay_sales'] ) ) ? $post_data['paypay_sales'] : 'manual';

		if ( ( 'on' === $options['acting_settings']['sbps']['card_activate'] || 'token' === $options['acting_settings']['sbps']['card_activate'] ) ||
			'on' === $options['acting_settings']['sbps']['conv_activate'] ||
			'on' === $options['acting_settings']['sbps']['payeasy_activate'] ||
			'on' === $options['acting_settings']['sbps']['wallet_activate'] ||
			'on' === $options['acting_settings']['sbps']['mobile_activate'] ||
			'on' === $options['acting_settings']['sbps']['paypay_activate'] ) {
			$unavailable_activate = false;
			foreach ( $payment_method as $settlement => $payment ) {
				if ( in_array( $settlement, $this->unavailable_method ) && 'activate' === $payment['use'] ) {
					$unavailable_activate = true;
					break;
				}
			}
			if ( $unavailable_activate ) {
				$this->error_mes .= __( '* Settlement that can not be used together is activated.', 'usces' ) . '<br />';
			} else {
				if ( WCUtils::is_blank( $post_data['merchant_id'] ) ) {
					$this->error_mes .= '※マーチャントID を入力してください<br />';
				}
				if ( WCUtils::is_blank( $post_data['service_id'] ) ) {
					$this->error_mes .= '※サービスID を入力してください<br />';
				}
				if ( WCUtils::is_blank( $post_data['hash_key'] ) ) {
					$this->error_mes .= '※ハッシュキーを入力してください<br />';
				}
				if ( ( 'on' === $options['acting_settings']['sbps']['card_activate'] || 'token' === $options['acting_settings']['sbps']['card_activate'] ) ||
					'on' === $options['acting_settings']['sbps']['paypay_activate'] ) {
					if ( WCUtils::is_blank( $post_data['3des_key'] ) ) {
						$this->error_mes .= '※3DES 暗号化キーを入力してください<br />';
					}
					if ( WCUtils::is_blank( $post_data['3desinit_key'] ) ) {
						$this->error_mes .= '※3DES 初期化キーを入力してください<br />';
					}
				}
				if ( 'token' === $options['acting_settings']['sbps']['card_activate'] ) {
					if ( WCUtils::is_blank( $post_data['basic_id'] ) ) {
						$this->error_mes .= '※Basic認証ID を入力してください<br />';
					}
					if ( WCUtils::is_blank( $post_data['basic_password'] ) ) {
						$this->error_mes .= '※Basic認証 Password を入力してください<br />';
					}
				}
			}
		}

		if ( 'on' === $options['acting_settings']['sbps']['wallet_rakuten'] && 'on' === $options['acting_settings']['sbps']['wallet_rakutenv2'] ) {
			$this->error_mes .= '※楽天ペイと楽天ペイV2の併用はできません。楽天ペイV2をご利用ください。<br />';
		}

		if ( '' === $this->error_mes ) {
			$usces->action_status  = 'success';
			$usces->action_message = __( 'Options are updated.', 'usces' );
			$toactive              = array();
			if ( 'on' === $options['acting_settings']['sbps']['card_activate'] || 'token' === $options['acting_settings']['sbps']['card_activate'] ) {
				$usces->payment_structure[ $this->acting_flg_card ] = 'カード決済（SBPS）';
				foreach ( $payment_method as $settlement => $payment ) {
					if ( $this->acting_flg_card === $settlement && 'deactivate' === $payment['use'] ) {
						$toactive[] = $payment['name'];
					}
				}
			} else {
				unset( $usces->payment_structure[ $this->acting_flg_card ] );
			}
			if ( 'on' === $options['acting_settings']['sbps']['conv_activate'] ) {
				$usces->payment_structure[ $this->acting_flg_conv ] = 'コンビニ決済（SBPS）';
				foreach ( $payment_method as $settlement => $payment ) {
					if ( $this->acting_flg_conv === $settlement && 'deactivate' === $payment['use'] ) {
						$toactive[] = $payment['name'];
					}
				}
			} else {
				unset( $usces->payment_structure[ $this->acting_flg_conv ] );
			}
			if ( 'on' === $options['acting_settings']['sbps']['payeasy_activate'] ) {
				$usces->payment_structure[ $this->acting_flg_payeasy ] = 'ペイジー決済（SBPS）';
				foreach ( $payment_method as $settlement => $payment ) {
					if ( $this->acting_flg_payeasy === $settlement && 'deactivate' === $payment['use'] ) {
						$toactive[] = $payment['name'];
					}
				}
			} else {
				unset( $usces->payment_structure[ $this->acting_flg_payeasy ] );
			}
			if ( 'on' === $options['acting_settings']['sbps']['wallet_yahoowallet'] ||
				'on' === $options['acting_settings']['sbps']['wallet_rakuten'] ||
				'on' === $options['acting_settings']['sbps']['wallet_rakutenv2'] ||
				'on' === $options['acting_settings']['sbps']['wallet_paypal'] ||
				'on' === $options['acting_settings']['sbps']['wallet_alipay'] ) {
				$options['acting_settings']['sbps']['wallet_activate'] = 'on';
			} else {
				$options['acting_settings']['sbps']['wallet_activate'] = 'off';
			}
			if ( 'on' === $options['acting_settings']['sbps']['wallet_activate'] ) {
				$usces->payment_structure[ $this->acting_flg_wallet ] = 'ウォレット決済（SBPS）';
				foreach ( $payment_method as $settlement => $payment ) {
					if ( $this->acting_flg_wallet === $settlement && 'deactivate' === $payment['use'] ) {
						$toactive[] = $payment['name'];
					}
				}
			} else {
				unset( $usces->payment_structure[ $this->acting_flg_wallet ] );
			}
			if ( 'on' === $options['acting_settings']['sbps']['mobile_docomo'] ||
				'on' === $options['acting_settings']['sbps']['mobile_auone'] ||
				'on' === $options['acting_settings']['sbps']['mobile_softbank2'] ) {
				$options['acting_settings']['sbps']['mobile_activate'] = 'on';
			} else {
				$options['acting_settings']['sbps']['mobile_activate'] = 'off';
			}
			if ( 'on' === $options['acting_settings']['sbps']['mobile_activate'] ) {
				$usces->payment_structure[ $this->acting_flg_mobile ] = 'キャリア決済（SBPS）';
				foreach ( $payment_method as $settlement => $payment ) {
					if ( $this->acting_flg_mobile === $settlement && 'deactivate' === $payment['use'] ) {
						$toactive[] = $payment['name'];
					}
				}
			} else {
				unset( $usces->payment_structure[ $this->acting_flg_mobile ] );
			}
			if ( 'on' === $options['acting_settings']['sbps']['paypay_activate'] ) {
				$usces->payment_structure[ $this->acting_flg_paypay ] = 'PayPay オンライン決済（SBPS）';
				foreach ( $payment_method as $settlement => $payment ) {
					if ( $this->acting_flg_paypay === $settlement && 'deactivate' === $payment['use'] ) {
						$toactive[] = $payment['name'];
					}
				}
			} else {
				unset( $usces->payment_structure[ $this->acting_flg_paypay ] );
			}
			if ( ( 'on' === $options['acting_settings']['sbps']['card_activate'] || 'token' === $options['acting_settings']['sbps']['card_activate'] ) ||
				'on' === $options['acting_settings']['sbps']['conv_activate'] ||
				'on' === $options['acting_settings']['sbps']['payeasy_activate'] ||
				'on' === $options['acting_settings']['sbps']['wallet_activate'] ||
				'on' === $options['acting_settings']['sbps']['mobile_activate'] ||
				'on' === $options['acting_settings']['sbps']['paypay_activate'] ) {
				$options['acting_settings']['sbps']['activate']       = 'on';
				$options['acting_settings']['sbps']['send_url']       = 'https://fep.sps-system.com/f01/FepBuyInfoReceive.do';
				$options['acting_settings']['sbps']['send_url_check'] = 'https://stbfep.sps-system.com/Extra/BuyRequestAction.do';
				$options['acting_settings']['sbps']['send_url_test']  = 'https://stbfep.sps-system.com/f01/FepBuyInfoReceive.do';
				$options['acting_settings']['sbps']['token_url']      = 'https://token.sps-system.com/sbpstoken/com_sbps_system_token.js';
				$options['acting_settings']['sbps']['token_url_test'] = 'https://stbtoken.sps-system.com/sbpstoken/com_sbps_system_token.js';
				$options['acting_settings']['sbps']['api_url']        = 'https://api.sps-system.com/api/xmlapi.do';
				$options['acting_settings']['sbps']['api_url_test']   = 'https://stbfep.sps-system.com/api/xmlapi.do';
				usces_admin_orderlist_show_wc_trans_id();
				if ( 0 < count( $toactive ) ) {
					$usces->action_message .= __( 'Please update the payment method to "Activate". <a href="admin.php?page=usces_initial#payment_method_setting">General Setting > Payment Methods</a>', 'usces' );
				}
			} else {
				$options['acting_settings']['sbps']['activate'] = 'off';
				unset( $usces->payment_structure[ $this->acting_flg_card ] );
				unset( $usces->payment_structure[ $this->acting_flg_conv ] );
				unset( $usces->payment_structure[ $this->acting_flg_payeasy ] );
				unset( $usces->payment_structure[ $this->acting_flg_wallet ] );
				unset( $usces->payment_structure[ $this->acting_flg_mobile ] );
				unset( $usces->payment_structure[ $this->acting_flg_paypay ] );
			}
			$deactivate = array();
			foreach ( $payment_method as $settlement => $payment ) {
				if ( ! array_key_exists( $settlement, $usces->payment_structure ) ) {
					if ( 'deactivate' !== $payment['use'] ) {
						$payment['use'] = 'deactivate';
						$deactivate[]   = $payment['name'];
						usces_update_system_option( 'usces_payment_method', $payment['id'], $payment );
					}
				}
			}
			if ( 0 < count( $deactivate ) ) {
				$deactivate_message     = sprintf( __( '"Deactivate" %s of payment method.', 'usces' ), implode( ',', $deactivate ) );
				$usces->action_message .= $deactivate_message;
			}
		} else {
			$usces->action_status                           = 'error';
			$usces->action_message                          = __( 'Data have deficiency.', 'usces' );
			$options['acting_settings']['sbps']['activate'] = 'off';
			unset( $usces->payment_structure[ $this->acting_flg_card ] );
			unset( $usces->payment_structure[ $this->acting_flg_conv ] );
			unset( $usces->payment_structure[ $this->acting_flg_payeasy ] );
			unset( $usces->payment_structure[ $this->acting_flg_wallet ] );
			unset( $usces->payment_structure[ $this->acting_flg_mobile ] );
			unset( $usces->payment_structure[ $this->acting_flg_paypay ] );
			$deactivate = array();
			foreach ( $payment_method as $settlement => $payment ) {
				if ( in_array( $settlement, $this->pay_method ) ) {
					if ( 'deactivate' !== $payment['use'] ) {
						$payment['use'] = 'deactivate';
						$deactivate[]   = $payment['name'];
						usces_update_system_option( 'usces_payment_method', $payment['id'], $payment );
					}
				}
			}
			if ( 0 < count( $deactivate ) ) {
				$deactivate_message     = sprintf( __( '"Deactivate" %s of payment method.', 'usces' ), implode( ',', $deactivate ) );
				$usces->action_message .= $deactivate_message . __( 'Please complete the setup and update the payment method to "Activate".', 'usces' );
			}
		}
		ksort( $usces->payment_structure );
		update_option( 'usces', $options );
		update_option( 'usces_payment_structure', $usces->payment_structure );
	}

	/**
	 * クレジット決済設定画面フォーム
	 * usces_action_settlement_tab_body
	 */
	public function settlement_tab_body() {

		$settlement_selected = get_option( 'usces_settlement_selected', array() );
		if ( in_array( 'sbps', (array) $settlement_selected ) ) :
			$acting_opts      = $this->get_acting_settings();
			$merchant_id      = isset( $acting_opts['merchant_id'] ) ? $acting_opts['merchant_id'] : '';
			$service_id       = isset( $acting_opts['service_id'] ) ? $acting_opts['service_id'] : '';
			$hash_key         = isset( $acting_opts['hash_key'] ) ? $acting_opts['hash_key'] : '';
			$threedes_key     = isset( $acting_opts['3des_key'] ) ? $acting_opts['3des_key'] : '';
			$threedesinit_key = isset( $acting_opts['3desinit_key'] ) ? $acting_opts['3desinit_key'] : '';
			$basic_id         = isset( $acting_opts['basic_id'] ) ? $acting_opts['basic_id'] : '';
			$basic_password   = isset( $acting_opts['basic_password'] ) ? $acting_opts['basic_password'] : '';
			?>
	<div id="uscestabs_sbps">
	<div class="settlement_service"><span class="service_title"><?php echo esc_html( $this->acting_formal_name ); ?></span></div>
			<?php
			if ( isset( $_POST['acting'] ) && 'sbps' === wp_unslash( $_POST['acting'] ) ) :
				if ( '' !== $this->error_mes ) :
					?>
		<div class="error_message"><?php wel_esc_script_e( $this->error_mes ); ?></div>
					<?php
				elseif ( isset( $acting_opts['activate'] ) && 'on' === $acting_opts['activate'] ) :
					?>
		<div class="message"><?php esc_html_e( 'Test thoroughly before use.', 'usces' ); ?></div>
					<?php
				endif;
			endif;
			?>
	<form action="" method="post" name="sbps_form" id="sbps_form">
		<table class="settle_table">
			<tr>
				<th><a class="explanation-label" id="label_ex_merchant_id_sbps">マーチャントID</a></th>
				<td><input name="merchant_id" type="text" id="merchant_id_sbps" value="<?php echo esc_attr( $merchant_id ); ?>" class="regular-text" maxlength="5" /></td>
			</tr>
			<tr id="ex_merchant_id_sbps" class="explanation"><td colspan="2">契約時にSBペイメントサービスから発行されるマーチャントID（半角数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_service_id_sbps">サービスID</a></th>
				<td><input name="service_id" type="text" id="service_id_sbps" value="<?php echo esc_attr( $service_id ); ?>" class="regular-text" maxlength="3" /></td>
			</tr>
			<tr id="ex_service_id_sbps" class="explanation"><td colspan="2">契約時にSBペイメントサービスから発行されるサービスID（半角数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_hash_key_sbps">ハッシュキー</a></th>
				<td><input name="hash_key" type="text" id="hash_key_sbps" value="<?php echo esc_attr( $hash_key ); ?>" class="regular-text" maxlength="40" /></td>
			</tr>
			<tr id="ex_hash_key_sbps" class="explanation"><td colspan="2">契約時にSBペイメントサービスから発行されるハッシュキー（半角英数）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_3des_key_sbps">3DES<br />暗号化キー</a></th>
				<td><input name="3des_key" type="text" id="3des_key_sbps" value="<?php echo esc_attr( $threedes_key ); ?>" class="regular-text" maxlength="40" /></td>
			</tr>
			<tr id="ex_3des_key_sbps" class="explanation"><td colspan="2">契約時にSBペイメントサービスから発行される 3DES 暗号化キー（半角英数）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_3desinit_key_sbps">3DES<br />初期化キー</a></th>
				<td><input name="3desinit_key" type="text" id="3desinit_key_sbps" value="<?php echo esc_attr( $threedesinit_key ); ?>" class="regular-text" maxlength="40" /></td>
			</tr>
			<tr id="ex_3desinit_key_sbps" class="explanation"><td colspan="2">契約時にSBペイメントサービスから発行される 3DES 初期化キー（半角英数）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_ope_sbps"><?php esc_html_e( 'Operation Environment', 'usces' ); ?></a></th>
				<td><label><input name="ope" type="radio" id="ope_sbps_1" value="check"<?php checked( $acting_opts['ope'], 'check' ); ?> /><span>接続支援サイト</span></label><br />
					<label><input name="ope" type="radio" id="ope_sbps_2" value="test"<?php checked( $acting_opts['ope'], 'test' ); ?> /><span>テスト環境</span></label><br />
					<label><input name="ope" type="radio" id="ope_sbps_3" value="public"<?php checked( $acting_opts['ope'], 'public' ); ?> /><span>本番環境</span></label>
				</td>
			</tr>
			<tr id="ex_ope_sbps" class="explanation"><td colspan="2"><?php esc_html_e( 'Switch the operating environment.', 'usces' ); ?></td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>クレジットカード決済</th>
				<td><label><input name="card_activate" type="radio" class="card_activate_sbps" id="card_activate_sbps_1" value="on"<?php checked( $acting_opts['card_activate'], 'on' ); ?> /><span>リンク型で利用する</span></label><br />
					<label><input name="card_activate" type="radio" class="card_activate_sbps" id="card_activate_sbps_2" value="token"<?php checked( $acting_opts['card_activate'], 'token' ); ?> /><span>API 型で利用する</span></label><br />
					<label><input name="card_activate" type="radio" class="card_activate_sbps" id="card_activate_sbps_0" value="off"<?php checked( $acting_opts['card_activate'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr class="card_token_sbps">
				<th><a class="explanation-label" id="label_ex_cust_manage_sbps">クレジットカード情報保存</a></th>
				<td><label><input name="cust_manage" type="radio" id="cust_manage_sbps_1" value="on"<?php checked( $acting_opts['cust_manage'], 'on' ); ?> /><span>保存する</span></label><br />
					<label><input name="cust_manage" type="radio" id="cust_manage_sbps_2" value="choice"<?php checked( $acting_opts['cust_manage'], 'choice' ); ?> /><span>会員が選択して保存する</span></label><br />
					<label><input name="cust_manage" type="radio" id="cust_manage_sbps_0" value="off"<?php checked( $acting_opts['cust_manage'], 'off' ); ?> /><span>保存しない</span></label>
				</td>
			</tr>
			<?php
			$cust_manage_msg = '';
			if ( defined( 'WCEX_DLSELLER' ) ) {
				$cust_manage_msg = '自動継続課金をご利用の場合は「保存する」を選択してください。';
			} elseif ( defined( 'WCEX_AUTO_DELIVERY' ) ) {
				$cust_manage_msg = '定期購入をご利用の場合は「保存する」を選択してください。';
			}
			?>
			<tr id="ex_cust_manage_sbps" class="explanation card_token_sbps"><td colspan="2">クレジットカード情報お預かりサービスを利用して、会員のカード情報をSBペイメントサービスに保存します。保存する場合は、基本設定の「会員システム」を「利用する」に設定してください。<?php echo esc_html( $cust_manage_msg ); ?></td></tr>
			<tr class="card_link_token_sbps">
				<th><a class="explanation-label" id="label_ex_sales_sbps">売上方式</a></th>
				<td><label><input name="sales" type="radio" id="sales_sbps_manual" value="manual"<?php checked( $acting_opts['sales'], 'manual' ); ?> /><span>指定売上（仮売上）</span></label><br />
					<label><input name="sales" type="radio" id="sales_sbps_auto" value="auto"<?php checked( $acting_opts['sales'], 'auto' ); ?> /><span>自動売上（実売上）</span></label>
				</td>
			</tr>
			<tr id="ex_sales_sbps" class="explanation card_link_token_sbps"><td colspan="2">「指定売上」の場合は、決済時には与信のみ行い、Welcart の管理画面から手動で売上処理を行います。「自動売上」の場合は、決済時に即時売上計上されます。</td></tr>
			<?php if ( defined( 'WCEX_DLSELLER' ) ) : ?>
			<tr class="card_link_token_sbps">
				<th><a class="explanation-label" id="label_ex_sales_dlseller_sbps">自動継続課金売上方式</a></th>
				<td><label><input name="sales_dlseller" type="radio" id="sales_dlseller_sbps_manual" value="manual"<?php checked( $acting_opts['sales_dlseller'], 'manual' ); ?> /><span>指定売上（仮売上）</span></label><br />
					<label><input name="sales_dlseller" type="radio" id="sales_dlseller_sbps_auto" value="auto"<?php checked( $acting_opts['sales_dlseller'], 'auto' ); ?> /><span>自動売上（実売上）</span></label>
				</td>
			</tr>
			<tr id="ex_sales_dlseller_sbps" class="explanation card_link_token_sbps"><td colspan="2">自動継続課金（要 WCEX DLSeller）時の売上方式。</td></tr>
			<tr class="card_link_token_sbps">
				<th><a class="explanation-label" id="label_ex_auto_settlement_mail_sbps"><?php esc_html_e( 'Automatic Continuing Charging Completion Mail', 'usces' ); /* 自動継続課金完了メール */ ?></a></th>
				<td><label><input name="auto_settlement_mail" type="radio" id="auto_settlement_mail_sbps_1" value="on"<?php checked( $acting_opts['auto_settlement_mail'], 'on' ); ?> /><span><?php esc_html_e( 'Send', 'usces' ); ?></span></label><br />
					<label><input name="auto_settlement_mail" type="radio" id="auto_settlement_mail_sbps_0" value="off"<?php checked( $acting_opts['auto_settlement_mail'], 'off' ); ?> /><span><?php esc_html_e( "Don't send", 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr id="ex_auto_settlement_mail_sbps" class="explanation card_link_token_sbps"><td colspan="2"><?php esc_html_e( 'Send billing completion mail to the member on which automatic continuing charging processing (required WCEX DLSeller) is executed.', 'usces' ); ?></td></tr>
			<?php endif; ?>
			<tr>
				<th><a class="explanation-label" id="label_ex_basic_id_sbps">Basic認証ID</a></th>
				<td><input name="basic_id" type="text" id="basic_id_sbps" value="<?php echo esc_attr( $basic_id ); ?>" class="regular-text" maxlength="40" /></td>
			</tr>
			<tr id="ex_basic_id_sbps" class="explanation"><td colspan="2">契約時にSBペイメントサービスから発行される Basic認証ID（半角数字）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_basic_password_sbps">Basic認証Password</a></th>
				<td><input name="basic_password" type="text" id="basic_password_sbps" value="<?php echo esc_attr( $basic_password ); ?>" class="regular-text" maxlength="40" /></td>
			</tr>
			<tr id="ex_basic_password_sbps" class="explanation"><td colspan="2">契約時にSBペイメントサービスから発行される Basic認証 Password（半角英数）</td></tr>
			<tr class="card_link_sbps">
				<th>3Dセキュア</th>
				<td><label><input name="3d_secure" type="radio" id="threed_secure_sbps_1" value="on"<?php checked( $acting_opts['3d_secure'], 'on' ); ?> /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="3d_secure" type="radio" id="threed_secure_sbps_2" value="off"<?php checked( $acting_opts['3d_secure'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>コンビニ決済</th>
				<td><label><input name="conv_activate" type="radio" class="conv_activate_sbps" id="conv_activate_sbps_1" value="on"<?php checked( $acting_opts['conv_activate'], 'on' ); ?> /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="conv_activate" type="radio" class="conv_activate_sbps" id="conv_activate_sbps_2" value="off"<?php checked( $acting_opts['conv_activate'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr class="conv_sbps">
				<th><a class="explanation-label" id="label_ex_conv_limit_sbps"><?php esc_html_e( 'Payment due days', 'usces' ); ?></a></th>
				<td><input name="conv_limit" type="text" id="conv_limit" value="<?php echo esc_attr( $acting_opts['conv_limit'] ); ?>" class="small-text" /><?php esc_html_e( 'days', 'usces' ); ?>（1～59）</td>
			</tr>
			<tr id="ex_conv_limit_sbps" class="explanation"><td colspan="2">未設定の場合は申込時に設定した既定値となります。ここでの変更は、申込時に設定した既定値以内の指定が可能（既定値が14日の場合、13日まで）となります。既定値が不明な場合はSBペイメントサービスにお問い合わせください。</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>Pay-easy（ペイジー）決済</th>
				<td><label><input name="payeasy_activate" type="radio" id="payeasy_activate_sbps_1" value="on"<?php checked( $acting_opts['payeasy_activate'], 'on' ); ?> /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="payeasy_activate" type="radio" id="payeasy_activate_sbps_2" value="off"<?php checked( $acting_opts['payeasy_activate'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>Yahoo! ウォレット決済</th>
				<td><label><input name="wallet_yahoowallet" type="radio" id="wallet_yahoowallet_sbps_1" value="on"<?php checked( $acting_opts['wallet_yahoowallet'], 'on' ); ?> /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="wallet_yahoowallet" type="radio" id="wallet_yahoowallet_sbps_2" value="off"<?php checked( $acting_opts['wallet_yahoowallet'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_wallet_rakuten_sbps">楽天ペイ（オンライン決済）</a></th>
			<?php if ( 'on' === $acting_opts['wallet_rakuten'] ) : ?>
				<td><label><input name="wallet_rakuten" type="radio" id="wallet_rakuten_sbps_1" value="on"<?php checked( $acting_opts['wallet_rakuten'], 'on' ); ?> /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="wallet_rakuten" type="radio" id="wallet_rakuten_sbps_2" value="off"<?php checked( $acting_opts['wallet_rakuten'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			<?php else : ?>
				<td><label><input name="wallet_rakuten" type="radio" id="wallet_rakuten_sbps_1" value="on" disabled="disabled" /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="wallet_rakuten" type="radio" id="wallet_rakuten_sbps_2" value="off" checked="checked" /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			<?php endif; ?>
			</tr>
			<tr id="ex_wallet_rakuten_sbps" class="explanation"><td colspan="2">楽天ペイの新規ご契約は終了しました。楽天ペイV2をご利用ください。</td></tr>
			<tr>
				<th>楽天ペイV2（オンライン決済）</th>
				<td><label><input name="wallet_rakutenv2" type="radio" id="wallet_rakutenv2_sbps_1" value="on"<?php checked( $acting_opts['wallet_rakutenv2'], 'on' ); ?> /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="wallet_rakutenv2" type="radio" id="wallet_rakutenv2_sbps_2" value="off"<?php checked( $acting_opts['wallet_rakutenv2'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr>
				<th>PayPal 決済</th>
				<td><label><input name="wallet_paypal" type="radio" id="wallet_paypal_sbps_1" value="on"<?php checked( $acting_opts['wallet_paypal'], 'on' ); ?> /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="wallet_paypal" type="radio" id="wallet_paypal_sbps_2" value="off"<?php checked( $acting_opts['wallet_paypal'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr>
				<th>Alipay 国際決済</th>
				<td><label><input name="wallet_alipay" type="radio" id="wallet_alipay_sbps_1" value="on"<?php checked( $acting_opts['wallet_alipay'], 'on' ); ?> /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="wallet_alipay" type="radio" id="wallet_alipay_sbps_2" value="off"<?php checked( $acting_opts['wallet_alipay'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>d払い</th>
				<td><label><input name="mobile_docomo" type="radio" id="mobile_docomo_sbps_1" value="on"<?php checked( $acting_opts['mobile_docomo'], 'on' ); ?> /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="mobile_docomo" type="radio" id="mobile_docomo_sbps_2" value="off"<?php checked( $acting_opts['mobile_docomo'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr>
				<th>au かんたん決済</th>
				<td><label><input name="mobile_auone" type="radio" id="mobile_auone_sbps_1" value="on"<?php checked( $acting_opts['mobile_auone'], 'on' ); ?> /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="mobile_auone" type="radio" id="mobile_auone_sbps_2" value="off"<?php checked( $acting_opts['mobile_auone'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr>
				<th>ソフトバンク<br />まとめて支払い</th>
				<td><label><input name="mobile_softbank2" type="radio" id="mobile_softbank2_sbps_1" value="on"<?php checked( $acting_opts['mobile_softbank2'], 'on' ); ?> /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="mobile_softbank2" type="radio" id="mobile_softbank2_sbps_2" value="off"<?php checked( $acting_opts['mobile_softbank2'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>PayPay オンライン決済</th>
				<td><label><input name="paypay_activate" type="radio" class="paypay_activate_sbps" id="paypay_activate_sbps_1" value="on"<?php checked( $acting_opts['paypay_activate'], 'on' ); ?> /><span><?php esc_html_e( 'Use', 'usces' ); ?></span></label><br />
					<label><input name="paypay_activate" type="radio" class="paypay_activate_sbps" id="paypay_activate_sbps_2" value="off"<?php checked( $acting_opts['paypay_activate'], 'off' ); ?> /><span><?php esc_html_e( 'Do not Use', 'usces' ); ?></span></label>
				</td>
			</tr>
			<tr class="paypay_sbps">
				<th><a class="explanation-label" id="label_ex_paypay_sales_sbps">売上方式</a></th>
				<td><label><input name="paypay_sales" type="radio" id="paypay_sales_sbps_manual" value="manual"<?php checked( $acting_opts['paypay_sales'], 'manual' ); ?> /><span>指定売上（仮売上）</span></label><br />
					<label><input name="paypay_sales" type="radio" id="paypay_sales_sbps_auto" value="auto"<?php checked( $acting_opts['paypay_sales'], 'auto' ); ?> /><span>自動売上（実売上）</span></label>
				</td>
			</tr>
			<tr id="ex_paypay_sales_sbps" class="explanation paypay_sbps"><td colspan="2">「指定売上」の場合は、決済時には与信のみ行い、Welcart の管理画面から手動で売上処理を行います。「自動売上」の場合は、決済時に即時売上計上されます。</td></tr>
		</table>
		<input name="acting" type="hidden" value="sbps" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="SBペイメントサービスの設定を更新する" />
			<?php
			wp_nonce_field( 'admin_settlement', 'wc_nonce' );
			?>
	</form>
	<div class="settle_exp">
		<p><strong>SBペイメントサービス</strong></p>
		<a href="https://www.sbpayment.jp/service/partner/welcart/lp01/" target="_blank">SBペイメントサービスの詳細はこちら 》</a>
		<p>クレジットカード決済では、「API型（トークン決済方式）」と「リンク型」が選択できます。</p>
		<p>「API型」は、決済会社のページへは遷移せず、Welcart のページのみで決済まで完結します。デザインの統一性が保て、スムーズなチェックアウトが可能です。ただし、カード番号を扱いますので専用SSLが必須となります。入力されたカード番号はSBペイメントサービスのシステムに送信されますので、Welcart に保存することはありません。<br />
「リンク型」は、決済会社のページへ遷移してカード情報を入力します。<br />
クレジットカード決済以外の決済サービスでは、全て「リンク型」になります。</p>
		<p>PayPay オンライン決済で「自動売上」を利用したい場合は、「Basic 認証ID」「Basic 認証 Password」の設定が必要です。</p>
		<p>尚、本番環境では、正規SSL証明書のみでのSSL通信となりますのでご注意ください。</p>
			<?php
			$wcex = '';
			if ( defined( 'WCEX_DLSELLER' ) ) {
				$wcex = '、WCEX DLSeller での自動継続課金';
			} elseif ( defined( 'WCEX_AUTO_DELIVERY' ) ) {
				$wcex = '、WCEX Auto Delivery での定期購入';
			}
			?>
		<p>※Welcart v1.9.34 以前からご利用で、「リンク型」から「API型」へ変更される場合は、SBペイメントサービスへの申請が必要となります。また、管理画面連携機能<?php echo esc_html( $wcex ); ?>を利用したい場合は、「3DES 暗号化キー」「3DES 初期化キー」「Basic 認証ID」「Basic 認証 Password」の設定が必要です。<br />
各設定値がご不明な場合は、SBペイメントサービスにお問い合わせください。</p>
		<p>※クレジットカード決済およびPayPay オンライン決済で売上方式を「自動売上」にしていても、決済ステータスが「売上確定」にならなかったり、決済履歴にエラーが表示されている場合は、SBペイメントサービスの加盟店管理画面で売上方式が「自動売上」になっている可能性があります。SBペイメントサービスの売上方式を「指定売上」に変更してください。<br />
設定方法がご不明な場合は、SBペイメントサービスにお問い合わせください。</p>
	</div>
	</div><!-- uscestabs_sbps -->
			<?php
		endif;
	}

	/**
	 * 通知処理
	 * usces_after_cart_instant
	 */
	public function acting_notice() {
		global $usces;

		if ( isset( $_SERVER['REMOTE_ADDR'] ) && '61.215.213.47' === $_SERVER['REMOTE_ADDR'] ) {
			$post_data    = file_get_contents( 'php://input' );
			$request_data = $this->xml2assoc( $post_data, $this->acting_paypay );
			if ( isset( $request_data['@attributes']['id'] ) && isset( $request_data['merchant_id'] ) && isset( $request_data['service_id'] ) && isset( $request_data['sps_transaction_id'] ) && isset( $request_data['tracking_id'] ) && isset( $request_data['pay_option_manage'] ) ) {
				$acting_opts = $this->get_acting_settings();
				if ( $acting_opts['merchant_id'] === $request_data['merchant_id'] && $acting_opts['service_id'] === $request_data['service_id'] ) {
					$latest_log = $this->get_acting_latest_log( 0, $request_data['tracking_id'] );
					if ( $latest_log && isset( $latest_log['status'] ) && 'pending' === $latest_log['status'] && isset( $latest_log['order_id'] ) ) {
						$attributes_id = $request_data['@attributes']['id'];
						if ( 'NT01-00110-311' === $attributes_id ) {
							$status = 'increase';
						} elseif ( 'NT01-00112-311' === $attributes_id ) {
							$status                 = 'expired';
							$request_data['amount'] = $latest_log['amount'];
						} else {
							$status = 'error';
						}
						$this->save_acting_log( $request_data, $this->acting_paypay, $status, 'OK', $latest_log['order_id'], $request_data['tracking_id'] );
					}
					die( 'OK,' );
				}
			}
		}
	}

	/**
	 * 管理画面決済処理
	 * usces_action_admin_ajax
	 */
	public function admin_ajax() {
		global $usces;

		$mode = wp_unslash( $_POST['mode'] );
		$data = array();

		switch ( $mode ) {
			/* クレジットカード参照 */
			case 'get_sbps_card':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id    = ( isset( $_POST['order_id'] ) ) ? wp_unslash( $_POST['order_id'] ) : '';
				$tracking_id = ( isset( $_POST['tracking_id'] ) ) ? wp_unslash( $_POST['tracking_id'] ) : '';
				$member_id   = ( isset( $_POST['member_id'] ) ) ? wp_unslash( $_POST['member_id'] ) : '';
				if ( empty( $order_id ) || empty( $tracking_id ) ) {
					$data['status'] = 'NG';
					wp_send_json( $data );
					break;
				}

				$res                  = '';
				$status               = '';
				$reference_settlement = $this->get_settlement_status( 'sbps_card', $order_id, $tracking_id );
				if ( ( isset( $reference_settlement['res_result'] ) && 'OK' === $reference_settlement['res_result'] ) && ( isset( $reference_settlement['res_status'] ) && 0 === (int) $reference_settlement['res_status'] ) ) {
					$result         = 'OK';
					$payment_status = $reference_settlement['res_pay_method_info']['payment_status'];
					switch ( $payment_status ) {
						case 1:/* 与信済 */
							$status = 'manual';
							break;
						case 2:/* 売上済 */
							$status = 'sales';
							break;
						case 3:/* 与信取消済 */
							$status = 'cancel';
							break;
						case 4:/* 返金済 */
							$status = 'cancel';
							break;
						default:
							$status = 'error';
					}

					$latest_log = $this->get_acting_latest_log( $order_id, $tracking_id );
					if ( $status !== $latest_log['status'] ) {
						if ( 'cancel' === $status && 'refund' === $latest_log['status'] ) {
							$status = 'refund';
						}
					}

					$class       = ' card-' . $status;
					$status_name = $this->get_status_name( $status );
					$res        .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
					if ( 'error' !== $status ) {
						$amount = ( 'cancel' === $status ) ? 0 : $this->get_sales_amount( $order_id, $tracking_id );
						if ( 'cancel' === $status || 'refund' === $status ) {
							$res .= '<table class="sbps-settlement-admin-table">
								<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
									<td><input type="tel" class="settlement-amount" value="' . intval( $amount ) . '" disabled="disabled" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>
								</tr></table>';
						} else {
							$res .= '<table class="sbps-settlement-admin-table">
								<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
									<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $amount ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $amount ) . '" /></td>
								</tr></table>';
							$res .= '<div class="sbps-settlement-admin-button">';
							if ( 'manual' === $status ) {
								$res .= '<input id="sales-settlement" type="button" class="button" value="売上確定" />';
							}
							$res .= '<input id="cancel-settlement" type="button" class="button" value="取消" />';
							if ( 'manual' !== $status ) {
								$res .= '<input id="refund-settlement" type="button" class="button" value="部分返金" />';
							}
							$res .= '</div>';
						}
					} else {
						$status      = 'error';
						$status_name = $this->get_status_name( $status );
						$res        .= '<div class="sbps-settlement-admin card-error">' . $status_name . '</div>';
					}
				} else {
					$result     = 'NG';
					$latest_log = $this->get_acting_latest_log( $order_id, 0, 'ALL' );
					if ( 'NG' === $latest_log['result'] && 10 === strlen( $tracking_id ) ) {
						$cust_ref = $this->api_customer_reference( $member_id );
						if ( isset( $cust_ref['result'] ) && 'OK' === $cust_ref['result'] ) {
							$amount = 0;
							$res   .= '<table class="sbps-settlement-admin-table">
								<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
									<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $amount ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>
								</tr></table>';
							$res   .= '<div class="sbps-settlement-admin-button">';
							$res   .= '<input id="manual-settlement" type="button" class="button" data-trans_id="' . $tracking_id . '" value="与信済" />';
							$res   .= '</div>';
						} else {
							$status      = 'error';
							$status_name = $this->get_status_name( $status );
							$res        .= '<div class="sbps-settlement-admin card-error">' . $status_name . '</div>';
						}
					} else {
						$status      = 'error';
						$status_name = $this->get_status_name( $status );
						$res        .= '<div class="sbps-settlement-admin card-error">' . $status_name . '</div>';
					}
				}
				$res           .= $this->settlement_history( $order_id, $tracking_id );
				$data['status'] = $result;
				$data['result'] = $res;
				wp_send_json( $data );
				break;

			/* クレジットカード与信済 */
			case 'manual_sbps_card':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id  = ( isset( $_POST['order_id'] ) ) ? wp_unslash( $_POST['order_id'] ) : '';
				$order_num = ( isset( $_POST['order_num'] ) ) ? (int) wp_unslash( $_POST['order_num'] ) : 0;
				$member_id = ( isset( $_POST['member_id'] ) ) ? wp_unslash( $_POST['member_id'] ) : '';
				$amount    = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : 0;
				$trans_id  = ( isset( $_POST['trans_id'] ) ) ? wp_unslash( $_POST['trans_id'] ) : '';
				if ( empty( $order_id ) || empty( $member_id ) || empty( $amount ) || empty( $amount ) ) {
					$data['status'] = 'NG';
					wp_send_json( $data );
					break;
				}

				$res           = '';
				$acting_status = '';
				$acting_flg    = 'acting_sbps_card';
				$tracking_id   = '';

				$acting_opts = $this->get_acting_settings();
				$rand        = $trans_id;
				$cust_code   = $member_id;
				$cust_ref    = $this->api_customer_reference( $cust_code );
				if ( isset( $cust_ref['result'] ) && 'OK' === $cust_ref['result'] ) {
					$cart      = usces_get_ordercartdata( $order_id );
					$cart_row  = current( $cart );
					$item_id   = mb_convert_kana( $usces->getItemCode( $cart_row['post_id'] ), 'a', 'UTF-8' );
					$item_name = $usces->getCartItemName_byOrder( $cart_row );
					if ( 1 < count( $cart ) ) {
						$item_name .= ' ' . __( 'Others', 'usces' );
					}
					if ( 36 < mb_strlen( $item_name, 'UTF-8' ) ) {
						$item_name = mb_substr( $item_name, 0, 30, 'UTF-8' ) . '...';
					}
					$item_name     = trim( mb_convert_encoding( $item_name, 'SJIS', 'UTF-8' ) );
					$free1         = $acting_flg;
					$order_rowno   = '1';
					$encrypted_flg = '1';
					$request_date  = wp_date( 'YmdHis' );
					$sps_hashcode  = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $cust_code . $rand . $item_id . $item_name . $amount . $free1 . $order_rowno . $encrypted_flg . $request_date . $acting_opts['hash_key'];
					$sps_hashcode  = sha1( $sps_hashcode );
					$connection    = $this->get_connection();

					/* 決済要求 */
					$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST01-00131-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<cust_code>' . $cust_code . '</cust_code>
	<order_id>' . $rand . '</order_id>
	<item_id>' . $item_id . '</item_id>
	<item_name>' . base64_encode( $item_name ) . '</item_name>
	<amount>' . $amount . '</amount>
	<free1>' . base64_encode( $free1 ) . '</free1>
	<order_rowno>' . $order_rowno . '</order_rowno>
	<encrypted_flg>' . $encrypted_flg . '</encrypted_flg>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
					$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
					$response_settlement = $this->xml2assoc( $xml_settlement, $this->acting_card, $encrypted_flg );
					if ( isset( $response_settlement['res_result'] ) && 'OK' === $response_settlement['res_result'] ) {
						$sps_transaction_id = $response_settlement['res_sps_transaction_id'];
						$tracking_id        = $response_settlement['res_tracking_id'];
						$request_date       = wp_date( 'YmdHis' );
						$sps_hashcode       = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $sps_transaction_id . $tracking_id . $request_date . $acting_opts['hash_key'];
						$sps_hashcode       = sha1( $sps_hashcode );

						/* 確定要求 */
						$request_credit  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00101-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<sps_transaction_id>' . $sps_transaction_id . '</sps_transaction_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<processing_datetime></processing_datetime>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
						$xml_credit      = $this->get_xml_response( $connection['api_url'], $request_credit );
						$response_credit = $this->xml2assoc( $xml_credit, $this->acting_card );
						$response_credit = apply_filters( 'usces_filter_sbps_card_manual_log', $response_credit, $order_id );
						if ( isset( $response_credit['res_result'] ) ) {
							$status = 'manual';
							$result = $response_credit['res_result'];
							if ( 'OK' === $result ) {
								if ( ! isset( $response_credit['amount'] ) ) {
									$response_credit['amount'] = $amount;
								}
								if ( 1 < (int) $order_num ) {
									$this->update_acting_log( $this->acting_card, $order_id, $tracking_id, $trans_id );
									$this->save_acting_log( $response_credit, $this->acting_card, $status, $result, $order_id, $tracking_id );
								} else {
									$this->save_acting_log( $response_credit, $this->acting_card, $status, $result, $order_id, $tracking_id );
									$usces->set_order_meta_value( 'res_tracking_id', $tracking_id, $order_id );
									$usces->set_order_meta_value( 'wc_trans_id', $tracking_id, $order_id );
									if ( ! isset( $response_credit['acting'] ) ) {
										$response_credit['acting'] = $this->acting_card;
									}
									$usces->set_order_meta_value( $acting_flg, usces_serialize( $response_credit ), $order_id );
								}

								$class         = ' card-' . $status;
								$status_name   = $this->get_status_name( $status );
								$res          .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
								$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
								$res          .= '<table class="sbps-settlement-admin-table">
									<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
										<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $amount ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $amount ) . '" /></td>
									</tr></table>';
								$res          .= '<div class="sbps-settlement-admin-button">';
								$res          .= '<input id="sales-settlement" type="button" class="button" value="売上確定" />';
								if ( ! $this->is_status( array( 'cancel', 'refund' ), $order_id, $tracking_id ) ) {
									$res .= '<input id="cancel-settlement" type="button" class="button" value="取消" />';
								}
								$res .= '</div>';
							} else {
								$usces->set_order_meta_value( 'trans_id', $rand, $order_id );
								$this->save_acting_log( $response_credit, $this->acting_card, $status, $response_credit['res_result'], $order_id, $tracking_id );
								$status      = $response_credit['res_result'];
								$class       = ' card-' . $status;
								$status_name = $this->get_status_name( $status );
								$res        .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
							}
						} else {
							$result      = 'NG';
							$status      = 'error';
							$status_name = $this->get_status_name( $status );
							$res        .= '<div class="sbps-settlement-admin card-error">' . $status_name . '</div>';
						}
					} else {
						$result      = 'NG';
						$status      = 'error';
						$status_name = $this->get_status_name( $status );
						$res        .= '<div class="sbps-settlement-admin card-error">' . $status_name . '</div>';
					}
				} else {
					$result      = 'NG';
					$status      = 'error';
					$status_name = $this->get_status_name( $status );
					$res        .= '<div class="sbps-settlement-admin card-error">' . $status_name . '</div>';
				}
				$res                  .= $this->settlement_history( $order_id, $tracking_id );
				$data['status']        = $result;
				$data['result']        = $res;
				$data['acting_status'] = $acting_status;
				$data['tracking_id']   = $tracking_id;
				wp_send_json( $data );
				break;

			/* クレジットカード売上確定 */
			case 'sales_sbps_card':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id    = ( isset( $_POST['order_id'] ) ) ? wp_unslash( $_POST['order_id'] ) : '';
				$tracking_id = ( isset( $_POST['tracking_id'] ) ) ? wp_unslash( $_POST['tracking_id'] ) : '';
				$amount      = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : 0;
				if ( empty( $order_id ) || empty( $tracking_id ) || empty( $amount ) ) {
					$data['status'] = 'NG';
					wp_send_json( $data );
					break;
				}

				$res           = '';
				$acting_status = '';
				$acting_opts   = $this->get_acting_settings();
				$connection    = $this->get_connection();
				$request_date  = wp_date( 'YmdHis' );
				$sps_hashcode  = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $tracking_id . $request_date . $amount . $request_date . $acting_opts['hash_key'];
				$sps_hashcode  = sha1( $sps_hashcode );

				/* 売上要求 */
				$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00201-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<processing_datetime>' . $request_date . '</processing_datetime>
	<pay_option_manage>
		<amount>' . $amount . '</amount>
	</pay_option_manage>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
				$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
				$response_settlement = $this->xml2assoc( $xml_settlement, $this->acting_card );
				$response_settlement = apply_filters( 'usces_filter_sbps_card_sales_log', $response_settlement, $order_id );
				if ( isset( $response_settlement['res_result'] ) ) {
					$status = 'sales';
					$result = $response_settlement['res_result'];
					if ( 'OK' === $result ) {
						if ( ! isset( $response_settlement['amount'] ) ) {
							$response_settlement['amount'] = $amount;
						}
						$this->save_acting_log( $response_settlement, $this->acting_card, $status, $result, $order_id, $tracking_id );
						$class         = ' card-' . $status;
						$status_name   = $this->get_status_name( $status );
						$res          .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
					} else {
						$latest_log = $this->get_acting_latest_log( $order_id, $tracking_id );
						if ( ! isset( $response_settlement['amount'] ) ) {
							if ( isset( $latest_log['amount'] ) ) {
								$response_settlement['amount'] = $latest_log['amount'];
							}
						}
						$this->save_acting_log( $response_settlement, $this->acting_card, $status, $result, $order_id, $tracking_id );
						$status      = $latest_log['status'];
						$class       = ' card-' . $status;
						$status_name = $this->get_status_name( $status );
						$res        .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$amount      = $latest_log['amount'];
					}
					$res .= '<table class="sbps-settlement-admin-table">
						<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
							<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $amount ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $amount ) . '" /></td>
						</tr></table>';
					$res .= '<div class="sbps-settlement-admin-button">';
					if ( 'manual' === $status ) {
						$res .= '<input id="sales-settlement" type="button" class="button" value="売上確定" />';
					}
					if ( ! $this->is_status( array( 'cancel', 'refund' ), $order_id, $tracking_id ) ) {
						$res .= '<input id="cancel-settlement" type="button" class="button" value="取消" />';
						if ( 'manual' !== $status ) {
							$res .= '<input id="refund-settlement" type="button" class="button" value="部分返金" />';
						}
					}
					$res .= '</div>';
				} else {
					$result      = 'NG';
					$status      = 'error';
					$status_name = $this->get_status_name( $status );
					$res        .= '<div class="sbps-settlement-admin card-error">' . $status_name . '</div>';
				}
				$res                  .= $this->settlement_history( $order_id, $tracking_id );
				$data['status']        = $result;
				$data['result']        = $res;
				$data['acting_status'] = $acting_status;
				wp_send_json( $data );
				break;

			/* クレジットカード取消 */
			case 'cancel_sbps_card':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id    = ( isset( $_POST['order_id'] ) ) ? wp_unslash( $_POST['order_id'] ) : '';
				$tracking_id = ( isset( $_POST['tracking_id'] ) ) ? wp_unslash( $_POST['tracking_id'] ) : '';
				if ( empty( $order_id ) || empty( $tracking_id ) ) {
					$data['status'] = 'NG';
					wp_send_json( $data );
					break;
				}

				$res           = '';
				$acting_status = '';
				$acting_opts   = $this->get_acting_settings();
				$connection    = $this->get_connection();
				$request_date  = wp_date( 'YmdHis' );
				$sps_hashcode  = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $tracking_id . $request_date . $request_date . $acting_opts['hash_key'];
				$sps_hashcode  = sha1( $sps_hashcode );

				/* 取消返金要求 */
				$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00303-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<processing_datetime>' . $request_date . '</processing_datetime>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
				$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
				$response_settlement = $this->xml2assoc( $xml_settlement, $this->acting_card );
				$response_settlement = apply_filters( 'usces_filter_sbps_card_cancel_log', $response_settlement, $order_id );
				if ( isset( $response_settlement['res_result'] ) ) {
					$status = 'cancel';
					$result = $response_settlement['res_result'];
					if ( 'OK' === $result ) {
						$response_settlement['amount'] = 0;
						$this->save_acting_log( $response_settlement, $this->acting_card, $status, $result, $order_id, $tracking_id );
						$class         = ' card-' . $status;
						$status_name   = $this->get_status_name( $status );
						$res          .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$res          .= '<table class="sbps-settlement-admin-table">
							<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
								<td><input type="tel" class="settlement-amount" value="0" disabled="disabled" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>
							</tr></table>';
						$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
					} else {
						$latest_log = $this->get_acting_latest_log( $order_id, $tracking_id );
						if ( ! isset( $response_settlement['amount'] ) ) {
							if ( isset( $latest_log['amount'] ) ) {
								$response_settlement['amount'] = $latest_log['amount'];
							}
						}
						$this->save_acting_log( $response_settlement, $this->acting_card, $status, $result, $order_id, $tracking_id );
						$status      = $latest_log['status'];
						$class       = ' card-' . $status;
						$status_name = $this->get_status_name( $status );
						$res        .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$res        .= '<table class="sbps-settlement-admin-table">
							<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
								<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $latest_log['amount'] ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $latest_log['amount'] ) . '" /></td>
							</tr></table>';
						$res        .= '<div class="sbps-settlement-admin-button">';
						if ( 'manual' === $status ) {
							$res .= '<input id="sales-settlement" type="button" class="button" value="売上確定" />';
						}
						if ( ! $this->is_status( array( 'cancel', 'refund' ), $order_id, $tracking_id ) ) {
							$res .= '<input id="cancel-settlement" type="button" class="button" value="取消" />';
							if ( 'manual' !== $status ) {
								$res .= '<input id="refund-settlement" type="button" class="button" value="部分返金" />';
							}
						}
						$res .= '</div>';
					}
				} else {
					$result = 'NG';
				}
				$res                  .= $this->settlement_history( $order_id, $tracking_id );
				$data['status']        = $result;
				$data['result']        = $res;
				$data['acting_status'] = $acting_status;
				wp_send_json( $data );
				break;

			/* クレジットカード部分返金 */
			case 'refund_sbps_card':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id    = ( isset( $_POST['order_id'] ) ) ? wp_unslash( $_POST['order_id'] ) : '';
				$tracking_id = ( isset( $_POST['tracking_id'] ) ) ? wp_unslash( $_POST['tracking_id'] ) : '';
				$amount      = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : 0;
				if ( empty( $order_id ) || empty( $tracking_id ) || empty( $amount ) ) {
					$data['status'] = 'NG';
					wp_send_json( $data );
					break;
				}

				$res           = '';
				$acting_status = '';
				$acting_opts   = $this->get_acting_settings();
				$connection    = $this->get_connection();
				$request_date  = wp_date( 'YmdHis' );
				$sps_hashcode  = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $tracking_id . $request_date . $amount . $request_date . $acting_opts['hash_key'];
				$sps_hashcode  = sha1( $sps_hashcode );

				/* 部分返金要求 */
				$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00307-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<processing_datetime>' . $request_date . '</processing_datetime>
	<pay_option_manage>
		<amount>' . $amount . '</amount>
	</pay_option_manage>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
				$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
				$response_settlement = $this->xml2assoc( $xml_settlement, $this->acting_card );
				$response_settlement = apply_filters( 'usces_filter_sbps_card_refund_log', $response_settlement, $order_id );
				if ( isset( $response_settlement['res_result'] ) ) {
					$status = 'refund';
					$result = $response_settlement['res_result'];
					if ( 'OK' === $result ) {
						$response_settlement['amount'] = $amount * -1;
						$this->save_acting_log( $response_settlement, $this->acting_card, $status, $result, $order_id, $tracking_id );
						$class         = ' card-' . $status;
						$status_name   = $this->get_status_name( $status );
						$sales_amount  = $this->get_sales_amount( $order_id, $tracking_id );
						$res          .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$res          .= '<table class="sbps-settlement-admin-table">
							<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
								<td><input type="tel" class="settlement-amount" value="' . intval( $sales_amount ) . '" disabled="disabled" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>
							</tr></table>';
						$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
					} else {
						$latest_log = $this->get_acting_latest_log( $order_id, $tracking_id );
						if ( ! isset( $response_settlement['amount'] ) ) {
							if ( isset( $latest_log['amount'] ) ) {
								$response_settlement['amount'] = $latest_log['amount'];
							}
						}
						$this->save_acting_log( $response_settlement, $this->acting_card, $status, $result, $order_id, $tracking_id );
						$status      = $latest_log['status'];
						$class       = ' card-' . $status;
						$status_name = $this->get_status_name( $status );
						$res        .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$res        .= '<table class="sbps-settlement-admin-table">
							<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
								<td><input type="tel" value="' . $latest_log['amount'] . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>
							</tr></table>';
						$res        .= '<div class="sbps-settlement-admin-button">';
						if ( ! $this->is_status( array( 'cancel', 'refund' ), $order_id, $tracking_id ) ) {
							$res .= '<input id="cancel-settlement" type="button" class="button" value="取消" />';
							$res .= '<input id="refund-settlement" type="button" class="button" value="部分返金" />';
						}
						$res .= '</div>';
					}
				} else {
					$result = 'NG';
				}
				$res                  .= $this->settlement_history( $order_id, $tracking_id );
				$data['status']        = $result;
				$data['result']        = $res;
				$data['acting_status'] = $acting_status;
				wp_send_json( $data );
				break;

			/* クレジットカード決済エラー */
			case 'error_sbps_card':
				break;

			/* 継続課金情報更新 */
			case 'continuation_update':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id         = ( isset( $_POST['order_id'] ) ) ? wp_unslash( $_POST['order_id'] ) : '';
				$member_id        = ( isset( $_POST['member_id'] ) ) ? wp_unslash( $_POST['member_id'] ) : '';
				$contracted_year  = ( isset( $_POST['contracted_year'] ) ) ? wp_unslash( $_POST['contracted_year'] ) : '';
				$contracted_month = ( isset( $_POST['contracted_month'] ) ) ? wp_unslash( $_POST['contracted_month'] ) : '';
				$contracted_day   = ( isset( $_POST['contracted_day'] ) ) ? wp_unslash( $_POST['contracted_day'] ) : '';
				$charged_year     = ( isset( $_POST['charged_year'] ) ) ? wp_unslash( $_POST['charged_year'] ) : '';
				$charged_month    = ( isset( $_POST['charged_month'] ) ) ? wp_unslash( $_POST['charged_month'] ) : '';
				$charged_day      = ( isset( $_POST['charged_day'] ) ) ? wp_unslash( $_POST['charged_day'] ) : '';
				$price            = ( isset( $_POST['price'] ) ) ? wp_unslash( $_POST['price'] ) : 0;
				$status           = ( isset( $_POST['status'] ) ) ? wp_unslash( $_POST['status'] ) : '';

				$continue_data = $this->get_continuation_data( $member_id, $order_id );
				if ( ! $continue_data ) {
					$data['status'] = 'NG';
					wp_send_json( $data );
					break;
				}

				/* 継続中→停止 */
				if ( 'continuation' === $continue_data['status'] && 'cancellation' === $status ) {
					$this->update_continuation_data( $member_id, $order_id, $continue_data, true );
				} else {
					if ( ! empty( $contracted_year ) && ! empty( $contracted_month ) && ! empty( $contracted_day ) ) {
						$contracted_date = ( empty( $continue_data['contractedday'] ) ) ? dlseller_next_contracting( $order_id ) : $continue_data['contractedday'];
						if ( $contracted_date ) {
							$new_contracted_date = $contracted_year . '-' . $contracted_month . '-' . $contracted_day;
							if ( ! $this->isdate( $new_contracted_date ) ) {
								$data['status']  = 'NG';
								$data['message'] = __( 'Next contract renewal date is incorrect.', 'dlseller' );
								wp_send_json( $data );
							}
						}
					} else {
						$new_contracted_date = '';
					}
					$new_charged_date = $charged_year . '-' . $charged_month . '-' . $charged_day;
					if ( ! $this->isdate( $new_charged_date ) ) {
						$data['status']  = 'NG';
						$data['message'] = __( 'Next settlement date is incorrect.', 'dlseller' );
						wp_send_json( $data );
					}
					$tomorrow = date_i18n( 'Y-m-d', strtotime( '+1 day' ) );
					if ( $new_charged_date < $tomorrow ) {
						$data['status']  = 'NG';
						$data['message'] = sprintf( __( 'The next settlement date must be after %s.', 'dlseller' ), $tomorrow );
						wp_send_json( $data );
					}
					$continue_data['contractedday'] = $new_contracted_date;
					$continue_data['chargedday']    = $new_charged_date;
					$continue_data['price']         = usces_crform( $price, false, false, 'return', false );
					$continue_data['status']        = $status;
					$this->update_continuation_data( $member_id, $order_id, $continue_data );
				}
				$data['status'] = 'OK';
				wp_send_json( $data );
				break;

			/* PayPay参照 */
			case 'get_sbps_paypay':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id    = ( isset( $_POST['order_id'] ) ) ? wp_unslash( $_POST['order_id'] ) : '';
				$tracking_id = ( isset( $_POST['tracking_id'] ) ) ? wp_unslash( $_POST['tracking_id'] ) : '';
				if ( empty( $order_id ) || empty( $tracking_id ) ) {
					$data['status'] = 'NG';
					wp_send_json( $data );
					break;
				}

				$res                  = '';
				$status               = '';
				$reference_settlement = $this->get_settlement_status( 'sbps_paypay', $order_id, $tracking_id );
				if ( ( isset( $reference_settlement['res_result'] ) && 'OK' === $reference_settlement['res_result'] ) && ( isset( $reference_settlement['res_status'] ) && 0 === (int) $reference_settlement['res_status'] ) ) {
					$result         = 'OK';
					$payment_status = $reference_settlement['res_pay_method_info']['payment_status'];
					switch ( $payment_status ) {
						case 1:/* 与信済 */
							$status = 'manual';
							break;
						case 0:/* 処理中 */
						case 2:/* 売上処理中 */
							$status = 'pending';
							break;
						case 3:/* 入金済（売上済） */
							$status = 'sales';
							break;
						case 4:/* 与信取消済 */
						case 5:/* 返金済 */
							$status = 'cancel';
							break;
						case 9:/* 処理エラー */
							$status = 'error';
							break;
					}

					$latest_log = $this->get_acting_latest_log( $order_id, $tracking_id );
					if ( $status !== $latest_log['status'] ) {
						if ( 'cancel' === $status && 'refund' === $latest_log['status'] ) {
							$status = 'refund';
						}
					}

					$class       = ' paypay-' . $status;
					$status_name = $this->get_status_name( $status );
					$res        .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
					if ( 'pending' !== $status && 'error' !== $status ) {
						$amount = ( 'cancel' === $status ) ? 0 : $this->get_sales_amount( $order_id, $tracking_id );
						if ( 'cancel' === $status || 'refund' === $status ) {
							$res .= '<table class="sbps-settlement-admin-table">
								<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
									<td><input type="tel" class="settlement-amount" value="' . intval( $amount ) . '" disabled="disabled" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>
								</tr></table>';
						} else {
							$res .= '<table class="sbps-settlement-admin-table">
								<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
									<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $amount ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $amount ) . '" /></td>
								</tr></table>';
							$res .= '<div class="sbps-settlement-admin-button">';
							if ( 'manual' === $status ) {
								$res .= '<input id="sales-settlement" type="button" class="button" value="売上確定" />';
							}
							$res .= '<input id="cancel-settlement" type="button" class="button" value="取消" />';
							if ( 'manual' !== $status ) {
								$res .= '<input id="refund-settlement" type="button" class="button" value="部分返金" />';
							}
							if ( ! $this->is_status( array( 'auto', 'sales', 'increase' ), $order_id, $tracking_id ) ) {
								$res .= '<input id="increase-settlement" type="button" class="button" value="増額売上" />';
							}
							$res .= '</div>';
						}
					} else {
						if ( 'pending' !== $status ) {
							$status      = 'error';
							$status_name = $this->get_status_name( $status );
							$res        .= '<div class="sbps-settlement-admin paypay-error">' . $status_name . '</div>';
						}
					}
				} else {
					$result      = 'NG';
					$status      = 'error';
					$status_name = $this->get_status_name( $status );
					$res        .= '<div class="sbps-settlement-admin paypay-error">' . $status_name . '</div>';
				}
				$res           .= $this->settlement_history( $order_id, $tracking_id );
				$data['status'] = $result;
				$data['result'] = $res;
				wp_send_json( $data );
				break;

			/* PayPay売上確定 */
			case 'sales_sbps_paypay':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id    = ( isset( $_POST['order_id'] ) ) ? wp_unslash( $_POST['order_id'] ) : '';
				$tracking_id = ( isset( $_POST['tracking_id'] ) ) ? wp_unslash( $_POST['tracking_id'] ) : '';
				$amount      = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : 0;
				if ( empty( $order_id ) || empty( $tracking_id ) || empty( $amount ) ) {
					$data['status'] = 'NG';
					wp_send_json( $data );
					break;
				}

				$res           = '';
				$acting_status = '';
				$acting_opts   = $this->get_acting_settings();
				$connection    = $this->get_connection();
				$request_date  = wp_date( 'YmdHis' );
				$sps_hashcode  = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $tracking_id . $amount . $request_date . $acting_opts['hash_key'];
				$sps_hashcode  = sha1( $sps_hashcode );

				/* 売上要求 */
				$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00201-311">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<pay_option_manage>
		<amount>' . $amount . '</amount>
	</pay_option_manage>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
				$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
				$response_settlement = $this->xml2assoc( $xml_settlement, $this->acting_paypay );
				$response_settlement = apply_filters( 'usces_filter_sbps_paypay_sales_log', $response_settlement, $order_id );
				if ( isset( $response_settlement['res_result'] ) ) {
					$status = 'sales';
					$result = $response_settlement['res_result'];
					if ( 'OK' === $result ) {
						if ( ! isset( $response_settlement['amount'] ) ) {
							$response_settlement['amount'] = $amount;
						}
						$this->save_acting_log( $response_settlement, $this->acting_paypay, $status, $result, $order_id, $tracking_id );
						$class         = ' paypay-' . $status;
						$status_name   = $this->get_status_name( $status );
						$res          .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
					} else {
						$latest_log = $this->get_acting_latest_log( $order_id, $tracking_id );
						if ( ! isset( $response_settlement['amount'] ) ) {
							if ( isset( $latest_log['amount'] ) ) {
								$response_settlement['amount'] = $latest_log['amount'];
							}
						}
						$this->save_acting_log( $response_settlement, $this->acting_paypay, $status, $result, $order_id, $tracking_id );
						$status      = $latest_log['status'];
						$class       = ' paypay-' . $status;
						$status_name = $this->get_status_name( $status );
						$res        .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$amount      = $latest_log['amount'];
					}
					$res .= '<table class="sbps-settlement-admin-table">
						<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
							<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $amount ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $amount ) . '" /></td>
						</tr></table>';
					$res .= '<div class="sbps-settlement-admin-button">';
					if ( 'manual' === $status ) {
						$res .= '<input id="sales-settlement" type="button" class="button" value="売上確定" />';
					}
					if ( ! $this->is_status( array( 'cancel', 'refund' ), $order_id, $tracking_id ) ) {
						$res .= '<input id="cancel-settlement" type="button" class="button" value="取消" />';
						if ( 'manual' !== $status ) {
							$res .= '<input id="refund-settlement" type="button" class="button" value="部分返金" />';
						}
						if ( ! $this->is_status( array( 'auto', 'sales', 'increase' ), $order_id, $tracking_id ) ) {
							$res .= '<input id="increase-settlement" type="button" class="button" value="増額売上" />';
						}
					}
					$res .= '</div>';
				} else {
					$result      = 'NG';
					$status      = 'error';
					$status_name = $this->get_status_name( $status );
					$res        .= '<div class="sbps-settlement-admin card-error">' . $status_name . '</div>';
				}
				$res                  .= $this->settlement_history( $order_id, $tracking_id );
				$data['status']        = $result;
				$data['result']        = $res;
				$data['acting_status'] = $acting_status;
				wp_send_json( $data );
				break;

			/* PayPay取消 */
			case 'cancel_sbps_paypay':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id    = ( isset( $_POST['order_id'] ) ) ? wp_unslash( $_POST['order_id'] ) : '';
				$tracking_id = ( isset( $_POST['tracking_id'] ) ) ? wp_unslash( $_POST['tracking_id'] ) : '';
				if ( empty( $order_id ) || empty( $tracking_id ) ) {
					$data['status'] = 'NG';
					wp_send_json( $data );
					break;
				}

				$res           = '';
				$acting_status = '';
				$acting_opts   = $this->get_acting_settings();
				$connection    = $this->get_connection();
				$encrypted_flg = '1';
				$request_date  = wp_date( 'YmdHis' );
				$sps_hashcode  = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $tracking_id . $encrypted_flg . $request_date . $acting_opts['hash_key'];
				$sps_hashcode  = sha1( $sps_hashcode );

				/* 取消返金要求 */
				$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00303-311">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<encrypted_flg>' . $encrypted_flg . '</encrypted_flg>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
				$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
				$response_settlement = $this->xml2assoc( $xml_settlement, $this->acting_paypay, $encrypted_flg );
				$response_settlement = apply_filters( 'usces_filter_sbps_paypay_cancel_log', $response_settlement, $order_id );
				if ( isset( $response_settlement['res_result'] ) ) {
					$status = 'cancel';
					$result = $response_settlement['res_result'];
					if ( 'OK' === $result ) {
						$response_settlement['amount'] = 0;
						$this->save_acting_log( $response_settlement, $this->acting_paypay, $status, $result, $order_id, $tracking_id );
						$class         = ' paypay-' . $status;
						$status_name   = $this->get_status_name( $status );
						$res          .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
						$res          .= '<table class="sbps-settlement-admin-table">
							<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
								<td><input type="tel" class="settlement-amount" value="0" disabled="disabled" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>
							</tr></table>';
						$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
					} else {
						$latest_log = $this->get_acting_latest_log( $order_id, $tracking_id );
						if ( ! isset( $response_settlement['amount'] ) ) {
							if ( isset( $latest_log['amount'] ) ) {
								$response_settlement['amount'] = $latest_log['amount'];
							}
						}
						$this->save_acting_log( $response_settlement, $this->acting_paypay, $status, $result, $order_id, $tracking_id );
						$status      = $latest_log['status'];
						$class       = ' paypay-' . $status;
						$status_name = $this->get_status_name( $status );
						$res        .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$res        .= '<table class="sbps-settlement-admin-table">
							<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
								<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $latest_log['amount'] ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $latest_log['amount'] ) . '" /></td>
							</tr></table>';
						$res        .= '<div class="sbps-settlement-admin-button">';
						if ( 'manual' === $status ) {
							$res .= '<input id="sales-settlement" type="button" class="button" value="売上確定" />';
						}
						if ( ! $this->is_status( array( 'cancel', 'refund' ), $order_id, $tracking_id ) ) {
							$res .= '<input id="cancel-settlement" type="button" class="button" value="取消" />';
							if ( 'manual' !== $status ) {
								$res .= '<input id="refund-settlement" type="button" class="button" value="部分返金" />';
							}
							if ( ! $this->is_status( array( 'auto', 'sales', 'increase' ), $order_id, $tracking_id ) ) {
								$res .= '<input id="increase-settlement" type="button" class="button" value="増額売上" />';
							}
						}
						$res .= '</div>';
					}
				} else {
					$result = 'NG';
				}
				$res                  .= $this->settlement_history( $order_id, $tracking_id );
				$data['status']        = $result;
				$data['result']        = $res;
				$data['acting_status'] = $acting_status;
				wp_send_json( $data );
				break;

			/* PayPay返金 */
			case 'refund_sbps_paypay':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id    = ( isset( $_POST['order_id'] ) ) ? wp_unslash( $_POST['order_id'] ) : '';
				$tracking_id = ( isset( $_POST['tracking_id'] ) ) ? wp_unslash( $_POST['tracking_id'] ) : '';
				$amount      = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : 0;
				if ( empty( $order_id ) || empty( $tracking_id ) || empty( $amount ) ) {
					$data['status'] = 'NG';
					wp_send_json( $data );
					break;
				}

				$res           = '';
				$acting_status = '';
				$acting_opts   = $this->get_acting_settings();
				$connection    = $this->get_connection();
				$request_date  = wp_date( 'YmdHis' );
				$sps_hashcode  = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $tracking_id . $amount . $request_date . $acting_opts['hash_key'];
				$sps_hashcode  = sha1( $sps_hashcode );

				/* 返金要求 */
				$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00306-311">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<pay_option_manage>
		<amount>' . $amount . '</amount>
	</pay_option_manage>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
				$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
				$response_settlement = $this->xml2assoc( $xml_settlement, $this->acting_paypay );
				$response_settlement = apply_filters( 'usces_filter_sbps_paypay_refund_log', $response_settlement, $order_id );
				if ( isset( $response_settlement['res_result'] ) ) {
					$status = 'refund';
					$result = $response_settlement['res_result'];
					if ( 'OK' === $result ) {
						$response_settlement['amount'] = $amount * -1;
						$this->save_acting_log( $response_settlement, $this->acting_paypay, $status, $result, $order_id, $tracking_id );
						$class         = ' paypay-' . $status;
						$status_name   = $this->get_status_name( $status );
						$sales_amount  = $this->get_sales_amount( $order_id, $tracking_id );
						$res          .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$res          .= '<table class="sbps-settlement-admin-table">
							<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
								<td><input type="tel" class="settlement-amount" value="' . intval( $sales_amount ) . '" disabled="disabled" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>
							</tr></table>';
						$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
					} else {
						$latest_log = $this->get_acting_latest_log( $order_id, $tracking_id );
						if ( ! isset( $response_settlement['amount'] ) ) {
							if ( isset( $latest_log['amount'] ) ) {
								$response_settlement['amount'] = $latest_log['amount'];
							}
						}
						$this->save_acting_log( $response_settlement, $this->acting_paypay, $status, $result, $order_id, $tracking_id );
						$status      = $latest_log['status'];
						$class       = ' paypay-' . $status;
						$status_name = $this->get_status_name( $status );
						$res        .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$res        .= '<table class="sbps-settlement-admin-table">
							<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
								<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $latest_log['amount'] ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $latest_log['amount'] ) . '" /></td>
							</tr></table>';
						$res        .= '<div class="sbps-settlement-admin-button">';
						if ( ! $this->is_status( array( 'cancel', 'refund' ), $order_id, $tracking_id ) ) {
							$res .= '<input id="cancel-settlement" type="button" class="button" value="取消" />';
							if ( 'manual' !== $status ) {
								$res .= '<input id="refund-settlement" type="button" class="button" value="部分返金" />';
							}
							if ( ! $this->is_status( array( 'auto', 'sales', 'increase' ), $order_id, $tracking_id ) ) {
								$res .= '<input id="increase-settlement" type="button" class="button" value="増額売上" />';
							}
						}
						$res .= '</div>';
					}
				} else {
					$result = 'NG';
				}
				$res                  .= $this->settlement_history( $order_id, $tracking_id );
				$data['status']        = $result;
				$data['result']        = $res;
				$data['acting_status'] = $acting_status;
				wp_send_json( $data );
				break;

			/* PayPay増額売上 */
			case 'increase_sbps_paypay':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id    = ( isset( $_POST['order_id'] ) ) ? wp_unslash( $_POST['order_id'] ) : '';
				$tracking_id = ( isset( $_POST['tracking_id'] ) ) ? wp_unslash( $_POST['tracking_id'] ) : '';
				$amount      = ( isset( $_POST['amount'] ) ) ? wp_unslash( $_POST['amount'] ) : 0;
				if ( empty( $order_id ) || empty( $tracking_id ) || empty( $amount ) ) {
					$data['status'] = 'NG';
					wp_send_json( $data );
					break;
				}

				$res           = '';
				$acting_status = '';
				$acting_opts   = $this->get_acting_settings();
				$connection    = $this->get_connection();
				$request_date  = wp_date( 'YmdHis' );
				$sps_hashcode  = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $tracking_id . $amount . $request_date . $acting_opts['hash_key'];
				$sps_hashcode  = sha1( $sps_hashcode );

				/* 売上要求 */
				$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00201-311">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<pay_option_manage>
		<amount>' . $amount . '</amount>
	</pay_option_manage>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
				$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
				$response_settlement = $this->xml2assoc( $xml_settlement, $this->acting_paypay );
				$response_settlement = apply_filters( 'usces_filter_sbps_paypay_increase_log', $response_settlement, $order_id );
				if ( isset( $response_settlement['res_result'] ) ) {
					$result = $response_settlement['res_result'];
					if ( 'OK' === $result ) {
						if ( ! isset( $response_settlement['amount'] ) ) {
							$response_settlement['amount'] = $amount;
						}
						$status = 'increase';
						$this->save_acting_log( $response_settlement, $this->acting_paypay, $status, $result, $order_id, $tracking_id );
						$class         = ' paypay-' . $status;
						$status_name   = $this->get_status_name( $status );
						$res          .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$res          .= '<table class="sbps-settlement-admin-table">
							<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
								<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $amount ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $amount ) . '" /></td>
							</tr></table>';
						$res          .= '<div class="sbps-settlement-admin-button">';
						$res          .= '<input id="sales-settlement" type="button" class="button" value="売上確定" />';
						$res          .= '<input id="cancel-settlement" type="button" class="button" value="取消" />';
						$res          .= '</div>';
						$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
					} elseif ( 'AC' === $result ) {
						$status = 'pending';
						if ( ! isset( $response_settlement['amount'] ) ) {
							$response_settlement['amount'] = $amount;
						}
						$this->save_acting_log( $response_settlement, $this->acting_paypay, $status, $result, $order_id, $tracking_id );
						$class         = ' paypay-' . $status;
						$status_name   = $this->get_status_name( $status );
						$res          .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
					} else {
						if ( ! isset( $response_settlement['amount'] ) ) {
							$response_settlement['amount'] = $amount;
						}
						$this->save_acting_log( $response_settlement, $this->acting_paypay, 'pending', $result, $order_id, $tracking_id );
						$latest_log  = $this->get_acting_latest_log( $order_id, $tracking_id );
						$status      = $latest_log['status'];
						$class       = ' paypay-' . $status;
						$status_name = $this->get_status_name( $status );
						$res        .= '<div class="sbps-settlement-admin' . $class . '">' . $status_name . '</div>';
						$res        .= '<table class="sbps-settlement-admin-table">
							<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>
								<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $latest_log['amount'] ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $latest_log['amount'] ) . '" /></td>
							</tr></table>';
						$res        .= '<div class="sbps-settlement-admin-button">';
						if ( 'manual' === $status ) {
							$res .= '<input id="sales-settlement" type="button" class="button" value="売上確定" />';
						}
						if ( ! $this->is_status( array( 'cancel', 'refund' ), $order_id, $tracking_id ) ) {
							$res .= '<input id="cancel-settlement" type="button" class="button" value="取消" />';
							if ( 'manual' !== $status ) {
								$res .= '<input id="refund-settlement" type="button" class="button" value="部分返金" />';
							}
							if ( ! $this->is_status( array( 'auto', 'sales', 'increase' ), $order_id, $tracking_id ) ) {
								$res .= '<input id="increase-settlement" type="button" class="button" value="増額売上" />';
							}
						}
						$res .= '</div>';
					}
				} else {
					$result = 'NG';
				}
				$res                  .= $this->settlement_history( $order_id, $tracking_id );
				$data['status']        = $result;
				$data['result']        = $res;
				$data['acting_status'] = $acting_status;
				wp_send_json( $data );
				break;
		}
	}

	/**
	 * 受注編集画面に表示する決済情報の値整形
	 * usces_filter_settle_info_field_value
	 *
	 * @param  string $value Settlement information value.
	 * @param  string $key Settlement information key.
	 * @param  string $acting Acting type.
	 * @return string
	 */
	public function settlement_info_field_value( $value, $key, $acting ) {
		if ( ! in_array( 'acting_' . $acting, $this->pay_method ) ) {
			return $value;
		}
		$value = parent::settlement_info_field_value( $value, $key, $acting );

		return $value;
	}

	/**
	 * 決済状況
	 * usces_filter_orderlist_detail_value
	 *
	 * @param  string $detail HTML.
	 * @param  string $value Settlement info key.
	 * @param  string $key Settlement info value.
	 * @param  int    $order_id Order number.
	 * @return array
	 */
	public function orderlist_settlement_status( $detail, $value, $key, $order_id ) {
		global $usces;

		if ( 'wc_trans_id' !== $key || empty( $value ) ) {
			return $detail;
		}

		$order_data = $usces->get_order_data( $order_id, 'direct' );
		$payment    = usces_get_payments_by_name( $order_data['order_payment_name'] );
		$acting_flg = ( isset( $payment['settlement'] ) ) ? $payment['settlement'] : '';
		if ( 'acting_sbps_card' === $acting_flg || 'acting_sbps_paypay' === $acting_flg ) {
			$tracking_id = $usces->get_order_meta_value( 'res_tracking_id', $order_id );
			if ( 'acting_sbps_card' === $acting_flg && defined( 'WCEX_AUTO_DELIVERY' ) && empty( $tracking_id ) ) {
				$trans_id   = $usces->get_order_meta_value( 'trans_id', $order_id );
				$regular_id = $usces->get_order_meta_value( 'regular_id', $order_id );
				if ( ! empty( $regular_id ) && empty( $trans_id ) ) {
					$acting_status = 'error';
				} else {
					$latest_log = $this->get_acting_latest_log( $order_id, 0, 'ALL' );
					if ( isset( $latest_log['result'] ) && 'OK' !== $latest_log['result'] ) {
						$cust_ref = $this->api_customer_reference( $order_data['mem_id'] );
						if ( isset( $cust_ref['result'] ) && 'OK' === $cust_ref['result'] ) {
						} else {
							$acting_status = 'error';
						}
					}
				}
			} else {
				$acting_status = $this->get_acting_status( $order_id, $tracking_id );
			}
			if ( ! empty( $acting_status ) ) {
				$status_name = '';
				$class       = '';
				switch ( $acting_flg ) {
					case 'acting_sbps_card':
						$class       = ' card-' . $acting_status;
						$status_name = $this->get_status_name( $acting_status );
						break;
					case 'acting_sbps_paypay':
						$class       = ' paypay-' . $acting_status;
						$status_name = $this->get_status_name( $acting_status );
						break;
				}
				$detail = '<td>' . $value . '<span class="acting-status' . $class . '">' . $status_name . '</span></td>';
			}
		}
		return $detail;
	}

	/**
	 * 受注編集画面【ステータス】
	 * usces_action_order_edit_form_status_block_middle
	 *
	 * @param array  $data Order data.
	 * @param array  $cscs_meta Custom field data.
	 * @param string $action_args {
	 *     The array of Order related data.
	 *     @type string $order_action Order action mode.
	 *     @type int    $order_id     Order ID.
	 *     @type array  $cart         Cart data.
	 * }
	 */
	public function settlement_status( $data, $cscs_meta, $action_args ) {
		global $usces;
		extract( $action_args );

		if ( 'new' !== $order_action && ! empty( $order_id ) ) {
			$payment    = usces_get_payments_by_name( $data['order_payment_name'] );
			$acting_flg = ( isset( $payment['settlement'] ) ) ? $payment['settlement'] : '';
			if ( 'acting_sbps_card' === $acting_flg || 'acting_sbps_paypay' === $acting_flg ) {
				$tracking_id = $usces->get_order_meta_value( 'res_tracking_id', $order_id );
				if ( 'acting_sbps_card' === $acting_flg && defined( 'WCEX_AUTO_DELIVERY' ) && empty( $tracking_id ) ) {
					$trans_id   = $usces->get_order_meta_value( 'trans_id', $order_id );
					$regular_id = $usces->get_order_meta_value( 'regular_id', $order_id );
					if ( ! empty( $regular_id ) && empty( $trans_id ) ) {
						$acting_status = 'error';
					} else {
						$latest_log = $this->get_acting_latest_log( $order_id, 0, 'ALL' );
						if ( isset( $latest_log['result'] ) && 'OK' !== $latest_log['result'] ) {
							$cust_ref = $this->api_customer_reference( $data['mem_id'] );
							if ( isset( $cust_ref['result'] ) && 'OK' === $cust_ref['result'] ) {
							} else {
								$acting_status = 'error';
							}
						}
					}
				} else {
					$acting_status = $this->get_acting_status( $order_id, $tracking_id );
				}
				if ( ! empty( $acting_status ) ) {
					$status_name = '';
					$class       = '';
					switch ( $acting_flg ) {
						case 'acting_sbps_card':
							$class       = ' card-' . $acting_status;
							$status_name = $this->get_status_name( $acting_status );
							break;
						case 'acting_sbps_paypay':
							$class       = ' paypay-' . $acting_status;
							$status_name = $this->get_status_name( $acting_status );
							break;
					}
					if ( ! empty( $status_name ) ) {
						echo '
						<tr>
							<td class="label status">' . esc_html__( 'Settlement status', 'usces' ) . '</td>
							<td class="col1 status"><span id="settlement-status"><span class="acting-status' . esc_attr( $class ) . '">' . esc_html( $status_name ) . '</span></span></td>
						</tr>';
					}
				}
			}
		}
	}

	/**
	 * 受注編集画面【支払情報】
	 * usces_action_order_edit_form_settle_info
	 *
	 * @param array  $data Order data.
	 * @param string $action_args {
	 *     The array of Order related data.
	 *     @type string $order_action Order action mode.
	 *     @type int    $order_id     Order ID.
	 *     @type array  $cart         Cart data.
	 * }
	 */
	public function settlement_information( $data, $action_args ) {
		global $usces;
		extract( $action_args );

		if ( 'new' !== $order_action && ! empty( $order_id ) ) {
			$payment = usces_get_payments_by_name( $data['order_payment_name'] );
			if ( 'acting_sbps_card' === $payment['settlement'] || 'acting_sbps_paypay' === $payment['settlement'] ) {
				$tracking_id = $usces->get_order_meta_value( 'res_tracking_id', $order_id );
				if ( empty( $tracking_id ) ) {
					$acting_data = usces_unserialize( $usces->get_order_meta_value( $payment['settlement'], $order_id ) );
					$tracking_id = ( isset( $acting_data['res_tracking_id'] ) ) ? $acting_data['res_tracking_id'] : '';
				}
				if ( 'acting_sbps_card' === $payment['settlement'] && empty( $tracking_id ) ) {
					$tracking_id = $usces->get_order_meta_value( 'trans_id', $order_id );
				}
				if ( ! empty( $tracking_id ) ) {
					echo '<input type="button" class="button settlement-information" id="settlement-information-' . esc_attr( $tracking_id ) . '" data-tracking_id="' . esc_attr( $tracking_id ) . '" data-num="1" value="' . esc_html__( 'Settlement info', 'usces' ) . '">';
				}
			}
		}
	}

	/**
	 * 決済情報ダイアログ
	 * usces_action_endof_order_edit_form
	 *
	 * @param array  $data Order data.
	 * @param string $action_args {
	 *     The array of Order related data.
	 *     @type string $order_action Order action mode.
	 *     @type int    $order_id     Order ID.
	 *     @type array  $cart         Cart data.
	 * }
	 */
	public function settlement_dialog( $data, $action_args ) {
		global $usces;
		extract( $action_args );

		if ( 'new' !== $order_action && ! empty( $order_id ) ) :
			$payment = usces_get_payments_by_name( $data['order_payment_name'] );
			if ( in_array( $payment['settlement'], $this->pay_method ) ) :
				?>
<div id="settlement_dialog" title="">
	<div id="settlement-response-loading"></div>
	<fieldset>
	<div id="settlement-response"></div>
	<input type="hidden" id="order_num">
	<input type="hidden" id="tracking_id">
	<input type="hidden" id="acting" value="<?php echo esc_attr( $payment['settlement'] ); ?>">
	<input type="hidden" id="error">
	</fieldset>
</div>
				<?php
			endif;
		endif;
	}

	/**
	 * 受注データ登録
	 * Call from usces_reg_orderdata() and usces_new_orderdata().
	 * usces_action_reg_orderdata
	 *
	 * @param string $args {
	 *     The array of Order related data.
	 *     @type array  $cart          Cart data.
	 *     @type array  $entry         Entry data.
	 *     @type int    $order_id      Order ID.
	 *     @type int    $member_id     Member ID.
	 *     @type array  $payments      Payment data.
	 *     @type int    $charging_type Charging type.
	 *     @type array  $results       Results data.
	 * }
	 */
	public function register_orderdata( $args ) {
		global $usces;
		extract( $args );

		$acting_flg = $payments['settlement'];
		if ( ! in_array( $acting_flg, $this->pay_method ) ) {
			return;
		}
		if ( ! $entry['order']['total_full_price'] ) {
			return;
		}

		parent::register_orderdata( $args );

		if ( 'acting_sbps_card' === $acting_flg || 'acting_sbps_paypay' === $acting_flg ) {
			$acting_opts = $this->get_acting_settings();
			$acting      = substr( $acting_flg, 7 );
			if ( 'acting_sbps_card' === $acting_flg ) {
				if ( 'on' === $acting_opts['card_activate'] && 'auto' === $acting_opts['sales'] ) {
					$status = 'manual';
				} elseif ( 'token' === $acting_opts['card_activate'] && 'auto' === $acting_opts['sales'] ) {
					$status = 'sales';
				} else {
					$status = $acting_opts['sales'];
				}
			} elseif ( 'acting_sbps_paypay' === $acting_flg ) {
				if ( 'auto' === $acting_opts['paypay_sales'] ) {
					$status = 'manual';
				} else {
					$status = $acting_opts['paypay_sales'];
				}
			}
			$result      = ( isset( $results['res_result'] ) ) ? $results['res_result'] : '';
			$tracking_id = ( isset( $results['res_tracking_id'] ) ) ? $results['res_tracking_id'] : '';
			if ( ! isset( $results['amount'] ) ) {
				$results['amount'] = usces_crform( $entry['order']['total_full_price'], false, false, 'return', false );
			}
			$results = apply_filters( 'usces_filter_' . $acting . '_register_orderdata_log', $results, $args );
			$this->save_acting_log( $results, $acting, $status, $result, $order_id, $tracking_id );

			if ( 'acting_sbps_card' === $acting_flg ) {
				if ( 'on' === $acting_opts['card_activate'] && 'auto' === $acting_opts['sales'] ) {
					$connection   = $this->get_connection();
					$process_date = wp_date( 'Ymd' ) . '000000';
					$request_date = wp_date( 'YmdHis' );
					$sps_hashcode = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $tracking_id . $process_date . $request_date . $acting_opts['hash_key'];
					$sps_hashcode = sha1( $sps_hashcode );

					/* 売上要求（自動売上） */
					$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00201-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<processing_datetime>' . $process_date . '</processing_datetime>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
					$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
					$response_settlement = $this->xml2assoc( $xml_settlement, $this->acting_card );
					if ( isset( $response_settlement['res_result'] ) ) {
						if ( ! isset( $response_settlement['amount'] ) ) {
							$response_settlement['amount'] = usces_crform( $entry['order']['total_full_price'], false, false, 'return', false );
						}
						$response_settlement = apply_filters( 'usces_filter_' . $acting . '_register_orderdata_log', $response_settlement, $args );
						$this->save_acting_log( $response_settlement, $this->acting_card, 'sales', $response_settlement['res_result'], $order_id, $tracking_id );
					}
				}
			} elseif ( 'acting_sbps_paypay' === $acting_flg ) {
				if ( 'auto' === $acting_opts['paypay_sales'] ) {
					$connection   = $this->get_connection();
					$request_date = wp_date( 'YmdHis' );
					$sps_hashcode = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $tracking_id . $request_date . $acting_opts['hash_key'];
					$sps_hashcode = sha1( $sps_hashcode );

					/* 売上要求（自動売上） */
					$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00201-311">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
					$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
					$response_settlement = $this->xml2assoc( $xml_settlement, $this->acting_paypay );
					if ( isset( $response_settlement['res_result'] ) ) {
						if ( ! isset( $response_settlement['amount'] ) ) {
							$response_settlement['amount'] = usces_crform( $entry['order']['total_full_price'], false, false, 'return', false );
						}
						$response_settlement = apply_filters( 'usces_filter_' . $acting . '_register_orderdata_log', $response_settlement, $args );
						$this->save_acting_log( $response_settlement, $this->acting_paypay, 'sales', $response_settlement['res_result'], $order_id, $tracking_id );
					}
				}
			}
		}
	}

	/**
	 * 決済ログ取得
	 *
	 * @param  int    $order_id Order number.
	 * @param  string $tracking_id Tracking ID.
	 * @param  string $result Result.
	 * @return array
	 */
	public function get_acting_log( $order_id = 0, $tracking_id = 0, $result = 'OK' ) {
		global $wpdb;

		if ( empty( $order_id ) ) {
			if ( 'OK' === $result ) {
				$query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}usces_acting_log WHERE `tracking_id` = %s AND `result` IN ( 'OK', 'AC' ) ORDER BY `ID` DESC, `datetime` DESC",
					$tracking_id
				);
			} else {
				$query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}usces_acting_log WHERE `tracking_id` = %s ORDER BY `ID` DESC, `datetime` DESC",
					$tracking_id
				);
			}
		} else {
			if ( empty( $tracking_id ) ) {
				if ( 'OK' === $result ) {
					$query = $wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}usces_acting_log WHERE `datetime` IN( SELECT MAX( `datetime` ) FROM {$wpdb->prefix}usces_acting_log WHERE `order_id` = %d GROUP BY `tracking_id` ) AND `order_id` = %d AND `result` IN ( 'OK', 'AC' ) ORDER BY `ID` DESC, `datetime` DESC",
						$order_id,
						$order_id
					);
				} else {
					$query = $wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}usces_acting_log WHERE `datetime` IN( SELECT MAX( `datetime` ) FROM {$wpdb->prefix}usces_acting_log WHERE `order_id` = %d GROUP BY `tracking_id` ) AND `order_id` = %d ORDER BY `ID` DESC, `datetime` DESC",
						$order_id,
						$order_id
					);
				}
			} else {
				if ( 'OK' === $result ) {
					$query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}usces_acting_log WHERE `order_id` = %d AND `tracking_id` = %s AND `result` IN ( 'OK', 'AC' ) ORDER BY `ID` DESC, `datetime` DESC",
						$order_id,
						$tracking_id
					);
				} else {
					$query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}usces_acting_log WHERE `order_id` = %d AND `tracking_id` = %s ORDER BY `ID` DESC, `datetime` DESC",
						$order_id,
						$tracking_id
					);
				}
			}
		}
		$log_data = $wpdb->get_results( $query, ARRAY_A );
		return $log_data;
	}

	/**
	 * 決済ログ出力
	 *
	 * @param  string $log Log data.
	 * @param  string $acting Acting type.
	 * @param  string $status Status.
	 * @param  string $result Result.
	 * @param  int    $order_id Order number.
	 * @param  string $tracking_id Tracking ID.
	 * @return array
	 */
	private function save_acting_log( $log, $acting, $status, $result, $order_id, $tracking_id ) {
		global $wpdb;

		if ( isset( $log['amount'] ) ) {
			$amount = $log['amount'];
		} elseif ( isset( $log['pay_option_manage']['amount'] ) ) {
			$amount = $log['pay_option_manage']['amount'];
		} elseif ( isset( $log['pay_option_manage']['rec_amount'] ) ) {
			$amount = $log['pay_option_manage']['rec_amount'];
		} else {
			$amount = 0;
		}
		$query = $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}usces_acting_log ( `datetime`, `log`, `acting`, `status`, `result`, `amount`, `order_id`, `tracking_id` ) VALUES ( %s, %s, %s, %s, %s, %f, %d, %s )",
			current_time( 'mysql' ),
			usces_serialize( $log ),
			$acting,
			$status,
			$result,
			$amount,
			$order_id,
			$tracking_id
		);
		$res   = $wpdb->query( $query );
		return $res;
	}

	/**
	 * 最新処理取得
	 *
	 * @param  int    $order_id Order number.
	 * @param  string $tracking_id Tracking ID.
	 * @param  string $result Result.
	 * @return array
	 */
	public function get_acting_latest_log( $order_id, $tracking_id, $result = 'OK' ) {
		$latest_log = array();
		$log_data   = $this->get_acting_log( $order_id, $tracking_id, $result );
		if ( $log_data ) {
			$data                   = current( $log_data );
			$latest_log['acting']   = $data['acting'];
			$latest_log['status']   = $data['status'];
			$latest_log['result']   = $data['result'];
			$latest_log['log']      = usces_unserialize( $data['log'] );
			$latest_log['amount']   = $data['amount'];
			$latest_log['order_id'] = $data['order_id'];
		}
		return $latest_log;
	}

	/**
	 * 最新決済金額取得
	 *
	 * @param  int    $order_id Order number.
	 * @param  string $tracking_id Tracking ID.
	 * @return int    $amount Sales amount.
	 */
	private function get_sales_amount( $order_id, $tracking_id ) {
		$sales_amount = 0;
		$log_data     = $this->get_acting_log( $order_id, $tracking_id );
		if ( $log_data ) {
			$amount = 0;
			$refund = 0;
			foreach ( (array) $log_data as $data ) {
				if ( 'refund' === $data['status'] ) {
					$refund = $data['amount'];
				} else {
					if ( $amount < $data['amount'] ) {
						$amount = $data['amount'];
					}
					if ( 'sales' === $data['status'] ) {
						$sales_amount = $data['amount'];
					}
				}
			}
			if ( 0 === (int) $sales_amount ) {
				$sales_amount = $amount;
			}
			if ( 0 !== (int) $refund ) {
				$sales_amount += $refund;
			}
		}
		return $sales_amount;
	}

	/**
	 * 決済処理取得
	 *
	 * @param  int    $order_id Order number.
	 * @param  string $tracking_id Tracking ID.
	 * @return string
	 */
	private function get_acting_status( $order_id, $tracking_id ) {
		global $wpdb;

		$acting_status = '';
		$latest_log    = $this->get_acting_latest_log( $order_id, $tracking_id );
		if ( isset( $latest_log['status'] ) ) {
			$acting_status = $latest_log['status'];
		}
		return $acting_status;
	}

	/**
	 * ステータスチェック
	 *
	 * @param  array  $status Status code.
	 * @param  int    $order_id Order number.
	 * @param  string $tracking_id Tracking ID.
	 * @return boolean
	 */
	private function is_status( $status, $order_id, $tracking_id ) {
		$exist    = false;
		$log_data = $this->get_acting_log( $order_id, $tracking_id );
		if ( $log_data ) {
			foreach ( (array) $log_data as $data ) {
				if ( in_array( $data['status'], $status ) ) {
					$exist = true;
					break;
				}
			}
		}
		return $exist;
	}

	/**
	 * 決済履歴
	 *
	 * @param  int    $order_id Order number.
	 * @param  string $tracking_id Tracking ID.
	 * @return string
	 */
	private function settlement_history( $order_id, $tracking_id ) {
		$html     = '';
		$log_data = $this->get_acting_log( $order_id, $tracking_id, 'ALL' );
		if ( $log_data ) {
			$num  = count( $log_data );
			$html = '<table class="settlement-history">
				<thead class="settlement-history-head">
					<tr><th></th><th>' . __( 'Processing date', 'usces' ) . '</th><th>' . __( 'Sequence number', 'usces' ) . '</th><th>' . __( 'Processing classification', 'usces' ) . '</th><th>' . __( 'Amount', 'usces' ) . '</th><th>' . __( 'Result', 'usces' ) . '</th></tr>
				</thead>
				<tbody class="settlement-history-body">';
			foreach ( (array) $log_data as $data ) {
				$log = usces_unserialize( $data['log'] );
				if ( 'NG' === $data['result'] ) {
					$err_code = ( isset( $log['res_err_code'] ) ) ? '<br>' . $log['res_err_code'] : '';
					$class    = ' error';
				} else {
					$err_code = '';
					$class    = '';
				}
				if ( isset( $log['res_sps_transaction_id'] ) ) {
					$transactionid = $log['res_sps_transaction_id'];
				} elseif ( isset( $log['sps_transaction_id'] ) ) {
					$transactionid = $log['sps_transaction_id'];
				} else {
					$transactionid = '';
				}
				$status_name = ( isset( $data['status'] ) ) ? $this->get_status_name( $data['status'] ) : '';
				$amount      = ( isset( $log['amount'] ) ) ? usces_crform( $log['amount'], false, true, 'return', true ) : '';
				$html       .= '<tr>
					<td class="num">' . $num . '</td>
					<td class="datetime">' . $data['datetime'] . '</td>
					<td class="transactionid">' . $transactionid . '</td>
					<td class="status">' . $status_name . '</td>
					<td class="amount">' . $amount . '</td>
					<td class="result' . $class . '">' . $data['result'] . $err_code . '</td>
				</tr>';
				$num--;
			}
			$html .= '</tbody>
				</table>';
		}
		return $html;
	}

	/**
	 * 処理区分名称取得
	 *
	 * @param  string $status Status code.
	 * @return string
	 */
	private function get_status_name( $status ) {
		$status_name = '';
		switch ( $status ) {
			case 'manual':/* 指定売上時 */
				$status_name = __( '与信済', 'usces' );
				break;
			case 'auto':/* 自動売上時 */
				$status_name = __( '自動売上', 'usces' );
				break;
			case 'sales':/* 管理画面からの売上要求実行時 */
				$status_name = __( '売上確定', 'usces' );
				break;
			case 'refund':/* 管理画面からの部分返金処理実行時 */
				$status_name = __( '部分返金', 'usces' );
				break;
			case 'increase':/* 増額売上確定通知受信時 */
				$status_name = __( '増額売上確定', 'usces' );
				break;
			case 'pending':/* 管理画面からの増額売上実行後 */
				$status_name = __( '増額売上処理中', 'usces' );
				break;
			case 'expired':/* 増額売上期限切れ */
				$status_name = __( '増額売上期限切れ', 'usces' );
				break;
			case 'cancel':/* 取消 */
				$status_name = __( '取消', 'usces' );
				break;
			case 'error':
				$status_name = __( '決済処理不可', 'usces' );
				break;
			default:
				$status_name = $status;
		}
		return $status_name;
	}

	/**
	 * 決済結果参照
	 *
	 * @param  string $acting Acting type.
	 * @param  int    $order_id Order number.
	 * @param  string $tracking_id Tracking ID.
	 * @return array
	 */
	private function get_settlement_status( $acting, $order_id, $tracking_id ) {
		$acting_opts = $this->get_acting_settings();
		if ( empty( $acting_opts['3des_key'] ) || empty( $acting_opts['3desinit_key'] ) ) {
			return array( 'res_result' => 'NG' );
		}
		$connection    = $this->get_connection();
		$encrypted_flg = '1';
		$request_date  = wp_date( 'YmdHis' );
		$sps_hashcode  = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $tracking_id . $encrypted_flg . $request_date . $acting_opts['hash_key'];
		$sps_hashcode  = sha1( $sps_hashcode );
		switch ( $acting ) {
			case 'sbps_card':
				$api_id = 'MG01-00101-101';
				break;
			case 'sbps_paypay':
				$api_id = 'MG01-00101-311';
				break;
			default:
				$api_id = '';
		}

		$response_settlement = array();
		if ( $api_id ) {
			/* 決済結果参照要求 */
			$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="' . $api_id . '">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<encrypted_flg>' . $encrypted_flg . '</encrypted_flg>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
			$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
			$response_settlement = $this->xml2assoc( $xml_settlement, $acting, $encrypted_flg );
		}
		return $response_settlement;
	}

	/**
	 * 受注データ支払方法取得
	 *
	 * @param  int $order_id Order number.
	 * @return string
	 */
	private function get_order_acting_flg( $order_id ) {
		global $wpdb;

		$query              = $wpdb->prepare( "SELECT `order_payment_name` FROM {$wpdb->prefix}usces_order WHERE `ID` = %d", $order_id );
		$order_payment_name = $wpdb->get_var( $query );
		$payment            = usces_get_payments_by_name( $order_payment_name );
		$acting_flg         = ( isset( $payment['settlement'] ) ) ? $payment['settlement'] : '';
		return $acting_flg;
	}

	/**
	 * 会員データ削除チェック
	 * usces_filter_delete_member_check
	 *
	 * @param  boolean $del Deletable.
	 * @param  int     $member_id Member ID.
	 * @return boolean
	 */
	public function delete_member_check( $del, $member_id ) {
		$cust_ref  = $this->api_customer_reference( $member_id );
		if ( isset( $cust_ref['result'] ) && 'OK' === $cust_ref['result'] ) {
			if ( usces_have_member_continue_order( $member_id ) || usces_have_member_regular_order( $member_id ) ) {
				$del = false;
			}
		}
		return $del;
	}

	/**
	 * フロントスクリプト
	 * wp_enqueue_scripts
	 */
	public function enqueue_scripts() {
		global $usces;

		/* 発送・支払方法ページ、クレジットカード情報更新ページ */
		if ( ! is_admin() && $this->is_validity_acting( 'card' ) ) :
			$usces_page = ( isset( $_GET['usces_page'] ) ) ? wp_unslash( $_GET['usces_page'] ) : '';
			if ( ( $usces->is_cart_page( $_SERVER['REQUEST_URI'] ) && 'delivery' === $usces->page ) ||
				( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) && ( 'member_register_settlement' === $usces_page || 'member_update_settlement' === $usces_page ) ) ) :
				$acting_opts = $this->get_acting_settings();
				if ( isset( $acting_opts['card_activate'] ) ) :
					$connection = $this->get_connection();
					?>
<script type="text/javascript" src="<?php echo esc_url( $connection['token_url'] ); ?>"></script>
					<?php
				endif;
			endif;
		endif;
	}

	/**
	 * フロントスクリプト
	 * wp_print_footer_scripts
	 */
	public function footer_scripts() {
		global $usces;

		if ( ! $this->is_validity_acting( 'card' ) ) {
			return;
		}

		parent::footer_scripts();

		/* マイページ */
		if ( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ) :

			/* クレジットカード情報更新ページ */
			$usces_page = ( isset( $_GET['usces_page'] ) ) ? wp_unslash( $_GET['usces_page'] ) : '';
			if ( 'member_register_settlement' === $usces_page || 'member_update_settlement' === $usces_page ) :
				$acting_opts = $this->get_acting_settings();
				wp_register_style( 'sbps-token-style', USCES_FRONT_PLUGIN_URL . '/css/sbps_token.css' );
				wp_enqueue_style( 'sbps-token-style' );
				wp_register_script( 'usces_member_sbps', USCES_FRONT_PLUGIN_URL . '/js/member_sbps.js', array( 'jquery' ), USCES_VERSION, true );
				$sbps_params                    = array();
				$sbps_params['sbps_merchantId'] = $acting_opts['merchant_id'];
				$sbps_params['sbps_serviceId']  = $acting_opts['service_id'];
				$sbps_params['message']         = array(
					'error_token'       => __( 'Credit card information is not appropriate.', 'usces' ),
					'error_card_number' => __( 'Credit card information is not appropriate.', 'usces' ),
					'error_card_expym'  => __( 'Credit card information is not appropriate.', 'usces' ),
					'error_card_seccd'  => __( 'Credit card information is not appropriate.', 'usces' ),
					'confirm_deletion'  => __( 'Are you sure delete credit card registration?', 'usces' ),
				);
				wp_localize_script( 'usces_member_sbps', 'sbps_params', $sbps_params );
				wp_enqueue_script( 'usces_member_sbps' );
				print_google_recaptcha_response( wp_unslash( $_GET['usces_page'] ), 'member-card-info', 'member_update_settlement' );
			else :
				$member = $usces->get_member();
				if ( usces_have_member_continue_order( $member['ID'] ) || usces_have_member_regular_order( $member['ID'] ) ) :
					?>
<script type="text/javascript">
jQuery( document ).ready( function( $ ) {
	$( "input[name='deletemember']" ).css( "display", "none" );
});
</script>
					<?php
				endif;
			endif;
		endif;
	}

	/**
	 * 支払回数
	 * usces_filter_delivery_secure_form_howpay
	 *
	 * @param  string $html HTML.
	 * @return string
	 */
	public function delivery_secure_form_howpay( $html ) {
		$usces_page = ( isset( $_GET['usces_page'] ) ) ? wp_unslash( $_GET['usces_page'] ) : '';
		if ( 'member_register_settlement' === $usces_page || 'member_update_settlement' === $usces_page ) {
			$html = '';
		}
		return $html;
	}

	/**
	 * クレジットカード登録・変更ページ表示
	 * usces_filter_template_redirect
	 */
	public function member_update_settlement() {
		global $usces;

		if ( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ) {
			if ( ! usces_is_membersystem_state() || ! usces_is_login() ) {
				return;
			}

			$acting_opts = $this->get_acting_settings();
			if ( 'off' === $acting_opts['cust_manage'] ) {
				return;
			}

			$usces_page = ( isset( $_REQUEST['usces_page'] ) ) ? wp_unslash( $_REQUEST['usces_page'] ) : '';
			if ( 'member_update_settlement' === $usces_page ) {
				add_filter( 'usces_filter_states_form_js', array( $this, 'states_form_js' ) );
				$usces->page = 'member_update_settlement';
				$this->member_update_settlement_form();
				exit();

			} elseif ( 'member_register_settlement' === $usces_page ) {
				add_filter( 'usces_filter_states_form_js', array( $this, 'states_form_js' ) );
				$usces->page = 'member_register_settlement';
				$this->member_update_settlement_form();
				exit();
			}
		}
		return false;
	}

	/**
	 * クレジットカード登録・変更ページ表示
	 * usces_filter_states_form_js
	 *
	 * @param  string $js Scripts.
	 * @return string
	 */
	public function states_form_js( $js ) {
		return '';
	}

	/**
	 * クレジットカード登録・変更ページリンク
	 * usces_action_member_submenu_list
	 */
	public function e_update_settlement() {
		global $usces;

		$member = $usces->get_member();
		$html   = $this->update_settlement( '', $member );
		echo $html; // no escape.
	}

	/**
	 * クレジットカード登録・変更ページリンク
	 * usces_filter_member_submenu_list
	 *
	 * @param  string $html Submenu area of the member page.
	 * @param  array  $member Member information.
	 * @return string
	 */
	public function update_settlement( $html, $member ) {

		$acting_opts = $this->get_acting_settings();
		if ( 'on' === $acting_opts['cust_manage'] || 'choice' === $acting_opts['cust_manage'] ) {
			$cust_ref = $this->api_customer_reference( $member['ID'] );
			if ( isset( $cust_ref['result'] ) && 'OK' === $cust_ref['result'] ) {
				$update_settlement_url = add_query_arg(
					array(
						'usces_page' => 'member_update_settlement',
						're-enter'   => 1,
					),
					USCES_MEMBER_URL
				);
				$html                 .= '<li class="settlement-update gotoedit"><a href="' . $update_settlement_url . '">' . __( 'Change the credit card is here >>', 'usces' ) . '</a></li>';
			} else {
				$register_settlement_url = add_query_arg(
					array(
						'usces_page' => 'member_register_settlement',
						're-enter'   => 1,
					),
					USCES_MEMBER_URL
				);
				$html                   .= '<li class="settlement-register gotoedit"><a href="' . $register_settlement_url . '">' . __( 'Credit card registration is here >>', 'usces' ) . '</a></li>';
			}
		}
		return $html;
	}

	/**
	 * クレジットカード登録・変更ページ
	 */
	public function member_update_settlement_form() {
		global $usces;

		$member      = $usces->get_member();
		$acting_opts = $this->get_acting_settings();

		$script                = '';
		$done_message          = '';
		$html                  = '';
		$update_settlement_url = add_query_arg(
			array(
				'usces_page' => $usces->page,
				'settlement' => 1,
				're-enter'   => 1,
			),
			USCES_MEMBER_URL
		);
		$register              = ( 'member_register_settlement' === $usces->page ) ? true : false;

		$err_code = '';

		$cust_code = $member['ID'];
		$cardlast4 = '';
		$expyy     = '';
		$expmm     = '';

		if ( 'on' === $acting_opts['cust_manage'] || 'choice' === $acting_opts['cust_manage'] ) {
			$update = ( isset( $_POST['update'] ) ) ? wp_unslash( $_POST['update'] ) : '';
			if ( 'register' === $update ) {
				check_admin_referer( 'member_update_settlement', 'wc_nonce' );
				$verify_action = wel_verify_update_settlement( $member['ID'] );
				if ( ! $verify_action ) {
					$usces->error_message .= '<p>' . __( 'Update has been locked. Please contact the store administrator.', 'usces' ) . '</p>';
				} else {
					$connection               = $this->get_connection();
					$sps_cust_info_return_flg = '1';
					$token                    = sanitize_text_field( wp_unslash( $_POST['token'] ) );
					$token_key                = sanitize_text_field( wp_unslash( $_POST['tokenKey'] ) );
					$cardbrand_return_flg     = '0';
					$encrypted_flg            = '1';
					$request_date             = wp_date( 'YmdHis' );
					$sps_hashcode             = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $cust_code . $sps_cust_info_return_flg . $token . $token_key . $cardbrand_return_flg . $encrypted_flg . $request_date . $acting_opts['hash_key'];
					$sps_hashcode             = sha1( $sps_hashcode );

					/* クレジットカード情報登録要求 */
					$request_cust_reg  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="MG02-00131-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<cust_code>' . $cust_code . '</cust_code>
	<sps_cust_info_return_flg>' . $sps_cust_info_return_flg . '</sps_cust_info_return_flg>
	<pay_option_manage>
		<token>' . $token . '</token>
		<token_key>' . $token_key . '</token_key>
		<cardbrand_return_flg>' . $cardbrand_return_flg . '</cardbrand_return_flg>
	</pay_option_manage>
	<encrypted_flg>' . $encrypted_flg . '</encrypted_flg>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
					$xml_cust_reg      = $this->get_xml_response( $connection['api_url'], $request_cust_reg );
					$response_cust_reg = $this->xml2assoc( $xml_cust_reg, $this->acting_card, $encrypted_flg );
					if ( isset( $response_cust_reg['res_result'] ) && 'OK' === $response_cust_reg['res_result'] ) {
						$done_message = __( 'Successfully registered.', 'usces' );
						$register     = false;
					} else {
						if ( isset( $response_cust_reg['res_err_code'] ) ) {
							$err_code = ' : ' . $response_cust_reg['res_err_code'];
						}
						$done_message = __( 'Registration failed.', 'usces' ) . $err_code;
					}
				}
			} elseif ( 'update' === $update ) {
				check_admin_referer( 'member_update_settlement', 'wc_nonce' );
				$verify_action = wel_verify_update_settlement( $member['ID'] );
				if ( ! $verify_action ) {
					$usces->error_message .= '<p>' . __( 'Update has been locked. Please contact the store administrator.', 'usces' ) . '</p>';
				} else {
					$connection               = $this->get_connection();
					$sps_cust_info_return_flg = '1';
					$token                    = sanitize_text_field( wp_unslash( $_POST['token'] ) );
					$token_key                = sanitize_text_field( wp_unslash( $_POST['tokenKey'] ) );
					$cardbrand_return_flg     = '0';
					$encrypted_flg            = '1';
					$request_date             = wp_date( 'YmdHis' );
					$sps_hashcode             = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $cust_code . $sps_cust_info_return_flg . $token . $token_key . $cardbrand_return_flg . $encrypted_flg . $request_date . $acting_opts['hash_key'];
					$sps_hashcode             = sha1( $sps_hashcode );

					/* クレジットカード情報更新要求 */
					$request_cust_upd  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="MG02-00132-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<cust_code>' . $cust_code . '</cust_code>
	<sps_cust_info_return_flg>' . $sps_cust_info_return_flg . '</sps_cust_info_return_flg>
	<pay_option_manage>
		<token>' . $token . '</token>
		<token_key>' . $token_key . '</token_key>
		<cardbrand_return_flg>' . $cardbrand_return_flg . '</cardbrand_return_flg>
	</pay_option_manage>
	<encrypted_flg>' . $encrypted_flg . '</encrypted_flg>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
					$xml_cust_upd      = $this->get_xml_response( $connection['api_url'], $request_cust_upd );
					$response_cust_upd = $this->xml2assoc( $xml_cust_upd, $this->acting_card, $encrypted_flg );
					if ( isset( $response_cust_upd['res_result'] ) && 'OK' === $response_cust_upd['res_result'] ) {
						$this->send_update_settlement_mail();
						$done_message = __( 'Successfully updated.', 'usces' );
					} else {
						if ( isset( $response_cust_upd['res_err_code'] ) ) {
							$err_code = ' : ' . $response_cust_upd['res_err_code'];
						}
						$done_message = __( 'Update failed.', 'usces' ) . $err_code;
					}
				}
			} elseif ( 'delete' === $update ) {
				check_admin_referer( 'member_update_settlement', 'wc_nonce' );
				$connection               = $this->get_connection();
				$sps_cust_info_return_flg = '1';
				$request_date             = wp_date( 'YmdHis' );
				$sps_hashcode             = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $cust_code . $sps_cust_info_return_flg . $request_date . $acting_opts['hash_key'];
				$sps_hashcode             = sha1( $sps_hashcode );

				/* クレジットカード情報削除要求 */
				$request_cust_del  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="MG02-00103-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<cust_code>' . $cust_code . '</cust_code>
	<sps_cust_info_return_flg>' . $sps_cust_info_return_flg . '</sps_cust_info_return_flg>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
				$xml_cust_del      = $this->get_xml_response( $connection['api_url'], $request_cust_del );
				$response_cust_del = $this->xml2assoc( $xml_cust_del, $this->acting_card );
				if ( isset( $response_cust_del['res_result'] ) && 'OK' === $response_cust_del['res_result'] ) {
					$done_message = __( 'Successfully deleted.', 'usces' );
					$register     = true;
				} else {
					if ( isset( $response_cust_del['res_err_code'] ) ) {
						$err_code = ' : ' . $response_cust_del['res_err_code'];
					}
					$done_message = __( 'Deletion failed.', 'usces' ) . $err_code;
				}
			}

			$cust_ref = $this->api_customer_reference( $cust_code );
			if ( isset( $cust_ref['result'] ) && 'OK' === $cust_ref['result'] ) {
				$cardlast4 = substr( $cust_ref['cc_number'], -4 );
				$expyy     = substr( $cust_ref['cc_expiration'], 0, 4 );
				$expmm     = substr( $cust_ref['cc_expiration'], 4, 2 );
			}
			$html .= '<input name="acting" type="hidden" value="' . $this->paymod_id . '" />
			<table class="customer_form" id="' . $this->paymod_id . '">';
			if ( ! empty( $cardlast4 ) ) {
				$html .= '
				<tr>
					<th scope="row">' . __( 'The last four digits of your card number', 'usces' ) . '</th>
					<td colspan="2"><p>' . $cardlast4 . '</p></td>
				</tr>';
			}
			$cardno_attention = apply_filters( 'usces_filter_cardno_attention', __( '(Single-byte numbers only)', 'usces' ) . '<div class="attention">' . __( '* Please do not enter symbols or letters other than numbers such as space (blank), hyphen (-) between numbers.', 'usces' ) . '</div>' );
			$html            .= '
				<tr>
					<th scope="row">' . __( 'card number', 'usces' ) . '</th>
					<td colspan="2"><input class="cc_number" id="cc_number" type="tel" maxlength="16" value="" />' . $cardno_attention . '</td>
				</tr>';
			$html            .= '
				<tr>
					<th scope="row">' . __( 'Card expiration', 'usces' ) . '</th>
					<td colspan="2">
					<select class="cc_expmm" id="cc_expmm">
						<option value=""' . ( empty( $expmm ) ? ' selected="selected"' : '' ) . '>--</option>';
			for ( $i = 1; $i <= 12; $i++ ) {
				$html .= '
						<option value="' . sprintf( '%02d', $i ) . '"' . ( ( $i == (int) $expmm ) ? ' selected="selected"' : '' ) . '>' . sprintf( '%2d', $i ) . '</option>';
			}
			$html .= '
					</select>' . __( 'month', 'usces' ) . '&nbsp;
					<select class="cc_expyy" id="cc_expyy">
						<option value=""' . ( empty( $expyy ) ? ' selected="selected"' : '' ) . '>----</option>';
			for ( $i = 0; $i < 15; $i++ ) {
				$year     = wp_date( 'Y' ) + $i;
				$selected = ( $year == $expyy ) ? ' selected="selected"' : '';
				$html    .= '
						<option value="' . $year . '"' . $selected . '>' . $year . '</option>';
			}
			$html           .= '
					</select>' . __( 'year', 'usces' ) . '
					</td>
				</tr>';
			$seccd_attention = apply_filters( 'usces_filter_seccd_attention', __( '(Single-byte numbers only)', 'usces' ) );
			$html           .= '
				<tr>
					<th scope="row">' . __( 'security code', 'usces' ) . '</th>
					<td colspan="2"><input class="cc_seccd" id="cc_seccd" type="tel" maxlength="4" value="" />' . $seccd_attention . '</td>
				</tr>';
			$html           .= '
			</table>';
		}

		if ( '' !== $done_message ) {
			$script .= '
<script type="text/javascript">
	jQuery.event.add( window, "load", function() {
		alert( "' . $done_message . '" );
	});
</script>';
		}
		$error_message = apply_filters( 'usces_filter_member_update_settlement_error_message', $usces->error_message );

		ob_start();
		get_header();

		if ( '' !== $script ) {
			echo $script; // no escape due to script.
		}
		?>
<div id="content" class="two-column">
<div class="catbox">
		<?php
		if ( have_posts() ) :
			usces_remove_filter();
			?>
<div class="post" id="wc_member_update_settlement">
			<?php if ( $register ) : ?>
<h1 class="member_page_title"><?php esc_html_e( 'Credit card registration', 'usces' ); ?></h1>
			<?php else : ?>
<h1 class="member_page_title"><?php esc_html_e( 'Credit card update', 'usces' ); ?></h1>
			<?php endif; ?>
<div class="entry">
<div id="memberpages">
<div class="whitebox">
	<div id="memberinfo">
	<div class="header_explanation"></div>
	<div class="error_message"><?php wel_esc_script_e( $error_message ); ?></div>
	<form id="member-card-info" name="member_update_settlement" action="<?php echo esc_url( $update_settlement_url ); ?>" method="post" onKeyDown="if(event.keyCode == 13) {return false;}">
			<?php wel_esc_script_e( $html ); ?>
		<div class="send">
			<input type="hidden" name="update" value="" />
			<input type="hidden" name="token" id="token" value="" />
			<input type="hidden" name="tokenKey" id="tokenKey" value="" />
			<?php if ( $register ) : ?>
			<input type="button" id="card-register" class="card-update" data-update_mode="register" value="<?php esc_attr_e( 'Register', 'usces' ); ?>" />
			<?php else : ?>
			<input type="button" id="card-update" class="card-update" data-update_mode="update" value="<?php esc_attr_e( 'Update', 'usces' ); ?>" />
				<?php if ( ! usces_have_member_continue_order( $member['ID'] ) && ! usces_have_member_regular_order( $member['ID'] ) ) : ?>
			<input type="button" id="card-delete" class="card-delete" data-update_mode="delete" value="<?php esc_attr_e( 'Delete', 'usces' ); ?>" />
				<?php endif; ?>
			<?php endif; ?>
			<input type="button" name="back" value="<?php esc_attr_e( 'Back to the member page.', 'usces' ); ?>" onclick="location.href='<?php echo esc_url( USCES_MEMBER_URL ); ?>'" />
			<input type="button" name="top" value="<?php esc_attr_e( 'Back to the top page.', 'usces' ); ?>" onclick="location.href='<?php echo esc_url( home_url() ); ?>'" />
		</div>
			<?php wp_nonce_field( 'member_update_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="footer_explanation"></div>
	</div><!-- end of memberinfo -->
</div><!-- end of whitebox -->
</div><!-- end of memberpages -->
</div><!-- end of entry -->
</div><!-- end of post -->
		<?php else : ?>
<p><?php esc_html_e( 'Sorry, no posts matched your criteria.', 'usces' ); ?></p>
		<?php endif; ?>
</div><!-- end of catbox -->
</div><!-- end of content -->
		<?php
		$sidebar = apply_filters( 'usces_filter_member_update_settlement_page_sidebar', 'cartmember' );
		if ( ! empty( $sidebar ) ) {
			get_sidebar( $sidebar );
		}
		get_footer();
		$contents = ob_get_contents();
		ob_end_clean();

		echo $contents; // no escape.
	}

	/**
	 * クレジットカード変更メール
	 */
	public function send_update_settlement_mail() {
		global $usces;

		$member = $usces->get_member();
		// $mail_data = $usces->options['mail_data'];

		$subject     = apply_filters( 'usces_filter_send_update_settlement_mail_subject', __( 'Confirmation of credit card update', 'usces' ), $member );
		$mail_header = __( 'Your credit card information has been updated on the membership page.', 'usces' ) . "\r\n\r\n";
		// $mail_footer = $mail_data['footer']['thankyou'];
		$mail_footer = get_option( 'blogname' ) . "\r\n";
		$name        = usces_localized_name( $member['name1'], $member['name2'], 'return' );

		$message  = '--------------------------------' . "\r\n";
		$message .= __( 'Member ID', 'usces' ) . ' : ' . $member['ID'] . "\r\n";
		$message .= __( 'Name', 'usces' ) . ' : ' . sprintf( _x( '%s', 'honorific', 'usces' ), $name ) . "\r\n";
		$message .= __( 'e-mail adress', 'usces' ) . ' : ' . $member['mailaddress1'] . "\r\n";
		$message .= '--------------------------------' . "\r\n\r\n";
		$message .= __( 'If you have not requested this email, sorry to trouble you, but please contact us.', 'usces' ) . "\r\n\r\n";
		$message  = apply_filters( 'usces_filter_send_update_settlement_mail_message', $message, $member );
		$message  = apply_filters( 'usces_filter_send_update_settlement_mail_message_head', $mail_header, $member ) . $message . apply_filters( 'usces_filter_send_update_settlement_mail_message_foot', $mail_footer, $member ) . "\r\n";
		$message  = sprintf( __( 'Dear %s', 'usces' ), $name ) . "\r\n\r\n" . $message;

		$send_para = array(
			'to_name'      => sprintf( _x( '%s', 'honorific', 'usces' ), $name ),
			'to_address'   => $member['mailaddress1'],
			'from_name'    => get_option( 'blogname' ),
			'from_address' => $usces->options['sender_mail'],
			'reply_name'   => get_option( 'blogname' ),
			'reply_to'     => usces_get_first_order_mail(),
			'return_path'  => $usces->options['sender_mail'],
			'subject'      => $subject,
			'message'      => do_shortcode( $message ),
		);
		usces_send_mail( $send_para );

		$admin_message  = $mail_header;
		$admin_message .= '--------------------------------' . "\r\n";
		$admin_message .= __( 'Member ID', 'usces' ) . ' : ' . $member['ID'] . "\r\n";
		$admin_message .= __( 'Name', 'usces' ) . ' : ' . sprintf( _x( '%s', 'honorific', 'usces' ), $name ) . "\r\n";
		$admin_message .= __( 'e-mail adress', 'usces' ) . ' : ' . $member['mailaddress1'] . "\r\n";
		$admin_message .= '--------------------------------' . "\r\n\r\n";
		if ( usces_have_member_continue_order( $member['ID'] ) ) {
			$admin_message .= $this->message_continue_order( $member['ID'] );
		}
		if ( usces_have_member_regular_order( $member['ID'] ) ) {
			$admin_message .= $this->message_regular_order( $member['ID'] );
		}
		$admin_message .=
			"\r\n----------------------------------------------------\r\n" .
			'REMOTE_ADDR : ' . $_SERVER['REMOTE_ADDR'] .
			"\r\n----------------------------------------------------\r\n";

		$admin_para = array(
			'to_name'      => apply_filters( 'usces_filter_bccmail_to_admin_name', 'Shop Admin' ),
			'to_address'   => $usces->options['order_mail'],
			'from_name'    => apply_filters( 'usces_filter_bccmail_from_admin_name', 'Welcart Auto BCC' ),
			'from_address' => $usces->options['sender_mail'],
			'reply_name'   => get_option( 'blogname' ),
			'reply_to'     => usces_get_first_order_mail(),
			'return_path'  => $usces->options['sender_mail'],
			'subject'      => $subject . '( ' . sprintf( _x( '%s', 'honorific', 'usces' ), $name ) . ' )',
			'message'      => do_shortcode( $admin_message ),
		);
		usces_send_mail( $admin_para );
	}

	/**
	 * 契約中の自動継続課金情報
	 *
	 * @param  int $member_id Member ID.
	 */
	public function message_continue_order( $member_id ) {
		global $usces, $wpdb;
		$message = '';

		$continuation_table = $wpdb->prefix . 'usces_continuation';
		$query              = $wpdb->prepare( "SELECT * FROM {$continuation_table} WHERE `con_member_id` = %d AND `con_acting` = %s AND `con_status` = 'continuation'", $member_id, 'acting_sbps_card' );
		$continue_order     = $wpdb->get_results( $query, ARRAY_A );
		if ( 0 < count( $continue_order ) ) {
			$message .= '--------------------------------' . "\r\n";
			$message .= __( 'Auto-continuation charging Information under Contract with a credit card', 'usces' ) . "\r\n";
			foreach ( $continue_order as $continue_data ) {
				$con_id       = $continue_data['con_id'];
				$con_order_id = $continue_data['con_order_id'];
				$message     .= __( 'Order number', 'usces' ) . ' : ' . $con_order_id;
				$latest_log   = $this->get_acting_latest_log( $con_order_id, 0, 'ALL' );
				if ( ! empty( $latest_log ) ) {
					$next_charging = ( empty( $continue_data['con_next_contracting'] ) ) ? dlseller_next_charging( $con_order_id ) : $continue_data['con_next_contracting'];
					$message      .= ' ( ' . __( 'Next Withdrawal Date', 'dlseller' ) . ' : ' . wp_date( __( 'Y/m/d' ), strtotime( $next_charging ) );
					if ( 0 < (int) $continue_data['con_interval'] ) {
						$next_contracting = ( empty( $continue_data['con_next_contracting'] ) ) ? dlseller_next_contracting( $con_order_id ) : $continue_data['con_next_contracting'];
						$message         .= ', ' . __( 'Renewal Date', 'dlseller' ) . ' : ' . wp_date( __( 'Y/m/d' ), strtotime( $next_contracting ) );
					}
					$message .= ' )';
					if ( 'NG' === $latest_log['result'] ) {
						$message .= ' ' . __( 'Condition', 'dlseller' ) . ' : ' . __( 'Settlement error', 'usces' );
						if ( ! empty( $latest_log['tracking_id'] ) ) {
							$message .= ' ( ' . __( 'Transaction ID', 'usces' ) . ' : ' . $latest_log['tracking_id'] . ' )';
						}
					}
				}
				$message .= "\r\n";
			}
			$message .= '--------------------------------' . "\r\n\r\n";
		}
		return $message;
	}

	/**
	 * 契約中の定期購入情報
	 *
	 * @param  int $member_id Member ID.
	 */
	public function message_regular_order( $member_id ) {
		global $usces, $wpdb;
		$message = '';

		$regular_table_name        = $wpdb->prefix . 'usces_regular';
		$regular_detail_table_name = $wpdb->prefix . 'usces_regular_detail';
		$order_table_name          = $wpdb->prefix . 'usces_order';
		$order_meta_table_name     = $wpdb->prefix . 'usces_order_meta';

		$query         = $wpdb->prepare( "SELECT r.reg_id, r.reg_payment_name, d.regdet_schedule_date FROM {$regular_detail_table_name} AS `d` RIGHT JOIN {$regular_table_name} AS `r` ON  r.reg_id = d.reg_id WHERE r.reg_mem_id = %d AND d.regdet_condition = 'continuation' GROUP BY r.reg_id", $member_id );
		$regular_order = $wpdb->get_results( $query, ARRAY_A );
		if ( 0 < count( $regular_order ) ) {
			foreach ( $regular_order as $regular_data ) {
				$payment = $usces->getPayments( $regular_data['reg_payment_name'] );
				if ( 'acting_sbps_card' != $payment['settlement'] ) {
					continue;
				}
				$reg_id             = $regular_data['reg_id'];
				$message           .= __( 'Regular ID', 'autodelivery' ) . ' : ' . $reg_id;
				$query              = $wpdb->prepare(
					"SELECT o.ID AS `order_id`, meta.meta_value AS `deco_id`, DATE_FORMAT( order_date, %s ) AS `date` 
						FROM {$order_table_name} AS `o` 
						LEFT JOIN {$order_meta_table_name} AS `meta` ON o.ID = meta.order_id AND meta.meta_key = 'dec_order_id' 
						LEFT JOIN {$regular_table_name} ON o.ID = reg_order_id 
						WHERE reg_id = %d 
					UNION ALL 
					SELECT o1.ID AS `order_id`, meta1.meta_value AS `deco_id`, DATE_FORMAT( order_date, %s ) AS `date` 
						FROM {$order_table_name} AS `o1` 
						LEFT JOIN {$order_meta_table_name} AS `meta1` ON o1.ID = meta1.order_id AND meta1.meta_key = 'dec_order_id' 
						LEFT JOIN {$order_meta_table_name} AS `meta2` ON o1.ID = meta2.order_id AND meta2.meta_key = 'regular_id' 
						WHERE meta2.meta_value = %d 
					ORDER BY order_id DESC, date DESC",
					'%Y-%m-%d', $reg_id, '%Y-%m-%d', $reg_id
				);
				$regular_order_data = $wpdb->get_results( $query, ARRAY_A );
				if ( 0 < count( $regular_order_data ) ) {
					$reg_order_id = $regular_order_data[0]['order_id'];
					$latest_log   = $this->get_acting_latest_log( $reg_order_id, 0, 'ALL' );
					if ( ! empty( $latest_log ) && 'OK' === $latest_log['result'] ) {
						if ( $this->isdate( $regular_data['regdet_schedule_date'] ) ) {
							$message .= ' ( ' . __( 'Scheduled order date', 'autodelivery' ) . ' : ' . wp_date( __( 'Y/m/d' ), strtotime( $regular_data['regdet_schedule_date'] ) ) . ' )';
						}
					} else {
						$message    .= ' ' . __( 'Condition', 'autodelivery' ) . ' : ' . __( 'Settlement error', 'usces' );
						$tracking_id = $usces->get_order_meta_value( 'res_tracking_id', $reg_order_id );
						if ( $tracking_id ) {
							$message .= ' ( ' . __( 'Transaction ID', 'usces' ) . ' : ' . $tracking_id . ' )';
						}
					}
					$message .= "\r\n";
				}
			}
			if ( '' != $message ) {
				$message = '--------------------------------' . "\r\n"
					. __( 'Subscription Information under Contract with a credit card', 'usces' ) . "\r\n"
					. $message
					. '--------------------------------' . "\r\n\r\n";
			}
		}
		return $message;
	}

	/**
	 * 日付チェック
	 *
	 * @param  object $date DateTime.
	 * @return boolean
	 */
	private function isdate( $date ) {
		if ( empty( $date ) ) {
			return false;
		}
		try {
			new DateTime( $date );
			list( $year, $month, $day ) = explode( '-', $date );
			$res                        = checkdate( (int) $month, (int) $day, (int) $year );
			return $res;
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * 利用可能な支払方法（継続課金・定期購入）
	 * dlseller_filter_the_payment_method_restriction
	 * wcad_filter_the_payment_method_restriction
	 *
	 * @param  array  $payments_restriction Payment method.
	 * @param  string $value Input value.
	 * @return array
	 */
	public function payment_method_restriction( $payments_restriction, $value ) {
		if ( ( usces_have_regular_order() || usces_have_continue_charge() ) && usces_is_login() ) {
			$sbps_card = false;
			foreach ( (array) $payments_restriction as $key => $payment ) {
				if ( 'acting_sbps_card' === $payment['settlement'] ) {
					$sbps_card = true;
				}
			}
			if ( ! $sbps_card ) {
				$payments               = usces_get_system_option( 'usces_payment_method', 'settlement' );
				$payments_restriction[] = $payments['acting_sbps_card'];
			}
			$sort = array();
			foreach ( (array) $payments_restriction as $key => $payment ) {
				$sort[ $key ] = $payment['sort'];
			}
			array_multisort( $sort, SORT_ASC, $payments_restriction );
		}
		return $payments_restriction;
	}

	/**
	 * 利用可能な支払方法
	 * usces_filter_the_continue_payment_method
	 *
	 * @param  array $payment_method Payment method.
	 * @return array
	 */
	public function continuation_payment_method( $payment_method ) {
		if ( ! array_key_exists( 'acting_sbps_card', $payment_method ) ) {
			$payment_method[] = 'acting_sbps_card';
		}
		return $payment_method;
	}

	/**
	 * 「初回引落し日」
	 * dlseller_filter_first_charging
	 *
	 * @param  object $time Datetime.
	 * @param  int    $post_id Post ID.
	 * @param  array  $usces_item Item data.
	 * @param  int    $order_id Order number.
	 * @param  array  $continue_data Continuation data.
	 * @return object
	 */
	public function first_charging_date( $time, $post_id, $usces_item, $order_id, $continue_data ) {
		if ( 99 === (int) $usces_item['item_chargingday'] ) {
			if ( empty( $order_id ) ) {
				$today                      = wp_date( 'Y-m-d' );
				list( $year, $month, $day ) = explode( '-', $today );
				$time                       = mktime( 0, 0, 0, (int) $month, (int) $day, (int) $year );
			}
		}
		return $time;
	}

	/**
	 * 継続課金会員リスト「契約」
	 * dlseller_filter_continue_member_list_continue_status
	 *
	 * @param  string $status Continuation status.
	 * @param  int    $member_id Member ID.
	 * @param  int    $order_id Order number.
	 * @param  array  $meta_data Continuation data.
	 * @return string
	 */
	public function continue_member_list_continue_status( $status, $member_id, $order_id, $meta_data ) {
		return $status;
	}

	/**
	 * 継続課金会員リスト「状態」
	 * dlseller_filter_continue_member_list_condition
	 *
	 * @param  string $condition Continuation condition.
	 * @param  int    $member_id Member ID.
	 * @param  int    $order_id Order number.
	 * @param  array  $continue_data Continuation data.
	 * @return string
	 */
	public function continue_member_list_condition( $condition, $member_id, $order_id, $continue_data ) {
		global $usces;

		if ( isset( $continue_data['acting'] ) && 'acting_sbps_card' === $continue_data['acting'] ) {
			$url       = admin_url( 'admin.php?page=usces_continue&continue_action=settlement_sbps_card&member_id=' . esc_attr( $member_id ) . '&order_id=' . esc_attr( $order_id ) );
			$condition = '<a href="' . $url . '">' . __( 'Detail', 'usces' ) . '</a>';
			if ( 'continuation' === $continue_data['status'] ) {
				$latest_log = $this->get_acting_latest_log( $order_id, 0, 'ALL' );
				if ( 'NG' === $latest_log['result'] ) {
					$condition .= '<div class="acting-status sbps-error">' . __( 'Settlement error', 'usces' ) . '</div>';
				}
			}
		}
		return $condition;
	}

	/**
	 * 継続課金会員決済状況ページ表示
	 * dlseller_action_continue_member_list_page
	 *
	 * @param string $continue_action Continuation action.
	 */
	public function continue_member_list_page( $continue_action ) {
		if ( 'settlement_sbps_card' == $continue_action ) {
			$member_id = ( isset( $_GET['member_id'] ) ) ? wp_unslash( $_GET['member_id'] ) : '';
			$order_id  = ( isset( $_GET['order_id'] ) ) ? wp_unslash( $_GET['order_id'] ) : '';
			if ( ! empty( $member_id ) && ! empty( $order_id ) ) {
				$this->continue_member_settlement_info_page( $member_id, $order_id );
				exit();
			}
		}
	}

	/**
	 * 継続課金会員決済状況ページ
	 *
	 * @param  int $member_id Member ID.
	 * @param  int $order_id Order number.
	 */
	public function continue_member_settlement_info_page( $member_id, $order_id ) {
		global $usces;

		$order_data = $usces->get_order_data( $order_id, 'direct' );
		if ( ! $order_data ) {
			return;
		}

		$payment = $usces->getPayments( $order_data['order_payment_name'] );
		if ( 'acting_sbps_card' !== $payment['settlement'] ) {
			return;
		}

		$continue_data = $this->get_continuation_data( $member_id, $order_id );
		$con_id        = $continue_data['con_id'];
		$curent_url    = $_SERVER['REQUEST_URI'];
		$navibutton    = '<a href="' . esc_url( $_SERVER['HTTP_REFERER'] ) . '" class="back-list"><span class="dashicons dashicons-list-view"></span>' . __( 'Back to the continue members list', 'dlseller' ) . '</a>';

		$member_info = $usces->get_member_info( $member_id );
		$name        = usces_localized_name( $member_info['mem_name1'], $member_info['mem_name2'], 'return' );

		$contracted_date = ( empty( $continue_data['contractedday'] ) ) ? dlseller_next_contracting( $order_id ) : $continue_data['contractedday'];
		if ( ! empty( $contracted_date ) ) {
			list( $contracted_year, $contracted_month, $contracted_day ) = explode( '-', $contracted_date );
		} else {
			$contracted_year  = 0;
			$contracted_month = 0;
			$contracted_day   = 0;
		}
		$charged_date = ( empty( $continue_data['chargedday'] ) ) ? dlseller_next_charging( $order_id ) : $continue_data['chargedday'];
		if ( ! empty( $charged_date ) ) {
			list( $charged_year, $charged_month, $charged_day ) = explode( '-', $charged_date );
		} else {
			$charged_year  = 0;
			$charged_month = 0;
			$charged_day   = 0;
		}
		$year = substr( wp_date( 'Y' ), 0, 4 );

		$log_data = $this->get_acting_log( $order_id, 0, 'ALL' );
		$num      = ( $log_data ) ? count( $log_data ) : 1;
		?>
<div class="wrap">
<div class="usces_admin">
<h1>Welcart Management <?php esc_html_e( 'Continuation charging member information', 'dlseller' ); ?></h1>
<p class="version_info">Version <?php echo esc_html( WCEX_DLSELLER_VERSION ); ?></p>
		<?php usces_admin_action_status(); ?>
<div class="edit_pagenav"><?php wel_esc_script_e( $navibutton ); ?></div>
<div id="datatable">
<div id="tablesearch" class="usces_tablesearch">
<div id="searchBox" style="display:block">
	<table class="search_table">
	<tr>
		<td class="label"><?php esc_html_e( 'Continuation charging information', 'dlseller' ); ?></td>
		<td>
			<table class="order_info">
			<tr>
				<th><?php esc_html_e( 'Member ID', 'dlseller' ); ?></th>
				<td><?php echo esc_html( $member_id ); ?></td>
				<th><?php esc_html_e( 'Contractor name', 'dlseller' ); ?></th>
				<td><?php echo esc_html( $name ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Order ID', 'dlseller' ); ?></th>
				<td><?php echo esc_html( $order_id ); ?></td>
				<th><?php esc_html_e( 'Application Date', 'dlseller' ); ?></th>
				<td><?php echo esc_html( $order_data['order_date'] ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Renewal Date', 'dlseller' ); ?></th>
				<td>
					<select id="contracted-year">
						<option value="0"<?php selected( (int) $contracted_year, 0 ); ?>></option>
		<?php for ( $i = 0; $i <= 10; $i++ ) : ?>
						<option value="<?php echo esc_attr( $year + $i ); ?>"<?php selected( (int) $contracted_year, ( (int) $year + $i ) ); ?>><?php echo esc_html( (int) $year + $i ); ?></option>
		<?php endfor; ?>
					</select>-<select id="contracted-month">
						<option value="0"<?php selected( (int) $contracted_month, 0 ); ?>></option>
		<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
						<option value="<?php printf( '%02d', $i ); ?>"<?php selected( (int) $contracted_month, $i ); ?>><?php printf( '%2d', $i ); ?></option>
		<?php endfor; ?>
					</select>-<select id="contracted-day">
						<option value="0"<?php selected( $contracted_day, 0 ); ?>></option>
		<?php for ( $i = 1; $i <= 31; $i++ ) : ?>
						<option value="<?php printf( '%02d', $i ); ?>"<?php selected( (int) $contracted_day, $i ); ?>><?php printf( '%2d', $i ); ?></option>
		<?php endfor; ?>
					</select>
				</td>
				<th><?php esc_html_e( 'Next Withdrawal Date', 'dlseller' ); ?></th>
				<td>
					<select id="charged-year">
						<option value="0"<?php selected( (int) $charged_year, 0 ); ?>></option>
						<option value="<?php echo esc_attr( $year ); ?>"<?php selected( (int) $charged_year, (int) $year ); ?>><?php echo esc_html( $year ); ?></option>
						<option value="<?php echo esc_attr( $year + 1 ); ?>"<?php selected( (int) $charged_year, ( (int) $year + 1 ) ); ?>><?php echo esc_html( (int) $year + 1 ); ?></option>
					</select>-<select id="charged-month">
						<option value="0"<?php selected( (int) $charged_month, 0 ); ?>></option>
		<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
						<option value="<?php printf( '%02d', $i ); ?>"<?php selected( (int) $charged_month, $i ); ?>><?php printf( '%2d', $i ); ?></option>
		<?php endfor; ?>
					</select>-<select id="charged-day">
						<option value="0"<?php selected( $charged_day, 0 ); ?>></option>
		<?php for ( $i = 1; $i <= 31; $i++ ) : ?>
						<option value="<?php printf( '%02d', $i ); ?>"<?php selected( (int) $charged_day, $i ); ?>><?php printf( '%2d', $i ); ?></option>
		<?php endfor; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Amount on order', 'usces' ); ?></th>
				<td><?php usces_crform( $continue_data['order_price'], false ); ?></td>
				<th><?php esc_html_e( 'Transaction amount', 'usces' ); ?></th>
				<td><input type="text" class="amount" id="price" style="text-align: right;" value="<?php usces_crform( $continue_data['price'], false, false, '', false ); ?>"><?php usces_crcode(); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Status', 'dlseller' ); ?></th>
				<td><select id="dlseller-status">
				<?php ob_start(); ?>
				<?php if ( 'continuation' === $continue_data['status'] ) : ?>
					<option value="continuation" selected="selected"><?php esc_html_e( 'Continuation', 'dlseller' ); ?></option>
					<option value="cancellation"><?php esc_html_e( 'Stop', 'dlseller' ); ?></option>
				<?php else : ?>
					<option value="cancellation" selected="selected"><?php esc_html_e( 'Cancellation', 'dlseller' ); ?></option>
					<option value="continuation"><?php esc_html_e( 'Resumption', 'dlseller' ); ?></option>
				<?php endif; ?>
				<?php
					$dlseller_status_options = ob_get_contents();
					ob_end_clean();
					$dlseller_status_options = apply_filters( 'usces_filter_continuation_charging_status_options', $dlseller_status_options, $continue_data );
					wel_esc_script_e( $dlseller_status_options );
				?>
				</select></td>
				<td colspan="2"><input id="continuation-update" type="button" class="button button-primary" value="<?php esc_attr_e( 'Update' ); ?>" /></td>
			</tr>
			</table>
			<?php do_action( 'usces_action_continuation_charging_information', $continue_data, $member_id, $order_id ); ?>
		</td>
	</tr>
	</table>
</div><!-- searchBox -->
</div><!-- tablesearch -->
<table id="mainDataTable" class="new-table order-new-table">
	<thead>
	<tr>
		<th scope="col">&nbsp;</th>
		<th scope="col"><?php esc_html_e( 'Processing date', 'usces' ); ?></th>
		<th scope="col"><?php esc_html_e( 'Transaction ID', 'usces' ); ?></th>
		<th scope="col"><?php esc_html_e( 'Settlement amount', 'usces' ); ?></th>
		<th scope="col"><?php esc_html_e( 'Processing classification', 'usces' ); ?></th>
		<th scope="col">&nbsp;</th>
	</tr>
	</thead>
		<?php
		foreach ( (array) $log_data as $data ) :
			$tracking_id = ( isset( $data['tracking_id'] ) ) ? $data['tracking_id'] : '';
			$latest_log  = $this->get_acting_latest_log( $order_id, $tracking_id, 'ALL' );
			if ( $latest_log ) :
				if ( 1 < $num ) {
					if ( isset( $latest_log['result'] ) && 'OK' !== $latest_log['result'] ) {
						$cust_ref = $this->api_customer_reference( $order_data['mem_id'] );
						if ( isset( $cust_ref['result'] ) && 'OK' === $cust_ref['result'] ) {
							$status      = '';
							$class       = ' card-' . $status;
							$status_name = '';
						} else {
							$status      = 'error';
							$class       = ' card-' . $status;
							$status_name = $this->get_status_name( $status );
						}
					} else {
						$status      = $latest_log['status'];
						$class       = ' card-' . $status;
						$status_name = $this->get_status_name( $status );
					}
				} else {
					$status      = $latest_log['status'];
					$class       = ' card-' . $status;
					$status_name = $this->get_status_name( $status );
				}
				$amount      = usces_crform( $latest_log['amount'], false, false, 'return', false );
				?>
	<tbody>
	<tr>
		<td><?php echo esc_html( $num ); ?></td>
		<td><?php echo esc_html( $data['datetime'] ); ?></td>
		<td><span id="settlement-tracking_id-<?php echo esc_attr( $num ); ?>"><?php echo esc_html( $tracking_id ); ?></span></td>
		<td class="amount"><span id="settlement-amount-<?php echo esc_attr( $num ); ?>"><?php echo esc_html( $amount ); ?></span></td>
		<td><span id="settlement-status-<?php echo esc_attr( $num ); ?>">
				<?php if ( $status_name ) : ?>
			<span class="acting-status<?php echo esc_attr( $class ); ?>"><?php esc_html_e( $status_name, 'usces' ); ?></span>
				<?php endif; ?>
			</span></td>
		<td>
			<input type="button" class="button settlement-information" id="settlement-information-<?php echo esc_attr( $tracking_id ); ?>" data-tracking_id="<?php echo esc_attr( $tracking_id ); ?>" data-num="<?php echo esc_attr( $num ); ?>" value="<?php esc_attr_e( 'Settlement info', 'usces' ); ?>">
		</td>
	</tr>
	</tbody>
				<?php
				$num--;
			endif;
		endforeach;
		?>
</table>
</div><!--datatable-->
<input name="member_id" type="hidden" id="member_id" value="<?php echo esc_attr( $member_id ); ?>" />
<input name="order_id" type="hidden" id="order_id" value="<?php echo esc_attr( $order_id ); ?>" />
<input name="con_id" type="hidden" id="con_id" value="<?php echo esc_attr( $con_id ); ?>" />
<input name="usces_referer" type="hidden" id="usces_referer" value="<?php echo esc_url( $curent_url ); ?>" />
		<?php wp_nonce_field( 'order_edit', 'wc_nonce' ); ?>
</div><!--usces_admin-->
</div><!--wrap-->
		<?php
		$order_action = 'edit';
		$cart         = array();
		$action_args  = compact( 'order_action', 'order_id', 'cart' );
		$this->settlement_dialog( $order_data, $action_args );
		include ABSPATH . 'wp-admin/admin-footer.php';
	}

	/**
	 * 自動継続課金処理
	 * dlseller_action_do_continuation_charging
	 *
	 * @param  string $today Today.
	 * @param  int    $member_id Member ID.
	 * @param  int    $order_id Order number.
	 * @param  array  $continue_data Continuation data.
	 */
	public function auto_continuation_charging( $today, $member_id, $order_id, $continue_data ) {
		global $usces;

		if ( ! usces_is_membersystem_state() ) {
			return;
		}

		if ( 0 >= $continue_data['price'] ) {
			return;
		}

		$order_data = $usces->get_order_data( $order_id, 'direct' );
		if ( ! $order_data || $usces->is_status( 'cancel', $order_data['order_status'] ) ) {
			return;
		}

		if ( 'acting_sbps_card' !== $continue_data['acting'] ) {
			return;
		}

		$acting_opts = $this->get_acting_settings();
		$rand        = usces_acting_key();
		$cust_code   = $member_id;
		$cust_ref    = $this->api_customer_reference( $cust_code );
		if ( isset( $cust_ref['result'] ) && 'OK' === $cust_ref['result'] ) {
			$cart      = usces_get_ordercartdata( $order_id );
			$cart_row  = current( $cart );
			$item_id   = mb_convert_kana( $usces->getItemCode( $cart_row['post_id'] ), 'a', 'UTF-8' );
			$item_name = $usces->getCartItemName_byOrder( $cart_row );
			if ( 36 < mb_strlen( $item_name, 'UTF-8' ) ) {
				$item_name = mb_substr( $item_name, 0, 30, 'UTF-8' ) . '...';
			}
			$item_name     = trim( mb_convert_encoding( $item_name, 'SJIS', 'UTF-8' ) );
			$amount        = usces_crform( $continue_data['price'], false, false, 'return', false );
			$free1         = 'acting_sbps_card';
			$order_rowno   = '1';
			$encrypted_flg = '1';
			$request_date  = wp_date( 'YmdHis' );
			$sps_hashcode  = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $cust_code . $rand . $item_id . $item_name . $amount . $free1 . $order_rowno . $encrypted_flg . $request_date . $acting_opts['hash_key'];
			$sps_hashcode  = sha1( $sps_hashcode );
			$connection    = $this->get_connection();

			/* 決済要求 */
			$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST01-00131-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<cust_code>' . $cust_code . '</cust_code>
	<order_id>' . $rand . '</order_id>
	<item_id>' . $item_id . '</item_id>
	<item_name>' . base64_encode( $item_name ) . '</item_name>
	<amount>' . $amount . '</amount>
	<free1>' . base64_encode( $free1 ) . '</free1>
	<order_rowno>' . $order_rowno . '</order_rowno>
	<encrypted_flg>' . $encrypted_flg . '</encrypted_flg>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
			$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
			$response_settlement = $this->xml2assoc( $xml_settlement, $this->acting_card, $encrypted_flg );
			if ( isset( $response_settlement['res_result'] ) && 'OK' === $response_settlement['res_result'] ) {
				$sps_transaction_id = $response_settlement['res_sps_transaction_id'];
				$tracking_id        = $response_settlement['res_tracking_id'];
				$request_date       = wp_date( 'YmdHis' );
				$sps_hashcode       = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $sps_transaction_id . $tracking_id . $request_date . $acting_opts['hash_key'];
				$sps_hashcode       = sha1( $sps_hashcode );

				/* 確定要求 */
				$request_credit  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00101-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<sps_transaction_id>' . $sps_transaction_id . '</sps_transaction_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<processing_datetime></processing_datetime>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
				$xml_credit      = $this->get_xml_response( $connection['api_url'], $request_credit );
				$response_credit = $this->xml2assoc( $xml_credit, $this->acting_card );
				if ( isset( $response_credit['res_result'] ) && 'OK' === $response_credit['res_result'] ) {
					if ( isset( $acting_opts['sales_dlseller'] ) && 'auto' === $acting_opts['sales_dlseller'] ) {
						$process_date = $response_credit['res_process_date'];
						$request_date = wp_date( 'YmdHis' );
						$sps_hashcode = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $sps_transaction_id . $tracking_id . $process_date . $amount . $request_date . $acting_opts['hash_key'];
						$sps_hashcode = sha1( $sps_hashcode );

						/* 売上要求（自動売上）*/
						$request_sales  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00201-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<sps_transaction_id>' . $sps_transaction_id . '</sps_transaction_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<processing_datetime>' . $process_date . '</processing_datetime>
	<pay_option_manage>
		<amount>' . $amount . '</amount>
	</pay_option_manage>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
						$xml_sales      = $this->get_xml_response( $connection['api_url'], $request_sales );
						$response_sales = $this->xml2assoc( $xml_sales, $this->acting_card );
						$response_sales = apply_filters( 'usces_filter_sbps_card_auto_continuation_charging_log', $response_sales, $member_id, $order_id, $continue_data );
						if ( isset( $response_sales['res_result'] ) && 'OK' === $response_sales['res_result'] ) {
							if ( ! isset( $response_sales['amount'] ) ) {
								$response_sales['amount'] = $amount;
							}
							$this->save_acting_log( $response_sales, $this->acting_card, $acting_opts['sales_dlseller'], $response_sales['res_result'], $order_id, $tracking_id );
							$this->auto_settlement_mail( $member_id, $order_id, $response_sales, $continue_data );
							do_action( 'usces_action_auto_continuation_charging', $member_id, $order_id, $continue_data, $response_sales );
						} else {
							$res_err_code = ( isset( $response_sales['res_err_code'] ) ) ? $response_sales['res_err_code'] : 'ST02-00201-101 Error';
							$data         = ( ! empty( $response_sales ) ) ? $response_sales : $xml_sales;
							$log          = array(
								'acting' => $this->acting_card,
								'key'    => $rand,
								'result' => $res_err_code,
								'data'   => $data,
							);
							usces_save_order_acting_error( $log );
							$this->save_acting_log( $response_sales, $this->acting_card, $acting_opts['sales_dlseller'], 'NG', $order_id, $tracking_id );
							$this->auto_settlement_error_mail( $member_id, $order_id, $response_sales, $continue_data );
							do_action( 'usces_action_auto_continuation_charging', $member_id, $order_id, $continue_data, $response_sales );
						}
					} else {
						/* 指定売上 */
						if ( ! isset( $response_credit['amount'] ) ) {
							$response_credit['amount'] = $amount;
						}
						$response_credit = apply_filters( 'usces_filter_sbps_card_auto_continuation_charging_log', $response_credit, $member_id, $order_id, $continue_data );
						$this->save_acting_log( $response_credit, $this->acting_card, $acting_opts['sales_dlseller'], $response_credit['res_result'], $order_id, $tracking_id );
						$this->auto_settlement_mail( $member_id, $order_id, $response_credit, $continue_data );
						do_action( 'usces_action_auto_continuation_charging', $member_id, $order_id, $continue_data, $response_credit );
					}
				} else {
					$res_err_code = ( isset( $response_credit['res_err_code'] ) ) ? $response_credit['res_err_code'] : 'ST02-00201-101 Error';
					$data         = ( ! empty( $response_credit ) ) ? $response_credit : $xml_credit;
					$log          = array(
						'acting' => $this->acting_card,
						'key'    => $rand,
						'result' => $res_err_code,
						'data'   => $data,
					);
					usces_save_order_acting_error( $log );
					$this->save_acting_log( $response_credit, $this->acting_card, $acting_opts['sales_dlseller'], 'NG', $order_id, $tracking_id );
					$this->auto_settlement_error_mail( $member_id, $order_id, $response_credit, $continue_data );
					do_action( 'usces_action_auto_continuation_charging', $member_id, $order_id, $continue_data, $response_credit );
				}
			} else {
				$res_err_code = ( isset( $response_settlement['res_err_code'] ) ) ? $response_settlement['res_err_code'] : 'ST01-00131-101 Error';
				$data         = ( ! empty( $response_settlement ) ) ? $response_settlement : $xml_settlement;
				$log          = array(
					'acting' => $this->acting_card,
					'key'    => $rand,
					'result' => $res_err_code,
					'data'   => $data,
				);
				usces_save_order_acting_error( $log );
				$this->save_acting_log( $response_settlement, $this->acting_card, $acting_opts['sales_dlseller'], 'NG', $order_id, $rand );
				$this->auto_settlement_error_mail( $member_id, $order_id, $response_settlement, $continue_data );
				do_action( 'usces_action_auto_continuation_charging', $member_id, $order_id, $continue_data, $response_settlement );
			}
		} else {
			$result = ( isset( $cust_ref['result'] ) ) ? $cust_ref['result'] : 'NG';
			$log    = array(
				'acting' => $this->acting_card . '(member_process)',
				'key'    => $member_id,
				'result' => $result,
				'data'   => $cust_ref,
			);
			usces_save_order_acting_error( $log );
			$this->save_acting_log( $cust_ref, $this->acting_card, 'error', $result, $order_id, $rand );
			$this->auto_settlement_error_mail( $member_id, $order_id, $cust_ref, $continue_data );
			do_action( 'usces_action_auto_continuation_charging', $member_id, $order_id, $continue_data, $cust_ref );
		}
	}

	/**
	 * 自動継続課金処理メール（正常）
	 *
	 * @param  int   $member_id Member ID.
	 * @param  int   $order_id Order number.
	 * @param  array $response_data Response data.
	 * @param  array $continue_data Continuation data.
	 */
	public function auto_settlement_mail( $member_id, $order_id, $response_data, $continue_data ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$order_data  = $usces->get_order_data( $order_id, 'direct' );
		$mail_body   = $this->auto_settlement_message( $member_id, $order_id, $order_data, $response_data, $continue_data, false );

		if ( 'on' === $acting_opts['auto_settlement_mail'] ) {
			$subject     = apply_filters( 'usces_filter_sbps_auto_settlement_mail_subject', __( 'Announcement of automatic continuing charging process', 'usces' ), $member_id, $order_id, $order_data, $response_data, $continue_data );
			$member_info = $usces->get_member_info( $member_id );
			$name        = usces_localized_name( $member_info['mem_name1'], $member_info['mem_name2'], 'return' );
			$mail_data   = usces_mail_data();
			$mail_header = '';
			if ( isset( $usces->options['put_customer_name'] ) && 1 === (int) $usces->options['put_customer_name'] ) {
				$mail_header .= sprintf( __( 'Dear %s', 'usces' ), $name ) . "\r\n\r\n";
			}
			$mail_header .= __( 'We will report automated accounting process was carried out as follows.', 'usces' ) . "\r\n\r\n";
			$mail_footer  = __( 'If you have any questions, please contact us.', 'usces' ) . "\r\n\r\n" . $mail_data['footer']['thankyou'];
			$message      = apply_filters( 'usces_filter_sbps_auto_settlement_mail_header', $mail_header, $member_id, $order_id, $order_data, $response_data, $continue_data ) .
				apply_filters( 'usces_filter_sbps_auto_settlement_mail_body', $mail_body, $member_id, $order_id, $order_data, $response_data, $continue_data ) .
				apply_filters( 'usces_filter_sbps_auto_settlement_mail_footer', $mail_footer, $member_id, $order_id, $order_data, $response_data, $continue_data );
			$headers      = apply_filters( 'usces_filter_sbps_auto_settlement_mail_headers', '' );
			$to_customer  = array(
				'to_name'      => sprintf( _x( '%s', 'honorific', 'usces' ), $name ),
				'to_address'   => $member_info['mem_email'],
				'from_name'    => get_option( 'blogname' ),
				'from_address' => $usces->options['sender_mail'],
				'reply_name'   => get_option( 'blogname' ),
				'reply_to'     => usces_get_first_order_mail(),
				'return_path'  => $usces->options['sender_mail'],
				'subject'      => $subject,
				'message'      => do_shortcode( $message ),
				'headers'      => $headers,
			);
			usces_send_mail( $to_customer );
		}

		$ok                                     = ( empty( $this->continuation_charging_mail['OK'] ) ) ? 0 : $this->continuation_charging_mail['OK'];
		$this->continuation_charging_mail['OK'] = $ok + 1;
		$this->continuation_charging_mail['mail'][] = $mail_body;
	}

	/**
	 * 自動継続課金処理メール（エラー）
	 *
	 * @param  int   $member_id Member ID.
	 * @param  int   $order_id Order number.
	 * @param  array $response_data Response data.
	 * @param  array $continue_data Continuation data.
	 */
	public function auto_settlement_error_mail( $member_id, $order_id, $response_data, $continue_data ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		$order_data  = $usces->get_order_data( $order_id, 'direct' );
		$mail_body   = $this->auto_settlement_message( $member_id, $order_id, $order_data, $response_data, $continue_data, false );

		if ( 'on' === $acting_opts['auto_settlement_mail'] ) {
			$subject     = apply_filters( 'usces_filter_sbps_auto_settlement_error_mail_subject', __( 'Announcement of automatic continuing charging process', 'usces' ), $member_id, $order_id, $order_data, $response_data, $continue_data );
			$member_info = $usces->get_member_info( $member_id );
			$name        = usces_localized_name( $member_info['mem_name1'], $member_info['mem_name2'], 'return' );
			$mail_data   = usces_mail_data();
			$mail_header = '';
			if ( isset( $usces->options['put_customer_name'] ) && 1 === (int) $usces->options['put_customer_name'] ) {
				$mail_header .= sprintf( __( 'Dear %s', 'usces' ), $name ) . "\r\n\r\n";
			}
			$mail_header .= __( 'We will reported that an error occurred in automated accounting process.', 'usces' ) . "\r\n\r\n";
			$mail_footer  = __( 'If you have any questions, please contact us.', 'usces' ) . "\r\n\r\n" . $mail_data['footer']['thankyou'];
			$message      = apply_filters( 'usces_filter_sbps_auto_settlement_error_mail_header', $mail_header, $member_id, $order_id, $order_data, $response_data, $continue_data ) .
				apply_filters( 'usces_filter_sbps_auto_settlement_error_mail_body', $mail_body, $member_id, $order_id, $order_data, $response_data, $continue_data ) .
				apply_filters( 'usces_filter_sbps_auto_settlement_error_mail_footer', $mail_footer, $member_id, $order_id, $order_data, $response_data, $continue_data );
			$headers      = apply_filters( 'usces_filter_sbps_auto_settlement_error_mail_headers', '' );
			$to_customer  = array(
				'to_name'      => sprintf( _x( '%s', 'honorific', 'usces' ), $name ),
				'to_address'   => $member_info['mem_email'],
				'from_name'    => get_option( 'blogname' ),
				'from_address' => $usces->options['sender_mail'],
				'reply_name'   => get_option( 'blogname' ),
				'reply_to'     => usces_get_first_order_mail(),
				'return_path'  => $usces->options['sender_mail'],
				'subject'      => $subject,
				'message'      => do_shortcode( $message ),
				'headers'      => $headers,
			);
			usces_send_mail( $to_customer );
		}

		$error                                      = ( empty( $this->continuation_charging_mail['NG'] ) ) ? 0 : $this->continuation_charging_mail['NG'];
		$this->continuation_charging_mail['NG']     = $error + 1;
		$this->continuation_charging_mail['mail'][] = $mail_body;
	}

	/**
	 * 自動継続課金処理メール本文
	 *
	 * @param  int     $member_id Member ID.
	 * @param  int     $order_id Order number.
	 * @param  array   $order_data Order data.
	 * @param  array   $response_data Response data.
	 * @param  array   $continue_data Continuation data.
	 * @param  boolean $html HTML Mail.
	 * @return string
	 */
	public function auto_settlement_message( $member_id, $order_id, $order_data, $response_data, $continue_data, $html = true ) {
		global $usces;

		if ( usces_is_html_mail() && $html ) {
			$message = $this->auto_settlement_message_htmlbody( $member_id, $order_id, $order_data, $response_data, $continue_data );
		} else {
			$member_info     = $usces->get_member_info( $member_id );
			$name            = usces_localized_name( $member_info['mem_name1'], $member_info['mem_name2'], 'return' );
			$contracted_date = ( isset( $continue_data['contractedday'] ) ) ? $continue_data['contractedday'] : '';
			$charged_date    = ( isset( $continue_data['chargedday'] ) ) ? $continue_data['chargedday'] : '';

			$message  = usces_mail_line( 2 ); // --------------------
			$message .= __( 'Order ID', 'dlseller' ) . ' : ' . $order_id . "\r\n";
			$message .= __( 'Application Date', 'dlseller' ) . ' : ' . $order_data['order_date'] . "\r\n";
			$message .= __( 'Member ID', 'dlseller' ) . ' : ' . $member_id . "\r\n";
			$message .= __( 'Contractor name', 'dlseller' ) . ' : ' . sprintf( _x( '%s', 'honorific', 'usces' ), $name ) . "\r\n";

			$cart      = usces_get_ordercartdata( $order_id );
			$cart_row  = current( $cart );
			$item_name = $usces->getCartItemName_byOrder( $cart_row );
			$options   = ( empty( $cart_row['options'] ) ) ? array() : $cart_row['options'];
			$message  .= __( 'Items', 'usces' ) . ' : ' . $item_name . "\r\n";
			if ( is_array( $options ) && 0 < count( $options ) ) {
				$optstr = '';
				foreach ( $options as $key => $value ) {
					if ( ! empty( $key ) ) {
						$key   = urldecode( $key );
						$value = maybe_unserialize( $value );
						if ( is_array( $value ) ) {
							$c       = '';
							$optstr .= '( ' . $key . ' : ';
							foreach ( $value as $v ) {
								$optstr .= $c . rawurldecode( $v );
								$c       = ', ';
							}
							$optstr .= " )\r\n";
						} else {
							$optstr .= '( ' . $key . ' : ' . rawurldecode( $value ) . " )\r\n";
						}
					}
				}
				$message .= $optstr;
			}

			$message .= __( 'Settlement amount', 'usces' ) . ' : ' . usces_crform( $continue_data['price'], true, false, 'return' ) . "\r\n";
			if ( isset( $response_data['reminder'] ) ) {
				if ( ! empty( $charged_date ) ) {
					$message .= __( 'Next Withdrawal Date', 'dlseller' ) . ' : ' . $charged_date . "\r\n";
				}
				if ( ! empty( $contracted_date ) ) {
					$message .= __( 'Renewal Date', 'dlseller' ) . ' : ' . $contracted_date . "\r\n";
				}
			} else {
				if ( ! empty( $charged_date ) ) {
					$message .= __( 'Next Withdrawal Date', 'dlseller' ) . ' : ' . $charged_date . "\r\n";
				}
				if ( ! empty( $contracted_date ) ) {
					$message .= __( 'Renewal Date', 'dlseller' ) . ' : ' . $contracted_date . "\r\n";
				}
				$message .= "\r\n";

				if ( isset( $response_data['res_result'] ) && 'OK' === $response_data['res_result'] ) {
					$message .= __( 'Result', 'usces' ) . ' : ' . __( 'Normal done', 'usces' ) . "\r\n";
				} else {
					$message .= __( 'Result', 'usces' ) . ' : ' . __( 'Error', 'usces' ) . "\r\n";
					if ( isset( $response_data['res_err_code'] ) ) {
						$message .= 'res_err_code : ' . $response_data['res_err_code'] . "\r\n";
					}
				}
			}
			$message .= usces_mail_line( 2 ) . "\r\n";// --------------------
		}
		return $message;
	}

	/**
	 * Automatic renewal billing process email body html
	 *
	 * @param  int   $member_id Member ID.
	 * @param  int   $order_id Order number.
	 * @param  array $order_data Order data.
	 * @param  array $response_data Response data.
	 * @param  array $continue_data Continuation data.
	 * @return string
	 */
	public function auto_settlement_message_htmlbody( $member_id, $order_id, $order_data, $response_data, $continue_data ) {
		global $usces;

		$member_info     = $usces->get_member_info( $member_id );
		$name            = usces_localized_name( $member_info['mem_name1'], $member_info['mem_name2'], 'return' );
		$contracted_date = ( isset( $continue_data['contractedday'] ) ) ? $continue_data['contractedday'] : '';
		$charged_date    = ( isset( $continue_data['chargedday'] ) ) ? $continue_data['chargedday'] : '';

		$message  = '<table style="font-size: 14px; margin-bottom: 30px; width: 100%; border-collapse: collapse; border: 1px solid #ddd;"><tbody>';
		$message .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
		$message .= __( 'Order ID', 'dlseller' );
		$message .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
		$message .= $order_id;
		$message .= '</td></tr>';

		$message .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
		$message .= __( 'Application Date', 'dlseller' );
		$message .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
		$message .= $order_data['order_date'];
		$message .= '</td></tr>';

		$message .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
		$message .= __( 'Member ID', 'dlseller' );
		$message .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
		$message .= $member_id;
		$message .= '</td></tr>';

		$message .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
		$message .= __( 'Contractor name', 'dlseller' );
		$message .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
		$message .= sprintf( _x( '%s', 'honorific', 'usces' ), $name );
		$message .= '</td></tr>';

		$cart      = usces_get_ordercartdata( $order_id );
		$cart_row  = current( $cart );
		$item_name = $usces->getCartItemName_byOrder( $cart_row );
		$options   = ( empty( $cart_row['options'] ) ) ? array() : $cart_row['options'];
		$message  .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
		$message  .= __( 'Items', 'usces' );
		$message  .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
		$message  .= $item_name;
		if ( is_array( $options ) && 0 < count( $options ) ) {
			$optstr = '';
			foreach ( $options as $key => $value ) {
				if ( ! empty( $key ) ) {
					$key   = urldecode( $key );
					$value = maybe_unserialize( $value );
					if ( is_array( $value ) ) {
						$c       = '';
						$optstr .= '( ' . $key . ' : ';
						foreach ( $value as $v ) {
							$optstr .= $c . rawurldecode( $v );
							$c       = ', ';
						}
						$optstr .= ' )<br>';
					} else {
						$optstr .= '( ' . $key . ' : ' . rawurldecode( $value ) . ' )<br>';
					}
				}
			}
			$message .= $optstr;
		}
		$message .= '</td></tr>';

		$message .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
		$message .= __( 'Settlement amount', 'usces' );
		$message .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
		$message .= usces_crform( $continue_data['price'], true, false, 'return' );
		$message .= '</td></tr>';
		if ( isset( $response_data['reminder'] ) ) {
			if ( ! empty( $charged_date ) ) {
				$message .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
				$message .= __( 'Next Withdrawal Date', 'dlseller' );
				$message .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
				$message .= $charged_date;
				$message .= '</td></tr>';
			}
			if ( ! empty( $contracted_date ) ) {
				$message .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
				$message .= __( 'Renewal Date', 'dlseller' );
				$message .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
				$message .= $contracted_date;
				$message .= '</td></tr>';
			}
		} else {
			if ( ! empty( $charged_date ) ) {
				$message .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
				$message .= __( 'Next Withdrawal Date', 'dlseller' );
				$message .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
				$message .= $charged_date;
				$message .= '</td></tr>';
			}
			if ( ! empty( $contracted_date ) ) {
				$message .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
				$message .= __( 'Renewal Date', 'dlseller' );
				$message .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
				$message .= $contracted_date;
				$message .= '</td></tr>';
			}

			if ( isset( $response_data['res_result'] ) && 'OK' === $response_data['res_result'] ) {
				$message .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
				$message .= __( 'Result', 'usces' );
				$message .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
				$message .= __( 'Normal done', 'usces' );
				$message .= '</td></tr>';
			} else {
				$message .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
				$message .= __( 'Result', 'usces' );
				$message .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
				$message .= __( 'Error', 'usces' );
				$message .= '</td></tr>';
				if ( isset( $response_data['res_err_code'] ) ) {
					$message .= '<tr><td style="background-color: #f9f9f9; padding: 12px; width: 33%; border: 1px solid #ddd; text-align: left;">';
					$message .= 'res_err_code';
					$message .= '</td><td style="padding: 12px; width: 67%; border: 1px solid #ddd;">';
					$message .= $response_data['res_err_code'];
					$message .= '</td></tr>';
				}
			}
		}
		$message .= '</tbody></table>';
		return $message;
	}

	/**
	 * 自動継続課金処理
	 * dlseller_action_do_continuation
	 *
	 * @param  string $today Today.
	 * @param  array  $todays_charging Charged data.
	 */
	public function do_auto_continuation( $today, $todays_charging ) {
		global $usces;

		if ( empty( $todays_charging ) ) {
			return;
		}

		$ok            = ( empty( $this->continuation_charging_mail['OK'] ) ) ? 0 : $this->continuation_charging_mail['OK'];
		$error         = ( empty( $this->continuation_charging_mail['NG'] ) ) ? 0 : $this->continuation_charging_mail['NG'];
		$admin_subject = apply_filters( 'usces_filter_sbps_autobilling_email_admin_subject', __( 'Automatic Continuing Charging Process Result', 'usces' ) . ' ' . $today, $today );
		$admin_footer  = apply_filters( 'usces_filter_sbps_autobilling_email_admin_mail_footer', __( 'For details, please check on the administration panel > Continuous charge member list > Continuous charge member information.', 'usces' ) );
		$admin_message = __( 'Report that automated accounting process has been completed.', 'usces' ) . "\r\n\r\n"
			. __( 'Processing date', 'usces' ) . ' : ' . wp_date( 'Y-m-d H:i:s' ) . "\r\n"
			. __( 'Normal done', 'usces' ) . ' : ' . $ok . "\r\n"
			. __( 'Abnormal done', 'usces' ) . ' : ' . $error . "\r\n\r\n";
		foreach ( (array) $this->continuation_charging_mail['mail'] as $mail ) {
			$admin_message .= $mail . "\r\n";
		}
		$admin_message .= $admin_footer . "\r\n";

		$to_admin = array(
			'to_name'      => apply_filters( 'usces_filter_bccmail_to_admin_name', 'Shop Admin' ),
			'to_address'   => $usces->options['order_mail'],
			'from_name'    => apply_filters( 'usces_filter_bccmail_from_admin_name', 'Welcart Auto BCC' ),
			'from_address' => $usces->options['sender_mail'],
			'reply_name'   => get_option( 'blogname' ),
			'reply_to'     => usces_get_first_order_mail(),
			'return_path'  => $usces->options['sender_mail'],
			'subject'      => $admin_subject,
			'message'      => do_shortcode( $admin_message ),
		);
		usces_send_mail( $to_admin );
		unset( $this->continuation_charging_mail );
	}

	/**
	 * 課金日通知メール
	 * dlseller_filter_reminder_mail_body
	 *
	 * @param  string $mail_body Message body.
	 * @param  int    $order_id Order number.
	 * @param  array  $continue_data Continuation data.
	 * @return string
	 */
	public function reminder_mail_body( $mail_body, $order_id, $continue_data ) {
		global $usces;

		$member_id     = $continue_data['member_id'];
		$order_id      = $continue_data['order_id'];
		$order_data    = $usces->get_order_data( $order_id, 'direct' );
		$response_data = array( 'reminder' => 'reminder' );
		$mail_body     = $this->auto_settlement_message( $member_id, $order_id, $order_data, $response_data, $continue_data );
		return $mail_body;
	}

	/**
	 * 契約更新日通知メール
	 * dlseller_filter_contract_renewal_mail_body
	 *
	 * @param  string $mail_body Message body.
	 * @param  int    $order_id Order number.
	 * @param  array  $continue_data Continuation data.
	 * @return string
	 */
	public function contract_renewal_mail_body( $mail_body, $order_id, $continue_data ) {
		global $usces;

		$member_id     = $continue_data['member_id'];
		$order_data    = $usces->get_order_data( $order_id, 'direct' );
		$response_data = array( 'reminder' => 'contract_renewal' );
		$mail_body     = $this->auto_settlement_message( $member_id, $order_id, $order_data, $response_data, $continue_data );
		return $mail_body;
	}

	/**
	 * 継続課金会員データ取得
	 *
	 * @param  int $member_id Member ID.
	 * @param  int $order_id Order number.
	 * @return array
	 */
	private function get_continuation_data( $member_id, $order_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT 
			`con_id` AS `con_id`, 
			`con_acting` AS `acting`, 
			`con_order_price` AS `order_price`, 
			`con_price` AS `price`, 
			`con_next_charging` AS `chargedday`, 
			`con_next_contracting` AS `contractedday`, 
			`con_startdate` AS `startdate`, 
			`con_status` AS `status` 
			FROM {$wpdb->prefix}usces_continuation 
			WHERE `con_order_id` = %d AND `con_member_id` = %d",
			$order_id,
			$member_id
		);
		$data  = $wpdb->get_row( $query, ARRAY_A );
		return $data;
	}

	/**
	 * 継続課金会員データ更新
	 *
	 * @param  int     $member_id Member ID.
	 * @param  int     $order_id Order number.
	 * @param  array   $data Continuation data.
	 * @param  boolean $stop Stop continuous billing.
	 * @return boolean
	 */
	private function update_continuation_data( $member_id, $order_id, $data, $stop = false ) {
		global $wpdb;

		if ( $stop ) {
			$query = $wpdb->prepare(
				"UPDATE {$wpdb->prefix}usces_continuation SET 
				`con_status` = 'cancellation' 
				WHERE `con_order_id` = %d AND `con_member_id` = %d",
				$order_id,
				$member_id
			);
		} else {
			$query = $wpdb->prepare(
				"UPDATE {$wpdb->prefix}usces_continuation SET 
				`con_price` = %f, 
				`con_next_charging` = %s, 
				`con_next_contracting` = %s, 
				`con_status` = %s 
				WHERE `con_order_id` = %d AND `con_member_id` = %d",
				$data['price'],
				$data['chargedday'],
				$data['contractedday'],
				$data['status'],
				$order_id,
				$member_id
			);
		}
		$res = $wpdb->query( $query );
		return $res;
	}

	/**
	 * ゼウス定期購入メッセージ
	 * wcad_filter_admin_notices
	 *
	 * @param  string $msg Admin message.
	 * @return string
	 */
	public function admin_notices_autodelivery( $msg ) {
		global $usces;

		$acting_opts = $this->get_acting_settings();
		if ( ( isset( $acting_opts['activate'] ) && 'on' === $acting_opts['activate'] ) &&
			( isset( $acting_opts['card_activate'] ) && ( 'on' === $acting_opts['card_activate'] || 'on' === $acting_opts['card_activate'] || 'token' === $acting_opts['card_activate'] ) ) &&
			( isset( $acting_opts['cust_manage'] ) && 'on' === $acting_opts['cust_manage'] ) ) {
			$msg = '';
		} else {
			$zeus_opts       = $usces->options['acting_settings']['zeus'];
			$zeus_flag       = ( ( isset( $zeus_opts['activate'] ) && 'on' === $zeus_opts['activate'] ) && ( isset( $zeus_opts['card_activate'] ) && 'on' === $zeus_opts['card_activate'] ) ) ? true : false;
			$zeus_batch      = ( isset( $zeus_opts['batch'] ) ) ? $zeus_opts['batch'] : 'off';
			$welcartpay_opts = $usces->options['acting_settings']['welcart'];
			$welcart_flag    = ( ( isset( $welcartpay_opts['activate'] ) && 'on' === $welcartpay_opts['activate'] ) && ( isset( $welcartpay_opts['card_activate'] ) && ( 'on' === $welcartpay_opts['card_activate'] || 'link' === $welcartpay_opts['card_activate'] || 'token' === $welcartpay_opts['card_activate'] ) ) ) ? true : false;
			$welcart_batch   = ( isset( $welcartpay_opts['quickpay'] ) ) ? $welcartpay_opts['quickpay'] : 'off';
			if ( ( ! $zeus_flag || 'off' === $zeus_batch ) || ( ! $welcart_flag || 'off' === $welcart_batch ) ) {
				$msg = '
				<div class="error">
				<p>「クレジット決済設定」にて、SBペイメントサービスのクレジットカード情報保存を「保存する」に設定してください。</p>
				</div>';
			}
		}
		return $msg;
	}

	/**
	 * 発送先リスト利用可能決済
	 * wcad_filter_shippinglist_acting
	 *
	 * @param  string $acting Payment method.
	 * @return string
	 */
	public function set_shippinglist_acting( $acting ) {
		$acting = 'acting_sbps_card';
		return $acting;
	}

	/**
	 * 管理画面利用可能決済メッセージ
	 * wcad_filter_available_regular_payment_method
	 *
	 * @param  array $payment_method Payment method.
	 * @return array
	 */
	public function available_regular_payment_method( $payment_method ) {
		$payment_method[] = 'acting_sbps_card';
		return $payment_method;
	}

	/**
	 * 定期購入決済処理
	 * wcad_action_reg_auto_orderdata
	 *
	 * @param string $args {
	 *     The array of Order related data.
	 *     @type array  $cart          Cart data.
	 *     @type array  $entry         Entry data.
	 *     @type int    $order_id      Order ID.
	 *     @type int    $member_id     Member ID.
	 *     @type array  $payments      Payment data.
	 *     @type int    $charging_type Charging type.
	 *     @type float  $total_amount  Total amount.
	 *     @type int    $reg_id        Regular ID.
	 * }
	 */
	public function register_auto_orderdata( $args ) {
		global $usces;
		extract( $args );

		$acting_flg = $payments['settlement'];
		if ( 'acting_sbps_card' !== $acting_flg ) {
			return;
		}

		if ( ! usces_is_membersystem_state() ) {
			return;
		}

		if ( 0 >= $total_amount ) {
			return;
		}

		$settltment_errmsg = '';

		$acting_opts = $this->get_acting_settings();
		$rand        = usces_acting_key();
		$cust_code   = $member_id;
		$cust_ref    = $this->api_customer_reference( $cust_code );
		if ( isset( $cust_ref['result'] ) && 'OK' === $cust_ref['result'] ) {
			$cart_row  = current( $cart );
			$item_id   = mb_convert_kana( $usces->getItemCode( $cart_row['post_id'] ), 'a', 'UTF-8' );
			$item_name = $usces->getCartItemName_byOrder( $cart_row );
			if ( 1 < count( $cart ) ) {
				$item_name .= ' ' . __( 'Others', 'usces' );
			}
			if ( 36 < mb_strlen( $item_name, 'UTF-8' ) ) {
				$item_name = mb_substr( $item_name, 0, 30, 'UTF-8' ) . '...';
			}
			$item_name     = trim( mb_convert_encoding( $item_name, 'SJIS', 'UTF-8' ) );
			$amount        = usces_crform( $total_amount, false, false, 'return', false );
			$free1         = $acting_flg;
			$order_rowno   = '1';
			$encrypted_flg = '1';
			$request_date  = wp_date( 'YmdHis' );
			$sps_hashcode  = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $cust_code . $rand . $item_id . $item_name . $amount . $free1 . $order_rowno . $encrypted_flg . $request_date . $acting_opts['hash_key'];
			$sps_hashcode  = sha1( $sps_hashcode );
			$connection    = $this->get_connection();

			/* 決済要求 */
			$request_settlement  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST01-00131-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<cust_code>' . $cust_code . '</cust_code>
	<order_id>' . $rand . '</order_id>
	<item_id>' . $item_id . '</item_id>
	<item_name>' . base64_encode( $item_name ) . '</item_name>
	<amount>' . $amount . '</amount>
	<free1>' . base64_encode( $free1 ) . '</free1>
	<order_rowno>' . $order_rowno . '</order_rowno>
	<encrypted_flg>' . $encrypted_flg . '</encrypted_flg>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
			$xml_settlement      = $this->get_xml_response( $connection['api_url'], $request_settlement );
			$response_settlement = $this->xml2assoc( $xml_settlement, $this->acting_card, $encrypted_flg );
			if ( isset( $response_settlement['res_result'] ) && 'OK' === $response_settlement['res_result'] ) {
				$sps_transaction_id = $response_settlement['res_sps_transaction_id'];
				$tracking_id        = $response_settlement['res_tracking_id'];
				$request_date       = wp_date( 'YmdHis' );
				$sps_hashcode       = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $sps_transaction_id . $tracking_id . $request_date . $acting_opts['hash_key'];
				$sps_hashcode       = sha1( $sps_hashcode );

				/* 確定要求 */
				$request_credit  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00101-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<sps_transaction_id>' . $sps_transaction_id . '</sps_transaction_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<processing_datetime></processing_datetime>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
				$xml_credit      = $this->get_xml_response( $connection['api_url'], $request_credit );
				$response_credit = $this->xml2assoc( $xml_credit, $this->acting_card );
				if ( isset( $response_credit['res_result'] ) && 'OK' === $response_credit['res_result'] ) {
					if ( isset( $acting_opts['sales'] ) && 'auto' === $acting_opts['sales'] ) {
						$process_date = $response_credit['res_process_date'];
						$request_date = wp_date( 'YmdHis' );
						$sps_hashcode = $acting_opts['merchant_id'] . $acting_opts['service_id'] . $sps_transaction_id . $tracking_id . $process_date . $amount . $request_date . $acting_opts['hash_key'];
						$sps_hashcode = sha1( $sps_hashcode );

						/* 売上要求（自動売上）*/
						$request_sales  = '<?xml version="1.0" encoding="Shift_JIS"?>
<sps-api-request id="ST02-00201-101">
	<merchant_id>' . $acting_opts['merchant_id'] . '</merchant_id>
	<service_id>' . $acting_opts['service_id'] . '</service_id>
	<sps_transaction_id>' . $sps_transaction_id . '</sps_transaction_id>
	<tracking_id>' . $tracking_id . '</tracking_id>
	<processing_datetime>' . $process_date . '</processing_datetime>
	<pay_option_manage>
		<amount>' . $amount . '</amount>
	</pay_option_manage>
	<request_date>' . $request_date . '</request_date>
	<sps_hashcode>' . $sps_hashcode . '</sps_hashcode>
</sps-api-request>';
						$xml_sales      = $this->get_xml_response( $connection['api_url'], $request_sales );
						$response_sales = $this->xml2assoc( $xml_sales, $this->acting_card );
						$response_sales = apply_filters( 'usces_filter_sbps_card_register_auto_orderdata_log', $response_sales, $args );
						if ( isset( $response_sales['res_result'] ) && 'OK' === $response_sales['res_result'] ) {
							if ( ! isset( $response_sales['amount'] ) ) {
								$response_sales['amount'] = $amount;
							}
							$this->save_acting_log( $response_sales, $this->acting_card, $acting_opts['sales'], $response_sales['res_result'], $order_id, $tracking_id );
							$usces->set_order_meta_value( 'res_tracking_id', $tracking_id, $order_id );
							$usces->set_order_meta_value( 'wc_trans_id', $tracking_id, $order_id );
							$usces->set_order_meta_value( 'trans_id', $rand, $order_id );
							if ( ! isset( $response_sales['acting'] ) ) {
								$response_sales['acting'] = $this->acting_card;
							}
							$usces->set_order_meta_value( $acting_flg, usces_serialize( $response_sales ), $order_id );
						} else {
							$usces->set_order_meta_value( 'trans_id', $rand, $order_id );
							$this->save_acting_log( $response_sales, $this->acting_card, $acting_opts['sales'], $response_sales['res_result'], $order_id, $tracking_id );
							$settltment_errmsg = __( '[Regular purchase] Settlement was not completed.', 'autodelivery' );
							$log               = array(
								'acting' => $this->acting_card,
								'key'    => $rand,
								'result' => $response_sales['res_err_code'],
								'data'   => $response_sales,
							);
							usces_save_order_acting_error( $log );
						}
						do_action( 'usces_action_register_auto_orderdata', $args, $response_sales );
					} else {
						/* 指定売上 */
						if ( ! isset( $response_credit['amount'] ) ) {
							$response_credit['amount'] = $amount;
						}
						$response_credit = apply_filters( 'usces_filter_sbps_card_register_auto_orderdata_log', $response_credit, $args );
						$this->save_acting_log( $response_credit, $this->acting_card, $acting_opts['sales'], $response_credit['res_result'], $order_id, $tracking_id );
						$usces->set_order_meta_value( 'res_tracking_id', $tracking_id, $order_id );
						$usces->set_order_meta_value( 'wc_trans_id', $tracking_id, $order_id );
						$usces->set_order_meta_value( 'trans_id', $rand, $order_id );
						if ( ! isset( $response_credit['acting'] ) ) {
							$response_credit['acting'] = $this->acting_card;
						}
						$usces->set_order_meta_value( $acting_flg, usces_serialize( $response_credit ), $order_id );
						do_action( 'usces_action_register_auto_orderdata', $args, $response_credit );
					}
				} else {
					$usces->set_order_meta_value( 'trans_id', $rand, $order_id );
					$this->save_acting_log( $response_credit, $this->acting_card, $acting_opts['sales'], $response_credit['res_result'], $order_id, $tracking_id );
					$settltment_errmsg = __( '[Regular purchase] Settlement was not completed.', 'autodelivery' );
					$log               = array(
						'acting' => $this->acting_card,
						'key'    => $rand,
						'result' => $response_credit['res_err_code'],
						'data'   => $response_credit,
					);
					usces_save_order_acting_error( $log );
					do_action( 'usces_action_register_auto_orderdata', $args, $response_credit );
				}
			} else {
				$usces->set_order_meta_value( 'trans_id', $rand, $order_id );
				$this->save_acting_log( $response_settlement, $this->acting_card, $acting_opts['sales'], $response_settlement['res_result'], $order_id, $tracking_id );
				$settltment_errmsg = __( '[Regular purchase] Settlement was not completed.', 'autodelivery' );
				$log               = array(
					'acting' => $this->acting_card,
					'key'    => $rand,
					'result' => $response_settlement['res_err_code'],
					'data'   => $response_settlement,
				);
				usces_save_order_acting_error( $log );
				do_action( 'usces_action_register_auto_orderdata', $args, $response_settlement );
			}
		} else {
			$usces->set_order_meta_value( 'trans_id', $rand, $order_id );
			$result = ( isset( $cust_ref['result'] ) ) ? $cust_ref['result'] : 'NG';
			$this->save_acting_log( $cust_ref, $this->acting_card, 'error', $result, $order_id, $rand );
			$settltment_errmsg = __( '[Regular purchase] Member information acquisition error.', 'autodelivery' );
			$log               = array(
				'acting' => $this->acting_card . '(member_process)',
				'key'    => $member_id,
				'result' => $result,
				'data'   => $cust_ref,
			);
			usces_save_order_acting_error( $log );
			do_action( 'usces_action_register_auto_orderdata', $args, $cust_ref );
		}
		if ( '' !== $settltment_errmsg ) {
			$settlement = array(
				'settltment_status' => __( 'Failure', 'autodelivery' ),
				'settltment_errmsg' => $settltment_errmsg,
			);
			$usces->set_order_meta_value( $acting_flg, usces_serialize( $settlement ), $order_id );
			wcad_settlement_error_mail( $order_id, $settltment_errmsg );
		}
	}

	/**
	 * 決済ログ更新
	 *
	 * @param string $acting Acting type.
	 * @param int    $order_id Order number.
	 * @param string $tracking_id Tracking ID.
	 * @param string $trans_id Transaction ID.
	 * @return array
	 */
	private function update_acting_log( $acting, $order_id, $tracking_id, $trans_id ) {
		global $wpdb;

		$res = false;

		$acting_log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}usces_acting_log WHERE `acting` = %d AND `order_id` = %d AND `tracking_id` = %s",
				$acting,
				$order_id,
				$trans_id
			),
			ARRAY_A
		);

		if ( $acting_log && isset( $acting_log['status'] ) && 'error' === $acting_log['status'] ) {
			$res = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}usces_acting_log SET `tracking_id` = %s WHERE `ID` = %d",
					$tracking_id,
					$acting_log['ID']
				)
			);
		}
		return $res;
	}
}
