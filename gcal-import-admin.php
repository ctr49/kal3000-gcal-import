<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


/**
 * Display the admin table
 *
 * @since 0.1.0
 *
 */

/*
 * Quellen: 
 * https://codex.wordpress.org/Creating_Options_Pages
 * http://ottopress.com/2009/wordpress-settings-api-tutorial/
 */

add_action('admin_menu', 'gcal_admin_add_page');

function gcal_admin_add_page() {
    add_options_page( 'GCal Importer Einstellungen', 'GCal Importer', 'manage_options', 'kal3000-gcal-import', 'gcal_options_page');
}

function gcal_options_page() {
    
    if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

    ?>
    
    <div class="wrap">
	<h1><?= esc_html(get_admin_page_title()); ?></h1>

    <form action="options.php" method="post">
    <?php settings_fields('gcal_options'); ?>
    <?php do_settings_sections('gcal'); ?>
     
    <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
    </form></div>

<?php
}

add_action('admin_init', 'gcal_admin_init');

function gcal_admin_init(){
    register_setting( 'gcal_options', 'gcal_options', 'gcal_options_validate' );
    add_settings_section('gcal_feeds', 'Terminkategorien und ICS-Feeds', 'gcal_feeds_section_text', 'gcal');

    // settings fields dynamisch pro Feed generieren, nur ein Callback mit Args nutzen. 
    $terms = get_terms( array(
                          'taxonomy' => 'termine_type',
                          'hide_empty' => false,  ) 
                    );
    foreach($terms as $term){
        $unique_id = 'gcal_feed_' . $term->name;
        $feed_name = 'ICS Feed für Terminkategorie ' . $term->name;
        add_settings_field($unique_id, $feed_name, 'gcal_feeds_setting_string', 'gcal', 'gcal_feeds', array($unique_id));
    }

    add_settings_section('gcal_timer', 'Zeitintervall', 'gcal_timer_section_text', 'gcal');
    add_settings_field('gcal_timer', 'Zeitintervall', 'gcal_timer_setting_string', 'gcal', 'gcal_timer');

    add_settings_section('gcal_geocoding', 'Geocoding', 'gcal_geocoding_section_text', 'gcal');
    add_settings_field('gcal_geocoding', 'Geocoding', 'gcal_geocoding_setting_string', 'gcal', 'gcal_geocoding');    
}


function gcal_feeds_section_text() {
?>
    <p><b>Bitte hier die zu den Terminkategorien gehörigen Feeds eintragen (copy & paste!).</b></br>
       <b>Wenn zu einer Terminkategorie kein Feed gehört, einfach leer lassen.</b></p>
<?php
}



function gcal_feeds_setting_string($args) {
    $options = get_option('gcal_options');
    $placeholder = "z.B. https://calendar.google.com/calendar/ical/.../public/basic.ics";
    // die id entspricht dem unique_id in add_settings_field.
    // der name wird options.php als Name der zu setzenden Option übergeben
    // der Value ist der inhalt von der $option[unique_id]. 
    echo '<input type="text" id="' . $args[0] . '" name="gcal_options[' . $args[0] . ']" value="' . $options[$args[0]] . '" size="80" maxlength="256" placeholder="' . $placeholder . '" > </br>';
}


function gcal_timer_section_text() {
?>
    <p><b>Zeitintervall in Minuten, in dem die Feeds synchronisiert werden sollen.</b></br>
       <b>Neu setzen erfordert einen Neustart des Plugins (Deaktivieren / Aktivieren).</b></p>
<?php
}


function gcal_timer_setting_string() {
    $options = get_option('gcal_options');
    $placeholder = "default 60";
    echo '<input type="text" id="gcal_timer" name="gcal_options[gcal_timer]" value="' . $options['gcal_timer'] . '" size="6" maxlength="6" placeholder="' . $placeholder . '" > Minuten </br>';
}



function gcal_geocoding_section_text() {
?>
    <p><b>Um Termine auf der Karte zu sehen, ist es nötig, die Orte zu geocoden, d.h. </br>
          deren geografische Länge und Breite herauszufinden. Dafür sind mehrere </br>
          Verfahren wählbar:</b>
    </p>
<?php
}


function gcal_geocoding_setting_string() {
    $options = get_option('gcal_options');
    $current = ( isset ($options['gcal_geocoding']) ? $options['gcal_geocoding'] : 'off' ); // default off 

    $coders = array( 
                    array( 
                        'option' => 'off',
                        'name' => 'deaktiviert',
                    ),             
                    array( 
                        'option' => 'official',
                        'name' => 'Google official (in Entwicklung; erfordert einen API Key)',
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
    
    foreach ( $coders as $coder ) {
        $checked = ( $current == $coder['option'] ? 'checked' : '' );
        echo '<input type="radio" id="gcal_geocoding" name="gcal_options[gcal_geocoding]" value ="' . $coder['option'] . '" ' . $checked . '> ' . $coder['name'] . '</br>' ;
    }
    
}



function gcal_options_validate($input) {
    return $input; 

/*
    $newinput['text_string'] = trim($input['text_string']);
    if(!preg_match('/^[a-z0-9]{32}$/i', $newinput['text_string'])) {
    $newinput['text_string'] = '';
    }
    return $newinput;
*/
}

/*
    <div class="wrap">
	<h1><?= esc_html(get_admin_page_title()); ?></h1>

    <h3>Terminkategorien und ICS-Feeds</h3>
    <p><b>Bitte hier die zu den Terminkategorien gehörigen Feeds eintragen (copy & paste!).</b></p>
    <p><b>Wenn zu einer Kategorie kein Feed gehört, einfach leer lassen.</b></p>
    <p><b>Feeds lassen sich jederzeit aktivieren oder deaktivieren, ohne andere Einstellungen zu ändern.</b></p>
    <form action="options.php" method="post">
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
            $active = "$term->name" . '_active';
            echo "<tr> ";
            echo "<td> <b>$term->name</b> </td> ";
            echo "<td> <input type='text' value='$link' size='80' maxlength='256' name='$term->name' placeholder='z.B. https://calendar.google.com/calendar/ical/.../public/basic.ics' > </td> ";
            echo "<td> <input type='checkbox' value='active' name='$active' $checked > </td> ";
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
    echo "<tr> <td><b>Zeitintervall</b></td> <td> <input type='text' id='TODO' value='$interval' size='6' maxlength='6' name='interval'> Minuten</td> </tr>";
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
        echo "<input type='radio' id='$opt' value='$opt' name='geocoding' $checked >";
        echo "<label for='$opt'>$name</label>";
        // apikey text area
        if ( 'official' == $opt ) {
            echo "<label>; in dem Fall ist ein API Key erforderlich:</label> <input type='text' name='apikey' value='$apikey' size='40' > </label>";
        }
        echo "</div>" . PHP_EOL;
    }
    echo "</td></tr>" . PHP_EOL;
    echo "</table>" . PHP_EOL;
    wp_nonce_field( 'gcal_import_opts', 'gcal_import_nonce' );
    echo "<input type='hidden' name='abspath' value='" . ABSPATH . "'>" . PHP_EOL; 
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

*/

