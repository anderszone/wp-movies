<?php
if ( ! defined('ABSPATH') ) exit;

// ==========================
// DEFINE FALLBACK CONSTANTS
// ==========================
// Prevent "undefined constant" warnings in VS Code
if ( ! defined('WP_MOVIES_CONTACT_PAGE_ID') ) define('WP_MOVIES_CONTACT_PAGE_ID', 0);
if ( ! defined('WP_MOVIES_CONTACT_PAGE') )    define('WP_MOVIES_CONTACT_PAGE', 'contact'); // default slug

// ==========================
// ENQUEUE CONTACT FORM JS
// ==========================
function wp_movies_enqueue_contact_validation() {

    // Get current page ID and slug
    $current_page_id   = get_queried_object_id();
    $current_page_slug = $current_page_id ? get_post_field('post_name', $current_page_id) : '';

    // Only enqueue script if we are on the contact page
    if ( $current_page_id === WP_MOVIES_CONTACT_PAGE_ID || $current_page_slug === WP_MOVIES_CONTACT_PAGE ) {
        wp_enqueue_script(
            'wp-movies-contact-validation',
            plugin_dir_url( __FILE__ ) . 'js/contact-validation.js',
            array(),
            '1.0',
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'wp_movies_enqueue_contact_validation' );

// ==========================
// CONTACT FORM SHORTCODE
// ==========================
function wp_movies_contact_form_shortcode() {

    // Status messages
    $status = isset($_GET['form_status']) ? sanitize_text_field($_GET['form_status']) : '';

    // Prefill values
    $name_value = '';
    $email_value = '';
    $message_value = '';

    if ( isset( $_SESSION['contact_form'] ) ) {
        $name_value    = esc_attr( $_SESSION['contact_form']['name'] ?? '' );
        $email_value   = esc_attr( $_SESSION['contact_form']['email'] ?? '' );
        $message_value = esc_textarea( $_SESSION['contact_form']['message'] ?? '' );

        unset( $_SESSION['contact_form'] );
    }

    ob_start();
    ?>

    <div class="wp-movies-contact-form">

        <?php if ( $status === 'success' ) : ?>
            <p class="contact-success" role="status">Message sent successfully!</p>
        <?php elseif ( $status === 'error' ) : ?>
            <p class="contact-error" role="alert">Something went wrong. Please try again.</p>
        <?php endif; ?>

        <form class="wp-movies-form contact-section" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" novalidate>
            <p>
                <label for="name">Your Name</label><br>
                <input type="text" name="name" id="name" value="<?php echo esc_attr($name_value); ?>" required autocomplete="name" aria-required="true">
            </p>

            <p>
                <label for="email">Email</label><br>
                <input type="email" name="email" id="email" value="<?php echo esc_attr($email_value); ?>" required autocomplete="email" aria-required="true">
            </p>

            <p>
                <label for="message">Message</label><br>
                <textarea name="message" id="message" rows="5" required autocomplete="off" aria-required="true"><?php echo esc_textarea($message_value); ?></textarea>
            </p>

            <?php wp_nonce_field('wp_movies_contact_nonce', 'wp_movies_nonce'); ?>
            <input type="hidden" name="action" value="wp_movie_contact">

            <!-- Honeypot -->
            <input type="text" name="website" style="display:none" autocomplete="off">

            <p>
                <button type="submit" class="btn-primary">Send</button>
            </p>
        </form>

    </div>

    <?php
    return ob_get_clean();
}
add_shortcode('wp_movies_contact_form', 'wp_movies_contact_form_shortcode');

// ==========================
// HANDLE CONTACT FORM SUBMISSION
// ==========================
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

    $contact_url = apply_filters(
        'wp_movies_contact_url',
        esc_url( home_url( '/' . WP_MOVIES_CONTACT_PAGE . '/' ) )
    );

    // Validate input
    if ( empty( $name ) || empty( $email ) || empty( $message ) || ! is_email( $email ) ) {

        if ( session_status() === PHP_SESSION_ACTIVE ) {
            $_SESSION['contact_form'] = array(
                'name'    => $name,
                'email'   => $email,
                'message' => $message,
            );
        }

        wp_safe_redirect( add_query_arg( array('form_status' => 'error'), $contact_url ) );
        exit;
    }

    // Email
    $to = sanitize_email( apply_filters('wp_movies_contact_email', 'info@anderswebb.se') );

    $subject = apply_filters(
        'wp_movies_contact_subject',
        __('New message from WP Movies', 'wp-movies')
    );

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
        wp_safe_redirect( add_query_arg( array('form_status' => 'error'), $contact_url ) );
        exit;
    }

    // Clear session
    if ( session_status() === PHP_SESSION_ACTIVE ) {
        unset( $_SESSION['contact_form'] );
    }

    wp_safe_redirect( add_query_arg( array('form_status' => 'success'), $contact_url ) );
    exit;
}

// ==========================
// CONTACT FORM MODULE (DEBUG)
// ==========================
wp_movies_register_module('contact');
