<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================
// ADMIN MENU
// ==========================

add_action( 'admin_menu', 'wp_movies_register_admin_menu' );

function wp_movies_register_admin_menu() {
    add_menu_page(
        'TMDB Movies',
        'TMDB Movies',
        'manage_options',
        'tmdb-movies',
        'wp_movies_admin_page',
        'dashicons-format-video',
        20
    );

    // Update Data (ersätter WordPress auto submenu)
    add_submenu_page(
        'tmdb-movies',
        'Update Data',
        'Update Data',
        'manage_options',
        'tmdb-movies',   // ← samma slug som parent
        'wp_movies_admin_page'
    );

    // Genres
    add_submenu_page(
        'tmdb-movies',
        'Genres',
        'Genres',
        'manage_options',
        'edit-tags.php?taxonomy=genre&post_type=movie'
    );
}

// ==========================
// FIX ADMIN MENU HIGHLIGHT
// ==========================

add_filter('parent_file', function($parent_file) {
    global $current_screen;

    if ( isset($current_screen->taxonomy) && $current_screen->taxonomy === 'genre' ) {
        $parent_file = 'tmdb-movies';
    }

    return $parent_file;
});

// ==========================
// FIX ADMIN SUBMENU ACTIVE
// ==========================

add_filter('submenu_file', function($submenu_file) {
    global $current_screen;

    if ( isset($current_screen->taxonomy) && $current_screen->taxonomy === 'genre' ) {
        $submenu_file = 'edit-tags.php?taxonomy=genre&post_type=movie';
    }

    return $submenu_file;
});

// ==========================
// ADMIN PAGE CALLBACK
// ==========================

function wp_movies_admin_page() {
    ?>
    <div class="wrap">
        <h1>Update Local Database from TMDB</h1>
        <p>This will fetch the latest popular movies and TV shows from TMDB and update the local <code>wp_movies</code> table.</p>

    <?php
        // --------------------------
        // DISPLAY NOTICES (GET only)
        // --------------------------
        
        $notice = isset($_GET['wp_movies_notice'])
            ? sanitize_text_field($_GET['wp_movies_notice'])
            : '';

        if ( $notice ) {

            if ( $notice === 'updated' ) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>TMDB data in the local database has been updated successfully.</p>';
                echo '</div>';
            }

            elseif ( $notice === 'genres_updated' ) {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p>Missing genres were successfully synced from TMDB.</p>';
                echo '</div>';
            }

            elseif ( $notice === 'no_genres' ) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p>No genres needed updating.</p>';
                echo '</div>';
            }
        }
    ?>

        <!-- Update Now button -->
        <form method="post">
            <?php wp_nonce_field('wp_movies_update_nonce'); ?>
            <input type="submit" name="wp_movies_update"
                   class="button button-primary"
                   value="Update Now from TMDB">
        </form>

        <form method="post" style="margin-top:1em;">
            <?php wp_nonce_field('wp_movies_update_genres_nonce'); ?>
            <input type="submit" name="wp_movies_update_genres"
                   class="button button-secondary"
                   value="Sync Missing Genres from TMDB">
        </form>

        <p>&nbsp;</p>
        <hr>
        <p>&nbsp;</p>

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
    if ( strpos($hook, 'tmdb-movies') === false ) return;
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

// ==============================
// AJAX: Admin - Refresh Movies
// ==============================

add_action('wp_ajax_refresh_movies', 'wp_movies_refresh_movies');
function wp_movies_refresh_movies() {
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized', 403);
    if ( ! check_ajax_referer('refresh_movies_nonce', '_wpnonce', false) ) wp_send_json_error('Nonce verification failed', 403);

    $movies = wp_movies_get_from_db('movie', 8, true);

    wp_send_json_success(['movies' => $movies]);
}

// ===============================
// AJAX: Admin - Refresh TV Shows
// ===============================

add_action('wp_ajax_refresh_tvshows', 'wp_movies_refresh_tvshows');
function wp_movies_refresh_tvshows() {
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized', 403);
    if ( ! check_ajax_referer('refresh_tvshows_nonce', '_wpnonce', false) ) wp_send_json_error('Nonce verification failed', 403);

    $tvshows = wp_movies_get_from_db('tv', 8, true);

    wp_send_json_success(['tvshows' => $tvshows]);
}
