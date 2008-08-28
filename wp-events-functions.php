<?php
/*-------------------------------------------------------------
 Name:      events_countdown

 Purpose:   Calculates countdown times
 Receive:   $time_start, $time_end, $message, $allday
 Return:	$output_countdown
-------------------------------------------------------------*/
function events_countdown($time_start, $time_end, $message, $allday) {
	global $events_config, $events_language;

  	$timezone = date("U") . $events_config['timezone'];
  	$difference = $time_start - $timezone;
  	if ($difference < 0) $difference = 0;

 	$days_left = floor($difference/60/60/24);
  	$hours_left = floor(($difference - $days_left*60*60*24)/60/60);
  	$minutes_left = floor(($difference - $days_left*60*60*24 - $hours_left*60*60)/60);

	if($minutes_left < "10") $minutes_left = "0".$minutes_left;
	if($hours_left < "10") $hours_left = "0".$hours_left;

	$output_countdown = '';
  	if ($allday == 'Y') {
		$output_countdown .= $events_language['language_allday'];
  	} else if ( $days_left == 0 and $hours_left == 0 and $minutes_left == 0 and date('U') > $time_end) {
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
 Receive:   $time_start, $time_end, $message
 Return:	$output_archive
-------------------------------------------------------------*/
function events_countup($time_start, $time_end, $message) {
	global $events_config, $events_language;

  	$timezone = date("U") . $events_config['timezone'];
  	$difference = $timezone - $time_start;
  	if ($difference < 0) $difference = 0;

 	$days_ago = floor($difference/60/60/24);
  	$hours_ago = floor(($difference - $days_ago*60*60*24)/60/60);
  	$minutes_ago = floor(($difference - $days_ago*60*60*24 - $hours_ago*60*60)/60);

	if($minutes_ago < "10") $minutes_ago = "0".$minutes_ago;
	if($hours_ago < "10") $hours_ago = "0".$hours_ago;

	$output_archive = '';
  	if ( $days_ago == 0 and $hours_ago == 0 and $minutes_ago == 0 and date('U') > $time_end ) {
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
  	$difference = $event_end - $event_start;
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
		$events = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."events` WHERE `priority` = 'yes' AND `thetime` > ".date("U")." ORDER BY ".$events_config['order']." LIMIT ".$events_config['amount']);
		/* Start processing data */
		if(count($events) == 0) {
			$output_sidebar .= '<em>'.$events_language['language_noevents'].'</em>';
		} else {
			foreach($events as $event) {
				$get_category = $wpdb->get_row("SELECT name FROM `".$wpdb->prefix."events_categories` WHERE `id` = $event->category");
				
				$template = $events_template['sidebar_template'];
				if($event->title_link == 'Y') { $event->title = '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$event->title.'</a>'; }
				$template = str_replace('%title%', substr($event->title, 0 , $events_config['sidelength']), $template);
				$template = str_replace('%event%', substr($event->pre_message, 0 , $events_config['sidelength']), $template);
				
				if(strlen($event->link) > 0) { $template = str_replace('%link%', '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$events_language['language_pagelink'].'</a>', $template); }
				if(strlen($event->link) == 0) { $template = str_replace('%link%', '', $template); }
				
				$template = str_replace('%countdown%', events_countdown($event->thetime, $event->theend, substr($event->post_message, 0 , $events_config['sidelength']), $event->allday), $template);
				$template = str_replace('%starttime%', str_replace('00:00', '', utf8_encode(strftime($events_config['timeformat_sidebar'], $event->thetime))), $template);
				$template = str_replace('%startdate%', utf8_encode(strftime($events_config['dateformat_sidebar'], $event->thetime)), $template);
				
				$template = str_replace('%author%', $event->author, $template);
				$template = str_replace('%category%', $get_category->name, $template);
				
				if(strlen($event->location) != 0) { $template = str_replace('%location%', $events_template['location_seperator'].$event->location, $template); }
				if(strlen($event->location) == 0) { $template = str_replace('%location%', '', $template); }

				$output_sidebar .= stripslashes(html_entity_decode($template));
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
 Name:      events_list

 Purpose:   Create list of events for the template using shortcodes
 Receive:   $atts, $content
 Return:	$output_page
-------------------------------------------------------------*/
function events_list($atts, $content = null) {
	global $wpdb, $events_config, $events_language, $events_template;
	
	if(empty($atts['amount'])) $amount = ""; 
		else $amount = " LIMIT $atts[amount]";
		
	if(empty($atts['order'])) $order = $events_config['order']; 
		else $amount = $atts['order'];
		
	if(empty($atts['category'])) $category = ''; 
		else $category = " AND `category` = '$atts[category]'";
		
	if(empty($atts['event'])) $one_event = ''; 
		else $one_event = " AND `id` = '$atts[event]'";
		
	$page_header = $events_template['page_h_template'];
	$page_footer = $events_template['page_f_template'];
	$output_page = stripslashes(html_entity_decode($page_header));
	if($events_config['order']){
		$events = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."events` WHERE `thetime` > ".date('U')."$category$one_event ORDER BY $order$amount");
		if ( count($events) == 0 ) {
			$output_page .= '<em>'.$events_language['language_noevents'].'</em>';
		} else {
			foreach ( $events as $event ) {
				$get_category = $wpdb->get_row("SELECT name FROM `".$wpdb->prefix."events_categories` WHERE `id` = $event->category");
				
				$template = $events_template['page_template'];
				if($event->title_link == 'Y') { $event->title = '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$event->title.'</a>'; }
				$template = str_replace('%title%', $event->title, $template);
				$template = str_replace('%event%', $event->pre_message, $template);
				$template = str_replace('%after%', $event->post_message, $template);
				
				if(strlen($event->link) > 0) { $template = str_replace('%link%', '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$events_language['language_pagelink'].'</a>', $template); }
				if(strlen($event->link) == 0) { $template = str_replace('%link%', '', $template); }
				
				$template = str_replace('%countdown%', events_countdown($event->thetime, $event->theend, $event->post_message, $event->allday), $template);
				$template = str_replace('%duration%', events_duration($event->thetime, $event->theend, $event->allday), $template);
				
				$template = str_replace('%startdate%', utf8_encode(strftime($events_config['dateformat'], $event->thetime)), $template);
				$template = str_replace('%starttime%', str_replace('00:00', '', utf8_encode(strftime($events_config['timeformat'], $event->thetime))), $template);
				
				if($event->thetime == $event->theend and $events_config['hideend'] == 'hide') {
					$template = str_replace('%enddate%', '', $template);					
					$template = str_replace('%endtime%', '', $template);					
				} else { 
					$template = str_replace('%enddate%', utf8_encode(strftime($events_config['dateformat'], $event->theend)), $template);
					$template = str_replace('%endtime%', str_replace('00:00', '', utf8_encode(strftime($events_config['timeformat'], $event->thetime))), $template);
				}
				
				$template = str_replace('%author%', $event->author, $template);
				$template = str_replace('%category%', $get_category->name, $template);
				
				if(strlen($event->location) != 0) { $template = str_replace('%location%', $events_template['location_seperator'].$event->location, $template); }
				if(strlen($event->location) == 0) { $template = str_replace('%location%', '', $template); }
				
				$output_page .= stripslashes(html_entity_decode($template));
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
		
	if(empty($atts['category'])) $category = ''; 
		else $category = " AND `category` = '$atts[category]'";
		
	$archive_header = $events_template['archive_h_template'];
	$archive_footer = $events_template['archive_f_template'];
	$output_archive = stripslashes(html_entity_decode($archive_header));
	if($events_config['order_archive']){
		/* Archived events */
		$events = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."events` WHERE `archive` = 'yes' AND `thetime` < ".date('U')."$category ORDER BY $order$amount");
		/* Start processing data */
		if ( count($events) == 0 ) {
			$output_archive .= '<em>'.$events_language['language_noarchive'].'</em>';
		} else {
			foreach ( $events as $event ) {
				$get_category = $wpdb->get_row("SELECT name FROM `".$wpdb->prefix."events_categories` WHERE `id` = $event->category");

				$template = $events_template['archive_template'];
				if($event->title_link == 'Y') { $event->title = '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$event->title.'</a>'; }
				$template = str_replace('%title%', $event->title, $template);
				$template = str_replace('%event%', $event->pre_message, $template);
				$template = str_replace('%after%', $event->post_message, $template);
				
				if(strlen($event->link) > 0) { $template = str_replace('%link%', '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$events_language['language_pagelink'].'</a>', $template); }
				if(strlen($event->link) == 0) { $template = str_replace('%link%', '', $template); }
				
				$template = str_replace('%countup%', events_countup($event->thetime, $event->theend, $event->post_message, $event->allday), $template);
				$template = str_replace('%duration%', events_duration($event->thetime, $event->theend, $event->allday), $template);
				
				$template = str_replace('%startdate%', utf8_encode(strftime($events_config['dateformat'], $event->thetime)), $template);
				$template = str_replace('%starttime%', str_replace('00:00', '', utf8_encode(strftime($events_config['timeformat'], $event->thetime))), $template);
				
				if($event->thetime == $event->theend and $events_config['hideend'] == 'show') {
					$template = str_replace('%enddate%', '', $template);					
					$template = str_replace('%endtime%', '', $template);					
				} else { 
					$template = str_replace('%enddate%', utf8_encode(strftime($events_config['dateformat'], $event->theend)), $template);
					$template = str_replace('%endtime%', str_replace('00:00', '', utf8_encode(strftime($events_config['timeformat'], $event->thetime))), $template);
				}
				
				$template = str_replace('%author%', $event->author, $template);
				$template = str_replace('%category%', $get_category->name, $template);
				
				if(strlen($event->location) != 0) { $template = str_replace('%location%', $events_template['location_seperator'].$event->location, $template); }
				if(strlen($event->location) == 0) { $template = str_replace('%location%', '', $template); }
				
				$output_archive .= stripslashes(html_entity_decode($template));
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
		
	if(empty($atts['category'])) $category = ''; 
		else $category = " AND `category` = '$atts[category]'";
		
	$daily_header = $events_template['daily_h_template'];
	$daily_footer = $events_template['daily_f_template'];
	$output_daily = stripslashes(html_entity_decode($daily_header));
	if($events_config['order']){
		// Todays events
		$daynow = date("U");
		$daystart = date("U", mktime(0, 0, 0, date("m"),   date("d"),   date("Y")));
		$dayend = $daystart + 86400;
		$events = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."events` WHERE (`thetime` >= ".$daystart." AND `theend` <= ".$dayend.") OR (".$daynow." >= `thetime` AND ".$daynow." <= `theend`)$category ORDER BY $order$amount");
		// Start processing data
		if ( count($events) == 0 ) {
			$output_daily .= '<em>'.$events_language['language_nodaily'].'</em>';
		} else {
			foreach ( $events as $event ) {
				$get_category = $wpdb->get_row("SELECT name FROM `".$wpdb->prefix."events_categories` WHERE `id` = $event->category");

				$template = $events_template['daily_template'];
				if($event->title_link == 'Y') { $event->title = '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$event->title.'</a>'; }
				$template = str_replace('%title%', $event->title, $template);
				$template = str_replace('%event%', $event->pre_message, $template);
				$template = str_replace('%after%', $event->post_message, $template);
				
				if(strlen($event->link) > 0) { $template = str_replace('%link%', '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$events_language['language_pagelink'].'</a>', $template); }
				if(strlen($event->link) == 0) { $template = str_replace('%link%', '', $template); }
				
				$template = str_replace('%countdown%', events_countdown($event->thetime, $event->post_message, $event->allday), $template);
				$template = str_replace('%duration%', events_duration($event->thetime, $event->theend, $event->allday), $template);
				
				$template = str_replace('%startdate%', utf8_encode(strftime($events_config['dateformat'], $event->thetime)), $template);
				$template = str_replace('%starttime%', str_replace('00:00', '', utf8_encode(strftime($events_config['timeformat'], $event->thetime))), $template);
				
				if($event->thetime == $event->theend and $events_config['hideend'] == 'show') {
					$template = str_replace('%enddate%', '', $template);					
					$template = str_replace('%endtime%', '', $template);					
				} else { 
					$template = str_replace('%enddate%', utf8_encode(strftime($events_config['dateformat'], $event->theend)), $template);
					$template = str_replace('%endtime%', str_replace('00:00', '', utf8_encode(strftime($events_config['timeformat'], $event->thetime))), $template);
				}
				
				$template = str_replace('%author%', $event->author, $template);
				$template = str_replace('%category%', $get_category->name, $template);
				
				if(strlen($event->location) != 0) { $template = str_replace('%location%', $events_template['location_seperator'].$event->location, $template); }
				if(strlen($event->location) == 0) { $template = str_replace('%location%', '', $template); }
				
				$output_daily .= stripslashes(html_entity_decode($template));
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