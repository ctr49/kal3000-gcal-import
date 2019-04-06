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
        $feed_name = $term->name;
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
    $apikey = ( isset ($options['gcal_apikey']) ? $options['gcal_apikey'] : '' ); // default empty

    $coders = array( 
                    array( 
                        'option' => 'off',
                        'name' => 'deaktiviert',
                    ),             
                    array( 
                        'option' => 'official',
                        'name' => 'Google official - in Entwicklung; erfordert einen API Key --> ',
                    ),             
                    array( 
                        'option' => 'inofficial',
                        'name' => 'Google inofficial',
                    ),             
                    array( 
                        'option' => 'osm',
                        'name' => 'OpenStreetMap - in Entwicklung',
                    ),             
                );
    
    foreach ( $coders as $coder ) {
        $checked = ( $current == $coder['option'] ? 'checked' : '' );
        echo '<input type="radio" id="gcal_geocoding" name="gcal_options[gcal_geocoding]" value ="' . $coder['option'] . '" ' . $checked . '> ' . $coder['name'];
        if ( $coder['option'] == 'official' ) {
            echo '<input type="text" size="32" id="gcal_geocoding" name="gcal_options[gcal_apikey]" value="' . $apikey . '">';
        }

        echo '</br>' ;
    }   
}



function gcal_options_validate($input) {
    return $input; 

// TODO 

/*
    $newinput['text_string'] = trim($input['text_string']);
    if(!preg_match('/^[a-z0-9]{32}$/i', $newinput['text_string'])) {
    $newinput['text_string'] = '';
    }
    return $newinput;
*/
}



