<?php

if ( ! defined('ABSPATH') ) {
    exit;
}

/**
 * Get movies or TV shows by genre (simple fallback version)
 *
 * @param string $genre_slug
 * @param string $type movie|tv
 * @return array
 */
function wp_movies_get_by_genre_smart( $genre_slug, $type = 'movie' ) {

    // Debug logging (helps trace incoming values)
    error_log('GENRE SLUG: ' . $genre_slug);
    error_log('TYPE: ' . $type);

    global $wpdb;

    // Database table name (WordPress prefix + custom table)
    $table = $wpdb->prefix . 'movies';

    // Ensure type is valid (fallback to "movie" if invalid input is given)
    $type = in_array( $type, ['movie', 'tv'], true ) ? $type : 'movie';

    // Normalize genre slug:
    // Converts "sci-fi" → "sci fi" for better database matching
    $genre_search = str_replace('-', ' ', $genre_slug);

    // Query database for matching movies or TV shows
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT tmdb_id, title, poster, release_date
             FROM $table
             WHERE LOWER(genre) LIKE LOWER(%s)
             AND type = %s
             ORDER BY release_date DESC",
            '%' . $genre_search . '%',
            $type
        )
    );

    // Return empty array if no results are found
    if ( ! $results ) {
        return [];
    }

    return $results;
}
