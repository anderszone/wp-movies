<?php
/**
 * Plugin Name: WP Movies
 * Description: Fetches and manages TMDB movies and TV shows in WordPress.
 * Version: 1.1
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
        error_log('TMDB API Fel: ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (json_last_error() !== JSON_ERROR_NONE || empty($data) || isset($data->status_code)) {
        error_log('Fel i TMDB-svar: ' . $body);
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
        error_log('Måste ha id, title, release_date');
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
        
        $wpdb->update(
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
        return $existing_id;
        
        // Om du bara vill ignorera och INTE lägga till dubbletten:
        error_log("Filmen {$movie->id} finns redan, hoppar över.");
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
        error_log('Fel vid insert: ' . $wpdb->last_error);
        return false;
    }
    return $wpdb->insert_id;
}

add_action('init', function() {
    $api_key = TMDB_API_KEY;
    $tmdb_id = 1061474; // Exempel: Superman
    $movie = wp_movies_get_tmdb_movie_details($tmdb_id, $api_key, 'en-US');
    if ($movie) {
        $result = wp_movies_save_tmdb_movie($movie);
        if ($result) {
            error_log('Filmdat sparad i wp_movies!');
        } else {
            error_log('Fel vid sparande till tabell: ' . $GLOBALS['wpdb']->last_error);
        }
    } else {
        error_log('Kunde inte hämta data från TMDB.');
    }
});

// ==========================
// SAVE TO DB (with genres)
// ==========================
function wp_movies_save_to_db($items, $type = 'movie') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'movies';

    foreach ( $items as $item ) {
		 // Logga genre_ids och genres för felsökning
        error_log('WP Movies genre_ids: ' . print_r($item->genre_ids ?? null, true));
        error_log('WP Movies genres: ' . print_r($item->genres ?? null, true));

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
        $wpdb->replace(
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
            error_log("Unknown genre ID: $id for type $type");
        }
    }
	// error_log('WP Movies DEBUG: genre_ids: ' . print_r($ids, true) . ' returnerade namn: ' . print_r($names, true));
    return $names;
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

function wp_movies_update_missing_genres() {
    global $wpdb;

    $tmdb_api_key = TMDB_API_KEY;
    $table = $wpdb->prefix . 'movies';

    $rows = $wpdb->get_results("SELECT * FROM $table WHERE genre IS NULL OR genre = ''");
    $updated_posts = array();

    if (!$rows) {
        error_log('wp-movies: Inga filmer eller serier att uppdatera.');
        return $updated_posts;
    }

    foreach ($rows as $row) {
        $tmdb_id = $row->tmdb_id;
        $type = $row->type;

        $url = "https://api.themoviedb.org/3/{$type}/{$tmdb_id}?api_key={$tmdb_api_key}&language=sv-SE";
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            error_log("wp-movies: Fel vid TMDB-anrop för $tmdb_id ({$row->title})");
            continue;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data->genres)) {
            error_log("wp-movies: Inga genres funna för $tmdb_id ({$row->title})");
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
            error_log("wp-movies: Genre uppdaterad: $info");
        } else {
            error_log("wp-movies: Misslyckades uppdatera genre för $tmdb_id ({$row->title})");
        }
    }

    // Optionellt: Sammanfattning till debug.log
    if (!empty($updated_posts)) {
        error_log('wp-movies: Totalt uppdaterade poster: ' . count($updated_posts));
    } else {
        error_log('wp-movies: Ingen post uppdaterades.');
    }

    return $updated_posts;
}

// Kör funktionen direkt, endast en gång eller via t.ex. adminmeny/WP-CLI
wp_movies_update_missing_genres();

// ==========================
// ADMIN PAGE CALLBACK
// ==========================
function wp_movies_admin_page() {
    ?>
    <div class="wrap">
		<h1>Update Local Database from TMDB</h1>
		<p>This will fetch the latest popular movies and TV shows from TMDB and update the local <code>wp_movies</code> table.</p>

        <!-- Update Now button -->
        <form method="post">
            <?php wp_nonce_field('wp_movies_update_nonce'); ?>
            <input type="submit" name="wp_movies_update" class="button button-primary"
                value="Update Now from TMDB">
        </form>
		
		<form method="post" style="margin-top:1em;">
			<?php wp_nonce_field('wp_movies_update_genres_nonce'); ?>
			<input type="submit" name="wp_movies_update_genres" class="button button-secondary"
				value="Uppdatera saknade genrer">
		</form>
		
		<?php
		if ( isset($_POST['wp_movies_update_genres']) && check_admin_referer('wp_movies_update_genres_nonce') ) {
			$updated_posts = wp_movies_update_missing_genres();
			if (!empty($updated_posts)) {
				echo '<div class="notice notice-success is-dismissible">';
				echo '<p>Genrer har uppdaterats för följande:</p>';
				echo '<ul style="max-height:300px;overflow:auto;">';
				foreach ($updated_posts as $post) {
					echo '<li><strong>' . esc_html($post['title']) . '</strong> (' . esc_html(ucfirst($post['type'])) . ') – <em>' . esc_html($post['genres']) . '</em></li>';
				}
				echo '</ul>';
				echo '</div>';
			} else {
				echo '<div class="notice notice-warning is-dismissible">';
				echo '<p>Inga genrer kunde uppdateras. (Alla kanske redan har genrer?)</p>';
				echo '</div>';
			}
		}
        
        if ( isset($_POST['wp_movies_update']) && check_admin_referer('wp_movies_update_nonce') ) {
            wp_movies_fetch_and_save();
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>TMDB data in the local database has been updated from <a href="https://www.themoviedb.org" target="_blank">TMDB</a> with latest movies and TV Shows.</p>';
            echo '</div>';
        }
        ?>

        <p>&nbsp;</p>
		<hr>
		<p>&nbsp;</p>

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
