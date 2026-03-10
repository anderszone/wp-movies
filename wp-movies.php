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
require_once WP_MOVIES_PLUGIN_PATH . 'includes/session.php';

// ==========================
// TRANSIENT KEYS
// ==========================

define('WP_MOVIES_TRANSIENT_MOVIE', 'wp_movies_tmdb_movie');
define('WP_MOVIES_TRANSIENT_TV', 'wp_movies_tmdb_tv');

// ==========================
// SIMPLE DEBUG LOGGER
// ==========================

function wp_movies_log($message, $level = 'info') {

    // Only log in local environment
    if (!defined('WP_ENVIRONMENT_TYPE') || constant('WP_ENVIRONMENT_TYPE') !== 'local') {
        return;
    }

    // Only log warnings and errors (not normal info messages)
    if ($level === 'error' || $level === 'warning') {

        if (is_array($message) || is_object($message)) {
            error_log(print_r($message, true));
        } else {
            error_log($message);
        }

    }
}

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
// CREATE TABLE ON PLUGIN ACTIVATION
// ==========================

register_activation_hook( __FILE__, 'wp_movies_create_table_if_not_exists' );

function wp_movies_create_table_if_not_exists() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'movies';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tmdb_id bigint(20) NOT NULL,
        title text NOT NULL,
        genre varchar(255) DEFAULT NULL,
        poster varchar(255) DEFAULT '' NOT NULL,
        release_date date DEFAULT NULL,
        type varchar(20) DEFAULT 'movie' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY tmdb_id (tmdb_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    // Clear any existing TMDB cache on activation
    delete_transient(WP_MOVIES_TRANSIENT_MOVIE);
    delete_transient(WP_MOVIES_TRANSIENT_TV);
}

// ==========================
// FETCH FROM TMDB
// ==========================

function wp_movies_fetch_from_tmdb( $type = 'movie' ) {

    // Allow only valid types
    $type = ($type === 'tv') ? 'tv' : 'movie';

    $transient_key = ($type === 'tv')
    ? WP_MOVIES_TRANSIENT_TV
    : WP_MOVIES_TRANSIENT_MOVIE;

    $cached = get_transient($transient_key);
    if ( false !== $cached ) {
        return $cached;
    }

    // Ensure API key exists
    if ( ! defined('TMDB_API_KEY') || ! constant('TMDB_API_KEY') ) {
        wp_movies_log('TMDB_API_KEY missing or empty.', 'error');
        return false;
    }

    $api_key = constant('TMDB_API_KEY');

    $api_url = sprintf(
        'https://api.themoviedb.org/3/%s/popular?api_key=%s&language=en-US&page=1',
        $type,
        urlencode($api_key)
    );

    $response = wp_remote_get( $api_url, [
        'timeout' => 15,
    ]);

    // Retry once if first request fails
    if ( is_wp_error( $response ) ) {

        $error_code = $response->get_error_code();

        if ( $error_code === 'http_request_failed' ) {
            wp_movies_log('First TMDB request failed. Retrying...', 'warning');

            $response = wp_remote_get( $api_url, [ 'timeout' => 15 ] );
        }

        if ( is_wp_error( $response ) ) {
            wp_movies_log(
                'TMDB request failed after retry: ' . $response->get_error_message(),
                'error'
            );
            return false;
        }
    }

    // Kontrollera HTTP-statuskod
    $status_code = wp_remote_retrieve_response_code( $response );

    if ( $status_code === 429 ) {
        wp_movies_log('TMDB rate limit reached.', 'error');
        return false;
    }

    if ( $status_code !== 200 ) {
        wp_movies_log('TMDB returned HTTP status: ' . $status_code, 'error');
        return false;
    }

    $body = wp_remote_retrieve_body( $response );

    if ( empty( $body ) ) {
        wp_movies_log('TMDB returned empty body.', 'error');
        return false;
    }

    $data = json_decode( $body );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        wp_movies_log('JSON decode error: ' . json_last_error_msg(), 'error');
        return false;
    }

    if ( empty( $data->results ) || ! is_array( $data->results ) ) {
        wp_movies_log('TMDB response missing results array.', 'error');
        return false;
    }

    set_transient($transient_key, $data->results, HOUR_IN_SECONDS);

    return $data->results;
}

/**
 * Hämta TMDB-detaljer för en viss film.
 *
 * @param int $tmdb_id
 * @param string $api_key
 * @param string $language
 * @return object|false
 */
function wp_movies_get_tmdb_movie_details($tmdb_id, $api_key, $language = 'en-US') {
    $url = sprintf(
        'https://api.themoviedb.org/3/movie/%d?api_key=%s&language=%s',
        $tmdb_id,
        urlencode($api_key),
        urlencode($language)
    );

    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        wp_movies_log('TMDB API Error: ' . $response->get_error_message(), 'error');
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (json_last_error() !== JSON_ERROR_NONE || empty($data) || isset($data->status_code)) {
        wp_movies_log('TMDB API response error: ' . $body, 'error');
        return false;
    }

    return $data; // stdClass med filmdata
}

/**
 * Spara en (detaljerad) TMDB-film till wp_movies-tabellen (inget dublettfel).
 *
 * @param object $movie TMDB-filmdata som objekt
 * @return int|false Radens ID eller false om misslyckat
 */
function wp_movies_save_tmdb_movie($movie) {
    global $wpdb;

    // Kontrollera att rätt fält finns
    if (!isset($movie->id, $movie->title, $movie->release_date)) {
        wp_movies_log('Missing required fields: id, title, release_date');
        return false;
    }

    $table = $wpdb->prefix . 'movies';

    // Hantera genres (kan vara array av objekt eller string)
    $genres = '';
    if (!empty($movie->genres) && is_array($movie->genres)) {
        $genre_names = array_map(function($g){ return $g->name; }, $movie->genres);
        $genres = implode(', ', $genre_names);
    } elseif (!empty($movie->genre)) {
        $genres = $movie->genre;
    }

    $poster_path = !empty($movie->poster_path) ? $movie->poster_path : '';

    // Kolla om filmen redan finns (unikt tmdb_id)
    $existing_id = $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM $table WHERE tmdb_id = %d", $movie->id)
    );

    if ($existing_id) {
        // Om du vill uppdatera posten istället, avkommentera dessa rader:
        
        $result = $wpdb->update(
            $table,
            array(
                'title'        => $movie->title,
                'genre'        => $genres,
                'poster'       => $poster_path,
                'release_date' => $movie->release_date,
                'type'         => 'movie'
            ),
            array('tmdb_id' => $movie->id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            wp_movies_log('Database update error: ' . $wpdb->last_error);
            return false;
        }

        wp_movies_log("Movie {$movie->id} updated.");
        return $existing_id;
    }

    // Skapa array med alla fält som din tabell kräver
    $data = array(
        'tmdb_id'      => $movie->id,
        'title'        => $movie->title,
        'genre'        => $genres,
        'poster'       => $poster_path,
        'release_date' => $movie->release_date,
        'type'         => 'movie'
    );

    $format = array('%d', '%s', '%s', '%s', '%s', '%s');

    $result = $wpdb->insert($table, $data, $format);

    if (false === $result) {
        wp_movies_log('Database insert error: ' . $wpdb->last_error);
        return false;
    }
    return $wpdb->insert_id;
}

// ==========================
// SAVE TO DB (with genres)
// ==========================

function wp_movies_save_to_db($items, $type = 'movie') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'movies';

    foreach ( $items as $item ) {
	    // Logga genre_ids och genres för felsökning
        // wp_movies_log('WP Movies genre_ids: ' . print_r($item->genre_ids ?? null, true));
        // wp_movies_log('WP Movies genres: ' . print_r($item->genres ?? null, true));

        // --- Hämta genretext ---
        $genre_text = '';

        if ( isset( $item->genre_ids ) && is_array( $item->genre_ids ) ) {
            // genre_ids finns i "popular" endpoint
            $genres = wp_movies_get_genre_names_from_ids( $item->genre_ids, $type );
            $genre_text = implode( ', ', $genres );
        } elseif ( isset( $item->genres ) && is_array( $item->genres ) ) {
            // vissa TMDB-svar innehåller redan "genres" med namn
            $genre_text = implode( ', ', array_column( $item->genres, 'name' ) );
        }

        // --- Spara allt till databasen ---
        $result = $wpdb->replace(
            $table_name,
            [
                'tmdb_id'      => $item->id,
                'title'        => $item->title ?? $item->name,
                'poster'       => $item->poster_path ?? '',
                'release_date' => $item->release_date ?? ($item->first_air_date ?? null),
                'genre'        => $genre_text,
                'type'         => $type
            ],
            ['%d','%s','%s','%s','%s','%s']
        );

        if ($result === false) {
            wp_movies_log('Database replace error: ' . $wpdb->last_error);
        }
    }
}

// ==========================
// HELPER: Convert genre IDs → names
// ==========================

function wp_movies_get_genre_names_from_ids( $ids, $type = 'movie' ) {
    // Vanliga TMDB-genrer
    $genres_movie = [
        28 => 'Action',
        12 => 'Adventure',
        16 => 'Animation',
        35 => 'Comedy',
        80 => 'Crime',
        99 => 'Documentary',
        18 => 'Drama',
        10751 => 'Family',
        14 => 'Fantasy',
        36 => 'History',
        27 => 'Horror',
        10402 => 'Music',
        9648 => 'Mystery',
        10749 => 'Romance',
        878 => 'Science Fiction',
        10770 => 'TV Movie',
        53 => 'Thriller',
        10752 => 'War',
        37 => 'Western'
    ];

    $genres_tv = [
        10759 => 'Action & Adventure',
        16 => 'Animation',
        35 => 'Comedy',
        80 => 'Crime',
        99 => 'Documentary',
        18 => 'Drama',
        10751 => 'Family',
        10762 => 'Kids',
        9648 => 'Mystery',
        10763 => 'News',
        10764 => 'Reality',
        10765 => 'Sci-Fi & Fantasy',
        10766 => 'Soap',
        10767 => 'Talk',
        10768 => 'War & Politics',
        37 => 'Western'
    ];

    $map = ($type === 'tv') ? $genres_tv : $genres_movie;

    $names = [];
	
	// Byt ut nedanstående loop mot den med error_log:
    foreach ( $ids as $id ) {
        if ( isset( $map[ $id ] ) ) {
            $names[] = $map[ $id ];
        } else {
            wp_movies_log("Unknown genre ID: $id for type $type", 'warning');
        }
    }
    return $names;
} 

// ==========================
// FETCH & SAVE
// ==========================

function wp_movies_fetch_and_save() {

    // Clear TMDB cache before manual update
    delete_transient(WP_MOVIES_TRANSIENT_MOVIE);
    delete_transient(WP_MOVIES_TRANSIENT_TV);

    // Movies
    $movies = wp_movies_fetch_from_tmdb('movie');
    if ($movies) {
        wp_movies_save_to_db($movies, 'movie');
    } else {
        wp_movies_log("Failed to fetch movies from TMDB", 'error');
    }

    // TV Shows
    $tvshows = wp_movies_fetch_from_tmdb('tv');
    if ($tvshows) {
        wp_movies_save_to_db($tvshows, 'tv');
    } else {
        wp_movies_log("Failed to fetch TV shows from TMDB", 'error');
    }
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

    return $results;
}

function wp_movies_update_missing_genres() {
    global $wpdb;

    if ( ! defined('TMDB_API_KEY') ) {
        wp_movies_log('TMDB_API_KEY missing.');
        return [];
    }

    $tmdb_api_key = defined('TMDB_API_KEY') ? constant('TMDB_API_KEY') : '';
    $table = $wpdb->prefix . 'movies';

    $rows = $wpdb->get_results("SELECT * FROM $table WHERE genre IS NULL OR genre = ''");
    $updated_posts = array();

    if (!$rows) {
        wp_movies_log("No movies or TV shows needed genre updates.");
        return $updated_posts;
    }

    foreach ($rows as $row) {
        $tmdb_id = $row->tmdb_id;
        $type = $row->type;

        $url = "https://api.themoviedb.org/3/{$type}/{$tmdb_id}?api_key={$tmdb_api_key}&language=en-US";
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            wp_movies_log("TMDB request failed for ID {$tmdb_id} ({$row->title})");
            continue;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data->genres)) {
            wp_movies_log("No genres returned for ID {$tmdb_id} ({$row->title})");
            continue;
        }

        $genres = implode(', ', array_map(fn($g) => $g->name, $data->genres));

        $result = $wpdb->update(
            $table, 
            array('genre' => $genres),
            array('id' => $row->id),
            array('%s'),
            array('%d')
        );

        if($result !== false) {
            $info = "{$row->title} ({$type}) – {$genres}";
            $updated_posts[] = array(
                'title' => $row->title,
                'type' => $type,
                'genres' => $genres
            );
            // Logga varje uppdatering direkt till debug.log
            wp_movies_log("Genre updated: {$info}");
        } else {
            wp_movies_log("Failed to update genre for ID {$tmdb_id} ({$row->title})");
        }
    }

    // Optionellt: Sammanfattning till debug.log
    if (!empty($updated_posts)) {
        wp_movies_log("Total updated rows: " . count($updated_posts));
    } else {
        wp_movies_log("No rows were updated.");
    }
        return $updated_posts;
}

add_action('admin_init', 'wp_movies_handle_admin_actions');

function wp_movies_handle_admin_actions() {

    if ( ! isset($_GET['page']) || $_GET['page'] !== 'update-tmdb-data' ) {
        return;
    }

    if ( isset($_POST['wp_movies_update']) && check_admin_referer('wp_movies_update_nonce') ) {

        wp_movies_fetch_and_save();

        wp_redirect(
            add_query_arg(
                array( 'wp_movies_notice' => 'updated' ),
                admin_url('tools.php?page=update-tmdb-data')
            )
        );
        exit;
    }

    if ( isset($_POST['wp_movies_update_genres']) && check_admin_referer('wp_movies_update_genres_nonce') ) {

        $updated_posts = wp_movies_update_missing_genres();
        $status = empty($updated_posts) ? 'no_genres' : 'genres_updated';

        wp_redirect(
            add_query_arg(
                array( 'wp_movies_notice' => $status ),
                admin_url('tools.php?page=update-tmdb-data')
            )
        );
        exit;
    }
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
