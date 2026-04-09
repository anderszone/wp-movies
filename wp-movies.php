<?php
/**
 * Plugin Name: WP Movies
 * Description: Fetches and manages TMDB movies and TV shows in WordPress.
 * Version: 1.2
 * Author: Anders Johansson
 */

if (!defined('ABSPATH')) exit;

// Plugin path and URL constants
define('WP_MOVIES_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WP_MOVIES_PLUGIN_URL', plugin_dir_url(__FILE__));

// Full list of plugin modules to load (necessary for plugin functionality)
$all_modules = [
    'logger', 'constants', 'tmdb-check', 'admin', 'admin-ajax',
    'assets', 'menu-icons', 'contact', 'cpt', 'database', 'session', 'admin-scripts'
];

// List of modules to log
$log_modules = [
    'logger',        // Logging functions
    'constants',     // Constants
    'tmdb-check',    // TMDB API fetch
    'admin',         // Admin page logic
    'admin-ajax',    // AJAX handlers
    'assets',
    'menu-icons',
    'admin-scripts'  // JS enqueue / admin assets
];

$GLOBALS['wp_movies_log_modules'] = $log_modules;

foreach ($all_modules as $file) {
    $path = WP_MOVIES_PLUGIN_PATH . "includes/$file.php";

    if (file_exists($path)) {
        require_once $path;
    } else {
        if (function_exists('wp_movies_log')) {
            wp_movies_log("Missing module: $file", 'ERROR', 'core');
        }
    }
}

if (function_exists('wp_movies_log')) {
    wp_movies_log('WP Movies plugin initialized', 'INFO', 'core');
}

// Add a blank line separator after modules
if (
    function_exists('wp_movies_log_block_separator') &&
    function_exists('wp_get_environment_type') &&
    wp_get_environment_type() === 'local'
) {
    wp_movies_log_block_separator();
}
