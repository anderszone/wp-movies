<?php
if ( ! defined('ABSPATH') ) exit;

add_filter('wp_movies_fa_cdn_url', function($url) {
    if (apply_filters('wp_movies_force_bad_cdn', false)) {
        return 'https://invalid-cdn-url-test.css';
    }
    return $url;
});

// ==========================
// ENQUEUE FRONTEND ASSETS WITH SAFE FALLBACK
// ==========================
function wp_movies_enqueue_assets() {
    $request_id  = $GLOBALS['wp_movies_request_id'] ?? 'unknown';
    $user_id     = get_current_user_id();
    $method      = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    $memory_used = round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB';
    $plugin_version = '1.2';
    $handle         = 'font-awesome';
    $cdn_url = apply_filters('wp_movies_fa_cdn_url', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
    $local_path     = plugin_dir_url(__FILE__) . 'css/font-awesome.min.css';

    // --------------------------
    // Register CDN
    // --------------------------
    wp_register_style($handle, $cdn_url, [], '6.4.0', 'all');

    // --------------------------
    // Check if CDN is reachable (cached 10 min)
    // --------------------------
    $transient_key = 'wp_movies_fa_cdn_status';
    $cdn_status    = get_transient($transient_key);

    if ($cdn_status === false) {
        $response   = wp_remote_head($cdn_url, ['timeout' => 2]);
        $cdn_status = (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200);
        set_transient($transient_key, $cdn_status, 10 * MINUTE_IN_SECONDS);
    }

    $fallback_needed = !$cdn_status;

    if ($fallback_needed) {
        // Enqueue local fallback
        wp_enqueue_style($handle . '-local', $local_path, [], $plugin_version, 'all');

        // Log fallback
        wp_movies_log(
            sprintf(
                'Font Awesome CDN failed, using local fallback. URL: %s / Method: %s / User ID: %s / Memory: %s / ReqID: %s',
                $cdn_url, $method, $user_id, $memory_used, $request_id
            ),
            'DEBUG', 'assets'
        );

        // Browser console log
        add_action('wp_head', function() use ($local_path) {
            echo "<script>console.log('WP-MOVIES: Font Awesome CDN failed, loaded local fallback: {$local_path}');</script>";
        });
    } else {
        // Enqueue CDN normally
        wp_enqueue_style($handle);
    }

    // --------------------------
    // Menu Icons CSS
    // --------------------------
    wp_enqueue_style(
        'wp-movies-menu-icons',
        plugin_dir_url(__FILE__) . 'css/menu-icons.css',
        array($fallback_needed ? $handle . '-local' : $handle),
        $plugin_version
    );
}
add_action('wp_enqueue_scripts', 'wp_movies_enqueue_assets');

// ==========================
// ENQUEUE ADMIN ASSETS WITH SAFE FALLBACK
// ==========================
function wp_movies_enqueue_admin_assets($hook) {
    $request_id  = $GLOBALS['wp_movies_request_id'] ?? 'unknown';
    $user_id     = get_current_user_id();
    $method      = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    $memory_used = round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB';
    $plugin_version = '1.2';
    $handle         = 'font-awesome-admin';
    $cdn_url = apply_filters('wp_movies_fa_cdn_url', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
    $local_path     = plugin_dir_url(__FILE__) . 'css/font-awesome.min.css';

    wp_register_style($handle, $cdn_url, [], '6.4.0', 'all');

    $transient_key = 'wp_movies_fa_cdn_status_admin';
    $cdn_status    = get_transient($transient_key);

    if ($cdn_status === false) {
        $response   = wp_remote_head($cdn_url, ['timeout' => 2]);
        $cdn_status = (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200);
        set_transient($transient_key, $cdn_status, 10 * MINUTE_IN_SECONDS);
    }

    $fallback_needed = !$cdn_status;

    if ($fallback_needed) {
        wp_enqueue_style($handle . '-local', $local_path, [], $plugin_version, 'all');

        wp_movies_log(
            sprintf(
                'Font Awesome CDN failed, using local fallback. URL: %s / Method: %s / User ID: %s / Memory: %s / ReqID: %s',
                $cdn_url, $method, $user_id, $memory_used, $request_id
            ),
            'DEBUG', 'assets'
        );

        add_action('admin_head', function() use ($local_path) {
            echo "<script>console.log('WP-MOVIES [ADMIN]: Font Awesome CDN failed, loaded local fallback: {$local_path}');</script>";
        });
    } else {
        wp_enqueue_style($handle);
    }
}
add_action('admin_enqueue_scripts', 'wp_movies_enqueue_admin_assets');

// ==========================
// ADD SRI + CROSSORIGIN FOR FRONTEND
// ==========================
function wp_movies_add_sri($html, $handle) {
    $frontend_handles = array('font-awesome');
    if (in_array($handle, $frontend_handles, true)) {
        $sri = 'sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==';
        $html = str_replace(
            "rel='stylesheet'",
            "rel='stylesheet' integrity='$sri' crossorigin='anonymous'",
            $html
        );
    }
    return $html;
}
add_filter('style_loader_tag', 'wp_movies_add_sri', 10, 2);

// ==========================
// ASSETS MODULE (DEBUG)
// ==========================
wp_movies_register_module('assets');
