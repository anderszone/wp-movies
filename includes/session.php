<?php
// Start session if not already started
function wp_movies_start_session() {
    if ( session_status() === PHP_SESSION_NONE ) {
        session_start();
    }
}
add_action( 'init', 'wp_movies_start_session' );

// ==========================
// SESSION DEBUG (safe)
// ==========================
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
    error_log( '[WP-MOVIES] SESSION loaded: ' . __FILE__ );
}
