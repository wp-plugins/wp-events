<?php
#-------------------------------------------------------------
# DO NOT REMOVE ME!! THIS FILE IS NEEDED FOR THE PLUGIN!
# I HANDLE THE CALCULATION OF DATES AND OUTPUT OF THE PLUGIN!
#-------------------------------------------------------------

/*-------------------------------------------------------------
 Name:      events_countdown

 Purpose:   Calculates countdown times
 Receive:   $time_event, $message
 Return:	$output_countdown
-------------------------------------------------------------*/
function events_countdown($time_event, $message) {
	global $events_config;

  	$timezone = date("U") . $events_config['timezone'];
  	//$timezone = date("U") + (get_option('gmt_offset') * 3600);
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
		$output_countdown .= $events_config['language_today'].', '. $hours_left .':'. $minutes_left .' '.$events_config['language_hours'].'.';
	} else if ( $days_left == 0 and $hours_left == 0 ) {
		$output_countdown .= $events_config['language_today'].'. '.$events_config['in'].' '. $minutes_left .' '.$events_config['language_minutes'].'.';
	} else {
	  	$output_countdown .= $events_config['language_in'].' ';
		if($days_left == 1) {
			$output_countdown .= $days_left .' '.$events_config['language_day'].' ';
		} else {
			$output_countdown .= $days_left .' '.$events_config['language_days'].' ';
		}
		$output_countdown .= $events_config['language_and'].' '. $hours_left .':'. $minutes_left .' '.$events_config['language_hours'].'.';
	}
	return $output_countdown;
}

/*-------------------------------------------------------------
 Name:      events_archive

 Purpose:   Calculates the time since the event
 Receive:   $time_event
 Return:	$output_archive
-------------------------------------------------------------*/
function events_archive($time_event, $message) {
	global $events_config;

  	$timezone = date("U") . $events_config['timezone'];
  	//$timezone = date("U") + (get_option('gmt_offset') * 3600);
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
		$output_archive .= $events_config['language_today'].', '. $hours_ago .':'. $minutes_ago .' '.$events_config['language_hours'].' '.$events_config['language_ago'].'.';
	} else if ( $days_ago == 0 and $hours_ago == 0 ) {
		$output_archive .= $events_config['language_today'].'. '. $minutes_ago .' '.$events_config['language_minutes'].' '.$events_config['language_ago'].'.';
	} else {
		if($days_ago == 1) {
			$output_archive .= $days_ago .' '.$events_config['language_day'].' ';
		} else {
			$output_archive .= $days_ago .' '.$events_config['language_days'].' ';
		}
		$output_archive .= $events_config['language_and'].' '. $hours_ago .':'. $minutes_ago .' '.$events_config['language_hours'].' '.$events_config['language_ago'].'.';
	}
	return $output_archive;
}

/*-------------------------------------------------------------
 Name:      events_duration

 Purpose:   Calculates the duration of the event
 Receive:   $event_start, $event_end
 Return:	$output_duration
-------------------------------------------------------------*/
function events_duration($event_start, $event_end) {
	global $events_config;

//  	$timezone = date("U") . $events_config['timezone'];
  	$timezone = date("U") . get_option('gmt_offset');
  	$difference = $event_end - $timezone;
  	if ($difference < 0) $difference = 0;

 	$days_duration = floor($difference/60/60/24);
  	$hours_duration = floor(($difference - $days_duration*60*60*24)/60/60);
  	$minutes_duration = floor(($difference - $days_duration*60*60*24 - $hours_duration*60*60)/60);

	if($minutes_duration < "10") $minutes_duration = "0".$minutes_duration;
	if($hours_duration < "10") $hours_duration = "0".$hours_duration;

	$output_duration = '';
  	if (($days_duration == 0 and $hours_duration == 0 and $minutes_duration == 0) or ($event_start == $event_end)) {
		$output_duration .= 'No duration!';
	} else if ( $days_duration == 0 ) {
		$output_duration .= $hours_duration .':'. $minutes_duration .' '.$events_config['language_hours'].'.';
	} else if ( $days_duration == 0 and $hours_duration == 0 ) {
		$output_duration .= $minutes_duration .' '.$events_config['language_minutes'].'.';
	} else {
		if($days_duration == 1) {
			$output_duration .= $days_duration .' '.$events_config['language_day'].' ';
		} else {
			$output_duration .= $days_duration .' '.$events_config['language_days'].' ';
		}
		$output_duration .= $events_config['language_and'].' '. $hours_duration .':'. $minutes_duration .' '.$events_config['language_hours'].'.';
	}

	return $output_duration;
}

/*-------------------------------------------------------------
 Name:      events_sidebar

 Purpose:   Show events in the sidebar
 Receive:   -none-
 Return:	$output_sidebar
-------------------------------------------------------------*/
function events_sidebar() {
	global $wpdb, $events_config;

	$sidebar_header = $events_config['sidebar_h_template'];
	$sidebar_footer = $events_config['sidebar_f_template'];
	$sidebar_header = str_replace('%sidebar_title%', $events_config['language_s_title'], $sidebar_header);
	$output_sidebar = stripslashes(html_entity_decode($sidebar_header));
	if($events_config['order']){
		/* Fetch events */
		$SQL = "SELECT * FROM ".$wpdb->prefix."events WHERE priority = 'yes' AND thetime > ".date("U")." ORDER BY ".$events_config['order']." LIMIT ".$events_config['amount'];
		$events = $wpdb->get_results($SQL);
		/* Start processing data */
		if(count($events) == 0) {
			$output_sidebar .= '<em>'.$events_config['language_noevents'].'</em>';
		} else {
			foreach($events as $event) {
				/* Build event output */
				$sidebar_template = $events_config['sidebar_template'];
				if($event->title_link == 'Y') { $event->title = '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$event->title.'</a>'; }
				$sidebar_template = str_replace('%title%', substr($event->title, 0 , $events_config['sidelength']), $sidebar_template);
				$sidebar_template = str_replace('%event%', substr($event->pre_message, 0 , $events_config['sidelength']), $sidebar_template);
				if(strlen($event->link) > 0) { $sidebar_template = str_replace('%link%', '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$events_config['language_pagelink'].'</a>', $sidebar_template); }
				if(strlen($event->link) == 0) { $sidebar_template = str_replace('%link%', '', $sidebar_template); }
				$sidebar_template = str_replace('%starttime%', events_countdown($event->thetime, substr($event->post_message, 0 , $events_config['sidelength'])), $sidebar_template);
				$sidebar_template = str_replace('%date%', utf8_encode(strftime($events_config['dateformat_sidebar'], $event->thetime)), $sidebar_template);
				$sidebar_template = str_replace('%author%', $event->author, $sidebar_template);

				$output_sidebar .= stripslashes(html_entity_decode($sidebar_template));
			}
		}
	} else {
		/* Configuration is fuxored, output an error */
		$output_sidebar .= '<em style="color:#f00;">'.$events_config['language_e_config'].'.</em>';
	}
	$output_sidebar .= stripslashes(html_entity_decode($sidebar_footer));
	
	return $output_sidebar;
}

/*-------------------------------------------------------------
 Name:      events_page

 Purpose:   Create list of events for the template
 Receive:   $content
 Return:	$output_page
-------------------------------------------------------------*/
function events_page($content) {
	global $wpdb, $events_config;

	$page_header = $events_config['page_h_template'];
	$page_footer = $events_config['page_f_template'];
	$page_header = str_replace('%page_title%', $events_config['language_p_title'], $page_header);
	$output_page = stripslashes(html_entity_decode($page_header));
	if($events_config['order']){
		/* Current events */
		$SQL = "SELECT * FROM ".$wpdb->prefix."events WHERE thetime > ".date("U")." ORDER BY ".$events_config['order'];
		$events = $wpdb->get_results($SQL);
		/* Start processing data */
		if ( count($events) == 0 ) {
			$output_page .= '<em>'.$events_config['language_noevents'].'</em>';
		} else {
			foreach ( $events as $event ) {
				/* Build event output */
				$page_template = $events_config['page_template'];
				if($event->title_link == 'Y') { $event->title = '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$event->title.'</a>'; }
				$page_template = str_replace('%title%', $event->title, $page_template);
				$page_template = str_replace('%event%', $event->pre_message, $page_template);
				$page_template = str_replace('%after%', $event->post_message, $page_template);
				if(strlen($event->link) > 0) { $page_template = str_replace('%link%', '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$events_config['language_pagelink'].'</a>', $page_template); }
				if(strlen($event->link) == 0) { $page_template = str_replace('%link%', '', $page_template); }
				$page_template = str_replace('%starttime%', events_countdown($event->thetime, $event->post_message), $page_template);
				$page_template = str_replace('%endtime%', utf8_encode(strftime($events_config['dateformat_sidebar'], $event->theend)), $page_template);
				$page_template = str_replace('%duration%', events_duration($event->thetime, $event->theend), $page_template);
				$page_template = str_replace('%date%', utf8_encode(strftime($events_config['dateformat'], $event->thetime)), $page_template);
				$page_template = str_replace('%author%', $event->author, $page_template);
				
				$output_page .= stripslashes(html_entity_decode($page_template));
			}
		}
	} else {
		/* Configuration is fuxored, output an error */
		$output_page .= '<em style="color:#f00;">'.$events_config['language_e_config'].'.</em>';
	}
	$output_page .= stripslashes(html_entity_decode($page_footer));
	
	$content = preg_replace("/\[events_list\]/is", $output_page, $content);
	return $content;
}

/*-------------------------------------------------------------
 Name:      events_page_archive

 Purpose:   Create list of archived events for the template
 Receive:   $content
 Return:	$output_archive
-------------------------------------------------------------*/
function events_page_archive($content) {
	global $wpdb, $events_config;

	$archive_header = $events_config['archive_h_template'];
	$archive_footer = $events_config['archive_f_template'];
	$archive_header = str_replace('%archive_title%', $events_config['language_a_title'], $archive_header);
	$output_archive = stripslashes(html_entity_decode($archive_header));
	if($events_config['order_archive']){
		/* Archived events */
		$arSQL = "SELECT * FROM ".$wpdb->prefix."events WHERE archive='yes' AND thetime < ".date("U")." ORDER BY ".$events_config['order_archive'];
		$events = $wpdb->get_results($arSQL);
		/* Start processing data */
		if ( count($events) == 0 ) {
			$output_archive .= '<em>'.$events_config['language_noarchive'].'</em>';
		} else {
			foreach ( $events as $event ) {
				/* Build event output */
				$archive_template = $events_config['archive_template'];
				if($event->title_link == 'Y') { $event->title = '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$event->title.'</a>'; }
				$archive_template = str_replace('%title%', $event->title, $archive_template);
				$archive_template = str_replace('%event%', $event->pre_message, $archive_template);
				$archive_template = str_replace('%after%', $event->post_message, $archive_template);
				if(strlen($archive->link) > 0) { $archive_template = str_replace('%link%', '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$events_config['language_pagelink'].'</a>', $archive_template); }
				if(strlen($archive->link) == 0) { $archive_template = str_replace('%link%', '', $archive_template); }
				$archive_template = str_replace('%starttime%', events_archive($archive->thetime, $event->post_message), $archive_template);
				$archive_template = str_replace('%endtime%', utf8_encode(strftime($events_config['dateformat_sidebar'], $event->theend)), $archive_template);
				$archive_template = str_replace('%duration%', events_duration($event->thetime, $archive->theend), $archive_template);
				$archive_template = str_replace('%date%', utf8_encode(strftime($events_config['dateformat'], $event->thetime)), $archive_template);
				$archive_template = str_replace('%author%', $event->author, $archive_template);
				
				$output_archive .= stripslashes(html_entity_decode($archive_template));
			}
		}
	} else {
		/* Configuration is fuxored, output an error */
		$output_archive .= '<em style="color:#f00;">'.$events_config['language_e_config'].'.</em>';
	}
	$output_archive .= stripslashes(html_entity_decode($archive_footer));
	
	$content = preg_replace("/\[events_archive\]/is", $output_archive, $content);
	return $content;
}

/*-------------------------------------------------------------
 Name:      events_daily

 Purpose:   Create list of events for today on the template
 Receive:   $content
 Return:	$output_daily
-------------------------------------------------------------*/
function events_daily($content) {
	global $wpdb, $events_config;

	$daily_header = $events_config['daily_h_template'];
	$daily_footer = $events_config['daily_f_template'];
	$daily_header = str_replace('%daily_title%', $events_config['language_d_title'], $daily_header);
	$output_daily = stripslashes(html_entity_decode($daily_header));
	if($events_config['order']){
		// Todays events
		$daystart = date("U", mktime(0, 0, 0, date("m"),   date("d"),   date("Y")));
		$dayend = $daystart + 86400;
		$toSQL = "SELECT id, title, pre_message, post_message, link, thetime, theend, author FROM ".$wpdb->prefix."events WHERE thetime > ".$daystart." AND thetime < ".$dayend." ORDER BY ".$events_config['order'];
		$events = $wpdb->get_results($toSQL);
		// Start processing data
		if ( count($dailies) == 0 ) {
			$output_daily .= '<em>'.$events_config['language_nodaily'].'</em>';
		} else {
			foreach ( $events as $event ) {
				// Build event output
				$daily_template = $events_config['daily_template'];
				if($event->title_link == 'Y') { $event->title = '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$event->title.'</a>'; }
				$daily_template = str_replace('%title%', $event->title, $daily_template);
				$daily_template = str_replace('%event%', $event->pre_message, $daily_template);
				$daily_template = str_replace('%after%', $event->post_message, $daily_template);
				if(strlen($daily->link) > 0) { $daily_template = str_replace('%link%', '<a href="'.$event->link.'" target="'.$events_config['linktarget'].'">'.$events_config['language_pagelink'].'</a>', $daily_template); }
				if(strlen($daily->link) == 0) { $daily_template = str_replace('%link%', '', $daily_template); }
				$daily_template = str_replace('%starttime%', events_countdown($event->thetime, $event->post_message), $daily_template);
				$daily_template = str_replace('%endtime%', utf8_encode(strftime($events_config['dateformat_sidebar'], $event->theend)), $daily_template);
				$daily_template = str_replace('%duration%', events_duration($event->thetime, $event->theend), $daily_template);
				$daily_template = str_replace('%date%', utf8_encode(strftime($events_config['dateformat'], $event->thetime)), $daily_template);
				$daily_template = str_replace('%author%', $event->author, $daily_template);
				
				$output_daily .= stripslashes(html_entity_decode($daily_template));
			}
		}
	} else {
		// Configuration is fuxored, output an error
		$output_daily .= '<em style="color:#f00;">'.$events_config['language_e_config'].'.</em>';
	}
	$output_daily .= stripslashes(html_entity_decode($daily_footer));
	
	$content = preg_replace("/\[events_today\]/is", $output_daily, $content);
	return $content;
}

?>