<?php
/**
 * Plugin Name: Kal3000 Google Calender Importer
 * Plugin URI:  https://github.com/hmilz/kal3000-gcal-import
 * Description: Imports and Merges an Arbitrary Number of Public Google Calendars into Kal3000
 * Version:     0.1
 * Author:      Harald Milz <hm@seneca.muc.de>
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0
 * Domain Path: /languages
 *
 * {Plugin Name} is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 * 
 * {Plugin Name} is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with {Plugin Name}. If not, see {License URI}.
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define ('GCAL_TABLE', 'gcal_import');

// we may need a http proxy for the fetch. Should be set from the admin page. 
// define ('http_proxy', 'http://example.org:8080'); 


/*
 * gcal-import-install
 * create DB table with:
 * - gcal_category - name of the calendar, for later per-unit display
 * - gcal_link - the public or private .ics link
 * - gcal_veranstalter - ?  
 * - gcal_active - flag if a calendar is active or not. Default active. 
 *
 */

// The real work goes here. 
include dirname( __FILE__ ) . "/gcal-import-worker.php"; 
add_action( 'gcal_import_worker_hook', 'gcal_import_worker' );


// temporary: create 5 min scheduler	
add_filter( 'cron_schedules', 'example_add_cron_interval' );
 
function example_add_cron_interval( $schedules ) {
    $schedules['five_minutes'] = array(
        'interval' => 300,
        'display'  => esc_html__( 'Every Five Minutes' ),
    );
 
    return $schedules;
}


/**
 * Initializes the plugin and creates a table
 *
 * @since 0.1.0
 *
 * - gcal_category - name of the calendar, for later per-unit display
 * - gcal_link - the public or private .ics link
 * - gcal_veranstalter - ?  
 * - gcal_active - flag if a calendar is active or not. Default active. 
 *
 * Since there is no install hook in WP, we will use the activation hook for both. 
 */

function gcal_import_activate()
{
    error_log ("gcal_import_activate started");
    global $wpdb;
    // CREATE table if it does not exist already. 
    $table = $wpdb->prefix.GCAL_TABLE;
    $query = "CREATE TABLE IF NOT EXISTS $table (
        id INT(9) NOT NULL AUTO_INCREMENT,
        gcal_category VARCHAR(32) NOT NULL,
        gcal_link VARCHAR(256) NOT NULL,
        gcal_active INT(9) DEFAULT 1,
	    UNIQUE KEY id (id)
    );";
    $wpdb->query($query);

    // Populate table
    // this will later be set by the admin page
    // empty it first to prevent doublettes
    $wpdb->query("DELETE FROM $table WHERE 1=1");
    $wpdb->query("INSERT INTO $table(gcal_category, gcal_link, gcal_active)
        VALUES('kv-freising', 'https://calendar.google.com/calendar/ical/gruene.freising%40gmail.com/public/basic.ics', '1')");
    $wpdb->query("INSERT INTO $table(gcal_category, gcal_link, gcal_active)
	    VALUES('ov-freising', '/tmp/neufahrn.ics', '1')");


    // do it once now! Won't work if the table hasn't been populated yet. 
    $result = $wpdb->query("SELECT gcal_category FROM $table");
    if ($result != 0) {
        gcal_import_worker; 
    }
    // and start the scheduler; 
    // in production, we will do this hourly. 
    if ( ! wp_next_scheduled( 'gcal_import_worker_hook' ) ) {
        wp_schedule_event( time(), 'five_minutes', 'gcal_import_worker_hook' );
    //  wp_schedule_event( time(), 'hourly', 'gcal_import_worker_hook' );
    }
    error_log ("gcal_import_activate finished");

}

register_activation_hook( __FILE__, 'gcal_import_activate' );


/**
 * Deactivate unregisters the scheduling function.
 *
 * @since 0.1.0
 *
 */

function gcal_import_deactivate()
{
    error_log ("gcal_import_deactivate started");
    wp_clear_scheduled_hook('gcal_import_worker_hook');
    error_log ("gcal_import_deactivate finished");
}	

register_deactivation_hook( __FILE__, 'gcal_import_deactivate' );


/**
 * Uninstall drops our DB table
 *
 * @since 0.1.0
 *
 */

function gcal_import_uninstall()
{
    error_log ("gcal_import_uninstall started");
    // can we uninstall without deactivating first?     
    // gcal_import_deactivate; 
    global $wpdb;
    $table = $wpdb->prefix.GCAL_TABLE;
    $wpdb->query("DROP TABLE $table");
    error_log ("gcal_import_uninstall finished");
}	

register_uninstall_hook( __FILE__, 'gcal_import_uninstall' );


/**
 * Display the admin table
 * may go to a separate file eventually
 *
 * @since 0.1.0
 *
 */

function gcal_import_admin()
{
    global $wpdb;
    $table = $wpdb->prefix.GCAL_TABLE;

    // we MUST protect queries, 
    // see https://codex.wordpress.org/Class_Reference/wpdb#Protect_Queries_Against_SQL_Injection_Attacks
    // $sql = $wpdb->prepare( 'INSERT INTO $table ... ' , value_parameter[, value_parameter ... ] );
    
}




	


