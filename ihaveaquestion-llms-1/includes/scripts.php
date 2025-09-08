<?php

if (!defined('ABSPATH')) {
    exit;
}

function wp_cep_enqueue_assets() {
    // Get logged-in user details
    $current_user = wp_get_current_user();
    $user_data = [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'first_name' => is_user_logged_in() ? esc_html(get_user_meta($current_user->ID, 'first_name', true)) : '',
        'last_name' => is_user_logged_in() ? esc_html(get_user_meta($current_user->ID, 'last_name', true)) : '',
        'email' => is_user_logged_in() ? esc_html($current_user->user_email) : '',
    ];

    // Enqueue JavaScript
    wp_enqueue_script('wp-cep-popup', plugins_url('../assets/js/popup.js', __FILE__), ['jquery'], null, true);
    
    // Enqueue CSS
    wp_enqueue_style('wp-cep-styles', plugins_url('../assets/css/styles.css', __FILE__));

    // Localize script with user data
    wp_localize_script('wp-cep-popup', 'wp_cep_ajax', $user_data);
}

add_action('wp_enqueue_scripts', 'wp_cep_enqueue_assets');
