<?php
/**
 * Handle contact form submission
 */

// Handle form actions
add_action( 'admin_post_nopriv_wp_movie_contact', 'wp_movie_handle_contact' );
add_action( 'admin_post_wp_movie_contact', 'wp_movie_handle_contact' );

function wp_movie_handle_contact() {

    // Verify nonce
    if (
        ! isset( $_POST['wp_movie_nonce'] ) ||
        ! wp_verify_nonce( $_POST['wp_movie_nonce'], 'wp_movie_contact_action' )
    ) {
        wp_die( 'Security check failed.' );
    }

    // Sanitize input
    $name    = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
    $email   = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
    $message = isset( $_POST['message'] ) ? trim( sanitize_textarea_field( $_POST['message'] ) ) : '';

    $contact_url = home_url( '/contact/' );

    // Validate input
    if ( empty( $name ) || empty( $email ) || empty( $message ) || ! is_email( $email ) ) {

        // Store values in session
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            $_SESSION['contact_form'] = array(
                'name'    => $name,
                'email'   => $email,
                'message' => $message,
            );
        }

        $redirect_url = add_query_arg(
            array(
                'form_status' => 'error',
            ),
            $contact_url
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    // Email address
    $to = sanitize_email( 'info@anderswebb.se' );

    $subject = 'New message from WP Movies';

    $body  = "Name: {$name}\n";
    $body .= "Email: {$email}\n\n";
    $body .= "Message:\n{$message}";

    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: Anders Webb <info@anderswebb.se>',
        'Reply-To: ' . $email,
    );

    $mail_sent = wp_mail( $to, $subject, $body, $headers );

    if ( ! $mail_sent ) {

        $redirect_url = add_query_arg(
            array(
                'form_status' => 'error',
            ),
            $contact_url
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }

    // Clear stored form data after success
    if ( session_status() === PHP_SESSION_ACTIVE ) {
        unset( $_SESSION['contact_form'] );
    }

    // Redirect with success
    $redirect_url = add_query_arg(
        array(
            'form_status' => 'success',
        ),
        $contact_url
    );

    wp_safe_redirect( $redirect_url );
    exit;
}
