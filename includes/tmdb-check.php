<?php
if (!defined('ABSPATH')) exit;

// ==========================
// TMDB API KEY CHECK
// ==========================
if (!defined('TMDB_API_KEY') || !constant('TMDB_API_KEY')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>WP Movies:</strong> TMDB_API_KEY is missing or empty in wp-config.php.</p></div>';
    });
    return; // Stop plugin execution
}
