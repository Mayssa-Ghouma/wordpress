<?php
/**
 * Google reCAPTCHA v3
 *
 * @package Welcart
 */

/**
 * Google reCAPTCHA v3
 */
class USCES_GOOGLE_RECAPTCHA {

	/**
	 * Option value
	 *
	 * @var array
	 */
	public static $opts;

	/**
	 * Class Constructor
	 */
	public function __construct() {

		self::initialize_data();

		if ( is_admin() ) {
			add_action( 'usces_action_admin_system_extentions', array( $this, 'setting_form' ) );
			add_action( 'init', array( $this, 'save_data' ) );
		}
	}

	/**
	 * Initialize
	 */
	public function initialize_data() {
		global $usces;
		$options = get_option( 'usces_ex', array() );
		$options['system']['google_recaptcha']['status']     = ! isset( $options['system']['google_recaptcha']['status'] ) ? 0 : (int) $options['system']['google_recaptcha']['status'];
		$options['system']['google_recaptcha']['site_key']   = ! isset( $options['system']['google_recaptcha']['site_key'] ) ? '' : $options['system']['google_recaptcha']['site_key'];
		$options['system']['google_recaptcha']['secret_key'] = ! isset( $options['system']['google_recaptcha']['secret_key'] ) ? '' : $options['system']['google_recaptcha']['secret_key'];
		$options['system']['google_recaptcha']['score']      = ! isset( $options['system']['google_recaptcha']['score'] ) ? '0.5' : $options['system']['google_recaptcha']['score'];
		update_option( 'usces_ex', $options );
		self::$opts = $options['system']['google_recaptcha'];
	}

	/**
	 * Save option data
	 */
	public function save_data() {
		global $usces;

		if ( isset( $_POST['usces_google_recaptcha_option_update'] ) ) {
			check_admin_referer( 'admin_system', 'wc_nonce' );
			if ( ! current_user_can( 'wel_manage_setting' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}

			self::$opts['status']     = filter_input( INPUT_POST, 'google_recaptcha_status', FILTER_VALIDATE_INT );
			self::$opts['site_key']   = filter_input( INPUT_POST, 'site_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			self::$opts['secret_key'] = filter_input( INPUT_POST, 'secret_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			self::$opts['score']      = filter_input( INPUT_POST, 'score', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

			if ( ! self::$opts['status'] ) {
				update_option( 'usces_recaptcha_condition', '' );
			}

			$options                               = get_option( 'usces_ex', array() );
			$options['system']['google_recaptcha'] = self::$opts;
			update_option( 'usces_ex', $options );
		}
	}

	/**
	 * Setting form
	 */
	public function setting_form() {
		$status    = ( self::$opts['status'] || self::$opts['status'] ) ? '<span class="running">' . __( 'Running', 'usces' ) . '</span>' : '<span class="stopped">' . __( 'Stopped', 'usces' ) . '</span>';
		$condition = get_option( 'usces_recaptcha_condition', '' );
		if ( 'response_OK' === $condition ) {
			$condition_str = '<span class="running">response_OK</span>';
		} elseif ( 'response_NG' === $condition || 'response_ERROR' === $condition ) {
			$condition_str = '<span class="stopped">' . $condition . '</span>';
		} else {
			$condition_str = '';
		}
		?>
		<form action="" method="post" name="option_form" id="google_recaptcha_form">
			<div class="postbox">
				<div class="postbox-header">
					<h2><span><?php esc_html_e( 'Google reCAPTCHA v3', 'usces' ); ?></span><?php wel_esc_script_e( $status ); ?><span><?php wel_esc_script_e( $condition_str ); ?></span></h2>
					<div class="handle-actions"><button type="button" class="handlediv" id="google_recaptcha"><span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Toggle panel: %s' ), __( 'Google reCAPTCHA v3', 'usces' ) ) ); ?></span><span class="toggle-indicator"></span></button></div>
				</div>
				<div class="inside">
					<table class="form_table">
						<tr height="35">
							<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_google_recaptcha_status');"><?php esc_html_e( 'Google reCAPTCHA v3', 'usces' ); ?></a></th>
							<td width="10"><input name="google_recaptcha_status" id="google_recaptcha_status0" type="radio" value="0"<?php checked( self::$opts['status'], 0 ); ?> /></td><td width="100"><label for="google_recaptcha_status0"><?php esc_html_e( 'disable', 'usces' ); ?></label></td>
							<td width="10"><input name="google_recaptcha_status" id="google_recaptcha_status1" type="radio" value="1"<?php checked( self::$opts['status'], 1 ); ?> /></td><td width="100"><label for="google_recaptcha_status1"><?php esc_html_e( 'enable', 'usces' ); ?></label></td>
							<td><div id="ex_google_recaptcha_status" class="explanation"><?php esc_html_e( 'Enable Google reCAPTCHA v3.', 'usces' ); ?><br><?php printf( __( 'You can register your new site <a href="%s" target="_blank">here</a>.', 'usces' ), 'https://www.google.com/recaptcha/admin/create' ); ?><?php _e( 'Select "reCAPTCHA v3" as the reCAPTCHA type.', 'usces' ); ?></div></td>
						</tr>
					</table>
					<table class="form_table">
						<tr height="35">
							<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_google_recaptcha_site_key');"><?php esc_html_e( 'Site Key', 'usces' ); ?></a></th>
							<td width="10"><input name="site_key" id="site_key" type="text" value="<?php echo esc_attr( self::$opts['site_key'] ); ?>" /></td>
							<td><div id="ex_google_recaptcha_site_key" class="explanation"><?php esc_html_e( 'The site key to use when displaying the reCAPTCHA.', 'usces' ); ?></div></td>
							<td><div id="error_google_recaptcha_site_key" class="explanation"><?php esc_html_e( 'Please enter the site key.', 'usces' ); ?></div></td>
						</tr>
						<tr height="35">
							<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_google_recaptcha_secret_key');"><?php esc_html_e( 'Secret Key', 'usces' ); ?></a></th>
							<td width="10"><input name="secret_key" id="secret_key" type="text" value="<?php echo esc_attr( self::$opts['secret_key'] ); ?>" /></td>
							<td><div id="ex_google_recaptcha_secret_key" class="explanation"><?php esc_html_e( 'The secret key used for validation. It must not be public.', 'usces' ); ?></div></td>
							<td><div id="error_google_recaptcha_secret_key" class="explanation"><?php esc_html_e( 'Please enter the secret key.', 'usces' ); ?></div></td>
						</tr>
						<tr height="35">
							<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_google_recaptcha_score');"><?php esc_html_e( 'Score', 'usces' ); ?></a></th>
							<td width="10">
								<select name="score" id="score">
								<option value="1.0" <?php selected( '1.0', self::$opts['score'] ); ?>>1.0</option>
								<option value="0.9" <?php selected( '0.9', self::$opts['score'] ); ?>>0.9</option>
								<option value="0.8" <?php selected( '0.8', self::$opts['score'] ); ?>>0.8</option>
								<option value="0.7" <?php selected( '0.7', self::$opts['score'] ); ?>>0.7</option>
								<option value="0.6" <?php selected( '0.6', self::$opts['score'] ); ?>>0.6</option>
								<option value="0.5" <?php selected( '0.5', self::$opts['score'] ); ?>>0.5(Default)</option>
								<option value="0.4" <?php selected( '0.4', self::$opts['score'] ); ?>>0.4</option>
								<option value="0.3" <?php selected( '0.3', self::$opts['score'] ); ?>>0.3</option>
								<option value="0.2" <?php selected( '0.2', self::$opts['score'] ); ?>>0.2</option>
								<option value="0.1" <?php selected( '0.1', self::$opts['score'] ); ?>>0.1</option>
								<option value="0.0" <?php selected( '0.0', self::$opts['score'] ); ?>>0.0</option>
							</select>
							<td><div id="ex_google_recaptcha_score" class="explanation"><?php esc_html_e( 'Set the threshold for scores returned by Google to determine whether to treat them as human. The closer the score is to 0.0, the more likely it is to be a bot, while a score closer to 1.0 indicates a higher likelihood of being human. The default value is set to 0.5.', 'usces' ); ?></div></td>
						</tr>
					</table>
					<hr />
					<input name="usces_google_recaptcha_option_update" type="submit" class="button button-primary" value="<?php esc_attr_e( 'change decision', 'usces' ); ?>" />
				</div>
			</div><!--postbox-->
			<?php wp_nonce_field( 'admin_system', 'wc_nonce' ); ?>
		</form>
		<?php
	}
}
