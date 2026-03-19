<?php
if (!defined('ABSPATH')) exit;

add_action('admin_enqueue_scripts', function($hook) { 
    if ($hook !== 'toplevel_page_tmdb-movies') return;

    $js_path = WP_MOVIES_PLUGIN_PATH . 'includes/admin-refresh.js';
    $js_url  = WP_MOVIES_PLUGIN_URL . 'includes/admin-refresh.js';

    wp_enqueue_script(
        'wp-movies-admin-js',
        $js_url,
        ['jquery'],
        file_exists($js_path) ? filemtime($js_path) : false,
        true
    );

    wp_localize_script('wp-movies-admin-js','wpMoviesAjax',[
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce_update_tmdb' => wp_create_nonce('wp_movies_update_tmdb_nonce'),
        'nonce_sync_genres' => wp_create_nonce('wp_movies_sync_genres_nonce'),
        'nonce_movies' => wp_create_nonce('wp_movies_refresh_movies_nonce'),
        'nonce_tvshows' => wp_create_nonce('wp_movies_refresh_tvshows_nonce')
    ]);
});

// ==========================
// ADMIN SCRIPTS DEBUG MODULE
// ==========================
wp_movies_register_module('admin-scripts');
