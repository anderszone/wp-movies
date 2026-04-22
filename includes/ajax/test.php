<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_test_action', 'wp_movies_ajax_test');
add_action('wp_ajax_nopriv_test_action', 'wp_movies_ajax_test');

function wp_movies_ajax_test() {

    // SECURITY
    check_ajax_referer('wp_movies_refresh_movies_nonce', 'nonce');

    // OPTIONAL CAPABILITY CHECK
    if (!current_user_can('read')) {
        wp_send_json_error([
            'message' => 'Unauthorized'
        ], 403);
    }

    // DEV / PROD GUARD
    if (WP_MOVIES_ENV === 'prod') {
        wp_send_json_error([
            'message' => 'Disabled in production'
        ], 403);
    }

    // LOGGING
    wp_movies_log('AJAX test triggered', 'INFO', 'test');

    // RESPONSE
    wp_send_json_success([
        'message' => 'AJAX works',
    ]);
}
