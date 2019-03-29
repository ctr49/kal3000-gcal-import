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


function gcal_import_geocode($location) {

    error_log ("entering gcal_import_geocode($location)");

    // we try to cache results as we will need many times the same results especially for recurring events.
    // we will use a hash for the location because the hash has a fixed length, while the location has not. 
    // This table will grow indefinitely over time, so we need to add a timestamp field and remove 
    // entries that are older than, say, 30 days each time. 
    // this will also cope with Google subtly changing location strings in Maps over time. 
    // new entries will thus replace outdated ones over time. 

/*
Caching neu: in wp_options-> gcal_options ein Array geocache anlegen. Darunter für jeden hash ein Array schreiben, also: 

Datenmodell:

$geocache = array (
        hash1 = array (
            'gcal_geo_lat' => '',
            'gcal_geo_lon' => '',
            'gcal_geo_timestamp' => 0,
        ),
        hash2 = array ... 
        ); 


Schreiben: 
$options = get_options ( 'gcal_options' );

$geocache = $options ( 'geocache' );
$geocache['hashx'] = array ( $lat, $lon, time(), ); 

$options ( 'geocache' ) = $geocache; 

Löschen: 

foreach ( $geocache as $key => $value ) {
    if ( $key['gcal_geo_timestamp'] < time() - 2592000 ) {
        unset ( $options['geocache']['hashx'] )
    }
}

set_options ( 'gcal_options' );

Suchen: if ( isset ( $options['geocache']['hashx'] ) ) ... 



*/

    // check the cache first
    global $wpdb;
    $table = $wpdb->prefix.GCAL_GEO_TABLE;
    $hash = hash ('md5', $location); 
    $query = "SELECT gcal_geo_lat, gcal_geo_lon FROM $table WHERE gcal_geo_hash = '$hash'";
    error_log ("gcal_import_geocode looking up hash $hash location $location");
    error_log ("query: $query");
    $result = $wpdb->get_row( $query, ARRAY_N );
    $file = dirname (__FILE__) . "/$hash-lookup-result.txt";
    file_put_contents ($file, var_export ($result, TRUE));
    if ( $wpdb->num_rows == 1 ) { // it should only be a single row! 
        error_log ("gcal_import_geocode found hash $hash lat $result[0] lon $result[1]");
    } else {    
        // do the housekeeping first, before we create a new caching entry. 
        // remove all cache entries which are older than 30 days. 
        $outdated = time() - 2592000; // 30 Tage
        $query = "DELETE FROM $table WHERE gcal_geo_timestamp < $outdated";
        $wpdb->query($query);


        $options = get_option('gcal_options');
        $result = array ('', '');

        switch ( $options['gcal_geocoding'] ) {
            case "official" :
                $result = gcal_import_geocode_official($location);
                break;
            case "inofficial" :
                $result = gcal_import_geocode_inofficial($location);
                break;
            case "osm" :
                $result = gcal_import_geocode_osm($location);
                break;
        }

        // do the caching now, but only if both values are set. 
        // $wpdb_insert does all the sanitizing for us. 
        if ($result[0] != "" && $result[1] != "") {
            $wpdb->insert($table, array(
                'gcal_geo_location' => substr( $location, 0, 128 ),
                'gcal_geo_hash' => $hash,
                'gcal_geo_lat' => $result[0],
                'gcal_geo_lon' => $result[1], 
                'gcal_geo_timestamp' => time(),
            ));
        }
    }
    return ($result);
    // error handling? 

}


function gcal_import_geocode_official($location) {
    return array ('','');
}


function gcal_import_geocode_osm($location) {
    return array ('','');
}


function gcal_import_geocode_inofficial($location) {

    error_log ("entering gcal_import_geocode_inofficial($location)");

    $attempts = 0;
    $success = false;
    // we'll need to be easy with GMaps in order no to get a 429 Too Many Requests. 
    // max 3 retries with 2 second pauses, else we give up. 
    while ($success == false && $attempts < 3) {
        // @ = 'ignore_errors' => TRUE
        $url = "https://maps.google.com/maps?q=" . urlencode ($location);
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
            return array ('', '');
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

    // and return the result: 
    return array ($matches[1], $matches[2]); 

}


