<?php
if (!defined('ABSPATH')) exit;

add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'tools_page_update-tmdb-data') return;

    wp_enqueue_script(
        'wp-movies-admin-js',
        plugin_dir_url(__FILE__) . '../admin-refresh.js',
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
