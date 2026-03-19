<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Handle contact form submission
 */

// Handle form actions
add_action( 'admin_post_nopriv_wp_movie_contact', 'wp_movies_handle_contact' );
add_action( 'admin_post_wp_movie_contact', 'wp_movies_handle_contact' );

function wp_movies_handle_contact() {
    // Verify nonce
    if (
        ! isset( $_POST['wp_movies_nonce'] ) ||
        ! wp_verify_nonce( $_POST['wp_movies_nonce'], 'wp_movies_contact_nonce' )
    ) {
        wp_die( esc_html__( 'Security check failed.', 'wp-movies' ) );
    }

    // Honeypot spam protection
    if ( ! empty( $_POST['website'] ) ) {
        wp_die( esc_html__( 'Spam detected.', 'wp-movies' ) );
    } 

    // Sanitize input
    $name    = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
    $email   = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
    $message = isset( $_POST['message'] ) ? trim( sanitize_textarea_field( $_POST['message'] ) ) : '';

    $contact_url = esc_url( home_url( '/contact/' ) );

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

    $subject = __('New message from WP Movies', 'wp-movies');

    $body  = "Name: {$name}\n";
    $body .= "Email: {$email}\n\n";
    $body .= "Message:\n{$message}";

    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: Anders Webb <info@anderswebb.se>',
        'Reply-To: ' . sanitize_email( $email ),
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

// ==========================
// CONTACT FORM DEBUG
// ==========================
wp_movies_register_module('contact');
