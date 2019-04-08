<?php
/**
 * Plugin Name: Kal3000 Google Calender Importer
 * Plugin URI:  https://github.com/hmilz/kal3000-gcal-import
 * Description: Imports and Merges an Arbitrary Number of Public Google Calendars into Kal3000
 * Version:     0.2.0
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

define ('GCAL_GEO_TABLE', 'gcal_import_geocache');


// The real work goes here. 
require_once dirname( __FILE__ ) . "/gcal-import-worker.php"; 
require_once dirname( __FILE__ ) . "/gcal-import-admin.php"; 


// create custom scheduler from custom option
add_filter( 'cron_schedules', 'gcal_cron_interval' );
 
function gcal_cron_interval( $schedules ) {
    $options = get_option('gcal_options');
    $current = ( isset ($options['gcal_timer']) ? $options['gcal_timer'] : '60' ); // default 60 minutes 
    $interval = 60 * $current; // wir speichern Minuten
    $schedules['gcal_interval'] = array(
        'interval' => $interval,
        'display'  => esc_html__( 'GCal fetch interval' ),
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
    global $wpdb;
    // CREATE geocaching table if it does not exist already. 
    // the location field will be used only during development and debugging, and will be omitted in production. 
    $table = $wpdb->prefix.GCAL_GEO_TABLE;
    $query = "CREATE TABLE IF NOT EXISTS $table (
        id INT(9) NOT NULL AUTO_INCREMENT,
        gcal_geo_location VARCHAR(128) NOT NULL,
        gcal_geo_hash VARCHAR(40) NOT NULL,
        gcal_geo_lat VARCHAR(20) NOT NULL,
        gcal_geo_lon VARCHAR(20) NOT NULL,
        gcal_geo_timestamp INT(16) NOT NULL,
	    UNIQUE KEY id (id)
    );";
    $wpdb->query($query);

    // and start the scheduler; 
    if ( ! wp_next_scheduled( 'gcal_import_worker_hook' ) ) {
        wp_schedule_event( time(), 'gcal_interval', 'gcal_import_worker_hook' );
    }

    gcal_error_log ("INFO: gcal_import activated");

    // empty geocode cache if option is set. 
    $options = get_option('gcal_options');
    if ( isset ( $options['gcal_reset_cache'] ) && '1' == $options['gcal_reset_cache'] ) { 
        $wpdb->query("DELETE IGNORE FROM $table WHERE 1=1"); 
        gcal_error_log ("INFO: emptied geocoding cache");
    }
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
    // clean up! Many plugins forget the housekeeping when deactivating. 
    wp_clear_scheduled_hook('gcal_import_worker_hook');
    gcal_error_log ("INFO: gcal_import deactivated");
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
    // clean up! Many plugins forget the housekeeping when uninstalling. 
    gcal_error_log ("INFO: uninstalling gcal_import");
    // can we uninstall without deactivating first?     
    // gcal_import_deactivate; 
    global $wpdb;
    // drop the geocache table 
    $table = $wpdb->prefix.GCAL_GEO_TABLE ; 
    $wpdb->query( "DROP TABLE IF EXISTS $table" );
    // and the options.
    delete_option ( 'gcal_options' );
}	

register_uninstall_hook( __FILE__, 'gcal_import_uninstall' );


/* 
 * Debug logging if debugging is activated 
 * 
 * @since 0.3.0
 */

function gcal_error_log($args) {
    $options = get_option('gcal_options');
    if ( isset ( $options['gcal_debugging'] ) && '1' == $options['gcal_debugging'] ) { 
        error_log ( "GCal: $args" );
    }
}

