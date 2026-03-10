<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Security: prevent direct access
}

// Only run if this is an AJAX request
if ( defined('DOING_AJAX') && DOING_AJAX ) {

    /**
     * Validate admin user capability and nonce for AJAX requests.
     *
     * @param string $nonce_name The expected nonce action name
     */
    function wp_movies_ajax_validate( $nonce_name ) {
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Unauthorized', 403);
        }
        if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], $nonce_name) ) {
            wp_send_json_error('Nonce verification failed', 403);
        }
    }

    // ==============================
    // AJAX: Refresh Movies
    // ==============================
    add_action('wp_ajax_refresh_movies', function() {
        wp_movies_ajax_validate('refresh_movies_nonce');

        if ( ! function_exists('wp_movies_get_from_db') ) {
            wp_send_json_error('Function not available', 500);
        }

        $movies = wp_movies_get_from_db('movie', 8, true);
        wp_send_json_success(['movies' => $movies]);
    });

    // ==============================
    // AJAX: Refresh TV Shows
    // ==============================
    add_action('wp_ajax_refresh_tvshows', function() {
        wp_movies_ajax_validate('refresh_tvshows_nonce');

        if ( ! function_exists('wp_movies_get_from_db') ) {
            wp_send_json_error('Function not available', 500);
        }

        $tvshows = wp_movies_get_from_db('tv', 8, true);
        wp_send_json_success(['tvshows' => $tvshows]);
    });

}
