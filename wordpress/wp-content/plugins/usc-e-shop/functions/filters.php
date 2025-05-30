<?php
/**
 * Welcart functions
 *
 * Hooking the main functions.
 *
 * @package Welcart
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.PHP.NoSilencedErrors

/**
 * Get postmeta.
 *
 * @param string $null Null value.
 * @param int    $object_id Post ID.
 * @param string $meta_key Meta key.
 * @param bool   $single Text or Array.
 * @return mixed
 */
function usces_filter_get_post_metadata( $null, $object_id, $meta_key, $single ) {
	global $wpdb;

	$query = $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $object_id, $meta_key );
	$metas = $wpdb->get_col( $query );
	if ( ! empty( $metas ) ) {
		return array_map( 'maybe_unserialize', $metas );
	}

	if ( $single ) {
		return '';
	} else {
		return array();
	}
}

/**
 * Save decorated order id.
 * usces_action_reg_orderdata
 *
 * @param array $args {
 *     The array of order-related data.
 *     @type array $cart Cart data.
 *     @type array $entry Entry data.
 *     @type int   $order_id Order ID.
 *     @type int   $member_id Member ID.
 *     @type array $payments Payment Method.
 *     @type array $charging_type Charge type.
 *     @type array $results Settlement result data.
 * }
 */
function usces_action_reg_orderdata( $args ) {
	global $usces;
	extract( $args );

	$options = get_option( 'usces', array() );
	$prefix  = $options['system']['dec_orderID_prefix'] ?? '';
	$prefix  = apply_filters( 'usces_filter_dec_order_id_prefix', $prefix, $args );

	$dec_order_id = usces_make_deco_order_id( $order_id );
	$dec_order_id = apply_filters( 'usces_filter_dec_order_id', $dec_order_id, $args );
	$dec_order_id = $prefix . $dec_order_id;

	$usces->set_order_meta_value( 'dec_order_id', $dec_order_id, $order_id );
}

/**
 * Inventory (stock) adjustment.
 * usces_action_reg_orderdata
 *
 * @param array $args {
 *     The array of order-related data.
 *     @type array $cart Cart data.
 *     @type array $entry Entry data.
 *     @type int   $order_id Order ID.
 *     @type int   $member_id Member ID.
 *     @type array $payments Payment Method.
 *     @type array $charging_type Charge type.
 *     @type array $results Settlement result data.
 * }
 */
function usces_action_reg_orderdata_stocks( $args ) {
	global $usces;
	extract( $args );

	foreach ( $cart as $cartrow ) {

		$item_order_acceptable = (int) $usces->getItemOrderAcceptable( $cartrow['post_id'], false );
		$sku                   = urldecode( $cartrow['sku'] );
		$zaikonum              = $usces->getItemZaikoNum( $cartrow['post_id'], $sku, false );
		if ( WCUtils::is_blank( $zaikonum ) ) {
			continue;
		}
		$zaikonum = (int) $zaikonum - (int) $cartrow['quantity'];
		if ( 1 !== $item_order_acceptable ) {
			if ( $zaikonum < 0 ) {
				$zaikonum = 0;
			}
		}

		$usces->updateItemZaikoNum( $cartrow['post_id'], $sku, $zaikonum );

		if ( 1 !== $item_order_acceptable ) {
			if ( $zaikonum <= 0 ) {
				$default_empty_status = apply_filters( 'usces_filter_default_empty_status', 2 );
				$usces->updateItemZaiko( $cartrow['post_id'], $sku, $default_empty_status );
				do_action( 'usces_action_outofstock', $cartrow['post_id'], $sku, $cartrow, $args );
			}
		}
	}
}

/**
 * Add security headers (member, cart).
 * send_headers
 *
 * @return void
 */
function usces_add_security_headers() {
	global $usces;
	if ( is_customize_preview() ) {
		return;
	}
	$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
	if ( $usces->is_member_page( $request_uri ) || $usces->is_cart_page( $request_uri ) ) {
		header( 'X-Frame-Options: DENY' );
		header( "Content-Security-Policy: frame-ancestors 'none'" );
	}
}

/**
 * OGP meta.
 * wp_head
 */
function usces_action_ogp_meta() {
	global $usces, $post;

	if ( empty( $post ) || ! $usces->is_item( $post ) || ! is_single() ) {
		return;
	}

	$item       = wel_get_product( $post->ID );
	$pictid     = $usces->get_mainpictid( $item['itemCode'] );
	$image_info = wp_get_attachment_image_src( $pictid, 'thumbnail' );

	$ogs['title']       = $item['itemName'];
	$ogs['type']        = 'product';
	$ogs['description'] = strip_tags( get_the_title( $post->ID ) );
	$ogs['url']         = get_permalink( $post->ID );
	$ogs['image']       = ( isset( $image_info[0] ) ) ? $image_info[0] : '';
	$ogs['site_name']   = get_option( 'blogname' );

	$ogs = apply_filters( 'usces_filter_ogp_meta', $ogs, $post->ID );

	foreach ( $ogs as $key => $value ) {
		echo "\n" . '<meta property="og:' . $key . '" content="' . $value . '">';
	}
}

/**
 * 構造化データのJSON出力.
 * wp_head
 */
function usces_action_structured_data_json() {
	global $post, $usces;

	if ( 0 === USCES_STRUCTURED_DATA_PRODUCT::$opts['status'] || empty( $post ) ) {
		return;
	}

	global $post, $usces;
	if ( empty( $post ) || ! $usces->is_item( $post ) ) {
		return;
	}

	$product = wel_get_product( $post->ID );
	if ( is_admin() || ! is_single() || ! $product['itemCode'] ) {
		return;
	}

	$itemname              = $product['itemName'];
	$skus                  = $product['_sku'];
	$target_sku            = null;
	$structuredDataSkuCode = $product['structuredDataSku'];
	$patch_skus            = array();

	foreach ( $skus as $sku ) {
		// 廃盤は削除.
		if ( 4 === (int) $usces->getItemZaikoStatusId( $post->ID, $sku['code'] ) ) {
			continue;
		}
		// 特定のSKUがあればそれを対象とする.
		if ( $structuredDataSkuCode === $sku['code'] ) {
			$target_sku = $sku;
		}
		$patch_skus[] = $sku;
	}
	if ( null === $target_sku ) {
		foreach ( $patch_skus as $sku ) {
			// 1番目のSKU.
			if ( 'first' === USCES_STRUCTURED_DATA_PRODUCT::$opts['default_price'] ) {
				$target_sku = $sku;
				break;
			}
			// 1番安価のSKU.
			if ( 'minimum' === USCES_STRUCTURED_DATA_PRODUCT::$opts['default_price'] ) {
				if ( $target_sku && (int) $target_sku['price'] <= (int) $sku['price'] ) {
					continue;
				}
				$target_sku = $sku;
			} else {
				if ( $target_sku && (int) $target_sku['price'] >= (int) $sku['price'] ) { // 1番高価のSKU.
					continue;
				}
				$target_sku = $sku;
			}
		}
	}
	if ( empty( $target_sku ) ) {
		return;
	}
	$target_sku['availability'] = $usces->is_item_zaiko( $post->ID, $sku['code'] ) ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut';

	$url         = get_the_permalink();
	$image       = usces_the_itemImageURL( 0, 'return' );
	$description = function_exists( 'get_field' ) ? get_field( 'field_rich_result_product_description' ) : '';
	$stock       = usces_have_zaiko_anyone( $post->ID ) ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut';
	$cr          = $usces->options['system']['currency'];
	if ( isset( $usces_settings['currency'][ $cr ] ) ) {
		list( $code, $decimal, $point, $seperator, $symbol ) = $usces_settings['currency'][ $cr ];
	} else {
		$code = 'JPY';
	}
	$data   = array(
		'@context'    => 'https://schema.org/',
		'@type'       => 'Product',
		'name'        => $itemname,
		'image'       => $image,
		'description' => $description,
		'productID'   => $product['itemCode'],
		'offers'      => [
			'@type'         => 'Offer',
			'sku'           => $product['itemCode'] . '-' . $target_sku['code'],
			'priceCurrency' => $code,
			'price'         => $target_sku['price'],
			'availability'  => $target_sku['availability'],
		],
	);
	$script = json_encode( apply_filters( 'usces_filter_structured_data', $data, $post ) );

	echo "\n" . '<script type="application/ld+json">' . $script . '</script>';
}

/**
 * 特定のSKUデータを構造化データのに利用するためのフォーム.
 * usces_item_master_first_section
 *
 * @param string $html First section area.
 * @param int    $post_ID Post Id.
 */
function usces_item_master_structured_data( $html, $post_ID ) {
	if ( 1 === USCES_STRUCTURED_DATA_PRODUCT::$opts['status'] ) {
		$product           = wel_get_product( $post_ID );
		$structuredDataSku = ( isset( $product['structuredDataSku'] ) ) ? $product['structuredDataSku'] : '';
		?>
		<tr>
			<th><?php esc_html_e( 'SKUs Code used for structured data', 'usces' ); ?></th>
			<td>
				<input type="text" name="structuredDataSku" id="structuredDataSku" class="structuredDataSku" value="<?php echo esc_attr( $structuredDataSku ); ?>" />
				<input type="hidden" name="structuredDataSku_nonce" id="structuredDataSku_nonce" value="<?php echo wp_create_nonce( 'structuredDataSku_nonce' ); ?>" />
			</td>
		</tr>
		<?php
	}
}

/**
 * Make Directory.
 */
function wc_mkdir() {
	global $usces;
	if ( is_admin() && ! WCUtils::is_blank( $usces->options['logs_path'] ) && false !== strpos( $_SERVER['SERVER_SOFTWARE'], 'Apache' ) ) {
		$welcart_file_dir = $usces->options['logs_path'] . '/welcart';
		$logs_dir         = $welcart_file_dir . '/logs';
		if ( ! file_exists( $welcart_file_dir ) ) {
			$res = @mkdir( $welcart_file_dir, 0700 );
			if ( ! $res ) {
				$msg = '<div class="error"><p>下記のディレクトリーを、所有者：' . get_current_user() . '、パーミッション：700 で作成してください。 <br />' . $welcart_file_dir . '</p></div>';
				add_action(
					'admin_notices',
					function() {
						echo addcslashes( $msg, '"' );
					}
				);
			}
		}
		$stat = stat( $welcart_file_dir );
		print_r( $stat );
	}
}

/**
 * Save the oreder cart data.
 * usces_action_reg_orderdata
 *
 * @param array $args {
 *     The array of order-related data.
 *     @type array $cart Cart data.
 *     @type array $entry Entry data.
 *     @type int   $order_id Order ID.
 *     @type int   $member_id Member ID.
 *     @type array $payments Payment Method.
 *     @type array $charging_type Charge type.
 *     @type array $results Settlement result data.
 * }
 */
function usces_reg_ordercartdata( $args ) {
	global $usces, $wpdb, $usces_settings;
	extract( $args );

	if ( ! $order_id ) {
		return;
	}
	$cart_table      = $wpdb->prefix . 'usces_ordercart';
	$cart_meta_table = $wpdb->prefix . 'usces_ordercart_meta';

	foreach ( $cart as $row_index => $value ) {
		$post_id     = (int) $value['post_id'];
		$product     = wel_get_product( $post_id, false );
		$item_code   = $product['itemCode'];
		$item_name   = $product['itemName'];
		$skus        = $usces->get_skus( $value['post_id'], 'code', false );
		$sku_encoded = $value['sku'];
		$skucode     = urldecode( $value['sku'] );
		$sku         = $skus[ $skucode ];
		$tax         = 0;
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$cart_table} 
				(order_id, row_index, post_id, item_code, item_name, sku_code, sku_name, cprice, price, quantity, 
				unit, tax, destination_id, cart_serial ) 
				VALUES (%d, %d, %d, %s, %s, %s, %s, %f, %f, %f, %s, %d, %d, %s)",
				$order_id,
				$row_index,
				$post_id,
				$item_code,
				$item_name,
				$skucode,
				$sku['name'],
				$sku['cprice'],
				$value['price'],
				$value['quantity'],
				$sku['unit'],
				$tax,
				null,
				$value['serial']
			)
		);

		$cart_id    = $wpdb->insert_id;
		$opt_fields = wel_get_opts( $post_id, 'sort', false );

		if ( $value['options'] ) {

			foreach ( (array) $opt_fields as $okey => $val ) {

				$enc_key = urlencode( $val['name'] );
				$means   = $opt_fields[ $okey ]['means'];

				if ( 3 === (int) $means ) {

					if ( '' == $value['options'][ $enc_key ] ) {
						$ovalue = $value['options'][ $enc_key ];
					} else {
						$ovalue = urldecode( $value['options'][ $enc_key ] );
					}
				} elseif ( 4 === (int) $means ) {

					if ( is_array( $value['options'][ $enc_key ] ) ) {

						$temp = array();
						foreach ( $value['options'][ $enc_key ] as $v ) {
							$temp[] = urldecode( $v );
						}
						$ovalue = serialize( $temp );
					} elseif ( '' == $value['options'][ $enc_key ] ) {

						$ovalue = $value['options'][ $enc_key ];
					} else {

						$ovalue = urldecode( $value['options'][ $enc_key ] );
					}
				} else {

					if ( is_array( $value['options'][ $enc_key ] ) ) {
						$temp = array();
						foreach ( $value['options'][ $enc_key ] as $k => $v ) {
							$temp[ $k ] = urldecode( $v );
						}
						$ovalue = serialize( $temp );
					} else {
						$ovalue = urldecode( $value['options'][ $enc_key ] );
					}
				}
				$ovalue = wel_safe_text_serialize( $ovalue );
				$wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$cart_meta_table} 
						( cart_id, meta_type, meta_key, meta_value ) VALUES (%d, %s, %s, %s)",
						$cart_id,
						'option',
						$val['name'],
						$ovalue
					)
				);
			}
		}

		if ( $value['advance'] ) {

			foreach ( (array) $value['advance'] as $akey => $avalue ) {
				$advance = $usces->cart->wc_unserialize( $avalue );

				if ( is_array( $advance ) ) {

					if ( isset( $advance[ $post_id ][ $sku_encoded ] ) && is_array( $advance[ $post_id ][ $sku_encoded ] ) ) {
						$akeys = array_keys( $advance[ $post_id ][ $sku_encoded ] );

						foreach ( (array) $akeys as $akey ) {
							if ( is_array( $advance[ $post_id ][ $sku_encoded ][ $akey ] ) ) {
								$avalue = serialize( $advance[ $post_id ][ $sku_encoded ][ $akey ] );
							} else {
								$avalue = $advance[ $post_id ][ $sku_encoded ][ $akey ];
							}
							$wpdb->query(
								$wpdb->prepare(
									"INSERT INTO {$cart_meta_table} 
									( cart_id, meta_type, meta_key, meta_value ) VALUES ( %d, 'advance', %s, %s )",
									$cart_id,
									$akey,
									$avalue
								)
							);
						}
					} else {
						$akeys  = array_keys( $advance );
						$akey   = empty( $akeys[0] ) ? 'advance' : $akeys[0];
						$avalue = serialize( $advance );
						$wpdb->query(
							$wpdb->prepare(
								"INSERT INTO {$cart_meta_table} 
								( cart_id, meta_type, meta_key, meta_value ) VALUES ( %d, 'advance', %s, %s )",
								$cart_id,
								$akey,
								$avalue
							)
						);
					}
				} else {
					$avalue = urldecode( $avalue );
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO {$cart_meta_table} 
							( cart_id, meta_type, meta_key, meta_value ) VALUES ( %d, 'advance', %s, %s )",
							$cart_id,
							$akey,
							$avalue
						)
					);
				}
			}
		}

		if ( $usces->is_reduced_taxrate() ) {
			if ( isset( $sku['taxrate'] ) && 'reduced' == $sku['taxrate'] ) {
				$tkey   = 'reduced';
				$tvalue = $usces->options['tax_rate_reduced'];
			} else {
				$tkey   = 'standard';
				$tvalue = $usces->options['tax_rate'];
			}
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$cart_meta_table} 
					( cart_id, meta_type, meta_key, meta_value ) VALUES ( %d, 'taxrate', %s, %s )",
					$cart_id,
					$tkey,
					$tvalue
				)
			);
		}

		do_action( 'usces_action_reg_ordercart_row', $cart_id, $row_index, $value, $args );
	}
}

/**
 * Document title.
 * pre_get_document_title
 * wp_title
 * alias fiter_mainTitle()
 *
 * @param string $title Document title.
 * @param string $sep Separator.
 * @return string
 */
function filter_mainTitle( $title, $sep = '' ) {
	return fiter_mainTitle( $title, $sep );
}

/**
 * Document title.
 *
 * @param string $title Document title.
 * @param string $sep Separator.
 * @return string
 */
function fiter_mainTitle( $title, $sep = '' ) {
	global $usces;

	if ( empty( $sep ) ) {
		$sep = apply_filters( 'document_title_separator', '|' );
	}

	switch ( $usces->page ) {
		case 'cart':
			$newtitle = apply_filters( 'usces_filter_title_cart', __( 'In the cart', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		case 'customer':
			$newtitle = apply_filters( 'usces_filter_title_customer', __( 'Customer Information', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		case 'delivery':
			$newtitle = apply_filters( 'usces_filter_title_delivery', __( 'Shipping / Payment options', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		case 'confirm':
			$newtitle = apply_filters( 'usces_filter_title_confirm', __( 'Confirmation', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		case 'ordercompletion':
			$newtitle = apply_filters( 'usces_filter_title_ordercompletion', __( 'Order Complete', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		case 'error':
			$newtitle = apply_filters( 'usces_filter_title_error', __( 'Error', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' ); // new fitler name.
			break;

		case 'search_item':
			$newtitle = apply_filters( 'usces_filter_title_search_item', __( "'AND' search by categories", 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		case 'maintenance':
			$newtitle = apply_filters( 'usces_filter_title_maintenance', __( 'Under Maintenance', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		case 'login':
			$newtitle = apply_filters( 'usces_filter_title_login', __( 'Log-in for members', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		case 'member':
			$newtitle = apply_filters( 'usces_filter_title_member', __( 'Membership information', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		case 'newmemberform':
			$newtitle = apply_filters( 'usces_filter_title_newmemberform', __( 'New enrollment form', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		case 'newcompletion':
			$newtitle = apply_filters( 'usces_filter_title_newcompletion', __( 'New enrollment complete', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' ); // new fitler name.
			break;

		case 'editmemberform':
			$newtitle = apply_filters( 'usces_filter_title_editmemberform', __( 'Member information editing', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' ); // new fitler name.
			break;

		case 'editcompletion':
			$newtitle = apply_filters( 'usces_filter_title_editcompletion', __( 'Membership information change is completed', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' ); // new fitler name.
			break;

		case 'lostmemberpassword':
			$newtitle = apply_filters( 'usces_filter_title_lostmemberpassword', __( 'The new password acquisition', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		case 'lostcompletion':
			$newtitle = apply_filters( 'usces_filter_title_lostcompletion', __( 'New password procedures for obtaining complete', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' ); // new fitler name.
			break;

		case 'changepassword':
			$newtitle = apply_filters( 'usces_filter_title_changepassword', __( 'Change password', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		case 'changepasscompletion':
			$newtitle = apply_filters( 'usces_filter_title_changepasscompletion', __( 'Password change is completed', 'usces' ) ) . ' ' . $sep . ' ' . get_bloginfo( 'name' );
			break;

		default:
			$newtitle = $title;
	}
	return $newtitle;
}

/**
 * Separator
 * document_title_separator
 *
 * @param string $sep Separator.
 * @return string
 */
function usces_document_title_separator( $sep ) {
	$sep = '|';
	return $sep;
}

/**
 * Univarsal Analytics( Dashboard )
 *
 * @return array
 */
function usces_Universal_trackPageview() {
	global $usces;

	$push = array();

	switch ( $usces->page ) {
		case 'cart':
			$push[] = "'page' : '/wc_cart'";
			break;

		case 'customer':
			$push[] = "'page' : '/wc_customer'";
			break;

		case 'delivery':
			$push[] = "'page' : '/wc_delivery'";
			break;

		case 'confirm':
			$push[] = "'page' : '/wc_confirm'";
			break;

		case 'ordercompletion':
			$push[]  = "'page' : '/wc_ordercompletion'";
			$sesdata = $usces->cart->get_entry();
			if ( isset( $sesdata['order']['ID'] ) && ! empty( $sesdata['order']['ID'] ) ) {
				$order_id    = $sesdata['order']['ID'];
				$data        = $usces->get_order_data( $order_id, 'direct' );
				$cart        = unserialize( $data['order_cart'] );
				$total_price = $usces->get_total_price( $cart ) + $data['order_discount'] - $data['order_usedpoint'];
				if ( $total_price < 0 ) {
					$total_price = 0;
				}

				$push[]     = "'require', 'ecommerce', 'ecommerce.js'";
				$push[]     = "'ecommerce:addTransaction', { 
							id: '" . $order_id . "', 
							affiliation: '" . get_option( 'blogname' ) . "',
							revenue: '" . $total_price . "',
							shipping: '" . $data['order_shipping_charge'] . "',
							tax: '" . $data['order_tax'] . "' }";
				$cart_count = ( $cart && is_array( $cart ) ) ? count( $cart ) : 0;
				for ( $i = 0; $i < $cart_count; $i++ ) {
					$cart_row = $cart[ $i ];
					$post_id  = $cart_row['post_id'];
					$sku      = urldecode( $cart_row['sku'] );
					$quantity = $cart_row['quantity'];
					$itemname = $usces->getItemName( $post_id );
					$skuprice = $cart_row['price'];
					$cats     = $usces->get_item_cat_genre_ids( $post_id );
					if ( is_array( $cats ) ) {
						sort( $cats );
					}
					$category = ( isset( $cats[0] ) ) ? get_cat_name( $cats[0] ) : '';

					$push[] = "'ecommerce:addItem', {
								id: '" . $order_id . "',
								sku: '" . $sku . "',
								name: '" . $itemname . "',
								category: '" . $category . "',
								price: '" . $skuprice . "',
								quantity: '" . $quantity . "' }";
				}
				$push[] = "'ecommerce:send'";
			}
			break;

		case 'error':
			$push[] = "'page' : '/wc_error'";
			break;

		case 'search_item':
			$push[] = "'page' : '/wc_search_item'";
			break;

		case 'maintenance':
			$push[] = "'page' : '/wc_maintenance'";
			break;

		case 'login':
			$push[] = "'page' : '/wc_login'";
			break;

		case 'member':
			$push[] = "'page' : '/wc_member'";
			break;

		case 'newmemberform':
			$push[] = "'page' : '/wc_newmemberform'";
			break;

		case 'newcompletion':
			$push[] = "'page' : '/wc_newcompletion'";
			break;

		case 'editmemberform':
			$push[] = "'page' : '/wc_editmemberform'";
			break;

		case 'editcompletion':
			$push[] = "'page' : '/wc_editcompletion'";
			break;

		case 'lostmemberpassword':
			$push[] = "'page' : '/wc_lostmemberpassword'";
			break;

		case 'lostcompletion':
			$push[] = "'page' : '/wc_lostcompletion'";
			break;

		case 'changepassword':
			$push[] = "'page' : '/wc_changepassword'";
			break;

		case 'changepasscompletion':
			$push[] = "'page' : '/wc_changepasscompletion'";
			break;

		default:
			break;
	}
	return $push;
}

/**
 * Classic Analytics ( Dashboard )
 *
 * @return array
 */
function usces_Classic_trackPageview() {
	global $usces;

	$push = array();

	switch ( $usces->page ) {
		case 'cart':
			$push = usces_trackPageview_cart( $push );
			break;

		case 'customer':
			$push = usces_trackPageview_customer( $push );
			break;

		case 'delivery':
			$push = usces_trackPageview_delivery( $push );
			break;

		case 'confirm':
			$push = usces_trackPageview_confirm( $push );
			break;

		case 'ordercompletion':
			$push = usces_trackPageview_ordercompletion( $push );
			break;

		case 'error':
			$push = usces_trackPageview_error( $push );
			break;

		case 'login':
			$push = usces_trackPageview_login( $push );
			break;

		case 'member':
			$push = usces_trackPageview_member( $push );
			break;

		case 'newmemberform':
			$push = usces_trackPageview_newmemberform( $push );
			break;

		case 'newcompletion':
			$push = usces_trackPageview_newcompletion( $push );
			break;

		case 'editmemberform':
			$push = usces_trackPageview_editmemberform( $push );
			break;

		case 'search_item':
			$push = usces_trackPageview_search_item( $push );
			break;

		case 'maintenance':
		case 'editcompletion':
		case 'lostmemberpassword':
		case 'lostcompletion':
		case 'changepassword':
		case 'changepasscompletion':
		default:
			break;
	}
	return $push;
}

/**
 * Univarsal Analytics( Yoast )
 * monsterinsights_frontend_tracking_options_analytics_end
 * yoast-ga-push-array-universal
 *
 * @param array $push Push array.
 * @return array
 */
function usces_Universal_trackPageview_by_Yoast( $push ) {
	global $usces;

	foreach ( $push as $p_key => $p_val ) {
		$pos1 = strpos( (string) $p_val, "'send'" );
		$pos2 = strpos( (string) $p_val, "'pageview'" );
		if ( false !== $pos1 && false !== $pos2 ) {
			unset( $push[ $p_key ] );
		}
	}

	switch ( $usces->page ) {
		case 'cart':
			$push[] = "'send', 'pageview', {'page' : '/wc_cart'}";
			break;

		case 'customer':
			$push[] = "'send', 'pageview', {'page' : '/wc_customer'}";
			break;

		case 'delivery':
			$push[] = "'send', 'pageview', {'page' : '/wc_delivery'}";
			break;

		case 'confirm':
			$push[] = "'send', 'pageview', {'page' : '/wc_confirm'}";
			break;

		case 'ordercompletion':
			$push[]  = "'send', 'pageview', {'page' : '/wc_ordercompletion'}";
			$sesdata = $usces->cart->get_entry();
			if ( isset( $sesdata['order']['ID'] ) && ! empty( $sesdata['order']['ID'] ) ) {
				$order_id    = $sesdata['order']['ID'];
				$data        = $usces->get_order_data( $order_id, 'direct' );
				$cart        = unserialize( $data['order_cart'] );
				$total_price = $usces->get_total_price( $cart ) + $data['order_discount'] - $data['order_usedpoint'];
				if ( $total_price < 0 ) {
					$total_price = 0;
				}

				$push[]     = "'require', 'ecommerce', 'ecommerce.js'";
				$push[]     = "'ecommerce:addTransaction', { 
								id: '" . $order_id . "', 
								affiliation: '" . esc_js( get_option( 'blogname' ) ) . "',
								revenue: '" . $total_price . "',
								shipping: '" . $data['order_shipping_charge'] . "',
								tax: '" . $data['order_tax'] . "'
							}";
				$cart_count = ( $cart && is_array( $cart ) ) ? count( $cart ) : 0;
				for ( $i = 0; $i < $cart_count; $i++ ) {
					$cart_row = $cart[ $i ];
					$post_id  = $cart_row['post_id'];
					$sku      = urldecode( $cart_row['sku'] );
					$quantity = $cart_row['quantity'];
					$itemname = $usces->getItemName( $post_id );
					$skuprice = $cart_row['price'];
					$cats     = $usces->get_item_cat_genre_ids( $post_id );
					if ( is_array( $cats ) ) {
						sort( $cats );
					}
					$category = ( isset( $cats[0] ) ) ? get_cat_name( $cats[0] ) : '';

					$push[] = "'ecommerce:addItem', {
									id: '" . $order_id . "',
									sku: '" . esc_js( $sku ) . "',
									name: '" . esc_js( $itemname ) . "',
									category: '" . esc_js( $category ) . "',
									price: '" . $skuprice . "',
									quantity: '" . $quantity . "'
								}";
				}
				$push[] = "'ecommerce:send'";
			}
			break;

		case 'error':
			$push[] = "'send', 'pageview', {'page' : '/wc_error'}";
			break;

		case 'search_item':
			$push[] = "'send', 'pageview', {'page' : '/wc_search_item'}";
			break;

		case 'maintenance':
			$push[] = "'send', 'pageview', {'page' : '/wc_maintenance'}";
			break;

		case 'login':
			$push[] = "'send', 'pageview', {'page' : '/wc_login'}";
			break;

		case 'member':
			$push[] = "'send', 'pageview', {'page' : '/wc_member'}";
			break;

		case 'newmemberform':
			$push[] = "'send', 'pageview', {'page' : '/wc_newmemberform'}";
			break;

		case 'newcompletion':
			$push[] = "'send', 'pageview', {'page' : '/wc_newcompletion'}";
			break;

		case 'editmemberform':
			$push[] = "'send', 'pageview', {'page' : '/wc_editmemberform'}";
			break;

		case 'editcompletion':
			$push[] = "'send', 'pageview', {'page' : '/wc_editcompletion'}";
			break;

		case 'lostmemberpassword':
			$push[] = "'send', 'pageview', {'page' : '/wc_lostmemberpassword'}";
			break;

		case 'lostcompletion':
			$push[] = "'send', 'pageview', {'page' : '/wc_lostcompletion'}";
			break;

		case 'changepassword':
			$push[] = "'send', 'pageview', {'page' : '/wc_changepassword'}";
			break;

		case 'changepasscompletion':
			$push[] = "'send', 'pageview', {'page' : '/wc_changepasscompletion'}";
			break;

		default:
			$push[] = "'send', 'pageview'";
			break;
	}
	return $push;
}

/**
 * Order completion tracking.
 * wp_enqueue_scripts
 * monsterinsights_frontend_tracking_gtag_after_pageview
 */
function usces_ecommerce_reporting() {
	global $usces;

	if ( 'ordercompletion' !== $usces->page ) {
		return;
	}

	$sesdata = $usces->cart->get_entry();
	if ( isset( $sesdata['order']['ID'] ) && ! empty( $sesdata['order']['ID'] ) ) {
		$order_id    = $sesdata['order']['ID'];
		$data        = $usces->get_order_data( $order_id, 'direct' );
		$cart        = unserialize( $data['order_cart'] );
		$total_price = $usces->get_total_price( $cart ) + $data['order_discount'] - $data['order_usedpoint'];
		if ( $total_price < 0 ) {
			$total_price = 0;
		}
		$ecommerce_reporting = "__gtagTracker('event', 'purchase', {
			transaction_id: '" . $order_id . "', 
			affiliation: '" . esc_js( get_option( 'blogname' ) ) . "',
			value: '" . $total_price . "',
			currency: '" . $usces->get_currency_code() . "',
			tax: '" . $data['order_tax'] . "',
			shipping: '" . $data['order_shipping_charge'] . "',
			items: [";

		$cart_count = ( $cart && is_array( $cart ) ) ? count( $cart ) : 0;
		for ( $i = 0; $i < $cart_count; $i++ ) {
			$cart_row = $cart[ $i ];
			$post_id  = $cart_row['post_id'];
			$sku      = urldecode( $cart_row['sku'] );
			$quantity = $cart_row['quantity'];
			$itemname = $usces->getItemName( $post_id );
			$skuprice = $cart_row['price'];
			$cats     = $usces->get_item_cat_genre_ids( $post_id );
			if ( is_array( $cats ) ) {
				sort( $cats );
			}
			$category = ( isset( $cats[0] ) ) ? get_cat_name( $cats[0] ) : '';

			$ecommerce_reporting .= "{
							id: '" . $post_id . "',
							sku: '" . esc_js( $sku ) . "',
							name: '" . esc_js( $itemname ) . "',
							category: '" . esc_js( $category ) . "',
							price: '" . $skuprice . "',
							quantity: '" . $quantity . "'
						},";
		}
		$ecommerce_reporting .= "]});";

		$monsterinsights_current_version = usces_MonsterInsights_get_version();
		if ( version_compare( $monsterinsights_current_version, '8.0.0', '>=' ) ) {
			wp_register_script( 'after-monsterinsights-frontend-script', '', array( 'monsterinsights-frontend-script' ) );
			wp_enqueue_script( 'after-monsterinsights-frontend-script' );
			wp_add_inline_script( 'after-monsterinsights-frontend-script', $ecommerce_reporting );
		} else {
			wel_esc_script_e( $ecommerce_reporting );
		}
	}
}

/**
 * Universal trackPageview by monsterInsight.
 * monsterinsights_frontend_tracking_options_gtag_end
 *
 * @param array $push Push array.
 * @return array
 */
function usces_Universal_trackPageview_by_monsterInsight( $push ) {
	global $usces;

	$monsterinsights_current_version = usces_MonsterInsights_get_version();
	if ( version_compare( $monsterinsights_current_version, '8.0.0', '>=' ) ) {
		switch ( $usces->page ) {
			case 'cart':
				$push['page_path'] = 'wc_cart';
				break;

			case 'customer':
				$push['page_path'] = 'wc_customer';
				break;

			case 'delivery':
				$push['page_path'] = 'wc_delivery';
				break;

			case 'confirm':
				$push['page_path'] = 'wc_confirm';
				break;

			case 'ordercompletion':
				$push['page_path'] = 'wc_ordercompletion';
				break;

			case 'error':
				$push['page_path'] = 'wc_error';
				break;

			case 'search_item':
				$push['page_path'] = 'wc_search_item';
				break;

			case 'maintenance':
				$push['page_path'] = 'wc_maintenance';
				break;

			case 'login':
				$push['page_path'] = 'wc_login';
				break;

			case 'member':
				$push['page_path'] = 'wc_member';
				break;

			case 'newmemberform':
				$push['page_path'] = 'wc_newmemberform';
				break;

			case 'newcompletion':
				$push['page_path'] = 'wc_newcompletion';
				break;

			case 'editmemberform':
				$push['page_path'] = 'wc_editmemberform';
				break;

			case 'editcompletion':
				$push['page_path'] = 'wc_editcompletion';
				break;

			case 'lostmemberpassword':
				$push['page_path'] = 'wc_lostmemberpassword';
				break;

			case 'lostcompletion':
				$push['page_path'] = 'wc_lostcompletion';
				break;

			case 'changepassword':
				$push['page_path'] = 'wc_changepassword';
				break;

			case 'changepasscompletion':
				$push['page_path'] = 'wc_changepasscompletion';
				break;

			default: // keep origin page path.
				break;
		}
	} else {
		switch ( $usces->page ) {
			case 'cart':
				$push['page_path'] = "'/wc_cart'";
				break;

			case 'customer':
				$push['page_path'] = "'/wc_customer'";
				break;

			case 'delivery':
				$push['page_path'] = "'/wc_delivery'";
				break;

			case 'confirm':
				$push['page_path'] = "'/wc_confirm'";
				break;

			case 'ordercompletion':
				$push['page_path'] = "'/wc_ordercompletion'";
				break;

			case 'error':
				$push['page_path'] = "'/wc_error'";
				break;

			case 'search_item':
				$push['page_path'] = "'/wc_search_item'";
				break;

			case 'maintenance':
				$push['page_path'] = "'/wc_maintenance'";
				break;

			case 'login':
				$push['page_path'] = "'/wc_login'";
				break;

			case 'member':
				$push['page_path'] = "'/wc_member'";
				break;

			case 'newmemberform':
				$push['page_path'] = "'/wc_newmemberform'";
				break;

			case 'newcompletion':
				$push['page_path'] = "'/wc_newcompletion'";
				break;

			case 'editmemberform':
				$push['page_path'] = "'/wc_editmemberform'";
				break;

			case 'editcompletion':
				$push['page_path'] = "'/wc_editcompletion'";
				break;

			case 'lostmemberpassword':
				$push['page_path'] = "'/wc_lostmemberpassword'";
				break;

			case 'lostcompletion':
				$push['page_path'] = "'/wc_lostcompletion'";
				break;

			case 'changepassword':
				$push['page_path'] = "'/wc_changepassword'";
				break;

			case 'changepasscompletion':
				$push['page_path'] = "'/wc_changepasscompletion'";
				break;

			default: // keep origin page path.
				break;
		}
	}
	return $push;
}

/**
 * Classic Analytics ( Yoast )
 * yoast-ga-push-array-ga-js
 *
 * @param array $push Push array.
 * @return array
 */
function usces_Classic_trackPageview_by_Yoast( $push ) {
	global $usces;

	foreach ( $push as $p_key => $p_val ) {
		$pos1 = strpos( (string) $p_val, "'_trackPageview" );
		if ( false !== $pos1 ) {
			unset( $push[ $p_key ] );
		}
	}
	switch ( $usces->page ) {
		case 'cart':
			$push[] = "'_trackPageview', '/wc_cart'";
			break;

		case 'customer':
			$push[] = "'_trackPageview', '/wc_customer'";
			break;

		case 'delivery':
			$push[] = "'_trackPageview', '/wc_delivery'";
			break;

		case 'confirm':
			$push[] = "'_trackPageview', '/wc_confirm'";
			break;

		case 'ordercompletion':
			global $usces;

			$push[]  = "'_trackPageview','/wc_ordercompletion'";
			$sesdata = $usces->cart->get_entry();
			if ( isset( $sesdata['order']['ID'] ) && ! empty( $sesdata['order']['ID'] ) ) {
				$order_id    = $sesdata['order']['ID'];
				$data        = $usces->get_order_data( $order_id, 'direct' );
				$cart        = unserialize( $data['order_cart'] );
				$total_price = $usces->get_total_price( $cart ) + $data['order_discount'] - $data['order_usedpoint'];
				if ( $total_price < 0 ) {
					$total_price = 0;
				}

				$push[]     = "'_addTrans', '" . $order_id . "', '" . esc_js( get_option( 'blogname' ) ) . "', '" . $total_price . "', '" . $data['order_tax'] . "', '" . $data['order_shipping_charge'] . "', '" . esc_js( $data['order_address1'] . $data['order_address2'] ) . "', '" . esc_js( $data['order_pref'] ) . "', '" . get_locale() . "'";
				$cart_count = ( $cart && is_array( $cart ) ) ? count( $cart ) : 0;
				for ( $i = 0; $i < $cart_count; $i++ ) {
					$cart_row = $cart[ $i ];
					$post_id  = $cart_row['post_id'];
					$sku      = urldecode( $cart_row['sku'] );
					$quantity = $cart_row['quantity'];
					$itemname = $usces->getItemName( $post_id );
					$skuprice = $cart_row['price'];
					$cats     = $usces->get_item_cat_genre_ids( $post_id );
					if ( is_array( $cats ) ) {
						sort( $cats );
					}
					$category = ( isset( $cats[0] ) ) ? get_cat_name( $cats[0] ) : '';
					$push[]   = "'_addItem', '" . $order_id . "', '" . esc_js( $sku ) . "', '" . esc_js( $itemname ) . "', '" . esc_js( $category ) . "', '" . $skuprice . "', '" . $quantity . "'";
				}
				$push[] = "'_trackTrans'";
			}
			break;

		case 'error':
			$push[] = "'_trackPageview', '/wc_error'";
			break;

		case 'login':
			$push[] = "'_trackPageview', '/wc_login'";
			break;

		case 'member':
			$push[] = "'_trackPageview', '/wc_member'";
			break;

		case 'newmemberform':
			$push[] = "'_trackPageview', '/wc_newmemberform'";
			break;

		case 'newcompletion':
			$push[] = "'_trackPageview', '/wc_newcompletion'";
			break;

		case 'editmemberform':
			$push[] = "'_trackPageview', '/wc_editmemberform'";
			break;

		case 'search_item':
			$push[] = "'_trackPageview', '/wc_search_item'";
			break;

		case 'maintenance':
			$push[] = "'_trackPageview', '/wc_maintenance'";
			break;

		case 'editcompletion':
			$push[] = "'_trackPageview', '/wc_editcompletion'";
			break;

		case 'lostmemberpassword':
			$push[] = "'_trackPageview', '/wc_lostmemberpassword'";
			break;

		case 'lostcompletion':
			$push[] = "'_trackPageview', '/wc_lostcompletion'";
			break;

		case 'changepassword':
			$push[] = "'_trackPageview', '/wc_changepassword'";
			break;

		case 'changepasscompletion':
			$push[] = "'_trackPageview', '/wc_changepasscompletion'";
			break;

		default:
			$push[] = "'_trackPageview'";
			break;
	}
	return $push;
}

/**
 * Using MonsterInsights.
 * plugins_loaded
 */
function usces_monsterinsights() {
	$monsterinsights_current_version = usces_MonsterInsights_get_version();
	if ( null !== $monsterinsights_current_version && version_compare( $monsterinsights_current_version, '6.0.11', '>' ) ) {
		add_filter( 'monsterinsights_frontend_tracking_options_analytics_end', 'usces_Universal_trackPageview_by_Yoast' );
		add_filter( 'monsterinsights_frontend_tracking_options_gtag_end', 'usces_Universal_trackPageview_by_monsterInsight' );
		if ( version_compare( $monsterinsights_current_version, '8.0.0', '>=' ) ) {
			add_action( 'wp_enqueue_scripts', 'usces_ecommerce_reporting', 10 );
		} else {
			add_action( 'monsterinsights_frontend_tracking_gtag_after_pageview', 'usces_ecommerce_reporting' );
		}
	} else {
		add_filter( 'yoast-ga-push-array-universal', 'usces_Universal_trackPageview_by_Yoast' );
		add_filter( 'yoast-ga-push-array-ga-js', 'usces_Classic_trackPageview_by_Yoast' );
	}
}

/**
 * Get version of MonsterInsights.
 *
 * @return string
 */
function usces_monsterinsights_get_version() {
	if ( ! function_exists( 'MonsterInsights_Pro' ) && ! function_exists( 'MonsterInsights_Lite' ) ) {
		return;
	}
	$instance = class_exists( 'MonsterInsights' ) ? MonsterInsights_Pro() : MonsterInsights_Lite();
	return $instance->version;
}

/**
 * Order memo area.
 * usces_action_order_edit_form_detail_top
 *
 * @param array $data Order data.
 * @param array $csod_meta Custom order data.
 */
function usces_order_memo_form_detail_top( $data, $csod_meta ) {
	global $usces;

	$order_memo = '';
	if ( ! empty( $data['ID'] ) ) {
		$order_memo = $usces->get_order_meta_value( 'order_memo', $data['ID'] );
	}
	$res = '<tr>
				<td class="label border">' . __( 'Administrator Note', 'usces' ) . '</td>
				<td colspan="5" class="col1 border memo">
					<textarea name="order_memo" class="order_memo">' . esc_html( $order_memo ) . '</textarea>
				</td>
			</tr>';
	wel_esc_script_e( $res );
}

/**
 * Update order memo.
 * usces_action_update_orderdata
 *
 * @param object $new_orderdata New order data.
 */
function usces_update_order_memo( $new_orderdata ) {
	global $usces;

	if ( isset( $_POST['order_memo'] ) ) {
		$usces->set_order_meta_value( 'order_memo', $_POST['order_memo'], $new_orderdata->ID );
	}
}

/**
 * Save order memo.
 * usces_action_reg_orderdata
 *
 * @param array $args {
 *     The array of order-related data.
 *     @type array $cart Cart data.
 *     @type array $entry Entry data.
 *     @type int   $order_id Order ID.
 *     @type int   $member_id Member ID.
 *     @type array $payments Payment Method.
 *     @type array $charging_type Charge type.
 *     @type array $results Settlement result data.
 * }
 */
function usces_register_order_memo( $args ) {
	global $usces;
	extract( $args );

	if ( isset( $_POST['order_memo'] ) && $order_id ) {
		$usces->set_order_meta_value( 'order_memo', $_POST['order_memo'], $order_id );
	}
}

/**
 * Tracking number field area.
 * usces_action_order_edit_form_delivery_block
 *
 * @param array $data Order data.
 * @param array $cscs_meta Custom customer data.
 * @param array $action_args Order action.
 * @return void
 */
function usces_add_tracking_number_field( $data, $cscs_meta, $action_args ) {
	global $usces;
	$locale = get_locale();

	$deli_comps = array( 'クロネコヤマト', 'ヤマト運輸', '佐川急便', '日本通運', 'ゆうパック', '日本郵便', '郵便書留', '西濃運輸', '福山通運', '名鉄運輸', '新潟運輸', 'トナミ運輸', '第一貨物', '飛騨倉庫運輸', '西武運輸', 'クリックポスト', 'トールエクスプレス', 'セイノーエクスプレス', '信州名鉄運輸', '大川配送サービス', 'その他' );
	$deli_comps = apply_filters( 'usces_filter_deli_comps', $deli_comps, $data );

	$tracking_number = '';
	if ( ! empty( $data['ID'] ) ) {
		$tracking_number  = $usces->get_order_meta_value( apply_filters( 'usces_filter_tracking_meta_key', 'tracking_number' ), $data['ID'] );
		$delivery_company = $usces->get_order_meta_value( 'delivery_company', $data['ID'] );
	} else {
		$tracking_number  = '';
		$delivery_company = '';
	}
	$res  = '';
	$res .= '
			<tr>
				<td class="label">' . __( 'Delivery company', 'usces' ) . '</td>
				<td class="col1">';
	if ( 'ja' === $locale ) {
		$res .= '		<select name="delivery_company" style="width:100%;" >' . "\n";
		$res .= '<option value="">' . __( '-- Select --', 'usces' ) . '</option>' . "\n";
		foreach ( $deli_comps as $comp ) {
			$res .= '<option value="' . esc_attr( $comp ) . '"' . selected( $comp, $delivery_company, false ) . '>' . esc_html( $comp ) . '</option>' . "\n";
		}
		$res .= '		</select>' . "\n";
	} else {
		$res .= '		<input name="delivery_company" type="text" style="width:100%;" value="' . esc_attr( $delivery_company ) . '">';
	}
	$res .= '	</td>
			</tr>';
	$res .= '<tr>
				<td class="label">' . __( 'Tracking number', 'usces' ) . '</td>
				<td class="col1">
					<input name="tracking_number" type="text" style="width:100%;" value="' . esc_attr( $tracking_number ) . '">
				</td>
			</tr>' . "\n";
	wel_esc_script_e( $res );
}

/**
 * Update tracking number.
 * usces_action_update_orderdata
 *
 * @param object $new_orderdata New order data.
 */
function usces_update_tracking_number( $new_orderdata ) {
	global $usces;

	if ( isset( $_POST['tracking_number'] ) ) {
		$usces->set_order_meta_value( apply_filters( 'usces_filter_tracking_meta_key', 'tracking_number' ), wp_unslash( $_POST['tracking_number'] ), $new_orderdata->ID );
	}

	if ( isset( $_POST['delivery_company'] ) ) {
		$usces->set_order_meta_value( 'delivery_company', wp_unslash( $_POST['delivery_company'] ), $new_orderdata->ID );
	}
}

/**
 * Admin scripts.
 * admin_enqueue_scripts
 *
 * @param string $hook_suffix The current admin page.
 */
function usces_admin_enqueue_scripts( $hook_suffix ) {
	global $wp_scripts;
	if ( false !== strpos( $hook_suffix, 'usc-e-shop' )
		|| false !== strpos( $hook_suffix, 'welcart' )
		|| false !== strpos( $hook_suffix, 'usces' )
	) {
		$ui        = $wp_scripts->query( 'jquery-ui-core' );
		$ui_themes = apply_filters( 'usces_filter_jquery_ui_themes_welcart', 'smoothness' );
		wp_enqueue_style( 'jquery-ui-welcart', "//code.jquery.com/ui/{$ui->ver}/themes/{$ui_themes}/jquery-ui.css" );
	}
	if ( 'welcart-management_page_usces_memberlist' == $hook_suffix
		|| 'toplevel_page_usces_orderlist' == $hook_suffix
	) {
		$path = USCES_FRONT_PLUGIN_URL . '/js/jquery/jquery-cookie.js';
		wp_enqueue_script( 'usces_member_cookie', $path, array( 'jquery' ), USCES_VERSION, true );
	}
}

/**
 * Cron schedules.
 * cron_schedules
 *
 * @param array $schedules An array of non-default cron schedule arrays.
 * @return array
 */
function usces_schedules_intervals( $schedules ) {
	$schedules['weekly'] = array(
		'interval' => 604800,
		'display'  => 'Weekly',
	);
	return $schedules;
}

/**
 * Welcart activate.
 * plugins_loaded
 */
function usces_responce_wcsite() {
	$my_wcid = get_option( 'usces_wcid' );

	if ( isset( $_POST['sname'] ) && isset( $_POST['wcid'] ) && '54.64.221.23' == $_SERVER['REMOTE_ADDR'] ) {
		$data['usces']                     = get_option( 'usces', array() );
		$data['usces_settlement_selected'] = get_option( 'usces_settlement_selected', array() );
		$res                               = json_encode( $data );
		header( 'Content-Type: application/json' );
		wel_esc_script_e( $res );
		exit;
	}
}

/**
 * Welcart activate.
 */
function usces_wcsite_activate() {
	$usces       = get_option( 'usces', array() );
	$circulating = wel_get_circulating_amount();
	$sku_num     = wel_get_sku_total_num();
	$cat_num     = wel_get_cat_total_num();

	$metas['usces_company_name']       = $usces['company_name'];
	$metas['usces_company_tel']        = $usces['tel_number'];
	$metas['usces_company_number']     = $usces['business_registration_number'];
	$metas['usces_company_zip']        = $usces['zip_code'];
	$metas['usces_company_location']   = $usces['address1'] . ' ' . $usces['address2'];
	$metas['usces_inquiry_mail']       = $usces['inquiry_mail'];
	$metas['usces_base_country']       = $usces['system']['base_country'];
	$metas['usces_circulating_amount'] = $circulating['amount'];
	$metas['usces_circulating_count']  = $circulating['count'];
	$metas['usces_categories']         = wel_get_categories();
	$metas['usces_cat_num']            = $cat_num;
	$metas['usces_sku_num']            = $sku_num;
	$metas['usces_themes']             = wel_get_themes();
	$metas['usces_plugins']            = wel_get_plugins();
	$metas['usces_used_sett']          = wel_get_activ_pay_methd();

	$base_metas = array(
		'wcid'   => get_option( 'usces_wcid' ),
		'wchost' => $_SERVER['SERVER_NAME'],
		'refer'  => get_option( 'home' ),
		'act'    => 1,
	);
	$params     = array_merge( $base_metas, $metas );
	usces_wcsite_connection( $params );
}

/**
 * Welcart deactivate.
 */
function usces_wcsite_deactivate() {
	$params = array(
		'wcid'   => get_option( 'usces_wcid' ),
		'wchost' => $_SERVER['SERVER_NAME'],
		'refer'  => get_option( 'home' ),
		'act'    => 0,
	);
	usces_wcsite_connection( $params );
}

/**
 * Session cache limiter.
 * usces_action_session_start
 */
function usces_session_cache_limiter() {
	global $usces;

	if ( $usces->is_cart_page( $_SERVER['REQUEST_URI'] ) && isset( $_REQUEST['usces_page'] ) && 'search_item' == $_REQUEST['usces_page'] ) {
		session_cache_limiter( 'private_no_expire' );
	}
}

/**
 * Disused function
 * usces_action_login_page_footer
 */
function usces_action_login_page_liwpp() {
}

/**
 * Disused function
 * usces_filter_login_page_footer
 *
 * @param string $html Template form.
 * @return string
 */
function usces_filter_login_page_liwpp( $html ) {
	return $html;
}

/**
 * Disused function
 * usces_action_customer_page_member_inform
 */
function usces_action_customer_page_liwpp() {
}

/**
 * Disused function
 * usces_filter_customer_page_member_inform
 *
 * @param string $html Template form.
 * @return string
 */
function usces_filter_customer_page_liwpp( $html ) {
	return $html;
}

/**
 * Disused function
 * usces_filter_login_widget
 *
 * @param string $html Template form.
 * @return string
 */
function usces_filter_login_widget_liwpp( $html ) {
	return $html;
}

/**
 * Disused function
 * wp
 */
function usces_login_width_paypal() {
}

/**
 * Atobarai each availability area.
 *
 * @param string $second_section Second section area.
 * @param int    $post_id Post ID.
 * @return string
 */
function usces_atobarai_each_availability( $second_section, $post_id ) {

	$product = wel_get_product( $post_id );

	$deferred_payment_propriety = (int) $product['atobarai_propriety'];

	$second_section .= '
	<tr>
		<th>' . __( 'Atobarai Propriety', 'usces' ) . '</th>
		<td>
			<label for="deferred_payment_propriety0"><input name="deferred_payment_propriety" id="deferred_payment_propriety0" type="radio" value="0"' . ( ! $deferred_payment_propriety ? ' checked="checked"' : '' ) . '>' . __( 'available', 'usces' ) . '</label>
			<label for="deferred_payment_propriety1"><input name="deferred_payment_propriety" id="deferred_payment_propriety1" type="radio" value="1"' . ( $deferred_payment_propriety ? ' checked="checked"' : '' ) . '>' . __( 'not available', 'usces' ) . '</label>
		</td>
	</tr>';
	return $second_section;
}

/**
 * Disused function
 *
 * @param int    $post_id Post ID.
 * @param object $post Post data.
 */
function usces_atobarai_update_each_availability( $post_id, $post ) {
	// This process has moved to item_post.php 1568.
}

/**
 * Setup of settlement.
 * plugins_loaded
 */
function usces_instance_settlement() {
	global $usces;

	$usces->settlement->setup();
}

/**
 * Setup of extensions.
 * plugins_loaded
 */
function usces_instance_extentions() {
	global $ganbare_tencho, $order_stock_linkage, $data_list_upgrade, $verify_members_email, $data_google_recaptcha, $brute_force;

	require_once USCES_EXTENSIONS_DIR . '/GanbareTencho/GanbareTencho.class.php';
	$ganbare_tencho = new USCES_GANBARE_TENCHO();

	require_once USCES_EXTENSIONS_DIR . '/OrderStockLinkage/order_stock_linkage.php';
	$order_stock_linkage = new USCES_STOCK_LINKAGE();

	require_once USCES_EXTENSIONS_DIR . '/DataListUpgrade/data_list_upgrade.php';
	$data_list_upgrade = new USCES_DATALIST_UPGRADE();

	require_once USCES_EXTENSIONS_DIR . '/VerifyMembersEmail/verify_members_email.php';
	$verify_members_email = new USCES_VERIFY_MEMBERS_EMAIL();

	require_once USCES_EXTENSIONS_DIR . '/GoogleRecaptcha/google_recaptcha.php';
	$data_google_recaptcha = new USCES_GOOGLE_RECAPTCHA();

	require_once USCES_EXTENSIONS_DIR . '/BruteForceCountermeasures/brute_force_countermeasures.php';
	$brute_force = new USCES_BRUTE_FORCE_COUNTER_MEASURES();

	require_once USCES_EXTENSIONS_DIR . '/StructuredDataProduct/structured_data_product.php';
	$structured_data_product = new USCES_STRUCTURED_DATA_PRODUCT();

	require_once USCES_EXTENSIONS_DIR . '/NewProductImageRegister/class-new-product-image-register.php';
	$new_product_image_register = new NEW_PRODUCT_IMAGE_REGISTER();

	require_once USCES_EXTENSIONS_DIR . '/OperationLog/class-operation-log.php';
	$operation_log = new OPERATION_LOG();

	require_once USCES_EXTENSIONS_DIR . '/CreditCardSecurity/class-creditcard-security.php';
	$card_security = new USCES_CREDITCARD_SECURITY();
}

/**
 * Get list of attachment image attributes.
 * wp_get_attachment_image_attributes
 *
 * @param array      $attr Array of attribute values for the image markup, keyed by attribute name.
 * @param WP_Post    $attachment Image attachment post.
 * @param string|int $size Requested image size.
 * @return array
 */
function usces_get_attachment_image_attributes( $attr, $attachment, $size ) {
	global $usces;

	if ( $usces->is_cart_or_member_page( $_SERVER['REQUEST_URI'] ) || $usces->is_inquiry_page( $_SERVER['REQUEST_URI'] ) ) {
		if ( $usces->use_ssl && isset( $attr['srcset'] ) ) {
			$srcset         = $attr['srcset'];
			$attr['srcset'] = str_replace( get_option( 'siteurl' ), USCES_SSL_URL_ADMIN, $srcset );
		}
	}
	return $attr;
}

/**
 * SSL charm
 * init
 *
 * @return void
 */
function usces_ssl_charm() {
	global $usces;
	if ( $usces->use_ssl && ( $usces->is_cart_or_member_page( $_SERVER['REQUEST_URI'] ) || $usces->is_inquiry_page( $_SERVER['REQUEST_URI'] ) ) ) {
		if ( function_exists( 'usces_ob_callback' ) ) {
			ob_start( 'usces_ob_callback' );
		} else {
			ob_start( 'usces_ob_rewrite' );
		}
	}
}

/**
 * Output buffer rewrite
 *
 * @param array $buffer Rewrite buffer.
 * @return array
 */
function usces_ob_rewrite( $buffer ) {
	$pattern     = array(
		'|(<[^<]*)href=\"' . get_option( 'siteurl' ) . '([^>]*)\.css([^>]*>)|',
		'|(<[^<]*)src=\"' . get_option( 'siteurl' ) . '([^>]*>)|',
	);
	$replacement = array(
		'${1}href="' . USCES_SSL_URL_ADMIN . '${2}.css${3}',
		'${1}src="' . USCES_SSL_URL_ADMIN . '${2}',
	);
	$buffer      = preg_replace( $pattern, $replacement, $buffer );
	return $buffer;
}

/**
 * Enqueue scripts.
 * wp_enqueue_scripts
 */
function usces_wp_enqueue_scripts() {
	global $usces;

	$no_cart_css = isset( $usces->options['system']['no_cart_css'] ) ? $usces->options['system']['no_cart_css'] : 0;

	wp_enqueue_style( 'usces_default_css', USCES_FRONT_PLUGIN_URL . '/css/usces_default.css', array(), USCES_VERSION );
	wp_enqueue_style( 'dashicons' );

	if ( ! $no_cart_css ) {
		wp_enqueue_style( 'usces_cart_css', USCES_FRONT_PLUGIN_URL . '/css/usces_cart.css', array( 'usces_default_css' ), USCES_VERSION );
	}

	$theme_version = defined( 'USCES_THEME_VERSION' ) ? USCES_THEME_VERSION : USCES_VERSION;
	if ( file_exists( get_stylesheet_directory() . '/usces_cart.css' ) ) {
		wp_enqueue_style( 'theme_cart_css', get_stylesheet_directory_uri() . '/usces_cart.css', array( 'usces_default_css' ), $theme_version );
	}

	if ( $usces->is_cart_or_member_page( $_SERVER['REQUEST_URI'] ) ) {

		if ( isset( $usces->options['address_search'] ) && 'activate' == $usces->options['address_search'] ) {
			wp_enqueue_script( 'usces_ajaxzip3', 'https://ajaxzip3.github.io/ajaxzip3.js', array(), current_time( 'timestamp' ), false );
		}

		if ( isset( $usces->page ) && 'confirm' === $usces->page ) {
			$cart_comfirm = USCES_FRONT_PLUGIN_URL . '/js/cart_confirm.js';
			wp_enqueue_script( 'usces_cart_comfirm', $cart_comfirm, array( 'jquery' ), current_time( 'timestamp' ), false );
		}

		if ( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ) {
			$member_common_js = USCES_FRONT_PLUGIN_URL . '/js/member_common.js';
			wp_enqueue_script( 'usces_member_page_js', $member_common_js, array( 'jquery' ), current_time( 'timestamp' ), true );
		}
	}
}

/**
 * Check confirm.
 * wp_ajax_welcart_confirm_check
 */
function welcart_confirm_check_ajax() {
	global $usces;

	$nonce     = filter_input( INPUT_POST, 'wc_nonce', FILTER_SANITIZE_SPECIAL_CHARS );
	$ajax      = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_SPECIAL_CHARS );
	$uscesid   = filter_input( INPUT_POST, 'uscesid', FILTER_SANITIZE_SPECIAL_CHARS );
	$condition = filter_input( INPUT_POST, 'wc_condition' );

	if ( 'welcart_confirm_check' !== $ajax ) {
		$res = 'not permitted1';
		wp_send_json_error( $res );
	}

	if ( ! wp_verify_nonce( $nonce, 'wc_confirm' ) ) {
		$res = 'not permitted2';
		wp_send_json_error( $res );
	}
	if ( PHP_SESSION_NONE === session_status() && $uscesid ) {
		$sessid = $usces->uscesdc( $uscesid );
		session_id( $sessid );
		@session_start(); // phpcs:ignore
	}

	$current['entry'] = $usces->cart->get_entry();
	$current['cart']  = $usces->cart->get_cart();
	session_write_close();

	$condition = json_decode( urldecode( $condition ), true );

	if ( $condition == $current ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		$res = 'same';
	} elseif ( empty( $current['cart'] ) ) {
		$res = 'timeover';
	} elseif ( $current['cart'] == $condition['cart'] && $current['entry'] != $condition['entry'] ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		$res = 'entrydiff';
	} else {
		$res = 'different';
	}
	wp_send_json_success( $res );
}

/**
 * Parameter uscesL10n
 * usces_filter_uscesL10n
 *
 * @param string $nouse Unused.
 * @param int    $post_id Post ID.
 * @return string
 */
function usces_confirm_uscesL10n( $nouse, $post_id ) {
	global $usces;

	if ( 'confirm' === $usces->page ) {
		$condition['entry'] = $usces->cart->get_entry();
		$condition['cart']  = $usces->cart->get_cart();

		$js  = '';
		$js .= "'condition': '" . urlencode( wp_json_encode( $condition ) ) . "',\n";
		$js .= "'cart_url': '" . USCES_CART_URL . "',\n";
		$js .= "'check_mes': '" . __( 'Purchase information has been updated. Please repeat the procedure.\n\nPlease do not open and work more than one tab (window).\n', 'usces' ) . "',\n";
		return $js;
	}
}

/**
 * Search zipcode
 * usces_filter_states_form_js
 *
 * @param string $js Script.
 * @return string
 */
function usces_search_zipcode_check( $js ) {
	global $usces;

	$option = get_option( 'usces', array() );
	if ( ! isset( $option['address_search'] ) || 'activate' != $option['address_search'] ) {
		return $js;
	}

	if ( ( ( $usces->use_js && ( is_page( USCES_MEMBER_NUMBER ) || $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ) )
			&& ( ( true === $usces->is_member_logged_in() && WCUtils::is_blank( $usces->page ) ) || 'member' == $usces->page || 'editmemberform' == $usces->page || 'newmemberform' == $usces->page ) )
			||
		( ( $usces->use_js && ( is_page( USCES_CART_NUMBER ) || $usces->is_cart_page( $_SERVER['REQUEST_URI'] ) ) )
			&& ( 'customer' == $usces->page || 'delivery' == $usces->page ) ) ) {

		$zip_id = ( isset( $_REQUEST['usces_page'] ) && 'msa_setting' == $_REQUEST['usces_page'] ) ? 'msa_zip' : 'zipcode';
		$js    .= '
	<script type="text/javascript">
	(function($) {
		$("#search_zipcode").click(function () {
			var str = $("#' . $zip_id . '").val();
			if( !str.match(/^\d{7}$|^\d{3}-\d{4}$/) ){
				alert("' . __( 'Please enter the zip code correctly.', 'usces' ) . '");
				$("#' . $zip_id . '").focus();
			}
		});
	})(jQuery);
	</script>';
	}
	return $js;
}

/**
 * Admin member list page.
 * load-welcart-management_page_usces_memberlist
 */
function usces_admin_member_list_hook() {

	if ( ! isset( $_POST['member_list_options_apply'] ) ) {
		return;
	}

	$list_option = get_option( 'usces_memberlist_option', array() );
	foreach ( (array) $list_option['view_column'] as $key => $value ) {
		if ( isset( $_POST['hide'][ $key ] ) ) {
			$list_option['view_column'][ $key ] = 1;
		} else {
			$list_option['view_column'][ $key ] = 0;
		}
	}
	$list_option['max_row'] = (int) $_POST['member_list_per_page'];

	update_option( 'usces_memberlist_option', $list_option );
}

/**
 * Admin order list page.
 * load-toplevel_page_usces_orderlist
 *
 * @param string $hook The current admin page.
 * @return void
 */
function usces_admin_order_list_hook( $hook ) {
	if ( ! isset( $_POST['order_list_options_apply'] ) ) {
		return;
	}

	$list_option = get_option( 'usces_orderlist_option', array() );
	foreach ( (array) $list_option['view_column'] as $key => $value ) {
		if ( isset( $_POST['hide'][ $key ] ) ) {
			$list_option['view_column'][ $key ] = 1;
		} else {
			$list_option['view_column'][ $key ] = 0;
		}
	}
	$list_option['max_row'] = (int) $_POST['order_list_per_page'];

	update_option( 'usces_orderlist_option', $list_option );
}

/**
 * Admin member list screen settings
 * screen_settings
 *
 * @param string $screen_settings Screen settings.
 * @param object $screen WP_Screen object.
 * @return string
 */
function usces_memberlist_screen_settings( $screen_settings, $screen ) {
	if ( 'welcart-management_page_usces_memberlist' != $screen->id
	|| ( isset( $_REQUEST['member_action'] ) && 'edit' == $_REQUEST['member_action'] ) ) {
		return $screen_settings;
	}

	require_once USCES_PLUGIN_DIR . '/classes/memberList.class.php';
	$memberlist  = new WlcMemberList();
	$arr_column  = $memberlist->get_column();
	$list_option = get_option( 'usces_memberlist_option', array() );
	$init_view   = array( 'ID', 'name1', 'name2', 'pref', 'address1', 'tel', 'email', 'entrydate', 'rank', 'point' );

	$screen_settings = '
	<fieldset class="metabox-prefs">
		<legend>' . __( 'Columns' ) . '</legend>';
	foreach ( $arr_column as $key => $value ) {
		if ( 'ID' == $key || 'csod_' == substr( $key, 0, 5 ) ) {
			continue;
		}

		if ( ! isset( $list_option['view_column'][ $key ] ) && in_array( $key, $init_view ) ) {
			$list_option['view_column'][ $key ] = 1;
		} elseif ( ! isset( $list_option['view_column'][ $key ] ) ) {
			$list_option['view_column'][ $key ] = 0;
		}

		$checked          = $list_option['view_column'][ $key ] ? ' checked="checked"' : '';
		$screen_settings .= '<label><input class="hide-column-tog" name="hide[' . $key . ']" type="checkbox" id="' . $key . '-hide" value="' . esc_attr( $value ) . '"' . $checked . ' />' . esc_html( $value ) . '</label>' . "\n";
	}
	$screen_settings .= '</fieldset>';

	if ( ! isset( $list_option['max_row'] ) ) {
		$list_option['max_row'] = 50;
	}

	$screen_settings .= '<fieldset class="screen-options">
		<legend>' . __( 'Pagination' ) . '</legend>
		<label for="edit_post_per_page">' . __( 'Number of items per page:' ) . '</label>
		<input type="number" step="1" min="1" max="999" class="screen-per-page" name="member_list_per_page" id="member_list_per_page" maxlength="3" value="' . (int) $list_option['max_row'] . '" />
	</fieldset>
	<p class="submit"><input type="submit" name="member_list_options_apply" id="screen-options-apply" class="button button-primary" value="' . __( 'Apply' ) . '"  /></p>';

	update_option( 'usces_memberlist_option', $list_option );
	return $screen_settings;
}

/**
 * Admin order list screen settings
 * screen_settings
 *
 * @param string $screen_settings Screen settings.
 * @param object $screen WP_Screen object.
 * @return string
 */
function usces_orderlist_screen_settings( $screen_settings, $screen ) {
	if ( 'toplevel_page_usces_orderlist' != $screen->id
	|| ( isset( $_REQUEST['order_action'] ) && 'edit' == $_REQUEST['order_action'] ) ) {
		return $screen_settings;
	}

	require_once USCES_PLUGIN_DIR . '/classes/orderList2.class.php';
	$orderlist   = new WlcOrderList();
	$arr_column  = $orderlist->get_all_column();
	$list_option = get_option( 'usces_orderlist_option', array() );
	$init_view   = array( 'deco_id', 'order_date', 'process_status', 'payment_name', 'receipt_status', 'total_price', 'deli_method', 'mem_id', 'name1', 'name2', 'pref' );
	if ( defined( 'WCEX_AUTO_DELIVERY' ) && version_compare( WCEX_AUTO_DELIVERY_VERSION, '1.4.0', '>=' ) ) {
		$init_view[] = 'reg_id';
	}
	$init_view = apply_filters( 'usces_filter_orderlist_column_init_view', $init_view );

	$screen_settings = '
	<fieldset class="metabox-prefs">
		<legend>' . __( 'Columns' ) . '</legend>';
	foreach ( $arr_column as $key => $value ) {

		if ( ! isset( $list_option['view_column'][ $key ] ) && in_array( $key, $init_view ) ) {
			$list_option['view_column'][ $key ] = 1;
		} elseif ( ! isset( $list_option['view_column'][ $key ] ) ) {
			$list_option['view_column'][ $key ] = 0;
		}

		$checked          = ( isset( $list_option['view_column'][ $key ] ) && $list_option['view_column'][ $key ] ) ? ' checked="checked"' : '';
		$screen_settings .= '<label><input class="hide-column-tog" name="hide[' . $key . ']" type="checkbox" id="' . $key . '-hide" value="' . $key . '"' . $checked . ' />' . esc_html( $value ) . '</label>' . "\n";
	}
	$screen_settings .= '</fieldset>';

	if ( ! isset( $list_option['max_row'] ) ) {
		$list_option['max_row'] = 50;
	}

	$screen_settings .= '<fieldset class="screen-options">
		<legend>' . __( 'Pagination' ) . '</legend>
		<label for="edit_post_per_page">' . __( 'Number of items per page:' ) . '</label>
		<input type="order" step="1" min="1" max="999" class="screen-per-page" name="order_list_per_page" id="order_list_per_page" maxlength="3" value="' . (int) $list_option['max_row'] . '" />
	</fieldset>
	<p class="submit"><input type="submit" name="order_list_options_apply" id="screen-options-apply" class="button button-primary" value="' . __( 'Apply' ) . '"  /></p>';

	update_option( 'usces_orderlist_option', $list_option );
	return $screen_settings;
}

/**
 * New member registration anti-spam.
 * usces_filter_member_check
 *
 * @param string $mes Message.
 * @return string
 */
function usces_memberreg_spamcheck( $mes ) {

	if ( ! WCUtils::is_blank( $_POST['member']['name1'] ) && trim( $_POST['member']['name1'] ) == trim( $_POST['member']['name2'] ) ) {
		$mes .= __( 'Name is not correct', 'usces' ) . '<br />';
	}

	if ( ! WCUtils::is_blank( $_POST['member']['address1'] ) && trim( $_POST['member']['address1'] ) == trim( $_POST['member']['address2'] ) ) {
		$mes .= __( 'Address is not correct', 'usces' ) . '<br />';
	}

	if ( $mes ) {
		usces_log( 'memberreg_spamcheck : ' . $mes, 'acting_transaction.log' );
	}

	return $mes;
}

/**
 * New member registration anti-spam.
 * usces_filter_member_check_fromcart
 *
 * @param string $mes Message.
 * @return string
 */
function usces_fromcart_memberreg_spamcheck( $mes ) {

	if ( ! WCUtils::is_blank( $_POST['customer']['name1'] ) && trim( $_POST['customer']['name1'] ) == trim( $_POST['customer']['name2'] ) ) {
		$mes .= __( 'Name is not correct', 'usces' ) . '<br />';
	}

	if ( ! WCUtils::is_blank( $_POST['customer']['address1'] ) && trim( $_POST['customer']['address1'] ) == trim( $_POST['customer']['address2'] ) ) {
		$mes .= __( 'Address is not correct', 'usces' ) . '<br />';
	}

	if ( $mes ) {
		usces_log( 'fromcart_memberreg_spamcheck : ' . $mes, 'acting_transaction.log' );
	}

	return $mes;
}

/**
 * Welcart priority
 * pre_update_option_active_plugins
 *
 * @param mixed $active_plugins The new, unserialized option value.
 * @param mixed $old_value The old option value.
 * @return array
 */
function usces_priority_active_plugins( $active_plugins, $old_value ) {
	foreach ( $active_plugins as $no => $path ) {
		if ( USCES_PLUGIN_BASENAME == $path ) {
			unset( $active_plugins[ $no ] );
			array_unshift( $active_plugins, USCES_PLUGIN_BASENAME );
			break;
		}
	}
	return $active_plugins;
}

/**
 * Password change e-mail.
 * usces_action_changepass_page_inform
 */
function usces_action_lostmail_inform() {
	$mem_mail = wp_unslash( $_REQUEST['mem'] );
	$lostkey  = wp_unslash( $_REQUEST['key'] );
	$html     = '
	<input type="hidden" name="mem" value="' . esc_attr( $mem_mail ) . '" />
	<input type="hidden" name="key" value="' . esc_attr( $lostkey ) . '" />' . "\n";
	wel_esc_script_e( $html );
}

/**
 * Password change e-mail.
 * usces_filter_changepassword_inform
 *
 * @param string $html Form.
 * @return string
 */
function usces_filter_lostmail_inform( $html ) {
	$mem_mail = wp_unslash( $_REQUEST['mem'] );
	$lostkey  = wp_unslash( $_REQUEST['key'] );
	$html    .= '
	<input type="hidden" name="mem" value="' . esc_attr( $mem_mail ) . '" />
	<input type="hidden" name="key" value="' . esc_attr( $lostkey ) . '" />' . "\n";
	return $html;
}

/**
 * Purchase template form.
 * usces_filter_confirm_inform
 *
 * @param string $html Purchase template form.
 * @param array  $payments Payment data.
 * @param string $acting_flag Payment method.
 * @param string $rand Settlement link key.
 * @param string $purchase_disabled Disabled tag.
 * @return string
 */
function wc_purchase_nonce( $html, $payments, $acting_flag, $rand, $purchase_disabled ) {
	global $usces;

	$nonacting_settlements = apply_filters( 'usces_filter_nonacting_settlements', $usces->nonacting_settlements );
	if ( strpos( $html, '_purchase_nonce' ) || in_array( $payments['settlement'], $nonacting_settlements ) ) {
		return $html;
	}

	$noncekey = 'wc_purchase_nonce' . $usces->get_uscesid( false );
	$html    .= wp_nonce_field( $noncekey, '_purchase_nonce', false, false ) . "\n";
	return $html;
}

/**
 * Purchase check
 * usces_purchase_check
 */
function wc_purchase_nonce_check() {
	global $usces;

	$entry = $usces->cart->get_entry();
	if ( ! isset( $entry['order']['payment_name'] ) || empty( $entry['order']['payment_name'] ) ) {
		wp_redirect( home_url() );
		exit;
	}

	$nonacting_settlements = apply_filters( 'usces_filter_nonacting_settlements', $usces->nonacting_settlements );
	$payments              = usces_get_payments_by_name( $entry['order']['payment_name'] );
	if ( in_array( $payments['settlement'], $nonacting_settlements ) ) {
		return true;
	}

	$nonce    = isset( $_REQUEST['_purchase_nonce'] ) ? $_REQUEST['_purchase_nonce'] : '';
	$noncekey = 'wc_purchase_nonce' . $usces->get_uscesid( false );
	if ( empty( $nonce ) || wp_verify_nonce( $nonce, $noncekey ) ) {
		return true;
	}

	wp_redirect( home_url() );
	exit;
}

/**
 * Checking in $usces->use_point()
 * usces_action_confirm_page_point_inform
 */
function usces_use_point_nonce() {
	global $usces;

	$noncekey = 'use_point' . $usces->get_uscesid( false );
	wp_nonce_field( $noncekey, 'wc_nonce' );
}

/**
 * Check post member page
 * usces_action_newmember_page_inform
 * usces_action_memberinfo_page_inform
 * usces_action_newpass_page_inform
 * usces_action_changepass_page_inform
 * usces_action_customer_page_inform
 */
function usces_post_member_nonce() {
	global $usces;

	$noncekey = 'post_member' . $usces->get_uscesid( false );
	wp_nonce_field( $noncekey, 'wc_nonce' );
}

/**
 * Check post member login page
 * usces_action_login_page_inform
 * usces_action_customer_page_member_inform
 */
function usces_member_login_nonce() {
	global $usces;

	$noncekey = 'post_member' . $usces->get_uscesid( false );
	wp_nonce_field( $noncekey, 'wel_nonce' );
}

/**
 * Order edit form additional information
 * usces_action_order_edit_form_detail_bottom
 *
 * @param array $data Order data.
 * @param array $cscs_meta Custom customer data.
 * @param array $action_args Order action.
 * @return void
 */
function wel_order_edit_customer_additional_information( $data, $cscs_meta, $action_args ) {
	global $usces;

	$order_id = isset( $data['ID'] ) ? (int) $data['ID'] : 0;
	if ( ! $order_id ) {
		return;
	}

	$value = $usces->get_order_meta_value( 'extra_info', $order_id );
	if ( ! $value ) {
		return;
	}

	$infos = unserialize( $value );
	$html  = '<tr>
	<td colspan="2" class="label cus_note_label">' . __( 'Others', 'usces' ) . '</td>
	<td colspan="4" class="cus_note_label">' . "\n";
	$html .= '<table border="0" cellspacing="0" class="extra_info cus_info">' . "\n";
	foreach ( $infos as $key => $info ) {
		$key   = str_replace( 'USER_AGANT', 'USER_AGENT', $key );
		$html .= '<tr><td class="label cus_note_label">' . esc_html( $key ) . '</td><td class="cus_note_label">' . esc_html( $info ) . '</td></tr>' . "\n";
	}
	$html .= '</table>' . "\n";
	$html .= '</td></tr>' . "\n";

	wel_esc_script_e( $html );
}

/**
 * Save additional information.
 * usces_post_reg_orderdata
 *
 * @param int   $order_id Order ID.
 * @param array $results Settlement result data.
 * @return void
 */
function wel_save_extra_info_to_ordermeta( $order_id, $results ) {
	global $usces;

	if ( ! $order_id ) {
		return;
	}

	$info = array(
		'IP'         => ( isset( $_SERVER['REMOTE_ADDR'] ) ) ? $_SERVER['REMOTE_ADDR'] : '',
		'USER_AGENT' => ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) ? $_SERVER['HTTP_USER_AGENT'] : '',
	);

	$usces->set_order_meta_value( 'extra_info', serialize( $info ), $order_id );
}

/**
 * Navigation menu arguments.
 * wp_nav_menu_args
 *
 * @param array $args An array containing wp_nav_menu() arguments.
 * @return array
 */
function usces_wp_nav_menu_args( $args ) {

	usces_remove_filter();

	return $args;
}

/**
 * Navigation menu.
 * wp_nav_menu
 *
 * @param string   $nav_menu The HTML content for the navigation menu.
 * @param stdClass $args     An object containing wp_nav_menu() arguments.
 * @return string
 */
function usces_wp_nav_menu( $nav_menu, $args ) {

	usces_reset_filter();

	return $nav_menu;
}

/**
 * Session write close.
 * admin_init
 */
function usces_close_session() {
	if ( session_status() == PHP_SESSION_ACTIVE ) {
		session_write_close();
	}
}

/**
 * Handles OPTIONS requests for the server.
 *
 * This is handled outside of the server code, as it doesn't obey normal route
 * mapping.
 *
 * @since 4.4.0
 *
 * @param mixed           $response Current reponse, either response or `null` to indicate pass-through.
 * @param WP_REST_Server  $handler  ResponseHandler instance (usually WP_REST_Server).
 * @param WP_REST_Request $request  The request that was used to make current response.
 * @return WP_REST_Response Modified response, either response or `null` to indicate pass-through.
 */
function usces_close_session_loopback( $response, $handler, $request ) {
	$router = $request->get_route();
	if ( ! empty( $router ) && '/wp-site-health/v1/tests/loopback-requests' == $router ) {
		usces_close_session();
	}
	return $response;
}

/**
 * Google recaptcha script.
 * wp_print_footer_scripts
 */
function usces_add_google_recaptcha_v3_script() {
	global $usces, $usces_entries, $usces_carts;

	if ( $usces->is_member_page( $_SERVER['REQUEST_URI'] ) && 'newmemberform' === $usces->page ) {
		print_google_recaptcha_response( 'create_new_member', 'memberpages' );
	}

	if ( ( $usces->is_cart_page( $_SERVER['REQUEST_URI'] ) && 'customer' === $usces->page ) ) {
		print_google_recaptcha_response( 'customer_order_page', 'customer-info', 'customer_form' );
	}
	if ( ( $usces->is_cart_page( $_SERVER['REQUEST_URI'] ) && 'delivery' === $usces->page ) ) {
		print_google_recaptcha_response( 'delivery_order_page', 'delivery-info', '' );
	}
}

/**
 * Google recaptcha badge display.
 *
 * @param string $action Page action.
 * @param string $parent_element_id Parent page.
 * @param string $form_name Page name.
 * @return void
 */
function print_google_recaptcha_response( $action, $parent_element_id, $form_name = '' ) {
	$option = get_option( 'usces_ex', array() );
	if ( isset( $option['system']['google_recaptcha']['status'] ) && $option['system']['google_recaptcha']['status'] && ! ( empty( $option['system']['google_recaptcha']['site_key'] ) ) && ! ( empty( $option['system']['google_recaptcha']['secret_key'] ) ) ) {
		$site_key = isset( $option['system']['google_recaptcha']['site_key'] ) ? $option['system']['google_recaptcha']['site_key'] : '';
		echo '<script src="https://www.google.com/recaptcha/api.js?render=' . esc_js( $site_key ) . '"></script>';
		echo '<script>';
		echo 'grecaptcha.ready(function() {';
		echo '	grecaptcha.execute(\'' . esc_js( $site_key ) . '\', {action: "' . esc_js( $action ) . '"}).then(function(token) {';
		echo '		jQuery("form").append(\'<input type="hidden" id="elm_recaptcha_response" name="recaptcha_response" value="\'+ token +\'">\');';
		echo '		if(!jQuery("input[type=hidden]").is("[name=recaptcha_response]")) { jQuery("input[name=member_regmode]").after(\'<input type="hidden" id="elm_recaptcha_response" name="recaptcha_response" value="\'+ token +\'">\'); }';
		echo '	});';
		echo '});';
		echo '</script>';

		// run time reload google captcha token v3 key after 1 minutes.
		echo '<script>';
		echo 'var max_google_reload_times = 10;';
		echo 'var google_reload_times = 1;';
		echo 'var function_token_reload = setInterval(function(){';
		echo '	if (google_reload_times > max_google_reload_times) {';
		echo '		clearInterval(function_token_reload);';
		echo '	} else { ';
		echo '		google_reload_times = google_reload_times + 1;';
		echo '		grecaptcha.ready(function() {';
		echo '			grecaptcha.execute(\'' . esc_js( $site_key ) . '\', {action: "' . esc_js( $action ) . '"}).then(function(token) {';
		echo '				jQuery("form #elm_recaptcha_response").val(token);';
		echo '			});';
		echo '		});';
		echo '	}';
		echo '}, 1000*60);';
		echo '</script>';
	}
}

/**
 * Upload CSV.
 * wp_ajax_wel_item_upload_ajax
 *
 * @return void
 */
function wel_item_upload_ajax() {
	$_REQUEST['action'] = 'upload_register';
	usces_item_uploadcsv();
}

/**
 * Check progress.
 * wp_ajax_wel_item_progress_check_ajax
 *
 * @since 2.7.7
 * @return void
 */
function wel_item_progress_check_ajax() {
	require_once USCES_PLUGIN_DIR . '/functions/progress-check.php';
	usces_item_progress_check();
}

/**
 * Completed.
 * wp_ajax_wel_item_progress_completed_ajax
 *
 * @since 2.8.5
 * @return void
 */
function wel_item_progress_completed_ajax() {
	check_ajax_referer( 'wel_progress_check_ajax', 'nonce' );

	if ( 4 > usces_get_admin_user_level() ) {
		die( 'user_level' );
	}

	$logfile = wp_unslash( filter_input( INPUT_POST, 'logfile', FILTER_DEFAULT ) );
	$logfile = WP_CONTENT_DIR . USCES_UPLOAD_TEMP . DIRECTORY_SEPARATOR . $logfile;
	// Make sure the file is exist.
	if ( usces_is_reserved_file( $logfile ) ) {
		// Get the content and echo it.
		$text = file_get_contents( $logfile );
		echo( $text );
	}
	exit;
}

/**
 * Implementation of hook usces_filter_admin_custom_field_input_value()
 * Append new options from the instance of the order custom field.
 *
 * @since 2.5.3
 * @see usces_admin_custom_field_input()
 * @param string $value List of options.
 * @param string $key The custom field key.
 * @param string $entry The instance of the custom field.
 * @param string $custom_field The type of the custom field. Ex: order, delivery, customer, member...
 * @return string $value List of options appended new options from the instance.
 */
function usces_filter_admin_custom_field_input_value( $value, $key, $entry, $custom_field ) {
	if ( 'order' === $custom_field ) {
		$means                = $entry['means'];
		$is_choice_field_type = '0' === $means || '3' === $means || '4' === $means;
		if ( $is_choice_field_type ) {
			$options = explode( "\n", $value );

			if ( is_array( $entry['data'] ) ) {
				$data = $entry['data'];
			} else {
				$data = ( ! empty( $entry['data'] ) && '#NONE#' !== $entry['data'] ) ? array( $entry['data'] ) : array();
			}

			$diff_values = array_diff( $data, $options );

			if ( ! empty( $diff_values ) ) {
				$options = array_merge( $options, $diff_values );
				$value   = implode( "\n", $options );
			}
		}
	}

	return $value;
}

/**
 * Create Post type for Welcart product.
 * init
 *
 * @since 2.5.5
 */
function usces_create_product_type() {
	register_post_type(
		'product',
		array(
			'labels'        => array(
				'name'          => __( 'Items', 'usces' ),
				'singular_name' => 'product',
			),
			'public'        => false,
			'has_archive'   => false,
			'menu_position' => 3,
			'show_in_rest'  => false,
		)
	);
}

/**
 * Executement release card update lock.
 * usces_action_member_list_page
 *
 * @since 2.5.8
 * @param string $member_action Action.
 */
function wel_execute_release_card_update_lock( $member_action ) {
	if ( 'editpost' === $member_action ) {
		$flag      = filter_input( INPUT_POST, 'release_card_update_lock', FILTER_DEFAULT );
		$member_id = filter_input( INPUT_POST, 'member_id', FILTER_VALIDATE_INT );
		if ( 'release' === $flag && ! empty( $member_id ) ) {
			wel_release_card_update_lock( $member_id );
		}
	}
}

/**
 * Welcart auto update control.
 * auto_update_plugin
 *
 * @since 2.8
 * @param bool|null $update Whether to update.
 * @param object    $item   The update offer.
 * @return boolean  $update
 */
function usces_auto_update_welcart( $update, $item ) {
	$targets = array( 'usc-e-shop' );
	if ( in_array( $item->slug, $targets ) ) {

		$available_updates = get_site_transient( 'update_plugins' );
		if ( ! isset( $available_updates->response['usc-e-shop/usc-e-shop.php'] ) ) {
			return true;
		}

		$plugin_info   = $available_updates->response['usc-e-shop/usc-e-shop.php'];
		$current_arr   = explode( '.', USCES_VERSION, 3 );
		$current_major = (string) $current_arr[0] . '.' . (string) $current_arr[1];
		$new_arr       = explode( '.', $plugin_info->new_version, 3 );
		$new_major     = (string) $new_arr[0] . '.' . (string) $new_arr[1];

		if ( $current_major === $new_major ) {
			return true;
		} else {
			return false;
		}
	} else {
		return $update;
	}
}

/**
 * Check if the same item code does not exist.
 * wp_ajax_wel_item_code_exists_ajax
 *
 * @since 2.8
 * @param string $item_code Item code.
 * @return boolean $res true if present, false otherwise.
 */
function wel_item_code_exists_ajax() {

	$wc_nonce  = filter_input( INPUT_POST, 'wc_nonce' );
	$item_code = filter_input( INPUT_POST, 'item_code' );

	$res = array(
		'result'    => null,
		'item_code' => $item_code,
		'message'   => '',
	);

	if ( ! wp_verify_nonce( $wc_nonce, 'check_item_code' ) ) {
		wp_send_json( $res );
	}

	$post_id = wel_get_id_by_item_code( $item_code, false );

	$res['result']  = $post_id;
	$res['message'] = esc_js( sprintf( __( 'Product code %s has already been registered.', 'usces' ), $item_code ) );

	wp_send_json( $res );
}

/**
 * All credit security unlocked.
 * wp_ajax_credit_security_unlock
 */
function wel_credit_security_unlock() {

	$wc_nonce = filter_input( INPUT_POST, 'wc_nonce' );
	$res      = array();

	if ( ! wp_verify_nonce( $wc_nonce, 'credit_security_unlock' ) ) {
		wp_send_json( $res );
	}

	wel_all_unlock();

	wp_send_json( $res );
}
