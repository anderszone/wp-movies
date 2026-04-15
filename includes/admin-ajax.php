<?php
if (!defined('ABSPATH')) exit;

/**
 * Update movies from TMDB.
 *
 * @return array Returns an array of updated movies.
 */
function update_movies_from_tmdb() {
    return true; // stub/real implementation
}

/**
 * Sync missing genres from TMDB.
 *
 * @return bool Returns true on success.
 */
function sync_missing_genres() {
    return true; // stub/real implementation
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
    // AJAX: Update Now from TMDB
    // ==============================
    add_action('wp_ajax_wp_movies_update_tmdb', function() {
        wp_movies_ajax_validate('wp_movies_update_tmdb_nonce');

        error_log(PHP_EOL); // <-- blank line

        error_log('[WP-MOVIES][DEBUG] AJAX: update_tmdb triggered');

        $result = update_movies_from_tmdb();

        error_log('[WP-MOVIES][DEBUG] TMDB Update result: ' . print_r($result, true));

        wp_send_json_success(['result' => $result]);
    });

    // ==============================
    // AJAX: Sync Missing Genres from TMDB
    // ==============================
    add_action('wp_ajax_wp_movies_sync_genres', function() {
        wp_movies_ajax_validate('wp_movies_sync_genres_nonce');

        error_log('[WP-MOVIES][DEBUG] AJAX: sync_genres triggered');

        $result = sync_missing_genres();

        error_log('[WP-MOVIES][DEBUG] Sync Genres result: ' . print_r($result, true));

        wp_send_json_success(['result' => $result]);
    });

    // ==============================
    // AJAX: Refresh Movies
    // ==============================
    add_action('wp_ajax_wp_movies_refresh_movies', function() {
        wp_movies_ajax_validate('wp_movies_refresh_movies_nonce');

        $movies = wp_movies_get_from_db('movie', 8, true);
        $titles = array_map(fn($m) => $m->title ?? '(no title)', $movies);

        error_log('[WP-MOVIES][DEBUG] Refresh Movies: ' . implode(', ', $titles));

        wp_send_json_success(['movies' => $movies]);
    });

    // ==============================
    // AJAX: Refresh TV Shows
    // ==============================
    add_action('wp_ajax_wp_movies_refresh_tvshows', function() {
        wp_movies_ajax_validate('wp_movies_refresh_tvshows_nonce');

        $tvshows = wp_movies_get_from_db('tv', 8, true);
        $titles = array_map(fn($t) => $t->title ?? '(no title)', $tvshows);

        error_log('[WP-MOVIES][DEBUG] Refresh TV Shows: ' . implode(', ', $titles));

        wp_send_json_success(['tvshows' => $tvshows]);
    });
}

// ==========================
// ADMIN AJAX DEBUG (safe)
// ==========================
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
    error_log( '[WP-MOVIES] ADMIN-AJAX loaded: ' . __FILE__ );
}
