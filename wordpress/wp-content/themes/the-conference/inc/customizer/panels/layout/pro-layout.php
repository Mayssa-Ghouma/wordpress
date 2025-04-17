<?php
/**
 * Pro Layout Settings
 *
 * @package The Conference
 */

function the_conference_customize_register_pro_layout( $wp_customize ) {
    
    /** Header Layout */
    $wp_customize->add_section(
        'header_layout_settings',
        array(
            'title'    => __( 'Header Layout', 'the-conference' ),
            'panel'    => 'layout_settings',
            'priority' => 30
        )
    );
    
    /** Note */
    $wp_customize->add_setting(
        'header_layout_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );

    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'header_layout_text',
            array(
                'section'     => 'header_layout_settings',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );

    $wp_customize->add_setting( 
        'header_layout_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio',
            'transport'         => 'postMessage'
        ) 
    );

    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'header_layout_settings',
            array(
                'section'     => 'header_layout_settings',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/header-layout.png',
                ),
            )
        )
    );

    /** Header Layout Ends*/

    /** Pagination Settings */
    $wp_customize->add_section(
        'pagination_settings',
        array(
            'title'    => __( 'Pagination Settings', 'the-conference' ),
            'panel'    => 'layout_settings',
        )
    );
    
    /** Note */
    $wp_customize->add_setting(
        'pagination_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );

    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'pagination_text',
            array(
                'section'     => 'pagination_settings',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );

    $wp_customize->add_setting( 
        'pagination_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio',
            'transport'         => 'postMessage'
        ) 
    );

    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'pagination_settings',
            array(
                'section'     => 'pagination_settings',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/pagination.png',
                ),
            )
        )
    );

    /** Pagination Settings Ends*/
    
}
add_action( 'customize_register', 'the_conference_customize_register_pro_layout' );