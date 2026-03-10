<?php
/**
 * Get movies and/or TV shows from the wp_movies table by genre.
 *
 * Supports:
 * - Exact genre matching (column: 'genre')
 * - Multiple types (movie / tv)
 *
 * @param string       $genre_slug Genre slug.
 * @param string|array $type       'movie', 'tv', or array ['movie','tv']. Empty = both.
 * @param int          $limit      Number of results to return.
 * @param string       $orderby    Sort column (release_date, title, random).
 *
 * @return array Database results.
 */

function wp_movies_get_by_genre_smart( $genre_slug, $type = '', $limit = 50, $orderby = 'release_date' ) {
    global $wpdb;

    // Sanitize input
    $genre_slug = sanitize_title( $genre_slug );
    $limit = intval( $limit );
    $orderby = strtolower( $orderby );

    if ( $limit <= 0 ) {
        $limit = 50;
    }

    // Create LIKE pattern for genre
    $genre_like = '%' . strtolower( trim( $genre_slug ) ) . '%';

    $sql = "
        SELECT * FROM {$wpdb->prefix}movies
        WHERE LOWER(genre) LIKE %s
    ";
    $params = array( $genre_like );

    // Filter by type (movie / tv)
    if ( ! empty( $type ) ) {
        if ( is_array( $type ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $type ), '%s' ) );
            $sql .= " AND type IN ($placeholders)";
            $params = array_merge( $params, $type );
        } else {
            $sql .= " AND type = %s";
            $params[] = $type;
        }
    }

    // Sorting logic
    if ( $orderby === 'random' || $orderby === 'rand()' ) {

        $sql .= " ORDER BY RAND()";
    } else {
        // Prevent SQL injection with whitelist
        $allowed_orderbys = array( 'release_date', 'title' );
        $orderby_safe = in_array( $orderby, $allowed_orderbys ) ? $orderby : 'release_date';

        $sql .= " ORDER BY {$orderby_safe} DESC";
    }

    // Limit results
    $sql .= " LIMIT %d";
    $params[] = $limit;

    $query = $wpdb->prepare( $sql, $params );
    return $wpdb->get_results( $query );
}
