<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// we may need a http proxy for the fetch. Should be set from the admin page. 
// define ('http_proxy', 'http://example.org:8080'); 
// we'll set this as category => proxy, link => link, active => 0; 
// in the admin page, all entries will be displayed with a checkbox for activating, deactivating, deleting. 



/**
 * The worker gets called by the WP scheduler hourly. 
 * POST: simulates or performs a real POST. 
 *
 * @since 0.1.0
 *
 */

function gcal_import_worker()
{
    error_log ("gcal_import_worker started", 0);
    error_log ("und wieder raus.");
    return (0);

    /*
     * retrieve the proxy from the db, and if it exists, construct a context. 
     * TODO: USER:PASS in DB. 
     *
     * http://www.pirob.com/2013/06/php-using-getheaders-and-filegetcontents-functions-behind-proxy.html


     * http://ubuntu1804/site/wp-admin/post.php?post=128&action=edit
     * vi +809 ./wp-admin/includes/post.php function wp_write_post()
     
     */

    global $wpdb;
    $table = $wpdb->prefix.GCAL_TABLE;
    $categories = $wpdb->get_results("SELECT gcal_category, gcal_link from $table WHERE gcal_active = '1'");
    if ($wpdb->num_rows == 0) {
        error_log ("keine Einträge in $wpdb->prefix.GCAL_TABLE gefunden.");
        return (0);
    }
    $file = dirname (__FILE__) . '/categories.txt'; 
    file_put_contents ($file, var_export ($categories, TRUE)); 

    foreach ( $categories as $category) {
        error_log ("found category $category->gcal_category");
        $table = $wpdb->prefix . 'postmeta';
    	$post_ids = $wpdb->get_results("SELECT post_id FROM $table WHERE
    	        meta_key = '_gcal_category' AND meta_value = '$category->gcal_category'"); 

// die ganze Logik ist Mist, weil immer wieder die gleichen termine gelöscht und neu angelegt werden, was Last auf WP bringt. 
// besser: wir merken uns je Kalendereintrag die Google-UID und
// - wenn der Eintrag schon existiert: nur updaten
// - wenn nicht, neu anlegen
// - wenn im Reply ein vorhandener Termin nicht vorkommt, wurde er wohl gelöscht, und raus damit. 
// d.h. wir merken uns in postmeta zusätzlich die _gcal_uid. 

    	foreach ($post_ids as $post_id) {
            error_log ("trashing post_id $post_id->post_id");
    		wp_trash_post($post_id->post_id); // should we DELETE here? 
    	}
    	// jetzt die neuen Posts anlegen
    	gcal_import_do_import($category->gcal_category, $category->gcal_link);
    }	    

    error_log ("gcal_import_worker finished", 0);
}	

add_action( 'gcal_import_worker_hook', 'gcal_import_worker' );



function gcal_import_geocity($location) {

    // Wenn die Adresse im Feld Stadt steht, wird sie richtig angezeigt, ergo:
    return ($location); 

}


function gcal_import_geoshow($location) {

    // later
    return ''; 

}


/*
function getHttpCode($http_response_header)
{
    if(is_array($http_response_header))
    {
        $parts=explode(' ',$http_response_header[0]);
        if(count($parts)>1) //HTTP/1.0 <code> <text>
            return intval($parts[1]); //Get code
    }
    return 0;
}
*/


function gcal_import_geocode($location) {

    error_log ("entering gcal_import_geocode ($location)");
    // we try to cache results as we will need many times the same results especially for recurring events.
    // we will use a hash for the location because the hash has a fixed length, while the location has not. 
    // This table will grow indefinitely over time, so we need to add a timestamp field and remove 
    // entries that are older than, say, 30 days each time. 
    // this will also cope with Google subtly changing location strings in Maps over time. 
    // new entries will thus replace outdated ones over time. 
    global $wpdb;
    $table = $wpdb->prefix.GCAL_GEO_TABLE;
/*
    // CREATE table if it does not exist already. 
    $query = "CREATE TABLE IF NOT EXISTS $table (
        id INT(9) NOT NULL AUTO_INCREMENT,
        gcal_geo_hash VARCHAR(40) NOT NULL,
        gcal_geo_lat VARCHAR(20) NOT NULL,
        gcal_geo_lon VARCHAR(20) NOT NULL,
        gcal_geo_timestamp DATETIME NOT NULL,
	    UNIQUE KEY id (id)
    );";
    $wpdb->query($query);
*/

    $hash = hash ('md5', $location); 
    $query = "SELECT gcal_geo_lat, gcal_geo_lon FROM $table WHERE gcal_geo_hash = '$hash'";
    error_log ("gcal_import_geocode looking up hash $hash location $location");
    error_log ("query: $query");
    $result = $wpdb->get_row( $query, ARRAY_N );
    $file = dirname (__FILE__) . "/$hash-lookup-result.txt";
    file_put_contents ($file, var_export ($result, TRUE));
    if ( $wpdb->num_rows == 1 ) { // it should only be a single row! 
        error_log ("gcal_import_geocode found hash $hash lat $result[0] lon $result[1]");
        return ($result);
    } else {    

        // do the housekeeping first, before we create a new caching entry. 
        $outdated = time() - 2592000; // 30 Tage
        $query = "DELETE FROM $table WHERE gcal_geo_timestamp < $outdated";
        $wpdb->query($query);

        $attempts = 0;
        $success = false;
        // let's be a mobile Firefox Klar browser just for fun. 
        $useragent = "User-Agent: Mozilla/5.0 (Android 7.0; Mobile; rv:62.0) Gecko/62.0 Firefox/62.0";
        // we'll need to be easy with GMaps in order no to get a 429 Too Many Requests. 
        while ($success == false && $attempts < 3) {
            // @ = 'ignore_errors' => TRUE
            $url = "https://maps.google.com/maps?q=" . urlencode ($location);
/*
            // we use curl instead of file_get_contents because curl does many high level things e.g. redirects and cookies
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            // but Google does not seem to like the useragent. The result is crap. 
            // curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
            // später können wir noch einen proxy einbauen: 
            // curl_setopt($ch, CURLOPT_PROXY, $proxy);
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
*/
            // we use wp-remote.* instead of file_get_contents because it does many high level things e.g. redirects
            $response = wp_remote_get($url);
            $result = wp_remote_retrieve_body($response);
            $http_code = wp_remote_retrieve_response_code($response);
            if (200 == $http_code) {
                $success = true;
            } elseif (429 == $http_code) {
                time.sleep(2);  
                ++$attempts; 
                error_log ("got $attempts HTTP 429 Too Many Requests on $url");
            } else {
                error_log ("Ärgerlicher HTTP Fehler $http_code");
                return array (' ', ' ');
            }
        }
    
        // ok so $result seems to be valid.
        $file = dirname (__FILE__) . "/$hash-result.html";
        file_put_contents ($file, $result);
        // and now we need to look for:
        $pattern = '#www.google.com/maps/preview/place/[^/]+/@([\d\.]+),([\d\.]+),.*#';
        preg_match ($pattern, $result, $matches);
        $file = dirname (__FILE__) . "/$hash-matches.html";
        file_put_contents ($file, var_export ($matches, TRUE));
        error_log ("gcal_import_geocode geocoded lat=$matches[1] lon=$matches[2] for hash $hash");

        // do the caching now, but only if both values are set. 
        // $wpdb_insert does all the sanitizing for us. 
        if ($matches[1] != "" && $matches[2] != "") {
            $wpdb->insert($table, array(
                'gcal_geo_location' => substr( $location, 0, 128 ),
                'gcal_geo_hash' => $hash,
                'gcal_geo_lat' => $matches[1],
                'gcal_geo_lon' => $matches[2], 
                'gcal_geo_timestamp' => time(),
            ));
        }

        // error handling? 
        // and return the result: 
        return array ($matches[1], $matches[2]); 
    }
}


function gcal_import_do_import($category, $link) {

    error_log ("entering gcal_import_do_import($category, $link)");
   
	require_once dirname (__FILE__) . '/icalparser/src/IcalParser.php';
	require_once dirname (__FILE__) . '/icalparser/src/Recurrence.php';
	require_once dirname (__FILE__) . '/icalparser/src/Freq.php';
	require_once dirname (__FILE__) . '/icalparser/src/WindowsTimezones.php';

	$cal = new \om\IcalParser();
	$results = $cal->parseFile($link);

    $file = dirname (__FILE__) . "/cal-$category-parsed.txt";
    file_put_contents ($file, var_export($results, TRUE));

    // we must set a current user because we may not be logged in. 
    $user_id = 1;
    $user = get_user_by( 'id', $user_id ); 
    if( $user ) {
        wp_set_current_user( $user_id, $user->user_login );
        wp_set_auth_cookie( $user_id );
    }
	foreach ($cal->getSortedEvents() as $r) {
        // wenn DTEND in der Vergangenheit liegt, nicht mehr posten. Next. 
        $now = new DateTime(); 
//        $dtend = new DateTime($r['DTEND']);
        $summary = $r['SUMMARY'];
        $dtstart = $r['DTSTART']->format('d.m.Y H:i');
        if ($r['DTEND'] < $now) {
            error_log ("not posting expired event $summary on $dtstart");
            continue;
        } else {
            error_log ("processing $summary on $dtstart");
        }

        // The zeitstempel. No idea what it's for, but kal3000 seems to use it. 
        $wpc_from = $r['DTSTART']->format('d.m.Y H:i');
        // code borrowed from kal3000_termine_save_postdata which will not be invoked. 
        $zeitstempel = strftime( strToTime( $wpc_from ) );
        if(!$zeitstempel) {
                // strftime doesn't seem to work, so let's get creative
                preg_match("/([0-9]{1,2}).\s(\w{1,})\s([0-9]{4})\s([0-9]{2}):([0-9]{2})/", $wpc_from, $zeitstempel);
                
                $month_number = "";
                for($i=1;$i<=12;$i++){
                        if(strtolower(date_i18n("F", mktime(0, 0, 0, $i, 1, 0))) == strtolower($zeitstempel[2])){
                                $month_number = $i;
                                break;
                        }
                }

                $zeit = mktime($zeitstempel[4], $zeitstempel[5], 0, $month_number, $zeitstempel[1], $zeitstempel[3]);
                $zeitstempel = date_i18n('U', $zeit);
        }

        // geocoden
        $location = urldecode ($r['LOCATION']);
        error_log ("invoking gcal_import_geocode for $location");
        $my_latlon = gcal_import_geocode($location);

        // create a default form
//         $post = get_default_post_to_edit ('termine', false);
        $post_type = 'termine';

        // why can't I simply call get_default_post_to_edit? This gives an undefined function error! 
        $post = new stdClass;
        $post->ID = 0;
        $post->post_author = '';
        $post->post_date = '';
        $post->post_date_gmt = '';
        $post->post_password = '';
        $post->post_name = '';
        $post->post_type = $post_type;
        $post->post_status = 'draft';
        $post->to_ping = '';
        $post->pinged = '';
        $post->comment_status = get_default_comment_status( $post_type );
        $post->ping_status = get_default_comment_status( $post_type, 'pingback' );
        $post->post_pingback = get_option( 'default_pingback_flag' );
        $post->post_category = get_option( 'default_category' );
        $post->page_template = 'default';
        $post->post_parent = 0;
        $post->menu_order = 0;
        $post = new WP_Post( $post );
        $post->post_content = apply_filters( 'default_content', $post_content, $post );
        $post->post_title = apply_filters( 'default_title', $post_title, $post );
        $post->post_excerpt = apply_filters( 'default_excerpt', $post_excerpt, $post );


        $file = dirname (__FILE__) . '/' . 'post-defaults.txt';
        file_put_contents ( $file, var_export ($post, TRUE) );

// TODO: 
        if ( isset($r['ATTACH']) ) {
            // create image attachment and associate with new post
            $attach = $r['ATTACH'];
            $summary = $r['SUMMARY'];
            error_log ("gcal_import_do_import found attachment $attach for $summary");
        }

        // and fill in the post form
        $post->post_author = '1';
        $post->post_content = $r['DESCRIPTION'];
        $post->post_title = $r['SUMMARY'];
        // create an excerpt for the overview page ([wpcalendar kat=...])
        if (strlen ($r['DESCRIPTION']) > 160) {
            $post->post_excerpt = substr ($r['DESCRIPTION'], 0, 160) . ' ...'; // first 160 chars of DESCRIPTION plus ' ...'
        } else {
            $post->post_excerpt = $r['DESCRIPTION'];
        }
        $post->post_status = 'publish';
        $post->post_category = array ($category,); 
        // sanitized title. We will add a timestamp to enable recurring events
        // this is not handled properly by wp_insert_post - recurring events would all have the same post_name. 
        // $post->post_name = $r['DTSTART']->format('Y-m-d-H-i') . '-' . strtolower( urlencode($r['SUMMARY']) ) ; 
        $post->visibility = 'public';

        // now the wpcalendar metas. 
        $post->meta_input = array(
            '_wpcal_from' => $r['DTSTART']->format('d.m.Y H:i'),
            '_bis' => $r['DTEND']->format('d.m.Y H:i'),
            '_geocity' => gcal_import_geocity($r['LOCATION']),
            '_geoshow' => gcal_import_geoshow($r['LOCATION']),
            '_lat' => $my_latlon[0],
            '_lon' => $my_latlon[1],
            '_zoom' => '10',
            '_veranstalter' => '',
            '_veranstalterlnk' => '',
            '_zeitstempel' => $zeitstempel,
            '_gcal_category' => $category,
        );

        // debug
        $file = dirname (__FILE__) . '/' . $post->post_name . '-finished.txt';
        file_put_contents ( $file, var_export ($post, TRUE) );

        $post_id = wp_insert_post( $post, false );
        error_log ("posted new post $post_id");
        // return ($post_id);
	}
}  


