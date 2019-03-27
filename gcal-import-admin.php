<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Display the admin table
 *
 * @since 0.1.0
 *
 */

function gcal_import_admin() {
    
    global $wpdb; 

    if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

    ?>
    <div class="wrap">
	<h1><?= esc_html(get_admin_page_title()); ?></h1>

    <h3>Terminkategorien und ICS-Feeds</h3>
    <p><b>Bitte hier die zu den Terminkategorien gehörigen Feeds eintragen (copy & paste!).</b></p>
    <p><b>Wenn zu einer Kategorie kein Feed gehört, einfach leer lassen.</b></p>
    <p><b>Feeds lassen sich jederzeit aktivieren oder deaktivieren, ohne andere Einstellungen zu ändern.</b></p>
    <form action="gcal_import_opts_action.php" method="post">
    <table border=0>
    <tr align=left> <th>Terminkategorie</th> <th>ICS-Feed https://...</th> <th>Aktiv</th> </tr>

    <?php 
    // TODO: Plausibilitätscheck, wenn link leer, dann inaktiv! Kann auf der Empfängerseite passieren. 
    $term_ids = $wpdb->get_results( "SELECT term_id FROM wp_term_taxonomy WHERE taxonomy = 'termine_type'" );
    foreach ( $term_ids as $tax ) {
        $names = $wpdb->get_results( "SELECT name FROM wp_terms WHERE term_id = $tax->term_id ORDER BY name ASC" );
        foreach ( $names as $term ) {
            $table = $wpdb->prefix.GCAL_TABLE;
            $r = $wpdb->get_results( "SELECT gcal_link, gcal_active FROM $table WHERE gcal_category = '$term->name'", ARRAY_A );
            $link = $r[0]['gcal_link'];
            $checked = ( $r[0]['gcal_active'] == '1' ? 'checked' : '' );
            echo "<tr> ";
            echo "<td> <b>$term->name</b> </td> ";
            echo "<td> <input type=\"text\" value=\"$link\" size=\"80\" maxlength=\"256\" name=\"$term->name\" placeholder=\"z.B. https://calendar.google.com/calendar/ical/.../public/basic.ics\" > </td> ";
            echo "<td> <input type=\"checkbox\" value=\"active\" name=\"\" $checked > </td> ";
            echo "</tr>" . PHP_EOL; 
        }
    }
    ?>

    </table>
    <!-- now the options -->
    <h3>Timing und Geocoding</h3>
    <table border=0>
    <!-- <tr> <th>Option</th> <th>Einstellung</th> </tr> -->

    <?php
    $interval = get_option( '_gcal_interval' );
    echo "<tr> <td><b>Zeitintervall</b></td> <td> <input type=\"text\" id=\"TODO\" value=\"$interval\" size=\"6\" maxlength=\"6\" name=\"interval\"> Minuten</td> </tr>";
    $geocoding = get_option( '_gcal_geocoding' );
    $apikey = get_option( '_gcal_apikey' );
    // TODO: die Eingabe Api Key bei Geocoding Google official muss validiert werden! -> Empfängerseite. 
    echo "<tr> <td><b>Geocoding</b></td> <td> "; 
    $options = array( 
                    array( 
                        'option' => 'off',
                        'name' => 'deaktiviert',
                    ),             
                    array( 
                        'option' => 'official',
                        'name' => 'Google official',
                    ),             
                    array( 
                        'option' => 'inofficial',
                        'name' => 'Google inofficial',
                    ),             
                    array( 
                        'option' => 'osm',
                        'name' => 'OpenStreetMap (in Entwicklung)',
                    ),             
                );

    foreach ( $options as $option ) {
        $checked = ( $geocoding == $option['option'] ? 'checked' : '' );
        $opt = $option['option'];
        $name = $option['name'];
        echo "<div>" . PHP_EOL;
        echo "<input type=\"radio\" id=\"$opt\" value=\"$opt\" name=\"geocoding\" $checked >";
        echo "<label for=\"$opt\">$name</label>";
        // apikey text area
        if ( 'official' == $opt ) {
            echo "<label>; in dem Fall ist ein API Key erforderlich:</label> <input type=\"text\" name=\"apikey\" value=\"$apikey\" size=\"40\" > </label>";
        }
        echo "</div>" . PHP_EOL;
    }
    echo "</td></tr>" . PHP_EOL;
    echo "</table>" . PHP_EOL;
    wp_nonce_field( 'gcal_import_opts', 'gcal_import_nonce' );
    ?>
    
    <input type="submit" value="Speichern">
    </form>
    </div>

    <?php
}


add_action( 'admin_menu', 'gcal_plugin_menu' );


function gcal_plugin_menu() {
	add_options_page( 'GCal Importer Options', 'GCal Importer', 'manage_options', 'kal3000-gcal-import', 'gcal_import_admin' );
}



