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
    
    global $wpdb;

    // Database table name (WordPress prefix + custom table)
    $table = $wpdb->prefix . 'movies';

    // Ensure type is valid (fallback to "movie" if invalid input is given)
    $type = in_array( $type, ['movie', 'tv'], true ) ? $type : 'movie';

    // Normalize genre slug (e.g. "sci-fi" → "sci fi")
    $genre_search = str_replace('-', ' ', $genre_slug);

    // Map slug to possible genre names in database
    $genre_map = [
        'sci-fi'  => ['Science Fiction', 'Sci-Fi & Fantasy'],
        'action'  => ['Action', 'Action & Adventure'],
        'fantasy' => ['Fantasy', 'Sci-Fi & Fantasy'],
    ];

    // Use mapped genres if available, otherwise fallback to normalized slug
    $genres = $genre_map[$genre_slug] ?? [$genre_search];

    // Build dynamic WHERE clause for multiple genre matches
    $like_clauses = [];
    $values = [];

    foreach ( $genres as $g ) {
        $like_clauses[] = "LOWER(genre) LIKE LOWER(%s)";
        $values[] = '%' . $g . '%';
    }

    // Combine all genre conditions with OR
    $where_genre = implode(' OR ', $like_clauses);

    // Add type as last parameter
    $values[] = $type;

    // Final query
    $query = "
        SELECT tmdb_id, title, poster, release_date
        FROM $table
        WHERE ($where_genre)
        AND type = %s
        ORDER BY release_date DESC
    ";

    // Execute query safely
    $results = $wpdb->get_results(
        $wpdb->prepare( $query, ...$values )
    );

    // Return empty array if no results are found
    if ( ! $results ) {
        return [];
    }

    return $results;
}
