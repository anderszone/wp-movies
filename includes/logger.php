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
