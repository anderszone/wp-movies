<?php
/**
 * Plugin Name: WP Movies
 * Description: Fetches and manages TMDB movies and TV shows in WordPress.
 * Version: 1.2
 * Author: Anders Johansson
 */

if (!defined('ABSPATH')) exit;

// ==========================
// PLUGIN PATH
// ==========================
define('WP_MOVIES_PLUGIN_PATH', plugin_dir_path(__FILE__));

// ==========================
// LOAD MODULES
// ==========================
$modules = [
    'constants',      // Plugin constants
    'tmdb-check',     // TMDB API key validation
    'admin',          // Admin pages & menus
    'admin-ajax',     // AJAX handlers
    'assets',         // CSS & JS assets
    'contact',        // Contact form / integration
    'cpt',            // Custom post types
    'database',       // Database setup & helpers
    'logger',         // Debug logger
    'session',        // Session helpers
    'admin-scripts'   // Admin JS enqueue & localization
];

foreach ($modules as $file) {
    $path = WP_MOVIES_PLUGIN_PATH . "includes/$file.php";
    if (file_exists($path)) require_once $path;
}
