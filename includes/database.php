<?php
if (!defined('ABSPATH')) exit;

// ==========================
// TMDB GENRES
// ==========================
define('WP_MOVIES_GENRES_MOVIE', [
    28=>'Action',12=>'Adventure',16=>'Animation',35=>'Comedy',80=>'Crime',
    99=>'Documentary',18=>'Drama',10751=>'Family',14=>'Fantasy',36=>'History',
    27=>'Horror',10402=>'Music',9648=>'Mystery',10749=>'Romance',878=>'Science Fiction',
    10770=>'TV Movie',53=>'Thriller',10752=>'War',37=>'Western'
]);
define('WP_MOVIES_GENRES_TV', [
    10759=>'Action & Adventure',16=>'Animation',35=>'Comedy',80=>'Crime',99=>'Documentary',
    18=>'Drama',10751=>'Family',10762=>'Kids',9648=>'Mystery',10763=>'News',
    10764=>'Reality',10765=>'Sci-Fi & Fantasy',10766=>'Soap',10767=>'Talk',10768=>'War & Politics',
    37=>'Western'
]);

// ==========================
// DATABASE TABLE
// ==========================
register_activation_hook(__FILE__, 'wp_movies_create_table_if_not_exists');
function wp_movies_create_table_if_not_exists() {
    global $wpdb;
    $t=$wpdb->prefix.'movies';
    $sql="CREATE TABLE $t (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tmdb_id bigint(20) NOT NULL,
        title text NOT NULL,
        genre varchar(255) DEFAULT NULL,
        poster varchar(255) DEFAULT '' NOT NULL,
        release_date date DEFAULT NULL,
        type varchar(20) DEFAULT 'movie' NOT NULL,
        PRIMARY KEY(id),
        UNIQUE KEY tmdb_id(tmdb_id),
        KEY type (type)
    ) ".$wpdb->get_charset_collate().";";
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    delete_transient(WP_MOVIES_TRANSIENT_MOVIE);
    delete_transient(WP_MOVIES_TRANSIENT_TV);
}

// ==========================
// FETCH FROM TMDB
// ==========================
function wp_movies_fetch_from_tmdb($type='movie'){
    $type=($type==='tv')?'tv':'movie';
    $tr=($type==='tv')?WP_MOVIES_TRANSIENT_TV:WP_MOVIES_TRANSIENT_MOVIE;
    if(($c=get_transient($tr))!==false) return $c;
    if(!defined('TMDB_API_KEY')||!constant('TMDB_API_KEY')){wp_movies_log('TMDB_API_KEY missing.','error'); return false;}
    $url="https://api.themoviedb.org/3/$type/popular?api_key=".constant('TMDB_API_KEY')."&language=en-US&page=1";
    $res=wp_remote_get($url,['timeout'=>15]);
    if(is_wp_error($res)){wp_movies_log('TMDB request failed: '.$res->get_error_message(),'error'); return false;}
    $code=wp_remote_retrieve_response_code($res);
    if($code===429||$code!==200){wp_movies_log("TMDB HTTP status $code",'error'); return false;}
    $data=json_decode(wp_remote_retrieve_body($res));
    if(json_last_error()!==JSON_ERROR_NONE||empty($data->results)){wp_movies_log('TMDB JSON decode error or missing results.','error'); return false;}
    set_transient($tr,$data->results,HOUR_IN_SECONDS);
    return $data->results;
}

function wp_movies_get_tmdb_movie_details($id,$key,$lang='en-US'){
    $res=wp_remote_get("https://api.themoviedb.org/3/movie/$id?api_key=".urlencode($key)."&language=".urlencode($lang));
    if(is_wp_error($res)){wp_movies_log('TMDB API Error: '.$res->get_error_message(),'error'); return false;}
    $data=json_decode(wp_remote_retrieve_body($res));
    if(json_last_error()!==JSON_ERROR_NONE||empty($data)||isset($data->status_code)){wp_movies_log('TMDB API response error.','error'); return false;}
    return $data;
}

// ==========================
// SAVE TO DB
// ==========================
function wp_movies_save_tmdb_movie($m){
    global $wpdb;
    if(!isset($m->id,$m->title,$m->release_date)){wp_movies_log('Missing required fields','error'); return false;}
    $t=$wpdb->prefix.'movies';
    $g=!empty($m->genres)?implode(', ',array_map(fn($x)=>$x->name,$m->genres)):($m->genre??'');
    $p=$m->poster_path??'';
    if($ex=$wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE tmdb_id=%d",$m->id))){
        $r=$wpdb->update($t,['title'=>$m->title,'genre'=>$g,'poster'=>$p,'release_date'=>$m->release_date,'type'=>'movie'],['tmdb_id'=>$m->id],['%s','%s','%s','%s','%s'],['%d']);
        if($r===false){wp_movies_log('DB update error: '.$wpdb->last_error); return false;}
        wp_movies_log("Movie {$m->id} updated."); return $ex;
    }
    $r=$wpdb->insert($t,['tmdb_id'=>$m->id,'title'=>$m->title,'genre'=>$g,'poster'=>$p,'release_date'=>$m->release_date,'type'=>'movie'],['%d','%s','%s','%s','%s','%s']);
    if($r===false){wp_movies_log('DB insert error: '.$wpdb->last_error); return false;}
    return $wpdb->insert_id;
}

function wp_movies_save_to_db($items,$type='movie'){
    if(empty($items) || !is_array($items)){
        wp_movies_log('Invalid items array','error');
        return;
    }
    global $wpdb; $t=$wpdb->prefix.'movies';
    foreach($items as $i){
        if(empty($i->id)) continue;
        $g=isset($i->genre_ids)?implode(', ',wp_movies_get_genre_names_from_ids($i->genre_ids,$type)):(isset($i->genres)?implode(', ',array_column($i->genres,'name')):'');
        $wpdb->replace($t,[
            'tmdb_id'=>$i->id,
            'title'=>$i->title ?? ($i->name ?? ''),
            'poster'=>$i->poster_path??'',
            'release_date'=>$i->release_date ?? ($i->first_air_date ?? ''),
            'genre'=>$g,
            'type'=>$type
        ],['%d','%s','%s','%s','%s','%s'])?:wp_movies_log('DB replace error: '.$wpdb->last_error);
    }
}

// ==========================
// HELPERS
// ==========================
function wp_movies_get_genre_names_from_ids($ids,$type='movie'){
    $map=($type==='tv')?WP_MOVIES_GENRES_TV:WP_MOVIES_GENRES_MOVIE; $names=[];
    foreach($ids as $id) isset($map[$id])?$names[]=$map[$id]:wp_movies_log("Unknown genre ID $id for $type",'warning');
    return $names;
}

// ==========================
// FETCH & SAVE ALL
// ==========================
function wp_movies_fetch_and_save(){
    delete_transient(WP_MOVIES_TRANSIENT_MOVIE); delete_transient(WP_MOVIES_TRANSIENT_TV);
    if($m=wp_movies_fetch_from_tmdb('movie')) wp_movies_save_to_db($m,'movie'); else wp_movies_log("Failed to fetch movies",'error');
    if($tv=wp_movies_fetch_from_tmdb('tv')) wp_movies_save_to_db($tv,'tv'); else wp_movies_log("Failed to fetch TV shows",'error');
}

// ==========================
// FETCH FROM DB
// ==========================
function wp_movies_get_from_db($type='movie', $limit=8, $random=false){
    global $wpdb;
    $t = $wpdb->prefix . 'movies';
    $q = $random
        ? $wpdb->prepare(
            "SELECT * FROM $t WHERE type=%s LIMIT %d OFFSET %d",
            $type,
            $limit,
            max(0, rand(0, max(0, $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $t WHERE type=%s", $type)) - $limit)))
        )
        : $wpdb->prepare("SELECT * FROM $t WHERE type=%s ORDER BY id DESC LIMIT %d", $type, $limit);
    return $wpdb->get_results($q);
}

// ==========================
// UPDATE MISSING GENRES
// ==========================
function wp_movies_update_missing_genres(){
    global $wpdb; if(!defined('TMDB_API_KEY')){wp_movies_log('TMDB_API_KEY missing.'); return [];}
    $t=$wpdb->prefix.'movies'; $rows=$wpdb->get_results("SELECT * FROM $t WHERE genre IS NULL OR genre=''"); $upd=[];
    if(!$rows){wp_movies_log("No genre updates."); return $upd;}
    foreach($rows as $r){
        $res=wp_remote_get("https://api.themoviedb.org/3/{$r->type}/{$r->tmdb_id}?api_key=".constant('TMDB_API_KEY')."&language=en-US");
        if(is_wp_error($res)){wp_movies_log("TMDB request failed for {$r->tmdb_id}"); continue;}
        $data=json_decode(wp_remote_retrieve_body($res)); if(empty($data->genres)){wp_movies_log("No genres for {$r->tmdb_id}"); continue;}
        $g=implode(', ',array_map(fn($x)=>$x->name,$data->genres));
        $wpdb->update($t,['genre'=>$g],['id'=>$r->id],['%s'],['%d'])!==false?$upd[]=['title'=>$r->title,'type'=>$r->type,'genres'=>$g]:wp_movies_log("Failed update for {$r->tmdb_id}");
        wp_movies_log("Genre updated: {$r->title} ({$r->type}) – {$g}");
    }
    wp_movies_log(!empty($upd)?"Total updated: ".count($upd):"No rows updated.");
    return $upd;
}

// ==========================
// ADMIN ACTIONS
// ==========================
add_action('admin_init','wp_movies_handle_admin_actions');
function wp_movies_handle_admin_actions(){
    if(!isset($_GET['page'])||$_GET['page']!=='update-tmdb-data') return;
    if(isset($_POST['wp_movies_update'])&&check_admin_referer('wp_movies_update_nonce')){wp_movies_fetch_and_save(); wp_redirect(add_query_arg(['wp_movies_notice'=>'updated'],admin_url('tools.php?page=update-tmdb-data'))); exit;}
    if(isset($_POST['wp_movies_update_genres'])&&check_admin_referer('wp_movies_update_genres_nonce')){$u=wp_movies_update_missing_genres(); wp_redirect(add_query_arg(['wp_movies_notice'=>empty($u)?'no_genres':'genres_updated'],admin_url('tools.php?page=update-tmdb-data'))); exit;}
}

// ==========================
// DATABASE DEBUG (safe)
// ==========================
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
    error_log( '[WP-MOVIES] DATABASE loaded: ' . __FILE__ );
}
