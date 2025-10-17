jQuery(document).ready(function($) {

    $('#refresh-movies').on('click', function() {
        const resultDiv = $('#refresh-result');
        resultDiv.html('<em>Loading new random movies...</em>');

        $.post(wpMoviesAjax.ajax_url, {
            action: 'refresh_movies',
            _wpnonce: wpMoviesAjax.nonce_movies
        }, function(response) {
            if (response.success) {
                const movies = response.data.movies.map(m => m.title).join(', ');
                resultDiv.html('<strong>Movies:</strong> ' + movies);
            } else {
                resultDiv.html('<span style="color:red;">Error loading movies.</span>');
            }
        });
    });

    $('#refresh-tvshows').on('click', function() {
        const resultDiv = $('#refresh-result');
        resultDiv.html('<em>Loading new random TV shows...</em>');

        $.post(wpMoviesAjax.ajax_url, {
            action: 'refresh_tvshows',
            _wpnonce: wpMoviesAjax.nonce_tvshows
        }, function(response) {
            if (response.success) {
                const tvshows = response.data.tvshows.map(t => t.title).join(', ');
                resultDiv.html('<strong>TV Shows:</strong> ' + tvshows);
            } else {
                resultDiv.html('<span style="color:red;">Error loading TV shows.</span>');
            }
        });
    });

});
