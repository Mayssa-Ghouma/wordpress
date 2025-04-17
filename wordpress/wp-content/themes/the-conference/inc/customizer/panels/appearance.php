<?php
/**
 * Appearance Settings
 *
 * @package The Conference
 */

function the_conference_customize_register_appearance( $wp_customize ) {
    
    $wp_customize->add_panel( 
        'appearance_settings', 
        array(
            'title'       => __( 'Appearance Settings', 'the-conference' ),
            'priority'    => 25,
            'capability'  => 'edit_theme_options',
            'description' => __( 'Change color and body background.', 'the-conference' ),
        ) 
    );

    /** Typography */
    $wp_customize->add_section(
        'typography_settings',
        array(
            'title'    => __( 'Typography', 'the-conference' ),
            'priority' => 50,
            'panel'    => 'appearance_settings',
        )
    );
    
    $wp_customize->add_setting(
        'ed_localgoogle_fonts',
        array(
            'default'           => false,
            'sanitize_callback' => 'the_conference_sanitize_checkbox',
        )
    );
    
    $wp_customize->add_control(
        'ed_localgoogle_fonts',
        array(
            'label'   => __( 'Load Google Fonts Locally', 'the-conference' ),
            'section' => 'typography_settings',
            'type'    => 'checkbox',
        )
    );

    $wp_customize->add_setting(
        'ed_preload_local_fonts',
        array(
            'default'           => false,
            'sanitize_callback' => 'the_conference_sanitize_checkbox',
        )
    );
    
    $wp_customize->add_control(
        'ed_preload_local_fonts',
        array(
            'label'           => __( 'Preload Local Fonts', 'the-conference' ),
            'section'         => 'typography_settings',
            'type'            => 'checkbox',
            'active_callback' => 'the_conference_flush_fonts_callback'
        )
    );
    

    $wp_customize->add_setting(
        'flush_google_fonts',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses',
        )
    );

    $wp_customize->add_control(
        'flush_google_fonts',
        array(
            'label'       => __( 'Flush Local Fonts Cache', 'the-conference' ),
            'description' => __( 'Click the button to reset the local fonts cache.', 'the-conference' ),
            'type'        => 'button',
            'settings'    => array(),
            'section'     => 'typography_settings',
            'input_attrs' => array(
                'value' => __( 'Flush Local Fonts Cache', 'the-conference' ),
                'class' => 'button button-primary flush-it',
            ),
            'active_callback' => 'the_conference_flush_fonts_callback'
        )
    );
    /** Move Background Image section to appearance panel */
    $wp_customize->get_section( 'colors' )->panel                          = 'appearance_settings';
    $wp_customize->get_section( 'colors' )->priority                       = 30;
    $wp_customize->get_section( 'background_image' )->panel                = 'appearance_settings';
    $wp_customize->get_section( 'background_image' )->priority             = 35;
    $wp_customize->get_section( 'background_image' )->title                = __( 'Background', 'the-conference' );
    $wp_customize->get_control( 'background_color' )->description          = __( 'Background color of the theme.', 'the-conference' );
    
    /** Color Settings */
    /** Note */
    $wp_customize->add_setting(
        'color_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );
    
    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'color_text',
            array(
                'section'     => 'colors',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );

   
    $wp_customize->add_setting( 
        'color_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio'
        ) 
    );
    
    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'color_settings',
            array(
                'section'     => 'colors',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/color-settings.png',
                ),
            )
        )
    );
    /** Color Settings End */

    /** Typography */

     /** Note */
     $wp_customize->add_setting(
        'typography_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );
    
    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'typography_text',
            array(
                'section'     => 'typography_settings',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );
   
    $wp_customize->add_setting( 
        'typpography_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio'
        ) 
    );
    
    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'typpography_settings',
            array(
                'section'     => 'typography_settings',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/typography.png',
                ),
            )
        )
    );
    /** Typography End */
}
add_action( 'customize_register', 'the_conference_customize_register_appearance' );

function the_conference_flush_fonts_callback( $control ){
    $ed_localgoogle_fonts   = $control->manager->get_setting( 'ed_localgoogle_fonts' )->value();
    $control_id   = $control->id;
    
    if ( $control_id == 'flush_google_fonts' && $ed_localgoogle_fonts ) return true;
    if ( $control_id == 'ed_preload_local_fonts' && $ed_localgoogle_fonts ) return true;
    return false;
}