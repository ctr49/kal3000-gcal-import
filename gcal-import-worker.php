<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// we may need a http proxy for the fetch. Should be set from the admin page. 
// define ('http_proxy', 'http://example.org:8080'); 
// we'll set this as category => proxy, link => link, active => 0; 
// in the admin page, all entries will be displayed with a checkbox for activating, deactivating, deleting. 

require_once __DIR__ . "/gcal-import-geocode.php"; 


/**
 * The worker gets called by the WP scheduler. 
 *
 * @since 0.1.0
 *
 */

function gcal_import_worker() {

    /*
     * retrieve the proxy from the db, and if it exists, construct a context. 
     * TODO: USER:PASS in DB. 
     *
     * http://www.pirob.com/2013/06/php-using-getheaders-and-filegetcontents-functions-behind-proxy.html
     */

    $options = get_option('gcal_options');
    $terms = get_terms( array(
                          'taxonomy' => 'termine_type',
                          'hide_empty' => false,  ) 
                    );
    foreach($terms as $term){
        $unique_id = 'gcal_feed_' . $term->name;
        if ( empty ( $options[$unique_id] ) || $options[$unique_id] == '' ) {
            gcal_error_log (INFO, "link for event category $term->name is not known; next");
            continue;
        }

/*
The update and delete logic goes as follows: 
- before we start updating, we mark all entries from this event category with recent = false. (i.e. outdated). 
- when we scan the ICS feed, 
  - if an entry with the same UID exists and was not updated in the meantime: do nothing. 
  - if it was updated in the meantime, we update our entry and set recent to true
  - if no such entry exists, we insert a new one and set recent to true
- when we're finished, we delete all entries from this event category which still have recent set to false. 
  Apparently, they were deleted on the remote end. 
*/

        // so we look for all published event posts in the GCal event category 
        $args = array (
            'post_type'   => 'termine',
            'post_status' => 'publish',
            'meta_key'    => '_gcal_category',
            'meta_value'  => $term->name,
        );
        $post_ids = get_posts( $args );
        // and set their recent flag to false. 
        if(is_array($post_ids)) {
            foreach( $post_ids as $post_id ) {
                $id = $post_id->ID;
                update_post_meta( $id, '_gcal_recent', 'false' );
            }  
        }
    	// now we process the current feed. 
        $link = $options[$unique_id];
        gcal_error_log (INFO, "now importing event cat $term->name, link $link");
    	gcal_import_do_import($term->name, $link);

        // look if there are any published event posts in the current event category which were not posted anew or updated (ie recent == false)
        $args = array (
            'post_type'   => 'termine',
            'post_status' => 'publish',
            'meta_query'  => array(
                array(
                    'key'     => '_gcal_category',
                    'value'   => $term->name,
                ), 
                array(
                    'key'     => '_gcal_recent',
                    'value'   => 'false',
                ),        
            )
        );
        $post_ids = get_posts( $args );
        // and trash them. 
        if(is_array($post_ids)) {
            foreach( $post_ids as $post_id ) {
                $id = $post_id->ID;
                wp_trash_post( $id );
                gcal_error_log (INFO, "Event post $id gelöscht.");
            }  
        }

    }	    
}	

add_action( 'gcal_import_worker_hook', 'gcal_import_worker' );


require_once __DIR__ . '/icalparser/src/IcalParser.php';
require_once __DIR__ . '/icalparser/src/Recurrence.php';
require_once __DIR__ . '/icalparser/src/Freq.php';
require_once __DIR__ . '/icalparser/src/WindowsTimezones.php';


function curl_get_remote($url) {
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $response = curl_exec($ch);
        if  ( curl_errno($ch) ) {
//             $info = curl_getinfo($ch);
            $message = __FUNCTION__ . ": cURL error " . curl_error($ch); 
//             gcal_error_log (WARN, $message);
            curl_close($ch);
            throw new \RuntimeException($message);
        } 
        curl_close($ch);
        // now we're sure we have a valid response: 
        return ($response);
}


function gcal_import_do_import($category, $link) {

    $my_latlon = array('', '');
	$cal = new \om\IcalParser();
	$results = $cal->parseString(curl_get_remote($link));

// TODO: Fehlerbehandlung, wenn der Link kaputt ist. Muss graceful passieren. 
// icalparser nutzt intern file_get_contents, und da kommt man nicht ohne weiteres ran. Evtl. ändern auf curl? 
// oder abfangen mit @file... 

    // we must set a current user because we may not be logged in. 
    $user_id = 1;
    $user = get_user_by( 'id', $user_id ); 
    if( $user ) {
        wp_set_current_user( $user_id, $user->user_login );
        wp_set_auth_cookie( $user_id );
    }
	foreach ($cal->getSortedEvents() as $r) {
        // if DTEND lies in the past, this event has expired. Ignore. 
        $now = new DateTime(); 
//        $dtend = new DateTime($r['DTEND']);
        $summary = $r['SUMMARY'];
        $dtstart = $r['DTSTART']->format('d.m.Y H:i');
        if ($r['DTEND'] < $now) {
            continue;
        } else {
            gcal_error_log (INFO, "processing $summary on $dtstart");
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
        $my_latlon = gcal_import_geocode($location);
        $file = dirname (__FILE__) . "/latlon-$hash.txt";
        file_put_contents ($file, var_export ($my_latlon, TRUE));

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

// TODO: 
        if ( isset($r['ATTACH']) ) {
            // create image attachment and associate with new post
            $attach = $r['ATTACH'];
            $summary = $r['SUMMARY'];
            gcal_error_log (INFO, "found attachment $attach for $summary");
        }

        if ( isset ( $r['CLASS'] ) && 'PRIVATE' == $r['CLASS']) {
            $secretevent = true;
        } else {
            $secretevent = false;
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
            '_geostadt' => gcal_import_geocity($r['LOCATION']),
            '_geoshow' => gcal_import_geoshow($r['LOCATION']),
            '_lat' => $my_latlon[0],
            '_lon' => $my_latlon[1],
            '_zoom' => '7',
            '_veranstalter' => '',
            '_veranstalterlnk' => '',
            '_zeitstempel' => $zeitstempel,
            '_gcal_uid' => $r['UID'],
            '_gcal_recent' => 'true',
            '_gcal_created' => $r['LAST-MODIFIED']->format('U'),
            '_gcal_category' => $category,
            '_secretevent' => $secretevent,
        );

        // so we have the new posts's attributes. Now we need to decide what to do with it. 
        // first, we try to find a published post with the same UID and zeitstempel. (due to recurring events having the same UID)
        // Alas, this will lead to events that were shifted to be trashed and posted anew. We cannot tell shifted events from recurring events.
        $args = array (
            'post_type'   => 'termine',
            'post_status' => 'publish',
            'meta_query'  => array(
                array(
                    'key'     => '_gcal_uid',
                    'value'   => $r['UID'],
                ), 
                array(
                    'key'     => '_zeitstempel',
                    'value'   => $zeitstempel,
                ),        
            )
        );
        $post_ids = get_posts( $args );  
        // did we find one? (It should really be only one!) 
        if ( is_array ( $post_ids ) ) {
             if ( empty ( $post_ids ) ) {
                // ok, none found, so we insert the new one
                $post_id = wp_insert_post( $post );
                if ( is_wp_error( $post_id ) ) {    
                    $message = $post_id->get_error_message();
                    gcal_error_log ( WARN, $message ); 
                } else {
                    update_post_meta( $post_id, '_edit_last', $user_id );
                    $now = time();
                    $lock = "$now:$user_id";
                    update_post_meta( $post_id, '_edit_lock', $lock );
                    // and assign the taxonomy type and event category. 
                    wp_set_object_terms( $post_id, $category, 'termine_type' );
                    gcal_error_log (INFO, "posted new post $post_id");
                }
            } else {
                // good, the post exists already. 
                $id = $post_ids[0]->ID;
                $created = get_post_meta( $id, '_gcal_created', true );
                $lastmodified = $r['LAST-MODIFIED']->format('U');
                // was it updated on the remote calendar? (was if modified after it was created remotely?) 
                if ( $lastmodified > $created ) {
                    // yes, so we update the existing post. We don't care _what_ changed. 
                    $post->ID = $id ; 
                    $post_id = wp_update_post( $post, false );
                    // and update the _created field
                    update_post_meta ( $id, '_gcal_created', $lastmodified ); 
                    gcal_error_log (INFO, "updated post $post_id");
                } elseif ( $lastmodified < $created ) {
                    // iiiiek! A time reversal or a secret time machine! That should not happen! 
                    gcal_error_log (WARN, "post $id last-modified : created $lastmodified < $created ");
                } // else both are equal, and we do nothing except setting recent to true. 
                update_post_meta ( $id, '_gcal_recent', 'true' ); 
            }
        } else {
            $file = dirname (__FILE__) . '/get_posts-' . $post->post_name . '.txt';
            gcal_error_log (WARN, "hmmm, get_posts() did not return an array. Logging to $file");
            file_put_contents ($file, var_export ($post_ids, TRUE)); 
        }
        // and on the next entry. 
	}
    // foreach end. we're finished. 
}  


