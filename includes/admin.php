<?php
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

    if ($current_screen->taxonomy === 'genre') {
        $parent_file = 'tmdb-movies';
    }

    return $parent_file;
});

// ==========================
// FIX ADMIN SUBMENU ACTIVE
// ==========================

add_filter('submenu_file', function($submenu_file) {
    global $current_screen;

    if ($current_screen->taxonomy === 'genre') {
        $submenu_file = 'edit-tags.php?taxonomy=genre&post_type=movie';
    }

    return $submenu_file;
});
