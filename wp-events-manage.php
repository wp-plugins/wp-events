<?php
#-------------------------------------------------------------
# DO NOT REMOVE ME!! THIS FILE IS NEEDED FOR THE PLUGIN!
# I HANDLE THE MANAGEMENT OF THE EVENTS!
#-------------------------------------------------------------

/*-------------------------------------------------------------
 Name:      events_insert_input

 Purpose:   Prepare input form on saving new or updated events
 Receive:   -None-
 Return:	-None-
-------------------------------------------------------------*/
function events_insert_input() {
	global $wpdb, $userdata, $events_config;
	
	$event_id 			= $_POST['events_event_id'];
	$eventmsg 			= 'Passed event';
	$author 			= $_POST['events_username'];
	$title	 			= htmlspecialchars(trim($_POST['events_title'], "\t\n "), ENT_QUOTES);
	$title_link	 		= $_POST['events_title_link'];
	$location 			= htmlspecialchars(trim($_POST['events_location'], "\t\n "), ENT_QUOTES);
	$pre_event 			= htmlspecialchars(trim($_POST['events_pre_event'], "\t\n "), ENT_QUOTES);
	$post_event 		= htmlspecialchars(trim($_POST['events_post_event'], "\t\n "), ENT_QUOTES);
	$link		 		= htmlspecialchars(trim($_POST['events_link'], "\t\n "), ENT_QUOTES);
	$allday		 		= $_POST['events_allday'];
	$sday 				= htmlspecialchars(trim($_POST['events_sday'], "\t\n "), ENT_QUOTES);
	$smonth 			= htmlspecialchars(trim($_POST['events_smonth'], "\t\n "), ENT_QUOTES);
	$syear 				= htmlspecialchars(trim($_POST['events_syear'], "\t\n "), ENT_QUOTES);
	$shour 				= htmlspecialchars(trim($_POST['events_shour'], "\t\n "), ENT_QUOTES);
	$sminute 			= htmlspecialchars(trim($_POST['events_sminute'], "\t\n "), ENT_QUOTES);
	$eday 				= htmlspecialchars(trim($_POST['events_eday'], "\t\n "), ENT_QUOTES);
	$emonth 			= htmlspecialchars(trim($_POST['events_emonth'], "\t\n "), ENT_QUOTES);
	$eyear 				= htmlspecialchars(trim($_POST['events_eyear'], "\t\n "), ENT_QUOTES);
	$ehour 				= htmlspecialchars(trim($_POST['events_ehour'], "\t\n "), ENT_QUOTES);
	$eminute 			= htmlspecialchars(trim($_POST['events_eminute'], "\t\n "), ENT_QUOTES);
	$priority 			= $_POST['events_priority'];
	$archive 			= $_POST['events_archive'];

	if (strlen($title)!=0 AND strlen($syear)!=0 AND strlen($sday)!=0 AND strlen($smonth)!=0) {
		/* Date is sorted here */
		if(strlen($shour) == 0) $shour = 0;
		if(strlen($sminute) == 0) $sminute = 0;
		
		if(strlen($ehour) == 0) $ehour = $shour;
		if(strlen($eminute) == 0) $eminute = $sminute;
		if(strlen($emonth) == 0) $emonth = $smonth;
		if(strlen($eday) == 0) $eday = $sday;
		if(strlen($eyear) == 0) $eyear = $syear;
		
		$startdate = mktime($shour, $sminute, 0, $smonth, $sday, $syear);
		$enddate = mktime($ehour, $eminute, 0, $emonth, $eday, $eyear);
		
		if(strlen($post_event) == 0) {
			$post_event = $eventmsg;
		}
		
		if(isset($title_link) AND strlen($link) == 0) {
			// If link checkmark is on but no link is set...
			$title_link = 'N';			
		} else if(isset($title_link) AND strlen($link) != 0) {
			// See if the link checkmark is on and a link is set
			$title_link = 'Y';
		} else {
			// Or there is just no link
			$title_link = 'N';
		}
		
		if(isset($allday)) {
			$allday = 'Y';			
		} else {
			// Or there is just no link
			$allday = 'N';
		}
		
		/* Check if you need to update or insert a new record */
		if(strlen($event_id) != 0) {
			/* Update an existing event */
			$postquery = "UPDATE ".$wpdb->prefix."events SET
			title = '$title', title_link = '$title_link', location = '$location', pre_message = '$pre_event', post_message = '$post_event', link = '$link', allday = '$allday', thetime = '$startdate', theend = '$enddate', priority = '$priority', archive = '$archive', author = '$author'
			WHERE id = '$event_id'";
			$action = "update";
		} else {
			/* New event */
			$postquery = "INSERT INTO ".$wpdb->prefix."events
			(title, title_link, location, pre_message, post_message, link, allday, thetime, theend, author, priority, archive)
			VALUES ('$title', '$title_link', '$location', '$pre_event', '$post_event', '$link', '$allday', '$startdate', '$enddate', '$author', '$priority', '$archive')";		
			$action = "new";
		}
		if($wpdb->query($postquery) !== FALSE) {
			events_return($action);
			exit;
		} else {
			die(mysql_error());
		}
	} else {
		events_return('field_error');
		exit;
	}
}

/*-------------------------------------------------------------
 Name:      events_clear_old

 Purpose:   Removes old non archived events
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_clear_old() {
	global $wpdb;

	$removeme = date("U") - 86400;
	$wpdb->query("DELETE FROM ".$wpdb->prefix."events WHERE thetime < ".$removeme." AND archive = 'no'");
}

/*-------------------------------------------------------------
 Name:      events_request_delete

 Purpose:   Remove event from database
 Receive:   $del_archive
 Return:    -none-
-------------------------------------------------------------*/
function events_request_delete($del_archive = 0) {
	global $wpdb, $events_config;

	$event_ids = $_POST['eventcheck'];
	/* Check if multiple events are checked */
	if($event_ids != '') {
		foreach($event_ids as $event_id) {
			events_delete_eventid($event_id);
		}
	} else {
		/* Delete one event, w/ button */
		$event_id = $_GET['delete_event'];
		events_delete_eventid($event_id);
	}
	events_return('delete');
	exit;
}

/*-------------------------------------------------------------
 Name:      events_delete_eventid

 Purpose:   Remove event from database
 Receive:   event id
 Return:    boolean
-------------------------------------------------------------*/
function events_delete_eventid ($event_id) {
	global $wpdb, $userdata, $events_config;

	if($event_id > 0) {
		$SQL = "SELECT
		".$wpdb->prefix."events.id,
		".$wpdb->prefix."events.author,
		".$wpdb->prefix."users.display_name as display_name
		FROM
		".$wpdb->prefix."events,
		".$wpdb->prefix."users
		WHERE
		".$wpdb->prefix."events.id = '$event_id'
		AND
		".$wpdb->prefix."users.display_name = ".$wpdb->prefix."events.author";

		$event = $wpdb->get_row($SQL);

		if ( $userdata->user_level >= $events_config['managelevel'] OR $event->display_name == $event->author ) {
			$SQL = "DELETE FROM ".$wpdb->prefix."events WHERE id = $event_id";
			if($wpdb->query($SQL) == FALSE) {
				die(mysql_error());
			}
		} else {
			events_return('no_access');
			exit;
		}
	}
}

/*-------------------------------------------------------------
 Name:      events_return

 Purpose:   Return to events management
 Receive:   $action
 Return:    -none-
-------------------------------------------------------------*/
function events_return($action) {
	switch($action) {
		case "new" :
			wp_redirect('post-new.php?page=wp-events.php&action=created');
		break;
		
		case "update" :
			wp_redirect('edit.php?page=wp-events.php&action=updated');
		break;
		
		case "field_error" :
			wp_redirect('post-new.php?page=wp-events.php&action=field_error');
		break;
		
		case "access" :
			wp_redirect('post-new.php?page=wp-events.php&action=no_access');
		break;
		
		case "delete" :
			wp_redirect('edit.php?page=wp-events.php&action=deleted');
		break;
		case "uninstall" :
			wp_redirect('plugins.php?deactivate=true');
		break;
	}
}
?>