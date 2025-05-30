<?php
/**
 * The Conference Theme Info
 *
 * @package The Conference
 */

if ( ! function_exists( 'the_conference_customizer_theme_info' ) ) :
	/**
     * Add theme info
     */
	function the_conference_customizer_theme_info( $wp_customize ) {
		
	    $wp_customize->add_section( 'theme_info_section', array(
			'title'       => __( 'Video Tutorial & Documentation' , 'the-conference' ),
			'priority'    => 6,
		) );
	    
	    /** Important Links */
		$wp_customize->add_setting( 'theme_info_setting',
	        array(
	            'default' => '',
	            'sanitize_callback' => 'wp_kses_post',
	        )
	    );
	    
	    $theme_info = '<p>';

	    /* translators: 1: string, 2: documentation url, 3: string */
	    $theme_info .= sprintf( '%1$s<a href="%2$s" target="_blank">%3$s</a>', esc_html__( 'Video Tutorials : ', 'the-conference' ), esc_url( 'https://www.youtube.com/watch?v=P4vPXnakElk&list=PL8D5hAypnzVgRJf3KwOYX_uN1zmJsSQzj' ), esc_html__( 'Click here.', 'the-conference' ) );

	    $theme_info .= '</p><p>';

	    /* translators: 1: string, 2: documentation url, 3: string */
	    $theme_info .= sprintf( '%1$s<a href="%2$s" target="_blank">%3$s</a>', esc_html__( 'Documentation Link : ', 'the-conference' ), esc_url( 'https://docs.rarathemes.com/docs/the-conference/?utm_source=the_conference&utm_medium=customizer&utm_campaign=docs' ), esc_html__( 'Click here.', 'the-conference' ) );
	    $theme_info .= '</p>';

		$wp_customize->add_control( new The_Conference_Note_Control( $wp_customize,
	        'theme_info_setting', 
	            array(
	                'section'     => 'theme_info_section',
	                'description' => $theme_info
	            )
	        )
	    );

	}
endif;
add_action( 'customize_register', 'the_conference_customizer_theme_info' );

if( class_exists( 'WP_Customize_Section' ) ) :
/**
 * Adding Go to Pro Section in Customizer
 * https://github.com/justintadlock/trt-customizer-pro
 */
class The_Conference_Customize_Section_Pro extends WP_Customize_Section {

	/**
	 * The type of customize section being rendered.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $type = 'pro-section';

	/**
	 * Custom button text to output.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $pro_text = '';

	/**
	 * Custom pro button URL.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	public $pro_url = '';

	/**
	 * Add custom parameters to pass to the JS via JSON.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function json() {
		$json = parent::json();

		$json['pro_text'] = $this->pro_text;
		$json['pro_url']  = esc_url( $this->pro_url );

		return $json;
	}

	/**
	 * Outputs the Underscore.js template.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	protected function render_template() { ?>
		<li id="accordion-section-{{ data.id }}" class="accordion-section control-section control-section-{{ data.type }} cannot-expand">
			<h3 class="accordion-section-title">
				{{ data.title }}
				<# if ( data.pro_text && data.pro_url ) { #>
					<a href="{{{ data.pro_url }}}" class="button button-secondary alignright" target="_blank">{{ data.pro_text }}</a>
				<# } #>
			</h3>
		</li>
	<?php }
}
endif;

add_action( 'customize_register', 'the_conference_page_sections_pro' );

function the_conference_page_sections_pro( $manager ) {
	// Register custom section types.
	$manager->register_section_type( 'The_Conference_Customize_Section_Pro' );

	// Register sections.
	$manager->add_section(
		new The_Conference_Customize_Section_Pro(
			$manager,
			'the_conference_view_pro',
			array(
				'title'    => esc_html__( 'Pro Available', 'the-conference' ),
                'priority' => 5, 
				'pro_text' => esc_html__( 'VIEW PRO THEME', 'the-conference' ),
				'pro_url'  => 'https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro'
			)
		)
	);
}