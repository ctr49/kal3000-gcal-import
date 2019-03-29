<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// we may need a http proxy for the fetch. Should be set from the admin page. 
// define ('http_proxy', 'http://example.org:8080'); 
// we'll set this as category => proxy, link => link, active => 0; 
// in the admin page, all entries will be displayed with a checkbox for activating, deactivating, deleting. 

require_once dirname ( __FILE__ ) . "/gcal-import-geocode.php"; 


/**
 * The worker gets called by the WP scheduler. 
 *
 * @since 0.1.0
 *
 */

function gcal_import_worker() {

    error_log ("gcal_import_worker started", 0);
/*
    error_log ("und wieder raus.");
    return (0);
*/

    /*
     * retrieve the proxy from the db, and if it exists, construct a context. 
     * TODO: USER:PASS in DB. 
     *
     * http://www.pirob.com/2013/06/php-using-getheaders-and-filegetcontents-functions-behind-proxy.html
     */

    $options = get_option('gcal_options');

    $file = dirname ( __FILE__ ) . '/options.txt'; 
    file_put_contents ($file, var_export($options, TRUE));
    $terms = get_terms( array(
                          'taxonomy' => 'termine_type',
                          'hide_empty' => false,  ) 
                    );
    foreach($terms as $term){
        $unique_id = 'gcal_feed_' . $term->name;
        error_log ("found event category $unique_id");
        if ( empty ( $options[$unique_id] ) || $options[$unique_id] == '' ) {
            error_log ( "event category $term->name is not known; next");
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

        // so we look for all published event posts in the event category 
        $args = array (
            'post_type'   => 'termine',
            'meta_key'    => '_gcal_category',
            'meta_value'  => $term->name,
            'post_status' => 'publish',
            )
        );
        $post_ids = get_posts( $args );
        // and set their recent flag to false. 
        foreach( $post_ids as $post_id ) {
            update_post_meta( $post_id, '_gcal_recent', false, );
        }  

    	// now we process the current feed. 
        $link = $options[$unique_id];
        error_log ("now importing event cat $term->name, link $link");
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
                    'value'   => false,
                ),        
            )
        );
        $post_ids = get_posts( $args );
        // and trash them. 
        foreach( $post_ids as $post_id ) {
            wp_trash_post( $post_id );
            error_log ("Event post $post_id gelöscht.");
        }  

    }	    

    error_log ("gcal_import_worker finished", 0);
}	

add_action( 'gcal_import_worker_hook', 'gcal_import_worker' );


function gcal_import_do_import($category, $link) {

    error_log ("entering gcal_import_do_import($category, $link)");
   
	require_once dirname (__FILE__) . '/icalparser/src/IcalParser.php';
	require_once dirname (__FILE__) . '/icalparser/src/Recurrence.php';
	require_once dirname (__FILE__) . '/icalparser/src/Freq.php';
	require_once dirname (__FILE__) . '/icalparser/src/WindowsTimezones.php';

	$cal = new \om\IcalParser();
	$results = $cal->parseFile($link);

// TODO: Fehlerbehandlung, wenn der Link kaputt ist. Muss graceful passieren. 

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
        // if DTEND lies in the past, this event has ended. Ignore. 
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
            '_gcal_uid' = $r['UID'],
            '_gcal_recent' = true,
            '_gcal_created' = $r['LAST-MODIFIED']->format('d.m.Y H:i'),
            '_gcal_category' => $category,
        );

        // debug
        $file = dirname (__FILE__) . '/' . $post->post_name . '-finished.txt';
        file_put_contents ( $file, var_export ($post, TRUE) );

        // so we have the new posts's attributes. Now we need to decide what to do with it.  Jetzt müssen wir entscheiden, was damit zu tun ist. 
        // first, we try to find a published post with the same UID: 
        $args = array (
            'post_type'   => 'termine',
            'meta_key'    => '_gcal_uid',
            'meta_value'  => $r['UID'],
            'post_status' => 'publish',
            )
        );
        $post_ids = get_posts( $args );  
        // did we find one? (It should really be only one!) 
        if ( empty ( $post_ids ) ) {
            // ok, none found, so we insert the new one
            $post_id = wp_insert_post( $post, false );
            // and assign the taxonomy type and event category. 
            wp_set_object_terms( $post_id, $category, 'termine_type' );
            error_log ("posted new post $post_id");
        } else {
            // good, the post exists already. 
            $post_id = $post_ids[0];
            $created = get_post_meta( $post_id, '_gcal_created' );
            // was it updated on the remote calendar? (was if modified after it was created remotely?) 
            if ( $r['LAST-MODIFIED'] > $created ) {
                // yes, so we update the existing post. We don't care _what_ changed. 
                $post->ID = $post_id ; 
                $post_id = wp_update_post( $post, false );
                error_log ("updated post $post_id");
            } elseif ( $r['LAST-MODIFIED'] == $created ) { 
                // it was not modified after we created it, so we do nothing! 
            } elseif ( $r['LAST-MODIFIED'] < $created ) {
                // iiiiek! A time reversal or a secret time machine! That should not happen! 
                error_log ("Warnung: merkwürdiges last-modified Datum beim Update von Post $post_id!");
            }
        } 
        // and on the next entry. 
	}
    // foreach end. we're finished. 
}  


