<?php
// ==========================
// ENQUEUE PLUGIN ASSETS
// ==========================

// Load Font Awesome icons for frontend UI
function wp_movies_enqueue_assets() {
    wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0' );
}

add_action( 'wp_enqueue_scripts', 'wp_movies_enqueue_assets' );
