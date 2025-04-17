<?php
/**
 * Footer Setting
 *
 * @package The Conference
 */

function the_conference_customize_register_footer( $wp_customize ) {
    
    $wp_customize->add_section(
        'footer_settings',
        array(
            'title'      => __( 'Footer Settings', 'the-conference' ),
            'priority'   => 150,
            'capability' => 'edit_theme_options',
        )
    );
    
    /** Footer Copyright */
    $wp_customize->add_setting(
        'footer_copyright',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post',
            'transport'         => 'postMessage'
        )
    );
    
    $wp_customize->add_control(
        'footer_copyright',
        array(
            'label'       => __( 'Footer Copyright Text', 'the-conference' ),
            'section'     => 'footer_settings',
            'type'        => 'textarea',
        )
    );
    
    $wp_customize->selective_refresh->add_partial( 'footer_copyright', array(
        'selector' => '.site-info .copyright',
        'render_callback' => 'the_conference_get_footer_copyright',
    ) );
    
    /** Footer Settings */

     /** Note */
     $wp_customize->add_setting(
        'pro_footer_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );
    
    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'pro_footer_text',
            array(
                'section'     => 'footer_settings',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );
   
    $wp_customize->add_setting( 
        'pro_footer_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio'
        ) 
    );
    
    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'pro_footer_settings',
            array(
                'section'     => 'footer_settings',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/footer.png',
                ),
            )
        )
    );
    /** Footer Settings End */
}
add_action( 'customize_register', 'the_conference_customize_register_footer' );