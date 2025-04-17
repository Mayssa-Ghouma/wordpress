<?php
/**
 * When a new member joins, confirm the authenticity of
 * the email address and the identity of the member.
 *
 * The following built-in templates have been added.
 * templates/cart/verifying.php
 * templates/cart/verified.php
 * templates/member/verifying.php
 *
 * @package Welcart
 */

/**
 * Verify Members Email class
 */
class USCES_VERIFY_MEMBERS_EMAIL {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Verifyemail options
	 *
	 * @var array
	 */
	public static $opts;

	/**
	 * Initialization vector key
	 *
	 * @var string
	 */
	public static $verify_key;

	/**
	 * Initialization vector key
	 *
	 * @var string
	 */
	public static $algo;

	/**
	 * Construct
	 */
	public function __construct() {

		self::initialize_data();
		self::$verify_key = 'zMsHTOIF6xRrmaMB';
		self::$algo       = 'AES-128-CBC';

		if ( is_admin() ) {
			add_action( 'usces_action_admin_system_extentions', array( $this, 'setting_form' ) );
			add_action( 'init', array( $this, 'save_data' ) );
		}

		if ( self::$opts['switch_flag'] ) {

			add_action( 'wc_cron', array( $this, 'delete_flag' ) );

			if ( ! is_admin() ) {
				add_filter( 'usces_filter_veirfyemail_newmemberform', array( $this, 'member_registered' ), 10, 2 );
				add_filter( 'usces_filter_veirfyemail_newmemberfromcart', array( $this, 'member_registered' ), 10, 2 );
				add_action( 'usces_main', array( $this, 'verified_email' ) );
			}
		}

		if ( self::$opts['edit_flag'] && usces_is_membersystem_state() ) {

			add_action( 'wc_cron', array( $this, 'delete_edit_flag' ) );

			if ( ! is_admin() ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
				add_action( 'wp_print_styles', array( $this, 'print_styles' ) );
				add_action( 'usces_main', array( $this, 'member_auth_first' ), 1 );
				add_filter( 'usces_filter_template_redirect', array( $this, 'member_auth' ), 1 );
				add_action( 'usces_main', array( $this, 'verified_member' ) );
				add_filter( 'usces_filter_login_inform', array( $this, 'login_inform' ) );
				add_action( 'usces_action_login_page_inform', array( $this, 'login_page_inform' ) );
				add_filter( 'usces_filter_member_submenu_list', array( $this, 'member_submenu_list' ), 99, 2 );
				add_action( 'usces_action_member_logined', array( $this, 'after_login' ) );
			}
		}

		if ( self::$opts['login_notify'] && usces_is_membersystem_state() ) {
			add_action( 'usces_action_after_login', array( $this, 'login_notify' ) );
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
	 * Initialize
	 * Modified:30 Sep.2019
	 */
	public function initialize_data() {
		global $usces;

		$options = get_option( 'usces_ex', array() );
		$options['system']['verifyemail']['switch_flag']  = ! isset( $options['system']['verifyemail']['switch_flag'] ) ? 0 : (int) $options['system']['verifyemail']['switch_flag'];
		$options['system']['verifyemail']['edit_flag']    = ! isset( $options['system']['verifyemail']['edit_flag'] ) ? 1 : (int) $options['system']['verifyemail']['edit_flag'];
		$options['system']['verifyemail']['login_notify'] = ! isset( $options['system']['verifyemail']['login_notify'] ) ? 0 : (int) $options['system']['verifyemail']['login_notify'];
		update_option( 'usces_ex', $options );
		self::$opts = $options['system']['verifyemail'];
	}

	/**
	 * Save option data
	 * Modified:30 Sep.2019
	 */
	public function save_data() {
		global $usces;

		if ( isset( $_POST['usces_verifyemail_option_update'] ) ) {

			check_admin_referer( 'admin_system', 'wc_nonce' );
			if ( ! current_user_can( 'wel_manage_setting' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}

			if ( isset( $_POST['verifyemail_switch_flag'] ) ) {
				self::$opts['switch_flag'] = (int) $_POST['verifyemail_switch_flag'];
			} else {
				self::$opts['switch_flag'] = 0;
			}

			if ( isset( $_POST['verifyemail_edit_flag'] ) ) {
				self::$opts['edit_flag'] = (int) $_POST['verifyemail_edit_flag'];
			} else {
				self::$opts['edit_flag'] = 0;
			}

			if ( isset( $_POST['verifyemail_login_notify'] ) ) {
				self::$opts['login_notify'] = (int) $_POST['verifyemail_login_notify'];
			} else {
				self::$opts['login_notify'] = 0;
			}

			$options                          = get_option( 'usces_ex', array() );
			$options['system']['verifyemail'] = self::$opts;
			update_option( 'usces_ex', $options );

			if ( 0 === self::$opts['edit_flag'] ) {
				$this->delete_edit_flag();
			}
		}
	}

	/**
	 * Setting form
	 * Modified:30 Sep.2019
	 */
	public function setting_form() {
		$status = ( self::$opts['switch_flag'] || self::$opts['edit_flag'] || self::$opts['login_notify'] ) ? '<span class="running">' . __( 'Running', 'usces' ) . '</span>' : '<span class="stopped">' . __( 'Stopped', 'usces' ) . '</span>';
		?>
	<form action="" method="post" name="option_form" id="verifyemail_form">
	<div class="postbox">
		<div class="postbox-header">
			<h2><span><?php esc_html_e( 'Security on membership', 'usces' ); ?></span><?php echo wp_kses_post( $status ); ?></h2>
			<div class="handle-actions"><button type="button" class="handlediv" id="verifyemail"><span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Toggle panel: %s' ), __( 'Security on membership', 'usces' ) ) ); ?></span><span class="toggle-indicator"></span></button></div>
		</div>
		<div class="inside">
		<table class="form_table">
			<tr height="35">
				<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_verifyemail_switch_flag');"><?php esc_html_e( 'Email verification for new registration', 'usces' ); ?></a></th>
				<td width="10"><input name="verifyemail_switch_flag" id="verifyemail_switch_flag0" type="radio" value="0"<?php checked( self::$opts['switch_flag'], 0 ); ?> /></td><td width="100"><label for="verifyemail_switch_flag0"><?php esc_html_e( 'disable', 'usces' ); ?></label></td>
				<td width="10"><input name="verifyemail_switch_flag" id="verifyemail_switch_flag1" type="radio" value="1"<?php checked( self::$opts['switch_flag'], 1 ); ?> /></td><td width="100"><label for="verifyemail_switch_flag1"><?php esc_html_e( 'enable', 'usces' ); ?></label></td>
				<td><div id="ex_verifyemail_switch_flag" class="explanation"><?php esc_html_e( 'Send an e-mail to the e-mail address registered when new member registration, and make them approve that the e-mail address is definitely their own.', 'usces' ); ?></div></td>
			</tr>
			<tr height="35">
				<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_verifyemail_edit_flag');"><?php esc_html_e( 'Email verification for editing information', 'usces' ); ?></a></th>
				<td width="10"><input name="verifyemail_edit_flag" id="verifyemail_edit_flag0" type="radio" value="0"<?php checked( self::$opts['edit_flag'], 0 ); ?> /></td><td width="100"><label for="verifyemail_edit_flag0"><?php esc_html_e( 'disable', 'usces' ); ?></label></td>
				<td width="10"><input name="verifyemail_edit_flag" id="verifyemail_edit_flag1" type="radio" value="1"<?php checked( self::$opts['edit_flag'], 1 ); ?> /></td><td width="100"><label for="verifyemail_edit_flag1"><?php esc_html_e( 'enable', 'usces' ); ?></label></td>
				<td><div id="ex_verifyemail_edit_flag" class="explanation"><?php esc_html_e( "When editing membership information, an email will be sent to the member's email address to confirm that the change is indeed made by the member. Membership information cannot be edited unless the authentication link is clicked.", 'usces' ); ?></div></td>
			</tr>
			<tr height="35">
				<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_verifyemail_login_notify');"><?php esc_html_e( 'Login Notification', 'usces' ); ?></a></th>
				<td width="10"><input name="verifyemail_login_notify" id="verifyemail_login_notify0" type="radio" value="0"<?php checked( self::$opts['login_notify'], 0 ); ?> /></td><td width="100"><label for="verifyemail_login_notify0"><?php esc_html_e( 'disable', 'usces' ); ?></label></td>
				<td width="10"><input name="verifyemail_login_notify" id="verifyemail_login_notify1" type="radio" value="1"<?php checked( self::$opts['login_notify'], 1 ); ?> /></td><td width="100"><label for="verifyemail_login_notify1"><?php esc_html_e( 'enable', 'usces' ); ?></label></td>
				<td><div id="ex_verifyemail_login_notify" class="explanation"><?php esc_html_e( 'An email will be sent to the member notifying them that they have logged in.', 'usces' ); ?></div></td>
			</tr>
		</table>
		<hr />
		<input name="usces_verifyemail_option_update" type="submit" class="button button-primary" value="<?php esc_attr_e( 'change decision', 'usces' ); ?>" />
		</div>
	</div><!--postbox-->
		<?php wp_nonce_field( 'admin_system', 'wc_nonce' ); ?>
	</form>
		<?php
	}

	/**
	 * Member Registration
	 * usces_filter_veirfyemail_newmemberform
	 * usces_filter_veirfyemail_newmemberfromcart
	 * Modified:30 Sep.2019
	 *
	 * @param bool  $ob Switch.
	 * @param array $member Member data.
	 * @return bool
	 */
	public function member_registered( $ob, $member ) {
		global $usces;

		$time = current_time( 'timestamp' );
		$usces->set_member_meta_value( '_verifying', $time, $member['ID'] );
		$member_regmode = wp_unslash( $_POST['member_regmode'] );

		if ( 'newmemberform' === $member_regmode ) {
			add_filter( 'usces_filter_template_redirect', array( $this, 'start_verifying' ) );
		} elseif ( 'newmemberfromcart' === $member_regmode ) {
			add_filter( 'usces_filter_template_redirect', array( $this, 'start_verifying_cart' ) );
			$member['flat_pass'] = wp_unslash( $_POST['customer']['password1'] );
		}

		remove_filter( 'usces_filter_template_redirect', 'dlseller_filter_template_redirect', 2 );

		$member['regmode'] = $member_regmode;
		$this->send_verifymail( $member );
		unset( $_SESSION['usces_member'] );

		return true;
	}

	/**
	 * Start verifying
	 * Modified:30 Sep.2019
	 */
	public function start_verifying() {
		global $usces;

		if ( file_exists( get_theme_file_path( '/wc_templates/member/wc_member_verifying.php' ) ) ) {
			include get_theme_file_path( '/wc_templates/member/wc_member_verifying.php' );
			exit;
		}

		return true;
	}

	/**
	 * Start verifying
	 * Modified:30 Sep.2019
	 */
	public function start_verifying_cart() {
		global $usces;

		if ( file_exists( get_theme_file_path( '/wc_templates/cart/wc_cart_verifying.php' ) ) ) {
			include get_theme_file_path( '/wc_templates/cart/wc_cart_verifying.php' );
			exit;
		}

		return true;
	}

	/**
	 * Send verifymail
	 * Modified:30 Sep.2019
	 *
	 * @param array $user User data.
	 * @return bool
	 */
	public function send_verifymail( $user ) {
		global $usces;

		$remaining_hour    = $this->get_remaining_hour();
		$res               = false;
		$newmem_admin_mail = $usces->options['newmem_admin_mail'];
		$name              = sprintf( _x( '%s', 'honorific', 'usces' ), usces_localized_name( trim( $user['name1'] ), trim( $user['name2'] ), 'return' ) );
		$mailaddress1      = trim( $user['mailaddress1'] );

		$subject  = apply_filters( 'usces_filter_send_verifymembermail_subject', __( 'Request email confirmation', 'usces' ), $user );
		$message  = sprintf( __( 'Thank you for registering to %s.', 'usces' ), get_option( 'blogname' ) ) . "\r\n\r\n";
		$message .= __( 'By accessing the following URL, you can complete membership registration.', 'usces' ) . "\r\n";
		$message .= __( 'Please note that the procedure has not been completed until you receive registration complete e-mail.', 'usces' ) . "\r\n\r\n";
		$message .= sprintf( __( 'The following URL is valid for %d hours. If it expires, please try again from the beginning.', 'usces' ), $remaining_hour ) . "\r\n\r\n";
		$message .= $this->get_verify_url( $user ) . "\r\n\r\n";

		$message .= '--------------------------------' . "\r\n";
		$message .= __( 'Please delete this email if you were not aware that you were going to receive it.', 'usces' ) . "\r\n";
		$message .= '--------------------------------' . "\r\n\r\n";
		$message .= get_option( 'blogname' ) . "\r\n";
		if ( 1 === (int) $usces->options['put_customer_name'] ) {
			$dear_name = sprintf( __( 'Dear %s', 'usces' ), usces_localized_name( trim( $user['name1'] ), trim( $user['name2'] ), 'return' ) );
			$message   = $dear_name . "\r\n\r\n" . $message;
		}
		$message = apply_filters( 'usces_filter_send_verifymembermail_message', $message, $user );

		$para1 = array(
			'to_name'      => $name,
			'to_address'   => $mailaddress1,
			'from_name'    => get_option( 'blogname' ),
			'from_address' => $usces->options['sender_mail'],
			'reply_name'   => get_option( 'blogname' ),
			'reply_to'     => usces_get_first_order_mail(),
			'return_path'  => $usces->options['sender_mail'],
			'subject'      => $subject,
			'message'      => do_shortcode( $message ),
		);
		$para1 = apply_filters( 'usces_filter_send_verifymembermail_para1', $para1 );
		$res   = usces_send_mail( $para1 );
		return $res;
	}

	/**
	 * Get verify url
	 * Modified:30 Sep.2019
	 *
	 * @param array $user User data.
	 * @return string
	 */
	public function get_verify_url( $user ) {
		global $usces;

		$encrypt_value = $this->encrypt_value( $user );
		$query         = array(
			'verify'     => $encrypt_value,
			'usces_page' => 'memberverified',
			'uscesid'    => $usces->get_uscesid( false ),
		);
		$query         = apply_filters( 'usces_filter_verifymail_query', $query, $user );
		$url           = add_query_arg( $query, USCES_MEMBER_URL );

		return $url;
	}

	/**
	 * Encrypt value
	 * Modified:30 Sep.2019
	 *
	 * @param array $user User data.
	 * @return array
	 */
	public function encrypt_value( $user ) {
		if ( isset( $user['auth_code'] ) ) {
			$datatext = $user['auth_code'];
		} else {
			$datatext = $user['regmode'] . ',' . $user['ID'];
		}
		$key           = get_option( 'usces_wcid' );
		$encrypt_value = openssl_encrypt( $datatext, self::$algo, $key, OPENSSL_RAW_DATA, self::$verify_key );
		$encrypt_value = urlencode( base64_encode( $encrypt_value ) );

		return $encrypt_value;
	}

	/**
	 * Decrypt value
	 * Modified:30 Sep.2019
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public function decrypt_value( $value ) {
		$key  = get_option( 'usces_wcid' );
		$data = base64_decode( $value );
		$data = openssl_decrypt( $data, self::$algo, $key, OPENSSL_RAW_DATA, self::$verify_key );

		return $data;
	}

	/**
	 * Verified email
	 * usces_main
	 * Modified:30 Sep.2019
	 */
	public function verified_email() {
		global $usces;

		if ( ! $usces->is_cart_or_member_page( $_SERVER['REQUEST_URI'] ) || ! isset( $_GET['verify'] ) ) {
			return;
		}

		usces_register_action( 'page_allow_email', 'get', 'usces_page', 'memberverified', array( $this, 'allow_email' ) );
		add_filter( 'usces_filter_template_redirect', array( $this, 'complete_verifying' ) );
		remove_filter( 'usces_filter_template_redirect', 'dlseller_filter_template_redirect', 2 );
	}

	/**
	 * Allow email
	 * Modified:30 Sep.2019
	 */
	public function allow_email() {
		global $usces;

		if ( ! $usces->is_cart_or_member_page( $_SERVER['REQUEST_URI'] ) || ! isset( $_GET['verify'] ) ) {
			return;
		}

		$value    = wp_unslash( $_GET['verify'] );
		$datatext = $this->decrypt_value( $value );
		$data     = explode( ',', $datatext );
		$regmode  = $data[0];
		$mem_id   = (int) $data[1];
		$member   = $usces->get_member_info( $mem_id );
		$flag     = $usces->get_member_meta_value( '_verifying', $mem_id );
		if ( empty( $member ) || empty( $flag ) ) {
			wp_redirect( USCES_MEMBER_URL );
			exit;
		}

		$user = array();
		foreach ( $member as $key => $value ) {
			if ( 'mem_email' === $key ) {
				$user['mailaddress1'] = $value;
			} elseif ( 'mem_name1' === $key ) {
				$user['name1'] = $value;
			} elseif ( 'mem_name2' === $key ) {
				$user['name2'] = $value;
			} elseif ( 'mem_name3' === $key ) {
				$user['name3'] = $value;
			} elseif ( 'mem_name4' === $key ) {
				$user['name4'] = $value;
			} elseif ( 'mem_zip' === $key ) {
				$user['zipcode'] = $value;
			} elseif ( 'customer_country' === $key ) {
				$user['country'] = $value;
			} elseif ( 'mem_pref' === $key ) {
				$user['pref'] = $value;
			} elseif ( 'mem_address1' === $key ) {
				$user['address1'] = $value;
			} elseif ( 'mem_address2' === $key ) {
				$user['address2'] = $value;
			} elseif ( 'mem_address3' === $key ) {
				$user['address3'] = $value;
			} elseif ( 'mem_tel' === $key ) {
				$user['tel'] = $value;
			} elseif ( 'mem_fax' === $key ) {
				$user['fax'] = $value;
			} else {
				$user[ $key ] = $value;
			}
		}

		$usces->del_member_meta( '_verifying', $mem_id );

		do_action( 'usces_action_member_registered', $user, $mem_id );
		usces_send_regmembermail( $user );

		if ( 'newmemberform' === $regmode ) {
			unset( $_SESSION['usces_member'] );
			$usces->page = 'newcompletion';
			add_action( 'the_post', array( $usces, 'action_memberFilter' ) );
			add_action( 'template_redirect', array( $usces, 'template_redirect' ) );

			do_action( 'usces_action_after_newmemberform_verified', $user, $data );

		} elseif ( 'newmemberfromcart' === $regmode ) {
			unset( $_SESSION['usces_entry']['customer'] );
			$usces->page = 'cartverified';
			add_action( 'the_post', array( $usces, 'action_cartFilter' ) );
			add_action( 'template_redirect', array( $usces, 'template_redirect' ) );

			do_action( 'usces_action_after_newmemberfromcart_verified', $user, $data );
		}
	}

	/**
	 * Complete verifying
	 * Modified:30 Sep.2019
	 */
	public function complete_verifying() {
		global $usces;

		if ( file_exists( get_theme_file_path( '/wc_templates/cart/wc_cart_verified.php' ) ) ) {
			include get_theme_file_path( '/wc_templates/cart/wc_cart_verified.php' );
			exit;
		}

		return true;
	}

	/**
	 * Get remaining hour
	 * Modified:30 Sep.2019
	 */
	public static function get_remaining_hour() {
		$crons          = _get_cron_array();
		$remaining_time = 0;
		foreach ( $crons as $time => $cron ) {
			foreach ( $cron as $key => $value ) {
				if ( 'wc_cron' === $key ) {
					$remaining_time = $time - current_time( 'timestamp', 1 );
				}
			}
		}
		$remaining_hour = floor( $remaining_time / 3600 );
		if ( 1 > $remaining_hour ) {
			$remaining_hour = 1;
		}

		return $remaining_hour;
	}

	/**
	 * 'wc_cron' event
	 * Modified:30 Sep.2019
	 */
	public function delete_flag() {
		global $wpdb;

		$table   = usces_get_tablename( 'usces_member_meta' );
		$mem_ids = $wpdb->get_col( $wpdb->prepare( "SELECT `member_id` FROM {$table} WHERE `meta_key` = %s", '_verifying' ) );

		foreach ( (array) $mem_ids as $mem_id ) {
			usces_delete_memberdata( $mem_id );
		}
	}

	/**
	 * Delete expired authentication flags.
	 */
	public function delete_edit_flag() {
		global $usces, $wpdb;

		$member_meta_table = usces_get_tablename( 'usces_member_meta' );
		$member_ids        = $wpdb->get_col( $wpdb->prepare( "SELECT `member_id` FROM {$member_meta_table} WHERE `meta_key` = %s", 'auth_url_creation_time' ) );
		if ( ! empty( $member_ids ) ) {
			$now = current_time( 'timestamp' );
			foreach ( (array) $member_ids as $member_id ) {
				$time = $usces->get_member_meta_value( 'auth_url_creation_time', $member_id );
				if ( $time < $now ) {
					$usces->del_member_meta( 'auth_url_creation_time', $member_id );
					$usces->del_member_meta( 'member_edit_auth_code', $member_id );
				}
			}
		}
	}

	/**
	 * Member information editing page.
	 *
	 * @return bool
	 */
	private function is_member_edit_page() {
		global $usces;

		$member_edit = false;
		if ( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ) {
			$usces_page = ( ! empty( $usces->page ) ) ? $usces->page : filter_input( INPUT_GET, 'usces_page', FILTER_DEFAULT );
			switch ( $usces_page ) {
				case 'newmember':
				case 'member_auth_error':
				case 'member_register_settlement':
				case 'member_update_settlement':
				case 'member_auto_billing_info':
				case 'member_favorite_page':
				case 'autodelivery_history':
				case 'msa_setting':
				case 'mda_setting':
					$member_edit = false;
					break;
				default:
					$member_edit = true;
			}
		}
		return $member_edit;
	}

	/**
	 * Front scripts.
	 * wp_enqueue_scripts
	 */
	public function enqueue_scripts() {
		global $usces;

		if ( $this->is_member_edit_page() ) {
			$member       = $usces->get_member();
			$edit_url     = add_query_arg(
				array(
					'usces_page' => 'member_edit',
				),
				USCES_MEMBER_URL
			);
			$card_reg_url = add_query_arg(
				array(
					'usces_page' => 'member_register_settlement',
					're-enter'   => 1,
				),
				USCES_MEMBER_URL
			);
			$card_upd_url = add_query_arg(
				array(
					'usces_page' => 'member_update_settlement',
					're-enter'   => 1,
				),
				USCES_MEMBER_URL
			);
			wp_register_script( 'wel-member-auth', USCES_FRONT_PLUGIN_URL . '/js/member_auth.js', array( 'jquery' ), USCES_VERSION, true );
			$member_params                        = array();
			$member_params['url']['go2top']       = esc_url( home_url() );
			$member_params['url']['edit']         = $edit_url;
			$member_params['url']['card_reg']     = $card_reg_url;
			$member_params['url']['card_upd']     = $card_upd_url;
			$member_params['label']['go2top']     = esc_html__( 'Back to the top page.', 'usces' );
			$member_params['label']['edit']       = esc_html__( 'To member information editing', 'usces' );
			$member_params['message']['edit']     = esc_html__( 'Identity verification is required to edit your membership information. Are you sure you want to send an authentication email to your registered email address?', 'usces' );
			$member_params['message']['card_reg'] = esc_html__( 'Identity verification is required to register credit card information. Are you sure you want to send an authentication email to your registered email address?', 'usces' );
			$member_params['message']['card_upd'] = esc_html__( 'Identity verification is required to change credit card information. Are you sure you want to send an authentication email to your registered email address?', 'usces' );
			$member_params['message']['done']     = esc_html__( 'Successfully updated.', 'usces' );
			$member_params['edit_auth']           = ( isset( $member['edit_auth'] ) ) ? $member['edit_auth'] : '';
			wp_localize_script( 'wel-member-auth', 'member_params', $member_params );
			wp_enqueue_script( 'wel-member-auth' );
		}
	}

	/**
	 * Front styles.
	 * wp_print_styles
	 */
	public function print_styles() {
		if ( $this->is_member_edit_page() ) :
			?>
<style type="text/css">
.send {
	padding-top: .714286em;
	text-align: center;
}
</style>
			<?php
		endif;
	}

	/**
	 * Authenticate first.
	 * usces_main
	 */
	public function member_auth_first() {
		global $usces;

		if ( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ) {
			if ( ! usces_is_login() ) {
				return;
			}
			$usces_page = ( ! empty( $usces->page ) ) ? $usces->page : filter_input( INPUT_GET, 'usces_page', FILTER_DEFAULT );
			switch ( $usces_page ) {
				case 'member_register_settlement':
				case 'member_update_settlement':
					$member = $usces->get_member();
					if ( ! $this->member_auth_check( $member ) ) {
						$usces->page = 'member_edit';
						$url         = add_query_arg(
							array(
								'usces_page'    => 'member_edit',
								'usces_subpage' => $usces_page,
							),
							USCES_MEMBER_URL
						);
						wp_safe_redirect( $url );
						exit();
					}
					break;

				case 'member_auto_billing_info':
				case 'member_favorite_page':
				case 'autodelivery_history':
				case 'msa_setting':
				case 'mda_setting':
					break;
				default:
			}
		}
	}

	/**
	 * Authenticate Membership.
	 * usces_filter_template_redirect
	 */
	public function member_auth() {
		global $usces;

		if ( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ) {
			$usces_page = filter_input( INPUT_GET, 'usces_page', FILTER_DEFAULT );
			if ( 'member_auth_error' === $usces_page ) {
				$this->member_verifying_error_form();
				exit();
			}
			if ( ! usces_is_login() ) {
				return false;
			}
			if ( 'member_edit' !== $usces_page && 'member_edit' !== $usces->page ) {
				return false;
			}

			add_filter( 'usces_filter_states_form_js', array( $this, 'states_form_js' ) );

			$member = $usces->get_member();
			if ( ! $this->member_auth_check( $member ) ) {
				$subpage = filter_input( INPUT_GET, 'usces_subpage', FILTER_DEFAULT );
				$now     = current_time( 'timestamp' );
				$time    = strtotime( '+15 minutes', $now );
				$usces->set_member_meta_value( 'auth_url_creation_time', $time, $member['ID'] );
				$auth_code = $this->generate_auth_code();
				$usces->set_member_meta_value( 'member_edit_auth_code', $auth_code, $member['ID'] );
				$member['regmode']   = 'updatememberform';
				$member['auth_code'] = $auth_code;
				$this->send_verify_edit_email( $member, $time );
				$this->member_edit_verifying_form();
				exit();
			} else {
				$usces->page = 'member_edit';
				$this->member_edit_form( $member );
				exit();
			}
		}

		return false;
	}

	/**
	 * Authentication Code Generation.
	 *
	 * @return string
	 */
	public function generate_auth_code() {
		$characters       = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$characterslength = strlen( $characters );
		$auth_code        = '';
		for ( $i = 0; $i < 24; $i++ ) {
			$auth_code .= $characters[ wp_rand( 0, $characterslength - 1 ) ];
		}
		return $auth_code;
	}

	/**
	 * Disable hook.
	 * usces_filter_states_form_js
	 *
	 * @param string $js Scripts.
	 * @return string
	 */
	public function states_form_js( $js ) {
		return '';
	}

	/**
	 * Send member verification email.
	 *
	 * @param array $member Member data.
	 * @param int   $time Remaining time in minutes.
	 */
	public function send_verify_edit_email( $member, $time ) {
		global $usces;

		$name         = usces_localized_name( trim( $member['name1'] ), trim( $member['name2'] ), 'return' );
		$mailaddress1 = trim( $member['mailaddress1'] );

		$subject = apply_filters( 'usces_filter_send_verifyeditmail_subject', __( 'Identity Verification Email', 'usces' ), $member );
		$totime  = date( __( 'Y-n-j G:i', 'usces' ), $time );

		$message  = sprintf( __( 'This email is a verification email for updating your membership information at "%s".', 'usces' ), get_option( 'blogname' ) ) . "\r\n";
		$message .= __( 'By clicking on the URL below, your identity will be authenticated.', 'usces' ) . "\r\n";
		$message .= __( 'After authentication, My Page will be displayed, so please click the menu again to proceed.', 'usces' ) . "\r\n";
		$message .= sprintf( __( 'Please note that this URL is valid until %s.', 'usces' ), $totime ) . "\r\n\r\n";
		$message .= $this->get_verify_edit_url( $member ) . "\r\n\r\n";

		$message .= __( 'Please delete this email if you were not aware that you were going to receive it.', 'usces' ) . "\r\n\r\n";
		$message .= get_option( 'blogname' ) . "\r\n";
		if ( 1 === (int) $usces->options['put_customer_name'] ) {
			// translators: %s: name of user.
			$dear_name = sprintf( __( 'Dear %s', 'usces' ), $name );
			$message   = $dear_name . "\r\n\r\n" . $message;
		}
		$message = apply_filters( 'usces_filter_send_verifyeditmail_message', $message, $member );

		$para1 = array(
			'to_name'      => sprintf( _x( '%s', 'honorific', 'usces' ), $name ),
			'to_address'   => $mailaddress1,
			'from_name'    => get_option( 'blogname' ),
			'from_address' => $usces->options['sender_mail'],
			'reply_name'   => get_option( 'blogname' ),
			'reply_to'     => usces_get_first_order_mail(),
			'return_path'  => $usces->options['sender_mail'],
			'subject'      => $subject,
			'message'      => do_shortcode( $message ),
		);
		$para1 = apply_filters( 'usces_filter_send_verifyeditmail_para1', $para1 );
		usces_send_mail( $para1 );
	}

	/**
	 * URL for member verification.
	 *
	 * @param array $member Member data.
	 * @return string
	 */
	private function get_verify_edit_url( $member ) {
		global $usces;

		$encrypt_value = $this->encrypt_value( $member );
		$query         = array(
			'member_edit_auth_code' => $encrypt_value,
		);
		$query         = apply_filters( 'usces_filter_verifyeditmail_query', $query, $member );
		$url           = add_query_arg( $query, USCES_MEMBER_URL );

		return $url;
	}

	/**
	 * Member Authentication Check.
	 *
	 * @param array $member Member data.
	 * @return bool
	 */
	public function member_auth_check( $member ) {
		if ( empty( $member['edit_auth'] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Authentication mail transmission completion page.
	 */
	public function member_edit_verifying_form() {
		get_header();
		ob_start();
		?>
<div class="storycontent">
<div id="primary" class="site-content">
<main id="content" class="inner_block member-page" role="main">
		<?php
		if ( have_posts() ) :
			usces_remove_filter();
			?>
	<article class="post wc_member" id="wc_member">
		<h1 class="member_page_title"><?php esc_html_e( 'Verifying', 'usces' ); ?></h1>

		<div id="memberpages">
		<div class="whitebox">
		<div id="memberedit">

		<div class="header_explanation">
			<?php echo apply_filters( 'usces_filter_memberverifying_page_header', '' ); ?>
		</div>

		<h2><?php esc_html_e( 'Authentication email sent.', 'usces' ); ?></h2>
		<p><?php esc_html_e( 'When updating important information, you will need to authenticate your identity by email.', 'usces' ); ?></p>
		<p><?php esc_html_e( 'You will receive an authentication email to your registered email address. Please click the URL in the body of the email to approve it.', 'usces' ); ?></p>
		<p><?php esc_html_e( 'The URL for authentication expires in 15 minutes from now.', 'usces' ); ?></p>
		<p><?php esc_html_e( 'If the expiration date has passed, please click the menu again to send the authentication email.', 'usces' ); ?></p>

		<div class="footer_explanation">
			<?php echo apply_filters( 'usces_filter_memberverifying_page_footer', '' ); ?>
		</div>

		<div class="send">
			<input type="button" name="back" class="top back" value="<?php esc_attr_e( 'Back', 'usces' ); ?>" onclick="location.href='<?php echo esc_url( USCES_MEMBER_URL ); ?>'" />
		</div>

		</div><!-- #memberedit -->
		</div><!-- .whitebox -->
		</div><!-- #memberpages -->
	</article><!-- .post .wc_member #wc_member -->
</main><!-- #content .inner_block .member-page -->
</div><!-- #primary .site-content -->
</div><!-- .storycontent -->
			<?php
		endif;
		$contents = ob_get_contents();
		ob_end_clean();
		$contents = apply_filters( 'usces_filter_member_edit_verifying_form', $contents );
		echo $contents; // no escape.
		get_footer();
	}

	/**
	 * Authentication mail transmission completion page.
	 */
	public function member_verifying_error_form() {
		$auth_error = filter_input( INPUT_GET, 'auth_error', FILTER_DEFAULT );
		get_header();
		ob_start();
		?>
<div class="storycontent">
<div id="primary" class="site-content">
<main id="content" class="inner_block member-page" role="main">
		<?php
		if ( have_posts() ) :
			usces_remove_filter();
			?>
	<article class="post wc_member" id="wc_member">
		<h1 class="member_page_title"><?php esc_html_e( 'Verifying', 'usces' ); ?></h1>

		<div id="memberpages">
		<div class="whitebox">
		<div id="memberedit">

		<div class="header_explanation">
			<?php echo apply_filters( 'usces_filter_memberverifying_error_page_header', '' ); ?>
		</div>

		<h2><?php esc_html_e( 'Email Authentication Error', 'usces' ); ?></h2>
			<?php if ( 0 < $auth_error ) : ?>
		<p><?php esc_html_e( 'Authentication by email has failed.', 'usces' ); ?></p>
			<?php endif; ?>
		<p><?php esc_html_e( 'Authentication URL has expired or invalid access.', 'usces' ); ?></p>

		<div class="footer_explanation">
			<?php echo apply_filters( 'usces_filter_memberverifying_error_page_footer', '' ); ?>
		</div>

		<div class="send">
			<input type="button" name="back" class="top back" value="<?php esc_attr_e( 'Back to the member page.', 'usces' ); ?>" onclick="location.href='<?php echo esc_url( USCES_MEMBER_URL ); ?>'" />
		</div>

		</div><!-- #memberedit -->
		</div><!-- .whitebox -->
		</div><!-- #memberpages -->
	</article><!-- .post .wc_member #wc_member -->
</main><!-- #content .inner_block .member-page -->
</div><!-- #primary .site-content -->
</div><!-- .storycontent -->
			<?php
		endif;
		$contents = ob_get_contents();
		ob_end_clean();
		$contents = apply_filters( 'usces_filter_member_verifying_error_form', $contents, $auth_error );
		echo $contents; // no escape.
		get_footer();
	}

	/**
	 * Member information editing page.
	 *
	 * @param array $member Member data.
	 */
	public function member_edit_form( $member ) {
		global $usces;

		$update = '';
		if ( empty( $usces->error_message ) && isset( $_POST['editmember'] ) ) {
			$update = 'update';
		}

		get_header();
		ob_start();
		?>
<div class="storycontent">
<div id="primary" class="site-content">
<main id="content" class="inner_block member-page" role="main">
		<?php
		if ( have_posts() ) :
			usces_remove_filter();
			?>
	<article class="post wc_member" id="wc_member">
		<h1 class="member_page_title"><?php esc_html_e( 'Member information editing', 'usces' ); ?></h1>

		<div id="memberpages">
		<div class="whitebox">
		<div id="memberedit">

		<div class="error_message"><?php usces_error_message(); ?></div>

		<form action="<?php echo esc_url( USCES_MEMBER_URL ); ?>#edit" method="post" onKeyDown="if (event.keyCode == 13) {return false;}">
			<table class="customer_form">

				<?php uesces_addressform( 'member', $member, 'echo' ); ?>

				<tr>
					<th scope="row"><?php esc_html_e( 'e-mail adress', 'usces' ); ?></th>
					<td colspan="2"><input name="member[mailaddress1]" id="mailaddress1" type="text" value="<?php echo esc_attr( $member['mailaddress1'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'E-mail address (for verification)', 'usces' ); ?></th>
					<td colspan="2"><input name="member[mailaddress2]" id="mailaddress2" type="text" autocomplete="off" />
					<?php esc_html_e( 'Leave it blank in case of no change.', 'usces' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'password', 'usces' ); ?></th>
					<td colspan="2">
					<input name="member[password1]" id="password1" type="password" readonly />
					<?php esc_html_e( 'Leave it blank in case of no change.', 'usces' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Password (confirm)', 'usces' ); ?></th>
					<td colspan="2">
					<input name="member[password2]" id="password2" type="password" readonly />
					<?php esc_html_e( 'Leave it blank in case of no change.', 'usces' ); ?></td>
				</tr>
			</table>

			<input name="member_regmode" type="hidden" value="updatememberform" />
			<input name="member_id" type="hidden" value="<?php echo esc_attr( $member['ID'] ); ?>" />
			<input id="update" type="hidden" value="<?php echo esc_attr( $update ); ?>" />
			<div class="send">
				<input type="button" name="top" class="top" value="<?php esc_attr_e( 'Back to the top page.', 'usces' ); ?>" onclick="location.href='<?php echo esc_url( home_url() ); ?>'" />
				<input type="button" name="back" class="top back" value="<?php esc_attr_e( 'Back to the member page.', 'usces' ); ?>" onclick="location.href='<?php echo esc_url( USCES_MEMBER_URL ); ?>'" />
				<input type="submit" name="editmember" class="editmember" value="<?php esc_html_e( 'update it', 'usces' ); ?>" />
				<input type="submit" name="deletemember" class="deletemember" value="<?php esc_html_e( 'delete it', 'usces' ); ?>" onclick="return confirm('<?php esc_attr_e( 'All information about the member is deleted. Are you all right?', 'usces' ); ?>');" />
				<?php $noncekey = 'post_member' . $usces->get_uscesid( false ); ?>
				<?php wp_nonce_field( $noncekey, 'wc_nonce' ); ?>
			</div><!-- .send -->
		</form>

		</div><!-- #memberedit -->
		</div><!-- .whitebox -->
		</div><!-- #memberpages -->
	</article><!-- .post .wc_member #wc_member -->
</main><!-- #content .inner_block .member-page -->
</div><!-- #primary .site-content -->
</div><!-- .storycontent -->
			<?php
		endif;
		$contents = ob_get_contents();
		ob_end_clean();
		$contents = apply_filters( 'usces_filter_member_edit_form', $contents, $member, $update );
		echo $contents; // no escape.
		get_footer();
	}

	/**
	 * Verify membership with an authentication code.
	 *
	 * @param string $auth_code Authentication Code.
	 */
	private function get_member_id_by_auth_code( $auth_code ) {
		global $wpdb;

		$member_meta_table = usces_get_tablename( 'usces_member_meta' );
		$member_id         = $wpdb->get_var( $wpdb->prepare( "SELECT `member_id` FROM {$member_meta_table} WHERE `meta_key` = %s AND `meta_value` = %s", 'member_edit_auth_code', $auth_code ) );
		return $member_id;
	}

	/**
	 * Verify Membership.
	 * usces_main
	 */
	public function verified_member() {
		global $usces;

		if ( ! $usces->is_member_page( $_SERVER['REQUEST_URI'] ) || ! isset( $_GET['member_edit_auth_code'] ) ) {
			return;
		}

		$value     = wp_unslash( $_GET['member_edit_auth_code'] );
		$auth_code = $this->decrypt_value( $value );
		$member_id = $this->get_member_id_by_auth_code( $auth_code );

		if ( empty( $member_id ) && usces_is_login() ) {
			$member = $usces->get_member();
			if ( isset( $member['edit_auth'] ) ) {
				$loggedin_member_id = $this->get_member_id_by_auth_code( $member['edit_auth'] );
				if ( (int) $member['ID'] === (int) $loggedin_member_id ) {
					wp_safe_redirect(
						add_query_arg(
							array(
								'usces_page' => 'member_auth_error',
								'auth_error' => '0',
							),
							USCES_MEMBER_URL
						)
					);
					exit();
				}
			}
		}

		$member_info = $usces->get_member_info( $member_id );
		$time        = $usces->get_member_meta_value( 'auth_url_creation_time', $member_id );
		$now         = current_time( 'timestamp' );
		if ( empty( $member_info ) || empty( $time ) || $time < $now ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'usces_page' => 'member_auth_error',
						'auth_error' => '1',
					),
					USCES_MEMBER_URL
				)
			);
			exit();
		}

		if ( usces_is_login() ) {
			$member = $usces->get_member();
			if ( (int) $member['ID'] !== (int) $member_id ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'usces_page' => 'member_auth_error',
							'auth_error' => '2',
						),
						USCES_MEMBER_URL
					)
				);
				exit();
			}

			if ( isset( $_SESSION['usces_member']['edit_auth'] ) ) {
				if ( $_SESSION['usces_member']['edit_auth'] !== $auth_code ) {
					wp_safe_redirect(
						add_query_arg(
							array(
								'usces_page' => 'member_auth_error',
								'auth_error' => '3',
							),
							USCES_MEMBER_URL
						)
					);
					exit();
				}
			}

			$_SESSION['usces_member']['edit_auth'] = $auth_code;

			$usces->page = 'member';
			add_action( 'the_post', array( $usces, 'action_memberFilter' ) );
			add_action( 'template_redirect', array( $usces, 'template_redirect' ) );

		} else {
			$usces->page = 'login';
			add_action( 'the_post', array( $usces, 'action_memberFilter' ) );
			add_action( 'template_redirect', array( $usces, 'template_redirect' ) );
		}
	}

	/**
	 * Member Login.
	 * usces_filter_login_inform
	 *
	 * @param string $form HTML Form.
	 * @return string
	 */
	public function login_inform( $form ) {
		global $usces;

		if ( isset( $_SERVER['QUERY_STRING'] ) ) {
			parse_str( $_SERVER['QUERY_STRING'], $query );
			if ( isset( $query['member_edit_auth_code'] ) ) {
				$auth_code = $this->decrypt_value( $query['member_edit_auth_code'] );
				$form     .= '<input type="hidden" name="memeditauth" value="' . esc_attr( $auth_code ) . '">';
			} elseif ( isset( $_POST['memeditauth'] ) ) {
				$form .= '<input type="hidden" name="memeditauth" value="' . esc_attr( $_POST['memeditauth'] ) . '">';
			}
		} elseif ( isset( $_POST['memeditauth'] ) ) {
			$form .= '<input type="hidden" name="memeditauth" value="' . esc_attr( $_POST['memeditauth'] ) . '">';
		}
		return $form;
	}

	/**
	 * Member Login.
	 * usces_action_login_page_inform
	 */
	public function login_page_inform() {
		global $usces;

		if ( isset( $_SERVER['QUERY_STRING'] ) ) {
			parse_str( $_SERVER['QUERY_STRING'], $query );
			if ( isset( $query['member_edit_auth_code'] ) ) {
				$auth_code = $this->decrypt_value( $query['member_edit_auth_code'] );
				echo '<input type="hidden" name="memeditauth" value="' . esc_attr( $auth_code ) . '">';
			} elseif ( isset( $_POST['memeditauth'] ) ) {
				echo '<input type="hidden" name="memeditauth" value="' . esc_attr( $_POST['memeditauth'] ) . '">';
			}
		} elseif ( isset( $_POST['memeditauth'] ) ) {
			echo '<input type="hidden" name="memeditauth" value="' . esc_attr( $_POST['memeditauth'] ) . '">';
		}
	}

	/**
	 * Submenu area of the member page.
	 * usces_filter_member_submenu_list
	 *
	 * @param string $submenu_list Submenu area.
	 * @param array  $member Member information.
	 * @return string
	 */
	public function member_submenu_list( $submenu_list, $member ) {
		if ( empty( $submenu_list ) ) {
			$submenu_list = '<li class="member-edit"><a href="#edit">' . esc_html__( 'To member information editing', 'usces' ) . '</a></li>';
		}
		return $submenu_list;
	}

	/**
	 * Processing after login.
	 * usces_action_member_logined
	 */
	public function after_login() {
		global $usces;

		if ( isset( $_POST['memeditauth'] ) ) {
			$auth_code = filter_input( INPUT_POST, 'memeditauth', FILTER_DEFAULT );
			$member_id = $this->get_member_id_by_auth_code( $auth_code );
			if ( (int) $member_id === (int) $_SESSION['usces_member']['ID'] ) {
				$_SESSION['usces_member']['edit_auth'] = $auth_code;
			}
		}
	}

	/**
	 * Send login notification email.
	 * usces_action_after_login
	 */
	public function login_notify() {
		global $usces;

		$member       = $usces->get_member();
		$name         = usces_localized_name( trim( $member['name1'] ), trim( $member['name2'] ), 'return' );
		$mailaddress1 = trim( $member['mailaddress1'] );

		// translators: %s: name of shop.
		$subject = apply_filters( 'usces_filter_send_loginnotifymail_subject', sprintf( __( '%s : Login Notification', 'usces' ), get_option( 'blogname' ) ), $member );

		$message  = __( 'There has been a login on your account. Please review the following information and if you are not familiar with it, please change your password for your own safety.', 'usces' ) . "\r\n\r\n";
		$message .= '--------------------------------' . "\r\n";
		$message .= __( 'Login date and time', 'usces' ) . ' : ' . wp_date( 'Y-m-d H:i:s' ) . "\r\n";
		$message .= __( 'Member ID', 'usces' ) . ' : ' . $member['ID'] . "\r\n";
		$message .= __( 'Full name', 'usces' ) . ' : ' . sprintf( __( 'Dear %s', 'usces' ), $name ) . "\r\n";
		$message .= '--------------------------------' . "\r\n\r\n";

		$message .= get_option( 'blogname' ) . "\r\n";
		if ( 1 === (int) $usces->options['put_customer_name'] ) {
			// translators: %s: name of user.
			$dear_name = sprintf( __( 'Dear %s', 'usces' ), $name );
			$message   = $dear_name . "\r\n\r\n" . $message;
		}
		$message = apply_filters( 'usces_filter_send_loginnotifymail_message', $message, $member );

		$para1 = array(
			'to_name'      => sprintf( _x( '%s', 'honorific', 'usces' ), $name ),
			'to_address'   => $mailaddress1,
			'from_name'    => get_option( 'blogname' ),
			'from_address' => $usces->options['sender_mail'],
			'reply_name'   => get_option( 'blogname' ),
			'reply_to'     => usces_get_first_order_mail(),
			'return_path'  => $usces->options['error_mail'],
			'subject'      => $subject,
			'message'      => do_shortcode( $message ),
		);
		$para1 = apply_filters( 'usces_filter_send_loginnotifymail_para1', $para1 );
		usces_send_mail( $para1 );
	}
}
