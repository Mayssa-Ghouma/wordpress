<?php
/**
 * Admin Page functions.
 *
 * @package Welcart
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.PHP.NoSilencedErrors

/**
 * Front scripts.
 */
function usces_states_form_js() {
	global $usces;

	$js = '';
	if ( $usces->use_js
		&& ( ( ( is_page( USCES_MEMBER_NUMBER ) || $usces->is_member_page( $_SERVER['REQUEST_URI'] ) ) && ( ( true === $usces->is_member_logged_in() && WCUtils::is_blank( $usces->page ) ) || 'member' === $usces->page || 'editmemberform' === $usces->page || 'newmemberform' === $usces->page ) )
		|| ( ( is_page( USCES_CART_NUMBER ) || $usces->is_cart_page( $_SERVER['REQUEST_URI'] ) ) && ( 'customer' === $usces->page || 'delivery' === $usces->page ) )
		) ) {

		$js .= '<script type="text/javascript">
		(function($) {
		uscesForm = {
			settings: {
				url: uscesL10n.ajaxurl,
				type: "POST",
				cache: false
			},
			
			changeStates : function( country, type ) {
	
				var s = this.settings;
				s.url = "' . USCES_SSL_URL . '/";
				s.data = "usces_ajax_action=change_states&country=" + country;
				$.ajax( s ).done(function( data ){
					if( "error" == data ){
						alert("error");
					}else{
						$("select#" + type + "_pref").html( data );
						if( customercountry == country && "customer" == type ){
							$("#" + type + "_pref").prop({selectedIndex:customerstate});
						}else if( deliverycountry == country && "delivery" == type ){
							$("#" + type + "_pref").prop({selectedIndex:deliverystate});
						}else if( customercountry == country && "member" == type ){
							$("#" + type + "_pref").prop({selectedIndex:customerstate});
						}
					}
				}).fail(function( msg ){
					alert("error");
				});
				return false;
			}
		};';

		if ( 'customer' === $usces->page ) {

			$js .= 'var customerstate = $("#customer_pref").get(0).selectedIndex;
			var customercountry = $("#customer_country").val();
			var deliverystate = "";
			var deliverycountry = "";
			var memberstate = "";
			var membercountry = "";
			$("#customer_country").change(function () {
				var country = $("#customer_country option:selected").val();
				uscesForm.changeStates( country, "customer" );
			});';

		} elseif ( 'delivery' === $usces->page ) {

			$js .= 'var customerstate = "";
			var customercountry = "";
			var deliverystate = $("#delivery_pref").get(0).selectedIndex;
			var deliverycountry = $("#delivery_country").val();
			var memberstate = "";
			var membercountry = "";
			$("#delivery_country").change(function () {
				var country = $("#delivery_country option:selected").val();
				uscesForm.changeStates( country, "delivery" );
			});';

		} elseif ( ( true === $usces->is_member_logged_in() && WCUtils::is_blank( $usces->page ) ) || ( true === $usces->is_member_logged_in() && 'member' === $usces->page ) || 'editmemberform' === $usces->page || 'newmemberform' === $usces->page ) {

			$js .= 'var customerstate = "";
			var customercountry = "";
			var deliverystate = "";
			var deliverycountry = "";
			var memberstate = $("#member_pref").get(0).selectedIndex;
			var membercountry = $("#member_country").val();
			$("#member_country").change(function () {
				var country = $("#member_country option:selected").val();
				uscesForm.changeStates( country, "member" );
			});';
		}
		$js .= '})(jQuery);
			</script>';
	}

	echo apply_filters( 'usces_filter_states_form_js', $js );
}

/**
 * Point reduction control
 *
 * @param string $currency Currency code.
 */
function usces_get_pointreduction( $currency ) {
	global $usces, $usces_settings;

	$form = $usces_settings['currency'][ $currency ];
	if ( 2 === (int) $form[1] ) {
		$reduction = 0.01;
	} else {
		$reduction = 1;
	}
	$reduction = apply_filters( 'usces_filter_pointreduction', $reduction );
	return $reduction;
}

/**
 * Admin Item Page.
 * current_screen
 */
function admin_prodauct_current_screen() {
	global $current_screen, $post, $typenow;

	$wp_version = get_bloginfo( 'version' );
	if ( version_compare( $wp_version, '3.4-beta3', '<' ) ) {
		return;
	}

	if ( ! ( isset( $_GET['page'] ) && ( ( 'usces_itemedit' === $_GET['page'] && isset( $_GET['action'] ) ) || 'usces_itemnew' === $_GET['page'] ) ) ) {
		return;
	}

	$typenow = 'product';

	if ( isset( $_GET['post'] ) ) {
		$post_id = $post_ID = (int) $_GET['post'];
	} elseif ( isset( $_POST['post_ID'] ) ) {
		$post_id = $post_ID = (int) $_POST['post_ID'];
	} else {
		$post_id = $post_ID = 0;
	}
	$post_type        = 'post';
	$post_type_object = get_post_type_object( $post_type );

	if ( $post_id ) {
		$product = wel_get_product( $post_id );
		$post    = $product['_pst'];
	} else {
		$post    = get_default_post_to_edit( $post_type, true );
		$post_ID = $post->ID;
	}

	require_once USCES_PLUGIN_DIR . '/includes/meta-boxes.php';

	add_meta_box( 'submitdiv', __( 'Publish' ), 'usces_post_submit_meta_box', $post_type, 'side', 'core' );
	if ( NEW_PRODUCT_IMAGE_REGISTER::$opts['switch_flag'] ) {
		add_meta_box( 'item-main-pict', __( 'Item image', 'usces' ), 'wel_post_item_pict_box_html', $post_type, 'side', 'high' );
	} else {
		add_meta_box( 'item-main-pict', __( 'Item image', 'usces' ), 'post_item_pict_box', $post_type, 'side', 'high' );
	}

	/* all taxonomies */
	foreach ( get_object_taxonomies( $post_type ) as $tax_name ) {
		$taxonomy = get_taxonomy( $tax_name );
		if ( ! $taxonomy->show_ui ) {
			continue;
		}
		$label = $taxonomy->labels->name;

		if ( ! is_taxonomy_hierarchical( $tax_name ) ) {
			add_meta_box( 'tagsdiv-' . $tax_name, $label, 'usces_post_tags_meta_box', $post_type, 'side', 'core' );
		} else {
			add_meta_box( $tax_name . 'div', $label, 'usces_post_categories_meta_box', $post_type, 'side', 'core', array( 'taxonomy' => $tax_name, 'descendants_and_self' => USCES_ITEM_CAT_PARENT_ID ) );
		}
	}

	if ( post_type_supports( $post_type, 'page-attributes' ) ) {
		add_meta_box( 'pageparentdiv', 'page' === $post_type ? __( 'Page Attributes' ) : __( 'Attributes' ), 'usces_page_attributes_meta_box', $post_type, 'side', 'core' );
	}
	if ( current_theme_supports( 'post-thumbnails', $post_type ) && post_type_supports( $post_type, 'thumbnail' ) ) {
		add_meta_box( 'postimagediv', __( 'Featured image', 'usces' ), 'usces_post_thumbnail_meta_box', $post_type, 'side', 'low' );
	}
	if ( post_type_supports( $post_type, 'excerpt' ) ) {
		add_meta_box( 'postexcerpt', __( 'Excerpt' ), 'usces_post_excerpt_meta_box', $post_type, 'normal', 'core' );
	}
	if ( post_type_supports( $post_type, 'trackbacks' ) ) {
		add_meta_box( 'trackbacksdiv', __( 'Send Trackbacks' ), 'usces_post_trackback_meta_box', $post_type, 'normal', 'core' );
	}
	if ( post_type_supports( $post_type, 'custom-fields' ) ) {
		add_meta_box( 'postcustom', __( 'Custom Fields' ), 'usces_post_custom_meta_box', $post_type, 'normal', 'core' );
	}
	if ( post_type_supports( $post_type, 'comments' ) ) {
		add_meta_box( 'commentstatusdiv', __( 'Discussion' ), 'usces_post_comment_status_meta_box', $post_type, 'normal', 'core' );
	}
	if ( ( isset( $post->post_status ) && ( 'publish' === $post->post_status || 'private' === $post->post_status ) ) && post_type_supports( $post_type, 'comments' ) ) {
		add_meta_box( 'commentsdiv', __( 'Comments' ), 'usces_post_comment_meta_box', $post_type, 'normal', 'core' );
	}
	if ( ! ( ( isset( $post->post_status ) && 'pending' === $post->post_status ) && ! current_user_can( $post_type_object->cap->publish_posts ) ) ) {
		add_meta_box( 'slugdiv', __( 'Slug' ), 'usces_post_slug_meta_box', $post_type, 'normal', 'core' );
	}
	if ( post_type_supports( $post_type, 'author' ) ) {
		if ( is_super_admin() || current_user_can( $post_type_object->cap->edit_others_posts ) ) {
			add_meta_box( 'authordiv', __( 'Author' ), 'usces_post_author_meta_box', $post_type, 'normal', 'core' );
		}
	}

	if ( post_type_supports( $post_type, 'revisions' ) && 0 < $post_ID && wp_get_post_revisions( $post_ID ) ) {
		add_meta_box( 'revisionsdiv', __( 'Revisions' ), 'usces_post_revisions_meta_box', $post_type, 'normal', 'core' );
	}

	$current_screen->base      = $post_type;
	$current_screen->id        = $post_type;
	$current_screen->post_type = $post_type;
}

/**
 * Edit Item Page Header.
 * admin_head-welcart-shop_page_usces_itemedit
 */
function admin_prodauct_header() {

	$wp_version = get_bloginfo( 'version' );
	if ( version_compare( $wp_version, '3.4-beta3', '<' ) ) {
		return;
	}
	if ( isset( $_REQUEST['action'] ) && 'edit' === $_REQUEST['action'] ) {

		$suport_display = '<p>' . __( 'Product registration documentation', 'usces' ) . '<br /><a href="http://www.welcart.com/documents/manual-2/%E6%96%B0%E8%A6%8F%E5%95%86%E5%93%81%E8%BF%BD%E5%8A%A0" target="_new">' . __( 'Product editing screen', 'usces' ) . '</a></p>';

		get_current_screen()->add_help_tab(
			array(
				'id'      => 'suport-display',
				'title'   => 'Documents',
				'content' => $suport_display,
			)
		);
	}
}

/**
 * New Item Page Header.
 * admin_head-welcart-shop_page_usces_itemnew
 */
function admin_new_prodauct_header() {

	$wp_version = get_bloginfo( 'version' );
	if ( version_compare( $wp_version, '3.4-beta3', '<' ) ) {
		return;
	}
	$customize_display = '<p>' . __( 'The title field and the big Post Editing Area are fixed in place, but you can reposition all the other boxes using drag and drop, and can minimize or expand them by clicking the title bar of each box. Use the Screen Options tab to unhide more boxes (Excerpt, Send Trackbacks, Custom Fields, Discussion, Slug, Author) or to choose a 1- or 2-column layout for this screen.' ) . '</p>';

	get_current_screen()->add_help_tab(
		array(
			'id'      => 'customize-display',
			'title'   => __( 'Customizing This Display' ),
			'content' => $customize_display,
		)
	);
}

/**
 * Delete member payment information.
 *
 * @param string $id Meta key.
 */
function usces_clear_quickcharge( $id ) {
	global $wpdb;
	$table_name = usces_get_tablename( 'usces_member_meta' );
	$query      = $wpdb->prepare( "DELETE FROM $table_name WHERE meta_key = %s", $id );
	$res        = $wpdb->query( $query );

	return $res;
}

/**
 * Admin notice action.
 *
 * @param string $status Status.
 * @param string $message Message.
 */
function usces_admin_action_status( $status = '', $message = '' ) {
	global $usces;
	if ( empty( $status ) ) {
		$status               = $usces->action_status;
		$usces->action_status = 'none';
	}
	if ( empty( $message ) ) {
		$message               = $usces->action_message;
		$usces->action_message = '';
	}
	$class = '';
	if ( 'success' === $status ) {
		$class = 'updated';
	} elseif ( 'caution' === $status ) {
		$class = 'update-nag';
	} elseif ( 'error' === $status ) {
		$class = 'error';
	}
	if ( '' !== $class ) {
		?>
<div id="usces_admin_status">
	<div id="usces_action_status" class="<?php echo esc_attr( $class ); ?> notice is-dismissible">
		<p><strong><?php wel_esc_script_e( $message ); ?></strong></p><button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'usces' ); ?></span></button>
	</div>
</div>
		<?php
	} else {
		?>
<div id="usces_admin_status"></div>
		<?php
	}
}

/**
 * Admin notice messages.
 */
function usces_get_admin_script_message() {
	$mes_str   = '';
	$message   = array();
	$message[] = __( 'Please enter the option name.', 'usces' ); /* 0 */
	$message[] = __( 'The same option name exists.', 'usces' ); /* 1 */
	$message[] = __( 'Please enter the select value.', 'usces' ); /* 2 */
	$message[] = __( 'Leave the select value blank for text and textarea.', 'usces' ); /* 3 */
	$message[] = __( 'Please enter the SKU code value.', 'usces' ); /* 4 */
	$message[] = __( 'Please enter the sale price value.', 'usces' ); /* 5 */
	$message[] = __( 'Enter the SKU code in single-byte alphanumeric characters (including-_).', 'usces' ); /* 6 */
	$message[] = __( 'Enter the normal price numerically.', 'usces' ); /* 7 */
	$message[] = __( 'Enter the sale price numerically.', 'usces' ); /* 8 */
	$message[] = __( 'Enter the stock numerically.', 'usces' ); /* 9 */
	$message[] = __( 'The same SKU code exists.', 'usces' ); /* 10 */
	$message[] = __( 'Please enter the payment method name value.', 'usces' ); /* 11 */
	$message[] = __( 'Please select the payment type.', 'usces' ); /* 12 */
	$message[] = __( 'Please enter the value for the payment module.', 'usces' ); /* 13 */
	$message[] = __( 'The same payment method name exists.', 'usces' ); /* 14 */
	$message[] = __( 'Chose the %s', 'usces' ); /* 15 */
	$message[] = __( 'Input the %s', 'usces' ); /* 16 */
	$message[] = __( 'Please enter a numeric value.', 'usces' ); /* 17 */
	$message[] = __( 'Delete', 'usces' ); /* 18 */
	$message[] = __( 'Dismiss this notice.', 'usces' ); /* 19 */
	$message[] = __( ' - ', 'usces' ); /* 20 */
	$message[] = __( 'Enter the field key in single-byte alphanumeric characters (including-_).', 'usces' ); /* 21 */
	$message[] = __( 'Please enter the field name.', 'usces' ); /* 22 */
	$message[] = __( 'The same value as the field key exists.', 'usces' ); /* 23 */
	$message[] = __( 'The same value as the field name exists.', 'usces' ); /* 24 */
	$message[] = __( 'Delete SKU (********). Are you sure?', 'usces' ); /* 25 */
	$message   = apply_filters( 'usces_filter_admin_script_message', $message );
	foreach ( (array) $message as $key => $mes ) {
		$mes_str .= "'" . $mes . "',";
	}
	$mes_str = rtrim( $mes_str, ',' );
	return $mes_str;
}

/**
 * Force the payment ID to be displayed in the order list.
 */
function usces_admin_orderlist_show_wc_trans_id() {

	$list_option = get_option( 'usces_orderlist_option', array() );
	$wc_trans_id = ( isset( $list_option['view_column']['wc_trans_id'] ) ) ? $list_option['view_column']['wc_trans_id'] : 0;
	if ( empty( $wc_trans_id ) ) {
		$list_option['view_column']['wc_trans_id'] = 1;
		update_option( 'usces_orderlist_option', $list_option );
	}
}

/**
 * Optional control of mail settings page.
 * admin_print_footer_scripts
 */
function usces_admin_print_footer_scripts() {
	global $usces;

	$admin_page = ( isset( $_GET['page'] ) ) ? wp_unslash( $_GET['page'] ) : '';
	switch ( $admin_page ) :
		case 'usces_mail':
			$email_attach_feature = ( isset( $usces->options['email_attach_feature'] ) ) ? $usces->options['email_attach_feature'] : '0';
			?>
<script type="text/javascript">
jQuery( document ).ready( function( $ ) {
	var email_attach_feature = "<?php echo esc_js( $email_attach_feature ); ?>";
	if ( "1" == email_attach_feature ) {
		$( ".email_attach_feature" ).css( "display", "" );
	} else {
		$( ".email_attach_feature" ).css( "display", "none" );
	}
	$( document ).on( "change", "input[name='email_attach_feature']", function() {
		if ( "1" == $( this ).val() ) {
			$( ".email_attach_feature" ).css( "display", "" );
		} else {
			$( ".email_attach_feature" ).css( "display", "none" );
		}
	});
});
</script>
			<?php
			break;
	endswitch;
}

/**
 * Mail data.
 *
 * @return array
 */
function usces_mail_data() {
	global $usces;

	$options          = get_option( 'usces', array() );
	$mail_data_title  = $options['mail_data']['title'];
	$mail_data_header = $options['mail_data']['header'];
	$mail_data_footer = $options['mail_data']['footer'];
	foreach ( $mail_data_title as $mail => $value ) {
		if ( empty( $value ) && isset( $options['mail_default']['title'][ $mail ] ) ) {
			$mail_data_title[ $mail ] = $options['mail_default']['title'][ $mail ];
		}
	}
	foreach ( $mail_data_header as $mail => $value ) {
		if ( empty( $value ) && isset( $options['mail_default']['header'][ $mail ] ) ) {
			$mail_data_header[ $mail ] = $options['mail_default']['header'][ $mail ];
		}
	}
	foreach ( $mail_data_footer as $mail => $value ) {
		if ( empty( $value ) && isset( $options['mail_default']['footer'][ $mail ] ) ) {
			$mail_data_footer[ $mail ] = $options['mail_default']['footer'][ $mail ];
		}
	}
	$mail_thankyou       = get_option( 'usces_mail_thankyou', array( 'title' => $mail_data_title['thankyou'], 'header' => $mail_data_header['thankyou'], 'footer' => $mail_data_footer['thankyou'] ) );
	$mail_order          = get_option( 'usces_mail_order', array( 'title' => $mail_data_title['order'], 'header' => $mail_data_header['order'], 'footer' => $mail_data_footer['order'] ) );
	$mail_inquiry        = get_option( 'usces_mail_inquiry', array( 'title' => $mail_data_title['inquiry'], 'header' => $mail_data_header['inquiry'], 'footer' => $mail_data_footer['inquiry'] ) );
	$mail_membercomp     = get_option( 'usces_mail_membercomp', array( 'title' => $mail_data_title['membercomp'], 'header' => $mail_data_header['membercomp'], 'footer' => $mail_data_footer['membercomp'] ) );
	$mail_completionmail = get_option( 'usces_mail_completionmail', array( 'title' => $mail_data_title['completionmail'], 'header' => $mail_data_header['completionmail'], 'footer' => $mail_data_footer['completionmail'] ) );
	$mail_ordermail      = get_option( 'usces_mail_ordermail', array( 'title' => $mail_data_title['ordermail'], 'header' => $mail_data_header['ordermail'], 'footer' => $mail_data_footer['ordermail'] ) );
	$mail_changemail     = get_option( 'usces_mail_changemail', array( 'title' => $mail_data_title['changemail'], 'header' => $mail_data_header['changemail'], 'footer' => $mail_data_footer['changemail'] ) );
	$mail_receiptmail    = get_option( 'usces_mail_receiptmail', array( 'title' => $mail_data_title['receiptmail'], 'header' => $mail_data_header['receiptmail'], 'footer' => $mail_data_footer['receiptmail'] ) );
	$mail_mitumorimail   = get_option( 'usces_mail_mitumorimail', array( 'title' => $mail_data_title['mitumorimail'], 'header' => $mail_data_header['mitumorimail'], 'footer' => $mail_data_footer['mitumorimail'] ) );
	$mail_cancelmail     = get_option( 'usces_mail_cancelmail', array( 'title' => $mail_data_title['cancelmail'], 'header' => $mail_data_header['cancelmail'], 'footer' => $mail_data_footer['cancelmail'] ) );
	$mail_othermail      = get_option( 'usces_mail_othermail', array( 'title' => $mail_data_title['othermail'], 'header' => $mail_data_header['othermail'], 'footer' => $mail_data_footer['othermail'] ) );

	$mail_data           = array();
	$mail_data['title']  = array(
		'thankyou'       => $mail_thankyou['title'],
		'order'          => $mail_order['title'],
		'inquiry'        => $mail_inquiry['title'],
		'membercomp'     => $mail_membercomp['title'],
		'completionmail' => $mail_completionmail['title'],
		'ordermail'      => $mail_ordermail['title'],
		'changemail'     => $mail_changemail['title'],
		'receiptmail'    => $mail_receiptmail['title'],
		'mitumorimail'   => $mail_mitumorimail['title'],
		'cancelmail'     => $mail_cancelmail['title'],
		'othermail'      => $mail_othermail['title'],
	);
	$mail_data['header'] = array(
		'thankyou'       => $mail_thankyou['header'],
		'order'          => $mail_order['header'],
		'inquiry'        => $mail_inquiry['header'],
		'membercomp'     => $mail_membercomp['header'],
		'completionmail' => $mail_completionmail['header'],
		'ordermail'      => $mail_ordermail['header'],
		'changemail'     => $mail_changemail['header'],
		'receiptmail'    => $mail_receiptmail['header'],
		'mitumorimail'   => $mail_mitumorimail['header'],
		'cancelmail'     => $mail_cancelmail['header'],
		'othermail'      => $mail_othermail['header'],
	);
	$mail_data['footer'] = array(
		'thankyou'       => $mail_thankyou['footer'],
		'order'          => $mail_order['footer'],
		'inquiry'        => $mail_inquiry['footer'],
		'membercomp'     => $mail_membercomp['footer'],
		'completionmail' => $mail_completionmail['footer'],
		'ordermail'      => $mail_ordermail['footer'],
		'changemail'     => $mail_changemail['footer'],
		'receiptmail'    => $mail_receiptmail['footer'],
		'mitumorimail'   => $mail_mitumorimail['footer'],
		'cancelmail'     => $mail_cancelmail['footer'],
		'othermail'      => $mail_othermail['footer'],
	);
	return apply_filters( 'usces_filter_mail_data', $mail_data, $mail_data_title, $mail_data_header, $mail_data_footer );
}

/**
 * Hide the none item categories in the "Item category" tab of the category post box.
 * Implementation of the "admin_print_footer_scripts" action hook.
 *
 * @return void
 */
function usces_hide_none_item_cat() {
	global $wp_version;
	$current_page        = isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : '';
	$is_new_or_edit_item = in_array( $current_page, array( 'usces_itemnew', 'usces_itemedit' ), true );
	if ( $is_new_or_edit_item ) :
		$item_cats = get_term_children( USCES_ITEM_CAT_PARENT_ID, 'category' );
		if ( version_compare( $wp_version, '6.7', '>=' ) ) {
			$li_category_id     = '[id*="in-category-' . USCES_ITEM_CAT_PARENT_ID . '-"]';
			$li_category_prefix = 'in-category-';
		} else {
			$li_category_id     = '#category-' . USCES_ITEM_CAT_PARENT_ID;
			$li_category_prefix = 'category-';
		}
		$item_cat_checkbox_ids = array( $li_category_id );
		foreach ( $item_cats as $item_cat_id ) {
			if ( version_compare( $wp_version, '6.7', '>=' ) ) {
				$li_category_id = '[id*="in-category-' . $item_cat_id . '-"]';
			} else {
				$li_category_id = '#category-' . $item_cat_id;
			}
			$item_cat_checkbox_ids[] = $li_category_id;
		}
		$item_cat_checkbox_ids_str = implode( ',', $item_cat_checkbox_ids );
		?>
<script>
	(function($) {
		$('#category-all ul#categorychecklist li[id*="<?php echo esc_attr( $li_category_prefix ); ?>"]').not('<?php wel_esc_script_e( $item_cat_checkbox_ids_str ); ?>').hide();
	})(jQuery);
</script>
		<?php
	endif;
}

/**
 * Whether Welcart was installed for the first time.
 *
 * @return boolean
 */
function wel_is_first_install() {
	global $wpdb;

	$target = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM $wpdb->posts 
			WHERE (post_status = %s OR post_status = %s OR post_status = %s OR post_status = %s OR post_status LIKE %s ) 
			AND post_type = %s AND post_mime_type = %s",
			'publish',
			'private',
			'future',
			'pending',
			'%draft%',
			'post',
			'item'
		)
	);

	if ( is_array( $target ) && 0 < count( $target ) ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Get the circulation amount in the last month.
 *
 * @return int
 */
function wel_get_circulating_amount() {
	global $wpdb;

	$current_time    = current_time( 'timestamp' );
	$last_month_time = strtotime( 'first day of last month 00:00:00', $current_time );
	$last_month      = wp_date( 'Y-m-d H:i:s', $last_month_time );
	$this_month_time = strtotime( 'first day of this month 00:00:00', $current_time );
	$this_month      = wp_date( 'Y-m-d H:i:s', $this_month_time );
	$next_month_time = strtotime( 'last day of this month 23:59:59', $this_month_time );
	$next_month      = wp_date( 'Y-m-d H:i:s', $next_month_time );

	$table_name = $wpdb->prefix . 'usces_order';
	$normal_res = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT SUM(
				order_item_total_price + order_shipping_charge + order_cod_fee + order_tax - order_usedpoint + order_discount
			    ) AS total_full_price, COUNT(*) AS count
			FROM {$table_name}
			WHERE order_status NOT LIKE %s
			AND order_status NOT LIKE %s
			AND order_status NOT LIKE %s
			AND order_date >= %s
			AND order_date < %s",
			'%%mitumori%%',
			'%%cancel%%',
			'%%pending%%',
			$last_month,
			$this_month
		),
		ARRAY_A
	);

	$normal = array(
		'total_full_price' => isset( $normal_res['total_full_price'] ) ? (int) $normal_res['total_full_price'] : 0,
		'count'            => isset( $normal_res['count'] ) ? (int) $normal_res['count'] : 0,
	);

	$continue = array(
		'total_con_price' => 0,
		'count'           => 0,
	);
	if ( defined( 'WCEX_DLSELLER' ) ) {
		$access_table = $wpdb->prefix . 'usces_continuation';
		if ( $access_table !== $wpdb->get_var( "SHOW TABLES LIKE '$access_table'" ) ) {
			$conres = array(
				'total_con_price' => 0,
				'count'           => 0,
			);
		} else {
			$this_month = substr( $this_month, 0, 10 );
			$next_month = substr( $next_month, 0, 10 );
			$conres     = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT SUM( con_price ) AS total_con_price, COUNT(*) AS count FROM {$access_table}
					WHERE con_status = %s
					AND con_next_charging >= %s AND con_next_charging <= %s",
					'continuation',
					$this_month,
					$next_month
				),
				ARRAY_A
			);
		}

		$continue = array(
			'total_con_price' => isset( $conres['total_con_price'] ) ? (int) $conres['total_con_price'] : 0,
			'count'           => isset( $conres['count'] ) ? (int) $conres['count'] : 0,
		);
	}
	$result = array(
		'amount' => (int) $normal['total_full_price'] + (int) $continue['total_con_price'],
		'count'  => (int) $normal['count'] + (int) $continue['count'],
	);
	return $result;
}

/**
 * Get item categories.
 *
 * @return string
 */
function wel_get_categories() {

	$parent_category_slug = 'itemgenre';
	$parent_category      = get_category_by_slug( $parent_category_slug );

	if ( $parent_category ) {
		$categories = get_categories(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => 0,
				'parent'     => $parent_category->term_id,
			)
		);
	}

	if ( empty( $categories ) ) {
		$fallback_category_slug = 'item';
		$fallback_category      = get_category_by_slug( $fallback_category_slug );

		if ( $fallback_category ) {
			$categories = get_categories(
				array(
					'taxonomy'   => 'category',
					'hide_empty' => 0,
					'parent'     => $fallback_category->term_id,
				)
			);
		}
	}

	$result = '';
	if ( ! empty( $categories ) ) {
		foreach ( $categories as $index => $category ) {
			if ( 10 > $index ) {
				$result .= $category->name . ':';
			} else {
				break;
			}
		}
		$result = trim( $result, ':' );
	}

	return $result;
}

/**
 * Get total number of item categories.
 *
 * @return int
 */
function wel_get_cat_total_num() {

	$item_category = get_category_by_slug( 'item' );
	if ( $item_category ) {
		$args = array(
			'child_of'   => $item_category->term_id,
			'taxonomy'   => 'category',
			'hide_empty' => false,
		);

		$subcategories     = get_categories( $args );
		$subcategory_count = count( $subcategories );

	} else {
		$subcategory_count = 0;
	}

	return (int) $subcategory_count;
}

/**
 * Get total number of SKU.
 *
 * @return int
 */
function wel_get_sku_total_num() {
	global $wpdb;

	$status    = array();
	$sku_table = $wpdb->prefix . 'usces_skus';

	$data = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) AS sku_num FROM {$wpdb->posts} LEFT JOIN {$sku_table} ON ID = post_id
			WHERE post_mime_type = %s AND post_type = %s AND post_status <> %s",
			'item',
			'post',
			'trash'
		)
	);

	if ( $data ) {
		$res = (int) $data;
	} else {
		$res = 0;
	}
	return $res;
}

/**
 * Get active theme.
 *
 * @return string
 */
function wel_get_themes() {
	$result = '';

	$active_theme = wp_get_theme();
	$result       = $active_theme->get( 'Name' );

	if ( $active_theme->parent() ) {
		$parent_theme = $active_theme->parent();
		$result      .= ' : ' . $parent_theme->get( 'Name' );
	}

	return $result;
}

/**
 * Get active plugins.
 *
 * @return string
 */
function wel_get_plugins() {
	$result = '';

	$all_plugins    = get_plugins();
	$active_plugins = get_option( 'active_plugins', array() );

	foreach ( $active_plugins as $plugin_path ) {
		if ( strpos( $plugin_path, 'wcex' ) === 0 && isset( $all_plugins[ $plugin_path ] ) ) {
			$plugin  = $all_plugins[ $plugin_path ];
			$result .= dirname( $plugin_path ) . '(' . $plugin['Version'] . '):';
		}
	}
	$result = trim( $result, ':' );
	return $result;
}

/**
 * Get active payment methods.
 *
 * @return string
 */
function wel_get_activ_pay_methd() {
	global $usces;

	$result         = '';
	$payment_method = get_option( 'usces_payment_method', array() );

	foreach ( $payment_method as $method ) {
		if ( empty( $method['use'] ) || 'activate' !== $method['use'] ) {
			continue;
		}
		$payname = str_replace( 'acting', '', $method['settlement'] );
		$payname = ltrim( $payname, '_' );
		if ( empty( $payname ) ) {
			$payname = $method['module'];
		}
		$result .= $payname . ':';
	}
	$result = trim( $result, ':' );
	return $result;
}

/**
 * Implementation of hook "init".
 *  - Activate Paidy payment.
 *
 * @see includes\default_filters.php
 */
function usces_paidyapi() {
	$allowed_ip    = '52.68.140.181';
	$request_ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$is_allowed_ip = $allowed_ip === $request_ip;
	if ( $is_allowed_ip ) {
		$method         = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		$is_post_method = 'POST' === $method;
		if ( $is_post_method ) {
			$is_paidy_result = filter_input( INPUT_POST, 'paidy_result' );
			if ( $is_paidy_result ) {
				InstantPaidyDashboard::get_instance()->receive_paidy_result();
			}
		}
	}
}
