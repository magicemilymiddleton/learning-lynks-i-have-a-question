<?php

/**
 * Plugin Name: I have a question! A Quiz Email Plugin for LifterLMS
 * Description: This plugin adds functionality to LifterLMS Quizzes supports fill in the blank and multi-choice questions to send an email to an instructor if a student is confused per question
 * Version: 1.5
 * Author: Emily with LearningLynks
 */


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin path
define('WP_CEP_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Include necessary files
require_once WP_CEP_PLUGIN_DIR . 'includes/email-handler.php';
require_once WP_CEP_PLUGIN_DIR . 'includes/scripts.php';

// Activation hook (optional)
function wp_cep_activate() {
    // You can add activation logic here
}
register_activation_hook(__FILE__, 'wp_cep_activate');

// Deactivation hook (optional)
function wp_cep_deactivate() {
    // Cleanup if needed
}
register_deactivation_hook(__FILE__, 'wp_cep_deactivate');