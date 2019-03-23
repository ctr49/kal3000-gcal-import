<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// we may need a http proxy for the fetch. Should be set from the admin page. 
// define ('http_proxy', 'http://example.org:8080'); 
// we'll set this as category => proxy, link => link, active => 0; 
// in the admin page, all entries will be displayed with a checkbox for activating, deactivating, deleting. 



// TODO: housekeeping function. use WP scheduler. 
// This is where the real work is done, i.e. retrieve, parse the GCAL, and insert the posts. 
// Posting: simulate a real HTTPS POST
//

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
    $categories = $wpdb->get_results("SELECT gcal_category from $table");
    $file = dirname (__FILE__) . '/categories.txt'; 
    file_put_contents ($file, var_dump ($categories)); 
/*
    foreach ( $categories as $category) {
	error_log ("found category $category");
        gcal_import_process_category($category);
    }	    
*/

    error_log ("gcal_import_worker finished", 0);
}	

include ('CalFileParser.php'); 

function gcal_import_process_category($category) {
    global $wpdb;
    $table = $wpdb->prefix.GCAL_TABLE;
    $query = "SELECT gcal_link from $table WHERE gcal_category = '$category' AND gcal_active = '1' ;";
    $link = $wpdb->query($query);
    error_log ("found active link $link for category $category");        
    $cal = new CalFileParser();
    // will this also work with a proxy? After all, it does a file_get_contents internally.
    $result = $cal->parse($link);
    $file = dirname (__FILE__) . '/$category-array.txt';
    file_put_contents ($file, var_dump ($result)); 


}	


