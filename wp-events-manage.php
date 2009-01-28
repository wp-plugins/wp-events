<?php
/*-------------------------------------------------------------
 Name:      events_editor

 Purpose:   Use simple HTML formatting tools to generate events
 Receive:   $content, $id, $prev_id, $tab_index
 Return:	$template
-------------------------------------------------------------*/
function events_editor($content, $id = 'content', $prev_id = 'title') {
	$media_buttons = false;
	$richedit =  user_can_richedit();
	?>
	<div id="quicktags">
	<?php wp_print_scripts( 'quicktags' ); ?>
	<script type="text/javascript">edToolbar()</script>
	</div>

	<?php $the_editor = apply_filters('the_editor', "<div id='editorcontainer'><textarea rows='6' cols='40' name='$id' tabindex='4' id='$id'>%s</textarea></div>\n");
	$the_editor_content = apply_filters('the_editor_content', $content);

	printf($the_editor, $content);

	?>
	<script type="text/javascript">
	// <![CDATA[
	edCanvas = document.getElementById('<?php echo $id; ?>');
	<?php if ( user_can_richedit() && $prev_id ) { ?>
	var dotabkey = true;
	// If tinyMCE is defined.
	if ( typeof tinyMCE != 'undefined' ) {
		// This code is meant to allow tabbing from Title to Post (TinyMCE).
		jQuery('#<?php echo $prev_id; ?>')[jQuery.browser.opera ? 'keypress' : 'keydown'](function (e) {
			if (e.which == 9 && !e.shiftKey && !e.controlKey && !e.altKey) {
				if ( (jQuery("#post_ID").val() < 1) && (jQuery("#title").val().length > 0) ) { autosave(); }
				if ( tinyMCE.activeEditor && ! tinyMCE.activeEditor.isHidden() && dotabkey ) {
					e.preventDefault();
					dotabkey = false;
					tinyMCE.activeEditor.focus();
					return false;
				}
			}
		});
	}
	<?php } ?>
	// ]]>
	</script>
	<?php
}

/*-------------------------------------------------------------
 Name:      events_insert_input

 Purpose:   Prepare and insert data on saving new or updating event
 Receive:   $_POST
 Return:	-None-
-------------------------------------------------------------*/
function events_insert_input() {
	global $wpdb, $userdata, $events_config, $events_language, $events_tracker;
	
	if(current_user_can($events_config['minlevel'])) {
		$event_id 			= $_POST['events_event_id'];
		$eventmsg 			= $events_language['language_past'];
		$author 			= $_POST['events_username'];
		$title	 			= htmlspecialchars(trim($_POST['events_title'], "\t\n "), ENT_QUOTES);
		$title_link	 		= $_POST['events_title_link'];
		$location 			= htmlspecialchars(trim($_POST['events_location'], "\t\n "), ENT_QUOTES);
		$category 			= $_POST['events_category'];
		$pre_event 			= htmlspecialchars(trim($_POST['content'], "\t\n "), ENT_QUOTES);
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
			if(strlen($ehour) == 0) 	$ehour = $shour;
			if(strlen($eminute) == 0) 	$eminute = $sminute;
			if(strlen($emonth) == 0) 	$emonth = $smonth;
			if(strlen($eday) == 0) 		$eday = $sday;
			if(strlen($eyear) == 0) 	$eyear = $syear;
			
			$startdate 	= gmmktime($shour, $sminute, 0, $smonth, $sday, $syear);
			$enddate 	= gmmktime($ehour, $eminute, 0, $emonth, $eday, $eyear);
	
			if(strlen($post_event) == 0) $post_event = $eventmsg;
			
			if(isset($title_link) AND strlen($link) != 0) $title_link = 'Y';			
				else $title_link = 'N';
			
			if(isset($allday)) $allday = 'Y';			
				else $allday = 'N';
			
			if(strlen($event_id) != 0 AND isset($_POST['submit_save'])) {
				/* Update an existing event */
				$postquery = "UPDATE `".$wpdb->prefix."events` SET
				`title` = '$title', `title_link` = '$title_link', `location` = '$location', `category` = '$category',
				`pre_message` = '$pre_event', `post_message` = '$post_event', `link` = '$link', `allday` = '$allday', 
				`thetime` = '$startdate', `theend` = '$enddate', `priority` = '$priority', `archive` = '$archive', 
				`author` = '$author'
				WHERE `id` = '$event_id'";
				$action = "Update";
			} else {
				/* New or duplicate event */
				$postquery = "INSERT INTO `".$wpdb->prefix."events`
				(`title`, `title_link`, `location`, `category`, `pre_message`, `post_message`, `link`, `allday`, `thetime`, `theend`, `author`, `priority`, `archive`)
				VALUES ('$title', '$title_link', '$location', '$category', '$pre_event', '$post_event', '$link', '$allday', '$startdate', '$enddate', '$author', '$priority', '$archive')";		
				$action = "New";
			}
			if($wpdb->query($postquery) !== FALSE) {
				if($events_tracker['register'] == 'Y') { events_send_data($action.' Event'); }
				events_return(strtolower($action));
				exit;
			} else {
				die(mysql_error());
			}
		} else {
			events_return('field_error');
			exit;
		}
	} else {
		events_return('no_access');
		exit;
	}
}

/*-------------------------------------------------------------
 Name:      events_create_category

 Purpose:   Add a new category
 Receive:   $_POST
 Return:	-None-
-------------------------------------------------------------*/
function events_create_category() {
	global $wpdb, $events_config, $events_tracker, $userdata;
	
	$name = $_POST['events_category'];
	
	if (strlen($name) != 0) {
		$postquery = "INSERT INTO `".$wpdb->prefix."events_categories`
		(`name`)
		VALUES ('$name')";		
		if($wpdb->query($postquery) !== FALSE) {
			if($events_tracker['register'] == 'Y') { events_send_data('New Category'); }
			events_return('category_new');
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
 Name:      events_request_delete

 Purpose:   Prepare removal of banner or category from database
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_request_delete() {
	global $wpdb, $events_config, $events_tracker;

	$event_ids = $_POST['eventcheck'];
	$category_ids = $_POST['categorycheck'];
	if($event_ids != '') {
		foreach($event_ids as $event_id) {
			events_delete($event_id, 'event');
			if($events_tracker['register'] == 'Y') { events_send_data('Delete Event'); }
		}
		events_return('delete-event');
		exit;
	}
	if($category_ids != '') {
		foreach($category_ids as $category_id) {
			events_delete($category_id, 'category');
			if($events_tracker['register'] == 'Y') { events_send_data('Delete Category'); }
		}
		events_return('delete-category');
		exit;
	}
}

/*-------------------------------------------------------------
 Name:      events_delete

 Purpose:   Remove event or category from database
 Receive:   $id, $what
 Return:    -none-
-------------------------------------------------------------*/
function events_delete($id, $what) {
	global $wpdb, $userdata, $events_config;

	if($id > 0) {
		if (current_user_can($events_config['managelevel'])) {
			if($what == 'event') {
					$SQL = "DELETE FROM `".$wpdb->prefix."events` WHERE `id` = $id";
					if($wpdb->query($SQL) == FALSE) {
						die(mysql_error());
					}
			} else if ($what == 'category') {
				$SQL = "DELETE FROM `".$wpdb->prefix."events_categories` WHERE `id` = $id";
				if($wpdb->query($SQL) == FALSE) {
					die(mysql_error());
				}
			} else {
				events_return('error');
				exit;
			}
		} else {
			events_return('no_access');
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
		$option['sideshow'] 				= 1;
		$option['linktarget']				= '_blank';
		$option['amount'] 					= 2;
		$option['hideend'] 					= 'show';
		$option['minlevel'] 				= 7;
		$option['managelevel'] 				= 10;
		$option['custom_date_page']			= 'no';
		$option['custom_date_sidebar']		= 'no';
		$option['dateformat'] 				= '%d %B %Y';
		$option['dateformat_sidebar']		= '%d %b %Y';
		$option['timeformat'] 				= '%H:%M';
		$option['timeformat_sidebar']		= '%H:%M';
		$option['order'] 					= 'thetime ASC';
		$option['order_archive'] 			= 'thetime DESC';
		$option['localization'] 			= 'en_EN';
		update_option('events_config', $option);
	}
	
	if ( !$tracker = get_option('events_tracker') ) {
		$tracker['register']		 		= 'Y';
		$tracker['anonymous'] 				= 'N';
		update_option('events_tracker', $tracker);
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
		$language['language_past'] 			= 'Past event!';
		update_option('events_language', $language);
	}
}

/*-------------------------------------------------------------
 Name:      events_options_submit

 Purpose:   Save options from dashboard
 Receive:   $_POST
 Return:    -none-
-------------------------------------------------------------*/
function events_options_submit() {
	$buffer = get_option('events_tracker');
	
	// Prepare general settings
	$option['length'] 					= trim($_POST['events_length'], "\t\n ");
	$option['sidelength'] 				= trim($_POST['events_sidelength'], "\t\n ");
	$option['sideshow'] 				= $_POST['events_sideshow'];
	$option['amount'] 					= trim($_POST['events_amount'], "\t\n ");
	$option['minlevel'] 				= $_POST['events_minlevel'];
	$option['hideend']	 				= $_POST['events_hideend'];
	$option['managelevel'] 				= $_POST['events_managelevel'];
	$option['custom_date_page'] 		= $_POST['events_custom_date_page'];
	$option['custom_date_sidebar']		= $_POST['events_custom_date_sidebar'];
	$option['dateformat'] 				= htmlspecialchars(trim($_POST['events_dateformat'], "\t\n "), ENT_QUOTES);
	$option['dateformat_sidebar']		= htmlspecialchars(trim($_POST['events_dateformat_sidebar'], "\t\n "), ENT_QUOTES);
	$option['timeformat'] 				= $_POST['events_timeformat'];
	$option['timeformat_sidebar']		= $_POST['events_timeformat_sidebar'];
	$option['order']	 				= $_POST['events_order'];
	$option['order_archive'] 			= $_POST['events_order_archive'];
	$option['linktarget'] 				= $_POST['events_linktarget'];
	$option['localization'] 			= htmlspecialchars(trim($_POST['events_localization'], "\t\n "), ENT_QUOTES);
	update_option('events_config', $option);

	// Prepare Tracker settings
	if(isset($_POST['events_register'])) $tracker['register'] = 'Y';			
		else $tracker['register'] = 'N';
	if(isset($_POST['events_anonymous'])) $tracker['anonymous'] = 'Y';			
		else $tracker['anonymous'] = 'N';
	if($tracker['register'] == 'N' AND $buffer['register'] == 'Y') { events_send_data('Opt-out'); }
	update_option('events_tracker', $tracker);
	
	// Prepare Template settings
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
	
	// Prepare language settings
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
	$language['language_past'] 			= htmlspecialchars(trim($_POST['events_language_past'], "\t\n "), ENT_QUOTES);
	update_option('events_language', $language);
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
			wp_redirect('edit.php?page=wp-events&action=created');
		break;
		
		case "update" :
			wp_redirect('plugins.php?page=wp-events2&action=updated');
		break;
		
		case "field_error" :
			wp_redirect('edit.php?page=wp-events&action=field_error');
		break;
		
		case "error" :
			wp_redirect('edit.php?page=wp-events&action=error');
		break;
		
		case "no_access" :
			wp_redirect('plugins.php?page=wp-events2&action=no_access');
		break;
		
		case "delete-event" :
			wp_redirect('plugins.php?page=wp-events2&action=delete-event');
		break;
		
		case "delete-category" :
			wp_redirect('plugins.php?page=wp-events2&action=delete-category');
		break;
		
		case "uninstall" :
			wp_redirect('plugins.php?deactivate=true');
		break;
		
		case "category_new" :
			wp_redirect('plugins.php?page=wp-events2&action=category_new');
		break;
		
		case "category_field_error" :
			wp_redirect('plugins.php?page=wp-events2&action=category_field_error');
		break;
	}
}
?>