<?php
/**
 * ペイディ決済モジュール
 *
 * @package  Welcart
 * @author   Collne Inc.
 * @version  1.0.0
 * @since    2.6.11
 */
class PAIDY_SETTLEMENT {
	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * 決済代行会社ID
	 *
	 * @var string
	 */
	protected $paymod_id;

	/**
	 * 決済種別
	 *
	 * @var string
	 */
	protected $pay_method;

	/**
	 * 決済代行会社略称
	 *
	 * @var string
	 */
	protected $acting_name;

	/**
	 * 決済代行会社正式名称
	 *
	 * @var string
	 */
	protected $acting_formal_name;

	/**
	 * 決済代行会社URL
	 *
	 * @var string
	 */
	protected $acting_company_url;

	/**
	 * 併用不可決済モジュール
	 *
	 * @var array
	 */
	protected $unavailable_method;

	/**
	 * エラーメッセージ
	 *
	 * @var string
	 */
	protected $error_mes;

	/**
	 * Construct.
	 */
	public function __construct() {
		$this->paymod_id          = 'paidy';
		$this->pay_method         = array(
			'acting_paidy',
		);
		$this->acting_name        = 'ペイディ';
		$this->acting_formal_name = 'あと払い（ペイディ）';
		$this->acting_company_url = 'https://paidy.com/';

		$this->initialize_data();

		if ( is_admin() ) {
			add_action( 'admin_print_footer_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'usces_action_admin_settlement_update', array( $this, 'settlement_update' ) );
			add_action( 'usces_action_settlement_tab_title', array( $this, 'settlement_tab_title' ) );
			add_action( 'usces_action_settlement_tab_body', array( $this, 'settlement_tab_body' ) );
			add_action( 'usces_filter_add_payment_method', array( $this, 'add_payment_method' ) );
			add_action( 'usces_filter_up_payment_method', array( $this, 'up_payment_method' ) );
		}

		if ( $this->is_validity_acting() ) {
			add_action( 'init', array( $this, 'acting_transaction' ) );
			add_filter( 'usces_filter_is_complete_settlement', array( $this, 'is_complete_settlement' ), 10, 3 );
			if ( is_admin() ) {
				add_action( 'usces_action_admin_ajax', array( $this, 'admin_ajax' ) );
				add_filter( 'usces_filter_orderlist_detail_value', array( $this, 'orderlist_settlement_status' ), 10, 4 );
				add_action( 'usces_action_order_edit_form_status_block_middle', array( $this, 'settlement_status' ), 10, 3 );
				add_action( 'usces_action_order_edit_form_settle_info', array( $this, 'settlement_information' ), 10, 2 );
				add_action( 'usces_action_endof_order_edit_form', array( $this, 'settlement_dialog' ), 10, 2 );
				add_filter( 'usces_filter_settle_info_field_meta_keys', array( $this, 'settlement_info_field_meta_keys' ) );
				add_filter( 'usces_filter_settle_info_field_keys', array( $this, 'settlement_info_field_keys' ), 10, 2 );
				add_filter( 'usces_filter_settle_info_field_value', array( $this, 'settlement_info_field_value' ), 10, 3 );
				add_filter( 'usces_filter_get_link_key', array( $this, 'get_link_key' ), 10, 2 );
				add_action( 'usces_action_revival_order_data', array( $this, 'revival_orderdata' ), 10, 3 );
			} else {
				add_action( 'wp_print_footer_scripts', array( $this, 'footer_scripts' ), 9 );
				add_filter( 'usces_filter_confirm_inform', array( $this, 'confirm_inform' ), 10, 5 );
				add_action( 'usces_action_acting_processing', array( $this, 'acting_processing' ), 10, 2 );
				add_filter( 'usces_filter_check_acting_return_results', array( $this, 'acting_return' ) );
				add_filter( 'usces_filter_check_acting_return_duplicate', array( $this, 'check_acting_return_duplicate' ), 10, 2 );
				add_action( 'usces_action_reg_orderdata', array( $this, 'register_orderdata' ) );
				add_filter( 'usces_filter_get_error_settlement', array( $this, 'error_page_message' ) );
			}
		}
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
	 * Initialize.
	 */
	public function initialize_data() {
		$options = get_option( 'usces', array() );
		$options['acting_settings']['paidy']['activate']       = ( isset( $options['acting_settings']['paidy']['activate'] ) ) ? $options['acting_settings']['paidy']['activate'] : 'off';
		$options['acting_settings']['paidy']['paidy_activate'] = ( isset( $options['acting_settings']['paidy']['paidy_activate'] ) ) ? $options['acting_settings']['paidy']['paidy_activate'] : 'off';
		if ( false !== strpos( $options['acting_settings']['paidy']['public_key'], '_test_' ) ) {
			$options['acting_settings']['paidy']['environment']     = 'test';
			$options['acting_settings']['paidy']['public_key']      = ( ! empty( $options['acting_settings']['paidy']['public_key'] ) ) ? $options['acting_settings']['paidy']['public_key'] : '';
			$options['acting_settings']['paidy']['secret_key']      = ( ! empty( $options['acting_settings']['paidy']['secret_key'] ) ) ? $options['acting_settings']['paidy']['secret_key'] : '';
			$options['acting_settings']['paidy']['public_key_test'] = ( ! empty( $options['acting_settings']['paidy']['public_key'] ) ) ? $options['acting_settings']['paidy']['public_key'] : '';
			$options['acting_settings']['paidy']['secret_key_test'] = ( ! empty( $options['acting_settings']['paidy']['secret_key'] ) ) ? $options['acting_settings']['paidy']['secret_key'] : '';
		} else {
			$options['acting_settings']['paidy']['environment']     = ( isset( $options['acting_settings']['paidy']['environment'] ) ) ? $options['acting_settings']['paidy']['environment'] : 'live';
			$options['acting_settings']['paidy']['public_key']      = ( isset( $options['acting_settings']['paidy']['public_key'] ) ) ? $options['acting_settings']['paidy']['public_key'] : '';
			$options['acting_settings']['paidy']['secret_key']      = ( isset( $options['acting_settings']['paidy']['secret_key'] ) ) ? $options['acting_settings']['paidy']['secret_key'] : '';
			$options['acting_settings']['paidy']['public_key_test'] = ( isset( $options['acting_settings']['paidy']['public_key_test'] ) ) ? $options['acting_settings']['paidy']['public_key_test'] : '';
			$options['acting_settings']['paidy']['secret_key_test'] = ( isset( $options['acting_settings']['paidy']['secret_key_test'] ) ) ? $options['acting_settings']['paidy']['secret_key_test'] : '';
		}
		update_option( 'usces', $options );

		$available_settlement = get_option( 'usces_available_settlement', array() );
		if ( ! in_array( $this->paymod_id, $available_settlement, true ) ) {
			$available_settlement[ $this->paymod_id ] = $this->acting_name;
			update_option( 'usces_available_settlement', $available_settlement );
		}

		$this->unavailable_method = array( 'acting_paygent_paidy' );
	}

	/**
	 * 決済有効判定
	 * 支払方法で使用している場合に true
	 *
	 * @param  string $type Module type.
	 * @return boolean
	 */
	public function is_validity_acting( $type = '' ) {
		$options = get_option( 'usces', array() );
		if ( empty( $options['acting_settings']['paidy'] ) ) {
			return false;
		}

		$payment_method = usces_get_system_option( 'usces_payment_method', 'sort' );
		$method         = false;

		switch ( $type ) {
			case 'paidy':
				foreach ( $payment_method as $payment ) {
					if ( 'acting_paidy' === $payment['settlement'] && 'activate' === $payment['use'] ) {
						$method = true;
						break;
					}
				}
				if ( $method && $this->is_activate_paidy() ) {
					return true;
				} else {
					return false;
				}
				break;
			default:
				if ( isset( $options['acting_settings']['paidy']['activate'] ) && 'on' === $options['acting_settings']['paidy']['activate'] ) {
					return true;
				} else {
					return false;
				}
		}
	}

	/**
	 * ペイディ決済有効判定
	 *
	 * @return boolean
	 */
	public function is_activate_paidy() {
		$options = get_option( 'usces', array() );
		if ( ( isset( $options['acting_settings']['paidy']['activate'] ) && 'on' === $options['acting_settings']['paidy']['activate'] ) &&
			( isset( $options['acting_settings']['paidy']['paidy_activate'] ) && 'on' === $options['acting_settings']['paidy']['paidy_activate'] ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	/**
	 * 管理画面スクリプト
	 * admin_print_footer_scripts
	 */
	public function admin_scripts() {
		$admin_page = filter_input( INPUT_GET, 'page' );
		switch ( $admin_page ) :
			/* クレジット決済設定画面 */
			case 'usces_settlement':
				$settlement_selected = get_option( 'usces_settlement_selected', array() );
				if ( in_array( $this->paymod_id, (array) $settlement_selected, true ) ) :
					$options        = get_option( 'usces', array() );
					$paidy_activate = ( isset( $options['acting_settings']['paidy']['paidy_activate'] ) ) ? $options['acting_settings']['paidy']['paidy_activate'] : 'off';
					?>
<script type="text/javascript">
jQuery(document).ready( function($) {
	var paidy_activate = "<?php echo esc_js( $paidy_activate ); ?>";
	if( "on" == paidy_activate ) {
		$(".paidy_form").css("display","");
	} else {
		$(".paidy_form").css("display","none");
	}
	$(document).on( "change", ".paidy_activate", function() {
		if( "on" == $(this).val() ) {
			$(".paidy_form").css("display","");
		} else {
			$(".paidy_form").css("display","none");
		}
	});
});
</script>
					<?php
				endif;
				break;

			/* 受注編集画面 */
			case 'usces_orderlist':
				$order_id     = '';
				$acting_flg   = '';
				$order_action = filter_input( INPUT_GET, 'order_action' );
				if ( 'usces_orderlist' === $admin_page && ( 'edit' === $order_action || 'editpost' === $order_action || 'newpost' === $order_action ) ) {
					$order_id = ( isset( $_REQUEST['order_id'] ) ) ? wp_unslash( $_REQUEST['order_id'] ) : '';
					if ( ! empty( $order_id ) ) {
						$acting_flg = $this->get_order_acting_flg( $order_id );
					}
				}
				if ( in_array( $acting_flg, $this->pay_method, true ) ) :
					?>
<script type="text/javascript">
jQuery(document).ready( function($) {
	adminOrderEdit = {
		retrieveSettlementPaidy : function() {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: "json",
				data: {
					action: "usces_admin_ajax",
					mode: "retrieve_paidy",
					order_id: $("#order_id").val(),
					trans_id: $("#trans_id").val(),
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function(retVal,dataType) {
				if( retVal.result ) {
					$("#settlement-response").html(retVal.result);
				}
				$("#settlement-response-loading").html("");
			}).fail( function(jqXHR,textStatus,errorThrown) {
				console.log(textStatus);
				console.log(jqXHR.status);
				console.log(errorThrown.message);
				$("#settlement-response-loading").html("");
			});
			return false;
		},
		closeSettlementPaidy : function() {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: "json",
				data: {
					action: "usces_admin_ajax",
					mode: "close_paidy",
					order_id: $("#order_id").val(),
					trans_id: $("#trans_id").val(),
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function(retVal,dataType) {
				if( retVal.acting_status ) {
					$("#settlement-status").html(retVal.acting_status);
				}
				if( retVal.result ) {
					$("#settlement-response").html(retVal.result);
				}
				$("#settlement-response-loading").html("");
			}).fail( function(jqXHR,textStatus,errorThrown) {
				console.log(textStatus);
				console.log(jqXHR.status);
				console.log(errorThrown.message);
				$("#settlement-response-loading").html("");
			});
			return false;
		},
		captureSettlementPaidy : function() {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: "json",
				data: {
					action: "usces_admin_ajax",
					mode: "capture_paidy",
					order_id: $("#order_id").val(),
					trans_id: $("#trans_id").val(),
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function(retVal,dataType) {
				if( retVal.acting_status ) {
					$("#settlement-status").html(retVal.acting_status);
				}
				if( retVal.result ) {
					$("#settlement-response").html(retVal.result);
				}
				$("#settlement-response-loading").html("");
			}).fail( function(jqXHR,textStatus,errorThrown) {
				console.log(textStatus);
				console.log(jqXHR.status);
				console.log(errorThrown.message);
				$("#settlement-response-loading").html("");
			});
			return false;
		},
		refundSettlementPaidy : function(amount) {
			$("#settlement-response").html("");
			$("#settlement-response-loading").html('<img src="'+uscesL10n.USCES_PLUGIN_URL+'/images/loading.gif" />');
			$.ajax({
				url: ajaxurl,
				type: "POST",
				cache: false,
				dataType: "json",
				data: {
					action: "usces_admin_ajax",
					mode: "refund_paidy",
					order_id: $("#order_id").val(),
					trans_id: $("#trans_id").val(),
					amount: amount,
					wc_nonce: $("#wc_nonce").val()
				}
			}).done( function(retVal,dataType) {
				if( retVal.acting_status ) {
					$("#settlement-status").html(retVal.acting_status);
				}
				if( retVal.result ) {
					$("#settlement-response").html(retVal.result);
				}
				$("#settlement-response-loading").html("");
			}).fail( function(jqXHR,textStatus,errorThrown) {
				console.log(textStatus);
				console.log(jqXHR.status);
				console.log(errorThrown.message);
				$("#settlement-response-loading").html("");
			});
			return false;
		}
	};

	$("#settlement_dialog").dialog({
		dialogClass: "admin-paidy-dialog",
		bgiframe: true,
		autoOpen: false,
		height: "auto",
		width: 800,
		resizable: true,
		modal: true,
		buttons: {
			"<?php esc_html_e( 'Close' ); ?>": function() {
				$(this).dialog("close");
			}
		},
		open: function() {
			adminOrderEdit.retrieveSettlementPaidy();
		},
		close: function() {
		}
	});

	$(document).on( "click", ".settlement-information", function() {
		var trans_id = $(this).attr("data-trans_id");
		var order_num = $(this).attr("data-num");
		$("#trans_id").val(trans_id);
		$("#order_num").val(order_num);
		$("#settlement_dialog").dialog("option","title","<?php echo esc_js( $this->acting_name ); ?>");
		$("#settlement_dialog").dialog("open");
	});

	$(document).on( "click", "#paidy-capture-settlement", function() {
		if( ! confirm("キャプチャー（売上確定）します。よろしいですか？") ) {
			return;
		}
		adminOrderEdit.captureSettlementPaidy();
	});

	$(document).on( "click", "#paidy-close-settlement", function() {
		if( ! confirm("クローズします。よろしいですか？") ) {
			return;
		}
		adminOrderEdit.closeSettlementPaidy();
	});

	$(document).on( "click", "#paidy-refund-settlement", function() {
		var amount_original = parseInt($("#amount_original").val())||0;
		var amount_change = parseInt($("#amount_change").val())||0;
		if( amount_change == amount_original ) {
			return;
		}
		if( amount_change > amount_original ) {
			alert("決済金額を超える金額はリファンド（返金）できません。");
			return;
		}
		if( 0 == amount_change ) {
			if( ! confirm("全額リファンド（返金）します。よろしいですか？") ) {
				return;
			}
			adminOrderEdit.refundSettlementPaidy(amount_original);
		} else {
			var amount = amount_original - amount_change;
			if( ! confirm(amount+"円をリファンド（返金）します。よろしいですか？") ) {
				return;
			}
			adminOrderEdit.refundSettlementPaidy(amount);
		}
	});

	$(document).on( "keydown", ".settlement-amount", function(e) {
		var halfVal = $(this).val().replace(/[！-～]/g,
			function(tmpStr) {
				return String.fromCharCode(tmpStr.charCodeAt(0) - 0xFEE0);
			}
		);
		$(this).val(halfVal.replace(/[^0-9]/g,''));
	});
	$(document).on( "keyup", ".settlement-amount", function() {
		this.value = this.value.replace(/[^0-9]+/i,'');
		this.value = Number(this.value)||0;
	});
	$(document).on( "blur", ".settlement-amount", function() {
		this.value = this.value.replace(/[^0-9]+/i,'');
	});
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

		if ( filter_input( INPUT_POST, 'acting' ) !== $this->paymod_id ) {
			return;
		}

		$this->error_mes = '';
		$payment_method  = usces_get_system_option( 'usces_payment_method', 'settlement' );
		$options         = get_option( 'usces', array() );
		$post_data       = wp_unslash( $_POST );

		unset( $options['acting_settings']['paidy'] );
		$options['acting_settings']['paidy']['paidy_activate']  = ( isset( $post_data['paidy_activate'] ) ) ? $post_data['paidy_activate'] : 'off';
		$options['acting_settings']['paidy']['environment']     = ( isset( $post_data['environment'] ) ) ? $post_data['environment'] : 'live';
		$options['acting_settings']['paidy']['public_key']      = ( isset( $post_data['public_key'] ) ) ? $post_data['public_key'] : '';
		$options['acting_settings']['paidy']['secret_key']      = ( isset( $post_data['secret_key'] ) ) ? $post_data['secret_key'] : '';
		$options['acting_settings']['paidy']['public_key_test'] = ( isset( $post_data['public_key_test'] ) ) ? $post_data['public_key_test'] : '';
		$options['acting_settings']['paidy']['secret_key_test'] = ( isset( $post_data['secret_key_test'] ) ) ? $post_data['secret_key_test'] : '';

		if ( 'on' === $options['acting_settings']['paidy']['paidy_activate'] ) {
			if ( 'live' === $options['acting_settings']['paidy']['environment'] ) {
				if ( WCUtils::is_blank( $options['acting_settings']['paidy']['public_key'] ) ) {
					$this->error_mes .= '※本番用パブリックキーを入力してください<br />';
				}
				if ( WCUtils::is_blank( $options['acting_settings']['paidy']['secret_key'] ) ) {
					$this->error_mes .= '※本番用シークレットキーを入力してください<br />';
				}
			} else {
				if ( WCUtils::is_blank( $options['acting_settings']['paidy']['public_key_test'] ) ) {
					$this->error_mes .= '※テスト用パブリックキーを入力してください<br />';
				}
				if ( WCUtils::is_blank( $options['acting_settings']['paidy']['secret_key_test'] ) ) {
					$this->error_mes .= '※テスト用シークレットキーを入力してください<br />';
				}
			}
		}

		if ( '' === $this->error_mes ) {
			$usces->action_status  = 'success';
			$usces->action_message = __( 'Options are updated.', 'usces' );
			$toactive              = array();
			if ( 'on' === $options['acting_settings']['paidy']['paidy_activate'] ) {
				$usces->payment_structure['acting_paidy'] = 'Paidy';
				foreach ( $payment_method as $settlement => $payment ) {
					if ( 'acting_paidy' === $settlement && 'activate' !== $payment['use'] ) {
						$toactive[] = $payment['name'];
					}
				}
			} else {
				unset( $usces->payment_structure['acting_paidy'] );
			}
			if ( 'on' === $options['acting_settings']['paidy']['paidy_activate'] ) {
				$options['acting_settings']['paidy']['activate'] = 'on';
				usces_admin_orderlist_show_wc_trans_id();
				if ( 0 < count( $toactive ) ) {
					$usces->action_message .= __( 'Please update the payment method to "Activate". <a href="admin.php?page=usces_initial#payment_method_setting">General Setting > Payment Methods</a>', 'usces' );
				}
			} else {
				$options['acting_settings']['paidy']['activate'] = 'off';
				unset( $usces->payment_structure['acting_paidy'] );
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
			$usces->action_status                            = 'error';
			$usces->action_message                           = __( 'Data have deficiency.', 'usces' );
			$options['acting_settings']['paidy']['activate'] = 'off';
			unset( $usces->payment_structure['acting_paidy'] );
			$deactivate = array();
			foreach ( $payment_method as $settlement => $payment ) {
				if ( in_array( $settlement, $this->pay_method, true ) ) {
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
	 * クレジット決済設定画面タブ
	 * usces_action_settlement_tab_title
	 */
	public function settlement_tab_title() {
		$settlement_selected = get_option( 'usces_settlement_selected', array() );
		if ( in_array( $this->paymod_id, $settlement_selected, true ) ) {
			echo '<li><a href="#uscestabs_' . esc_html( $this->paymod_id ) . '">' . esc_html( $this->acting_name ) . '</a></li>';
		}
	}

	/**
	 * クレジット決済設定画面フォーム
	 * usces_action_settlement_tab_body
	 */
	public function settlement_tab_body() {
		$settlement_selected = get_option( 'usces_settlement_selected', array() );
		if ( in_array( $this->paymod_id, $settlement_selected, true ) ) :
			$options        = get_option( 'usces', array() );
			$activate       = ( isset( $options['acting_settings']['paidy']['activate'] ) && 'on' === $options['acting_settings']['paidy']['activate'] ) ? true : false;
			$paidy_activate = ( isset( $options['acting_settings']['paidy']['paidy_activate'] ) && ( 'on' === $options['acting_settings']['paidy']['paidy_activate'] ) ) ? 'on' : 'off';
			$environment    = ( isset( $options['acting_settings']['paidy']['environment'] ) ) ? $options['acting_settings']['paidy']['environment'] : 'live';
			?>
	<div id="uscestabs_paidy">
	<div class="settlement_service"><span class="service_title"><?php echo esc_html( $this->acting_name ); ?></span></div>
			<?php
			if ( filter_input( INPUT_POST, 'acting' ) === $this->paymod_id ) :
				if ( '' !== $this->error_mes ) :
					?>
	<div class="error_message"><?php wel_esc_script_e( $this->error_mes ); ?></div>
					<?php
				elseif ( $activate ) :
					?>
	<div class="message">十分にテストを行ってから運用してください。</div>
					<?php
				endif;
			endif;
			?>
	<form action="" method="post" name="paidy_form" id="paidy_form">
		<table class="settle_table">
			<tr>
				<th><?php echo esc_html( $this->acting_name ); ?></th>
				<td><label><input name="paidy_activate" type="radio" class="paidy_activate" id="paidy_activate_on" value="on"<?php checked( $paidy_activate, 'on' ); ?> /><span>利用する</span></label><br />
					<label><input name="paidy_activate" type="radio" class="paidy_activate" id="paidy_activate_off" value="off"<?php checked( $paidy_activate, 'off' ); ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr class="paidy_form">
				<th>動作環境</th>
				<td><label><input name="environment" type="radio" class="paidy_environment" id="paidy_environment_live" value="live"<?php checked( $environment, 'live' ); ?> /><span>本番環境</span></label><br />
					<label><input name="environment" type="radio" class="paidy_environment" id="paidy_environment_test" value="test"<?php checked( $environment, 'test' ); ?> /><span>テスト環境</span></label>
				</td>
			</tr>
			<tr class="paidy_form">
				<th><a class="explanation-label" id="label_ex_public_key_paidy">本番用パブリックキー</a></th>
				<td><input name="public_key" type="text" id="public_key_paidy" value="<?php echo esc_attr( $options['acting_settings']['paidy']['public_key'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_public_key_paidy" class="explanation paidy_form"><td colspan="2">ペイディ加盟店管理画面の設定より本番用の「パブリックキー」を取得してください。（半角英数字）</td></tr>
			<tr class="paidy_form">
				<th><a class="explanation-label" id="label_ex_secret_key_paidy">本番用シークレットキー</a></th>
				<td><input name="secret_key" type="text" id="secret_key_paidy" value="<?php echo esc_attr( $options['acting_settings']['paidy']['secret_key'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_secret_key_paidy" class="explanation paidy_form"><td colspan="2">ペイディ加盟店管理画面の設定より本番用の「シークレットキー」を取得してください。（半角英数字）</td></tr>
			<tr class="paidy_form">
				<th><a class="explanation-label" id="label_ex_public_key_test_paidy">テスト用パブリックキー</a></th>
				<td><input name="public_key_test" type="text" id="public_key_test_paidy" value="<?php echo esc_attr( $options['acting_settings']['paidy']['public_key_test'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_public_key_test_paidy" class="explanation paidy_form"><td colspan="2">ペイディ加盟店管理画面の設定よりテスト用の「パブリックキー」を取得してください。（半角英数字）</td></tr>
			<tr class="paidy_form">
				<th><a class="explanation-label" id="label_ex_secret_key_test_paidy">テスト用シークレットキー</a></th>
				<td><input name="secret_key_test" type="text" id="secret_key_test_paidy" value="<?php echo esc_attr( $options['acting_settings']['paidy']['secret_key_test'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_secret_key_test_paidy" class="explanation paidy_form"><td colspan="2">ペイディ加盟店管理画面の設定よりテスト用の「シークレットキー」を取得してください。（半角英数字）</td></tr>
		</table>
		<input name="acting" type="hidden" value="paidy" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="<?php echo esc_attr( $this->acting_name ); ?>の設定を更新する" />
			<?php wp_nonce_field( 'admin_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="settle_exp">
		<p><strong>ペイディとは</strong></p>
		<p>クレジットカードが不要なため、クレジットカードを持っていない若年層や、セキュリティが不安でクレジットカードをなるべく使いたくないユーザーも、安心してお買い物を楽しむことができる決済です。</p>
		<p><a href="https://paidy.com/landing/" target="_blank">ペイディの詳細はこちら 》</a></p>
		<p>【ご利用までの流れ】<br>
		お申し込み前に「特定商取引法に基づく表示」と「プライバシーポリシー（個人情報の第三者提供含む）」ページのご準備をお願いいたします。<br>
		<ol>
		<li>加盟店申請：ダッシュボードのペイディ決済らくらく設定、または<a href="https://paidy.com/merchant/application/" target="_blank">ペイディWebサイト</a>からお申し込みください。</li>
		<li>加盟店審査：お申し込みから最大10営業日で、審査結果メールが株式会社Paidyより届きます。添付されている条件通知書で、「契約日」と「経済条件」をご確認ください。</li>
		<li>利用開始 ：
			<ol>
			<li type="a">ペイディ決済らくらく設定から申し込んだ場合、ダッシュボードに表示されるボタンによりご利用開始が可能です。</li>
			<li type="a">ペイディWebサイトから申し込んだ場合、WelcartでAPIキーの設定が完了したら、ご利用開始となります。</li>
			</ol>
		</li>
		</ol>
		</p>
		<p>APIキーの確認方法は、<a href="https://download.paidy.com/merchant/paidy_intro_guide.pdf" target="_blank">ペイディ導入ガイド</a>の Paidy APIキー をご確認ください。</p>
		<p>【資料】<br>
		操作方法などについては下記をご覧ください。<br>
		<a href="https://download.paidy.com/merchant/paidy_intro_guide.pdf" target="_blank">ペイディ導入ガイド</a><br>
		<a href="https://download.paidy.com/merchant/PaidyMerchantWebUserGuide.pdf" target="_blank">ペイディ加盟店管理画面マニュアル</a><br>
		<a href="https://merchant-support.paidy.com/hc/ja" target="_blank">加盟店FAQ</a></p>
	</div>
	</div><!--uscestabs_paidy-->
			<?php
		endif;
	}

	/**
	 * 支払方法追加
	 * usces_filter_add_payment_method
	 *
	 * @param  array $newvalue Payment method data.
	 * @return array
	 */
	public function add_payment_method( $newvalue ) {
		if ( USCES_JP && 'acting_paidy' === $newvalue['settlement'] ) {
			$payment_method = get_option( 'usces_payment_method', array() );
			$unique         = true;
			foreach ( (array) $payment_method as $pm ) {
				if ( $pm['name'] === $this->acting_formal_name ) {
					$unique = false;
				} elseif ( $pm['name'] === $newvalue['name'] ) {
					$unique = false;
				}
			}
			if ( $unique ) {
				$newvalue['name'] = $this->acting_formal_name;
			}
			$newvalue['explanation'] = '<ul>
<li>クレジットカード、事前登録不要。</li>
<li>メールアドレスと携帯番号だけで、今すぐお買い物。</li>
<li>1か月に何度お買い物しても、お支払いは翌月まとめて1回でOK。</li>
<li>お支払いは翌月10日までに、コンビニ払い・銀行振込・口座振替で。</li>
</ul>';
		}
		return $newvalue;
	}

	/**
	 * 支払方法変更
	 * usces_filter_up_payment_method
	 *
	 * @param  array $value Payment method data.
	 * @return array
	 */
	public function up_payment_method( $value ) {
		if ( USCES_JP && 'acting_paidy' === $value['settlement'] ) {
			$value['name'] = $this->acting_formal_name;
		}
		return $value;
	}

	/**
	 * 結果通知処理
	 * usces_after_cart_instant
	 */
	public function acting_transaction() {
		global $usces;

		$post_json = file_get_contents( 'php://input' );
		$post_data = json_decode( $post_json, true );
		if ( isset( $post_data['payment_id'] ) && isset( $post_data['status'] ) && isset( $post_data['order_ref'] ) ) {
			if ( 'authorize_success' === $post_data['status'] ) {
				$order_id = usces_get_order_id_by_trans_id( $post_data['order_ref'] );
				if ( empty( $order_id ) ) {
					// $response_data = $this->api_close( $post_data['payment_id'] );
					$response_data = $this->api_retrieve( $post_data['payment_id'] );
					if ( isset( $response_data['status'] ) && 'authorized' === $response_data['status'] ) {
						if ( ! isset( $response_data['acting'] ) ) {
							$response_data['acting'] = 'paidy';
						}
						$res = $usces->order_processing( $response_data );
					}
				}
			}
			header( 'HTTP/1.0 200 OK' );
			die();
		}
	}

	/**
	 * ポイント即時付与
	 * usces_filter_is_complete_settlement
	 *
	 * @param  boolean $complete Complete the payment.
	 * @param  string  $payment_name Payment name.
	 * @param  string  $status Payment status.
	 * @return boolean
	 */
	public function is_complete_settlement( $complete, $payment_name, $status ) {
		$payment = usces_get_payments_by_name( $payment_name );
		if ( isset( $payment['settlement'] ) && 'acting_paidy' === $payment['settlement'] ) {
			$complete = true;
		}
		return $complete;
	}

	/**
	 * 管理画面決済処理
	 * usces_action_admin_ajax
	 */
	public function admin_ajax() {
		global $usces;

		$mode = filter_input( INPUT_POST, 'mode' );
		$data = array();

		switch ( $mode ) {
			/* 参照 */
			case 'retrieve_paidy':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id = filter_input( INPUT_POST, 'order_id' );
				$trans_id = filter_input( INPUT_POST, 'trans_id' );
				if ( empty( $order_id ) || empty( $trans_id ) ) {
					$data['status'] = 'retrieve_error';
					wp_send_json( $data );
					break;
				}

				$result = '';

				$latest_log    = $this->get_acting_latest_log( $order_id, $trans_id );
				$payment_id    = ( isset( $latest_log['payment_id'] ) ) ? $latest_log['payment_id'] : '';
				$response_data = $this->api_retrieve( $payment_id, true );
				if ( isset( $response_data['status'] ) ) {
					$status = $response_data['status'];
					$amount = ( isset( $response_data['final_amount'] ) ) ? $response_data['final_amount'] : $response_data['amount'];
					if ( ! empty( $response_data['capture_id'] ) && 0 < $amount ) {
						$status = 'capture';
					}
					$class       = ' paidy-' . $status;
					$status_name = $this->get_status_name( $status );
					$result     .= '<div class="paidy-settlement-admin' . $class . '">' . $status_name . '</div>';
					switch ( $status ) {
						case 'authorized':
							$result .= '<table class="settlement-admin-table">';
							$result .= '<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>';
							$result .= '<td><input type="tel" class="settlement-amount" value="' . intval( $amount ) . '" disabled="disabled" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>';
							$result .= '</tr></table>';
							$result .= '<div class="settlement-admin-button">';
							$result .= '<input id="paidy-capture-settlement" type="button" class="button" value="キャプチャー（売上確定）" />';
							$result .= '<input id="paidy-close-settlement" type="button" class="button" value="クローズ" />';
							$result .= '</div>';
							break;
						case 'capture':
							$result .= '<table class="settlement-admin-table">';
							$result .= '<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>';
							$result .= '<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $amount ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $amount ) . '" /></td>';
							$result .= '</tr></table>';
							$result .= '<div class="settlement-admin-button">';
							$result .= '<input id="paidy-refund-settlement" type="button" class="button" value="リファンド（返金）" />';
							$result .= '</div>';
							break;
						case 'closed':
							$result .= '<table class="settlement-admin-table">';
							$result .= '<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>';
							$result .= '<td><input type="tel" class="settlement-amount" value="0" disabled="disabled" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>';
							$result .= '</tr></table>';
							break;
					}
				}
				$result        .= $this->settlement_history( $order_id, $trans_id );
				$data['result'] = $result;
				wp_send_json( $data );
				break;

			/* クローズ */
			case 'close_paidy':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id = filter_input( INPUT_POST, 'order_id' );
				$trans_id = filter_input( INPUT_POST, 'trans_id' );
				if ( empty( $order_id ) || empty( $trans_id ) ) {
					$data['status'] = 'retrieve_error';
					wp_send_json( $data );
					break;
				}

				$result        = '';
				$status        = 'closed';
				$acting_status = '';

				$latest_log    = $this->get_acting_latest_log( $order_id, $trans_id );
				$payment_id    = ( isset( $latest_log['payment_id'] ) ) ? $latest_log['payment_id'] : '';
				$response_data = $this->api_close( $payment_id );
				if ( isset( $response_data['status'] ) && ! isset( $response_data['code'] ) ) {
					$this->save_acting_log( $response_data, 'paidy', $status, 'OK', $order_id, $trans_id );
					$class         = ' paidy-' . $status;
					$status_name   = $this->get_status_name( $status );
					$result       .= '<div class="paidy-settlement-admin' . $class . '">' . $status_name . '</div>';
					$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
					$result       .= '<table class="settlement-admin-table">';
					$result       .= '<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>';
					$result       .= '<td><input type="tel" class="settlement-amount" value="0" disabled="disabled" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>';
					$result       .= '</tr></table>';
				} else {
					$status = 'closed_error';
					$this->save_acting_log( $response_data, 'paidy', $status, 'NG', $order_id, $trans_id );
					$status_name = $this->get_status_name( $status );
					$result     .= '<div class="paidy-settlement-admin paidy-error">' . $status_name . '</div>';
					$amount      = (int) $latest_log['amount'];
					switch ( $latest_log['status'] ) {
						case 'authorized':
							$result .= '<table class="settlement-admin-table">';
							$result .= '<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>';
							$result .= '<td><input type="tel" class="settlement-amount" value="' . intval( $amount ) . '" disabled="disabled" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>';
							$result .= '</tr></table>';
							$result .= '<div class="settlement-admin-button">';
							$result .= '<input id="paidy-capture-settlement" type="button" class="button" value="キャプチャー（売上確定）" />';
							$result .= '<input id="paidy-close-settlement" type="button" class="button" value="クローズ" />';
							$result .= '</div>';
							break;
						case 'capture':
							$result .= '<table class="settlement-admin-table">';
							$result .= '<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>';
							$result .= '<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $amount ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $amount ) . '" /></td>';
							$result .= '</tr></table>';
							$result .= '<div class="settlement-admin-button">';
							$result .= '<input id="paidy-refund-settlement" type="button" class="button" value="リファンド（返金）" />';
							$result .= '</div>';
							break;
					}
				}
				$result               .= $this->settlement_history( $order_id, $trans_id );
				$data['result']        = $result;
				$data['acting_status'] = $acting_status;
				wp_send_json( $data );
				break;

			/* キャプチャー（売上確定） */
			case 'capture_paidy':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id = filter_input( INPUT_POST, 'order_id' );
				$trans_id = filter_input( INPUT_POST, 'trans_id' );
				if ( empty( $order_id ) || empty( $trans_id ) ) {
					$data['status'] = 'retrieve_error';
					wp_send_json( $data );
					break;
				}

				$result        = '';
				$status        = 'capture';
				$acting_status = '';

				$latest_log    = $this->get_acting_latest_log( $order_id, $trans_id );
				$payment_id    = ( isset( $latest_log['payment_id'] ) ) ? $latest_log['payment_id'] : '';
				$response_data = $this->api_capture( $payment_id );
				if ( isset( $response_data['status'] ) && ! isset( $response_data['code'] ) ) {
					$this->save_acting_log( $response_data, 'paidy', $status, 'OK', $order_id, $trans_id );
					$class         = ' paidy-' . $status;
					$status_name   = $this->get_status_name( $status );
					$result       .= '<div class="paidy-settlement-admin' . $class . '">' . $status_name . '</div>';
					$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
					$amount        = (int) $latest_log['amount'];
					$result       .= '<table class="settlement-admin-table">';
					$result       .= '<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>';
					$result       .= '<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $amount ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $amount ) . '" /></td>';
					$result       .= '</tr></table>';
					$result       .= '<div class="settlement-admin-button">';
					$result       .= '<input id="paidy-refund-settlement" type="button" class="button" value="リファンド（返金）" />';
					$result       .= '</div>';
				} else {
					$status = 'capture_error';
					$this->save_acting_log( $response_data, 'paidy', $status, 'NG', $order_id, $trans_id );
					$status_name = $this->get_status_name( $status );
					$amount      = (int) $latest_log['amount'];
					$result     .= '<div class="paidy-settlement-admin paidy-error">' . $status_name . '</div>';
					$result     .= '<table class="settlement-admin-table">';
					$result     .= '<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>';
					$result     .= '<td><input type="tel" class="settlement-amount" value="' . intval( $amount ) . '" disabled="disabled" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>';
					$result     .= '</tr></table>';
					$result     .= '<div class="settlement-admin-button">';
					$result     .= '<input id="paidy-capture-settlement" type="button" class="button" value="キャプチャー（売上確定）" />';
					$result     .= '<input id="paidy-close-settlement" type="button" class="button" value="クローズ" />';
					$result     .= '</div>';
				}
				$result               .= $this->settlement_history( $order_id, $trans_id );
				$data['result']        = $result;
				$data['acting_status'] = $acting_status;
				wp_send_json( $data );
				break;

			/* リファンド（返金） */
			case 'refund_paidy':
				check_admin_referer( 'order_edit', 'wc_nonce' );
				$order_id = filter_input( INPUT_POST, 'order_id' );
				$trans_id = filter_input( INPUT_POST, 'trans_id' );
				$amount   = filter_input( INPUT_POST, 'amount' );
				if ( empty( $order_id ) || empty( $trans_id ) ) {
					$data['status'] = 'retrieve_error';
					wp_send_json( $data );
					break;
				}

				$result        = '';
				$status        = 'refund';
				$acting_status = '';

				$latest_log    = $this->get_acting_latest_log( $order_id, $trans_id );
				$payment_id    = ( isset( $latest_log['payment_id'] ) ) ? $latest_log['payment_id'] : '';
				$retrieve_data = $this->api_retrieve( $payment_id, true );
				if ( isset( $retrieve_data['status'] ) && 'closed' === $retrieve_data['status'] && ! empty( $retrieve_data['capture_id'] ) && ! empty( $retrieve_data['final_amount'] ) ) {
					$response_data = $this->api_refund( $payment_id, $retrieve_data['capture_id'], $amount );
					if ( isset( $response_data['status'] ) && ! isset( $response_data['code'] ) ) {
						$this->save_acting_log( $response_data, 'paidy', $status, 'OK', $order_id, $trans_id, $amount );
						$final_amount = (int) $retrieve_data['final_amount'] - $amount;
						if ( 0 === $final_amount ) {
							$status = 'closed';
							$this->save_acting_log( $response_data, 'paidy', $status, 'OK', $order_id, $trans_id );
						} else {
							$status = 'capture';
						}
						$class         = ' paidy-' . $status;
						$status_name   = $this->get_status_name( $status );
						$result       .= '<div class="paidy-settlement-admin' . $class . '">' . $status_name . '</div>';
						$acting_status = '<span class="acting-status' . $class . '">' . $status_name . '</span>';
					} else {
						$status = 'refund_error';
						$this->save_acting_log( $response_data, 'paidy', $status, 'NG', $order_id, $trans_id );
						$status_name  = $this->get_status_name( $status );
						$result      .= '<div class="paidy-settlement-admin paidy-error">' . $status_name . '</div>';
						$final_amount = (int) $latest_log['amount'];
					}
					switch ( $status ) {
						case 'closed':
							$result .= '<table class="settlement-admin-table">';
							$result .= '<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>';
							$result .= '<td><input type="tel" class="settlement-amount" value="0" disabled="disabled" />' . __( usces_crcode( 'return' ), 'usces' ) . '</td>';
							$result .= '</tr></table>';
							break;
						default:
							$result .= '<table class="settlement-admin-table">';
							$result .= '<tr><th>' . __( 'Settlement amount', 'usces' ) . '</th>';
							$result .= '<td><input type="tel" class="settlement-amount" id="amount_change" value="' . intval( $final_amount ) . '" />' . __( usces_crcode( 'return' ), 'usces' ) . '<input type="hidden" id="amount_original" value="' . intval( $final_amount ) . '" /></td>';
							$result .= '</tr></table>';
							$result .= '<div class="settlement-admin-button">';
							$result .= '<input id="paidy-refund-settlement" type="button" class="button" value="リファンド（返金）" />';
							$result .= '</div>';
					}
				}
				$result               .= $this->settlement_history( $order_id, $trans_id );
				$data['result']        = $result;
				$data['acting_status'] = $acting_status;
				wp_send_json( $data );
				break;
		}
	}

	/**
	 * 決済履歴
	 *
	 * @param  int    $order_id Order number.
	 * @param  string $trans_id Transaction ID.
	 * @return string
	 */
	private function settlement_history( $order_id, $trans_id ) {
		$history  = '';
		$log_data = $this->get_acting_log( $order_id, $trans_id, 'ALL' );
		if ( $log_data ) {
			$num      = count( $log_data );
			$history  = '<table class="settlement-history">';
			$history .= '<thead class="settlement-history-head">';
			$history .= '<tr><th></th><th>' . __( 'Processing date', 'usces' ) . '</th><th>決済ID</th><th>' . __( 'Status', 'usces' ) . '</th><th>' . __( 'Amount', 'usces' ) . '</th><th>' . __( 'Result', 'usces' ) . '</th></tr>';
			$history .= '</thead>';
			$history .= '<tbody class="settlement-history-body">';
			foreach ( (array) $log_data as $data ) {
				$log         = usces_unserialize( $data['log'] );
				$payment_id  = ( isset( $log['id'] ) ) ? $log['id'] : '';
				$status_name = ( isset( $data['status'] ) ) ? $this->get_status_name( $data['status'] ) : '';
				$err_code    = '';
				$class       = '';
				if ( 'OK' === $data['result'] && 0 < $data['amount'] ) {
					$amount = usces_crform( $data['amount'], false, true, 'return', true );
				} else {
					$amount = '';
				}
				if ( 'OK' !== $data['result'] ) {
					$err_code = ( isset( $log['code'] ) ) ? $log['code'] : '';
					$class    = ' error';
				}
				$history .= '<tr>';
				$history .= '<td class="num">' . $num . '</td>';
				$history .= '<td class="datetime">' . $data['datetime'] . '</td>';
				$history .= '<td class="transactionid">' . $payment_id . '</td>';
				$history .= '<td class="status">' . $status_name . '</td>';
				$history .= '<td class="amount">' . $amount . '</td>';
				$history .= '<td class="result' . $class . '">' . $err_code . '</td>';
				$history .= '</tr>';
				$num--;
			}
			$history .= '</tbody>';
			$history .= '</table>';
		}
		return $history;
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
		if ( 'wc_trans_id' !== $key || empty( $value ) ) {
			return $detail;
		}

		$acting_flg = $this->get_order_acting_flg( $order_id );
		if ( 'acting_paidy' === $acting_flg ) {
			$trans_id       = $this->get_trans_id( $order_id );
			$payment_status = $this->get_payment_status( $order_id, $trans_id );
			if ( ! empty( $payment_status ) ) {
				$class       = ' paidy-' . $this->get_status( $payment_status );
				$status_name = $this->get_status_name( $payment_status );
				if ( ! empty( $status_name ) ) {
					$detail = '<td>' . esc_html( $value ) . '<span class="acting-status' . esc_html( $class ) . '">' . esc_html( $status_name ) . '</span></td>';
				}
			}
		}
		return $detail;
	}

	/**
	 * 受注編集画面【ステータス】
	 * usces_action_order_edit_form_status_block_middle
	 *
	 * @param  array $data Order data.
	 * @param  array $cscs_meta Custom field data.
	 * @param  array $action_args Compact array( 'order_action', 'order_id', 'cart' ).
	 */
	public function settlement_status( $data, $cscs_meta, $action_args ) {
		extract( $action_args );

		if ( 'new' !== $order_action && ! empty( $order_id ) ) {
			$payment    = usces_get_payments_by_name( $data['order_payment_name'] );
			$acting_flg = ( isset( $payment['settlement'] ) ) ? $payment['settlement'] : '';
			if ( 'acting_paidy' === $acting_flg ) {
				$trans_id       = $this->get_trans_id( $order_id );
				$payment_status = $this->get_payment_status( $order_id, $trans_id );
				if ( ! empty( $payment_status ) ) {
					$class       = ' paidy-' . $this->get_status( $payment_status );
					$status_name = $this->get_status_name( $payment_status );
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
	 * @param  array $data Order data.
	 * @param  array $action_args Compact array( 'order_action', 'order_id', 'cart' ).
	 */
	public function settlement_information( $data, $action_args ) {
		global $usces;
		extract( $action_args );

		if ( 'new' !== $order_action && ! empty( $order_id ) ) {
			$payment = usces_get_payments_by_name( $data['order_payment_name'] );
			if ( isset( $payment['settlement'] ) && 'acting_paidy' === $payment['settlement'] ) {
				$trans_id    = $this->get_trans_id( $order_id );
				$acting_data = usces_unserialize( $usces->get_order_meta_value( $payment['settlement'], $order_id ) );
				if ( ! empty( $trans_id ) && isset( $acting_data['id'] ) ) {
					echo '<input type="button" class="button settlement-information" id="settlement-information-' . esc_html( $trans_id ) . '" data-trans_id="' . esc_attr( $trans_id ) . '" data-num="1" value="' . esc_attr__( 'Settlement info', 'usces' ) . '">';
				}
			}
		}
	}

	/**
	 * 決済情報ダイアログ
	 * usces_action_endof_order_edit_form
	 *
	 * @param  array $data Order data.
	 * @param  array $action_args Compact array( 'order_action', 'order_id', 'cart' ).
	 */
	public function settlement_dialog( $data, $action_args ) {
		extract( $action_args );

		if ( 'new' !== $order_action && ! empty( $order_id ) ) :
			$payment = usces_get_payments_by_name( $data['order_payment_name'] );
			if ( isset( $payment['settlement'] ) && 'acting_paidy' === $payment['settlement'] ) :
				?>
<div id="settlement_dialog" title="">
	<div id="settlement-response-loading"></div>
	<fieldset>
	<div id="settlement-response"></div>
	<input type="hidden" id="order_num">
	<input type="hidden" id="trans_id">
	<input type="hidden" id="acting" value="acting_paidy">
	<input type="hidden" id="error">
	</fieldset>
</div>
				<?php
			endif;
		endif;
	}

	/**
	 * 受注データから取得する決済情報のキー
	 * usces_filter_settle_info_field_meta_keys
	 *
	 * @param  array $keys Settlement information key.
	 * @return array
	 */
	public function settlement_info_field_meta_keys( $keys ) {
		$keys = array_merge( $keys, array( 'acting', 'trans_id', 'trading_id' ) );
		return $keys;
	}

	/**
	 * 受注編集画面に表示する決済情報のキー
	 * usces_filter_settle_info_field_keys
	 *
	 * @param  array $keys Settlement information keys.
	 * @param  array $fields Settlement information fields.
	 * @return array
	 */
	public function settlement_info_field_keys( $keys, $fields ) {
		if ( isset( $fields['acting'] ) && 'paidy' === $fields['acting'] ) {
			$keys = array( 'acting', 'trans_id', 'trading_id' );
		}
		return $keys;
	}

	/**
	 * 受注編集画面に表示する決済情報の値整形
	 * usces_filter_settle_info_field_value
	 *
	 * @param  string $value Value.
	 * @param  string $key Key.
	 * @param  string $acting Acting type.
	 * @return string
	 */
	public function settlement_info_field_value( $value, $key, $acting ) {
		if ( 'acting' === $key && 'paidy' === $value ) {
			$value = $this->acting_name;
		}
		return $value;
	}

	/**
	 * 決済リンクキー
	 * usces_filter_get_link_key
	 *
	 * @param  string $linkkey Settlement link key.
	 * @param  array  $results Response data.
	 * @return string
	 */
	public function get_link_key( $linkkey, $results ) {
		if ( isset( $results['trans_id'] ) ) {
			$linkkey = $results['trans_id'];
		} elseif ( isset( $results['trading_id'] ) ) {
			$linkkey = $results['trading_id'];
		}
		return $linkkey;
	}

	/**
	 * 受注データ復旧処理
	 * usces_action_revival_order_data
	 *
	 * @param  int    $order_id Order number.
	 * @param  string $log_key Link key.
	 * @param  string $acting_flg Payment type.
	 */
	public function revival_orderdata( $order_id, $log_key, $acting_flg ) {
		global $usces;

		if ( 'acting_paidy' === $acting_flg ) {
			$usces->set_order_meta_value( 'trans_id', $log_key, $order_id );
			$usces->set_order_meta_value( 'wc_trans_id', $log_key, $order_id );
			$data = array(
				'trans_id' => $log_key,
			);
			$usces->set_order_meta_value( $acting_flg, usces_serialize( $data ), $order_id );
		}
	}

	/**
	 * 内容確認ページ [注文する] ボタン
	 * usces_filter_confirm_inform
	 *
	 * @param  string $form Purchase post form.
	 * @param  array  $payments Payment data.
	 * @param  string $acting_flg Payment type.
	 * @param  string $rand Welcart transaction key.
	 * @param  string $purchase_disabled Disable purchase button.
	 * @return string
	 */
	public function confirm_inform( $form, $payments, $acting_flg, $rand, $purchase_disabled ) {
		global $usces;

		if ( 'acting_paidy' !== $acting_flg ) {
			return $form;
		}

		$entry = $usces->cart->get_entry();
		$cart  = $usces->cart->get_cart();
		if ( ! $entry || ! $cart ) {
			return $form;
		}
		if ( ! $entry['order']['total_full_price'] ) {
			return $form;
		}

		usces_save_order_acting_data( $rand );
		$form = '<form name="purchase_form" action="' . USCES_CART_URL . '" method="post" onKeyDown="if(event.keyCode == 13){return false;}">
				<input type="hidden" name="purchase" value="' . $acting_flg . '">
				<input type="hidden" name="trans_id" value="' . $rand . '">
				<input type="hidden" name="paidy_id">
				<input type="hidden" name="paidy_created_at">
				<input type="hidden" name="paidy_status">
				<input type="hidden" name="_nonce" value="' . wp_create_nonce( $acting_flg ) . '">
				<div class="send paidy-send"><input type="button" id="paidy-checkout-button" onclick="paidyPay()" value="ペイディでお支払い" /></div>
			</form>
			<form action="' . USCES_CART_URL . '" method="post" onKeyDown="if(event.keyCode == 13){return false;}">
				<div class="send">
					' . apply_filters( 'usces_filter_confirm_before_backbutton', '', $payments, $acting_flg, $rand ) . '
					<input name="backDelivery" type="submit" id="back_button" class="back_to_delivery_button" value="' . __( 'Back', 'usces' ) . '"' . apply_filters( 'usces_filter_confirm_prebutton', '' ) . ' />
				</div>';
		return $form;
	}

	/**
	 * 決済処理
	 * usces_action_acting_processing
	 *
	 * @param  string $acting_flg Payment type.
	 * @param  array  $post_query Post data.
	 */
	public function acting_processing( $acting_flg, $post_query ) {
		global $usces;

		if ( 'acting_paidy' !== $acting_flg ) {
			return;
		}

		$entry = $usces->cart->get_entry();
		$cart  = $usces->cart->get_cart();
		if ( ! $entry || ! $cart ) {
			wp_redirect( USCES_CART_URL );
			exit();
		}

		parse_str( $post_query, $post_data );
		if ( isset( $post_data['_nonce'] ) && ! wp_verify_nonce( $post_data['_nonce'], $acting_flg ) ) {
			wp_redirect( USCES_CART_URL );
			exit();
		}

		if ( isset( $post_data['paidy_id'] ) && isset( $post_data['paidy_created_at'] ) && isset( $post_data['paidy_status'] ) && 'authorized' === $post_data['paidy_status'] ) {
			$acting        = substr( $acting_flg, 7 );
			$trans_id      = ( isset( $post_data['trans_id'] ) ) ? $post_data['trans_id'] : '';
			$response_data = $this->api_retrieve( $post_data['paidy_id'] );
			if ( isset( $response_data['status'] ) && 'authorized' === $response_data['status'] ) {
				$res = $usces->order_processing( $response_data );
				if ( 'ordercompletion' === $res ) {
					wp_redirect(
						add_query_arg(
							array(
								'acting'        => $acting,
								'acting_return' => 1,
								'result'        => 1,
								'_nonce'        => $post_data['_nonce'],
							),
							USCES_CART_URL
						)
					);
				} else {
					$log = array(
						'acting' => $acting,
						'key'    => $trans_id,
						'result' => 'ORDER DATA REGISTERED ERROR',
						'data'   => $response_data,
					);
					usces_save_order_acting_error( $log );
					wp_redirect(
						add_query_arg(
							array(
								'acting'        => $acting,
								'acting_return' => 0,
								'result'        => 0,
							),
							USCES_CART_URL
						)
					);
				}
			} else {
				$log = array(
					'acting' => $acting,
					'key'    => $trans_id,
					'result' => 'PAIDY AUTHORIZE ERROR',
					'data'   => $response_data,
				);
				usces_save_order_acting_error( $log );
				wp_redirect(
					add_query_arg(
						array(
							'acting'        => $acting,
							'acting_return' => 0,
							'result'        => 0,
						),
						USCES_CART_URL
					)
				);
			}
			exit();
		} elseif ( isset( $post_data['paidy_id'] ) && isset( $post_data['paidy_created_at'] ) && isset( $post_data['paidy_status'] ) && 'rejected' === $post_data['paidy_status'] ) {
			$acting   = substr( $acting_flg, 7 );
			$trans_id = ( isset( $post_data['trans_id'] ) ) ? $post_data['trans_id'] : '';
			$log      = array(
				'acting' => $acting,
				'key'    => $trans_id,
				'result' => 'PAIDY REJECTED',
				'data'   => $post_data,
			);
			usces_save_order_acting_error( $log );
			wp_redirect(
				add_query_arg(
					array(
						'acting'        => $acting,
						'acting_return' => 0,
						'result'        => 0,
					),
					USCES_CART_URL
				)
			);
			exit();
		}
	}

	/**
	 * 決済完了ページ制御
	 * usces_filter_check_acting_return_results
	 *
	 * @param  array $results Result data.
	 * @return array
	 */
	public function acting_return( $results ) {
		$acting = filter_input( INPUT_GET, 'acting' );
		if ( 'paidy' === $acting ) {
			$results              = wp_unslash( $_GET );
			$results[0]           = filter_input( INPUT_GET, 'acting_return' );
			$results['reg_order'] = false;
		}
		return $results;
	}

	/**
	 * 重複オーダー禁止処理
	 * usces_filter_check_acting_return_duplicate
	 *
	 * @param  string $trans_id Transaction ID.
	 * @param  array  $results Result data.
	 * @return string
	 */
	public function check_acting_return_duplicate( $trans_id, $results ) {
		if ( isset( $_POST['paidy_id'] ) || ( isset( $results['acting'] ) && 'paidy' === $results['acting'] ) ) {
			if ( isset( $_POST['trans_id'] ) ) {
				$trans_id = filter_input( INPUT_POST, 'trans_id' );
			} elseif ( isset( $results['order']['order_ref'] ) ) {
				$trans_id = $results['order']['order_ref'];
			}
		}
		return $trans_id;
	}

	/**
	 * 受注データ登録
	 * Called by usces_reg_orderdata() and usces_new_orderdata().
	 * usces_action_reg_orderdata
	 *
	 * @param  array $args Compact array( $cart, $entry, $order_id, $member_id, $payments, $charging_type, $results ).
	 */
	public function register_orderdata( $args ) {
		global $usces;
		extract( $args );

		$acting_flg = ( isset( $payments['settlement'] ) ) ? $payments['settlement'] : '';
		if ( 'acting_paidy' !== $acting_flg ) {
			return;
		}
		if ( ! $entry['order']['total_full_price'] ) {
			return;
		}

		$trans_id = '';
		if ( isset( $results['order']['order_ref'] ) ) {
			$trans_id = $results['order']['order_ref'];
		} elseif ( isset( $results['order_ref'] ) ) {
			$trans_id = $results['order_ref'];
		}
		if ( ! empty( $trans_id ) ) {
			if ( ! isset( $results['acting'] ) ) {
				$results['acting'] = 'paidy';
			}
			$usces->set_order_meta_value( $acting_flg, usces_serialize( $results ), $order_id );
			$usces->set_order_meta_value( 'trans_id', $trans_id, $order_id );
			$usces->set_order_meta_value( 'wc_trans_id', $trans_id, $order_id );
			$this->save_acting_log( $results, 'paidy', $results['status'], 'OK', $order_id, $trans_id );
		}
	}

	/**
	 * 決済エラーメッセージ
	 * usces_filter_get_error_settlement
	 *
	 * @param  string $form Payment error message.
	 * @return string
	 */
	public function error_page_message( $form ) {
		$acting = ( isset( $_REQUEST['acting'] ) ) ? wp_unslash( $_REQUEST['acting'] ) : '';
		if ( 'paidy' === $acting ) {
			$form .= '<br />
			<a href="' . USCES_CUSTOMER_URL . '">他の支払方法を選ぶ 》</a><br />';
		}
		return $form;
	}

	/**
	 * Front scripts.
	 * wp_print_footer_scripts
	 */
	public function footer_scripts() {
		global $usces;

		if ( $this->is_validity_acting( 'paidy' ) ) :
			/* 内容確認ページ */
			if ( $usces->is_cart_page( $_SERVER['REQUEST_URI'] ) && 'confirm' === $usces->page ) :
				$entry = $usces->cart->get_entry();
				$cart  = $usces->cart->get_cart();
				if ( empty( $entry['order']['total_full_price'] ) ) {
					return;
				}
				$payment = usces_get_payments_by_name( $entry['order']['payment_name'] );
				if ( empty( $payment['settlement'] ) || 'acting_paidy' !== $payment['settlement'] ) {
					return;
				}

				$continue = ( defined( 'WCEX_DLSELLER' ) ) ? usces_have_continue_charge( $cart ) : false;
				$regular  = ( defined( 'WCEX_AUTO_DELIVERY' ) ) ? usces_have_regular_order() : false;
				if ( $continue || $regular ) {
					return;
				}

				$acting_opts = $this->get_acting_settings();
				if ( usces_is_login() ) {
					$member           = $usces->get_member();
					$age              = ceil( ( strtotime( date_i18n( 'Y-m-d H:i:s' ) ) - strtotime( $member['registered'] ) ) / ( 60 * 60 * 24 ) );
					$member_data      = $this->get_buyer_data( $member['ID'] );
					$buyer_data       = '"user_id": "' . $member['ID'] . '",' . "\n";
					$buyer_data      .= "\t\t\t" . '"age": ' . $age . ',' . "\n";
					$buyer_data      .= "\t\t\t" . '"ltv": ' . $member_data['ltv'] . ',' . "\n";
					$buyer_data      .= "\t\t\t" . '"order_count": ' . $member_data['order_count'] . ',' . "\n";
					$buyer_data      .= "\t\t\t" . '"last_order_amount": ' . $member_data['last_order_amount'] . ',' . "\n";
					$buyer_data      .= "\t\t\t" . '"last_order_at": ' . $member_data['last_order_at'] . ',' . "\n";
					$number_of_points = '"number_of_points": "' . $member['point'] . '"' . "\n";
				} else {
					$buyer_data       = '"age": null,' . "\n";
					$buyer_data      .= "\t\t\t" . '"ltv": null,' . "\n";
					$buyer_data      .= "\t\t\t" . '"order_count": null,' . "\n";
					$buyer_data      .= "\t\t\t" . '"last_order_amount": null,' . "\n";
					$buyer_data      .= "\t\t\t" . '"last_order_at": null,' . "\n";
					$number_of_points = '';
				}
				$amount = usces_crform( $entry['order']['total_full_price'], false, false, 'return', false );
				// if ( isset( $acting_opts['environment'] ) && 'live' === $acting_opts['environment'] ) {
				$email = trim( $entry['customer']['mailaddress1'] );
				if ( ! empty( $entry['customer']['tel'] ) ) {
					$phone = '"phone": "' . str_replace( '-', '', mb_convert_kana( $entry['customer']['tel'], 'a', 'UTF-8' ) ) . '"' . "\n";
				} else {
					$phone = '';
				}
				// } else {
				// 	$email = 'successful.payment@paidy.com';
				// 	$phone = '"phone": "08000000001"' . "\n";
				// }
				$name1 = $entry['customer']['name1'] . ' ' . $entry['customer']['name2'];
				if ( ! empty( $entry['customer']['name3'] ) ) {
					$name2 = '"name2": "' . $entry['customer']['name3'] . ' ' . $entry['customer']['name4'] . '",' . "\n";
				} else {
					$name2 = '';
				}
				$line1                         = '';
				$line2                         = '';
				$city                          = '';
				$state                         = '';
				$zip                           = '';
				$additional_shipping_addresses = '';
				$billing_address               = '';
				if ( usces_is_login() && defined( 'WCEX_MSA' ) && isset( $entry['delivery']['delivery_flag'] ) && 2 === (int) $entry['delivery']['delivery_flag'] ) {
					$msacart   = $usces->msacart->get_cart();
					$count_msa = ( is_array( $msacart ) ) ? count( $msacart ) : 0;
					if ( 0 < $count_msa ) {
						krsort( $msacart );
						$idx_msa = 0;
						foreach ( $msacart as $group_id => $group ) {
							$delivery         = $group['delivery'];
							$destination_info = msa_get_destination( $usces->current_member['id'], $delivery['destination_id'] );
							if ( 7 === strlen( trim( $destination_info['msa_zip'] ) ) ) {
								$msa_zip = substr( trim( $destination_info['msa_zip'] ), 0, 3 ) . '-' . substr( trim( $destination_info['msa_zip'] ), 3 );
							} else {
								$msa_zip = trim( $destination_info['msa_zip'] );
							}
							if ( 0 === $idx_msa ) {
								$line1 = trim( $destination_info['msa_address3'] );
								$line2 = trim( $destination_info['msa_address2'] );
								$city  = trim( $destination_info['msa_address1'] );
								$state = trim( $destination_info['msa_pref'] );
								$zip   = $msa_zip;
							} elseif ( 1 === $idx_msa ) {
								$additional_shipping_addresses .= '"additional_shipping_addresses": [{' . "\n";
								$additional_shipping_addresses .= "\t\t\t\t" . '"line1": "' . trim( $destination_info['msa_address3'] ) . '",' . "\n";
								$additional_shipping_addresses .= "\t\t\t\t" . '"line2": "' . trim( $destination_info['msa_address2'] ) . '",' . "\n";
								$additional_shipping_addresses .= "\t\t\t\t" . '"city": "' . trim( $destination_info['msa_address1'] ) . '",' . "\n";
								$additional_shipping_addresses .= "\t\t\t\t" . '"state": "' . trim( $destination_info['msa_pref'] ) . '",' . "\n";
								$additional_shipping_addresses .= "\t\t\t\t" . '"zip": "' . $msa_zip . '"' . "\n";
								$additional_shipping_addresses .= "\t\t\t" . '}';
							} else {
								$additional_shipping_addresses .= ',' . "\n\t\t\t" . '{' . "\n";
								$additional_shipping_addresses .= "\t\t\t\t" . '"line1": "' . trim( $destination_info['msa_address3'] ) . '",' . "\n";
								$additional_shipping_addresses .= "\t\t\t\t" . '"line2": "' . trim( $destination_info['msa_address2'] ) . '",' . "\n";
								$additional_shipping_addresses .= "\t\t\t\t" . '"city": "' . trim( $destination_info['msa_address1'] ) . '",' . "\n";
								$additional_shipping_addresses .= "\t\t\t\t" . '"state": "' . trim( $destination_info['msa_pref'] ) . '",' . "\n";
								$additional_shipping_addresses .= "\t\t\t\t" . '"zip": "' . $msa_zip . '"' . "\n";
								$additional_shipping_addresses .= "\t\t\t" . '}';
							}
							$idx_msa++;
						}
						$additional_shipping_addresses .= '],' . "\n";
					}
				} else {
					$line1 = trim( $entry['delivery']['address3'] );
					$line2 = trim( $entry['delivery']['address2'] );
					$city  = trim( $entry['delivery']['address1'] );
					$state = trim( $entry['delivery']['pref'] );
					if ( 7 === strlen( trim( $entry['delivery']['zipcode'] ) ) ) {
						$zip = substr( trim( $entry['delivery']['zipcode'] ), 0, 3 ) . '-' . substr( trim( $entry['delivery']['zipcode'] ), 3 );
					} else {
						$zip = trim( $entry['delivery']['zipcode'] );
					}
					if ( isset( $entry['delivery']['delivery_flag'] ) && 1 === (int) $entry['delivery']['delivery_flag'] ) {
						$customer_line1 = trim( $entry['customer']['address3'] );
						$customer_line2 = trim( $entry['customer']['address2'] );
						$customer_city  = trim( $entry['customer']['address1'] );
						$customer_state = trim( $entry['customer']['pref'] );
						if ( 7 === strlen( trim( $entry['customer']['zipcode'] ) ) ) {
							$customer_zip = substr( trim( $entry['customer']['zipcode'] ), 0, 3 ) . '-' . substr( trim( $entry['customer']['zipcode'] ), 3 );
						} else {
							$customer_zip = trim( $entry['customer']['zipcode'] );
						}
						if ( $line1 !== $customer_line1 || $line2 !== $customer_line2 || $city !== $customer_city || $state !== $customer_state || $zip !== $customer_zip ) {
							$billing_address .= ',"delivery_locn_type": "not_primary_home",' . "\n";
							$billing_address .= "\t\t\t" . '"billing_address": {' . "\n";
							$billing_address .= "\t\t\t\t" . '"line1": "' . $customer_line1 . '",' . "\n";
							$billing_address .= "\t\t\t\t" . '"line2": "' . $customer_line2 . '",' . "\n";
							$billing_address .= "\t\t\t\t" . '"city": "' . $customer_city . '",' . "\n";
							$billing_address .= "\t\t\t\t" . '"state": "' . $customer_state . '",' . "\n";
							$billing_address .= "\t\t\t\t" . '"zip": "' . $customer_zip . '"' . "\n";
							$billing_address .= "\t\t\t" . '}' . "\n";
						}
					}
				}
				$items = '';
				foreach ( $cart as $cart_row ) {
					$items .= "\n\t\t\t\t" . '{' . "\n";
					$items .= "\t\t\t\t\t" . '"id": "' . $usces->getItemCode( $cart_row['post_id'] ) . '",' . "\n";
					$items .= "\t\t\t\t\t" . '"quantity": ' . $cart_row['quantity'] . ',' . "\n";
					$items .= "\t\t\t\t\t" . '"title": "' . $usces->getItemName( $cart_row['post_id'] ) . '",' . "\n";
					$items .= "\t\t\t\t\t" . '"unit_price": ' . $cart_row['price'] . "\n";
					$items .= "\t\t\t\t" . '},';
				}
				if ( isset( $entry['order']['discount'] ) && 0 !== $entry['order']['discount'] ) {
					$items .= "\n\t\t\t\t" . '{' . "\n";
					$items .= "\t\t\t\t\t" . '"quantity": 1,' . "\n";
					$items .= "\t\t\t\t\t" . '"title": "' . apply_filters( 'usces_confirm_discount_label', __( 'Campaign discount', 'usces' ) ) . '",' . "\n";
					$items .= "\t\t\t\t\t" . '"unit_price": ' . $entry['order']['discount'] . "\n";
					$items .= "\t\t\t\t" . '},';
				}
				if ( usces_is_member_system() && usces_is_member_system_point() && ! empty( $entry['order']['usedpoint'] ) ) {
					$items .= "\n\t\t\t\t" . '{' . "\n";
					$items .= "\t\t\t\t\t" . '"quantity": 1,' . "\n";
					$items .= "\t\t\t\t\t" . '"title": "' . __( 'Used points', 'usces' ) . '",' . "\n";
					$items .= "\t\t\t\t\t" . '"unit_price": ' . ( $entry['order']['usedpoint'] * -1 ) . "\n";
					$items .= "\t\t\t\t" . '},';
				}
				if ( ! empty( $entry['order']['cod_fee'] ) ) {
					$items .= "\n\t\t\t\t" . '{' . "\n";
					$items .= "\t\t\t\t\t" . '"quantity": 1,' . "\n";
					$items .= "\t\t\t\t\t" . '"title": "' . apply_filters( 'usces_filter_paidy_fee_label', 'ペイディ手数料' ) . '",' . "\n";
					$items .= "\t\t\t\t\t" . '"unit_price": ' . $entry['order']['cod_fee'] . "\n";
					$items .= "\t\t\t\t" . '},';
				}
				$items = rtrim( $items, ',' ) . "\n";
				if ( 0 !== $entry['order']['shipping_charge'] ) {
					$shipping = $entry['order']['shipping_charge'];
				} else {
					$shipping = '0';
				}
				if ( isset( $entry['order']['tax'] ) && 'exclude' === usces_get_tax_mode() ) {
					$tax = $entry['order']['tax'] . "\n";
				} else {
					$tax = '0' . "\n";
				}
				?>
<script type="text/javascript" src="<?php echo esc_url( $acting_opts['apps_url'] ); ?>"></script>
<script type="text/javascript">
var config = {
	"api_key": "<?php echo esc_html( $acting_opts['public_key'] ); ?>",
	"closed": function(callbackData) {
		if( 'closed' == callbackData.status ) {
			document.getElementById("paidy-checkout-button").style.pointerEvents = "auto";
		} else {
			var purchase_form = document.forms.purchase_form;
			purchase_form.paidy_id.value = callbackData.id;
			purchase_form.paidy_created_at.value = callbackData.created_at;
			purchase_form.paidy_status.value = callbackData.status;
			purchase_form.submit();
		}
	}
};
var paidyHandler = Paidy.configure(config);
function paidyPay() {
	document.getElementById("paidy-checkout-button").style.pointerEvents = "none";
	var rand    = document.getElementsByName("trans_id")[0].value;
	var payload = {
		"amount": <?php echo esc_js( $amount ); ?>,
		"currency": "JPY",
		"store_name": "<?php echo esc_html( get_option( 'blogname' ) ); ?>",
		"buyer": {
			"email": "<?php echo esc_js( $email ); ?>",
			"name1": "<?php echo esc_js( $name1 ); ?>",
			<?php echo $name2; // no escape. ?>
			<?php echo $phone; // no escape. ?>
		},
		"buyer_data": {
			<?php echo $buyer_data; // no escape. ?>
			<?php echo $additional_shipping_addresses; // no escape. ?>
			<?php echo $number_of_points; // no escape. ?>
			<?php echo $billing_address; // no escape. ?>
		},
		"order": {
			"items": [
				<?php echo $items; // no escape. ?>
			],
			"order_ref": rand,
			"shipping": <?php echo $shipping; // no escape. ?>,
			"tax": <?php echo $tax; // no escape. ?>
		},
		"shipping_address": {
			"line1": "<?php echo esc_js( $line1 ); ?>",
			"line2": "<?php echo esc_js( $line2 ); ?>",
			"city": "<?php echo esc_js( $city ); ?>",
			"state": "<?php echo esc_js( $state ); ?>",
			"zip": "<?php echo esc_js( $zip ); ?>"
		},
		"metadata": {
			"Platform": "Welcart"
		}
	};
	paidyHandler.launch(payload);
};
</script>
				<?php
			endif;
		endif;
	}

	/**
	 * Paidy Checkout Buyer data オブジェクト
	 *
	 * @param  int $member_id Post ID.
	 * @return array
	 */
	private function get_buyer_data( $member_id ) {
		global $wpdb;

		$ltv               = 0;
		$order_count       = 0;
		$last_order_amount = 0;
		$last_order_at     = 0;

		$query   = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}usces_order WHERE `mem_id` = %d ORDER BY `order_date` DESC", $member_id );
		$results = $wpdb->get_results( $query, ARRAY_A );
		if ( 0 < count( $results ) ) {
			foreach ( $results as $order ) {
				if ( false === strpos( $order['order_status'], 'cancel' ) && false === strpos( $order['order_status'], 'estimate' ) ) {
					$payment = usces_get_payments_by_name( $order['order_payment_name'] );
					if ( isset( $payment['settlement'] ) && 'acting_paidy' !== $payment['settlement'] ) {
						$total_price = $order['order_item_total_price'] - $order['order_usedpoint'] + $order['order_discount'] + $order['order_shipping_charge'] + $order['order_cod_fee'] + $order['order_tax'];
						if ( 0 === $order_count ) {
							$last_order_amount = $total_price;
							$last_order_at     = ceil( ( strtotime( date_i18n( 'Y-m-d H:i:s' ) ) - strtotime( $order['order_date'] ) ) / ( 60 * 60 * 24 ) );
						}
						$ltv += $total_price;
						$order_count++;
					}
				}
			}
		}
		$member_data = array(
			'ID'                => $member_id,
			'ltv'               => $ltv,
			'order_count'       => $order_count,
			'last_order_amount' => $last_order_amount,
			'last_order_at'     => $last_order_at,
		);

		return $member_data;
	}

	/**
	 * 決済オプション取得
	 *
	 * @return array
	 */
	protected function get_acting_settings() {
		$options         = get_option( 'usces', array() );
		$acting_settings = array();
		if ( isset( $options['acting_settings']['paidy'] ) ) {
			$environment                 = ( ! empty( $options['acting_settings']['paidy']['environment'] ) ) ? $options['acting_settings']['paidy']['environment'] : 'live';
			$acting_settings['api_url']  = 'https://api.paidy.com/payments/';
			$acting_settings['apps_url'] = 'https://apps.paidy.com/';
			if ( 'live' === $environment ) {
				$acting_settings['send_url']   = 'https://link.paidy.co.jp/v/u/request';
				$acting_settings['token_url']  = 'https://token.paidy.co.jp/js/PaygentToken.js';
				$acting_settings['public_key'] = ( ! empty( $options['acting_settings']['paidy']['public_key'] ) ) ? $options['acting_settings']['paidy']['public_key'] : '';
				$acting_settings['secret_key'] = ( ! empty( $options['acting_settings']['paidy']['secret_key'] ) ) ? $options['acting_settings']['paidy']['secret_key'] : '';
			} else {
				$acting_settings['send_url']   = 'https://sandbox.paidy.co.jp/v/u/request';
				$acting_settings['token_url']  = 'https://sandbox.paidy.co.jp/js/PaygentToken.js';
				$acting_settings['public_key'] = ( ! empty( $options['acting_settings']['paidy']['public_key_test'] ) ) ? $options['acting_settings']['paidy']['public_key_test'] : '';
				$acting_settings['secret_key'] = ( ! empty( $options['acting_settings']['paidy']['secret_key_test'] ) ) ? $options['acting_settings']['paidy']['secret_key_test'] : '';
			}
		}
		return $acting_settings;
	}

	/**
	 * 受注ID 取得
	 *
	 * @param  string $trans_id Transaction ID.
	 * @return int
	 */
	protected function get_order_id( $trans_id ) {
		global $wpdb;

		$query    = $wpdb->prepare( "SELECT `order_id` FROM {$wpdb->prefix}usces_order_meta WHERE `meta_key` = %s AND `meta_value` = %s", 'trans_id', $trans_id );
		$order_id = $wpdb->get_var( $query );
		return $order_id;
	}

	/**
	 * 取引ID 取得
	 *
	 * @param  int $order_id Order number.
	 * @return string
	 */
	protected function get_trans_id( $order_id ) {
		global $usces;

		$trans_id = $usces->get_order_meta_value( 'trans_id', $order_id );
		if ( empty( $trans_id ) ) {
			$trans_id = $usces->get_order_meta_value( 'trading_id', $order_id );
		}
		return $trans_id;
	}

	/**
	 * 受注ステータス取得
	 *
	 * @param  int $order_id Order number.
	 * @return string
	 */
	protected function get_order_status( $order_id ) {
		global $wpdb;

		$query        = $wpdb->prepare( "SELECT LOCATE( 'receipted', `order_status` ) FROM {$wpdb->prefix}usces_order WHERE `ID` = %d", $order_id );
		$order_status = $wpdb->get_var( $query );
		return $order_status;
	}

	/**
	 * 受注データ支払方法取得
	 *
	 * @param  int $order_id Order number.
	 * @return string
	 */
	protected function get_order_acting_flg( $order_id ) {
		global $wpdb;

		$query              = $wpdb->prepare( "SELECT `order_payment_name` FROM {$wpdb->prefix}usces_order WHERE `ID` = %d", $order_id );
		$order_payment_name = $wpdb->get_var( $query );
		$payment            = usces_get_payments_by_name( $order_payment_name );
		$acting_flg         = ( isset( $payment['settlement'] ) ) ? $payment['settlement'] : '';
		return $acting_flg;
	}

	/**
	 * 決済ログ取得
	 *
	 * @param  int    $order_id Order number.
	 * @param  string $trans_id Transaction ID.
	 * @param  string $result Result.
	 * @return array
	 */
	public function get_acting_log( $order_id = 0, $trans_id = 0, $result = 'OK' ) {
		global $wpdb;

		if ( empty( $order_id ) ) {
			if ( empty( $trans_id ) ) {
				return array();
			}
			if ( 'OK' === $result ) {
				$query = $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}usces_acting_log WHERE `tracking_id` = %s AND `result` = %s ORDER BY `ID` DESC, `datetime` DESC",
					$trans_id,
					'OK'
				);
			} else {
				$query = $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}usces_acting_log WHERE `tracking_id` = %s ORDER BY `ID` DESC, `datetime` DESC",
					$trans_id
				);
			}
		} else {
			if ( empty( $trans_id ) ) {
				if ( 'OK' === $result ) {
					$query = $wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}usces_acting_log WHERE `datetime` IN( SELECT MAX( `datetime` ) FROM {$wpdb->prefix}usces_acting_log WHERE `order_id` = %d GROUP BY `tracking_id` ) AND `order_id` = %d AND `result` = %s ORDER BY `ID` DESC, `datetime` DESC",
						$order_id,
						$order_id,
						'OK'
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
					$query = $wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}usces_acting_log WHERE `order_id` = %d AND `tracking_id` = %s AND `result` = %s ORDER BY `ID` DESC, `datetime` DESC",
						$order_id,
						$trans_id,
						'OK'
					);
				} else {
					$query = $wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}usces_acting_log WHERE `order_id` = %d AND `tracking_id` = %s ORDER BY `ID` DESC, `datetime` DESC",
						$order_id,
						$trans_id
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
	 * @param  string $trans_id Transaction ID.
	 * @param  float  $amount Amount.
	 * @return array
	 */
	private function save_acting_log( $log, $acting, $status, $result, $order_id, $trans_id, $amount = 0 ) {
		global $wpdb;

		if ( 'OK' === $result ) {
			if ( empty( $amount ) && isset( $log['amount'] ) ) {
				$amount = $log['amount'];
			}
		} else {
			$amount = 0;
		}
		if ( 'authorized' === $status && ! empty( $log['created_at'] ) ) {
			$datetime = date_i18n( 'Y-m-d H:i:s', strtotime( $log['created_at'] . ' +9hour' ) );
		} else {
			$datetime = date_i18n( 'Y-m-d H:i:s' );
		}
		$query = $wpdb->prepare(
			"INSERT INTO {$wpdb->prefix}usces_acting_log ( `datetime`, `log`, `acting`, `status`, `result`, `amount`, `order_id`, `tracking_id` ) VALUES ( %s, %s, %s, %s, %s, %f, %d, %s )",
			$datetime,
			usces_serialize( $log ),
			$acting,
			$status,
			$result,
			$amount,
			$order_id,
			$trans_id
		);
		$res   = $wpdb->query( $query );
		return $res;
	}

	/**
	 * 最新処理取得
	 *
	 * @param  int    $order_id Order number.
	 * @param  string $trans_id Transaction ID.
	 * @param  string $result Result.
	 * @return array
	 */
	public function get_acting_latest_log( $order_id, $trans_id, $result = 'OK' ) {
		$log_data = $this->get_acting_log( $order_id, $trans_id, $result );
		if ( $log_data ) {
			$data       = current( $log_data );
			$log        = usces_unserialize( $data['log'] );
			$payment_id = ( isset( $log['id'] ) ) ? $log['id'] : '';
			$latest_log = array(
				'log'        => $log,
				'acting'     => $data['acting'],
				'status'     => $data['status'],
				'result'     => $data['result'],
				'amount'     => $data['amount'],
				'order_id'   => $data['order_id'],
				'trans_id'   => $data['tracking_id'],
				'payment_id' => $payment_id,
			);
		} else {
			$latest_log = array();
		}
		return $latest_log;
	}

	/**
	 * 決済ステータス取得
	 *
	 * @param  int    $order_id Order number.
	 * @param  string $trans_id Transaction ID.
	 * @return string
	 */
	private function get_payment_status( $order_id, $trans_id ) {
		global $wpdb;

		$payment_status = '';
		$latest_log     = $this->get_acting_latest_log( $order_id, $trans_id, 'ALL' );
		if ( isset( $latest_log['status'] ) ) {
			$refund = strpos( $latest_log['status'], 'refund' );
			if ( false !== $refund ) {
				$payment_status = 'capture';
			} else {
				$payment_status = $latest_log['status'];
			}
		}
		return $payment_status;
	}

	/**
	 * 処理区分名称取得
	 *
	 * @param  string $payment_status Payment status code.
	 * @return string
	 */
	private function get_status_name( $payment_status ) {
		$status_name = '';
		switch ( $payment_status ) {
			case 'retrieve_error':
				$status_name = 'RETRIEVE ERROR';
				break;
			case 'authorized_error':
				$status_name = 'AUTHORIZED ERROR';
				break;
			case 'closed_error':
				$status_name = 'CLOSED ERROR';
				break;
			case 'capture_error':
				$status_name = 'CAPTURE ERROR';
				break;
			case 'refund_error':
				$status_name = 'REFUND ERROR';
				break;
			default:
				$status_name = strtoupper( $payment_status );
		}
		return $status_name;
	}

	/**
	 * 処理区分取得
	 *
	 * @param  string $payment_status Payment status code.
	 * @return string
	 */
	private function get_status( $payment_status ) {
		$status = '';
		switch ( $payment_status ) {
			case 'retrieve_error':
				$status = 'retrieve-error';
				break;
			case 'authorized_error':
				$status = 'authorized-error';
				break;
			case 'closed_error':
				$status = 'closed-error';
				break;
			case 'capture_error':
				$status = 'capture-error';
				break;
			case 'refund_error':
				$status = 'refund-error';
				break;
			default:
				$status = $payment_status;
		}
		return $status;
	}

	/**
	 * Retrieve API.
	 *
	 * @param string $payment_id Payment ID.
	 * @param bool   $history All history retrieval.
	 * @return array
	 */
	public function api_retrieve( $payment_id, $history = false ) {
		$acting_opts = $this->get_acting_settings();
		$api_url     = $acting_opts['api_url'] . $payment_id;
		$params      = array(
			'method'  => 'GET',
			'headers' => array(
				'Content-Type'  => 'application/json;charset=utf-8',
				'Authorization' => 'Bearer ' . $acting_opts['secret_key'],
				'Paidy-Version' => '2018-04-10',
			),
		);
		$response    = wp_remote_get( $api_url, $params );
		if ( is_wp_error( $response ) ) {
			$response_data = array();
		} else {
			$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $history ) {
				$amount     = $response_data['amount'];
				$capture_id = '';
				if ( isset( $response_data['captures'] ) ) {
					$captures = $response_data['captures'];
					foreach ( (array) $captures as $capture ) {
						if ( isset( $capture['id'] ) ) {
							$capture_id = $capture['id'];
						}
					}
					$response_data['capture_id'] = $capture_id;
				}
				if ( isset( $response_data['refunds'] ) ) {
					$refunds = $response_data['refunds'];
					foreach ( (array) $refunds as $refund ) {
						if ( isset( $refund['amount'] ) ) {
							$amount -= (int) $refund['amount'];
						}
					}
				}
				if ( 'closed' === $response_data['status'] && empty( $capture_id ) ) {
					$amount = 0;
				}
				$response_data['final_amount'] = $amount;
			}
		}
		return $response_data;
	}

	/**
	 * Captures API.
	 *
	 * @param string $payment_id Payment ID.
	 * @return array
	 */
	public function api_capture( $payment_id ) {
		$acting_opts = $this->get_acting_settings();
		$api_url     = $acting_opts['api_url'] . $payment_id . '/captures';
		$body        = array(
			'metadata' => array( 'Platform' => 'Welcart' ),
		);
		$params      = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type'  => 'application/json;charset=utf-8',
				'Authorization' => 'Bearer ' . $acting_opts['secret_key'],
				'Paidy-Version' => '2018-04-10',
			),
			'body'    => wp_json_encode( $body ),
		);
		$response    = wp_remote_post( $api_url, $params );
		if ( is_wp_error( $response ) ) {
			$response_data = array();
		} else {
			$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
		}
		return $response_data;
	}

	/**
	 * Refunds API.
	 *
	 * @param string $payment_id Payment ID.
	 * @param string $capture_id Capture ID.
	 * @param float  $amount 返金金額.
	 * @return array
	 */
	public function api_refund( $payment_id, $capture_id, $amount = 0 ) {
		$acting_opts = $this->get_acting_settings();
		$api_url     = $acting_opts['api_url'] . $payment_id . '/refunds';
		$body        = array(
			'metadata'   => array( 'Platform' => 'Welcart' ),
			'capture_id' => $capture_id,
		);
		if ( ! empty( $amount ) ) {
			$body['amount'] = $amount;
		}
		$params   = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type'  => 'application/json;charset=utf-8',
				'Authorization' => 'Bearer ' . $acting_opts['secret_key'],
				'Paidy-Version' => '2018-04-10',
			),
			'body'    => wp_json_encode( $body ),
		);
		$response = wp_remote_post( $api_url, $params );
		if ( is_wp_error( $response ) ) {
			$response_data = array();
		} else {
			if ( isset( $response_data['response']['code'] ) && '2' === substr( $response['response']['code'], 0, 1 ) ) {
				$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
			} else {
				$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
			}
		}
		return $response_data;
	}

	/**
	 * Close API.
	 *
	 * @param string $payment_id Payment ID.
	 * @return array
	 */
	public function api_close( $payment_id ) {
		$acting_opts = $this->get_acting_settings();
		$api_url     = $acting_opts['api_url'] . $payment_id . '/close';
		$body        = array(
			'metadata' => array( 'Platform' => 'Welcart' ),
		);
		$params      = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type'  => 'application/json;charset=utf-8',
				'Authorization' => 'Bearer ' . $acting_opts['secret_key'],
				'Paidy-Version' => '2018-04-10',
			),
			'body'    => wp_json_encode( $body ),
		);
		$response    = wp_remote_get( $api_url, $params );
		if ( is_wp_error( $response ) ) {
			$response_data = array();
		} else {
			$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
		}
		return $response_data;
	}
}
