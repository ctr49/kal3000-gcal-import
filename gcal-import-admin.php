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


