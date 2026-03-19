<?php
// ================================
// REGISTER MOVIE CUSTOM POST TYPE
// ================================
function wp_movie_register_post_type() {
    register_post_type( 'movie', array(
        'labels' => array(
            'name'               => __( 'Movies' ),
            'singular_name'      => __( 'Movie' ),
            'add_new'            => __( 'Add New Movie', 'wp-movie-website' ),
            'add_new_item'       => __( 'Add New Movie', 'wp-movie-website' ),
            'edit_item'          => __( 'Edit Movie', 'wp-movie-website' ),
            'new_item'           => __( 'New Movie', 'wp-movie-website' ),
            'view_item'          => __( 'View Movie', 'wp-movie-website' ),
            'search_items'       => __( 'Search Movies', 'wp-movie-website' ),
            'not_found'          => __( 'No movies found', 'wp-movie-website' ),
            'not_found_in_trash' => __( 'No movies found in Trash', 'wp-movie-website' ),
            'all_items'          => __( 'All Movies', 'wp-movie-website' ),
            'menu_name'          => __( 'Movies', 'wp-movie-website' ),
            'name_admin_bar'     => __( 'Movie', 'wp-movie-website' ),
        ),

        'public' => true,
        'has_archive' => true,
        'show_in_menu' => false,
        'rewrite' => array(
            'slug' => 'movies-archive'
        ),

        'show_in_rest' => true,

        'supports' => array(
            'title',
            'editor',
            'thumbnail',
            'excerpt'
        ),

        'menu_icon' => 'dashicons-format-video',
    ) );
}

// ================================
// REGISTER GENRE TAXONOMY
// ================================
function wp_movie_register_taxonomies() {
    register_taxonomy(
        'genre',
        'movie',

        array(
            'label' => __( 'Genres', 'wp-movie-website' ),

            'labels' => array(
                'name'              => _x( 'Genres', 'taxonomy general name', 'wp-movie-website' ),
                'singular_name'     => _x( 'Genre', 'taxonomy singular name', 'wp-movie-website' ),
                'search_items'      => __( 'Search Genres', 'wp-movie-website' ),
                'all_items'         => __( 'All Genres', 'wp-movie-website' ),
                'parent_item'       => __( 'Parent Genre', 'wp-movie-website' ),
                'parent_item_colon' => __( 'Parent Genre:', 'wp-movie-website' ),
                'edit_item'         => __( 'Edit Genre', 'wp-movie-website' ),
                'update_item'       => __( 'Update Genre', 'wp-movie-website' ),
                'add_new_item'      => __( 'Add New Genre', 'wp-movie-website' ),
                'new_item_name'     => __( 'New Genre Name', 'wp-movie-website' ),
                'menu_name'         => __( 'Genres', 'wp-movie-website' ),
            ),

            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'hierarchical'      => true,
            'show_in_rest'      => true,

            'rewrite' => array(
                'slug' => 'genre'
            ),
        )
    );
}

// ================================
// HOOKS
// ================================
add_action( 'init', 'wp_movie_register_post_type' );
add_action( 'init', 'wp_movie_register_taxonomies' );

// ==========================
// CUSTOM POST TYPE for DEBUG
// ==========================
wp_movies_register_module('cpt');
