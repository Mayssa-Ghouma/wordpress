<?php
/**
 * Structured Data Product
 *
 * @package Welcart
 */

/**
 * Structured Data Product
 */
class USCES_STRUCTURED_DATA_PRODUCT {

	/**
	 * Option value
	 *
	 * @var array
	 */
	public static $opts;

	/**
	 * Defailt option value
	 *
	 * @var array
	 */
	public static $default_option;

	/**
	 * Labels value
	 *
	 * @var array
	 */
	public static $labels = array();

	/**
	 * Class Constructor
	 */
	public function __construct() {
		self::initialize_data();
		if ( is_admin() ) {
			add_action( 'usces_action_admin_system_extentions', array( $this, 'setting_form' ) );
			add_action( 'init', array( $this, 'save_data' ) );
		}
		self::$labels = array(
			'first'   => 'First SKU selling price',
			'minimum' => 'Lowest SKU selling price',
			'maximum' => 'Highest SKU selling price',
		);
	}

	/**
	 * 構造化データの設定の初期化.
	 */
	public function initialize_data() {
		global $usces;
		$options = get_option( 'usces_ex', array() );

		$options['system']['structured_data_product']['status']        = ( ! isset( $options['system']['structured_data_product']['status'] ) ) ? 0 : (int) $options['system']['structured_data_product']['status'];
		$options['system']['structured_data_product']['default_price'] = ( ! isset( $options['system']['structured_data_product']['default_price'] ) ) ? 'first' : $options['system']['structured_data_product']['default_price'];
		update_option( 'usces_ex', $options );
		self::$opts = $options['system']['structured_data_product'];

		self::$default_option = array(
			'status'        => array( 0, 1 ),
			'default_price' => array( 'first', 'minimum', 'maximum' ),
		);
	}

	/**
	 * 構造化データの設定保存.
	 */
	public function save_data() {
		global $usces;

		if ( isset( $_POST['usces_structured_data_product_option_update'] ) ) {

			check_admin_referer( 'admin_system', 'wc_nonce' );
			if ( ! current_user_can( 'wel_manage_setting' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}

			self::$opts['default_price']                  = ( isset( $_POST['default_price'] ) ) ? $_POST['default_price'] : 'first';
			self::$opts['status']                         = ( isset( $_POST['structured_data_product_status'] ) ) ? (int) $_POST['structured_data_product_status'] : 0;
			$options                                      = get_option( 'usces_ex', array() );
			$options['system']['structured_data_product'] = self::$opts;
			if ( isset( $options['system']['structured_data_product']['error_message'] ) ) {
				unset( $options['system']['structured_data_product']['error_message'] );
			}
			update_option( 'usces_ex', $options );
		}
	}

	/**
	 * システム設定 > 拡張機能のフォーム作成.
	 */
	public function setting_form() {
		$status  = ( self::$opts['status'] || self::$opts['status'] ) ? '<span class="running">' . __( 'Running', 'usces' ) . '</span>' : '<span class="stopped">' . __( 'Stopped', 'usces' ) . '</span>';
		$colspan = count( self::$default_option['default_price'] );
		?>
		<form action="" method="post" name="option_form" id="structured_data_product_form">
			<div class="postbox">
				<div class="postbox-header">
					<h2><span><?php esc_html_e( 'Structured data measures', 'usces' ); ?></span><?php wel_esc_script_e( $status ); ?></h2>
					<div class="handle-actions">
						<button type="button" class="handlediv" id="structured_data_product">
							<span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Toggle panel: %s' ), __( 'Structured data measures', 'usces' ) ) ); ?></span>
							<span class="toggle-indicator"></span>
						</button>
					</div>
				</div>
				<div class="inside">
					<?php if ( isset( self::$opts['error_message'] ) ) { ?>
						<span class="stopped"><?php _e( self::$opts['error_message'], 'usces' ); ?></span>
					<?php } ?>
					<table class="form_table">
						<tr height="35">
							<th class="system_th">
								<a style="cursor:pointer;"
									onclick="toggleVisibility('ex_structured_data_product_status');"><?php esc_html_e( 'Structured data measures', 'usces' ); ?></a>
							</th>
							<td>
								<input name="structured_data_product_status" type="radio"
										id="structured_data_product_status_0"
										value="0"<?php checked( self::$opts['status'], 0 ); ?> />
							</td>
							<td>
								<label for="structured_data_product_status_0"><?php esc_html_e( 'disable', 'usces' ); ?></label>
							</td>
							<td>
								<input name="structured_data_product_status" type="radio"
										id="structured_data_product_status_1"
										value="1"<?php checked( self::$opts['status'], 1 ); ?> />
							</td>
							<td colspan="<?php echo esc_attr( $colspan ); ?>">
								<label for="structured_data_product_status_1"><?php esc_html_e( 'enable', 'usces' ); ?></label>
							</td>
							<td>
								<div id="ex_structured_data_product_status" class="explanation">
									<?php esc_html_e( 'Enable Structured data measures.', 'usces' ); ?>
								</div>
							</td>
						</tr>
						<tr height="35">
							<th class="system_th">
								<a style="cursor:pointer;"
									onclick="toggleVisibility('ex_default_price');"><?php esc_html_e( 'Default price', 'usces' ); ?>
								</a>
							</th>
							<?php foreach ( self::$default_option['default_price'] as $value ) { ?>
								<td>
									<input name="default_price" type="radio"
											id="structured_data_product_default_price_<?php echo esc_attr( $value ); ?>"
											value="<?php echo esc_attr( $value ); ?>"<?php checked( self::$opts['default_price'], $value ); ?> />
								</td>
								<td>
									<label for="structured_data_product_default_price_<?php echo esc_attr( $value ); ?>"><?php _e( USCES_STRUCTURED_DATA_PRODUCT::$labels[ $value ], 'usces' ); ?></label>
								</td>
							<?php } ?>
							<td>
								<div id="ex_default_price"
										class="explanation"><?php esc_html_e( 'Which SKU do you want to use?', 'usces' ); ?></div>
							</td>
						</tr>
					</table>
					<hr/>
					<input name="usces_structured_data_product_option_update" type="submit"
							class="button button-primary"
							value="<?php esc_attr_e( 'change decision', 'usces' ); ?>"/>
				</div>
			</div><!--postbox-->
			<?php wp_nonce_field( 'admin_system', 'wc_nonce' ); ?>
		</form>
		<?php
	}
}
