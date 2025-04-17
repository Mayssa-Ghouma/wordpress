<?php
/**
 * Pro Sections
 *
 * @package The Conference
 */

function the_conference_customize_register_frontpage_pro_sections( $wp_customize ){

    /** Schedule Section */
    $wp_customize->add_section(
        'schedule_settings',
        array(
            'title'    => __( 'Schedule Section', 'the-conference' ),
            'priority' => 36,
            'panel'    => 'frontpage_settings',
        )
    );

    /** Note */
    $wp_customize->add_setting(
        'schedule_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );

    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'schedule_text',
            array(
                'section'     => 'schedule_settings',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );

    $wp_customize->add_setting( 
        'schedule_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio',
            'transport'         => 'postMessage'
        ) 
    );

    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'schedule_settings',
            array(
                'section'     => 'schedule_settings',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/schedule-section-view.png',
                    'two'       => get_template_directory_uri() . '/images/pro/schedule-section.png',
                ),
            )
        )
    );

    /** Schedule Section Ends*/

    /** Ticket Section */
    $wp_customize->add_section(
        'ticket_settings',
        array(
            'title'    => __( 'Ticket Section', 'the-conference' ),
            'priority' => 36,
            'panel'    => 'frontpage_settings',
        )
    );

    /** Note */
    $wp_customize->add_setting(
        'ticket_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );

    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'ticket_text',
            array(
                'section'     => 'ticket_settings',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );

    $wp_customize->add_setting( 
        'ticket_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio',
            'transport'         => 'postMessage'
        ) 
    );

    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'ticket_settings',
            array(
                'section'     => 'ticket_settings',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/ticket-section-view.png',
                    'two'       => get_template_directory_uri() . '/images/pro/ticket-section.png',
                ),
            )
        )
    );

    /** Ticket Section Ends*/

    /** Timer Section */
    $wp_customize->add_section(
        'timer_settings',
        array(
            'title'    => __( 'Timer Section', 'the-conference' ),
            'priority' => 51,
            'panel'    => 'frontpage_settings',
        )
    );

    /** Note */
    $wp_customize->add_setting(
        'timer_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );

    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'timer_text',
            array(
                'section'     => 'timer_settings',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );

    $wp_customize->add_setting( 
        'timer_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio',
            'transport'         => 'postMessage'
        ) 
    );

    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'timer_settings',
            array(
                'section'     => 'timer_settings',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/timer-section-view.png',
                    'two'       => get_template_directory_uri() . '/images/pro/timer-section.png',
                ),
            )
        )
    );

    /** Timer Section Ends*/

    /** Sponsor Section */
    $wp_customize->add_section(
        'sponsor_settings',
        array(
            'title'    => __( 'Sponsor Section', 'the-conference' ),
            'priority' => 51,
            'panel'    => 'frontpage_settings',
        )
    );

    /** Note */
    $wp_customize->add_setting(
        'sponsor_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );

    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'sponsor_text',
            array(
                'section'     => 'sponsor_settings',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );

    $wp_customize->add_setting( 
        'sponsor_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio',
            'transport'         => 'postMessage'
        ) 
    );

    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'sponsor_settings',
            array(
                'section'     => 'sponsor_settings',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/sponsor-section-view.png',
                    'two'       => get_template_directory_uri() . '/images/pro/sponsor-section.png',
                ),
            )
        )
    );

    /** Sponsor Section Ends*/

    /** FAQ Seciton */
    $wp_customize->add_section(
        'faq_settings',
        array(
            'title'    => __( 'FAQ Seciton', 'the-conference' ),
            'priority' => 66,
            'panel'    => 'frontpage_settings',
        )
    );

    /** Note */
    $wp_customize->add_setting(
        'faq_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );

    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'faq_text',
            array(
                'section'     => 'faq_settings',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );

    $wp_customize->add_setting( 
        'faq_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio',
        ) 
    );

    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'faq_settings',
            array(
                'section'     => 'faq_settings',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/faq-section.png',
                ),
            )
        )
    );

    /** FAQ Seciton Ends*/

    /** Gallery Section */
    $wp_customize->add_section(
        'gallery_settings',
        array(
            'title'    => __( 'Gallery Section', 'the-conference' ),
            'priority' => 66,
            'panel'    => 'frontpage_settings',
        )
    );

    /** Note */
    $wp_customize->add_setting(
        'gallery_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );

    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'gallery_text',
            array(
                'section'     => 'gallery_settings',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );

    $wp_customize->add_setting( 
        'gallery_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio',
        ) 
    );

    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'gallery_settings',
            array(
                'section'     => 'gallery_settings',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/gallery-section.png',
                ),
            )
        )
    );

    /** Gallery Section Ends*/

    /** One Page Settings */
    $wp_customize->add_section(
        'onepage_settings',
        array(
            'title'    => __( 'One Page Settings', 'the-conference' ),
            'priority' => 86,
            'panel'    => 'frontpage_settings',
        )
    );

    /** Note */
    $wp_customize->add_setting(
        'onepage_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );

    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'onepage_text',
            array(
                'section'     => 'onepage_settings',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );

    $wp_customize->add_setting( 
        'onepage_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio',
        ) 
    );

    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'onepage_settings',
            array(
                'section'     => 'onepage_settings',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/one-page-section.png',
                ),
            )
        )
    );

    /** One Page Settings Ends*/

    /** Sort Front Page Section */
    $wp_customize->add_section(
        'sortpage_settings',
        array(
            'title'    => __( 'Sort Front Page Section', 'the-conference' ),
            'priority' => 86,
            'panel'    => 'frontpage_settings',
        )
    );

    /** Note */
    $wp_customize->add_setting(
        'sortpage_text',
        array(
            'default'           => '',
            'sanitize_callback' => 'wp_kses_post' 
        )
    );

    $wp_customize->add_control(
        new The_Conference_Note_Control( 
            $wp_customize,
            'sortpage_text',
            array(
                'section'     => 'sortpage_settings',
                'description' => sprintf( __( '%1$sThis feature is available in Pro version.%2$s %3$sUpgrade to Pro%4$s ', 'the-conference' ),'<div class="featured-pro"><span>', '</span>', '<a href="https://rarathemes.com/wordpress-themes/the-conference-pro/?utm_source=the_conference&utm_medium=customizer&utm_campaign=upgrade_to_pro" target="_blank">', '</a></div>' ),
            )
        )
    );

    $wp_customize->add_setting( 
        'sortpage_settings', 
        array(
            'default'           => 'one',
            'sanitize_callback' => 'the_conference_sanitize_radio',
        ) 
    );

    $wp_customize->add_control(
        new The_Conference_Radio_Image_Control(
            $wp_customize,
            'sortpage_settings',
            array(
                'section'     => 'sortpage_settings',
                'feat_class' => 'upg-to-pro',
                'choices'     => array(
                    'one'       => get_template_directory_uri() . '/images/pro/sort-section.png',
                ),
            )
        )
    );

    /** Sort Front Page Section Ends*/

}
add_action( 'customize_register', 'the_conference_customize_register_frontpage_pro_sections' );