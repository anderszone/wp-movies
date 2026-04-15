<?php
if ( ! defined('ABSPATH') ) exit;

// ==========================
// FILTER FOR FONT AWESOME CDN URL
// ==========================
// Allows forcing a bad CDN URL for testing fallback.
add_filter('wp_movies_fa_cdn_url', function($url) {
    if ( apply_filters('wp_movies_force_bad_cdn', false) ) {
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

    $plugin_data    = get_file_data(plugin_dir_path(__FILE__) . '../wp-movies.php', ['Version' => 'Version']);
    $plugin_version = $plugin_data['Version'] ?? '1.2';

    $handle     = 'font-awesome';
    $cdn_url    = apply_filters('wp_movies_fa_cdn_url', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css');
    $local_path = plugin_dir_url(__FILE__) . 'css/font-awesome.min.css';
    $log_debug  = defined('WP_DEBUG') && WP_DEBUG;

    wp_register_style($handle, $cdn_url, [], '7.0.1', 'all');

    // CDN check
    $transient_key = 'wp_movies_fa_cdn_status';
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

        if ($log_debug) {
            add_action('wp_head', function() use ($local_path) {
                echo "<script>console.log('WP-MOVIES: Font Awesome CDN failed, loaded local fallback: {$local_path}');</script>";
            });
        }
    } else {
        wp_enqueue_style($handle);
    }

    // ---------- Dynamisk local_path JS ----------
    wp_register_script('wp-movies-fa', '', [], null, true);
    wp_enqueue_script('wp-movies-fa');

    $local_path_js = json_encode($local_path);
    wp_add_inline_script('wp-movies-fa', "window.wpMoviesFAFallback = {$local_path_js};", 'before');

    // ---------- Fallback JS med DOM ready ----------
    $inline_js = <<<EOT
(function() {
    function runTest() {
        var testFA = document.createElement('i');
        testFA.className = 'fas fa-star';
        testFA.style.display = 'inline-block';
        testFA.style.fontSize = '16px';
        document.body.appendChild(testFA);

        var style = window.getComputedStyle(testFA);
        var fontLoaded = style.fontFamily && style.fontFamily.indexOf('Font Awesome 7') !== -1;

        if (!fontLoaded) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = window.wpMoviesFAFallback;
            document.head.appendChild(link);
            console.log('WP-MOVIES: Font Awesome CDN failed, loaded local fallback dynamically.');
        }

        document.body.removeChild(testFA);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runTest);
    } else {
        runTest();
    }
})();
EOT;
    wp_add_inline_script('wp-movies-fa', $inline_js, 'after');

    // ---------- Menu icons ----------
    wp_enqueue_style(
        'wp-movies-menu-icons',
        plugin_dir_url(__FILE__) . 'css/menu-icons.css',
        array($fallback_needed ? $handle . '-local' : $handle),
        $plugin_version
    );

    wp_add_inline_style('wp-movies-menu-icons', '
.fas::before,.far::before,.fab::before,[data-icon]::before{
    font-family:"Font Awesome 7 Free"!important;
    font-weight:900!important;
    speak:none;
    font-style:normal;
    font-variant:normal;
    text-transform:none;
    line-height:1;
    display:inline-block;
    text-align:center;
}
');

    // ---------- Contact form fallback ----------
    wp_enqueue_style(
        'wp-movies-contact',
        plugin_dir_url(__FILE__) . 'css/contact.css',
        [],
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

    // Dynamic plugin version
    $plugin_data    = get_file_data(plugin_dir_path(__FILE__) . '../wp-movies.php', ['Version' => 'Version']);
    $plugin_version = $plugin_data['Version'] ?? '1.2'; // Plugin-version

    $handle      = 'font-awesome-admin';
    $cdn_url     = apply_filters('wp_movies_fa_cdn_url','https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css');
    $local_path  = plugin_dir_url(__FILE__) . 'css/font-awesome.min.css';
    $log_debug   = defined('WP_DEBUG') && WP_DEBUG;

    wp_register_style($handle, $cdn_url, [], '7.0.1', 'all'); // FA-version  

    // Check CDN availability (cached 10 min)
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
                'Font Awesome Admin CDN failed, using local fallback. URL: %s / Method: %s / User ID: %s / Memory: %s / ReqID: %s',
                $cdn_url, $method, $user_id, $memory_used, $request_id
            ),
            'DEBUG', 'assets'
        );

        if ($log_debug) {
            add_action('admin_head', function() use ($local_path) {
                echo "<script>console.log('WP-MOVIES [ADMIN]: Font Awesome CDN failed, loaded local fallback: {$local_path}');</script>";
            });
        }
    } else {
        wp_enqueue_style($handle);
    }
}
add_action('admin_enqueue_scripts', 'wp_movies_enqueue_admin_assets');

// ==========================
// ADD SRI + CROSSORIGIN FOR FRONTEND
// ==========================
function wp_movies_add_sri($html, $handle) {
    $frontend_handles = ['font-awesome'];
    if (in_array($handle, $frontend_handles, true)) {
        // Korrekt SRI för Font Awesome 7.0.1 från Cloudflare CDN
        $sri = 'sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==';
        $html = str_replace(
            "rel='stylesheet'",
            "rel='stylesheet' integrity='$sri' crossorigin='anonymous'",
            $html
        );
    }
    return $html;
}
add_filter('style_loader_tag', 'wp_movies_add_sri', 10, 2);

// ================================
// DEBUG (safe, no side effects)
// ================================
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
    error_log( '[WP-MOVIES] ASSETS loaded: ' . __FILE__ );
}
