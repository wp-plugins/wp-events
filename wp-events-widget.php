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

		echo $before_widget;
		$url_parts = parse_url(get_bloginfo('home'));
		echo events_sidebar();
		echo $after_widget;
	}

	$widget_ops = array('classname' => 'widget_wp_events', 'description' => "Options are found on the 'settings > Events' panel!" );
	wp_register_sidebar_widget('Events', 'Events', 'widget_wp_events', $widget_ops);
}

/*-------------------------------------------------------------
 Name:      events_dashboard_widget

 Purpose:   Add a WordPress dashboard widget
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_dashboard_init() {
	wp_add_dashboard_widget( 'events_schedule_widget', 'Events', 'events_schedule_widget' );
}
 
add_action('wp_dashboard_setup', 'events_dashboard_init');
?>