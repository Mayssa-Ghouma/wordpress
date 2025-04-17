<?php
/**
 * Brute force attack countermeasures Class
 *
 * @package Welcart
 */

/**
 * Brute force attack countermeasures Class
 */
class USCES_BRUTE_FORCE_COUNTER_MEASURES {

	/**
	 * Option value
	 *
	 * @var array
	 */
	public static $opts;

	/**
	 * Default option value
	 *
	 * @var array
	 */
	public static $default_option;

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private $uploads_folder_path;

	/**
	 * Class Constructor
	 */
	public function __construct() {
		self::initialize_data();
		$this->uploads_folder_path = USCES_WP_CONTENT_DIR . '/uploads';
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

		$options                                    = get_option( 'usces_ex', array() );
		$options['system']['brute_force']['status'] = ( ! isset( $options['system']['brute_force']['status'] ) ) ? 0 : (int) $options['system']['brute_force']['status'];
		$options['system']['brute_force']['monitoring_span'] = ( ! isset( $options['system']['brute_force']['monitoring_span'] ) ) ? 5 : (int) $options['system']['brute_force']['monitoring_span'];
		$options['system']['brute_force']['num_of_errors']   = ( ! isset( $options['system']['brute_force']['num_of_errors'] ) ) ? 3 : (int) $options['system']['brute_force']['num_of_errors'];
		$options['system']['brute_force']['rejection_time']  = ( ! isset( $options['system']['brute_force']['rejection_time'] ) ) ? 10 : (int) $options['system']['brute_force']['rejection_time'];
		$options['system']['brute_force']['excluded_ip']     = ( ! isset( $options['system']['brute_force']['excluded_ip'] ) ) ? array() : $options['system']['brute_force']['excluded_ip'];
		update_option( 'usces_ex', $options );
		self::$opts = $options['system']['brute_force'];

		self::$default_option = array(
			'status'          => array( 0, 1 ),
			'monitoring_span' => array( 5, 10, 15 ),
			'num_of_errors'   => array( 3, 5, 10 ),
			'rejection_time'  => array( 10, 20, 30 ),
			'excluded_ip'     => array(),
		);
	}

	/**
	 * Save Options
	 * init
	 */
	public function save_data() {
		global $usces;

		if ( isset( $_POST['usces_brute_force_option_update'] ) ) {
			check_admin_referer( 'admin_system', 'wc_nonce' );
			if ( ! current_user_can( 'wel_manage_setting' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}

			if ( ! file_exists( $this->uploads_folder_path ) || ! is_writable( $this->uploads_folder_path ) ) {
				self::$opts['error_message'] = __( "You can't write to the wp-content/uploads/ folder. Please check the permissions.", 'usces' );
				self::$opts['status']        = 0;
			} else {
				if ( isset( self::$opts['error_message'] ) ) {
					unset( self::$opts['error_message'] );
				}
				self::$opts['status'] = ( isset( $_POST['brute_force_status'] ) ) ? (int) $_POST['brute_force_status'] : 0;
				if ( 0 === self::$opts['status'] ) {
					$usces_log_folder      = USCES_WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'usces_logs';
					$login_failed_log_path = $usces_log_folder . DIRECTORY_SEPARATOR . 'member_login_failed.log';
					$ip_blocked_path       = $usces_log_folder . DIRECTORY_SEPARATOR . 'ip_addresses_blocked.log';
					if ( file_exists( $login_failed_log_path ) ) {
						rename( $login_failed_log_path, $usces_log_folder . DIRECTORY_SEPARATOR . 'member_login_failed' . date_i18n( '-YmdHis' ) . '.log' );
					}
					if ( file_exists( $ip_blocked_path ) ) {
						rename( $ip_blocked_path, $usces_log_folder . DIRECTORY_SEPARATOR . 'ip_addresses_blocked' . date_i18n( '-YmdHis' ) . '.log' );
					}
				}
			}

			self::$opts['monitoring_span'] = ( isset( $_POST['monitoring_span'] ) ) ? (int) $_POST['monitoring_span'] : 5;
			self::$opts['num_of_errors']   = ( isset( $_POST['num_of_errors'] ) ) ? (int) $_POST['num_of_errors'] : 3;
			self::$opts['rejection_time']  = ( isset( $_POST['rejection_time'] ) ) ? (int) $_POST['rejection_time'] : 10;
			if ( isset( $_POST['excluded_ip'] ) ) {
				$ips         = array();
				$excluded_ip = explode( "\n", usces_change_line_break( $_POST['excluded_ip'] ) );
				foreach ( (array) $excluded_ip as $ip ) {
					if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
						$ips[] = $ip;
					}
				}
				self::$opts['excluded_ip'] = array_unique( $ips );
			}

			$options                          = get_option( 'usces_ex', array() );
			$options['system']['brute_force'] = self::$opts;
			if ( isset( $options['system']['brute_force']['error_message'] ) ) {
				unset( $options['system']['brute_force']['error_message'] );
			}
			update_option( 'usces_ex', $options );
		}
	}

	/**
	 * Setting Form
	 * usces_action_admin_system_extentions
	 */
	public function setting_form() {
		$status = ( ! empty( self::$opts['status'] ) && 1 === (int) self::$opts['status'] ) ? '<span class="running">' . __( 'Running', 'usces' ) . '</span>' : '<span class="stopped">' . __( 'Stopped', 'usces' ) . '</span>';
		if ( isset( self::$opts['excluded_ip'] ) && is_array( self::$opts['excluded_ip'] ) ) {
			$excluded_ip = implode( "\n", self::$opts['excluded_ip'] );
		} else {
			$excluded_ip = '';
		}
		?>
		<form action="" method="post" name="option_form" id="brute_force_form">
			<div class="postbox">
				<div class="postbox-header">
					<h2><span><?php esc_html_e( 'Brute-force attack countermeasures', 'usces' ); ?></span><?php wel_esc_script_e( $status ); ?></h2>
					<div class="handle-actions"><button type="button" class="handlediv" id="brute_force"><span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Toggle panel: %s' ), __( 'Brute-force attack countermeasures', 'usces' ) ) ); ?></span><span class="toggle-indicator"></span></button></div>
				</div>
				<div class="inside">
				<?php if ( isset( self::$opts['error_message'] ) ) : ?>
					<span class="stopped"><?php echo esc_html( self::$opts['error_message'] ); ?></span>
				<?php endif; ?>
					<table class="form_table">
						<tr height="35">
							<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_brute_force_status');"><?php esc_html_e( 'Brute-force attack countermeasures', 'usces' ); ?></a></th>
							<td width="10"><input name="brute_force_status" type="radio" id="brute_force_status_0" value="0"<?php checked( self::$opts['status'], 0 ); ?> /></td>
							<td width="100"><label for="brute_force_status_0"><?php esc_html_e( 'disable', 'usces' ); ?></label></td>
							<td width="10"><input name="brute_force_status" type="radio" id="brute_force_status_1" value="1"<?php checked( self::$opts['status'], 1 ); ?> /></td>
							<td width="100" colspan="3"><label for="brute_force_status_1"><?php esc_html_e( 'enable', 'usces' ); ?></label></td>
							<td><div id="ex_brute_force_status" class="explanation"><?php esc_html_e( 'Enable brute-force attack countermeasures. Detects "brute-force attack" on Welcart member login and prevents login.', 'usces' ); ?></div></td>
						</tr>
						<tr height="35">
							<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_monitoring_span');"><?php esc_html_e( 'Monitoring span', 'usces' ); ?></a></th>
						<?php foreach ( self::$default_option['monitoring_span'] as $value ) : ?>
							<td width="10"><input name="monitoring_span" type="radio" id="brute_force_monitoring_span_<?php echo esc_attr( $value ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php checked( self::$opts['monitoring_span'], $value ); ?> /></td>
							<td width="100"><label for="brute_force_monitoring_span_<?php echo esc_attr( $value ); ?>"><?php printf( __( '%s minutes', 'usces' ), esc_attr( $value ) ); ?></label></td>
						<?php endforeach; ?>
							<td><div id="ex_monitoring_span" class="explanation"><?php esc_html_e( 'If it fails "number of errors" times during the specified time, it is considered a "brute-force attack".', 'usces' ); ?></div></td>
						</tr>
						<tr height="35">
							<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_num_of_errors');"><?php esc_html_e( 'Number of errors', 'usces' ); ?></a></th>
						<?php foreach ( self::$default_option['num_of_errors'] as $value ) : ?>
							<td width="10"><input name="num_of_errors" type="radio" id="brute_force_num_of_errors_<?php echo esc_attr( $value ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php checked( self::$opts['num_of_errors'], $value ); ?> /></td>
							<td width="100"><label for="brute_force_num_of_errors_<?php echo esc_attr( $value ); ?>"><?php printf( __( '%s times', 'usces' ), esc_attr( $value ) ); ?></label></td>
						<?php endforeach; ?>
							<td><div id="ex_num_of_errors" class="explanation"><?php esc_html_e( 'If it fails a specified number of times during the "monitoring span", it is considered a "brute-force attack".', 'usces' ); ?></div></td>
						</tr>
						<tr height="35">
							<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_rejection_time');"><?php esc_html_e( 'Rejection time', 'usces' ); ?></a></th>
						<?php foreach ( self::$default_option['rejection_time'] as $value ) : ?>
							<td width="10"><input name="rejection_time" type="radio" id="brute_force_rejection_time_<?php echo esc_attr( $value ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php checked( self::$opts['rejection_time'], $value ); ?> /></td>
							<td width="100"><label for="brute_force_rejection_time_<?php echo esc_attr( $value ); ?>"><?php printf( __( '%s minutes', 'usces' ), esc_attr( $value ) ); ?></label></td>
						<?php endforeach; ?>
							<td><div id="ex_rejection_time" class="explanation"><?php esc_html_e( 'If it is considered a "brute-force attack", the login page will not be displayed for a specified period of time. (This will result in a 403 error.)', 'usces' ); ?></div></td>
						</tr>
						<tr>
							<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_excluded_ip');"><?php esc_html_e( 'Excluded IP Address', 'usces' ); ?></a></th>
							<td colspan="6"><textarea name="excluded_ip" id="excluded_ip"><?php wel_esc_script_e( $excluded_ip ); ?></textarea></td>
							<td><div id="ex_excluded_ip" class="explanation"><?php esc_html_e( 'Set IP addresses to exclude from attack monitoring. When specifying multiple IP addresses, enter them in a new line. Illegal IP addresses cannot be registered.', 'usces' ); ?></div></td>
						</tr>
					</table>
					<hr/>
					<input name="usces_brute_force_option_update" type="submit" class="button button-primary" value="<?php esc_attr_e( 'change decision', 'usces' ); ?>"/>
				</div>
			</div><!--postbox-->
			<?php wp_nonce_field( 'admin_system', 'wc_nonce' ); ?>
		</form>
		<?php
	}
}
