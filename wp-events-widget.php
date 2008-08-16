<?php
function widget_wp_events_init() {

	if ( !function_exists('register_sidebar_widget') )
		return;
	if ( !function_exists('events_sidebar') )
		return;

	function widget_wp_events($args) {
		extract($args);

		$options = get_option('widget_wp_events');
		$title = $options['title'];

		echo $before_widget;
		$url_parts = parse_url(get_bloginfo('home'));
		echo events_sidebar();
		echo $after_widget;
	}

	function widget_wp_events_control() {
		$options = get_option('widget_wp_events');
		if ( !is_array($options) )
			$options = array('title'=>'', 'buttontext'=>__('Events Widget', 'widgets'));
		if ( $_POST['wp_events-submit'] ) {

			$options['title'] = strip_tags(stripslashes($_POST['wp_events-title']));
			update_option('widget_wp_events', $options);
		}

		$title = htmlspecialchars($options['title'], ENT_QUOTES);
		
		echo '<p style="text-align:right;"><label for="wp_events-title">' . __('Title:') . ' <input style="width: 200px;" id="wp_events-title" name="wp_events-title" type="text" value="'.$title.'" /></label></p>';
		echo '<input type="hidden" id="wp_events-submit" name="wp_events-submit" value="1" />';
	}
	register_sidebar_widget(array('Events Widget', 'widgets'), 'widget_wp_events');
	register_widget_control(array('Events Widget', 'widgets'), 'widget_wp_events_control', 300, 100);
}

add_action('widgets_init', 'widget_wp_events_init');

?>