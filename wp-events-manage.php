<?php
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
	$category 			= $_POST['events_category'];
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
			$allday = 'N';
		}
		
		/* Check if you need to update or insert a new record */
		if(strlen($event_id) != 0 AND isset($_POST['submit_save'])) {
			/* Update an existing event */
			$postquery = "UPDATE `".$wpdb->prefix."events` SET
			`title` = '$title', `title_link` = '$title_link', `location` = '$location', `category` = '$category',
			`pre_message` = '$pre_event', `post_message` = '$post_event', `link` = '$link', `allday` = '$allday', 
			`thetime` = '$startdate', `theend` = '$enddate', `priority` = '$priority', `archive` = '$archive', 
			`author` = '$author'
			WHERE `id` = '$event_id'";
			$action = "update";
		} else {
			/* New or duplicate event */
			$postquery = "INSERT INTO `".$wpdb->prefix."events`
			(`title`, `title_link`, `location`, `category`, `pre_message`, `post_message`, `link`, `allday`, `thetime`, `theend`, `author`, `priority`, `archive`)
			VALUES ('$title', '$title_link', '$location', '$category', '$pre_event', '$post_event', '$link', '$allday', '$startdate', '$enddate', '$author', '$priority', '$archive')";		
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
 Name:      events_create_category

 Purpose:   Add a new category
 Receive:   -None-
 Return:	-None-
-------------------------------------------------------------*/
function events_create_category() {
	global $wpdb, $userdata;
	
	$name = $_POST['events_category'];
	
	if (strlen($name) != 0) {
		$postquery = "INSERT INTO ".$wpdb->prefix."events_categories
		(name)
		VALUES ('$name')";		
		$action = "category_new";
		if($wpdb->query($postquery) !== FALSE) {
			events_return($action);
			exit;
		} else {
			die(mysql_error());
		}
	} else {
		events_return('category_field_error');
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
	$wpdb->query("DELETE FROM `".$wpdb->prefix."events` WHERE `thetime` < ".$removeme." AND `archive` = 'no'");
}

/*-------------------------------------------------------------
 Name:      events_request_delete

 Purpose:   Prepare removal of banner or category from database
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_request_delete() {
	global $wpdb, $events_config;

	$event_ids = $_POST['eventcheck'];
	$category_ids = $_POST['categorycheck'];

	if($event_ids != '') {
		foreach($event_ids as $event_id) {
			events_delete($event_id, 'banner');
		}
	}
	if($category_ids != '') {
		foreach($category_ids as $category_id) {
			events_delete($category_id, 'group');
		}
	}
	events_return('delete');
	exit;
}

/*-------------------------------------------------------------
 Name:      events_delete_eventid

 Purpose:   Remove event or category from database
 Receive:   $id, $what
 Return:    -none-
-------------------------------------------------------------*/
function events_delete($id, $what) {
	global $wpdb, $userdata, $events_config;

	if($id > 0) {
		if($what == 'banner') {
			$SQL = "SELECT
			".$wpdb->prefix."events.author,
			".$wpdb->prefix."users.display_name as display_name
			FROM
			".$wpdb->prefix."events,
			".$wpdb->prefix."users
			WHERE
			".$wpdb->prefix."events.id = '$id'
			AND
			".$wpdb->prefix."users.display_name = ".$wpdb->prefix."events.author";
	
			$event = $wpdb->get_row($SQL);
	
			if ($event->display_name == $event->author ) {
				$SQL = "DELETE FROM ".$wpdb->prefix."events WHERE id = $id";
				if($wpdb->query($SQL) == FALSE) {
					die(mysql_error());
				}
			} else {
				events_return('no_access');
				exit;
			}
		} else if ($what == 'group') {
			$SQL = "DELETE FROM ".$wpdb->prefix."events_categories WHERE id = $id";
			if($wpdb->query($SQL) == FALSE) {
				die(mysql_error());
			}
		} else {
			events_return('error');
			exit;
		}
	}
}

/*-------------------------------------------------------------
 Name:      events_check_config

 Purpose:   Create or update the options
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_check_config() {
	if ( !$option = get_option('events_config') ) {
		// Default Options
		$option['length'] 					= 1000;
		$option['sidelength'] 				= 120;
		$option['linktarget']				= '_blank';
		$option['amount'] 					= 2;
		$option['minlevel'] 				= 7;
		$option['managelevel'] 				= 10;
		$option['custom_date_page']			= 'no';
		$option['custom_date_sidebar']		= 'no';
		$option['dateformat'] 				= '%d %B %Y';
		$option['dateformat_sidebar']		= '%d %b %Y';
		$option['timeformat'] 				= '%H:%M';
		$option['timeformat_sidebar']		= '%H:%M';
		$option['timezone']					= '+0';
		$option['auto_delete']				= 'yes';
		$option['order'] 					= 'thetime ASC';
		$option['order_archive'] 			= 'thetime DESC';
		$option['localization'] 			= 'en_EN';
		update_option('events_config', $option);
	}
	
	if ( !$template = get_option('events_template') ) {
		$template['sidebar_template'] 		= '<li>%title% %link% on %startdate% %starttime%<br />%countdown%</li>';
		$template['sidebar_h_template'] 	= '<h2>Highlighted events</h2><ul>';
		$template['sidebar_f_template'] 	= '</ul>';
		$template['page_template'] 			= '<p><strong>%title%</strong>, %event% on %startdate% %starttime%<br />%countdown%<br />Duration: %duration%<br />%link%</p>';
		$template['page_h_template'] 		= '<h2>Important events</h2>';
		$template['page_f_template'] 		= '';
		$template['archive_template'] 		= '<p><strong>%title%</strong>, %after% on %startdate% %starttime%<br />%countup%<br />%enddate% %endtime%<br />%link%</p>';
		$template['archive_h_template'] 	= '<h2>Archive</h2>';
		$template['archive_f_template'] 	= '';
		$template['daily_template'] 		= '<p>%title% %event% - %countdown% %link%</p>';
		$template['daily_h_template'] 		= '<h2>Todays events</h2>';
		$template['daily_f_template'] 		= '';
		$template['location_seperator']		= '@ ';
		update_option('events_template', $template);
	}

	if ( !$language = get_option('events_language') ) {
		$language['language_today'] 		= 'today';
		$language['language_hours'] 		= 'hours';
		$language['language_minutes'] 		= 'minutes';
		$language['language_day'] 			= 'day';
		$language['language_days'] 			= 'days';
		$language['language_and'] 			= 'and';
		$language['language_on'] 			= 'on';
		$language['language_in'] 			= 'in';
		$language['language_ago'] 			= 'ago';
		$language['language_sidelink']		= 'more &raquo;';
		$language['language_pagelink']		= 'More information &raquo;';
		$language['language_noevents'] 		= 'No events to show';
		$language['language_nodaily'] 		= 'No events today';
		$language['language_noarchive'] 	= 'No events in the archive';
		$language['language_e_config'] 		= 'A configuration error has occured';
		$language['language_noduration'] 	= 'No duration!';
		$language['language_allday'] 		= 'All-day event!';
		update_option('events_language', $language);
	}
}

/*-------------------------------------------------------------
 Name:      events_options_submit

 Purpose:   Save options
 Receive:   $_POST
 Return:    -none-
-------------------------------------------------------------*/
function events_options_submit() {
	//options page
	$option['length'] 					= trim($_POST['events_length'], "\t\n ");
	$option['sidelength'] 				= trim($_POST['events_sidelength'], "\t\n ");
	$option['amount'] 					= trim($_POST['events_amount'], "\t\n ");
	$option['minlevel'] 				= $_POST['events_minlevel'];
	$option['managelevel'] 				= $_POST['events_managelevel'];
	$option['custom_date_page'] 		= $_POST['events_custom_date_page'];
	$option['custom_date_sidebar']		= $_POST['events_custom_date_sidebar'];
	$option['dateformat'] 				= htmlspecialchars(trim($_POST['events_dateformat'], "\t\n "), ENT_QUOTES);
	$option['dateformat_sidebar']		= htmlspecialchars(trim($_POST['events_dateformat_sidebar'], "\t\n "), ENT_QUOTES);
	$option['timeformat'] 				= $_POST['events_timeformat'];
	$option['timeformat_sidebar']		= $_POST['events_timeformat_sidebar'];
	$option['timezone'] 				= $_POST['events_timezone'];
	$option['auto_delete']	 			= $_POST['events_auto_delete'];
	$option['order']	 				= $_POST['events_order'];
	$option['order_archive'] 			= $_POST['events_order_archive'];
	$option['linktarget'] 				= $_POST['events_linktarget'];
	$option['localization'] 			= htmlspecialchars(trim($_POST['events_localization'], "\t\n "), ENT_QUOTES);
	update_option('events_config', $option);
	
	$template['sidebar_template'] 		= htmlspecialchars(trim($_POST['sidebar_template'], "\t\n "), ENT_QUOTES);
	$template['sidebar_h_template'] 	= htmlspecialchars(trim($_POST['sidebar_h_template'], "\t\n "), ENT_QUOTES);
	$template['sidebar_f_template'] 	= htmlspecialchars(trim($_POST['sidebar_f_template'], "\t\n "), ENT_QUOTES);
	$template['page_template'] 			= htmlspecialchars(trim($_POST['page_template'], "\t\n "), ENT_QUOTES);
	$template['page_h_template'] 		= htmlspecialchars(trim($_POST['page_h_template'], "\t\n "), ENT_QUOTES);
	$template['page_f_template'] 		= htmlspecialchars(trim($_POST['page_f_template'], "\t\n "), ENT_QUOTES);
	$template['archive_template'] 		= htmlspecialchars(trim($_POST['archive_template'], "\t\n "), ENT_QUOTES);
	$template['archive_h_template'] 	= htmlspecialchars(trim($_POST['archive_h_template'], "\t\n "), ENT_QUOTES);
	$template['archive_f_template'] 	= htmlspecialchars(trim($_POST['archive_f_template'], "\t\n "), ENT_QUOTES);
	$template['daily_template']	 		= htmlspecialchars(trim($_POST['daily_template'], "\t\n "), ENT_QUOTES);
	$template['daily_h_template'] 		= htmlspecialchars(trim($_POST['daily_h_template'], "\t\n "), ENT_QUOTES);
	$template['daily_f_template'] 		= htmlspecialchars(trim($_POST['daily_f_template'], "\t\n "), ENT_QUOTES);
	$template['location_seperator']		= htmlspecialchars(trim($_POST['location_seperator'], "\t\n"), ENT_QUOTES); // Note, spaces are not filtered
	update_option('events_template', $template);
	
	$language['language_today'] 		= htmlspecialchars(trim($_POST['events_language_today'], "\t\n "), ENT_QUOTES);
	$language['language_hours'] 		= htmlspecialchars(trim($_POST['events_language_hours'], "\t\n "), ENT_QUOTES);
	$language['language_minutes'] 		= htmlspecialchars(trim($_POST['events_language_minutes'], "\t\n "), ENT_QUOTES);
	$language['language_day'] 			= htmlspecialchars(trim($_POST['events_language_day'], "\t\n "), ENT_QUOTES);
	$language['language_days'] 			= htmlspecialchars(trim($_POST['events_language_days'], "\t\n "), ENT_QUOTES);
	$language['language_and'] 			= htmlspecialchars(trim($_POST['events_language_and'], "\t\n "), ENT_QUOTES);
	$language['language_on'] 			= htmlspecialchars(trim($_POST['events_language_on'], "\t\n "), ENT_QUOTES);
	$language['language_in'] 			= htmlspecialchars(trim($_POST['events_language_in'], "\t\n "), ENT_QUOTES);
	$language['language_ago'] 			= htmlspecialchars(trim($_POST['events_language_ago'], "\t\n "), ENT_QUOTES);
	$language['language_sidelink']		= htmlspecialchars(trim($_POST['events_language_sidelink'], "\t\n "), ENT_QUOTES);
	$language['language_pagelink'] 		= htmlspecialchars(trim($_POST['events_language_pagelink'], "\t\n "), ENT_QUOTES);
	$language['language_noevents']		= htmlspecialchars(trim($_POST['events_language_noevents'], "\t\n "), ENT_QUOTES);
	$language['language_nodaily']		= htmlspecialchars(trim($_POST['events_language_nodaily'], "\t\n "), ENT_QUOTES);
	$language['language_noarchive'] 	= htmlspecialchars(trim($_POST['events_language_noarchive'], "\t\n "), ENT_QUOTES);
	$language['language_e_config'] 		= htmlspecialchars(trim($_POST['events_language_e_config'], "\t\n "), ENT_QUOTES);
	$language['language_noduration'] 	= htmlspecialchars(trim($_POST['events_language_noduration'], "\t\n "), ENT_QUOTES);
	$language['language_allday'] 		= htmlspecialchars(trim($_POST['events_language_allday'], "\t\n "), ENT_QUOTES);
	update_option('events_language', $language);
}

/*-------------------------------------------------------------
 Name:      events_notifications

 Purpose:   Display notifications 
 Receive:   $action
 Return:    $result
-------------------------------------------------------------*/
function events_notifications($action) {
	
	if ($action == 'created') {
		$result = "<div id=\"message\" class=\"updated fade\"><p>Event <strong>created</strong> | <a href=\"edit.php?page=wp-events.php\">manage events</a></p></div>";
	} else if ($action == 'no_access') {
		$result = "<div id=\"message\" class=\"updated fade\"><p>Action prohibited</p></div>";
	} else if ($action == 'field_error') {
		$result = "<div id=\"message\" class=\"updated fade\"><p>Not all fields met the requirements</p></div>";
	} else if ($action == 'deleted') {
		$result = "<div id=\"message\" class=\"updated fade\"><p>Event/Category <strong>deleted</strong></p></div>";
	} else if ($action == 'updated') {
		$result = "<div id=\"message\" class=\"updated fade\"><p>Event <strong>updated</strong></p></div>";
	} else if ($action == 'category_new') {
		$result = "<div id=\"message\" class=\"updated fade\"><p>Category <strong>created</strong></p></div>";
	} else if ($action == 'category_field_error') {
		$result = "<div id=\"message\" class=\"updated fade\"><p>No category name filled in</p></div>";
	}
	
	return $result;
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
		
		case "error" :
			wp_redirect('post-new.php?page=wp-events.php&action=error');
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
		
		case "category_new" :
			wp_redirect('edit.php?page=wp-events.php&action=category_new');
		break;
		
		case "category_field_error" :
			wp_redirect('edit.php?page=wp-events.php&action=category_field_error');
		break;
	}
}
?>