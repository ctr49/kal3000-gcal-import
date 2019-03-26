<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

function gcal_import_geocity($location) {

    // Wenn die Adresse im Feld Stadt steht, wird sie richtig angezeigt, ergo:
    return ($location); 

}

function gcal_import_geoshow($location) {

    // later
    return ''; 

}


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

define ('GCAL_GEO_TABLE', 'gcal_import_geocache');

function gcal_import_geocode($location) {

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
    $query = "SELECT gcal_geo_lat, gcal_geo_lon FROM $table WHERE gcal_geo_hash = $hash";
    error_log ("gcal_import_geocode looking up hash $hash location $location");
    $result = $wpdb->get_results($query);
    if ( ! empty ($result) ) {
        error_log ("gcal_import_geocode found hash $hash lat $result[0] lon $result[1]");
        return ($result);
    } else {    

        // do the housekeeping first, before we create a new caching entry. 
        $outdated = DateTime('NOW')->sub( new DateInterval('P30D') );
        $query = "DELETE FROM $table WHERE gcal_geo_timestamp < $outdated";
        $wpdb->query($query);

        $attempts = 0;
        $success = false;
        // let's be a mobile Firefox Klar browser just for fun. 
        $opts = array('http' =>
            array(
                'method' => "GET",
                'header' => "User-Agent: Mozilla/5.0 (Android 7.0; Mobile; rv:62.0) Gecko/62.0 Firefox/62.0",
            )
        );
        $context  = stream_context_create($opts);
        // we'll need to be easy with GMaps in order no to get a 429 Too Many Requests. 
        while ($success == false && $attempts < 3) {
            // @ = 'ignore_errors' => TRUE
            $url = 'https://maps.google.com/maps?q=' . urlencode ($location);
            $result = file_get_contents ($url, false, $context);
            if (429 == getHttpCode($http_response_header)) {
                time.sleep(2);  
                error_log ("got a HTTP 429 Too Many Requests on $url");
                ++$attempts; 
                continue;
            } else {
                $success = true;
            }
        }
    
        // bail gracefully if the fetch did not work for any reason
        if ($result === FALSE) { 
            return array ("", "");
        } else {
            // and now we need to look for:
            $pattern = '#www.google.com/maps/preview/place/[^/]+/@([\d\.]+),([\d\.]+),.*#';
            preg_match ($pattern, $result, $matches);
            error_log ("gcal_import_geocode geocoded lat $matches[1] lon $matches[2] for hash $hash");

            // do the caching now. 
            // $wpdb_insert does all the sanitizing for us. 
            $wpdb->insert($table, array(
                'gcal_geo_location' => substr( $location, 0, 128 ),
                'gcal_geo_hash' => $hash,
                'gcal_geo_lat' => $matches[1],
                'gcal_geo_lon' => $matches[2], 
                'gcal_geo_timestamp' => DateTime('NOW'),
            ));

            // error handling? 
            // and return the result: 
            return array ($matches[1], $matches[2]); 
        }
    }
}


function gcal_import_do_import($category, $link) {

    // global $_POST;
    $post = array();
   
	require_once dirname (__FILE__) . '/../../icalparser/src/IcalParser.php';
	require_once dirname (__FILE__) . '/../../icalparser/src/Recurrence.php';
	require_once dirname (__FILE__) . '/../../icalparser/src/Freq.php';
	require_once dirname (__FILE__) . '/../../icalparser/src/WindowsTimezones.php';

	$cal = new \om\IcalParser();
	$results = $cal->parseFile($link);

    // we must set a current user because we may not be logged in. 
    $user_id = 1;
    $user = get_user_by( 'id', $user_id ); 
    if( $user ) {
        wp_set_current_user( $user_id, $user->user_login );
        wp_set_auth_cookie( $user_id );
    }
	foreach ($cal->getSortedEvents() as $r) {
        // wenn DTEND in der Vergangenheit liegt, nicht mehr posten. Next. 
        if (DateTime($r['DTEND']) < DateTime('NOW')) {
            continue;
        }

        // The zeitstempel. No idea what it's for, but kal3000 seems to use it. 
        $wpc_from = $r['DTSTART']->format(d.m.Y H:i);
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
        $my_latlon = gcal_import_geocode($r['LOCATION']);

        // create a default form
        $post = get_default_post_to_edit ('termine');

        $file = dirname (__FILE__) . '/' . $post['post_name'] . '-defaults.txt';
        file_put_contents ( $file, var_export ($post, TRUE) );

// TODO: 
        if ( ! empty $r['ATTACH'] ) {
            // create image attachment and associate with new post
            error_log ("gcal_import_do_import found attachment $r['ATTACH'] for $r['SUMMARY']");
        }

        // and fill in the post form
        $post['post_content'] = $r['DESCRIPTION'];
        $post['post_title'] = $r['SUMMARY'];
        // create an excerpt for the overview page ([wpcalendar kat=...])
        if (strlen ($r['DESCRIPTION']) > 160) {
            $post['post_excerpt'] = substr ($r['DESCRIPTION'], 0, 160) . ' ...'; // first 160 chars of DESCRIPTION plus ' ...'
        } else {
            $post['post_excerpt'] = $r['DESCRIPTION'];
        }
        $post['post_status'] = 'published';
        $post['post_category'] = $category; 
        // sanitized title. We will add a timestamp to enable recurring events
        // this is not handled properly by wp_insert_post - recurring events would all have the same post_name. 
        $post['post_name'] = $r['DTSTART']->format('Y-m-d-H-i') . '-' . strtolower( urlencode($r['SUMMARY']) ) ; 
        $post['visibility'] = 'public';

        // now the wpcalendar metas. 
        $postmeta = array(
            _wpcal_from => $r['DTSTART']->format(d.m.Y H:i),
            _bis => $r['DTEND']->format(d.m.Y H:i),
            _geocity => gcal_import_geocity($r['LOCATION']),
            _geoshow => gcal_import_geoshow($r['LOCATION']),
            _lat => $my_latlon[0],
            _lon => $my_latlon[1],
            _zoom = 10,
            _veranstalter = '';
            _veranstalterlnk = '',
            _zeitstempel = $zeitstempel,
            _gcal_category => $category,
        );
        $post['meta_input'] = $postmeta;
        // debug
        $file = dirname (__FILE__) . '/' . $post['post_name'] . '-finished.txt';
        file_put_contents ( $file, var_export ($post, TRUE) );

        $post_id = wp_insert_post( $post, false );
        return ($post_id);
	}
}  


