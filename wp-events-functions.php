<?php
/*-------------------------------------------------------------
 Name:      events_countdown

 Purpose:   Calculates countdown times
 Receive:   $time_event, $message
 Return:	$output_countdown
-------------------------------------------------------------*/
function events_countdown($time_event, $message) {
	global $events_config, $events_language;

  	$timezone = date("U") . $events_config['timezone'];
  	$difference = $time_event - $timezone;
  	if ($difference < 0) $difference = 0;

 	$days_left = floor($difference/60/60/24);
  	$hours_left = floor(($difference - $days_left*60*60*24)/60/60);
  	$minutes_left = floor(($difference - $days_left*60*60*24 - $hours_left*60*60)/60);

	if($minutes_left < "10") $minutes_left = "0".$minutes_left;
	if($hours_left < "10") $hours_left = "0".$hours_left;

	$output_countdown = '';
  	if ( $days_left == 0 and $hours_left == 0 and $minutes_left == 0 ) {
		$output_countdown .= $message;
	} else if ( $days_left == 0 ) {
		$output_countdown .= $events_language['language_today'].', '. $hours_left .':'. $minutes_left .' '.$events_language['language_hours'].'.';
	} else if ( $days_left == 0 and $hours_left == 0 ) {
		$output_countdown .= $events_language['language_today'].'. '.$events_config['in'].' '. $minutes_left .' '.$events_language['language_minutes'].'.';
	} else {
	  	$output_countdown .= $events_language['language_in'].' ';
		if($days_left == 1) {
			$output_countdown .= $days_left .' '.$events_language['language_day'].' ';
		} else {
			$output_countdown .= $days_left .' '.$events_language['language_days'].' ';
		}
		$output_countdown .= $events_language['language_and'].' '. $hours_left .':'. $minutes_left .' '.$events_language['language_hours'].'.';
	}
	return $output_countdown;
}

/*-------------------------------------------------------------
 Name:      events_countup

 Purpose:   Calculates the time since the event
 Receive:   $time_event, $message
 Return:	$output_archive
-------------------------------------------------------------*/
function events_countup($time_event, $message) {
	global $events_config, $events_language;

  	$timezone = date("U") . $events_config['timezone'];
  	$difference = $timezone - $time_event;
  	if ($difference < 0) $difference = 0;

 	$days_ago = floor($difference/60/60/24);
  	$hours_ago = floor(($difference - $days_ago*60*60*24)/60/60);
  	$minutes_ago = floor(($difference - $days_ago*60*60*24 - $hours_ago*60*60)/60);

	if($minutes_ago < "10") $minutes_ago = "0".$minutes_ago;
	if($hours_ago < "10") $hours_ago = "0".$hours_ago;

	$output_archive = '';
  	if ( $days_ago == 0 and $hours_ago == 0 and $minutes_ago == 0 ) {
		$output_archive .= $message;
	} else if ( $days_ago == 0 ) {
		$output_archive .= $events_language['language_today'].', '. $hours_ago .':'. $minutes_ago .' '.$events_language['language_hours'].' '.$events_language['language_ago'].'.';
	} else if ( $days_ago == 0 and $hours_ago == 0 ) {
		$output_archive .= $events_language['language_today'].'. '. $minutes_ago .' '.$events_language['language_minutes'].' '.$events_language['language_ago'].'.';
	} else {
		if($days_ago == 1) {
			$output_archive .= $days_ago .' '.$events_language['language_day'].' ';
		} else {
			$output_archive .= $days_ago .' '.$events_language['language_days'].' ';
		}
		$output_archive .= $events_language['language_and'].' '. $hours_ago .':'. $minutes_ago .' '.$events_language['language_hours'].' '.$events_language['language_ago'].'.';
	}
	return $output_archive;
}

/*-------------------------------------------------------------
 Name:      events_duration

 Purpose:   Calculates the duration of the event
 Receive:   $event_start, $event_end, $allday
 Return:	$output_duration
-------------------------------------------------------------*/
function events_duration($event_start, $event_end, $allday) {
	global $events_config, $events_language;

  	$timezone = date("U") . $events_config['timezone'];
  	$difference = $event_end - $timezone;
  	if ($difference < 0) $difference = 0;

 	$days_duration = floor($difference/60/60/24);
  	$hours_duration = floor(($difference - $days_duration*60*60*24)/60/60);
  	$minutes_duration = floor(($difference - $days_duration*60*60*24 - $hours_duration*60*60)/60);

	if($minutes_duration < "10") $minutes_duration = "0".$minutes_duration;
	if($hours_duration < "10") $hours_duration = "0".$hours_duration;

	$output_duration = '';
  	if ($allday == 'Y') {
		$output_duration .= $events_language['language_allday'];
	} else if (($days_duration == 0 and $hours_duration == 0 and $minutes_duration == 0) or ($event_start == $event_end)) {
		$output_duration .= $events_language['language_noduration'];
	} else if ($days_duration == 0) {
		$output_duration .= $hours_duration .':'. $minutes_duration .' '.$events_language['language_hours'].'.';
	} else if ($days_duration == 0 and $hours_duration == 0) {
		$output_duration .= $minutes_duration .' '.$events_language['language_minutes'].'.';
	} else {
		if($days_duration == 1) {
			$output_duration .= $days_duration .' '.$events_language['language_day'].' ';
		} else {
			$output_duration .= $days_duration .' '.$events_language['language_days'].' ';
		}
		$output_duration .= $events_language['language_and'].' '. $hours_duration .':'. $minutes_duration .' '.$events_language['language_hours'].'.';
	}

	return $output_duration;
}

/*-------------------------------------------------------------
 Name:      events_sidebar

 Purpose:   Show events in the sidebar, also used for widget
 Receive:   -none-
 Return:	$output_sidebar
-------------------------------------------------------------*/
function events_sidebar() {
	global $wpdb, $events_config, $events_language, $events_template;

	$sidebar_header = $events_template['sidebar_h_template'];
	$sidebar_footer = $events_template['sidebar_f_template'];
	$output_sidebar = stripslashes(html_entity_decode($sidebar_header));
	if($events_config['order']){
		/* Fetch events */
		$SQL = "SELECT * FROM `".$wpdb->prefix."events` WHERE `priority` = 'yes' AND `thetime` > ".date("U")." ORDER BY ".$events_config['order']." LIMIT ".$events_config['amount'];
		$events = $wpdb->get_results($SQL);
		/* Start processing data */
		if(count($events) == 0) {
			$output_sidebar .= '<em>'.$events_language['language_noevents'].'</em>';
		} else {
			foreach($events as $event) {
				/* Build event output */
				$sidebar_template = $events_template['sidebar_template'];
				if($event->title_link == 'Y') { $event->title = '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$event->title.'</a>'; }
				$sidebar_template = str_replace('%title%', substr($event->title, 0 , $events_config['sidelength']), $sidebar_template);
				$sidebar_template = str_replace('%event%', substr($event->pre_message, 0 , $events_config['sidelength']), $sidebar_template);
				if(strlen($event->link) > 0) { $sidebar_template = str_replace('%link%', '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$events_language['language_pagelink'].'</a>', $sidebar_template); }
				if(strlen($event->link) == 0) { $sidebar_template = str_replace('%link%', '', $sidebar_template); }
				$sidebar_template = str_replace('%starttime%', events_countdown($event->thetime, substr($event->post_message, 0 , $events_config['sidelength'])), $sidebar_template);
				$sidebar_template = str_replace('%date%', str_replace('00:00', '', utf8_encode(strftime($events_config['dateformat_sidebar'], $event->thetime))), $sidebar_template);
				$sidebar_template = str_replace('%author%', $event->author, $sidebar_template);
				if(strlen($event->location) != 0) { $sidebar_template = str_replace('%location%', $events_template['location_seperator'].$event->location, $sidebar_template); }
				if(strlen($event->location) == 0) { $sidebar_template = str_replace('%location%', '', $sidebar_template); }

				$output_sidebar .= stripslashes(html_entity_decode($sidebar_template));
			}
		}
	} else {
		/* Configuration is fuxored, output an error */
		$output_sidebar .= '<em style="color:#f00;">'.$events_language['language_e_config'].'.</em>';
	}
	$output_sidebar .= stripslashes(html_entity_decode($sidebar_footer));
	
	return $output_sidebar;
}

/*-------------------------------------------------------------
 Name:      events_page

 Purpose:   Create list of events for the template using shortcodes
 Receive:   $atts, $content
 Return:	$output_page
-------------------------------------------------------------*/
function events_page($atts, $content = null) {
	global $wpdb, $events_config, $events_language, $events_template;
	
	if(empty($atts['amount'])) $amount = ""; 
		else $amount = " LIMIT $atts[amount]";
		
	if(empty($atts['order'])) $order = $events_config['order']; 
		else $amount = $atts['order'];
		
	$page_header = $events_template['page_h_template'];
	$page_footer = $events_template['page_f_template'];
	$output_page = stripslashes(html_entity_decode($page_header));
	if($events_config['order']){
		/* Current events */
		$SQL = "SELECT * FROM `".$wpdb->prefix."events` WHERE `thetime` > ".date('U')." ORDER BY $order$amount";
		$events = $wpdb->get_results($SQL);
		/* Start processing data */
		if ( count($events) == 0 ) {
			$output_page .= '<em>'.$events_language['language_noevents'].'</em>';
		} else {
			foreach ( $events as $event ) {
				/* Build event output */
				$page_template = $events_template['page_template'];
				if($event->title_link == 'Y') { $event->title = '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$event->title.'</a>'; }
				$page_template = str_replace('%title%', $event->title, $page_template);
				$page_template = str_replace('%event%', $event->pre_message, $page_template);
				$page_template = str_replace('%after%', $event->post_message, $page_template);
				if(strlen($event->link) > 0) { $page_template = str_replace('%link%', '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$events_language['language_pagelink'].'</a>', $page_template); }
				if(strlen($event->link) == 0) { $page_template = str_replace('%link%', '', $page_template); }
				$page_template = str_replace('%starttime%', events_countdown($event->thetime, $event->post_message), $page_template);
				$page_template = str_replace('%endtime%', utf8_encode(strftime($events_config['dateformat_sidebar'], $event->theend)), $page_template);
				$page_template = str_replace('%duration%', events_duration($event->thetime, $event->theend, $event->allday), $page_template);
				$page_template = str_replace('%date%', str_replace('00:00', '', utf8_encode(strftime($events_config['dateformat'], $event->thetime))), $page_template);
				$page_template = str_replace('%author%', $event->author, $page_template);
				if(strlen($event->location) != 0) { $page_template = str_replace('%location%', $events_template['location_seperator'].$event->location, $page_template); }
				if(strlen($event->location) == 0) { $page_template = str_replace('%location%', '', $page_template); }
				
				$output_page .= stripslashes(html_entity_decode($page_template));
			}
		}
	} else {
		/* Configuration is fuxored, output an error */
		$output_page .= '<em style="color:#f00;">'.$events_language['language_e_config'].'.</em>';
	}
	$output_page .= stripslashes(html_entity_decode($page_footer));
	
	return $output_page;
}

/*-------------------------------------------------------------
 Name:      events_archive

 Purpose:   Create list of archived events for the template using shortcodes
 Receive:   $atts, $content
 Return:	$output_archive
-------------------------------------------------------------*/
function events_archive($atts, $content = null) {
	global $wpdb, $events_config, $events_language, $events_template;

	if(empty($atts['amount'])) $amount = ""; 
		else $amount = " LIMIT $atts[amount]";
		
	if(empty($atts['order'])) $order = $events_config['order_archive']; 
		else $amount = $atts['order'];
		
	$archive_header = $events_template['archive_h_template'];
	$archive_footer = $events_template['archive_f_template'];
	$output_archive = stripslashes(html_entity_decode($archive_header));
	if($events_config['order_archive']){
		/* Archived events */
		$arSQL = "SELECT * FROM `".$wpdb->prefix."events` WHERE `archive` = 'yes' AND `thetime` < ".date('U')." ORDER BY $order$amount";
		$events = $wpdb->get_results($arSQL);
		/* Start processing data */
		if ( count($events) == 0 ) {
			$output_archive .= '<em>'.$events_language['language_noarchive'].'</em>';
		} else {
			foreach ( $events as $event ) {
				/* Build event output */
				$page_template = $events_template['archive_template'];
				if($event->title_link == 'Y') { $event->title = '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$event->title.'</a>'; }
				$page_template = str_replace('%title%', $event->title, $page_template);
				$page_template = str_replace('%event%', $event->pre_message, $page_template);
				$page_template = str_replace('%after%', $event->post_message, $page_template);
				if(strlen($event->link) > 0) { $page_template = str_replace('%link%', '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$events_language['language_pagelink'].'</a>', $page_template); }
				if(strlen($event->link) == 0) { $page_template = str_replace('%link%', '', $page_template); }
				$page_template = str_replace('%starttime%', events_countup($archive->thetime, $event->post_message), $page_template);
				$page_template = str_replace('%endtime%', utf8_encode(strftime($events_config['dateformat_sidebar'], $event->theend)), $page_template);
				$page_template = str_replace('%duration%', events_duration($event->thetime, $event->theend, $event->allday), $page_template);
				$page_template = str_replace('%date%', str_replace('00:00', '', utf8_encode(strftime($events_config['dateformat'], $event->thetime))), $page_template);
				$page_template = str_replace('%author%', $event->author, $page_template);
				if(strlen($event->location) != 0) { $page_template = str_replace('%location%', $events_template['location_seperator'].$event->location, $page_template); }
				if(strlen($event->location) == 0) { $page_template = str_replace('%location%', '', $page_template); }
				
				$output_archive .= stripslashes(html_entity_decode($page_template));
			}
		}
	} else {
		/* Configuration is fuxored, output an error */
		$output_archive .= '<em style="color:#f00;">'.$events_language['language_e_config'].'.</em>';
	}
	$output_archive .= stripslashes(html_entity_decode($archive_footer));
	
	return $output_archive;
}

/*-------------------------------------------------------------
 Name:      events_today

 Purpose:   Create list of events for today on the template using shortcodes
 Receive:   $atts, $content
 Return:	$output_daily
-------------------------------------------------------------*/
function events_today($atts, $content = null) {
	global $wpdb, $events_config, $events_language, $events_template;

	if(empty($atts['amount'])) $amount = ""; 
		else $amount = " LIMIT $atts[amount]";
		
	if(empty($atts['order'])) $order = $events_config['order']; 
		else $amount = $atts['order'];
		
	$daily_header = $events_template['daily_h_template'];
	$daily_footer = $events_template['daily_f_template'];
	$output_daily = stripslashes(html_entity_decode($daily_header));
	if($events_config['order']){
		// Todays events
		$daystart = date("U", mktime(0, 0, 0, date("m"),   date("d"),   date("Y")));
		$dayend = $daystart + 86400;
		$toSQL = "SELECT * FROM `".$wpdb->prefix."events` WHERE `thetime` >= ".$daystart." AND `thetime` <= ".$dayend." ORDER BY $order$amount";
		$events = $wpdb->get_results($toSQL);
		// Start processing data
		if ( count($events) == 0 ) {
			$output_daily .= '<em>'.$events_language['language_nodaily'].'</em>';
		} else {
			foreach ( $events as $event ) {
				// Build event output
				$page_template = $events_template['daily_template'];
				if($event->title_link == 'Y') { $event->title = '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$event->title.'</a>'; }
				$page_template = str_replace('%title%', $event->title, $page_template);
				$page_template = str_replace('%event%', $event->pre_message, $page_template);
				$page_template = str_replace('%after%', $event->post_message, $page_template);
				if(strlen($event->link) > 0) { $page_template = str_replace('%link%', '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$events_language['language_pagelink'].'</a>', $page_template); }
				if(strlen($event->link) == 0) { $page_template = str_replace('%link%', '', $page_template); }
				$page_template = str_replace('%starttime%', events_countdown($event->thetime, $event->post_message), $page_template);
				$page_template = str_replace('%endtime%', utf8_encode(strftime($events_config['dateformat_sidebar'], $event->theend)), $page_template);
				$page_template = str_replace('%duration%', events_duration($event->thetime, $event->theend, $event->allday), $page_template);
				$page_template = str_replace('%date%', str_replace('00:00', '', utf8_encode(strftime($events_config['dateformat'], $event->thetime))), $page_template);
				$page_template = str_replace('%author%', $event->author, $page_template);
				if(strlen($event->location) != 0) { $page_template = str_replace('%location%', $events_template['location_seperator'].$event->location, $page_template); }
				if(strlen($event->location) == 0) { $page_template = str_replace('%location%', '', $page_template); }
				
				$output_daily .= stripslashes(html_entity_decode($page_template));
			}
		}
	} else {
		// Configuration is fuxored, output an error
		$output_daily .= '<em style="color:#f00;">'.$events_language['language_e_config'].'.</em>';
	}
	$output_daily .= stripslashes(html_entity_decode($daily_footer));
	
	return $output_daily;
}

?>