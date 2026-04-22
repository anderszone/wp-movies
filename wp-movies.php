<?php
/**
 * Plugin Name: WP Movies
 * Description: Fetches and manages TMDB movies and TV shows in WordPress.
 * Version: 1.3
 * Author: Anders Johansson
 *
 * Architecture: Modular loader + logging system + AJAX API layer
 *
 * Changelog:
 * - Improved module loader stability
 * - Fixed undefined variable in bootstrap
 * - Improved dev/prod separation
 */

if (!defined('ABSPATH')) exit;

// ========================================================
// PATH CONSTANTS
// ========================================================
define('WP_MOVIES_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WP_MOVIES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_MOVIES_ENV', defined('WP_DEBUG') && WP_DEBUG ? 'dev' : 'prod');

// ========================================================
// MODULE CONFIGURATION
// ========================================================
// Core modules (always loaded)
$all_modules = [
    'logger',
    'constants',
    'tmdb-check',
    'admin',
    'admin-ajax',
    'assets',
    'menu-icons',
    'contact',
    'cpt',
    'database',
    'session',
    'admin-scripts'
];

// Modules included in logging system
$log_modules = [
    'logger',
    'constants',
    'tmdb-check',
    'admin',
    'admin-ajax',
    'assets',
    'menu-icons',
    'admin-scripts'
];

$GLOBALS['wp_movies_log_modules'] = $log_modules;

function wp_movies_load_module(string $file) {

    $path = WP_MOVIES_PLUGIN_PATH . "includes/$file.php";

    if (file_exists($path)) {
        require_once $path;

        if (function_exists('wp_movies_log')) {
            wp_movies_log("Loaded module: $file", 'INFO', 'core');
        }

        return true;
    }

    if (function_exists('wp_movies_log')) {
        wp_movies_log("Missing module: $file", 'ERROR', 'core');
    }

    return false;
}

// ========================================================
// MODULE LOADER
// ========================================================
foreach ($all_modules as $file) {
    wp_movies_load_module($file);
}

// ========================================================
// OPTIONAL MODULES (DEV ONLY)
// ========================================================
if (defined('WP_DEBUG') && WP_DEBUG) {
    require_once WP_MOVIES_PLUGIN_PATH . 'includes/ajax/test.php';
}

// ========================================================
// CORE EXTENSIONS
// ========================================================
require_once WP_MOVIES_PLUGIN_PATH . 'includes/genre-functions.php';

// ========================================================
// INITIALIZATION LOG
// ========================================================
if (function_exists('wp_movies_log')) {
    wp_movies_log('WP Movies plugin initialized', 'INFO', 'core');
}

// ========================================================
// DEV VISUAL SEPARATOR (LOCAL ONLY)
// ========================================================
if (
    function_exists('wp_movies_log_block_separator') &&
    function_exists('wp_get_environment_type') &&
    wp_get_environment_type() === 'local'
) {
    wp_movies_log_block_separator();
}
