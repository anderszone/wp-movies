<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================
// ADMIN MENU
// ==========================
add_action('admin_menu', function () {
    add_menu_page(
        'TMDB Movies',
        'TMDB Movies',
        'manage_options',
        'tmdb-movies',
        'wp_movies_admin_page',
        'dashicons-format-video',
        20
    );

    add_submenu_page(
        'tmdb-movies',
        'Update Data',
        'Update Data',
        'manage_options',
        'tmdb-movies',
        'wp_movies_admin_page'
    );

    add_submenu_page(
        'tmdb-movies',
        'Genres',
        'Genres',
        'manage_options',
        'edit-tags.php?taxonomy=genre&post_type=movie'
    );
});

// ==========================
// FIX MENU HIGHLIGHT
// ==========================
add_filter('parent_file', function ($parent_file) {
    $screen = get_current_screen();

    if ( isset($current_screen->taxonomy) && $current_screen->taxonomy === 'genre' ) {
        return 'tmdb-movies';
    }

    return $parent_file;
});

add_filter('submenu_file', function ($submenu_file) {
    $screen = get_current_screen();

    if ( isset($current_screen->taxonomy) && $current_screen->taxonomy === 'genre' ) {
        return 'edit-tags.php?taxonomy=genre&post_type=movie';
    }

    return $submenu_file;
});

// ==========================
// ADMIN PAGE
// ==========================
function wp_movies_admin_page() {
    $notice = isset($_GET['wp_movies_notice']) ? sanitize_key($_GET['wp_movies_notice']) : '';

    $messages = [
        'updated' => ['success','TMDB data in the local database has been updated successfully.'],
        'genres_updated' => ['success','Missing genres were successfully synced from TMDB.'],
        'no_genres' => ['warning','No genres needed updating.']
    ];

?>

<div class="wrap">

<h1><?php esc_html_e('Update Local Database from TMDB','wp-movies'); ?></h1>

<p>
<?php esc_html_e('This will fetch the latest popular movies and TV shows from TMDB and update the local','wp-movies'); ?>
<code>wp_movies</code>
<?php esc_html_e('table.','wp-movies'); ?>
</p>

<?php
if ( $notice && isset($messages[$notice]) ) {
    echo '<div class="notice notice-' . esc_attr($messages[$notice][0]) . ' is-dismissible"><p>' .
         esc_html($messages[$notice][1]) .
         '</p></div>';
}
?>

<form method="post">
<?php wp_nonce_field('wp_movies_update_nonce'); ?>
<input type="submit" name="wp_movies_update" class="button button-primary"
value="<?php echo esc_attr__('Update Now from TMDB','wp-movies'); ?>">
</form>

<form method="post" style="margin-top:1em;">
<?php wp_nonce_field('wp_movies_update_genres_nonce'); ?>
<input type="submit" name="wp_movies_update_genres" class="button button-secondary"
value="<?php echo esc_attr__('Sync Missing Genres from TMDB','wp-movies'); ?>">
</form>

<p>&nbsp;</p><hr><p>&nbsp;</p>

<h2><?php esc_html_e('Randomize Local Data','wp-movies'); ?></h2>

<p>
<?php esc_html_e('These buttons shuffle 8 random movies or TV shows from the local database. For testing / debug only.','wp-movies'); ?>
</p>

<button id="refresh-movies" class="button button-primary">🎬 <?php esc_html_e('Refresh Movies','wp-movies'); ?></button>
<button id="refresh-tvshows" class="button button-secondary">📺 <?php esc_html_e('Refresh TV Shows','wp-movies'); ?></button>

<div id="refresh-result" style="margin-top:20px;"></div>

</div>

<?php
}

// ==========================
// ADMIN JS
// ==========================
add_action('admin_enqueue_scripts', function ($hook) {
    if ( $hook !== 'toplevel_page_tmdb-movies' ) return;

    wp_enqueue_script(
        'wp-movies-admin-js',
        plugin_dir_url(__FILE__) . 'admin-refresh.js',
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script('wp-movies-admin-js','wpMoviesAjax',[
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce_movies' => wp_create_nonce('refresh_movies_nonce'),
        'nonce_tvshows' => wp_create_nonce('refresh_tvshows_nonce')
    ]);
});
