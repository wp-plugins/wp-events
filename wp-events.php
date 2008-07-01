<?php
/*
Plugin Name: Events
Plugin URI: http://www.sothq.net/projects/events-plugin/
Description: This plugin enables the user to show a list of events with a static countdown to date.
Author: Arnan de Gans
Version: 1.0
Author URI: http://www.sothq.net/
*/

#---------------------------------------------------
# Load other plugin files and configuration
#---------------------------------------------------
include_once(ABSPATH.'wp-content/plugins/wp-events/wp-events-install.php');
include_once(ABSPATH.'wp-content/plugins/wp-events/wp-events-functions.php');
include_once(ABSPATH.'wp-content/plugins/wp-events/wp-events-manage.php');
events_check_config();

#---------------------------------------------------
# Only proceed with the plugin if MySQL Tables are setup properly
#---------------------------------------------------
if(events_mysql_table_exists()) {
	// Add filters for adding the tags in the WP page/post field
	add_filter('the_content', 'events_page', 9);
	add_filter('the_content', 'events_page_archive', 9);
	add_filter('the_content', 'events_daily', 9);

	events_clear_old(); // Remove non archived old events

	add_action('admin_menu', 'events_add_pages'); //Add page menu links
	
	if(isset($_POST['events_submit'])) {
		add_action('init', 'events_insert_input'); //Save event
	}

	if(isset($_GET['delete_event']) OR isset($_POST['delete_multiple'])) {
		add_action('init', 'events_request_delete'); //Delete event
	}

	if(isset($_POST['events_submit_options']) AND $_GET['updated'] == "true") {
		add_action('init', 'events_options_submit'); //Update Options
	}

	if(isset($_POST['event_uninstall'])) {
		add_action('init', 'events_plugin_uninstall'); //Uninstall
	}
	
	if(isset($_POST['event_upgrade'])) {
		add_action('init', 'events_mysql_upgrade'); //Upgrade DB
	}

	// Load Options
	$events_config = get_option('events_config');
	setlocale(LC_TIME, $events_config['localization']);	
} else {
	// Install table if not existing
	events_mysql_install();
}

/*-------------------------------------------------------------
 Name:      events_add_pages

 Purpose:   Add pages to admin menus
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_add_pages() {
	global $events_config;

	add_management_page('Events', 'Events', $events_config['minlevel'], basename(__FILE__), 'events_manage_page');
	add_options_page('Events', 'Events', 10, basename(__FILE__), 'events_options_page');
	add_submenu_page('post-new.php', 'Event', 'Event', $events_config['minlevel'], basename(__FILE__), 'events_add_page');
}

/*-------------------------------------------------------------
 Name:      events_manage_page

 Purpose:   Admin management page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_manage_page() {
	global $wpdb, $userdata, $events_config;

	$action = $_GET['action'];
	if(isset($_POST['order'])) { $order = $_POST['order']; } else { $order = 'thetime ASC'; }
	
	if ($action == 'deleted') { ?>
		<div id="message" class="updated fade"><p>Event <strong>deleted</strong></p></div>
	<?php } else if ($action == 'no_access') { ?>
		<div id="message" class="updated fade"><p>Action prohibited</p></div>
	<?php } ?>

	<div class="wrap">
		<h2>Manage Events</h2>
		<?php $events = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "events ORDER BY ".$order); ?>

		<form name="events" id="post" method="post" action="edit.php?page=wp-events.php">
			<div class="tablenav">

				<div class="alignleft">
					<input onclick="return confirm('You are about to delete multiple events!\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" value="Delete" name="delete_multiple" class="button-secondary delete" />
					<select name='order' id='cat' class='postform' >
				        <option value="thetime ASC" <?php if($order == "thetime ASC") { echo 'selected'; } ?>>by date (ascending)</option>
				        <option value="thetime DESC" <?php if($order == "thetime DESC") { echo 'selected'; } ?>>by date (descending)</option>
				        <option value="ID ASC" <?php if($order == "ID ASC") { echo 'selected'; } ?>>in the order you made them (ascending)</option>
				        <option value="ID DESC" <?php if($order == "ID DESC") { echo 'selected'; } ?>>in the order you made them (descending)</option>
				        <option value="title ASC" <?php if($order == "title ASC") { echo 'selected'; } ?>>by title (A-Z)</option>
				        <option value="title DESC" <?php if($order == "title DESC") { echo 'selected'; } ?>>by title (Z-A)</option>
					</select>
					<input type="submit" id="post-query-submit" value="Sort" class="button-secondary" />
				</div>
	
				<br class="clear" />
			</div>

			<br class="clear" />
		<table class="widefat">
  			<thead>
  				<tr>
					<th scope="col" class="check-column">&nbsp;</th>
					<th scope="col" width="15%">Date</th>
					<th scope="col">Title</th>
					<th scope="col" width="20%">Starts when</th>
					<th scope="col" width="20%">Ends after</th>
				</tr>
  			</thead>
  			<tbody>
		<?php if ($events) {
			foreach($events as $event) {
				$class = ('alternate' != $class) ? 'alternate' : ''; ?>
			    <tr id='event-<?php echo $event->id; ?>' class=' <?php echo $class; ?>'>
					<th scope="row" class="check-column"><input type="checkbox" name="eventcheck[]" value="<?php echo $event->id; ?>" /></th>
					<td><?php echo date("F d Y H:i", $event->thetime);?></td>
					<td><strong><a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/post-new.php?page=wp-events.php&amp;edit_event='.$event->id;?>" title="Edit"><?php echo stripslashes(html_entity_decode($event->title));?></a></strong></td>
					<td><?php echo events_countdown($event->thetime, $event->post_message); ?></td>
					<td><?php echo events_duration($event->thetime, $event->theend);?></td>
				</tr>
 			<?php } ?>
 		<?php } else { ?>
			<tr id='no-id'><td scope="row" colspan="5"><em>No Events yet!</em></td></tr>
		<?php }	?>
			</tbody>
		</table>
		</form>
	</div>
	<?php	 
}

/*-------------------------------------------------------------
 Name:      events_add_page

 Purpose:   Create new events
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_add_page() {
	global $wpdb, $userdata, $events_config;
	
	if($_GET['edit_event']) {
		$event_edit_id = $_GET['edit_event'];
	}
	
	$action = $_GET['action']; 
	if ($action == 'created') { ?>
		<div id="message" class="updated fade"><p>Event <strong>created</strong> | <a href="edit.php?page=wp-events.php">manage events</a></p></div>
	<?php } else if ($action == 'updated') { ?>
		<div id="message" class="updated fade"><p>Event <strong>updated</strong> | <a href="edit.php?page=wp-events.php">manage events</a></p></div>
	<?php } else if ($action == 'no_access') { ?>
		<div id="message" class="updated fade"><p>Action prohibited</p></div>
	<?php } else if ($action == 'field_error') { ?>
		<div id="message" class="updated fade"><p>Not all fields met the requirements</p></div>
	<?php } ?>
	
	<div class="wrap">
		<?php if(!$event_edit_id) { ?>
		<h2>Add event</h2>
		<?php } else { ?>
		<h2>Edit event</h2>
		<?php
		$SQL = "SELECT * FROM ".$wpdb->prefix."events WHERE id = ".$event_edit_id;
		$edit_event = $wpdb->get_row($SQL);
		list($sday, $smonth, $syear, $shour, $sminute) = split(" ", date("d m Y H i", $edit_event->thetime));
		list($eday, $emonth, $eyear, $ehour, $eminute) = split(" ", date("d m Y H i", $edit_event->theend)); ?>
		<?php } ?>
		
	  	<form method="post" action="post-new.php?page=wp-events.php">
	  	   	<input type="hidden" name="events_submit" value="true" />
	    	<input type="hidden" name="events_username" value="<?php echo $userdata->display_name;?>" />
	    	<input type="hidden" name="events_event_id" value="<?php echo $event_edit_id;?>" />
	    	<table class="form-table">
				<tr valign="top">
					<td colspan="4" bgcolor="#DDD">Please note that the time field uses a 24 hour clock. This means that 22:00 hour is actually 10:00pm.<br />Hint: If you're used to the AM/PM system and the event takes place/starts after lunch just add 12 hours.</td>
				</tr>
		      	<tr>
			        <th scope="row">Title:</th>
			        <td colspan="3"><input name="events_title" type="text" size="40" maxlength="<?php echo $events_config['length'];?>" value="<?php echo $edit_event->title;?>" /> <em>Maximum <?php echo $events_config['length'];?> characters. HTML allowed.</em></td>
		      	</tr>
		      	<tr>
			        <th scope="row">Event:</th>
			        <td colspan="3"><input name="events_pre_event" type="text" size="40" maxlength="<?php echo $events_config['length'];?>" value="<?php echo $edit_event->pre_message;?>" /> <em>Maximum <?php echo $events_config['length'];?> characters. HTML allowed.</em></td>
		      	</tr>
		      	<tr>
			        <th scope="row">Start DD/MM/YYYY:</th>
			        <td width="25%"><input id="title" name="events_sday" type="text" size="4" maxlength="2" value="<?php echo $sday;?>" />/<input name="events_smonth" type="text" size="4" maxlength="2" value="<?php echo $smonth;?>" />/<input name="events_syear" type="text" size="4" maxlength="4" value="<?php echo $syear;?>" /></td>
			        <th scope="row">HH/MM (optional):</th>
			        <td width="25%"><input name="events_shour" type="text" size="4" maxlength="2" value="<?php echo $shour;?>" />/<input name="events_sminute" type="text" size="4" maxlength="2" value="<?php echo $sminute;?>" /></td>
		      	</tr>
		      	<tr>
			        <th scope="row">End DD/MM/YYYY (optional):</th>
			        <td width="25%"><input id="title" name="events_eday" type="text" size="4" maxlength="2" value="<?php echo $eday;?>" />/<input name="events_emonth" type="text" size="4" maxlength="2" value="<?php echo $emonth;?>" />/<input name="events_eyear" type="text" size="4" maxlength="4" value="<?php echo $eyear;?>" /></td>
			        <th scope="row">HH/MM (optional):</th>
			        <td width="25%"><input name="events_ehour" type="text" size="4" maxlength="2" value="<?php echo $ehour;?>" />/<input name="events_eminute" type="text" size="4" maxlength="2" value="<?php echo $eminute;?>" /></td>
		      	</tr>
		      	<tr>
			        <th scope="row">Show in the sidebar:</th>
			        <td width="25%"><select name="events_priority">
					<?php if($edit_event->priority == "yes" OR $edit_event->priority == "") { ?>
					<option value="yes">Yes</option>
					<option value="no">No</option>
					<?php } else { ?>
					<option value="no">No</option>
					<option value="yes">Yes</option>
					<?php } ?>
					</select></td>
					<th scope="row">Archive this event:</th>
					<td width="25%"><select name="events_archive">
					<?php if($edit_event->archive == "no" OR $edit_event->archive == "") { ?>
					<option value="no">No</option>
					<option value="yes">Yes</option>
					<?php } else { ?>
					<option value="yes">Yes</option>
					<option value="no">No</option>
					<?php } ?>
					</select></td>
				</tr>
		      	<tr>
			        <th scope="row">After (shows after the event, optional):</th>
			        <td colspan="3"><input name="events_post_event" type="text" size="40" maxlength="<?php echo $events_config['length'];?>" value="<?php echo $edit_event->post_message;?>" /> <em>Maximum <?php echo $events_config['length'];?> characters. HTML allowed.</em></td>
		      	</tr>
		      	<tr>
			        <th scope="row">Link to page (optional):</th>
			        <td colspan="3"><input name="events_link" type="text" size="40" maxlength="10000" value="<?php echo $edit_event->link;?>" /> <em>Include full url and http://, this can be any page.</em></td>
		      	</tr>
	    	</table>
	    	
	    	<p class="submit">
				<input type="submit" name="Submit" value="Save event &raquo;" />
	    	</p>

	  	</form>
	</div>
<?php }


/*-------------------------------------------------------------
 Name:      events_options_page

 Purpose:   Admin options page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_options_page() {
	$events_config = get_option('events_config');
	$theunixdate = date("U");
?>
	<div class="wrap">
	  	<h2>Events options</h2>
	  	<form method="post" action="<?php echo $_SERVER['REQUEST_URI'];?>&amp;updated=true">
	    	<input type="hidden" name="events_submit_options" value="true" />

	    	<h3>Main config</h3>	    	

	    	<table class="form-table">
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Here you can set how many events are shown in the sidebar. Also you define how the dates and time should look for both the sidebar and events page.</td>
				</tr>
		      	<tr valign="top">
			        <th scope="row">How much events in sidebar?</th>
			        <td><input name="events_amount" type="text" value="<?php echo $events_config['amount'];?>" size="3" /> (default: 2)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Format date pages?</th>
			        <td><select name="events_dateformat">
				        <option disabled="disabled">-- day month year --</option>
				        <option value="%d %b %Y %H:%M" <?php if($events_config['dateformat'] == "%d %b %Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d %b %Y %H:%M", $theunixdate)); ?></option>
				        <option value="%d %B %Y %H:%M" <?php if($events_config['dateformat'] == "%d %B %Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d %B %Y %H:%M", $theunixdate)); ?></option>
				        <option value="%d %B %Y %I:%M %p" <?php if($events_config['dateformat'] == "%d %B %Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d %B %Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%d %B %Y %H:%M:%S" <?php if($events_config['dateformat'] == "%d %B %Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d %B %Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%d %b %Y" <?php if($events_config['dateformat'] == "%d %b %Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d %b %Y", $theunixdate)); ?> (default)</option>
				        <option value="%d %B %Y" <?php if($events_config['dateformat'] == "%d %B %Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d %B %Y", $theunixdate)); ?></option>				        
				        <option disabled="disabled">-- weekday day/month/year --</option>
				        <option value="%a, %d %B %Y %H:%M" <?php if($events_config['dateformat'] == "%a, %d %B %Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%a, %d %B %Y %H:%M", $theunixdate)); ?></option>
				        <option value="%A, %d %B %Y %H:%M" <?php if($events_config['dateformat'] == "%A, %d %B %Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%A, %d %B %Y %H:%M", $theunixdate)); ?></option>
				        <option disabled="disabled">-- preferred by locale --</option>
				        <option value="%x" <?php if($events_config['dateformat'] == "%x") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%x", $theunixdate)); ?></option>
				        <option value="%c" <?php if($events_config['dateformat'] == "%c") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%c", $theunixdate)); ?></option>        
				        <option disabled="disabled">-- day/month/year --</option>
				        <option value="%d/%m/%Y" <?php if($events_config['dateformat'] == "%d/%m/%Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d/%m/%Y", $theunixdate)); ?></option>
				        <option value="%d/%m/%Y %H:%M" <?php if($events_config['dateformat'] == "%d/%m/%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d/%m/%Y %H:%M", $theunixdate)); ?></option>
				        <option value="%d/%m/%Y %H:%M:%S" <?php if($events_config['dateformat'] == "%d/%m/%Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d/%m/%Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%d/%m/%Y %I:%M %p" <?php if($events_config['dateformat'] == "%d/%m/%Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d/%m/%Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%d/%m/%Y %H:%M" <?php if($events_config['dateformat'] == "%d/%m/%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d/%m/%Y %H:%M", $theunixdate)); ?></option>
				        <option disabled="disabled">-- month/day/year --</option>
				        <option value="%m/%d/%Y" <?php if($events_config['dateformat'] == "%m/%d/%Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m/%d/%Y", $theunixdate)); ?></option>
				        <option value="%m/%d/%Y %H:%M" <?php if($events_config['dateformat'] == "%m/%d/%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m/%d/%Y %H:%M", $theunixdate)); ?></option>
				        <option value="%m/%d/%Y %H:%M:%S" <?php if($events_config['dateformat'] == "%m/%d/%Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m/%d/%Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%m/%d/%Y %I:%M %p" <?php if($events_config['dateformat'] == "%m/%d/%Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m/%d/%Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%m/%d/%Y %H:%M" <?php if($events_config['dateformat'] == "%m/%d/%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m/%d/%Y %H:%M", $theunixdate)); ?></option>
				        <option disabled="disabled">-- day-month-year --</option>
				        <option value="%d-%m-%Y" <?php if($events_config['dateformat'] == "%d-%m-%Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d-%m-%Y", $theunixdate)); ?></option>
				        <option value="%d-%m-%Y %H:%M" <?php if($events_config['dateformat'] == "%d-%m-%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d-%m-%Y %H:%M", $theunixdate)); ?></option>
				        <option value="%d-%m-%Y %H:%M:%S" <?php if($events_config['dateformat'] == "%d-%m-%Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d-%m-%Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%d-%m-%Y %I:%M %p" <?php if($events_config['dateformat'] == "%d-%m-%Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d-%m-%Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%d-%m-%Y %H:%M" <?php if($events_config['dateformat'] == "%d-%m-%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d-%m-%Y %H:%M", $theunixdate)); ?></option>
				        <option disabled="disabled">-- month-day-year --</option>
				        <option value="%m-%d-%Y" <?php if($events_config['dateformat'] == "%m-%d-%Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m-%d-%Y", $theunixdate)); ?></option>
				        <option value="%m-%d-%Y %H:%M" <?php if($events_config['dateformat'] == "%m-%d-%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m-%d-%Y %H:%M", $theunixdate)); ?></option>
				        <option value="%m-%d-%Y %H:%M:%S" <?php if($events_config['dateformat'] == "%m-%d-%Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m-%d-%Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%m-%d-%Y %I:%M %p" <?php if($events_config['dateformat'] == "%m-%d-%Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m-%d-%Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%m-%d-%Y %H:%M" <?php if($events_config['dateformat'] == "%m-%d-%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m-%d-%Y %H:%M", $theunixdate)); ?></option>
				        <option disabled="disabled">-- day.month.year --</option>
				        <option value="%d.%m.%Y" <?php if($events_config['dateformat'] == "%d.%m.%Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d.%m.%Y", $theunixdate)); ?></option>
				        <option value="%d.%m.%Y %H:%M" <?php if($events_config['dateformat'] == "%d.%m.%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d.%m.%Y %H:%M", $theunixdate)); ?></option>
				        <option value="%d.%m.%Y %H:%M:%S" <?php if($events_config['dateformat'] == "%d.%m.%Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d.%m.%Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%d.%m.%Y %I:%M %p" <?php if($events_config['dateformat'] == "%d.%m.%Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d.%m.%Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%d.%m.%Y %H:%M" <?php if($events_config['dateformat'] == "%d.%m.%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d.%m.%Y %H:%M", $theunixdate)); ?></option>
				        <option disabled="disabled">-- month.day.year --</option>
				        <option value="%m.%d.%Y" <?php if($events_config['dateformat'] == "%m.%d.%Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m.%d.%Y", $theunixdate)); ?></option>
				        <option value="%m.%d.%Y %H:%M" <?php if($events_config['dateformat'] == "%m.%d.%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m.%d.%Y %H:%M", $theunixdate)); ?></option>
				        <option value="%m.%d.%Y %H:%M:%S" <?php if($events_config['dateformat'] == "%m.%d.%Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m.%d.%Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%m.%d.%Y %I:%M %p" <?php if($events_config['dateformat'] == "%m.%d.%Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m.%d.%Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%m.%d.%Y %H:%M" <?php if($events_config['dateformat'] == "%m.%d.%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m.%d.%Y %H:%M", $theunixdate)); ?></option>
					</select></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Format date sidebar?</th>
			        <td><select name="events_dateformat_sidebar">
				        <option disabled="disabled">-- day month year --</option>
				        <option value="%d %b %Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%d %b %Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d %b %Y %H:%M", $theunixdate)); ?> (default)</option>
				        <option value="%d %B %Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%d %B %Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d %B %Y %H:%M", $theunixdate)); ?></option>
				        <option value="%d %B %Y %I:%M %p" <?php if($events_config['dateformat_sidebar'] == "%d %B %Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d %B %Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%d %B %Y %H:%M:%S" <?php if($events_config['dateformat_sidebar'] == "%d %B %Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d %B %Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%d %b %Y" <?php if($events_config['dateformat_sidebar'] == "%d %b %Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d %b %Y", $theunixdate)); ?></option>
				        <option value="%d %B %Y" <?php if($events_config['dateformat_sidebar'] == "%d %B %Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d %B %Y", $theunixdate)); ?></option>				        
				        <option disabled="disabled">-- weekday day/month/year --</option>
				        <option value="%a, %d %B %Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%a, %d %B %Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%a, %d %B %Y %H:%M", $theunixdate)); ?></option>
				        <option value="%A, %d %B %Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%A, %d %B %Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%A, %d %B %Y %H:%M", $theunixdate)); ?></option>
				        <option disabled="disabled">-- preferred by locale --</option>
				        <option value="%x" <?php if($events_config['dateformat_sidebar'] == "%x") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%x", $theunixdate)); ?></option>
				        <option value="%c" <?php if($events_config['dateformat_sidebar'] == "%c") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%c", $theunixdate)); ?></option>        
				        <option disabled="disabled">-- day/month/year --</option>
				        <option value="%d/%m/%Y" <?php if($events_config['dateformat_sidebar'] == "%d/%m/%Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d/%m/%Y", $theunixdate)); ?></option>
				        <option value="%d/%m/%Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%d/%m/%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d/%m/%Y %H:%M", $theunixdate)); ?></option>
				        <option value="%d/%m/%Y %H:%M:%S" <?php if($events_config['dateformat_sidebar'] == "%d/%m/%Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d/%m/%Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%d/%m/%Y %I:%M %p" <?php if($events_config['dateformat_sidebar'] == "%d/%m/%Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d/%m/%Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%d/%m/%Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%d/%m/%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d/%m/%Y %H:%M", $theunixdate)); ?></option>
				        <option disabled="disabled">-- month/day/year --</option>
				        <option value="%m/%d/%Y" <?php if($events_config['dateformat_sidebar'] == "%m/%d/%Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m/%d/%Y", $theunixdate)); ?></option>
				        <option value="%m/%d/%Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%m/%d/%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m/%d/%Y %H:%M", $theunixdate)); ?></option>
				        <option value="%m/%d/%Y %H:%M:%S" <?php if($events_config['dateformat_sidebar'] == "%m/%d/%Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m/%d/%Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%m/%d/%Y %I:%M %p" <?php if($events_config['dateformat_sidebar'] == "%m/%d/%Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m/%d/%Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%m/%d/%Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%m/%d/%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m/%d/%Y %H:%M", $theunixdate)); ?></option>
				        <option disabled="disabled">-- day-month-year --</option>
				        <option value="%d-%m-%Y" <?php if($events_config['dateformat_sidebar'] == "%d-%m-%Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d-%m-%Y", $theunixdate)); ?></option>
				        <option value="%d-%m-%Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%d-%m-%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d-%m-%Y %H:%M", $theunixdate)); ?></option>
				        <option value="%d-%m-%Y %H:%M:%S" <?php if($events_config['dateformat_sidebar'] == "%d-%m-%Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d-%m-%Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%d-%m-%Y %I:%M %p" <?php if($events_config['dateformat_sidebar'] == "%d-%m-%Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d-%m-%Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%d-%m-%Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%d-%m-%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d-%m-%Y %H:%M", $theunixdate)); ?></option>
				        <option disabled="disabled">-- month-day-year --</option>
				        <option value="%m-%d-%Y" <?php if($events_config['dateformat_sidebar'] == "%m-%d-%Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m-%d-%Y", $theunixdate)); ?></option>
				        <option value="%m-%d-%Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%m-%d-%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m-%d-%Y %H:%M", $theunixdate)); ?></option>
				        <option value="%m-%d-%Y %H:%M:%S" <?php if($events_config['dateformat_sidebar'] == "%m-%d-%Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m-%d-%Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%m-%d-%Y %I:%M %p" <?php if($events_config['dateformat_sidebar'] == "%m-%d-%Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m-%d-%Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%m-%d-%Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%m-%d-%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m-%d-%Y %H:%M", $theunixdate)); ?></option>
				        <option disabled="disabled">-- day.month.year --</option>
				        <option value="%d.%m.%Y" <?php if($events_config['dateformat_sidebar'] == "%d.%m.%Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d.%m.%Y", $theunixdate)); ?></option>
				        <option value="%d.%m.%Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%d.%m.%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d.%m.%Y %H:%M", $theunixdate)); ?></option>
				        <option value="%d.%m.%Y %H:%M:%S" <?php if($events_config['dateformat_sidebar'] == "%d.%m.%Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d.%m.%Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%d.%m.%Y %I:%M %p" <?php if($events_config['dateformat_sidebar'] == "%d.%m.%Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d.%m.%Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%d.%m.%Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%d.%m.%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%d.%m.%Y %H:%M", $theunixdate)); ?></option>
				        <option disabled="disabled">-- month.day.year --</option>
				        <option value="%m.%d.%Y" <?php if($events_config['dateformat_sidebar'] == "%m.%d.%Y") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m.%d.%Y", $theunixdate)); ?></option>
				        <option value="%m.%d.%Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%m.%d.%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m.%d.%Y %H:%M", $theunixdate)); ?></option>
				        <option value="%m.%d.%Y %H:%M:%S" <?php if($events_config['dateformat_sidebar'] == "%m.%d.%Y %H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m.%d.%Y %H:%M:%S", $theunixdate)); ?></option>
				        <option value="%m.%d.%Y %I:%M %p" <?php if($events_config['dateformat_sidebar'] == "%m.%d.%Y %I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m.%d.%Y %I:%M %p", $theunixdate)); ?></option>
				        <option value="%m.%d.%Y %H:%M" <?php if($events_config['dateformat_sidebar'] == "%m.%d.%Y %H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(strftime("%m.%d.%Y %H:%M", $theunixdate)); ?></option>
					</select></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Order events ...</th>
			        <td><select name="events_order">';
				        <option value="thetime ASC" <?php if($events_config['order'] == "thetime ASC") { echo 'selected'; } ?>>by date (ascending)</option>
				        <option value="thetime DESC" <?php if($events_config['order'] == "thetime DESC") { echo 'selected'; } ?>>by date (descending)</option>
				        <option value="ID ASC" <?php if($events_config['order'] == "ID ASC") { echo 'selected'; } ?>>in the order you made them (ascending)</option>
				        <option value="ID DESC" <?php if($events_config['order'] == "ID DESC") { echo 'selected'; } ?>>in the order you made them (descending)</option>
				        <option value="author ASC" <?php if($events_config['order'] == "author ASC") { echo 'selected'; } ?>>by author (A-Z)</option>
				        <option value="author DESC" <?php if($events_config['order'] == "author DESC") { echo 'selected'; } ?>>by author (Z-A)</option>
				        <option value="title ASC" <?php if($events_config['order'] == "title ASC") { echo 'selected'; } ?>>by title (A-Z)</option>
				        <option value="title DESC" <?php if($events_config['order'] == "title DESC") { echo 'selected'; } ?>>by title (Z-A)</option>
				        <option value="pre_message ASC" <?php if($events_config['order'] == "pre_message ASC") { echo 'selected'; } ?>>by description (A-Z)</option>
				        <option value="pre_message DESC" <?php if($events_config['order'] == "pre_message DESC") { echo 'selected'; } ?>>by description (Z-A)</option>
					</select></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Order archive ...</th>
			        <td><select name="events_order_archive">';
				        <option value="thetime ASC" <?php if($events_config['order_archive'] == "thetime ASC") { echo 'selected'; } ?>>by date (ascending)</option>
				        <option value="thetime DESC" <?php if($events_config['order_archive'] == "thetime DESC") { echo 'selected'; } ?>>by date (descending)</option>
				        <option value="ID ASC" <?php if($events_config['order_archive'] == "ID ASC") { echo 'selected'; } ?>>in the order you made them (ascending)</option>
				        <option value="ID DESC" <?php if($events_config['order_archive'] == "ID DESC") { echo 'selected'; } ?>>in the order you made them (descending)</option>
				        <option value="author ASC" <?php if($events_config['order_archive'] == "author ASC") { echo 'selected'; } ?>>by author (A-Z)</option>
				        <option value="author DESC" <?php if($events_config['order_archive'] == "author DESC") { echo 'selected'; } ?>>by author (Z-A)</option>
				        <option value="title ASC" <?php if($events_config['order_archive'] == "title ASC") { echo 'selected'; } ?>>by title (A-Z)</option>
				        <option value="title DESC" <?php if($events_config['order_archive'] == "title DESC") { echo 'selected'; } ?>>by title (Z-A)</option>
				        <option value="pre_message ASC" <?php if($events_config['order_archive'] == "pre_message ASC") { echo 'selected'; } ?>>by description (A-Z)</option>
				        <option value="pre_message DESC" <?php if($events_config['order_archive'] == "pre_message DESC") { echo 'selected'; } ?>>by description (Z-A)</option>
					</select></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Allow how much characters in the events?</th>
			        <td><input name="events_length" type="text" value="<?php echo $events_config['length'];?>" size="6" /> (default: 1000)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">How much characters to show in the sidebar?</th>
			        <td><input name="events_sidelength" type="text" value="<?php echo $events_config['sidelength'];?>" size="6" /> (default: 120)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Linked events ...</th>
			        <td><select name="events_linktarget">';
				        <option value="_blank" <?php if($events_config['linktarget'] == "_target") { echo 'selected'; } ?>>new window</option>
				        <option value="_self" <?php if($events_config['linktarget'] == "_self") { echo 'selected'; } ?>>same window</option>
				        <option value="_parent" <?php if($events_config['linktarget'] == "_parent") { echo 'selected'; } ?>>parent window</option>
					</select></td>
		      	</tr>
			</table>
				
		   	<h3>Templates</h3>
	
		   	<table class="form-table">
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">You can alter it's look here. You can use any text/html!<br />Available variables are shown next to the field. Use this option with care!</td>
				</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Sidebar header template:</th>
			        <td><textarea name="sidebar_h_template" cols="50" rows="3"><?php echo stripslashes($events_config['sidebar_h_template']); ?></textarea><br /><em>Options: %sidebar_title%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Sidebar template:</th>
			        <td><textarea name="sidebar_template" cols="50" rows="3"><?php echo stripslashes($events_config['sidebar_template']); ?></textarea><br /><em>Options: %author% %title% %event% %starttime% %date% %link%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Sidebar footer template:</th>
			        <td><textarea name="sidebar_f_template" cols="50" rows="3"><?php echo stripslashes($events_config['sidebar_f_template']); ?></textarea></td>
		      	</tr>
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Alter the main list on a seperate page.</td>
				</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Page header template:</th>
			        <td><textarea name="page_h_template" cols="50" rows="3"><?php echo stripslashes($events_config['page_h_template']); ?></textarea><br /><em>Options: %page_title%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Page template:</th>
			        <td><textarea name="page_template" cols="50" rows="3"><?php echo stripslashes($events_config['page_template']); ?></textarea><br /><em>Options: %title% %event% %after% %link% %starttime% %endtime% %duration% %date% %author%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Page footer template:</th>
			        <td><textarea name="page_f_template" cols="50" rows="3"><?php echo stripslashes($events_config['page_f_template']); ?></textarea></td>
		      	</tr>
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Edit the looks of the archive, also shown on the seperate page, here.</td>
				</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Archive header template:</th>
			        <td><textarea name="archive_h_template" cols="50" rows="3"><?php echo stripslashes($events_config['archive_h_template']); ?></textarea><br /><em>Options: %archive_title%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Archive template:</th>
			        <td><textarea name="archive_template" cols="50" rows="3"><?php echo stripslashes($events_config['archive_template']); ?></textarea><br /><em>Options: %title% %event% %after% %link% %starttime% %endtime% %duration% %date% %author%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Archive footer template:</th>
			        <td><textarea name="archive_f_template" cols="50" rows="3"><?php echo stripslashes($events_config['archive_f_template']); ?></textarea></td>
		      	</tr>
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Change the way what todays events look like.</th>
				</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Todays header template:</th>
			        <td><textarea name="daily_h_template" cols="50" rows="3"><?php echo stripslashes($events_config['daily_h_template']); ?></textarea><br /><em>Options: daily_title%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Todays template:</th>
			        <td><textarea name="daily_template" cols="50" rows="3"><?php echo stripslashes($events_config['daily_template']); ?></textarea><br /><em>Options: %title% %event% %after% %link% %starttime% %endtime% %duration% %date% %author%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Todays footer template:</th>
			        <td><textarea name="daily_f_template" cols="50" rows="3"><?php echo stripslashes($events_config['daily_f_template']); ?></textarea></td>
		      	</tr>
			</table>
			
	    	<h3>Management</h3>	    	
	
	    	<table class="form-table">
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Set these options to prevent certain userlevels from editing, creating or deleting events. The options panel user level cannot be changed. For more information on user roles go to <a href="http://codex.wordpress.org/Roles_and_Capabilities#Summary_of_Roles" target="_blank">the codex</a>.</td>
				</tr>
		      	<tr valign="top">
			        <th scope="row">Add/edit events?</th>
			        <td><select name="events_minlevel">';
				        <option value="10" <?php if($events_config['minlevel'] == "10") { echo 'selected'; } ?>>Administrator</option>
				        <option value="7" <?php if($events_config['minlevel'] == "7") { echo 'selected'; } ?>>Editor (default)</option>
				        <option value="2" <?php if($events_config['minlevel'] == "2") { echo 'selected'; } ?>>Author</option>
				        <option value="1" <?php if($events_config['minlevel'] == "1") { echo 'selected'; } ?>>Contributor</option>
				        <option value="0" <?php if($events_config['minlevel'] == "0") { echo 'selected'; } ?>>Subscriber</option>
					</select> <em>Can add/edit/view events.</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Delete events?</th>
			        <td><select name="events_managelevel">';
				        <option value="10" <?php if($events_config['managelevel'] == "10") { echo 'selected'; } ?>>Administrator (default)</option>
				        <option value="7" <?php if($events_config['managelevel'] == "7") { echo 'selected'; } ?>>Editor</option>
				        <option value="2" <?php if($events_config['managelevel'] == "2") { echo 'selected'; } ?>>Author</option>
				        <option value="1" <?php if($events_config['managelevel'] == "1") { echo 'selected'; } ?>>Contributor</option>
				        <option value="0" <?php if($events_config['managelevel'] == "0") { echo 'selected'; } ?>>Subscriber</option>
					</select> <em>Can view/delete events.</em></td>
		      	</tr>
			</table>
			
		    <h3>Language</h3>	    	
	
		    <table class="form-table">
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Here you set the language of the plugin. Change the fields below to match your language.
				</tr>
		      	<tr valign="top">
			        <th scope="row">Sidebar title:</th>
			        <td><input name="events_language_s_title" type="text" value="<?php echo $events_config['language_s_title'];?>" size="45" /> (default: Highlighted Events)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Page title:</th>
			        <td><input name="events_language_p_title" type="text" value="<?php echo $events_config['language_p_title'];?>" size="45" /> (default: Important Events)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Archive title:</th>
			        <td><input name="events_language_a_title" type="text" value="<?php echo $events_config['language_a_title'];?>" size="45" /> (default: Archive)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Daily title:</th>
			        <td><input name="events_language_d_title" type="text" value="<?php echo $events_config['language_d_title'];?>" size="45" /> (default: Todays Events)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Today:</th>
			        <td><input name="events_language_today" type="text" value="<?php echo $events_config['language_today'];?>" size="45" /> (default: today)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Hours:</th>
			        <td><input name="events_language_hours" type="text" value="<?php echo $events_config['language_hours'];?>" size="45" /> (default: hours)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Minutes:</th>
			        <td><input name="events_language_minutes" type="text" value="<?php echo $events_config['language_minutes'];?>" size="45" /> (default: minutes)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Day:</th>
			        <td><input name="events_language_day" type="text" value="<?php echo $events_config['language_day'];?>" size="45" /> (default: day)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Days:</th>
			        <td><input name="events_language_days" type="text" value="<?php echo $events_config['language_days'];?>" size="45" /> (default: days)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">And:</th>
			        <td><input name="events_language_and" type="text" value="<?php echo $events_config['language_and'];?>" size="45" /> (default: and)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">On:</th>
			        <td><input name="events_language_on" type="text" value="<?php echo $events_config['language_on'];?>" size="45" /> (default: on)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">In:</th>
			        <td><input name="events_language_in" type="text" value="<?php echo $events_config['language_in'];?>" size="45" /> (default: in)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Ago:</th>
			        <td><input name="events_language_ago" type="text" value="<?php echo $events_config['language_ago'];?>" size="45" /> (default: ago)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Sidebar link:</th>
			        <td><input name="events_language_sidelink" type="text" value="<?php echo $events_config['language_sidelink'];?>" size="45" /> (default: more &raquo;)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Page link:</th>
			        <td><input name="events_language_pagelink" type="text" value="<?php echo $events_config['language_pagelink'];?>" size="45" /> (default: More information &raquo;)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">If there are no events to show:</th>
			        <td><input name="events_language_noevents" type="text" value="<?php echo $events_config['language_noevents'];?>" size="45" /> (default: No events to show)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">If there are no todays events to show:</th>
			        <td><input name="events_language_nodaily" type="text" value="<?php echo $events_config['language_nodaily'];?>" size="45" /> (default: No events today)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">If the archive is empty:</th>
			        <td><input name="events_language_noarchive" type="text" value="<?php echo $events_config['language_noarchive'];?>" size="45" /> (default: No events in the archive)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">If there are no events to show:</th>
			        <td><input name="events_language_e_config" type="text" value="<?php echo $events_config['language_e_config'];?>" size="45" /> (default: A configuration error occured)</td>
		      	</tr>
			</table>
			
	    	<h3>Localization</h3>	    	
	
	    	<table class="form-table">
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Set the timezone to your timezone this ensures that no mishaps occur when you set the time for an event. <br />
					Localization can usually be en_EN. Changing this value should translate the dates to your language.<br />
					On Linux/Mac Osx you should use 'en_EN' in the field. For windows just 'en' should suffice. Your server most likely uses <strong><?php echo PHP_OS; ?>.</td>
				</tr>
		      	<tr valign="top">
			        <th scope="row">Your timezone?</th>
			        <td><select name="events_timezone">';
				        <option value="-43200" <?php if($events_config['timezone'] == "-43200") { echo 'selected'; } ?>>(GMT -12:00) Eniwetok, Kwajalein</option>
						<option value="-39600" <?php if($events_config['timezone'] == "-39600") { echo 'selected'; } ?>>(GMT -11:00) Midway Island, Samoa</option>
				        <option value="-36000" <?php if($events_config['timezone'] == "-36000") { echo 'selected'; } ?>>(GMT -10:00) Hawaii</option>
				        <option value="-32400" <?php if($events_config['timezone'] == "-32400") { echo 'selected'; } ?>>(GMT -9:00) Alaska</option>
				        <option value="-28800" <?php if($events_config['timezone'] == "-28800") { echo 'selected'; } ?>>(GMT -8:00) Pacific Time (US & Canada)</option>
				        <option value="-25200" <?php if($events_config['timezone'] == "-25200") { echo 'selected'; } ?>>(GMT -7:00) Mountain Time (US & Canada)</option>
				        <option value="-21600" <?php if($events_config['timezone'] == "-21600") { echo 'selected'; } ?>>(GMT -6:00) Central Time (US & Canada), Mexico City</option>
				        <option value="-18000" <?php if($events_config['timezone'] == "-18000") { echo 'selected'; } ?>>(GMT -5:00) Eastern Time (US & Canada), Bogota, Lima</option>
				        <option value="-14400" <?php if($events_config['timezone'] == "-14400") { echo 'selected'; } ?>>(GMT -4:00) Atlantic Time (Canada), Caracas, La Paz</option>
				        <option value="-12600" <?php if($events_config['timezone'] == "-12600") { echo 'selected'; } ?>>(GMT -3:30) Newfoundland</option>
				        <option value="-10800" <?php if($events_config['timezone'] == "-10800") { echo 'selected'; } ?>>(GMT -3:00) Brazil, Buenos Aires, Georgetown</option>
				        <option value="-7200" <?php if($events_config['timezone'] == "-7200") { echo 'selected'; } ?>>(GMT -2:00) Mid-Atlantic</option>
				        <option value="-3600" <?php if($events_config['timezone'] == "-3600") { echo 'selected'; } ?>>(GMT -1:00) Azores, Cape Verde Islands</option>
				        <option value="+0" <?php if($events_config['timezone'] == "+0") { echo 'selected'; } ?>>(GMT) Western Europe Time, London, Lisbon, Casablanca</option>
				        <option value="+3600" <?php if($events_config['timezone'] == "+3600") { echo 'selected'; } ?>>(GMT +1:00) Amsterdam, Brussels, Copenhagen, Madrid, Paris</option>
				        <option value="+7200" <?php if($events_config['timezone'] == "+7200") { echo 'selected'; } ?>>(GMT +2:00) Kanliningrad, South Africa</option>
				        <option value="+10800" <?php if($events_config['timezone'] == "+10800") { echo 'selected'; } ?>>(GMT +3:00) Baghdad, Riyadh, Moscow, St. Petersburg</option>
				        <option value="+12600" <?php if($events_config['timezone'] == "+12600") { echo 'selected'; } ?>>(GMT +3:30) Tehran</option>
				        <option value="+14400" <?php if($events_config['timezone'] == "+14400") { echo 'selected'; } ?>>(GMT +4:00) Abu Dhabi, Muscat, Baku, Tbilisi</option>
				        <option value="+16200" <?php if($events_config['timezone'] == "+16200") { echo 'selected'; } ?>>(GMT +4:30) Kabul</option>
				        <option value="+18000" <?php if($events_config['timezone'] == "+18000") { echo 'selected'; } ?>>(GMT +5:00) Ekaterinburg, Islamabad, Karachi, Tashkent</option>
				        <option value="+19800" <?php if($events_config['timezone'] == "+19800") { echo 'selected'; } ?>>(GMT +5:30) Bombay, Calcutta, Madras, New Delphi</option>
				        <option value="+21600" <?php if($events_config['timezone'] == "+21600") { echo 'selected'; } ?>>(GMT +6:00) Almaty, Dhaka, Colombo</option>
				        <option value="+25200" <?php if($events_config['timezone'] == "+25200") { echo 'selected'; } ?>>(GMT +7:00) Bangkok, Hanoi, Jakarta</option>
				        <option value="+28800" <?php if($events_config['timezone'] == "+28800") { echo 'selected'; } ?>>(GMT +8:00) Beijing, Perth, Singapore, Hong Kong</option>
				        <option value="+32400" <?php if($events_config['timezone'] == "+32400") { echo 'selected'; } ?>>(GMT +9:00) Adelaide, Darwin</option>
				        <option value="+36000" <?php if($events_config['timezone'] == "+36000") { echo 'selected'; } ?>>(GMT +10:00) Eastern Australia, Guam, Vladivostok</option>
				        <option value="+39600" <?php if($events_config['timezone'] == "+39600") { echo 'selected'; } ?>>(GMT +11:00) Magadan, Solomon Islands, New Caledonia</option>
				        <option value="+43200" <?php if($events_config['timezone'] == "+43200") { echo 'selected'; } ?>>(GMT +12:00) Auckland, Wellington, Fiji, Kamchatka</option>
					</select></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Date localization:</th>
			        <td><input name="events_localization" type="text" value="<?php echo $events_config['localization'];?>" size="10" /> (default: en_EN)</td>
		      	</tr>

	    	</table>
		    <p class="submit">
		      	<input type="submit" name="Submit" value="Update Options &raquo;" />
		    </p>
		</form>
	  	
	  	<h2>Events Uninstall</h2>
	  	<p>Events installs a table in MySQL. When you disable the plugin the table will not be deleted. To delete the table use the button below.</p>
	  	<p><b style="color: #f00;">WARNING! -- This process is irreversible and will delete ALL scheduled events!</b></p>
	  	<p>For the techies: Upon un-installation the wp_events table will be dropped along with the events_config record in the wp_options table.</p>
	  	<form method="post" action="<?php echo $_SERVER['REQUEST_URI'];?>">
	  		<p class="submit">
	    	<input type="hidden" name="event_uninstall" value="true" />
	    	<input onclick="return confirm('You are about to uninstall the events plugin\n  All scheduled events will be lost!\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" name="Submit" value="Uninstall Plugin &raquo;" />
	  		</p>
	  	</form>
	</div>
<?php
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
		$option['managelevel'] 				= 7;
		$option['dateformat'] 				= '%d %b %Y';
		$option['dateformat_sidebar']		= '%d %b %Y %H:%M';
		$option['timezone']					= '+0';
		$option['order'] 					= 'thetime ASC';
		$option['order_archive'] 			= 'thetime DESC';
		$option['language_s_title'] 		= 'Highlighted events';
		$option['language_p_title'] 		= 'Important events';
		$option['language_a_title'] 		= 'Archive';
		$option['language_d_title'] 		= 'Todays events';
		$option['language_today'] 			= 'today';
		$option['language_hours'] 			= 'hours';
		$option['language_minutes'] 		= 'minutes';
		$option['language_day'] 			= 'day';
		$option['language_days'] 			= 'days';
		$option['language_and'] 			= 'and';
		$option['language_on'] 				= 'on';
		$option['language_in'] 				= 'in';
		$option['language_ago'] 			= 'ago';
		$option['language_sidelink']		= 'more &raquo;';
		$option['language_pagelink']		= 'More information &raquo;';
		$option['language_noevents'] 		= 'No events to show';
		$option['language_nodaily'] 		= 'No events today';
		$option['language_noarchive'] 		= 'No events in the archive';
		$option['language_e_config'] 		= 'A configuration error has occured';
		$option['localization'] 			= 'en_EN';
		$option['sidebar_template'] 		= '<li>%title% %link% on %date%<br />%starttime%</li>';
		$option['sidebar_h_template'] 		= '<h2>%sidebar_title%</h2><ul>';
		$option['sidebar_f_template'] 		= '</ul>';
		$option['page_template'] 			= '<p><strong>%title%</strong>, %event% on %date%<br />%starttime%<br />Duration: %duration%<br />%link%</p>';
		$option['page_h_template'] 			= '<h2>%page_title%</h2>';
		$option['page_f_template'] 			= '';
		$option['archive_template'] 		= '<p><strong>%title%</strong>, %after% on %date%<br />%starttime%<br />%endtime%<br />%link%</p>';
		$option['archive_h_template'] 		= '<h2>%archive_title%</h2>';
		$option['archive_f_template'] 		= '';
		$option['daily_template'] 			= '<p>%title% %event% - %starttime% %link%</p>';
		$option['daily_h_template'] 		= '<h2>%daily_title%</h2>';
		$option['daily_f_template'] 		= '';
		update_option('events_config', $option);
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
	$option['length'] 				= trim($_POST['events_length'], "\t\n ");
	$option['sidelength'] 			= trim($_POST['events_sidelength'], "\t\n ");
	$option['amount'] 				= trim($_POST['events_amount'], "\t\n ");
	$option['minlevel'] 			= $_POST['events_minlevel'];
	$option['managelevel'] 			= $_POST['events_managelevel'];
	$option['dateformat'] 			= $_POST['events_dateformat'];
	$option['dateformat_sidebar']	= $_POST['events_dateformat_sidebar'];
	$option['timezone'] 			= $_POST['events_timezone'];
	$option['order']	 			= $_POST['events_order'];
	$option['order_archive'] 		= $_POST['events_order_archive'];
	$option['linktarget'] 			= $_POST['events_linktarget'];
	$option['language_s_title'] 	= htmlspecialchars(trim($_POST['events_language_s_title'], "\t\n "), ENT_QUOTES);
	$option['language_p_title'] 	= htmlspecialchars(trim($_POST['events_language_p_title'], "\t\n "), ENT_QUOTES);
	$option['language_a_title'] 	= htmlspecialchars(trim($_POST['events_language_a_title'], "\t\n "), ENT_QUOTES);
	$option['language_d_title']		= htmlspecialchars(trim($_POST['events_language_d_title'], "\t\n "), ENT_QUOTES);
	$option['language_today'] 		= htmlspecialchars(trim($_POST['events_language_today'], "\t\n "), ENT_QUOTES);
	$option['language_hours'] 		= htmlspecialchars(trim($_POST['events_language_hours'], "\t\n "), ENT_QUOTES);
	$option['language_minutes'] 	= htmlspecialchars(trim($_POST['events_language_minutes'], "\t\n "), ENT_QUOTES);
	$option['language_day'] 		= htmlspecialchars(trim($_POST['events_language_day'], "\t\n "), ENT_QUOTES);
	$option['language_days'] 		= htmlspecialchars(trim($_POST['events_language_days'], "\t\n "), ENT_QUOTES);
	$option['language_and'] 		= htmlspecialchars(trim($_POST['events_language_and'], "\t\n "), ENT_QUOTES);
	$option['language_on'] 			= htmlspecialchars(trim($_POST['events_language_on'], "\t\n "), ENT_QUOTES);
	$option['language_in'] 			= htmlspecialchars(trim($_POST['events_language_in'], "\t\n "), ENT_QUOTES);
	$option['language_ago'] 		= htmlspecialchars(trim($_POST['events_language_ago'], "\t\n "), ENT_QUOTES);
	$option['language_sidelink']	= htmlspecialchars(trim($_POST['events_language_sidelink'], "\t\n "), ENT_QUOTES);
	$option['language_pagelink'] 	= htmlspecialchars(trim($_POST['events_language_pagelink'], "\t\n "), ENT_QUOTES);
	$option['language_noevents']	= htmlspecialchars(trim($_POST['events_language_noevents'], "\t\n "), ENT_QUOTES);
	$option['language_nodaily']		= htmlspecialchars(trim($_POST['events_language_nodaily'], "\t\n "), ENT_QUOTES);
	$option['language_noarchive'] 	= htmlspecialchars(trim($_POST['events_language_noarchive'], "\t\n "), ENT_QUOTES);
	$option['language_e_config'] 	= htmlspecialchars(trim($_POST['events_language_e_config'], "\t\n "), ENT_QUOTES);
	$option['localization'] 		= htmlspecialchars(trim($_POST['events_localization'], "\t\n "), ENT_QUOTES);
	$option['sidebar_template'] 	= htmlspecialchars(trim($_POST['sidebar_template'], "\t\n "), ENT_QUOTES);
	$option['sidebar_h_template'] 	= htmlspecialchars(trim($_POST['sidebar_h_template'], "\t\n "), ENT_QUOTES);
	$option['sidebar_f_template'] 	= htmlspecialchars(trim($_POST['sidebar_f_template'], "\t\n "), ENT_QUOTES);
	$option['page_template'] 		= htmlspecialchars(trim($_POST['page_template'], "\t\n "), ENT_QUOTES);
	$option['page_h_template'] 		= htmlspecialchars(trim($_POST['page_h_template'], "\t\n "), ENT_QUOTES);
	$option['page_f_template'] 		= htmlspecialchars(trim($_POST['page_f_template'], "\t\n "), ENT_QUOTES);
	$option['archive_template'] 	= htmlspecialchars(trim($_POST['archive_template'], "\t\n "), ENT_QUOTES);
	$option['archive_h_template'] 	= htmlspecialchars(trim($_POST['archive_h_template'], "\t\n "), ENT_QUOTES);
	$option['archive_f_template'] 	= htmlspecialchars(trim($_POST['archive_f_template'], "\t\n "), ENT_QUOTES);
	$option['daily_template']	 	= htmlspecialchars(trim($_POST['daily_template'], "\t\n "), ENT_QUOTES);
	$option['daily_h_template'] 	= htmlspecialchars(trim($_POST['daily_h_template'], "\t\n "), ENT_QUOTES);
	$option['daily_f_template'] 	= htmlspecialchars(trim($_POST['daily_f_template'], "\t\n "), ENT_QUOTES);
	update_option('events_config', $option);
}
?>