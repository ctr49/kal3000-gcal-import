<?php


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
    // TODO: This table will grow indefinitely over time, so we should add a timestamp field and remove 
    // entries that are older than, say, 30 days each time. 
    // this will also cope with Google subtly changing location strings in Maps over time. 
    // new entries will thus replace outdated ones over time. 
    global $wpdb;
    // CREATE table if it does not exist already. 
    $table = $wpdb->prefix.GCAL_GEO_TABLE;
    $query = "CREATE TABLE IF NOT EXISTS $table (
        id INT(9) NOT NULL AUTO_INCREMENT,
        gcal_geo_hash VARCHAR(40) NOT NULL,
        gcal_geo_lat VARCHAR(20) NOT NULL,
        gcal_geo_lon VARCHAR(20) NOT NULL,
        gcal_geo_timestamp DATETIME NOT NULL,
	    UNIQUE KEY id (id)
    );";
    $wpdb->query($query);

    $hash = hash ('md5', $location); 
    $query = "SELECT gcal_geo_lat, gcal_geo_lon FROM $table WHERE gcal_geo_hash = $hash";
    $result = $wpdb->get_results($query);
    if ( ! empty ($result) ) {
        return array ($result[0], $result[1]);
    } else {    

        // do the housekeeping first, before we create a new caching entry. 
        $outdated = DateTime('NOW')->sub( new DateInterval('P30D') );
        $query = "DELETE FROM $table WHERE gcal_geo_timestamp < $outdated";
        $wpdb->query($query);

        // OK so we need to work on the rate limiting.
        return array ('48.3124161', '11.6637297');
    
        $attempts = 0;
        $success = false;
        $opts = array('http' =>
            array(
                'method' => "GET",
                'header'  => "User-Agent: Mozilla/5.0 (Android 7.0; Mobile; rv:62.0) Gecko/62.0 Firefox/62.0",
            )
        );
                           
        $context  = stream_context_create($opts);
    
        while ($success == false && $attempts < 3) {
            // @ = 'ignore_errors' => TRUE
            $url = 'https://maps.google.com/maps?q=' . urlencode ($location);
            $result = file_get_contents ($url, false, $context);
            // see if we were too fast (429 Too Many Requests)
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
            // do the caching now. 
            $wpdb->insert($table, array(
                'gcal_geo_hash' => $hash,
                'gcal_geo_lat' => $matches[1],
                'gcal_geo_lon' => $matches[2], 
                'gcal_geo_timestamp' => DateTime('NOW'),
            ));

            // error handling? 
            // and return the result: 
            return array ($matches[1], $matches[2]); 
        }
        // limit requests per second

    }

}


function gcal_import_do_import($category, $link) {

    $gcal_category = 'Neufahrn';
    $post = array();
   
	require_once dirname (__FILE__) . '/icalparser/src/IcalParser.php';
	require_once dirname (__FILE__) . '/icalparser/src/Recurrence.php';
	require_once dirname (__FILE__) . '/icalparser/src/Freq.php';
	require_once dirname (__FILE__) . '/icalparser/src/WindowsTimezones.php';

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
        // create a per-post nonce.
        // $gcal_nonce = wp_create_nonce();
        $gcal_nonce = 0x8d538f9a;
       	echo sprintf('	<li>%s - %s</li>' . PHP_EOL, $r['DTSTART']->format('j.n.Y'), $r['SUMMARY']); 
        // TODO: wenn DTEND in der Vergangenheit liegt, nicht mehr posten. Next. 
        $post['ID'] = 0;
        $post['post_author'] = $user_id;     // always admin to avoid permission things.
        $post['post_content'] = $r['DESCRIPTION'];
        $post['post_title'] = $r['SUMMARY'];
        if (strlen ($r['DESCRIPTION']) > 160) {
            $post['post_excerpt'] = substr ($r['DESCRIPTION'], 0, 160) . ' ...'; // first 160 chars of DESCRIPTION plus ' ...'
        } else {
            $post['post_excerpt'] = $r['DESCRIPTION'];
        }
        $post['post_status'] = 'published';
        $post['post_category'] = $category; // muss noch hierher.
        $post['post_name'] = $r['DTSTART']->format('Y-m-d-H-i') . '-' . urlencode($r['SUMMARY']) ; // sanitized title
        $post['post_type'] = 'termine';
        $post['visibility'] = 'public';
        $post['wpc_from'] = $r['DTSTART']; // muss noch ins richtige Format. 
        $post['wpc_until'] = $r['DTEND'];
        // Jetzt den ort zerlegen und geocoden
        $post['geocity'] = gcal_import_geocity($r['LOCATION']);
        $post['geoshow'] = gcal_import_geoshow($r['LOCATION']);
        // geocoden
        $my_latlon = gcal_import_geocode($r['LOCATION']);
        $post['wpc_lat'] = $my_latlon[0];
        $post['wpc_lon'] = $my_latlon[1];
        $post['wpc_zoom'] = 10;
        $post['wpcalendar_noncename'] = $gcal_nonce; //  
        // TODO: Jetzt alles nach $_POST kopieren
        // crappy global var ... 
//        $post_id = wp_write_post ();
        // error handling, wenn Fehler
//        add_post_meta ($post_id, '_gcal_category', '$category'); // gemient ist die aus dem GCal abgeleitete cat. 
        $file = dirname (__FILE__) . '/wp_write_post.txt';
        file_put_contents ( $file, var_export ($post, TRUE) );
	}

}  


