<?php
/*-------------------------------------------------------------
 Name:      events_widget_sidebar_init

 Purpose:   Events widget for the sidebar
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_widget_sidebar_init() {

	if ( !function_exists('register_sidebar_widget') )
		return;
	if ( !function_exists('events_sidebar') )
		return;

	/*-------------------------------------------------------------
	 Name:      events_widget_list
	
	 Purpose:   Show the sidebar listing
	 Receive:   $args
	 Return:    -none-
	-------------------------------------------------------------*/
	function events_widget_list($args) {
		extract($args);

		echo $before_widget;
		$url_parts = parse_url(get_bloginfo('home'));
		echo events_sidebar();
		echo $after_widget;
	}

	/*-------------------------------------------------------------
	 Name:      events_widget_list_control
	
	 Purpose:   Allow settings for the list widget
	 Receive:   -none-
	 Return:    -none-
	-------------------------------------------------------------*/
	function events_widget_list_control() {
			echo '<p>Options are found <a href="options-general.php?page=wp-events4">here</a>.<br /><small>Save your other widget settings first!</small></p>';
	}

	$widget_list_ops = array('classname' => 'events_widget_list', 'description' => "Add a list of Events to your Sidebar" );
	wp_register_sidebar_widget('Events-List', 'Events List', 'events_widget_list', $widget_list_ops);
	wp_register_widget_control('Events-List', 'Events List', 'events_widget_list_control' );
}

/*-------------------------------------------------------------
 Name:      events_widget_dashboard_init

 Purpose:   Add a WordPress dashboard widget
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_widget_dashboard_init() {
	wp_add_dashboard_widget( 'events_schedule_widget', 'Events', 'events_widget_dashboard' );
	wp_add_dashboard_widget( 'meandmymac_rss_widget', 'Meandmymac.net RSS Feed', 'meandmymac_rss_widget' );
}

/*-------------------------------------------------------------
 Name:      events_widget_dashboard

 Purpose:   Create new or edit events from the dashboard
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_widget_dashboard() {
	global $wpdb, $userdata, $events_config;

	$timezone = get_option('gmt_offset')*3600;
	$url = get_option('siteurl');
	?>
		<style type="text/css" media="screen">
		#events_schedule_widget h4 {
			font-family: "Lucida Grande", Verdana, Arial, "Bitstream Vera Sans", sans-serif;
			float: left;
			width: 5.5em;
			clear: both;
			font-weight: normal;
			text-align: right;
			padding-top: 5px;
			font-size: 12px;
		}
		
		#events_schedule_widget h4 label {
			margin-right: 10px;
		}
		
		#events_schedule_widget .options-wrap,
		#events_schedule_widget .input-text-wrap,
		#events_schedule_widget .textarea-wrap {
			margin: 0 0 1em 5em;
		}
		</style>
	<?php

	$SQL2 = "SELECT * FROM ".$wpdb->prefix."events_categories ORDER BY id";
	$categories = $wpdb->get_results($SQL2);
	if($categories) { ?>
		<form method="post" action="index.php" name="events">
	  	   	<input type="hidden" name="events_submit" value="true" />
	    	<input type="hidden" name="events_username" value="<?php echo $userdata->display_name;?>" />
	    	<input type="hidden" name="events_event_id" value="<?php echo $event_edit_id;?>" />

			<h4 id="quick-post-title"><label for="events_title">Title</label></h4>
			<div class="input-text-wrap">
				<input type="text" name="events_title" id="title" tabindex="130" autocomplete="off" value="" maxlength="<?php echo $events_config['length'];?>" />
			</div>

			<h4 id="content-label"><label for="events_pre_event">Event</label></h4>
			<div class="textarea-wrap">
				<textarea name="events_pre_event" id="content" class="mceEditor" rows="3" cols="15" tabindex="131"></textarea>
			</div>

		    <h4 id="quick-post-title" class="options"><label for="events_sday">When</label></h4>
		    <div class="options-wrap">
				<input id="title" name="events_sday" class="search-input" type="text" size="4" maxlength="2" tabindex="132" /> /
				<select name="events_smonth" tabindex="133">
					<option value="01">January</option>
					<option value="02">February</option>
					<option value="03">March</option>
					<option value="04">April</option>
					<option value="05">May</option>
					<option value="06">June</option>
					<option value="07">July</option>
					<option value="08">August</option>
					<option value="09">September</option>
					<option value="10">October</option>
					<option value="11">November</option>
					<option value="12">December</option>
				</select> /
				<input name="events_syear" class="search-input" type="text" size="4" maxlength="4" value="" tabindex="134" />
			</div>

			<h4 id="quick-post-title" class="options"><label for="events_category">Category</label></h4>
		    <div class="options-wrap">
				<select name='events_category' tabindex="135">
				<?php foreach($categories as $category) { ?>
				    <option value="<?php echo $category->id; ?>" <?php if($category->id == $edit_event->category) { echo 'selected'; } ?>><?php echo $category->name; ?></option>
			    <?php } ?>
			    </select>
			</div>

			<h4 id="quick-post-title" class="options"><label for="events_priority">Sidebar</label></h4>
		    <div class="options-wrap">
				<select name="events_priority" tabindex="136">
				<?php if($edit_event->priority == "yes" OR $edit_event->priority == "") { ?>
					<option value="yes">Yes, show in the sidebar</option>
					<option value="no">No, on the event page only</option>
				<?php } else { ?>
					<option value="no">No, on the event page only</option>
					<option value="yes">Yes, show in the sidebar</option>
				<?php } ?>
				</select>
			</div>

			<h4 id="quick-post-title" class="options"><label for="events_archive">Archive</label></h4>
		    <div class="options-wrap">
				<select name="events_archive" tabindex="137">
					<?php if($edit_event->archive == "no" OR $edit_event->archive == "") { ?>
					<option value="no">No, delete one day after the event ends</option>
					<option value="yes">Yes, save event for the archive</option>
					<?php } else { ?>
					<option value="yes">Yes, save event for the archive</option>
					<option value="no">No, delete one day after the event ends</option>
					<?php } ?>
				</select>
			</div>

	    	<p class="submit">
				<input type="submit" name="submit_save" class="button-primary" value="Save event" tabindex="138" /> <span style="padding-left: 10px;"><a href="edit.php?page=wp-events">Advanced</a></span>
	    	</p>
		</form>
	<?php } else { ?>
		<span style="font-style: italic;">You should create atleast one category before adding events! <a href="plugins.php?page=wp-events2">Add a category now</a>.</span>
	<?php } ?>
<?php }

/*-------------------------------------------------------------
 Name:      meandmymac_rss_widget

 Purpose:   Shows the Meandmymac RSS feed on the dashboard
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
if(!function_exists('meandmymac_rss_widget')) {
	function meandmymac_rss_widget() {
		?>
			<style type="text/css" media="screen">
			#meandmymac_rss_widget .text-wrap {
				padding-top: 5px;
				margin: 0.5em;
				display: block;
			}
			</style>
		<?php
		$rss = meandmymac_rss('http://meandmymac.net/feed/');
		$loop = 1;
		foreach($rss as $key => $item) { ?>
				<div class="text-wrap">
					<a href="<?php echo $item['link']; ?>" target="_blank"><?php echo $item['title']; ?></a> on <?php echo $item['date']; ?>.
				</div>
	<?php
			$loop++;
		}
	}
}
?>