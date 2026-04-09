<?php
if (!defined('ABSPATH')) exit;

// ==========================
// TRANSIENT KEYS
// ==========================
define('WP_MOVIES_TRANSIENT_MOVIE','wp_movies_tmdb_movie');
define('WP_MOVIES_TRANSIENT_TV','wp_movies_tmdb_tv');

// ==========================
// PLUGIN PATH
// ==========================
if (!defined('WP_MOVIES_PLUGIN_PATH')) {
    define('WP_MOVIES_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

// ==========================
// CONSTANTS DEBUG
// ==========================
wp_movies_register_module('constants');
