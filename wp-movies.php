<?php
/**
 * Plugin Name: WP Movies
 * Description: Fetches and manages TMDB movies and TV shows in WordPress.
 * Version: 1.1
 * Author: Anders Johansson
 */

// ==========================
// SECURITY
// ==========================
if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================
// CONSTANTS
// ==========================
define( 'WP_MOVIES_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// ==========================
// LOAD PLUGIN MODULES
// ==========================
require_once WP_MOVIES_PLUGIN_PATH . 'includes/admin.php';
require_once WP_MOVIES_PLUGIN_PATH . 'includes/admin-ajax.php';
require_once WP_MOVIES_PLUGIN_PATH . 'includes/assets.php';
require_once WP_MOVIES_PLUGIN_PATH . 'includes/contact.php';
require_once WP_MOVIES_PLUGIN_PATH . 'includes/cpt.php';
require_once WP_MOVIES_PLUGIN_PATH . 'includes/database.php';
require_once WP_MOVIES_PLUGIN_PATH . 'includes/logger.php';
require_once WP_MOVIES_PLUGIN_PATH . 'includes/session.php';

// ==========================
// TRANSIENT KEYS
// ==========================
define('WP_MOVIES_TRANSIENT_MOVIE', 'wp_movies_tmdb_movie');
define('WP_MOVIES_TRANSIENT_TV', 'wp_movies_tmdb_tv');

// ==========================
// REQUIRE TMDB API KEY
// ==========================
if ( ! defined('TMDB_API_KEY') || ! constant('TMDB_API_KEY') ) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>WP Movies:</strong> TMDB_API_KEY is missing or empty in wp-config.php.</p></div>';
    });
    return; // STOP plugin execution safely
}

// ==========================
// ENQUEUE ADMIN JS
// ==========================
add_action('admin_enqueue_scripts', function($hook) {
    if ( $hook !== 'tools_page_update-tmdb-data' ) return;
    wp_enqueue_script(
        'wp-movies-admin-js',
        plugin_dir_url(__FILE__) . 'admin-refresh.js',
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script('wp-movies-admin-js', 'wpMoviesAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce_movies' => wp_create_nonce('refresh_movies_nonce'),
        'nonce_tvshows' => wp_create_nonce('refresh_tvshows_nonce')
    ]);
});
