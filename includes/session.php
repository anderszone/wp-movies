<?php
// Start session if not already started
function wp_movies_start_session() {
    if ( session_status() === PHP_SESSION_NONE ) {
        session_start();
    }
}

add_action( 'init', 'wp_movies_start_session' );
