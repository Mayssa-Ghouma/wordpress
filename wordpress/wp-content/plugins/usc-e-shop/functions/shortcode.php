<?php
/**
 * Shortcode.
 *
 * @package Welcart
 */

/**
 * Direct into cart.
 *
 * @param array $atts Attributes.
 * @return usces_direct_intoCart
 */
function sc_direct_intoCart( $atts ) {
	global $usces;
	extract(
		shortcode_atts(
			array(
				'item'    => '',
				'sku'     => '',
				'value'   => null,
				'options' => null,
			),
			$atts
		)
	);

	if ( WCUtils::is_blank( $item ) || WCUtils::is_blank( $sku ) ) {
		return '';
	}
	$post_id = $usces->get_ID_byItemName( $item );

	return usces_direct_intoCart( $post_id, $sku, true, $value, $options, 'return' );
}
