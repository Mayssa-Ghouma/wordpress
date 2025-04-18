<?php
/**
 * Verified page template
 *
 * @package Welcart
 */

$member_compmode = $this->page;

$html = '<div id="memberpages">

<div class="post">';

$html  .= '<div class="header_explanation">';
$header = '';
$html  .= apply_filters( 'usces_filter_memberverified_page_header', $header );
$html  .= '</div>';

$html .= '<h2>' . esc_html__( 'Member registration complete', 'usces' ) . '</h2>';
$html .= '<p>' . esc_html__( 'Member registration is complete. Click "Continue shopping" to continue shopping.', 'usces' ) . '</p>';

$html  .= '<div class="footer_explanation">';
$footer = '';
$html  .= apply_filters( 'usces_filter_memberverified_page_footer', $footer );
$html  .= '</div>';

$redirect_to = add_query_arg(
	array(
		'usces_page' => 'delivery',
	),
	USCES_CART_URL
);
$continue_to = add_query_arg(
	array(
		'redirect_to' => urlencode( $redirect_to ),
	),
	USCES_LOGIN_URL
);

$html .= '<div><a class="welcart-btn orange" href="' . $continue_to . '" />' . esc_html__( 'Continue shopping', 'usces' ) . '</a></div>&nbsp;&nbsp;';
$html .= '<div class="send"><a href="' . home_url() . '" class="back_to_top_button">' . esc_html__( 'Back to the top page.', 'usces' ) . '</a></div>';
$html .= '</div>

</div>';
