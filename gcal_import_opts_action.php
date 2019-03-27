<?php

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

if ( ! empty( $_POST ) && check_admin_referer( 'gcal_import_opts', 'gcal_import_nonce' ) ) {
   // process form data

    echo "<div class=\"wrap\">" . PHP_EOL;
    echo "<h2>POST data:</h2>";
    echo "<pre>" . PHP_EOL;
    var_export ($_POST); 
    echo "</pre>" . PHP_EOL;

    echo "<h2>REQUEST data: </h2>";
    echo "<pre>" . PHP_EOL;
    var_export ($_REQUEST); 
    echo "</pre>" . PHP_EOL;
    
    echo "</div>" . PHP_EOL; 


    // alles in die DB schreiben

    // und redirect zurück zur Admin-Seite 


}



