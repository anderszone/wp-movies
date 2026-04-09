<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================
// ADMIN MENU
// ==========================
add_action('admin_menu', function () {
    add_menu_page('TMDB Movies','TMDB Movies','manage_options','tmdb-movies','wp_movies_admin_page','dashicons-format-video',20);
    add_submenu_page('tmdb-movies','Update Data','Update Data','manage_options','tmdb-movies','wp_movies_admin_page'); // same as main
    add_submenu_page('tmdb-movies','Genres','Genres','manage_options','edit-tags.php?taxonomy=genre&post_type=movie');
});

// ==========================
// FIX MENU HIGHLIGHT
// ==========================
add_filter('parent_file', 'wp_movies_fix_genre_menu');
add_filter('submenu_file', 'wp_movies_fix_genre_menu');
function wp_movies_fix_genre_menu($file){
    $screen = get_current_screen();
    if(isset($screen->taxonomy) && $screen->taxonomy==='genre'){
        return $screen->id==='edit-tags' ? 'edit-tags.php?taxonomy=genre&post_type=movie' : 'tmdb-movies';
    }
    return $file;
}

// ==========================
// HELPER: AJAX BUTTON
// ==========================
function wp_movies_admin_button($id, $label, $class='button-primary') {
    $nonce = wp_create_nonce('wp_movies_admin_nonce');
    // margin: top 0, right 10px, bottom 10px, left 0
    echo '<button id="'.esc_attr($id).'" class="button '.esc_attr($class).'" data-nonce="'.esc_attr($nonce).'" style="margin:10px 10px 20px 0;">'.esc_html($label).'</button>';
}

// ==========================
// ADMIN PAGE
// ==========================
function wp_movies_admin_page(){
    $notice = isset($_GET['wp_movies_notice']) ? sanitize_key($_GET['wp_movies_notice']) : '';
    $messages = [
        'updated'=>['success','TMDB data in the local database has been updated successfully.'],
        'genres_updated'=>['success','Missing genres were successfully synced from TMDB.'],
        'no_genres'=>['warning','No genres needed updating.']
    ];
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Update Local Database from TMDB','wp-movies'); ?></h1>
        <p><?php esc_html_e('This will fetch the latest popular movies and TV shows from TMDB and update the local','wp-movies'); ?>
        <code>wp_movies</code> <?php esc_html_e('table.','wp-movies'); ?></p>

        <?php if($notice && isset($messages[$notice])){
            echo '<div class="notice notice-'.esc_attr($messages[$notice][0]).' is-dismissible"><p>'.esc_html($messages[$notice][1]).'</p></div>';
        } ?>

        <!-- AJAX Buttons -->
        <?php 
        wp_movies_admin_button('update-tmdb','Update Now from TMDB');
        wp_movies_admin_button('sync-genres','Sync Missing Genres from TMDB','button-secondary');
        echo '<hr>'; 
        ?>       
        
        <h2><?php esc_html_e('Randomize Local Data','wp-movies'); ?></h2>
        <p><?php esc_html_e('These buttons shuffle 8 random movies or TV shows from the local database. For testing / debug only.','wp-movies'); ?></p>

        <?php 
        wp_movies_admin_button('refresh-movies','🎬 '.esc_html__('Refresh Movies','wp-movies'));
        wp_movies_admin_button('refresh-tvshows','📺 '.esc_html__('Refresh TV Shows','wp-movies'),'button-secondary');
        ?>
        <div id="refresh-result" style="margin-top:20px;"></div>
    </div>
    <?php
}

// ==========================
// ADMIN DEBUG MODULE
// ==========================
wp_movies_register_module('admin');
