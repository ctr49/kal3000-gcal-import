<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


function gcal_import_geocity($location) {

    // Wenn die Adresse im Feld Stadt steht, wird sie richtig angezeigt, ergo:
    $pattern = '/(.*), ([0-9]{5} [^,]+)/';
    preg_match ($pattern, $location, $matches);
    if ( empty ($matches[2])) {
        return ($location);
    } else {
        return ($matches[2]); 
    }
}


function gcal_import_geoshow($location) {

    // later
    // not NULL so kal3000_the_termin_geo() displays a map if lat/lon are available. 
    // Hotel-Gasthof Maisberger, Bahnhofstraße 54, 85375 Neufahrn bei Freising, Deutschland
    // alles bis ", [0-9]{5}" ist geoshow
    // alles ab [0-9]{5}[\,]+ ist geocity. 
    $pattern = '/(.*), ([0-9]{5} [^,]+)/';
    preg_match ($pattern, $location, $matches);
    return ($matches[1]); 

}


function gcal_import_geocode($location) {

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


    if ( '' == $location ) {
        return array ('', '');
    }
    // check the cache first
    global $wpdb;
    $table = $wpdb->prefix.GCAL_GEO_TABLE;
    $hash = hash ('md5', $location); 
    $query = "SELECT gcal_geo_lat, gcal_geo_lon FROM $table WHERE gcal_geo_hash = '$hash'";
    $result = $wpdb->get_row( $query, ARRAY_N );
    if ( $wpdb->num_rows == 1 ) { // it should only be a single row! 
        error_log ("INFO: geocode cache hit hash $hash lat $result[0] lon $result[1]");
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

        $file = dirname (__FILE__) . "/geocode-result-$hash.txt";
        file_put_contents ($file, var_export ($result, TRUE)); 
        // do the caching now, but only if both values are set. 
        // $wpdb_insert does all the sanitizing for us. 
        $lat = $result[0];
        $lon = $result[1];
        if ('' != $lat && '' != $lon) {
            $wpdb->insert($table, array(
                'gcal_geo_location' => substr( $location, 0, 128 ),
                'gcal_geo_hash' => $hash,
                'gcal_geo_lat' => $lat,
                'gcal_geo_lon' => $lon, 
                'gcal_geo_timestamp' => time(),
            ));
            gcal_error_log ("INFO: geocoded and cached lat=$lat lon=$lon for location $location");
        } 
        // error handling? 
    }
    return ($result);
}


function gcal_import_geocode_official($location) {
    $options = get_option('gcal_options');
    if ( ! isset ( $options['gcal_apikey'] ) || '' == $options['gcal_apikey'] ) {  // ??? we should handle this in the admin frontend. 
        gcal_error_log ("WARN: using Google official geocoding but provided no APIKEY");
        return array ('','');
    } else {
        $apikey = $options['gcal_apikey']; 
        $location = urlencode($location);
        $useragent = 'Mozilla/5.0 (X11; Linux x86_64; rv:57.0) Gecko/20100101 Firefox/57.0';
        // https://developers.google.com/maps/documentation/geocoding/start
        $url = "https://maps.googleapis.com/maps/api/geocode/json?address=$location&key=$apikey"; 

        $response = curl_get_remote($url); 
        $decoded = json_decode($response, true);
        $lat = $decoded['results']['0']['geometry']['location']['lat']; 
        $lon = $decoded['results']['0']['geometry']['location']['lng'];
        gcal_error_log ("INFO: " . __FUNCTION__ . " found lat $lat lon $lon");
        return array ($lat, $lon);
/*
{
   "error_message" : "The provided API key is invalid.",
   "results" : [],
   "status" : "REQUEST_DENIED"
}
*/
    }
}


function gcal_import_geocode_osm($location) {
    // https://wiki.openstreetmap.org/wiki/Nominatim
    // https://nominatim.openstreetmap.org/search?q=Hotel+Gumberger+Gasthof+GmbH&format=json'
    $location = urlencode($location);
    gcal_error_log ("gcal_import_geocode_osm: location $location");
    // the main problem with Nominatim is that it doesn't understand GCal location information very well. 
    // we ought to cut off the location name and the country, i.e. zip code, city & street address only 
    $url = 'https://nominatim.openstreetmap.org/search?q="' . $location . '"&format=json';
    $response = wp_remote_get($url);
    $json = wp_remote_retrieve_body($response);
    $http_code = wp_remote_retrieve_response_code($response);
    // we need to catch errors

    // https://www.php.net/manual/en/function.json-decode.php
    $decoded = json_decode($json, true);
    // TODO error handling e.g. if we get no usable values. 
/*
    $file = dirname (__FILE__) . '/json-decoded.txt';
    // should simply be ->lat and -> lon 
    file_put_contents ($file, var_export ($decoded, TRUE)); 
    // The first array level ([0]) is only needed because OSM returns a JSON with enclosing []. 
*/
    $lat = $decoded['0']['lat'];
    $lon = $decoded['0']['lon'];
    gcal_error_log ("gcal_import_geocode_osm found lat=$lat lon=$lon loc $location");
    return array ($lat, $lon);
}


function gcal_import_geocode_inofficial($location) {

    $attempts = 0;
    $success = false;
    // we'll need to be easy with GMaps in order no to get a 429 Too Many Requests reply. 
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
            gcal_error_log ("INFO: got $attempts HTTP 429 Too Many Requests on $url");
        } else {
            gcal_error_log ("WARN: Unspecified HTTP error $http_code");
            return array ('', '');
        }
    }

    // ok so $result seems to be valid.
    // and now we need to look for:
    $pattern = '#www.google.com/maps/preview/place/[^/]+/@([\d\.]+),([\d\.]+),.*#';
    preg_match ($pattern, $result, $matches);

    // and return the result: 
    return array ($matches[1], $matches[2]); 
}


