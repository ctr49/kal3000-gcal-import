<?php

function gcal_import_do_import($category, $link) {

    $gcal_category = 'Neufahrn';
    my_post = [];
   
	require_once dirname (__FILE__) . '/../../icalparser/src/IcalParser.php';
	require_once dirname (__FILE__) . '/../../icalparser/src/Recurrence.php';
	require_once dirname (__FILE__) . '/../../icalparser/src/Freq.php';
	require_once dirname (__FILE__) . '/../../icalparser/src/WindowsTimezones.php';

	$cal = new \om\IcalParser();
	$results = $cal->parseFile(
		'https://calendar.google.com/calendar/ical/gruene.freising%40gmail.com/public/basic.ics'
	);

    // we must set a current user because we may not be logged in. 
    $user_id = 1;
    $user = get_user_by( 'id', $user_id ); 
    if( $user ) {
        wp_set_current_user( $user_id, $user->user_login );
        wp_set_auth_cookie( $user_id );
    }
    // and create a nonce.
    $gcal_nonce = wp_create_nonce();
	foreach ($cal->getSortedEvents() as $r) {
        // TODO: wenn DTEND in der Vergangenheit liegt, nicht mehr posten. Next. 
        $my_post->ID = 0;
        $my_post->post_author = $user_id;     // always admin to avoid permission things.
        $my_post->post_content = $r['DESCRIPTION'];
        $my_post->post_title = $r['SUMMARY'];
        $my_post->post_excerpt = substr ($r['DESCRIPTION'], 0, 160) . ' ...'; // first 160 chars of DESCRIPTION plus ' ...'
        $my_post->post_status = 'published';
        $my_post->post_category = $gcal_category; // muss noch hierher.
        $my_post->post_name = '' ; // sanitized title
        $my_post->post_type = 'termine';
        $my_post->visibility = 'public';
        $my_post->wpc_from = $r['DTSTART']; // muss noch ins richtige Format. 
        $my_post->wpc_until = $r['DTEND'];
        // Jetzt den ort zerlegen und geocoden
        $my_post->geocity = gcal_geocity($r['LOCATION']);
        $my_post->geoshow = gcal_geoshow($r['LOCATION']);
        // geocoden
        $my_post->wpc_lat = gcal_latitude($r['LOCATION']);
        $my_post->wpc_lon = gcal_longitude($r['LOCATION']);
        $my_post->wpc_zoom = 10;
        $my_post->wpcalendar_noncename = $gcal_nonce; // tja. eines mit wp_create_nonce() erzeugen. 
        // Jetzt alles nach $_POST kopieren
        $post_id = wp_write_post ();
        // error handling, wenn Fehler
        add_post_meta ($post_id, '_gcal_category', '$category'); // gemient ist die aus dem GCal abgeleitete cat. 
	}

}  


