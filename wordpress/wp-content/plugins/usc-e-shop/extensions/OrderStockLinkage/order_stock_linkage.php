<?php
/**
 * The stock linkage by updating order data
 *
 * @package Welcart
 */

/**
 * The stock linkage by updating order data
 */
class USCES_STOCK_LINKAGE {

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

			if ( self::$opts['orderedit_flag'] ) {
				add_action( 'usces_action_update_orderdata', array( $this, 'update_order' ), 10, 5 );
				add_action( 'usces_action_del_orderdata', array( $this, 'del_order' ), 10, 2 );
				add_filter( 'usces_filter_add_ordercart', array( $this, 'ajax_add_ordercart_item' ), 10, 3 );
				add_action( 'usces_admin_delete_orderrow', array( $this, 'delete_ordercart_item' ), 10, 3 );
			}
			if ( self::$opts['collective_flag'] ) {
				add_action( 'usces_action_collective_order_status_each', array( $this, 'collective_update_order' ), 10, 3 );
				add_action( 'usces_action_collective_order_delete_each', array( $this, 'collective_del_order' ), 10, 2 );
			}
		}
	}

	/**
	 * Initialize
	 */
	public function initialize_data() {
		global $usces;
		$options = get_option( 'usces_ex', array() );
		$options['system']['stocklink']['orderedit_flag']  = ( ! isset( $options['system']['stocklink']['orderedit_flag'] ) ) ? 1 : (int) $options['system']['stocklink']['orderedit_flag'];
		$options['system']['stocklink']['collective_flag'] = ( ! isset( $options['system']['stocklink']['collective_flag'] ) ) ? 1 : (int) $options['system']['stocklink']['collective_flag'];
		update_option( 'usces_ex', $options );
		self::$opts = $options['system']['stocklink'];
	}

	/**
	 * Save Options
	 * init
	 */
	public function save_data() {
		global $usces;
		if ( isset( $_POST['usces_stocklink_option_update'] ) ) {

			check_admin_referer( 'admin_system', 'wc_nonce' );
			if ( ! current_user_can( 'wel_manage_setting' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
			}

			self::$opts['orderedit_flag']  = isset( $_POST['stocklink_orderedit_flag'] ) ? (int) $_POST['stocklink_orderedit_flag'] : 1;
			self::$opts['collective_flag'] = isset( $_POST['stocklink_collective_flag'] ) ? (int) $_POST['stocklink_collective_flag'] : 1;

			$options                        = get_option( 'usces_ex', array() );
			$options['system']['stocklink'] = self::$opts;
			update_option( 'usces_ex', $options );
		}
	}

	/**
	 * Setting Form
	 * usces_action_admin_system_extentions
	 */
	public function setting_form() {
		$status = ( self::$opts['orderedit_flag'] || self::$opts['collective_flag'] ) ? '<span class="running">' . __( 'Running', 'usces' ) . '</span>' : '<span class="stopped">' . __( 'Stopped', 'usces' ) . '</span>';
		?>
	<form action="" method="post" name="option_form" id="stocklink_form">
	<div class="postbox">
		<div class="postbox-header">
			<h2><span><?php esc_html_e( 'Stock Linkage OrderData', 'usces' ); ?></span><?php wel_esc_script_e( $status ); ?></h2>
			<div class="handle-actions"><button type="button" class="handlediv" id="stocklink"><span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Toggle panel: %s' ), __( 'Stock Linkage OrderData', 'usces' ) ) ); ?></span><span class="toggle-indicator"></span></button></div>
		</div>
		<div class="inside">
		<table class="form_table">
			<tr height="35">
				<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_stocklink_orderedit_flag');"><?php esc_html_e( 'Linkage Order Upadate', 'usces' ); ?></a></th>
				<td width="10"><input name="stocklink_orderedit_flag" id="stocklink_orderedit_flag0" type="radio" value="0"<?php checked( self::$opts['orderedit_flag'], 0 ); ?> /></td><td width="100"><label for="stocklink_orderedit_flag0"><?php esc_html_e( 'disable', 'usces' ); ?></label></td>
				<td width="10"><input name="stocklink_orderedit_flag" id="stocklink_orderedit_flag1" type="radio" value="1"<?php checked( self::$opts['orderedit_flag'], 1 ); ?> /></td><td width="100"><label for="stocklink_orderedit_flag1"><?php esc_html_e( 'enable', 'usces' ); ?></label></td>
				<td><div id="ex_stocklink_orderedit_flag" class="explanation"><?php esc_html_e( 'When activated, inventory will increase or decrease in tandem with the addition or deletion of items in the order edit screen.', 'usces' ); ?></div></td>
			</tr>
			<tr height="35">
				<th class="system_th"><a style="cursor:pointer;" onclick="toggleVisibility('ex_stocklink_collective_flag');"><?php esc_html_e( 'Linkage Order Collective Upadate', 'usces' ); ?></a></th>
				<td width="10"><input name="stocklink_collective_flag" id="stocklink_collective_flag0" type="radio" value="0"<?php checked( self::$opts['collective_flag'], 0 ); ?> /></td><td width="100"><label for="stocklink_collective_flag0"><?php esc_html_e( 'disable', 'usces' ); ?></label></td>
				<td width="10"><input name="stocklink_collective_flag" id="stocklink_collective_flag1" type="radio" value="1"<?php checked( self::$opts['collective_flag'], 1 ); ?> /></td><td width="100"><label for="stocklink_collective_flag1"><?php esc_html_e( 'enable', 'usces' ); ?></label></td>
				<td><div id="ex_stocklink_collective_flag" class="explanation"><?php esc_html_e( 'When activated, inventory will increase or decrease in tandem with the addition or deletion of products when performing a batch order update.', 'usces' ); ?></div></td>
			</tr>
		</table>
		<hr />
		<input name="usces_stocklink_option_update" type="submit" class="button button-primary" value="<?php esc_attr_e( 'change decision', 'usces' ); ?>" />
		</div>
	</div><!--postbox-->
		<?php wp_nonce_field( 'admin_system', 'wc_nonce' ); ?>
	</form>
		<?php
	}

	/**
	 * Order data update
	 * usces_action_update_orderdata
	 *
	 * @param object $new_order  New order data.
	 * @param string $old_status Old order status.
	 * @param object $old_order  Old order data.
	 * @param array  $new_carts  New cart data.
	 * @param array  $old_carts  Old cart data.
	 */
	public function update_order( $new_order, $old_status, $old_order, $new_carts, $old_carts ) {
		global $usces;

		if ( // If the status change was not.
			( ! $usces->is_status( 'adminorder', $old_order->order_status )
			&& ! $usces->is_status( 'estimate', $old_order->order_status )
			&& ! $usces->is_status( 'cancel', $old_order->order_status )
			&& ! $usces->is_status( 'adminorder', $new_order->order_status )
			&& ! $usces->is_status( 'estimate', $new_order->order_status )
			&& ! $usces->is_status( 'cancel', $new_order->order_status ) )
			|| ( $usces->is_status( 'adminorder', $old_order->order_status )
			&& ! $usces->is_status( 'cancel', $old_order->order_status )
			&& $usces->is_status( 'adminorder', $new_order->order_status )
			&& ! $usces->is_status( 'cancel', $new_order->order_status ) )
		) {
			foreach ( $old_carts as $ocart ) {
				$zaikonum = $usces->getItemZaikoNum( $ocart['post_id'], $ocart['sku_code'] );
				if ( WCUtils::is_blank( $zaikonum ) ) {
					continue;
				}
				$stock_id              = $usces->getItemZaikoStatusId( $ocart['post_id'], $ocart['sku_code'] );
				$item_order_acceptable = $usces->getItemOrderAcceptable( $ocart['post_id'] );
				foreach ( $new_carts as $ncart ) {
					if ( ( $ocart['post_id'] == $ncart['post_id'] ) && ( $ocart['sku_code'] == $ncart['sku_code'] ) ) {
						$fluctuation = $ncart['quantity'] - $ocart['quantity'];
						$value       = $zaikonum - $fluctuation;
						if ( 1 !== (int) $item_order_acceptable ) {
							if ( 0 >= $value ) {
								$value = 0;
								if ( 1 >= $stock_id ) {
									usces_update_sku( $ocart['post_id'], $ocart['sku_code'], 'stock', 2 );
								}
							} else {
								if ( 0 === (int) $zaikonum && 2 <= $stock_id ) {
									usces_update_sku( $ocart['post_id'], $ocart['sku_code'], 'stock', 0 );
								}
							}
						}
						usces_update_sku( $ocart['post_id'], $ocart['sku_code'], 'stocknum', $value );
					}
				}
			}

		} elseif ( // It has been canceled.
			( ! $usces->is_status( 'adminorder', $old_order->order_status )
			&& ! $usces->is_status( 'estimate', $old_order->order_status )
			&& ! $usces->is_status( 'cancel', $old_order->order_status )
			&& ! $usces->is_status( 'adminorder', $new_order->order_status )
			&& ! $usces->is_status( 'estimate', $new_order->order_status )
			&& $usces->is_status( 'cancel', $new_order->order_status ) )
			|| ( $usces->is_status( 'adminorder', $old_order->order_status )
			&& ! $usces->is_status( 'cancel', $old_order->order_status )
			&& $usces->is_status( 'adminorder', $new_order->order_status )
			&& $usces->is_status( 'cancel', $new_order->order_status ) )
		) {
			foreach ( $old_carts as $ocart ) {
				$zaikonum = $usces->getItemZaikoNum( $ocart['post_id'], $ocart['sku_code'] );
				if ( WCUtils::is_blank( $zaikonum ) ) {
					continue;
				}
				$stock_id              = $usces->getItemZaikoStatusId( $ocart['post_id'], $ocart['sku_code'] );
				$item_order_acceptable = $usces->getItemOrderAcceptable( $ocart['post_id'] );
				$value                 = $zaikonum + $ocart['quantity'];
				if ( 1 !== (int) $item_order_acceptable ) {
					if ( WCUtils::is_zero( $zaikonum ) && 2 <= $stock_id ) {
						usces_update_sku( $ocart['post_id'], $ocart['sku_code'], 'stock', 0 );
					}
				}
				usces_update_sku( $ocart['post_id'], $ocart['sku_code'], 'stocknum', $value );
			}

		} elseif ( // From cancellation to Enable.
			( ! $usces->is_status( 'adminorder', $old_order->order_status )
			&& ! $usces->is_status( 'estimate', $old_order->order_status )
			&& $usces->is_status( 'cancel', $old_order->order_status )
			&& ! $usces->is_status( 'adminorder', $new_order->order_status )
			&& ! $usces->is_status( 'estimate', $new_order->order_status )
			&& ! $usces->is_status( 'cancel', $new_order->order_status ) )
			|| ( $usces->is_status( 'adminorder', $old_order->order_status )
			&& $usces->is_status( 'cancel', $old_order->order_status )
			&& $usces->is_status( 'adminorder', $new_order->order_status )
			&& ! $usces->is_status( 'cancel', $new_order->order_status ) )
		) {
			foreach ( $new_carts as $ncart ) {
				$zaikonum = $usces->getItemZaikoNum( $ncart['post_id'], $ncart['sku_code'] );
				if ( WCUtils::is_blank( $zaikonum ) ) {
					continue;
				}
				$stock_id              = $usces->getItemZaikoStatusId( $ncart['post_id'], $ncart['sku_code'] );
				$item_order_acceptable = $usces->getItemOrderAcceptable( $ncart['post_id'] );
				$value                 = $zaikonum - $ncart['quantity'];
				if ( 1 !== (int) $item_order_acceptable ) {
					if ( 0 >= $value ) {
						if ( 1 >= $stock_id ) {
							usces_update_sku( $ncart['post_id'], $ncart['sku_code'], 'stock', 2 );
						}
						$value = 0;
					}
				}
				usces_update_sku( $ncart['post_id'], $ncart['sku_code'], 'stocknum', $value );
			}

		} elseif ( // From Estimate to Adminorder.
			$usces->is_status( 'estimate', $old_order->order_status )
			&& $usces->is_status( 'adminorder', $new_order->order_status )
			&& ! $usces->is_status( 'cancel', $new_order->order_status )
		) {
			foreach ( $new_carts as $ncart ) {
				$zaikonum = $usces->getItemZaikoNum( $ncart['post_id'], $ncart['sku_code'] );
				if ( WCUtils::is_blank( $zaikonum ) ) {
					continue;
				}
				$stock_id              = $usces->getItemZaikoStatusId( $ncart['post_id'], $ncart['sku_code'] );
				$item_order_acceptable = $usces->getItemOrderAcceptable( $ncart['post_id'] );
				$value                 = $zaikonum - $ncart['quantity'];
				if ( 1 !== (int) $item_order_acceptable ) {
					if ( 0 >= $value ) {
						if ( 1 >= $stock_id ) {
							usces_update_sku( $ncart['post_id'], $ncart['sku_code'], 'stock', 2 );
						}
						$value = 0;
					}
				}
				usces_update_sku( $ncart['post_id'], $ncart['sku_code'], 'stocknum', $value );
			}

		} elseif ( // From Adminorder to Estimate.
			$usces->is_status( 'adminorder', $old_order->order_status )
			&& $usces->is_status( 'estimate', $new_order->order_status )
			&& ! $usces->is_status( 'cancel', $new_order->order_status )
		) {
			foreach ( $new_carts as $ncart ) {
				$zaikonum = $usces->getItemZaikoNum( $ncart['post_id'], $ncart['sku_code'] );
				if ( WCUtils::is_blank( $zaikonum ) ) {
					continue;
				}
				$stock_id              = $usces->getItemZaikoStatusId( $ncart['post_id'], $ncart['sku_code'] );
				$item_order_acceptable = $usces->getItemOrderAcceptable( $ncart['post_id'] );
				$value                 = $zaikonum + $ncart['quantity'];
				if ( 1 !== (int) $item_order_acceptable ) {
					if ( WCUtils::is_zero( $zaikonum ) && 2 <= $stock_id ) {
						usces_update_sku( $ocart['post_id'], $ocart['sku_code'], 'stock', 0 );
					}
				}
				usces_update_sku( $ncart['post_id'], $ncart['sku_code'], 'stocknum', $value );
			}
		}
	}

	/**
	 * Batch update of order data
	 * usces_action_collective_order_status_each
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $statusstr  Order status.
	 * @param string $old_status Old order status.
	 */
	public function collective_update_order( $order_id, $statusstr, $old_status ) {
		global $usces;

		$action = isset( $_REQUEST['change']['word'] ) ? $_REQUEST['change']['word'] : '';
		switch ( $action ) {
			case 'adminorder':
				if (
					$usces->is_status( 'estimate', $old_status )
					&& ! $usces->is_status( 'cancel', $old_status )
				) {
					$carts = usces_get_ordercartdata( $order_id );
					foreach ( $carts as $cart ) {
						$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
						if ( WCUtils::is_blank( $zaikonum ) ) {
							continue;
						}
						$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
						$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
						$value                 = $zaikonum - $cart['quantity'];
						if ( 1 !== (int) $item_order_acceptable ) {
							if ( 0 >= $value ) {
								if ( 1 >= $stock_id ) {
									usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 2 );
								}
								$value = 0;
							}
						}
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
					}
				}
				break;

			case 'estimate':
				if (
					$usces->is_status( 'adminorder', $old_status )
					&& ! $usces->is_status( 'cancel', $old_status )
				) {
					$carts = usces_get_ordercartdata( $order_id );
					foreach ( $carts as $cart ) {
						$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
						if ( WCUtils::is_blank( $zaikonum ) ) {
							continue;
						}
						$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
						$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
						$value                 = $zaikonum + $cart['quantity'];
						if ( 1 !== (int) $item_order_acceptable ) {
							if ( WCUtils::is_zero( $zaikonum ) && 2 <= $stock_id ) {
								usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 0 );
							}
						}
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
					}

				} elseif (
					! $usces->is_status( 'adminorder', $old_status )
					&& ! $usces->is_status( 'estimate', $old_status )
					&& ! $usces->is_status( 'completion', $old_status )
					&& ! $usces->is_status( 'cancel', $old_status )
				) {
					$carts = usces_get_ordercartdata( $order_id );
					foreach ( $carts as $cart ) {
						$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
						if ( WCUtils::is_blank( $zaikonum ) ) {
							continue;
						}
						$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
						$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
						$value                 = $zaikonum + $cart['quantity'];
						if ( 1 !== (int) $item_order_acceptable ) {
							if ( WCUtils::is_zero( $zaikonum ) && 2 <= $stock_id ) {
								usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 0 );
							}
						}
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
					}
				}
				break;

			case 'cancel':
				if (
					$usces->is_status( 'adminorder', $old_status )
					&& ! $usces->is_status( 'cancel', $old_status )
				) {
					$carts = usces_get_ordercartdata( $order_id );
					foreach ( $carts as $cart ) {
						$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
						if ( WCUtils::is_blank( $zaikonum ) ) {
							continue;
						}
						$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
						$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
						$value                 = $zaikonum + $cart['quantity'];
						if ( 1 !== (int) $item_order_acceptable ) {
							if ( WCUtils::is_zero( $zaikonum ) && 2 <= $stock_id ) {
								usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 0 );
							}
						}
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
					}

				} elseif (
					! $usces->is_status( 'adminorder', $old_status )
					&& ! $usces->is_status( 'estimate', $old_status )
					&& ! $usces->is_status( 'cancel', $old_status )
				) {
					$carts = usces_get_ordercartdata( $order_id );
					foreach ( $carts as $cart ) {
						$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
						if ( WCUtils::is_blank( $zaikonum ) ) {
							continue;
						}
						$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
						$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
						$value                 = $zaikonum + $cart['quantity'];
						if ( 1 !== (int) $item_order_acceptable ) {
							if ( WCUtils::is_zero( $zaikonum ) && 2 <= $stock_id ) {
								usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 0 );
							}
						}
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
					}
				}
				break;

			case 'duringorder':
			case 'completion':
			case 'new':
			case 'neworder':
				if (
					$usces->is_status( 'adminorder', $old_status )
					&& $usces->is_status( 'cancel', $old_status )
				) {
					$carts = usces_get_ordercartdata( $order_id );
					foreach ( $carts as $cart ) {
						$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
						if ( WCUtils::is_blank( $zaikonum ) ) {
							continue;
						}
						$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
						$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
						$value                 = $zaikonum - $cart['quantity'];
						if ( 1 !== (int) $item_order_acceptable ) {
							if ( 0 >= $value ) {
								if ( 1 >= $stock_id ) {
									usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 2 );
								}
								$value = 0;
							}
						}
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
					}

				} elseif (
					! $usces->is_status( 'adminorder', $old_status )
					&& ! $usces->is_status( 'estimate', $old_status )
					&& $usces->is_status( 'cancel', $old_status )
				) {
					$carts = usces_get_ordercartdata( $order_id );
					foreach ( $carts as $cart ) {
						$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
						if ( WCUtils::is_blank( $zaikonum ) ) {
							continue;
						}
						$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
						$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
						$value                 = $zaikonum - $cart['quantity'];
						if ( 1 !== (int) $item_order_acceptable ) {
							if ( 0 >= $value ) {
								if ( 1 >= $stock_id ) {
									usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 2 );
								}
								$value = 0;
							}
						}
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
					}
				}
				break;
		}
	}

	/**
	 * Delete order data
	 * usces_action_del_orderdata
	 *
	 * @param object $order_data Order data.
	 * @param array  $args {
	 *     The array of cart data.
	 *     @type int $ID    Order ID.
	 *     @type int $point Point.
	 *     @type int $res   Query result.
	 * }
	 */
	public function del_order( $order_data, $args ) {
		global $usces;
		extract( $args );
		if (
			! $usces->is_status( 'adminorder', $order_data->order_status )
			&& ! $usces->is_status( 'estimate', $order_data->order_status )
			&& ! $usces->is_status( 'cancel', $order_data->order_status )
		) {
			$carts = usces_get_ordercartdata( $ID );
			foreach ( $carts as $cart ) {
				$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
				if ( WCUtils::is_blank( $zaikonum ) ) {
					continue;
				}
				$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
				$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
				$value                 = $zaikonum + $cart['quantity'];
				if ( 1 !== (int) $item_order_acceptable ) {
					if ( WCUtils::is_zero( $zaikonum ) && 2 <= $stock_id ) {
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 0 );
					}
				}
				usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
			}

		} elseif (
			$usces->is_status( 'adminorder', $order_data->order_status )
			&& ! $usces->is_status( 'estimate', $order_data->order_status )
			&& ! $usces->is_status( 'cancel', $order_data->order_status )
		) {
			$carts = usces_get_ordercartdata( $ID );
			foreach ( $carts as $cart ) {
				$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
				if ( WCUtils::is_blank( $zaikonum ) ) {
					continue;
				}
				$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
				$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
				$value                 = $zaikonum + $cart['quantity'];
				if ( 1 !== (int) $item_order_acceptable ) {
					if ( WCUtils::is_zero( $zaikonum ) && 2 <= $stock_id ) {
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 0 );
					}
				}
				usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
			}
		}
	}

	/**
	 * Batch deletion of order data
	 * usces_action_collective_order_delete_each
	 *
	 * @param int   $order_id  Order ID.
	 * @param array $order_res Query result.
	 */
	public function collective_del_order( $order_id, $order_res ) {
		global $usces;

		if (
			! $usces->is_status( 'adminorder', $order_res['order_status'] )
			&& ! $usces->is_status( 'estimate', $order_res['order_status'] )
			&& ! $usces->is_status( 'cancel', $order_res['order_status'] )
		) {
			$carts = usces_get_ordercartdata( $order_id );
			foreach ( $carts as $cart ) {
				$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
				if ( WCUtils::is_blank( $zaikonum ) ) {
					continue;
				}
				$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
				$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
				$value                 = $zaikonum + $cart['quantity'];
				if ( 1 !== (int) $item_order_acceptable ) {
					if ( WCUtils::is_zero( $zaikonum ) && 2 <= $stock_id ) {
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 0 );
					}
				}
				usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
			}

		} elseif (
			$usces->is_status( 'adminorder', $order_res['order_status'] )
			&& ! $usces->is_status( 'estimate', $order_res['order_status'] )
			&& ! $usces->is_status( 'cancel', $order_res['order_status'] )
		) {
			$carts = usces_get_ordercartdata( $order_id );
			foreach ( $carts as $cart ) {
				$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
				if ( WCUtils::is_blank( $zaikonum ) ) {
					continue;
				}
				$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
				$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
				$value                 = $zaikonum + $cart['quantity'];
				if ( 1 !== (int) $item_order_acceptable ) {
					if ( WCUtils::is_zero( $zaikonum ) && 2 <= $stock_id ) {
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 0 );
					}
				}
				usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
			}
		}
	}

	/**
	 * Add order cart data
	 * usces_filter_add_ordercart
	 *
	 * @param int $res      Query result.
	 * @param int $order_id Order ID.
	 * @param int $cart_id  Cart ID.
	 * @return int
	 */
	public function ajax_add_ordercart_item( $res, $order_id, $cart_id ) {
		global $usces;
		$cart     = usces_get_ordercartdata_row( $cart_id );
		$order    = $usces->get_order_data( $order_id, 'direct' );
		$newstock = '';
		if (
			! $usces->is_status( 'estimate', $order['order_status'] )
			&& ! $usces->is_status( 'cancel', $order['order_status'] )
		) {
			$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
			if ( WCUtils::is_blank( $zaikonum ) ) {
				return $res;
			}
			$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
			$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
			$value                 = $zaikonum - $cart['quantity'];
			if ( 1 !== (int) $item_order_acceptable ) {
				if ( 0 >= $value ) {
					if ( 1 >= $stock_id ) {
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 2 );
					}
					$value = 0;
				}
			}
			usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
		}
		return $res;
	}

	/**
	 * Delete order cart data
	 * usces_admin_delete_orderrow
	 *
	 * @param int   $del_cart_id Delete cart ID.
	 * @param int   $order_id    Order ID.
	 * @param array $old_cart    Old cart data.
	 */
	public function delete_ordercart_item( $del_cart_id, $order_id, $old_cart ) {
		global $usces;
		$cart  = usces_get_ordercartdata_row( $del_cart_id );
		$order = $usces->get_order_data( $order_id, 'direct' );
		if (
			! $usces->is_status( 'estimate', $order['order_status'] )
			&& ! $usces->is_status( 'cancel', $order['order_status'] )
		) {
			$zaikonum = $usces->getItemZaikoNum( $cart['post_id'], $cart['sku_code'] );
			if ( ! WCUtils::is_blank( $zaikonum ) ) {
				$stock_id              = $usces->getItemZaikoStatusId( $cart['post_id'], $cart['sku_code'] );
				$item_order_acceptable = $usces->getItemOrderAcceptable( $cart['post_id'] );
				$value                 = $zaikonum + $cart['quantity'];
				if ( 1 !== (int) $item_order_acceptable ) {
					if ( WCUtils::is_zero( $zaikonum ) && 2 <= $stock_id ) {
						usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stock', 0 );
					}
				}
				usces_update_sku( $cart['post_id'], $cart['sku_code'], 'stocknum', $value );
			}
		}
	}
}
