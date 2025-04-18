<?php
/**
 * Settlement Class.
 * ルミーズ
 *
 * @package Welcart
 * @author  Collne Inc.
 * @version 1.0.0
 */

/**
 * ルミーズ決済モジュール
 */
class REMISE_SETTLEMENT {
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
	 * マルチ決済にて選択した支払期間の名称リスト.
	 *
	 * @var array[]
	 */
	public static $x_pay_csv_list = array(
		'D001' => array(
			'label' => 'セブン-イレブン',
		),
		'D002' => array(
			'label' => 'ローソン',
		),
		'D005' => array(
			'label' => 'ミニストップ',
		),
		'D015' => array(
			'label' => 'セイコーマート',
		),
		'D405' => array(
			'label' => 'ペイジー',
		),
		'D010' => array(
			'label' => 'デイリーヤマザキ',
		),
		'D011' => array(
			'label' => 'ヤマザキデイリーストア',
		),
		'D030' => array(
			'label' => 'ファミリーマート',
		),
		'E201' => array(
			'label' => 'PayPay',
		),
		'E202' => array(
			'label' => 'PayPay',
		),
		'D401' => array(
			'label' => '楽天Ｅｄｙ',
		),
		'D404' => array(
			'label' => '楽天銀行',
		),
		'D406' => array(
			'label' => 'PayPay銀行',
		),
		'D403' => array(
			'label' => 'モバイルSuica',
		),
		'D451' => array(
			'label' => 'ウェブマネー',
		),
		'D452' => array(
			'label' => 'ビットキャッシュ',
		),
		'D453' => array(
			'label' => 'JCBプレモカード',
		),
		'P901' => array(
			'label' => 'コンビニ払込票',
		),
		'P902' => array(
			'label' => 'コンビニ払込票',
		),
		'P903' => array(
			'label' => 'コンビニ払込票',
		),
		'P904' => array(
			'label' => 'コンビニ払込票',
		),
		'P905' => array(
			'label' => 'コンビニ払込票',
		),
		'P906' => array(
			'label' => 'コンビニ払込票',
		),
		'C501' => array(
			'label' => 'クレジットカード',
		),
		'C502' => array(
			'label' => 'クレジットカード',
		),
		'C511' => array(
			'label' => 'PayPal',
		),
		'K801' => array(
			'label' => '口座振替会員登録',
		),
		'M601' => array(
			'label' => 'd払い',
		),
		'M602' => array(
			'label' => 'd払い',
		),
		'M603' => array(
			'label' => 'd払い',
		),
		'M611' => array(
			'label' => 'auかんたん決済',
		),
		'M612' => array(
			'label' => 'auかんたん決済',
		),
		'M613' => array(
			'label' => 'auかんたん決済',
		),
		'M621' => array(
			'label' => 'ソフトバンクまとめて支払い',
		),
		'M622' => array(
			'label' => 'ソフトバンクまとめて支払い',
		),
		'M623' => array(
			'label' => 'ソフトバンクまとめて支払い',
		),
	);

	/**
	 * Construct.
	 */
	public function __construct() {

		$this->paymod_id          = 'remise';
		$this->pay_method         = array(
			'acting_remise_card',
			'acting_remise_conv',
		);
		$this->acting_name        = 'ルミーズ';
		$this->acting_formal_name = 'ルミーズ';
		$this->acting_company_url = 'http://www.remise.jp/';

		$this->initialize_data();

		if ( is_admin() ) {
			add_action( 'admin_print_footer_scripts', array( $this, 'admin_scripts' ) );
			add_action( 'usces_action_admin_settlement_update', array( $this, 'settlement_update' ) );
			add_action( 'usces_action_settlement_tab_title', array( $this, 'settlement_tab_title' ) );
			add_action( 'usces_action_settlement_tab_body', array( $this, 'settlement_tab_body' ) );

			add_filter( 'usces_filter_settle_info_field_meta_keys', array( $this, 'settlement_info_field_meta_keys' ) );
			add_filter( 'usces_filter_settle_info_field_keys', array( $this, 'settlement_info_field_keys' ), 10, 2 );
			add_filter( 'usces_filter_settle_info_field_value', array( $this, 'settlement_info_field_value' ), 10, 3 );
		}

		if ( $this->is_activate_card() || $this->is_activate_conv() ) {
			add_action( 'usces_action_reg_orderdata', array( $this, 'register_orderdata' ) );
			add_filter( 'usces_filter_send_order_mail_payment', array( $this, 'order_mail_payment' ), 10, 6 );
		}

		if ( $this->is_validity_acting( 'card' ) ) {
			add_filter( 'usces_filter_template_redirect', array( $this, 'member_update_settlement' ), 1 );
			add_action( 'usces_action_member_submenu_list', array( $this, 'e_update_settlement' ) );
			add_filter( 'usces_filter_member_submenu_list', array( $this, 'update_settlement' ), 10, 2 );
			if ( is_admin() ) {
				add_action( 'usces_action_admin_member_info', array( $this, 'member_settlement_info' ), 10, 3 );
				add_action( 'usces_action_post_update_memberdata', array( $this, 'member_edit_post' ), 10, 2 );
			}
		}

		if ( $this->is_validity_acting( 'conv' ) ) {
			add_filter( 'usces_filter_payment_detail', array( $this, 'payment_detail' ), 10, 2 );
			add_action( 'usces_filter_completion_settlement_message', array( $this, 'completion_settlement_message' ), 10, 2 );
		}
		add_filter( 'usces_filter_order_confirm_mail_payment', array( $this, 'order_confirm_mail_payment' ), 10, 5 );
		add_filter( 'usces_filter_pdf_payment_name', array( $this, 'pdf_payment_name' ), 10, 2 );
	}

	/**
	 * Return an instance of this class.
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize.
	 */
	public function initialize_data() {
		$options = get_option( 'usces', array() );
		if ( ! isset( $options['acting_settings'] ) || ! isset( $options['acting_settings']['remise'] ) ) {
			$options['acting_settings']['remise']['SHOPCO']           = '';
			$options['acting_settings']['remise']['HOSTID']           = '';
			$options['acting_settings']['remise']['card_activate']    = 'off';
			$options['acting_settings']['remise']['card_jb']          = '';
			$options['acting_settings']['remise']['card_pc_ope']      = '';
			$options['acting_settings']['remise']['payquick']         = '';
			$options['acting_settings']['remise']['howpay']           = '';
			$options['acting_settings']['remise']['continuation']     = '';
			$options['acting_settings']['remise']['conv_activate']    = 'off';
			$options['acting_settings']['remise']['conv_pc_ope']      = '';
			$options['acting_settings']['remise']['S_PAYDATE']        = '';
			$options['acting_settings']['remise']['send_url_mbl']     = '';
			$options['acting_settings']['remise']['send_url_pc']      = '';
			$options['acting_settings']['remise']['send_url_cvs_mbl'] = '';
			$options['acting_settings']['remise']['send_url_cvs_pc']  = '';
			$options['acting_settings']['remise']['show_x_pay_csv']   = 'off';
			update_option( 'usces', $options );
		}
	}

	/**
	 * 決済有効判定
	 * 支払方法で使用している場合に true
	 *
	 * @param  string $type Module type.
	 * @return boolean
	 */
	public function is_validity_acting( $type = '' ) {
		$acting_opts = $this->get_acting_settings();
		if ( empty( $acting_opts ) ) {
			return false;
		}

		$payment_method = usces_get_system_option( 'usces_payment_method', 'sort' );
		$method         = false;

		switch ( $type ) {
			case 'card':
				foreach ( $payment_method as $payment ) {
					if ( 'acting_remise_card' === $payment['settlement'] && 'activate' === $payment['use'] ) {
						$method = true;
						break;
					}
				}
				if ( $method && $this->is_activate_card() ) {
					return true;
				} else {
					return false;
				}
				break;

			case 'conv':
				foreach ( $payment_method as $payment ) {
					if ( 'acting_remise_conv' === $payment['settlement'] && 'activate' === $payment['use'] ) {
						$method = true;
						break;
					}
				}
				if ( $method && $this->is_activate_conv() ) {
					return true;
				} else {
					return false;
				}
				break;

			default:
				if ( 'on' === $acting_opts['activate'] ) {
					return true;
				} else {
					return false;
				}
		}
	}

	/**
	 * クレジットカード決済有効判定
	 *
	 * @return boolean
	 */
	public function is_activate_card() {
		$acting_opts = $this->get_acting_settings();
		if ( ( isset( $acting_opts['activate'] ) && 'on' === $acting_opts['activate'] ) &&
			( isset( $acting_opts['card_activate'] ) && ( 'on' === $acting_opts['card_activate'] ) ) ) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	/**
	 * コンビニ・電子マネー決済有効判定
	 *
	 * @return boolean
	 */
	public function is_activate_conv() {
		$acting_opts = $this->get_acting_settings();
		if ( ( isset( $acting_opts['activate'] ) && 'on' === $acting_opts['activate'] ) &&
			( isset( $acting_opts['conv_activate'] ) && 'on' === $acting_opts['conv_activate'] ) ) {
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
			case 'usces_settlement':
				$settlement_selected = get_option( 'usces_settlement_selected' );
				if ( in_array( $this->paymod_id, (array) $settlement_selected, true ) ) :
					?>
<script type="text/javascript">
	jQuery(document).ready( function($) {
		const form_remise = $('form#remise_form');
		function card_activate_remise() {
			if( 'on' === form_remise.find("input[name='card_activate']:checked").val() ) {
				$(".card_form_remise").css("display","");
			} else {
				$(".card_form_remise").css("display","none");
			}
		}
		function conv_activate_remise() {
			if( 'on' === form_remise.find("input[name='conv_activate']:checked").val() ) {
				$(".conv_form_remise").css("display","");
			} else {
				$(".conv_form_remise").css("display","none");
			}
		}
		card_activate_remise();
		$("input[id^='card_activate_remise_']").click( function() {
			card_activate_remise();
		});
		conv_activate_remise();
		$("input[id^='conv_activate_remise_']").click( function() {
			conv_activate_remise();
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
		$options         = get_option( 'usces', array() );
		$payment_method  = usces_get_system_option( 'usces_payment_method', 'settlement' );
		$post_data       = wp_unslash( $_POST );

		unset( $options['acting_settings']['remise'] );
		$options['acting_settings']['remise']['plan']             = ( isset( $post_data['plan'] ) ) ? $post_data['plan'] : '';
		$options['acting_settings']['remise']['SHOPCO']           = ( isset( $post_data['SHOPCO'] ) ) ? $post_data['SHOPCO'] : '';
		$options['acting_settings']['remise']['HOSTID']           = ( isset( $post_data['HOSTID'] ) ) ? $post_data['HOSTID'] : '';
		$options['acting_settings']['remise']['card_activate']    = ( isset( $post_data['card_activate'] ) ) ? $post_data['card_activate'] : '';
		$options['acting_settings']['remise']['card_jb']          = ( isset( $post_data['card_jb'] ) ) ? $post_data['card_jb'] : '';
		$options['acting_settings']['remise']['card_pc_ope']      = ( isset( $post_data['card_pc_ope'] ) ) ? $post_data['card_pc_ope'] : '';
		$options['acting_settings']['remise']['payquick']         = ( isset( $post_data['payquick'] ) ) ? $post_data['payquick'] : '';
		$options['acting_settings']['remise']['howpay']           = ( isset( $post_data['howpay'] ) ) ? $post_data['howpay'] : '';
		$options['acting_settings']['remise']['continuation']     = ( isset( $post_data['continuation'] ) ) ? $post_data['continuation'] : '';
		$options['acting_settings']['remise']['conv_activate']    = ( isset( $post_data['conv_activate'] ) ) ? $post_data['conv_activate'] : '';
		$options['acting_settings']['remise']['conv_pc_ope']      = ( isset( $post_data['conv_pc_ope'] ) ) ? $post_data['conv_pc_ope'] : '';
		$options['acting_settings']['remise']['S_PAYDATE']        = ( isset( $post_data['S_PAYDATE'] ) ) ? $post_data['S_PAYDATE'] : '';
		$options['acting_settings']['remise']['send_url_mbl']     = ( isset( $post_data['send_url_mbl'] ) ) ? $post_data['send_url_mbl'] : '';
		$options['acting_settings']['remise']['send_url_pc']      = ( isset( $post_data['send_url_pc'] ) ) ? $post_data['send_url_pc'] : '';
		$options['acting_settings']['remise']['send_url_cvs_mbl'] = ( isset( $post_data['send_url_cvs_mbl'] ) ) ? $post_data['send_url_cvs_mbl'] : '';
		$options['acting_settings']['remise']['send_url_cvs_pc']  = ( isset( $post_data['send_url_cvs_pc'] ) ) ? $post_data['send_url_cvs_pc'] : '';
		$options['acting_settings']['remise']['show_x_pay_csv']   = ( isset( $post_data['show_x_pay_csv'] ) ) ? $post_data['show_x_pay_csv'] : '';

		if ( 'on' === $options['acting_settings']['remise']['card_activate'] || 'on' === $options['acting_settings']['remise']['conv_activate'] ) {
			// if ( isset( $post_data['plan_remise'] ) && WCUtils::is_zero( $post_data['plan_remise'] ) ) {
			// $this->error_mes .= '※サービスプランを選択してください<br />';
			// }
			if ( WCUtils::is_blank( $post_data['SHOPCO'] ) ) {
				$this->error_mes .= '※加盟店コードを入力してください<br />';
			}
			if ( WCUtils::is_blank( $post_data['HOSTID'] ) ) {
				$this->error_mes .= '※ホスト番号を入力してください<br />';
			}
			if ( 'on' === $options['acting_settings']['remise']['card_activate'] ) {
				if ( 'public' === $options['acting_settings']['remise']['card_pc_ope'] && WCUtils::is_blank( $post_data['send_url_pc'] ) ) {
					$this->error_mes .= '※クレジットカード決済の本番URLを入力してください<br />';
				}
			}
			if ( 'on' === $options['acting_settings']['remise']['conv_activate'] ) {
				if ( WCUtils::is_blank( $post_data['S_PAYDATE'] ) ) {
					$this->error_mes .= '※支払期限を入力してください<br />';
				}
				if ( 'public' === $options['acting_settings']['remise']['conv_pc_ope'] && WCUtils::is_blank( $post_data['send_url_cvs_pc'] ) ) {
					$this->error_mes .= '※コンビニ・電子マネー決済の本番URLを入力してください<br />';
				}
			}
		}

		if ( '' === $this->error_mes ) {
			$usces->action_status  = 'success';
			$usces->action_message = __( 'Options are updated.', 'usces' );
			if ( 'on' === $options['acting_settings']['remise']['card_activate'] || 'on' === $options['acting_settings']['remise']['conv_activate'] ) {
				$options['acting_settings']['remise']['activate'] = 'on';
				$options['acting_settings']['remise']['REMARKS3'] = 'A0000875';
				$toactive = array();
				if ( 'on' === $options['acting_settings']['remise']['card_activate'] ) {
					if ( 'test' === $options['acting_settings']['remise']['card_pc_ope'] ) {
						$options['acting_settings']['remise']['send_url_pc_test']  = 'https://test.remise.jp/rpgw2/pc/card/paycard.aspx';
						$options['acting_settings']['remise']['send_url_mbl_test'] = 'https://test.remise.jp/rpgw2/mbl/card/paycard.aspx';
					}
					$usces->payment_structure['acting_remise_card'] = 'カード決済（ルミーズ）';
					foreach ( $payment_method as $settlement => $payment ) {
						if ( 'acting_remise_card' === $settlement && 'activate' !== $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_remise_card'] );
				}
				if ( 'on' === $options['acting_settings']['remise']['conv_activate'] ) {
					if ( 'test' === $options['acting_settings']['remise']['conv_pc_ope'] ) {
						$options['acting_settings']['remise']['send_url_cvs_pc_test']  = 'https://test.remise.jp/rpgw2/pc/cvs/paycvs.aspx';
						$options['acting_settings']['remise']['send_url_cvs_mbl_test'] = 'https://test.remise.jp/rpgw2/mbl/cvs/paycvs.aspx';
					}
					$usces->payment_structure['acting_remise_conv'] = 'マルチ決済（ルミーズ）';
					foreach ( $payment_method as $settlement => $payment ) {
						if ( 'acting_remise_conv' === $settlement && 'activate' !== $payment['use'] ) {
							$toactive[] = $payment['name'];
						}
					}
				} else {
					unset( $usces->payment_structure['acting_remise_conv'] );
				}
				usces_admin_orderlist_show_wc_trans_id();
				if ( 0 < count( $toactive ) ) {
					$usces->action_message .= __( 'Please update the payment method to "Activate". <a href="admin.php?page=usces_initial#payment_method_setting">General Setting > Payment Methods</a>', 'usces' );
				}
			} else {
				$options['acting_settings']['remise']['activate'] = 'off';
				unset( $usces->payment_structure['acting_remise_card'] );
				unset( $usces->payment_structure['acting_remise_conv'] );
			}
			if ( 'on' !== $options['acting_settings']['remise']['card_activate'] || 'on' !== $options['acting_settings']['remise']['payquick'] || 'off' === $options['acting_settings']['remise']['activate'] ) {
				usces_clear_quickcharge( 'remise_pcid' );
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
			$usces->action_status                             = 'error';
			$usces->action_message                            = __( 'Data have deficiency.', 'usces' );
			$options['acting_settings']['remise']['activate'] = 'off';
			unset( $usces->payment_structure['acting_remise_card'] );
			unset( $usces->payment_structure['acting_remise_conv'] );
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
	 * クレジット決済設定画面タブ
	 * usces_action_settlement_tab_title
	 */
	public function settlement_tab_title() {
		$settlement_selected = get_option( 'usces_settlement_selected' );
		if ( in_array( $this->paymod_id, (array) $settlement_selected, true ) ) {
			echo '<li><a href="#uscestabs_' . esc_html( $this->paymod_id ) . '">' . esc_html( $this->acting_name ) . '</a></li>';
		}
	}

	/**
	 * クレジット決済設定画面フォーム
	 * usces_action_settlement_tab_body
	 */
	public function settlement_tab_body() {
		global $usces;

		$acting_opts         = $this->get_acting_settings();
		$settlement_selected = get_option( 'usces_settlement_selected' );
		if ( in_array( $this->paymod_id, (array) $settlement_selected, true ) ) :
			$plan            = ( isset( $acting_opts['plan'] ) ) ? $acting_opts['plan'] : '0';
			$shopco          = ( isset( $acting_opts['SHOPCO'] ) ) ? $acting_opts['SHOPCO'] : '';
			$hostid          = ( isset( $acting_opts['HOSTID'] ) ) ? $acting_opts['HOSTID'] : '';
			$card_activate   = ( isset( $acting_opts['card_activate'] ) && 'on' === $acting_opts['card_activate'] ) ? 'on' : 'off';
			$card_jb         = ( isset( $acting_opts['card_jb'] ) ) ? $acting_opts['card_jb'] : 'AUTH';
			$payquick        = ( isset( $acting_opts['payquick'] ) && 'on' === $acting_opts['payquick'] ) ? 'on' : 'off';
			$howpay          = ( isset( $acting_opts['howpay'] ) && 'on' === $acting_opts['howpay'] ) ? 'on' : 'off';
			$continuation    = ( isset( $acting_opts['continuation'] ) && 'on' === $acting_opts['continuation'] ) ? 'on' : 'off';
			$card_pc_ope     = ( isset( $acting_opts['card_pc_ope'] ) && 'public' === $acting_opts['card_pc_ope'] ) ? 'public' : 'test';
			$send_url_pc     = ( isset( $acting_opts['send_url_pc'] ) ) ? $acting_opts['send_url_pc'] : '';
			$conv_activate   = ( isset( $acting_opts['conv_activate'] ) && 'on' === $acting_opts['conv_activate'] ) ? 'on' : 'off';
			$s_paydate       = ( isset( $acting_opts['S_PAYDATE'] ) ) ? $acting_opts['S_PAYDATE'] : '';
			$conv_pc_ope     = ( isset( $acting_opts['conv_pc_ope'] ) && 'public' === $acting_opts['conv_pc_ope'] ) ? 'public' : 'test';
			$send_url_cvs_pc = ( isset( $acting_opts['send_url_cvs_pc'] ) ) ? $acting_opts['send_url_cvs_pc'] : '';
			$show_x_pay_csv  = ( isset( $acting_opts['show_x_pay_csv'] ) && 'on' === $acting_opts['show_x_pay_csv'] ) ? 'on' : 'off';
			?>
	<div id="uscestabs_remise">
	<div class="settlement_service"><span class="service_title"><?php echo esc_html( $this->acting_formal_name ); ?></span></div>
			<?php
			if ( filter_input( INPUT_POST, 'acting' ) === $this->paymod_id ) :
				if ( '' !== $this->error_mes ) :
					?>
		<div class="error_message"><?php echo wp_kses_post( $this->error_mes ); ?></div>
					<?php
				elseif ( isset( $acting_opts['activate'] ) && 'on' === $acting_opts['activate'] ) :
					?>
		<div class="message"><?php esc_html_e( 'Test thoroughly before use.', 'usces' ); ?></div>
					<?php
				endif;
			endif;
			?>
	<form action="" method="post" name="remise_form" id="remise_form">
		<table class="settle_table">
			<!--<tr>
				<th><a class="explanation-label" id="label_ex_plan_remise">契約プラン</a></th>
				<td>
					<select name="plan" id="plan_remise">
						<option value="0"<?php selected( $plan, '0' ); ?>>-------------------------</option>
						<option value="1"<?php selected( $plan, '1' ); ?>>スーパーバリュープラン</option>
						<option value="2"<?php selected( $plan, '2' ); ?>>ライトプラン</option>
					</select>
				</td>
			</tr>
			<tr id="ex_plan_remise" class="explanation"><td colspan="2"><?php echo esc_html( $this->acting_name ); ?>と契約したサービスプランを選択してください。<br />契約を変更したい場合は<?php echo esc_html( $this->acting_name ); ?>へお問合せください。</td></tr>-->
			<tr>
				<th><a class="explanation-label" id="label_ex_SHOPCO_remise">加盟店コード</a></th>
				<td><input name="SHOPCO" type="text" id="SHOPCO_remise" value="<?php echo esc_html( $shopco ); ?>" class="regular-text" maxlength="8" /></td>
			</tr>
			<tr id="ex_SHOPCO_remise" class="explanation"><td colspan="2">契約時に<?php echo esc_html( $this->acting_name ); ?>から発行される加盟店コード（半角英数）</td></tr>
			<tr>
				<th><a class="explanation-label" id="label_ex_HOSTID_remise">ホスト番号</a></th>
				<td><input name="HOSTID" type="text" id="HOSTID_remise" value="<?php echo esc_html( $hostid ); ?>" class="regular-text" maxlength="8" /></td>
			</tr>
			<tr id="ex_HOSTID_remise" class="explanation"><td colspan="2">契約時に<?php echo esc_html( $this->acting_name ); ?>から割り当てられるホスト番号（半角数字）</td></tr>
		</table>
		<table class="settle_table">
			<tr>
				<th>クレジットカード決済</th>
				<td><label><input name="card_activate" type="radio" id="card_activate_remise_1" value="on"<?php checked( $card_activate, 'on' ); ?> /><span>利用する</span></label><br />
					<label><input name="card_activate" type="radio" id="card_activate_remise_2" value="off"<?php checked( $card_activate, 'off' ); ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_card_jb_remise">ジョブコード</a></th>
				<td><!--<label><input name="card_jb" type="radio" id="card_jb_remise_1" value="CHECK"<?php checked( $card_jb, 'CHECK' ); ?> /><span>有効性チェック</span></label><br />-->
					<label><input name="card_jb" type="radio" id="card_jb_remise_2" value="AUTH"<?php checked( $card_jb, 'AUTH' ); ?> /><span>仮売上処理</span></label><br />
					<label><input name="card_jb" type="radio" id="card_jb_remise_3" value="CAPTURE"<?php checked( $card_jb, 'CAPTURE' ); ?> /><span>売上処理</span></label>
				</td>
			</tr>
			<tr id="ex_card_jb_remise" class="explanation card_form_remise"><td colspan="2">決済の種類を指定します。</td></tr>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_payquick_remise">ペイクイック機能</a></th>
				<td><label><input name="payquick" type="radio" id="payquick_remise_1" value="on"<?php checked( $payquick, 'on' ); ?> /><span>利用する</span></label><br />
					<label><input name="payquick" type="radio" id="payquick_remise_2" value="off"<?php checked( $payquick, 'off' ); ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_payquick_remise" class="explanation card_form_remise"><td colspan="2">Welcart の会員システムを利用している場合、会員に対して2回目以降の決済の際、クレジットカード番号、有効期限、名義人の入力が不要となります。<br />クレジットカード情報はWelcart では保存せず、<?php echo esc_html( $this->acting_name ); ?>のデータベースにて安全に保管されます。</td></tr>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_howpay_remise">お客様の支払方法</a></th>
				<td><label><input name="howpay" type="radio" id="howpay_remise_1" value="on"<?php checked( $howpay, 'on' ); ?> /><span>分割払いに対応する</span></label><br />
					<label><input name="howpay" type="radio" id="howpay_remise_2" value="off"<?php checked( $howpay, 'off' ); ?> /><span>一括払いのみ</span></label>
				</td>
			</tr>
			<tr id="ex_howpay_remise" class="explanation card_form_remise"><td colspan="2">「一括払い」以外をご利用の場合は<?php echo esc_html( $this->acting_name ); ?>側の設定が必要となります。前もって<?php echo esc_html( $this->acting_name ); ?>にお問合せください。<br >「スーパーバリュープラン」の場合は「一括払いのみ」を選択してください。</td></tr>
			<?php if ( defined( 'WCEX_DLSELLER' ) ) : ?>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_continuation_remise">自動継続課金</a></th>
				<td><label><input name="continuation" type="radio" id="continuation_remise_1" value="on"<?php checked( $continuation, 'on' ); ?> /><span>利用する</span></label><br />
					<label><input name="continuation" type="radio" id="continuation_remise_2" value="off"<?php checked( $continuation, 'off' ); ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr id="ex_continuation_remise" class="explanation card_form_remise"><td colspan="2">定期的に発生する月会費などの煩わしい課金処理を完全に自動化することができる機能です。<br />詳しくは<?php echo esc_html( $this->acting_name ); ?>にお問合せください。</td></tr>
			<?php endif; ?>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_card_pc_ope_remise">稼働環境</a></th>
				<td><label><input name="card_pc_ope" type="radio" id="card_pc_ope_remise_1" value="test"<?php checked( $card_pc_ope, 'test' ); ?> /><span>テスト環境</span></label><br />
					<label><input name="card_pc_ope" type="radio" id="card_pc_ope_remise_2" value="public"<?php checked( $card_pc_ope, 'public' ); ?> /><span>本番環境</span></label>
				</td>
			</tr>
			<tr id="ex_card_pc_ope_remise" class="explanation card_form_remise"><td colspan="2">動作環境を切り替えます。</td></tr>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_send_url_pc_remise">本番URL(PC)</a></th>
				<td><input name="send_url_pc" type="text" id="send_url_pc_remise" value="<?php echo esc_html( $send_url_pc ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_send_url_pc_remise" class="explanation card_form_remise"><td colspan="2">クレジットカード決済の本番環境(PC)で接続するURLを設定します。</td></tr>
			<?php
			if ( defined( 'WCEX_MOBILE' ) ) :
				$send_url_mbl = ( isset( $acting_opts['send_url_mbl'] ) ) ? $acting_opts['send_url_mbl'] : '';
				?>
			<tr class="card_form_remise">
				<th><a class="explanation-label" id="label_ex_send_url_mbl_remise">本番URL(携帯)</a></th>
				<td><input name="send_url_mbl" type="text" id="send_url_mbl_remise" value="<?php echo esc_html( $send_url_mbl ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_send_url_mbl_remise" class="explanation card_form_remise"><td colspan="2">クレジットカード決済の本番環境(携帯)で接続するURLを設定します。</td></tr>
			<?php endif; ?>
		</table>
		<table class="settle_table">
			<tr>
				<th>マルチ決済</a></th>
				<td><label><input name="conv_activate" type="radio" id="conv_activate_remise_1" value="on"<?php checked( $conv_activate, 'on' ); ?> /><span>利用する</span></label><br />
					<label><input name="conv_activate" type="radio" id="conv_activate_remise_2" value="off"<?php checked( $conv_activate, 'off' ); ?> /><span>利用しない</span></label>
				</td>
			</tr>
			<tr class="conv_form_remise">
				<th><a class="explanation-label" id="label_ex_paydate_remise">支払期限</a></th>
				<td><input name="S_PAYDATE" type="text" id="S_PAYDATE_remise" value="<?php echo esc_html( $s_paydate ); ?>" class="small-text" maxlength="3" />日</td>
			</tr>
			<tr id="ex_paydate_remise" class="explanation conv_form_remise"><td colspan="2">日数を設定します。（半角数字）</td></tr>
			<tr class="conv_form_remise">
				<th><a class="explanation-label" id="label_ex_conv_pc_ope_remise">稼働環境</a></th>
				<td><label><input name="conv_pc_ope" type="radio" id="conv_pc_ope_remise_1" value="test"<?php checked( $conv_pc_ope, 'test' ); ?> /><span>テスト環境</span></label><br />
					<label><input name="conv_pc_ope" type="radio" id="conv_pc_ope_remise_2" value="public"<?php checked( $conv_pc_ope, 'public' ); ?> /><span>本番環境</span></label>
				</td>
			</tr>
			<tr id="ex_conv_pc_ope_remise" class="explanation conv_form_remise"><td colspan="2">動作環境を切り替えます。</td></tr>
			<tr class="conv_form_remise">
				<th><a class="explanation-label" id="label_ex_send_url_cvs_pc_remise">本番URL(PC)</a></th>
				<td><input name="send_url_cvs_pc" type="text" id="send_url_cvs_pc_remise" value="<?php echo esc_html( $send_url_cvs_pc ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_send_url_cvs_pc_remise" class="explanation conv_form_remise"><td colspan="2">マルチ決済の本番環境(PC)で接続するURLを設定します。</td></tr>
			<?php
			if ( defined( 'WCEX_MOBILE' ) ) :
				$send_url_cvs_mbl = ( isset( $acting_opts['send_url_cvs_mbl'] ) ) ? $acting_opts['send_url_cvs_mbl'] : '';
				?>
			<tr class="conv_form_remise">
				<th><a class="explanation-label" id="label_ex_send_url_cvs_mbl_remise">本番URL(携帯)</a></th>
				<td><input name="send_url_cvs_mbl" type="text" id="send_url_cvs_mbl_remise" value="<?php echo esc_html( $send_url_cvs_mbl ); ?>" class="regular-text" /></td>
			</tr>
			<tr id="ex_send_url_cvs_mbl_remise" class="explanation conv_form_remise"><td colspan="2">マルチ決済の本番環境(携帯)で接続するURLを設定します。</td></tr>
			<?php endif; ?>
			<tr class="conv_form_remise">
				<th><a class="explanation-label" id="label_ex_show_x_pay_csv">マルチ決済支払先表示</a></th>
				<td><label><input name="show_x_pay_csv" type="radio" id="show_x_pay_csv_remise_1" value="on"<?php checked( $show_x_pay_csv, 'on' ); ?> /><span>表示する</span></label><br />
					<label><input name="show_x_pay_csv" type="radio" id="show_x_pay_csv_remise_2" value="off"<?php checked( $show_x_pay_csv, 'off' ); ?> /><span>表示しない</span></label>
				</td>
			</tr>
			<tr id="ex_show_x_pay_csv" class="explanation conv_form_remise"><td colspan="2">マルチ決済で支払いを行った際に、メールおよびPDFに支払先を表示します。</td></tr>
		</table>
		<input name="acting" type="hidden" value="remise" />
		<input name="usces_option_update" type="submit" class="button button-primary" value="<?php echo esc_attr( $this->acting_name ); ?>の設定を更新する" />
			<?php wp_nonce_field( 'admin_settlement', 'wc_nonce' ); ?>
	</form>
	<div class="settle_exp">
		<p><strong><?php esc_html_e( 'Remise Japanese Settlement', 'usces' ); ?></strong></p>
		<a href="<?php echo esc_url( $this->acting_company_url ); ?>" target="_blank"><?php echo esc_html( $this->acting_name ); ?>の詳細はこちら 》</a>
		<p>　</p>
		<p>この決済は「外部リンク型」の決済システムです。</p>
		<p>「外部リンク型」とは、決済会社のページへは遷移してカード情報を入力する決済システムです。</p>
		<p>「自動継続課金」を利用するには「WCEX DL Seller」拡張プラグインのインストールが必要です。</p>
	</div>
	</div><!--uscestabs_remise-->
			<?php
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
		$keys = array_merge( $keys, array( 'remise_x_pay_csv' ) );

		return $keys;
	}

	/**
	 * 受注編集画面に表示する決済情報のキー
	 * usces_filter_settle_info_field_keys
	 *
	 * @param array $keys Settlement information keys.
	 * @param array $fields Settlement information fields.
	 *
	 * @return array
	 */
	public function settlement_info_field_keys( $keys, $fields ) {
		if ( isset( $fields['remise_x_pay_csv'] ) && isset( self::$x_pay_csv_list[ $fields['remise_x_pay_csv'] ] ) ) {
			$keys = array_merge( $keys, array( 'remise_x_pay_csv' ) );
		}

		return $keys;
	}

	/**
	 * 受注編集画面に表示する決済情報の値整形
	 * usces_filter_settle_info_field_value
	 *
	 * @param string $value Value.
	 * @param string $key Key.
	 * @param string $acting Acting type.
	 *
	 * @return string
	 */
	public function settlement_info_field_value( $value, $key, $acting ) {
		if ( 'remise_x_pay_csv' === $key && isset( self::$x_pay_csv_list[ $value ] ) ) {
			$value = $value . '（' . self::$x_pay_csv_list[ $value ]['label'] . '）';
		}

		return $value;
	}

	/**
	 * 受注データ登録
	 * Called by usces_reg_orderdata() and usces_new_orderdata().
	 * usces_action_reg_orderdata
	 *
	 * @param array $args Compact array( $cart, $entry, $order_id, $member_id, $payments, $charging_type, $results ).
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

		if ( isset( $_REQUEST['X-S_TORIHIKI_NO'] ) ) {
			$usces->set_order_meta_value( 'settlement_id', wp_unslash( $_REQUEST['X-S_TORIHIKI_NO'] ), $order_id );
			$usces->set_order_meta_value( 'wc_trans_id', wp_unslash( $_REQUEST['X-S_TORIHIKI_NO'] ), $order_id );
			if ( isset( $_REQUEST['X-AC_MEMBERID'] ) ) {
				$usces->set_order_meta_value( wp_unslash( $_REQUEST['X-AC_MEMBERID'] ), 'continuation', $order_id );
				$usces->set_member_meta_value( 'continue_memberid_' . $order_id, wp_unslash( $_REQUEST['X-AC_MEMBERID'] ) );
			}
			if ( isset( $_REQUEST['X-PAY_CSV'] ) ) {
				$usces->set_order_meta_value( 'remise_x_pay_csv', wp_unslash( $_REQUEST['X-PAY_CSV'] ), $order_id );
			}
		}
	}

	/**
	 * Add payment institution to Thank You Mail payment methods.
	 *
	 * @param  string $msg_payment Payment method message.
	 * @param  int    $order_id Order number.
	 * @param  array  $payment Payment data.
	 * @param  array  $cart Cart data.
	 * @param  array  $entry Entry data.
	 * @param  array  $data Order data.
	 * @return string
	 */
	public function order_mail_payment( $msg_payment, $order_id, $payment, $cart, $entry, $data ) {
		global $usces;
		if ( 'acting_remise_conv' !== $payment['settlement'] ) {
			return $msg_payment;
		}
		$remise_x_pay_csv = $usces->get_order_meta_value( 'remise_x_pay_csv', $order_id );
		if ( empty( $remise_x_pay_csv ) ) {
			return $msg_payment;
		}
		$acting_opts    = $this->get_acting_settings();
		$show_x_pay_csv = ( isset( $acting_opts['show_x_pay_csv'] ) && 'on' === $acting_opts['show_x_pay_csv'] ) ? 'on' : 'off';
		if ( 'off' === $show_x_pay_csv ) {
			return $msg_payment;
		}
		if ( ! isset( self::$x_pay_csv_list[ $remise_x_pay_csv ] ) ) {
			return $msg_payment;
		}
		$remise_x_pay_csv_text = '（' . self::$x_pay_csv_list[ $remise_x_pay_csv ]['label'] . '）';
		if ( usces_is_html_mail() ) {
			$msg_payment = '<tr><td colspan="2" style="padding: 0 0 25px 0;">' . $payment['name'] . $remise_x_pay_csv_text . usces_payment_detail( $entry ) . '</td></tr>';
		} else {
			$msg_payment  = __( '** Payment method **', 'usces' ) . "\r\n";
			$msg_payment .= usces_mail_line( 1, $entry['customer']['mailaddress1'] ); // ********************
			$msg_payment .= $payment['name'] . $remise_x_pay_csv_text . usces_payment_detail( $entry ) . "\r\n\r\n";
		}
		return $msg_payment;
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
			if ( 'on' !== $acting_opts['payquick'] ) {
				return;
			}

			if ( isset( $_REQUEST['usces_page'] ) && 'member_update_settlement' === wp_unslash( $_REQUEST['usces_page'] ) ) {
				add_filter( 'usces_filter_states_form_js', array( $this, 'states_form_js' ) );
				$usces->page = 'member_update_settlement';
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
		global $usces;

		$acting_opts = $this->get_acting_settings();
		if ( 'on' === $acting_opts['payquick'] ) {
			$member = $usces->get_member();
			$pcid   = $usces->get_member_meta_value( 'remise_pcid', $member['ID'] );
			if ( ! empty( $pcid ) ) {
				$update_settlement_url = add_query_arg(
					array(
						'usces_page' => 'member_update_settlement',
						're-enter'   => 1,
					),
					USCES_MEMBER_URL
				);
				$html                 .= '<li class="settlement-update gotoedit"><a href="' . $update_settlement_url . '">' . __( 'Change the credit card is here >>', 'usces' ) . '</a></li>';
			}
		}
		return $html;
	}

	/**
	 * クレジットカード登録・変更ページ
	 */
	public function member_update_settlement_form() {
		global $usces;

		$member                = $usces->get_member();
		$member_id             = $member['ID'];
		$update_settlement_url = add_query_arg(
			array(
				'usces_page' => 'member_update_settlement',
				'settlement' => 1,
				're-enter'   => 1,
			),
			USCES_MEMBER_URL
		);
		$acting_opts           = $this->get_acting_settings();
		$send_url              = ( 'public' === $acting_opts['card_pc_ope'] ) ? $acting_opts['send_url_pc'] : $acting_opts['send_url_pc_test'];
		$rand                  = '0000000' . $member_id;
		$partofcard            = $usces->get_member_meta_value( 'partofcard', $member_id );
		$limitofcard           = $usces->get_member_meta_value( 'limitofcard', $member_id );
		$error_message         = apply_filters( 'usces_filter_member_update_settlement_error_message', $usces->error_message );

		ob_start();
		get_header();
		?>
<div id="content" class="two-column">
<div class="catbox">
		<?php
		if ( have_posts() ) :
			usces_remove_filter();
			?>
<div class="post" id="wc_<?php usces_page_name(); ?>">
<h1 class="member_page_title"><?php esc_html_e( 'Credit card update', 'usces' ); ?></h1>
<div class="entry">
<div id="memberpages">
<div class="whitebox">
	<div id="memberinfo">
	<div class="header_explanation">
			<?php do_action( 'usces_action_member_update_settlement_page_header' ); ?>
	</div>
	<div class="error_message"><?php wel_esc_script_e( $error_message ); ?></div>
	<div><?php echo __( 'Since the transition to the page of the settlement company by clicking the "Update", please fill out the information for the new card.<br />In addition, this process is intended to update the card information such as credit card expiration date, it is not in your contract renewal of service.<br />To check the current contract, please refer to the member page.', 'usces' ); ?><br /><br /></div>
			<?php
			if ( ! empty( $partofcard ) && ! empty( $limitofcard ) ) :
				?>
	<table>
		<tbody>
		<tr>
			<th scope="row"><?php esc_html_e( 'The last four digits of your card number', 'usces' ); ?></th><td><?php echo esc_html( $partofcard ); ?></div></td>
			<th scope="row"><?php esc_html_e( 'Expiration date', 'usces' ); ?></th><td><?php echo esc_html( $limitofcard ); ?></td>
		</tr>
		</tbody>
	</table>
				<?php
			endif;
			?>
	<form id="member-card-info" action="<?php echo esc_url( $send_url ); ?>" method="post" onKeyDown="if(event.keyCode == 13){return false;}" accept-charset="Shift_JIS">
		<input type="hidden" name="SHOPCO" value="<?php echo esc_attr( $acting_opts['SHOPCO'] ); ?>" />
		<input type="hidden" name="HOSTID" value="<?php echo esc_attr( $acting_opts['HOSTID'] ); ?>" />
		<input type="hidden" name="REMARKS3" value="<?php echo esc_attr( $acting_opts['REMARKS3'] ); ?>" />
		<input type="hidden" name="S_TORIHIKI_NO" value="<?php echo esc_attr( $rand ); ?>" />
		<input type="hidden" name="JOB" value="CHECK" />
		<input type="hidden" name="MAIL" value="<?php echo esc_attr( $member['mailaddress1'] ); ?>" />
		<input type="hidden" name="ITEM" value="0000990" />
		<input type="hidden" name="RETURL" value="<?php echo esc_attr( $update_settlement_url ); ?>" />
		<input type="hidden" name="NG_RETURL" value="<?php echo esc_attr( $update_settlement_url ); ?>" />
		<input type="hidden" name="EXITURL" value="<?php echo esc_attr( $update_settlement_url ); ?>" />
		<input type="hidden" name="OPT" value="welcart_card_update" />
		<input type="hidden" name="PAYQUICK" value="1" />
		<input type="hidden" name="dummy" value="&#65533;" />
		<div class="send">
			<input type="submit" name="purchase" class="checkout_button" value="<?php esc_attr_e( 'Update processing', 'usces' ); ?>" onclick="document.charset='Shift_JIS';" />
			<input type="button" name="back" value="<?php esc_attr_e( 'Back to the member page.', 'usces' ); ?>" onclick="location.href='<?php echo esc_url( USCES_MEMBER_URL ); ?>'" />
			<input type="button" name="top" value="<?php esc_attr_e( 'Back to the top page.', 'usces' ); ?>" onclick="location.href='<?php echo esc_url( home_url() ); ?>'" />
		</div>
	</form>
	<div class="footer_explanation">
			<?php do_action( 'usces_action_member_update_settlement_page_footer' ); ?>
	</div>
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
		$html = ob_get_contents();
		ob_end_clean();
		echo $html; // no escape.
	}

	/**
	 * 会員データ編集画面
	 * usces_action_admin_member_info
	 *
	 * @param  array $member_data Member data.
	 * @param  array $member_meta_data Member meta data.
	 * @param  array $member_history Member history data.
	 */
	public function member_settlement_info( $member_data, $member_meta_data, $member_history ) {
		if ( 0 < count( $member_meta_data ) ) :
			$cardinfo = array();
			foreach ( $member_meta_data as $value ) {
				if ( in_array( $value['meta_key'], array( 'remise_pcid', 'partofcard', 'limitofcard', 'remise_memid' ), true ) ) {
					$cardinfo[ $value['meta_key'] ] = $value['meta_value'];
				}
			}
			if ( 0 < count( $cardinfo ) ) :
				if ( array_key_exists( 'remise_pcid', $cardinfo ) ) :
					foreach ( $cardinfo as $key => $value ) :
						if ( 'remise_pcid' !== $key ) :
							if ( 'partofcard' === $key ) {
								$label = '下4桁';
							} elseif ( 'limitofcard' === $key ) {
								$label = '有効期限';
							} elseif ( 'remise_memid' === $key ) {
								$label = 'メンバーID';
							} else {
								$label = $key;
							}
							?>
			<tr>
				<td class="label"><?php echo esc_html( $label ); ?></td>
				<td><div class="rod_left shortm"><?php echo esc_html( $value ); ?></div></td>
			</tr>
							<?php
						endif;
					endforeach;
					?>
			<tr>
				<td class="label">ペイクイック</td>
				<td><div class="rod_left shortm">登録あり</div></td>
			</tr>
			<tr>
				<td class="label"><input type="checkbox" name="remise_pcid" id="remise_pcid" value="delete"></td>
				<td><label for="remise_pcid">ペイクイックを解除する</label></td>
			</tr>
					<?php
				endif;
			endif;
		endif;
	}

	/**
	 * 会員データ編集画面 カード情報登録解除
	 * usces_action_post_update_memberdata
	 *
	 * @param int     $member_id Member ID.
	 * @param boolean $res Result.
	 */
	public function member_edit_post( $member_id, $res ) {
		global $usces;

		if ( ! $this->is_activate_card() || false === $res ) {
			return;
		}

		if ( 'delete' === filter_input( INPUT_POST, 'remise_pcid' ) ) {
			$usces->del_member_meta( 'remise_pcid', $member_id );
		}
	}

	/**
	 * 支払方法説明
	 * usces_filter_payment_detail
	 *
	 * @param  string $str Payment method description.
	 * @param  array  $entry Entry data.
	 * @return string
	 */
	public function payment_detail( $str, $entry ) {
		$payment = usces_get_payments_by_name( $entry['order']['payment_name'] );
		if ( isset( $payment['settlement'] ) && 'acting_remise_conv' === $payment['settlement'] ) {
			$acting_opts    = $this->get_acting_settings();
			$payment_detail = __( '(', 'usces' ) . sprintf( __( 'Payment is valid for %s days from the date of order.', 'usces' ), $acting_opts['S_PAYDATE'] ) . __( ')', 'usces' );
			$str            = apply_filters( 'usces_filter_remise_payment_limit_conv', $payment_detail, $acting_opts['S_PAYDATE'] );
		}
		return $str;
	}

	/**
	 * 購入完了メッセージ
	 * usces_filter_completion_settlement_message
	 *
	 * @param  string $html Completion message.
	 * @param  array  $entry Entry data.
	 * @return string
	 */
	public function completion_settlement_message( $html, $entry ) {
		global $usces;

		if ( isset( $_REQUEST['acting'] ) && 'remise_conv' === wp_unslash( $_REQUEST['acting'] ) ) {
			$x_pay_csv = wp_unslash( $_REQUEST['X-PAY_CSV'] );
			$conv_name = usces_get_conv_name( $x_pay_csv );
			if ( empty( $conv_name ) ) {
				$conv_name = isset( self::$x_pay_csv_list[ $x_pay_csv ]['label'] ) ? self::$x_pay_csv_list[ $x_pay_csv ]['label'] : '';
			}

			$html   .= '<div id="status_table"><h5>ルミーズ・マルチ決済</h5>';
			$html   .= '<table>';
			$html   .= '<tr><th>ご請求番号</th><td>' . esc_html( wp_unslash( $_REQUEST['X-S_TORIHIKI_NO'] ) ) . '</td></tr>';
			$html   .= '<tr><th>ご請求合計金額</th><td>' . esc_html( wp_unslash( $_REQUEST['X-TOTAL'] ) ) . '</td></tr>';
			$paydate = wp_unslash( $_REQUEST['X-PAYDATE'] );
			$html   .= '<tr><th>お支払期限</th><td>' . esc_html( substr( $paydate, 0, 4 ) . '年' . substr( $paydate, 4, 2 ) . '月' . substr( $paydate, 6, 2 ) . '日' ) . '（期限を過ぎますとお支払ができません）</td></tr>';
			$html   .= '<tr><th>お支払先</th><td>' . esc_html( $conv_name ) . '</td></tr>';
			$html   .= $this->get_remise_conv_return( $x_pay_csv );
			$html   .= '</table>';
			$html   .= '<p>「お支払いのご案内」は、' . esc_html( $entry['customer']['mailaddress1'] ) . '　宛にメールさせていただいております。</p>';
			$html   .= '</div>';
		}
		return $html;
	}

	/**
	 * 管理画面送信メール
	 * usces_filter_order_confirm_mail_payment
	 *
	 * @param  string $msg_payment Default payment message.
	 * @param  int    $order_id Order number.
	 * @param  array  $payment Payment information.
	 * @param  array  $cart Cart data.
	 * @param  array  $data Order data.
	 * @return string
	 */
	public function order_confirm_mail_payment( $msg_payment, $order_id, $payment, $cart, $data ) {
		if ( 'acting_remise_conv' !== $payment['settlement'] ) {
			return $msg_payment;
		}

		global $usces;
		$remise_x_pay_csv = $usces->get_order_meta_value( 'remise_x_pay_csv', $order_id );
		if ( empty( $remise_x_pay_csv ) ) {
			return $msg_payment;
		}
		$acting_opts    = $this->get_acting_settings();
		$show_x_pay_csv = ( isset( $acting_opts['show_x_pay_csv'] ) && 'on' === $acting_opts['show_x_pay_csv'] ) ? 'on' : 'off';
		if ( 'off' === $show_x_pay_csv ) {
			return $msg_payment;
		}
		if ( ! isset( self::$x_pay_csv_list[ $remise_x_pay_csv ] ) ) {
			return $msg_payment;
		}
		$remise_x_pay_csv_text = '（' . self::$x_pay_csv_list[ $remise_x_pay_csv ]['label'] . '）';

		if ( usces_is_html_mail() ) {
			$msg_payment  = '<tr><td colspan="2" style="padding: 0 0 25px 0;">' . $payment['name']  . $remise_x_pay_csv_text . usces_payment_detail_confirm( $data ) . '</td></tr>';
		} else {
			$msg_payment  = __( '** Payment method **', 'usces' ) . "\r\n";
			$msg_payment .= usces_mail_line( 1, $data['order_email'] ); // ********************
			$msg_payment .= $payment['name'] . $remise_x_pay_csv_text;
			$msg_payment .= "\r\n\r\n";
		}

		return $msg_payment;
	}

	/**
	 * Add payment institution to PDF payment method.
	 *
	 * @param string $payment_name Payment Name..
	 * @param mixed  $data Order data.
	 *
	 * @return mixed|string
	 */
	public function pdf_payment_name( $payment_name, $data ) {
		global $usces;

		$acting_opts    = $this->get_acting_settings();
		$show_x_pay_csv = ( isset( $acting_opts['show_x_pay_csv'] ) && 'on' === $acting_opts['show_x_pay_csv'] ) ? 'on' : 'off';
		if ( 'off' === $show_x_pay_csv ) {
			return $payment_name;
		}

		$remise_x_pay_csv = $usces->get_order_meta_value( 'remise_x_pay_csv', $data->order['ID'] );
		if ( empty( $remise_x_pay_csv ) ) {
			return $payment_name;
		}

		if ( ! isset( self::$x_pay_csv_list[ $remise_x_pay_csv ] ) ) {
			return $payment_name;
		}
		$remise_x_pay_csv_text = '（' . self::$x_pay_csv_list[ $remise_x_pay_csv ]['label'] . '）';

		return $payment_name . $remise_x_pay_csv_text;
	}

	/**
	 * コンビニステータス
	 *
	 * @param  string $code CVS code.
	 * @return string
	 */
	protected function get_remise_conv_return( $code ) {
		switch ( $code ) {
			case 'D001': /* セブンイレブン */
				$html  = '<tr><th>払込番号</th><td>' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO1'] ) ) . '</td></tr>';
				$html .= '<tr><th>払込票のURL</th><td><a href="' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO2'] ) ) . '" target="_blank">' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO2'] ) ) . '</a></td></tr>';
				break;
			case 'D002': /* ローソン */
			case 'D015': /* セイコーマート */
			case 'D405': /* ペイジー */
				$html  = '<tr><th>受付番号</th><td>' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO1'] ) ) . '</td></tr>';
				$html .= '<tr><th>支払方法案内URL</th><td><a href="' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO2'] ) ) . '" target="_blank">' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO2'] ) ) . '</a></td></tr>';
				break;
			case 'D003': /* サンクス */
			case 'D004': /* サークルK */
			case 'D005': /* ミニストップ */
			case 'D010': /* デイリーヤマザキ */
			case 'D011': /* ヤマザキデイリーストア */
				$html  = '<tr><th>決済番号</th><td>' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO1'] ) ) . '</td></tr>';
				$html .= '<tr><th>支払方法案内URL</th><td><a href="' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO2'] ) ) . '" target="_blank">' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO2'] ) ) . '</a></td></tr>';
				break;
			case 'D030': /* ファミリーマート */
				$html  = '<tr><th>コード</th><td>' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO1'] ) ) . '</td></tr>';
				$html .= '<tr><th>注文番号</th><td>' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO2'] ) ) . '</td></tr>';
				break;
			case 'D401': /* CyberEdy */
			case 'D404': /* 楽天銀行 */
			case 'D406': /* ジャパネット銀行 */
			case 'D407': /* Suicaインターネットサービス */
			case 'D451': /* ウェブマネー */
			case 'D452': /* ビットキャッシュ */
			case 'D453': /* JCBプレモカード */
				$html  = '<tr><th>受付番号</th><td>' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO1'] ) ) . '</td></tr>';
				$html .= '<tr><th>支払手続URL</th><td><a href="' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO2'] ) ) . '" target="_blank">' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO2'] ) ) . '</a></td></tr>';
				break;
			case 'P901': /* コンビニ払込票 */
			case 'P902': /* コンビニ払込票（郵便振替対応） */
				$html = '<tr><th>受付番号</th><td>' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO1'] ) ) . '</td></tr>';
				break;
			case 'E201':
			case 'E202':
			case 'M601':
			case 'M602':
			case 'M603':
			case 'M611':
			case 'M612':
			case 'M613':
			case 'M621':
			case 'M622':
			case 'M623':
				$html = '<tr><th>支払手続きURL</th><td><a href="' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO2'] ) ) . '" target="_blank">' . esc_html( wp_unslash( $_REQUEST['X-PAY_NO2'] ) ) . '</a></td></tr>';
				break;
			default:
				$html = '';
		}
		return $html;
	}

	/**
	 * 決済オプション取得
	 *
	 * @return array
	 */
	protected function get_acting_settings() {
		global $usces;

		$acting_settings = ( isset( $usces->options['acting_settings'][ $this->paymod_id ] ) ) ? $usces->options['acting_settings'][ $this->paymod_id ] : array();
		return $acting_settings;
	}

	/**
	 * 契約中の自動継続課金情報
	 *
	 * @param  int $member_id Member ID.
	 * @return int
	 */
	protected function have_member_continue_order( $member_id ) {
		global $wpdb;

		$continue           = 0;
		$continuation_table = $wpdb->prefix . 'usces_continuation';
		$query              = $wpdb->prepare( "SELECT * FROM {$continuation_table} WHERE `con_member_id` = %d AND `con_status` = 'continuation' ORDER BY `con_price` DESC", $member_id );
		$continue_order     = $wpdb->get_results( $query, ARRAY_A );
		if ( $continue_order && 0 < count( $continue_order ) ) {
			$continue = $continue_order[0]['con_order_id'];
		}
		return $continue;
	}
}
