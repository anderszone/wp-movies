<?php
if (!defined('ABSPATH')) exit;

if (!isset($GLOBALS['wp_movies_loaded_modules'])) {
    $GLOBALS['wp_movies_loaded_modules'] = [];
}

// ========================================================
// WP MOVIES DEBUG LOGGER - DEV_LOG STUB FOR IDE
// ========================================================
if (!function_exists('dev_log')) {
    function dev_log($message, $level = 'INFO') {
        // Intentionally empty; real function is in MU-plugin
    }
}

// ========================================================
// LOG FILTER (MODULE CONTROL)
// ========================================================
function wp_movies_should_log($module) {
    if (!isset($GLOBALS['wp_movies_log_modules'])) {
        return true; // fallback: log everything
    }

    return in_array($module, $GLOBALS['wp_movies_log_modules'], true);
}

// ========================================================
// LOGGING FUNCTION
// ========================================================
function wp_movies_log($message, $level = 'INFO', $module = 'general') {

    if (function_exists('wp_get_environment_type') && wp_get_environment_type() !== 'local') {
        return;
    }

    if (!wp_movies_should_log($module)) {
        return;
    }

    $level_str = strtoupper($level);

    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $file  = isset($trace[1]['file']) ? basename($trace[1]['file']) : 'unknown';
    $line  = isset($trace[1]['line']) ? $trace[1]['line'] : '0';

    // AJAX separator handling
    if (defined('DOING_AJAX') && DOING_AJAX) {
        static $last_ajax = false;

        if ($last_ajax) {
            error_log(str_repeat('─', 40));
        }

        $last_ajax = true;
    }

    $log_str = "[$level_str][WP-MOVIES][$module][$file:$line] $message";

    if (function_exists('dev_log')) {
        dev_log($log_str, $level_str);
        return;
    }

    error_log($log_str);
}

// ========================================================
// MODULE-ONCE LOGGING
// ========================================================
function wp_movies_log_module_once(string $module, string $level = 'INFO') {

    global $wp_movies_logged_modules;

    if (!isset($wp_movies_logged_modules)) {
        $wp_movies_logged_modules = [];
    }

    if (!in_array($module, $wp_movies_logged_modules, true)) {
        $wp_movies_logged_modules[] = $module;
        wp_movies_log("$module loaded", $level, $module);
    }
}

// ========================================================
// LOG BLOCK SEPARATOR
// ========================================================
function wp_movies_log_block_separator() {
    error_log(str_repeat('─', 40));
}

// ========================================================
// PAGE CONTEXT DETECTOR (ROBUST 10/10 VERSION)
// ========================================================
function wp_movies_get_context() {

    // WP-CLI (safest possible check)
    if (class_exists('WP_CLI')) {
        return 'CLI';
    }

    // REST API (important edge case before admin)
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return 'REST';
    }

    // AJAX (frontend + admin)
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return is_admin() ? 'ADMIN_AJAX' : 'AJAX';
    }

    // WP-Cron
    if (defined('DOING_CRON') && DOING_CRON) {
        return 'CRON';
    }

    // Admin dashboard (non-AJAX)
    if (is_admin()) {
        return 'ADMIN';
    }

    return 'FRONTEND';
}

// ========================================================
// REQUEST METADATA (SAFE INITIALIZATION)
// ========================================================
if (!isset($GLOBALS['wp_movies_request_id'])) {
    $GLOBALS['wp_movies_request_id'] =
        function_exists('wp_generate_uuid4')
            ? wp_generate_uuid4()
            : uniqid('req_', true);
}

if (!isset($GLOBALS['wp_movies_request_start'])) {
    $GLOBALS['wp_movies_request_start'] = microtime(true);
}

// ========================================================
// SMART PAGE SUMMARY LOGGER (10/10)
// ========================================================
function wp_movies_log_page_summary() {

    if (function_exists('wp_get_environment_type') && wp_get_environment_type() !== 'local') {
        return;
    }

    global $wpdb;

    $start = $GLOBALS['wp_movies_request_start'] ?? microtime(true);

    $context = wp_movies_get_context();
    $modules = implode(',', $GLOBALS['wp_movies_loaded_modules'] ?? []);

    $time = round(microtime(true) - $start, 3);

    $queries = (isset($wpdb) && is_object($wpdb)) ? $wpdb->num_queries : 0;

    // SAFE SERVER ACCESS (CLI/CRON friendly)
    $url     = $_SERVER['REQUEST_URI'] ?? '';
    $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $user_id = function_exists('get_current_user_id')
        ? get_current_user_id()
        : 0;

    $memory = function_exists('size_format')
        ? size_format(memory_get_peak_usage(true))
        : memory_get_peak_usage(true) . ' bytes';

    $status = ($time > 1) ? 'SLOW' : 'OK';

    error_log(
        "[WP-MOVIES][$context][req:{$GLOBALS['wp_movies_request_id']}] " .
        "time: {$time}s / queries: {$queries} / memory: {$memory} " .
        "/ url: {$url} / method: {$method} / user: {$user_id}" .
        (!empty($modules) ? " / modules: {$modules}" : "") .
        " / status: {$status}"
    );
}

add_action('shutdown', 'wp_movies_log_page_summary');
