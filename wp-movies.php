<?php
/**
 * Plugin Name: WP Movies
 * Description: Fetches and manages TMDB movies and TV shows in WordPress.
 * Version: 1.0
 * Author: Anders Johansson
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================
// DEFINE TMDB API KEY
// ==========================
if ( ! defined('TMDB_API_KEY') ) {
    define('TMDB_API_KEY', 'din_tmdb_api_nyckel'); // Byt ut mot din riktiga nyckel
}

// ==========================
// CREATE TABLE ON PLUGIN ACTIVATION
// ==========================
register_activation_hook( __FILE__, 'wp_movies_create_table_if_not_exists' );
function wp_movies_create_table_if_not_exists() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'movies';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            tmdb_id bigint(20) NOT NULL,
            title text NOT NULL,
            poster varchar(255) DEFAULT '' NOT NULL,
            release_date date DEFAULT NULL,
            type varchar(20) DEFAULT 'movie' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tmdb_id (tmdb_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}

// ==========================
// FETCH FROM TMDB
// ==========================
function wp_movies_fetch_from_tmdb( $type = 'movie' ) {
    $api_key = TMDB_API_KEY;
    $api_url = "https://api.themoviedb.org/3/{$type}/popular?api_key={$api_key}&language=en-US&page=1";

    $response = wp_remote_get($api_url);
    if ( is_wp_error($response) ) return false;

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    return isset($data->results) ? $data->results : false;
}

// ==========================
// SAVE TO DB
// ==========================
function wp_movies_save_to_db($items, $type = 'movie') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'movies';

    foreach ( $items as $item ) {
        $wpdb->replace(
            $table_name,
            [
                'tmdb_id'      => $item->id,
                'title'        => $item->title ?? $item->name,
                'poster'       => $item->poster_path ?? '',
                'release_date' => $item->release_date ?? ($item->first_air_date ?? null),
                'type'         => $type
            ],
            ['%d','%s','%s','%s','%s']
        );
    }
}

// ==========================
// FETCH & SAVE WITH LOGGING
// ==========================
function wp_movies_fetch_and_save() {
    $current_user = wp_get_current_user();
    $username = $current_user && $current_user->exists() ? $current_user->user_login : 'system';

    error_log("\n==================== TMDB SYNC START ====================");
    error_log("Triggered by: {$username}");
    error_log("Timestamp: " . date('Y-m-d H:i:s'));

    // Movies
    $movies = wp_movies_fetch_from_tmdb('movie');
    if ($movies) {
        wp_movies_save_to_db($movies, 'movie');
        error_log("✅ Movies fetched and saved (" . count($movies) . " items)");
    } else {
        error_log("⚠️ Failed to fetch movies from TMDB");
    }

    // TV Shows
    $tvshows = wp_movies_fetch_from_tmdb('tv');
    if ($tvshows) {
        wp_movies_save_to_db($tvshows, 'tv');
        error_log("✅ TV shows fetched and saved (" . count($tvshows) . " items)");
    } else {
        error_log("⚠️ Failed to fetch TV shows from TMDB");
    }

    error_log("TMDB sync completed at " . date('Y-m-d H:i:s'));
    error_log("===================== TMDB SYNC END =====================\n");
}

// ==========================
// FETCH FROM DB (random & logging)
// ==========================
function wp_movies_get_from_db($type = 'movie', $limit = 8, $random = false) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'movies';

    $query = $random 
        ? $wpdb->prepare("SELECT * FROM $table_name WHERE type = %s ORDER BY RAND() LIMIT %d", $type, $limit)
        : $wpdb->prepare("SELECT * FROM $table_name WHERE type = %s ORDER BY id DESC LIMIT %d", $type, $limit);

    $results = $wpdb->get_results($query);

    $user = wp_get_current_user();
    $username = $user && $user->exists() ? $user->user_login : 'system';
    $method = $random ? 'Random selection' : 'Latest entries';

    error_log("\n--- DB FETCH ---");
    error_log("User: {$username}");
    error_log("Type: {$type}");
    error_log("Method: {$method}");
    error_log("Count: " . count($results));
    error_log("Timestamp: " . date('Y-m-d H:i:s'));
    error_log("----------------\n");

    return $results;
}

// ==========================
// ADMIN MENU
// ==========================
add_action('admin_menu', function() {
    add_submenu_page(
        'tools.php',
        'Update TMDB Data',
        'Update TMDB Data',
        'manage_options',
        'update-tmdb-data',
        'wp_movies_admin_page'
    );
});

// ==========================
// ADMIN PAGE CALLBACK
// ==========================
function wp_movies_admin_page() {
    ?>
    <div class="wrap">
        <h1>Update TMDB Data</h1>

        <!-- Update Now button -->
        <form method="post">
            <?php wp_nonce_field('wp_movies_update_nonce'); ?>
            <input type="submit" name="wp_movies_update" class="button button-primary"
                value="Update Now (For testing / debug only)">
        </form>

        <?php
        if ( isset($_POST['wp_movies_update']) && check_admin_referer('wp_movies_update_nonce') ) {
            wp_movies_fetch_and_save();
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>TMDB data in the local database has been updated from <a href="https://www.themoviedb.org" target="_blank">TMDB</a> with latest movies and TV Shows.</p>';
            echo '</div>';
        }
        ?>

        <hr>

        <!-- Randomize Local Data (for testing) -->
        <h2>Randomize Local Data</h2>
        <p>These buttons shuffle 8 random movies or TV shows from the local database. For testing / debug only.</p>

        <button id="refresh-movies" class="button button-primary">🎬 Refresh Movies</button>
        <button id="refresh-tvshows" class="button button-secondary">📺 Refresh TV Shows</button>

        <div id="refresh-result" style="margin-top:20px;"></div>
    </div>
    <?php
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

// ==========================
// AJAX: Admin - Refresh Movies
// ==========================
add_action('wp_ajax_refresh_movies', 'wp_movies_refresh_movies');
function wp_movies_refresh_movies() {
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized', 403);
    if ( ! check_ajax_referer('refresh_movies_nonce', '_wpnonce', false) ) wp_send_json_error('Nonce verification failed', 403);

    $movies = wp_movies_get_from_db('movie', 8, true);

    error_log("\n=== MANUAL REFRESH: MOVIES ===");
    error_log('Triggered by: ' . wp_get_current_user()->user_login);
    error_log('Number of movies randomized: ' . count($movies));
    error_log('Time: ' . date('Y-m-d H:i:s') . "\n");

    wp_send_json_success(['movies' => $movies]);
}

// ==========================
// AJAX: Admin - Refresh TV Shows
// ==========================
add_action('wp_ajax_refresh_tvshows', 'wp_movies_refresh_tvshows');
function wp_movies_refresh_tvshows() {
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized', 403);
    if ( ! check_ajax_referer('refresh_tvshows_nonce', '_wpnonce', false) ) wp_send_json_error('Nonce verification failed', 403);

    $tvshows = wp_movies_get_from_db('tv', 8, true);

    error_log("\n=== MANUAL REFRESH: TV SHOWS ===");
    error_log('Triggered by: ' . wp_get_current_user()->user_login);
    error_log('Number of TV shows randomized: ' . count($tvshows));
    error_log('Time: ' . date('Y-m-d H:i:s') . "\n");

    wp_send_json_success(['tvshows' => $tvshows]);
}

// ==========================
// AJAX: Front-End - Refresh Movies
// ==========================
add_action('wp_ajax_nopriv_front_refresh_movies', 'wp_front_refresh_movies');
add_action('wp_ajax_front_refresh_movies', 'wp_front_refresh_movies');
function wp_front_refresh_movies() {
    wp_send_json_success(['movies' => wp_movies_get_from_db('movie', 8, true)]);
}

// ==========================
// AJAX: Front-End - Refresh TV Shows
// ==========================
add_action('wp_ajax_nopriv_front_refresh_tvshows', 'wp_front_refresh_tvshows');
add_action('wp_ajax_front_refresh_tvshows', 'wp_front_refresh_tvshows');
function wp_front_refresh_tvshows() {
    wp_send_json_success(['tvshows' => wp_movies_get_from_db('tv', 8, true)]);
}
