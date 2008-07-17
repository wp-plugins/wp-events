<?php
/*-------------------------------------------------------------
 Name:      widget_wp_events_init

 Purpose:   Events widget for the sidebar
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function widget_wp_events_init() {

	if ( !function_exists('register_sidebar_widget') )
		return;
	if ( !function_exists('events_sidebar') )
		return;

	function widget_wp_events($args) {
		extract($args);

		echo $before_widget . $before_title . $after_title;
		$url_parts = parse_url(get_bloginfo('home'));
		echo events_sidebar();
		echo $after_widget;
	}

	$widget_ops = array('classname' => 'widget_wp_events', 'description' => "Options are found on the 'settings > Event' panel!" );
	wp_register_sidebar_widget('Events', 'Events', 'widget_wp_events', $widget_ops);
}
?>