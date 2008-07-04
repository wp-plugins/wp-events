<?php
/*
Plugin Name: Events Widget 
Plugin URI: http://meandmymac.net/plugins/events/
Description: Adds a sidebar widget to list events from the Events plugin.
Author: Ulisse Perusin <uli.peru@gmail.com>
Version: 1.1
Author URI: http://ulisse.wordpress.com
*/

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

	register_sidebar_widget(array('Events Widget', 'widgets'), 'widget_wp_events');
}

add_action('widgets_init', 'widget_wp_events_init');

?>