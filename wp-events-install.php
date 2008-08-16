<?php
/*-------------------------------------------------------------
 Name:      events_mysql_install

 Purpose:   Creates database tables if they don't exist
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function events_mysql_install() {
	global $wpdb;

	$table_name1 = $wpdb->prefix . "events";
	$table_name2 = $wpdb->prefix . "events_categories";

	if(!events_mysql_table_exists($table_name1)) {
		$add1 = "CREATE TABLE `".$table_name1."` (
	  		`id` mediumint(8) unsigned NOT NULL auto_increment PRIMARY KEY,
	  		`title` longtext NOT NULL,
	  		`title_link` varchar(3) NOT NULL default 'N',
	  		`location` varchar(255) NOT NULL,
	  		`category` int(11) NOT NULL default '1',
	  		`pre_message` longtext NOT NULL,
	  		`post_message` longtext NOT NULL,
	  		`link` longtext NOT NULL,
	  		`allday` varchar(3) NOT NULL default 'N',
	  		`thetime` int(15) NOT NULL default '0',
	  		`theend` int(15) NOT NULL default '0',
	  		`author` varchar(60) NOT NULL default '',
	  		`priority` varchar(4) NOT NULL default 'no',
	  		`archive` varchar(4) NOT NULL default 'no'
			);";
		if(mysql_query($add1) === true) {
			$table1 = 1;
		}
	} else {
		$table1 = 1;
	}

	if(!events_mysql_table_exists($table_name2)) {
		$add2 = "CREATE TABLE `".$table_name2."` (
			`id` mediumint(8) unsigned NOT NULL auto_increment PRIMARY KEY,
			`name` varchar(255) NOT NULL
			);";
			
		if(mysql_query($add2) === true) {
			$table2 = 1;
		}
	} else {
		$table2 = 1;
	}

	if($table1 == '1' AND $table2 == '1') {
		return true; //tables exist
	} else {
		events_mysql_warning();
		exit;
	}
}

/*-------------------------------------------------------------
 Name:      events_mysql_table_exists

 Purpose:   Check if the table exists in the database
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function events_mysql_table_exists($table_name) {
	global $wpdb;
	
	foreach ($wpdb->get_col("SHOW TABLES",0) as $table ) {
		if ($table == $table_name) {
			return true;
		}
	}
	return false;
}

/*-------------------------------------------------------------
 Name:      events_mysql_warning

 Purpose:   Database errors if things go wrong
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function events_mysql_warning() {
	echo '<div class="updated"><h3>WARNING! The MySQL table was not created! You cannot store events. Seek support at <a href="http://forum.at.meandmymac.net">http://forum.at.meandmymac.net</a>.</h3></div>';
}

/*-------------------------------------------------------------
 Name:      events_plugin_uninstall

 Purpose:   Delete the entire database table and remove the options on uninstall.
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function events_plugin_uninstall() {
	global $wpdb;

	// Drop MySQL Tables
	$SQL = "DROP TABLE `".$wpdb->prefix."events`";
	mysql_query($SQL) or die("An unexpected error occured.<br />".mysql_error());
	$SQL2 = "DROP TABLE `".$wpdb->prefix."events_categories`";
	mysql_query($SQL2) or die("An unexpected error occured.<br />".mysql_error());

	// Delete Option
	delete_option('events_config');
	delete_option('events_template');
	delete_option('events_language');

	// Deactivate Plugin
	$current = get_settings('active_plugins');
    array_splice($current, array_search( "wp-events/wp-events.php", $current), 1 );
	update_option('active_plugins', $current);
	do_action('deactivate_' . trim( $_GET['plugin'] ));

	events_return('uninstall');
}
?>