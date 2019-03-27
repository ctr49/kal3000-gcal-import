<?php

define('ABSPATH', $_REQUEST['abspath']);   // security??? 
  

echo "<pre> Hier geht's los, vor dem if. ABSPATH = " . ABSPATH . "</pre>"; 

if ( ! empty( $_REQUEST )  ) {
// if ( ! empty( $_REQUEST ) && check_admin_referer( 'gcal_import_opts', 'gcal_import_nonce' ) ) {

   // process form data

    echo "<div class=\"wrap\">" . PHP_EOL;

    echo "<h2>SERVER data:</h2>";
    echo "<pre>" . PHP_EOL;
    var_export ($_SERVER); 
    echo "</pre>" . PHP_EOL;

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
    if ( wp_redirect( _wp_http_referer ) ) {
        exit;
    }

}



