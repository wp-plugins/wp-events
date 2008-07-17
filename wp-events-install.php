<?php
#-------------------------------------------------------------
# DO NOT REMOVE ME!! THIS FILE IS NEEDED FOR THE PLUGIN!
# I HANDLE THE INSTALLATION AND DELETION OF THE PLUGIN!
#-------------------------------------------------------------

/*-------------------------------------------------------------
 Name:      events_mysql_install

 Purpose:   Creates database table if it doesnt exist
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function events_mysql_install() {
	global $wpdb;

	$table_name = $wpdb->prefix . "events";
	$sql = "CREATE TABLE ".$table_name." (
  		id mediumint(8) unsigned NOT NULL auto_increment,
  		title longtext NOT NULL,
  		title_link varchar(3) NOT NULL default 'N',
  		location varchar(255) NOT NULL,
  		pre_message longtext NOT NULL,
  		post_message longtext NOT NULL,
  		link longtext NOT NULL,
  		allday varchar(3) NOT NULL default 'N',
  		thetime int(15) NOT NULL default '0',
  		theend int(15) NOT NULL default '0',
  		author varchar(60) NOT NULL default '',
  		priority varchar(4) NOT NULL default 'Low',
  		archive varchar(4) NOT NULL default 'no',
  		PRIMARY KEY  (`id`)
		);";

	$wpdb->query($sql);

	if ( !events_mysql_table_exists()) {
		add_action('admin_menu', 'events_mysql_warning');
	}
}

/*-------------------------------------------------------------
 Name:      events_mysql_table_exists

 Purpose:   Check if the table exists in the database
 Receive:   -none-
 Return:	-none-
-------------------------------------------------------------*/
function events_mysql_table_exists() {
	global $wpdb;
	
	foreach ($wpdb->get_col("SHOW TABLES",0) as $table ) {
		if ($table == $wpdb->prefix."events") {
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
	echo '<div class="updated"><h3>WARNING! The MySQL table was not created! You cannot store events. Seek support at www.sothq.net.</h3></div>';
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
	$SQL = "DROP TABLE ".$wpdb->prefix."events";
	mysql_query($SQL) or die("An unexpected error occured.<br />".mysql_error());

	// Delete Option
	delete_option('events_config');

	// Deactivate Plugin
	$current = get_settings('active_plugins');
    array_splice($current, array_search( "wp-events/wp-events.php", $current), 1 );
	update_option('active_plugins', $current);
	do_action('deactivate_' . trim( $_GET['plugin'] ));

	events_return('uninstall');
}
?>