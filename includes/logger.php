<?php
if (!defined('ABSPATH')) exit;

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
    if (function_exists('wp_get_environment_type') && wp_get_environment_type() !== 'local') return;
    if (!wp_movies_should_log($module)) return;

    $level_str = strtoupper($level);

    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $file  = isset($trace[1]['file']) ? basename($trace[1]['file']) : 'unknown';
    $line  = isset($trace[1]['line']) ? $trace[1]['line'] : '0';

    if (defined('DOING_AJAX') && DOING_AJAX) {
        static $last_ajax = false;
        if ($last_ajax) error_log(str_repeat('─', 40));
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

    if (!isset($wp_movies_logged_modules)) $wp_movies_logged_modules = [];

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
// PAGE CONTEXT DETECTOR
// ========================================================
function wp_movies_get_context() {
    if (defined('DOING_AJAX') && DOING_AJAX) return 'AJAX';
    if (defined('DOING_CRON') && DOING_CRON) return 'CRON';
    if (is_admin()) return 'ADMIN';
    return 'FRONTEND';
}

// ========================================================
// MODULE REGISTRY
// ========================================================
if (!isset($GLOBALS['wp_movies_loaded_modules'])) $GLOBALS['wp_movies_loaded_modules'] = [];

function wp_movies_register_module($module) {
    if (!in_array($module, $GLOBALS['wp_movies_loaded_modules'], true)) {
        $GLOBALS['wp_movies_loaded_modules'][] = $module;
    }
}

// ========================================================
// SMART PAGE SUMMARY LOGGER (10/10)
// ========================================================
if (!isset($GLOBALS['wp_movies_request_id'])) {
    $GLOBALS['wp_movies_request_id']    = wp_generate_uuid4();
    $GLOBALS['wp_movies_request_start'] = microtime(true);
}

function wp_movies_log_page_summary() {
    if (function_exists('wp_get_environment_type') && wp_get_environment_type() !== 'local') return;

    global $wpdb;

    $context = wp_movies_get_context();
    $modules = implode(',', $GLOBALS['wp_movies_loaded_modules'] ?? []);
    $time    = round(microtime(true) - $GLOBALS['wp_movies_request_start'], 3);
    $queries = isset($wpdb) ? $wpdb->num_queries : 0;

    // --- NYA INFO FÖR DEBUG 10/10 ---
    $url     = $_SERVER['REQUEST_URI'] ?? '';
    $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
    $memory  = size_format(memory_get_peak_usage(true));
    $status  = ($time > 1) ? 'SLOW' : 'OK';

    error_log(
        "[WP-MOVIES][$context][req:{$GLOBALS['wp_movies_request_id']}] " .
        "time: {$time}s / queries: {$queries} / memory: {$memory} " .
        "/ url: {$url} / method: {$method} / user: {$user_id}" .
        (!empty($modules) ? " / modules: {$modules}" : "") .
        " / status: {$status}"
    );
}
add_action('shutdown', 'wp_movies_log_page_summary');
