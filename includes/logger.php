<?php
if (!defined('ABSPATH')) exit;

// ==========================
// SIMPLE DEBUG LOGGER
// ==========================
function wp_movies_log($message, $level = 'info') {
    // Only log in local environment
    if (!defined('WP_ENVIRONMENT_TYPE') || constant('WP_ENVIRONMENT_TYPE') !== 'local') {
        return;
    }
    // Only log warnings and errors
    if ($level === 'error' || $level === 'warning') {
        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }
    }
}

// ==========================
// LOG ONCE PER TMDB-ID
// ==========================
function wp_movies_log_once($tmdb_id, $message, $level = 'warning') {
    if (empty($tmdb_id)) {
        // Fallback: log normally if no ID provided
        wp_movies_log($message, $level);
        return;
    }

    // Load previously logged IDs
    $logged = get_transient('wp_movies_logged_ids') ?: [];

    if (isset($logged[$tmdb_id])) {
        // Already logged this ID
        return;
    }
    // Log the message
    wp_movies_log($message, $level);

    // Mark this ID as logged
    $logged[$tmdb_id] = true;
    set_transient('wp_movies_logged_ids', $logged, HOUR_IN_SECONDS);
}
