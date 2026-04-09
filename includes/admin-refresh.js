jQuery(document).ready(function($){

    function wp_movies_ajax_fail(xhr, status, error){
        console.error('[WP-MOVIES] AJAX FAILED:', status, error, xhr.responseText);
        $('#refresh-result').html('<strong>Error:</strong> Network or server error.');
    }

    // ==========================
    // UPDATE NOW TMDB BUTTON
    // ==========================
    // AJAX call to update all movies from TMDB
    $('#update-tmdb').on('click', function(e){
        e.preventDefault();
        console.log('Updating TMDB...');
        $.post(wpMoviesAjax.ajax_url, {
            action: 'wp_movies_update_tmdb',
            _wpnonce: wpMoviesAjax.nonce_update_tmdb
        }, function(response){
            if(response.success){
                console.log('[WP-MOVIES] TMDB Update:', response.data.result);
                $('#refresh-result').html('<strong>Success:</strong> TMDB update completed.');
            } else {
                console.error('[WP-MOVIES] TMDB Update error:', response.data);
                $('#refresh-result').html('<strong>Error:</strong> TMDB update failed.');
            }
        })
        .fail(wp_movies_ajax_fail);
    });

    // ==========================
    // SYNC MISSING GENRES BUTTON
    // ==========================
    // AJAX call to sync any missing genres from TMDB
    $('#sync-genres').on('click', function(e){
        e.preventDefault();
        console.log('Syncing genres...');
        $.post(wpMoviesAjax.ajax_url, {
            action: 'wp_movies_sync_genres',
            _wpnonce: wpMoviesAjax.nonce_sync_genres
        }, function(response){
            if(response.success){
                console.log('[WP-MOVIES] Sync Genres:', response.data.result);
                $('#refresh-result').html('<strong>Success:</strong> Genres synced.');
            } else {
                console.error('[WP-MOVIES] Sync Genres error:', response.data);
                $('#refresh-result').html('<strong>Error:</strong> Genre sync failed.');
            }
        })
        .fail(wp_movies_ajax_fail);
    });

    // ==========================
    // REFRESH MOVIES BUTTON
    // ==========================
    // AJAX call to fetch new random movies
    $('#refresh-movies').on('click', function(e){
        e.preventDefault();
        $('#refresh-result').html('<strong>Loading:</strong> Fetching random movies...');
        $.post(wpMoviesAjax.ajax_url, {
            action: 'wp_movies_refresh_movies',
            _wpnonce: wpMoviesAjax.nonce_movies
        }, function(response){
            if(response.success){
                let titles = response.data.movies.map(m => m.title || '(no title)');
                console.log('[WP-MOVIES] Refresh Movies:', titles.join(', '));
                let safeTitles = titles.map(t => $('<div>').text(t).html());
                $('#refresh-result').html('<strong>Movies:</strong> ' + safeTitles.join(', '));
            } else {
                console.error('[WP-MOVIES] Refresh Movies error:', response.data);
                $('#refresh-result').html('<strong>Error:</strong> Failed to refresh movies.');
            }
        })
        .fail(wp_movies_ajax_fail);
    });

    // ==========================
    // REFRESH TV SHOWS BUTTON
    // ==========================
    // AJAX call to fetch new random TV shows
    $('#refresh-tvshows').on('click', function(e){
        e.preventDefault();
        $('#refresh-result').html('<strong>Loading:</strong> Fetching random TV shows...');
        $.post(wpMoviesAjax.ajax_url, {
            action: 'wp_movies_refresh_tvshows',
            _wpnonce: wpMoviesAjax.nonce_tvshows
        }, function(response){
            if(response.success){
                let titles = response.data.tvshows.map(t => t.title || '(no title)');
                console.log('[WP-MOVIES] Refresh TV shows:', titles.join(', '));
                let safeTitles = titles.map(t => $('<div>').text(t).html());
                $('#refresh-result').html('<strong>TV shows:</strong> ' + safeTitles.join(', '));
            } else {
                console.error('[WP-MOVIES] Refresh TV shows error:', response.data);
                $('#refresh-result').html('<strong>Error:</strong> Failed to refresh TV shows.');
            }
        })
        .fail(wp_movies_ajax_fail);
    });
});
