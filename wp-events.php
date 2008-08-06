<?php
/*
Plugin Name: Events
Plugin URI: http://meandmymac.net/plugins/events/
Description: Enables the user to show a list of events with a static countdown to date. Sidebar widget and page template options. And more...
Author: Arnan de Gans
Version: 1.4
Author URI: http://meandmymac.net/
*/

#---------------------------------------------------
# Load other plugin files and configuration
#---------------------------------------------------
include_once(ABSPATH.'wp-content/plugins/wp-events/wp-events-install.php');
include_once(ABSPATH.'wp-content/plugins/wp-events/wp-events-functions.php');
include_once(ABSPATH.'wp-content/plugins/wp-events/wp-events-manage.php');
include_once(ABSPATH.'wp-content/plugins/wp-events/wp-events-widget.php');
events_check_config();

#---------------------------------------------------
# Only proceed with the plugin if MySQL Tables are setup properly
#---------------------------------------------------
if(events_mysql_table_exists()) {
	// Add filters for adding the tags in the WP page/post field
	add_shortcode('events_list', 'events_page');
	add_shortcode('events_today', 'events_today');
	add_shortcode('events_archive', 'events_archive');

	events_clear_old(); // Remove non archived old events

	add_action('widgets_init', 'widget_wp_events_init'); //Initialize the widget
	add_action('admin_menu', 'events_add_pages'); //Add page menu links
	
	if(isset($_POST['events_submit'])) {
		add_action('init', 'events_insert_input'); //Save event
	}

	if(isset($_GET['delete_event']) OR isset($_POST['delete_multiple'])) {
		add_action('init', 'events_request_delete'); //Delete event
	}

	if(isset($_POST['events_submit_options'])) {
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
	$events_template = get_option('events_template');
	$events_language = get_option('events_language');
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

	add_submenu_page('post-new.php', 'Events', 'Event', $events_config['minlevel'], 'wp-events', 'events_add_page');
	add_submenu_page('edit.php', 'Events', 'Events', $events_config['minlevel'], 'wp-events', 'events_manage_page');
	add_submenu_page('options-general.php', 'Events', 'Events', $events_config['minlevel'], 'wp-events', 'events_options_page');
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
	if(isset($_POST['order'])) { 
		$order = $_POST['order']; 
	} else { 
		$order = 'thetime ASC'; 
	}
	
	if ($action == 'deleted') { ?>
		<div id="message" class="updated fade"><p>Event <strong>deleted</strong></p></div>
	<?php } else if ($action == 'updated') { ?>
		<div id="message" class="updated fade"><p>Event <strong>updated</strong></p></div>
	<?php } else if ($action == 'no_access') { ?>
		<div id="message" class="updated fade"><p>Action prohibited</p></div>
	<?php } ?>

	<div class="wrap">
		<h2>Manage Events (<a href="post-new.php?page=wp-events.php">add new</a>)</h2>
		<?php $events = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."events` ORDER BY $order"); ?>

		<form name="events" id="post" method="post" action="edit.php?page=wp-events.php">
			<div class="tablenav">

				<div class="alignleft">
					<input onclick="return confirm('You are about to delete multiple events!\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" value="Delete" name="delete_multiple" class="button-secondary delete" />
					<select name='order' id='cat' class='postform' >
				        <option value="thetime ASC" <?php if($order == "thetime ASC") { echo 'selected'; } ?>>by date (ascending, default)</option>
				        <option value="thetime DESC" <?php if($order == "thetime DESC") { echo 'selected'; } ?>>by date (descending)</option>
				        <option value="ID ASC" <?php if($order == "ID ASC") { echo 'selected'; } ?>>in the order you made them (ascending)</option>
				        <option value="ID DESC" <?php if($order == "ID DESC") { echo 'selected'; } ?>>in the order you made them (descending)</option>
				        <option value="title ASC" <?php if($order == "title ASC") { echo 'selected'; } ?>>by title (A-Z)</option>
				        <option value="title DESC" <?php if($order == "title DESC") { echo 'selected'; } ?>>by title (Z-A)</option>
				        <option value="location ASC" <?php if($order == "location ASC") { echo 'selected'; } ?>>by location (A-Z)</option>
				        <option value="location DESC" <?php if($order == "location DESC") { echo 'selected'; } ?>>by location (Z-A)</option>
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
					<th scope="col" width="15%">Location</th>
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
					<td><?php echo stripslashes(html_entity_decode($event->location));?></td>
					<td><strong><a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/post-new.php?page=wp-events.php&amp;edit_event='.$event->id;?>" title="Edit"><?php echo stripslashes(html_entity_decode($event->title));?></a></strong></td>
					<td><?php echo events_countdown($event->thetime, $event->post_message); ?></td>
					<td><?php echo events_duration($event->thetime, $event->theend, $event->allday);?></td>
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
		$SQL = "SELECT * FROM `".$wpdb->prefix."events` WHERE `id` = $event_edit_id";
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
			        <td colspan="2"><input name="events_title" type="text" size="52" maxlength="<?php echo $events_config['length'];?>" value="<?php echo $edit_event->title;?>" /><br /><em>Maximum <?php echo $events_config['length'];?> characters.</em></td>
			        <td width="25%"><input type="checkbox" name="events_title_link" <?php if($edit_event->title_link == 'Y') { ?>checked="checked" <?php } ?>/> Make title a link. Use the field below.<br /><input type="checkbox" name="events_allday" <?php if($edit_event->allday == 'Y') { ?>checked="checked" <?php } ?>/> All-day event.</td>
		      	</tr>
		      	<tr>
			        <th scope="row">Event description:</th>
			        <td colspan="3"><textarea name="events_pre_event" cols="70" rows="8"><?php echo $edit_event->pre_message;?></textarea><br /><em>Maximum <?php echo $events_config['length'];?> characters. HTML allowed.</em></td>
		      	</tr>
		      	<tr>
			        <th scope="row">Location (optional):</th>
			        <td colspan="3"><input name="events_location" type="text" size="52" maxlength="255" value="<?php echo $edit_event->location;?>" /><br /><em>Maximum 255 characters.</em></td>
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
			        <th scope="row">Message when event ends (optional):</th>
			        <td colspan="3"><input name="events_post_event" type="text" size="52" maxlength="<?php echo $events_config['length'];?>" value="<?php echo $edit_event->post_message;?>" /><br /><em>Maximum <?php echo $events_config['length'];?> characters. HTML allowed.</em></td>
		      	</tr>
		      	<tr>
			        <th scope="row">Link to page (optional):</th>
			        <td colspan="3"><input name="events_link" type="text" size="52 " maxlength="10000" value="<?php echo $edit_event->link;?>" /><br /><em>Include full url and http://, this can be any page.</em></td>
		      	</tr>
	    	</table>
	    	
	    	<p class="submit">
				<input type="submit" name="Submit" value="Save event &raquo;" /> <span style="float: right;"><?php if($event_edit_id) { ?><a href="<?php echo get_option('siteurl').'/wp-admin/post-new.php?page=wp-events&amp;delete_event='.$edit_event->id; ?>" style="color: #f00;" onclick="return confirm('Are you sure you want to delete this event?')">Delete event</a><?php } ?></span>
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
	$events_template = get_option('events_template');
	$events_language = get_option('events_language');
	$theunixdate = date("U");
?>
	<div class="wrap">
	  	<h2>Events options</h2>
	  	<form method="post" action="<?php echo $_SERVER['REQUEST_URI'];?>&amp;updated=true">
	    	<input type="hidden" name="events_submit_options" value="true" />

	    	<h3>Main config</h3>	    	

	    	<table class="form-table">
				<tr valign="top">
					<td colspan="4" bgcolor="#DDD"><strong>Options for the sidebar and widget.</strong></td>
				</tr>
		      	<tr valign="top">
			        <th scope="row">Show this many events</th>
			        <td colspan="3"><input name="events_amount" type="text" value="<?php echo $events_config['amount'];?>" size="6" /> (default: 2)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Date format</th>
			        <?php if($events_config['custom_date_sidebar'] == 'no') { ?>
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
					</select></td>
					<?php } else { ?>
 			        <td><input name="events_dateformat_sidebar" type="text" value="<?php echo $events_config['dateformat_sidebar'];?>" size="30" /><br />Careful what you put here. Learn: <a href="http://www.php.net/manual/en/function.strftime.php" target="_blank">php manual</a>.</td>
 			        <?php } ?>
			        <th scope="row">Date system</th>
			        <td><select name="events_custom_date_sidebar">
			        	 <?php if($events_config['custom_date_sidebar'] == "no") { ?>
				        <option value="no">Default</option>
				        <option value="yes">Advanced</option>
				        <?php } else { ?>
				        <option value="yes">Advanced</option>
				        <option value="no">Default</option>
				        <?php } ?>
					</select> Save options to see the result!</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Character limit</th>
			        <td colspan="3"><input name="events_sidelength" type="text" value="<?php echo $events_config['sidelength'];?>" size="6" /> (default: 120)</td>
		      	</tr>
				<tr valign="top">
					<td colspan="4" bgcolor="#DDD"><strong>Options for the page</strong></td>
				</tr>
		      	<tr valign="top">
			        <th scope="row">Date format</th>
			        <?php if($events_config['custom_date_page'] == 'no') { ?>
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
					</select></td>
					<?php } else { ?>
 			        <td><input name="events_dateformat" type="text" value="<?php echo $events_config['dateformat'];?>" size="30" /><br />Careful what you put here. Learn: <a href="http://www.php.net/manual/en/function.strftime.php" target="_blank">php manual</a>.</td>
 			        <?php } ?>
			        <th scope="row">Date system</th>
			        <td><select name="events_custom_date_page">
			        	 <?php if($events_config['custom_date_page'] == "no") { ?>
				        <option value="no">Default</option>
				        <option value="yes">Advanced</option>
				        <?php } else { ?>
				        <option value="yes">Advanced</option>
				        <option value="no">Default</option>
				        <?php } ?>
					</select> Save options to see the result!</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Character limit</th>
			        <td colspan="3"><input name="events_length" type="text" value="<?php echo $events_config['length'];?>" size="6" /> (default: 1000)</td>
		      	</tr>
				<tr valign="top">
					<td colspan="4" bgcolor="#DDD"><strong>Global or other options.</strong></td>
				</tr>
		      	<tr valign="top">
			        <th scope="row">Order events</th>
			        <td colspan="3"><select name="events_order">
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
			        <th scope="row">Order archive</th>
			        <td colspan="3"><select name="events_order_archive">
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
			        <th scope="row">Links open in</th>
			        <td colspan="3"><select name="events_linktarget">
				        <option value="_blank" <?php if($events_config['linktarget'] == "_target") { echo 'selected'; } ?>>new window</option>
				        <option value="_self" <?php if($events_config['linktarget'] == "_self") { echo 'selected'; } ?>>same window</option>
				        <option value="_parent" <?php if($events_config['linktarget'] == "_parent") { echo 'selected'; } ?>>parent window</option>
					</select></td>
		      	</tr>
			</table>
		    <p class="submit">
		      	<input type="submit" name="Submit" value="Update Options &raquo;" />
		    </p>
				
		   	<h3>Templates</h3>
	
		   	<table class="form-table">
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Change the way Events presents Events on the website.<br />Available variables are shown below of the field. Use this option with care!</td>
				</tr>
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD"><strong>Sidebar and widget<strong></td>
				</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Header:</th>
			        <td><textarea name="sidebar_h_template" cols="50" rows="4"><?php echo stripslashes($events_template['sidebar_h_template']); ?></textarea><br /><em>Options: %sidebar_title%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Body:</th>
			        <td><textarea name="sidebar_template" cols="50" rows="4"><?php echo stripslashes($events_template['sidebar_template']); ?></textarea><br /><em>Options: %title% %event% %link% %starttime% %date% %author% %location%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Footer:</th>
			        <td><textarea name="sidebar_f_template" cols="50" rows="4"><?php echo stripslashes($events_template['sidebar_f_template']); ?></textarea></td>
		      	</tr>
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD"><strong>Page, main list<strong></td>
				</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Header:</th>
			        <td><textarea name="page_h_template" cols="50" rows="4"><?php echo stripslashes($events_template['page_h_template']); ?></textarea><br /><em>Options: %page_title%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Body:</th>
			        <td><textarea name="page_template" cols="50" rows="4"><?php echo stripslashes($events_template['page_template']); ?></textarea><br /><em>Options: %title% %event% %after% %link% %starttime% %endtime% %duration% %date% %author% %location%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Footer:</th>
			        <td><textarea name="page_f_template" cols="50" rows="4"><?php echo stripslashes($events_template['page_f_template']); ?></textarea></td>
		      	</tr>
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD"><strong>Page, archive list<strong></td>
				</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Header:</th>
			        <td><textarea name="archive_h_template" cols="50" rows="4"><?php echo stripslashes($events_template['archive_h_template']); ?></textarea><br /><em>Options: %archive_title%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Body:</th>
			        <td><textarea name="archive_template" cols="50" rows="4"><?php echo stripslashes($events_template['archive_template']); ?></textarea><br /><em>Options: %title% %event% %after% %link% %starttime% %endtime% %duration% %date% %author% %location%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Footer:</th>
			        <td><textarea name="archive_f_template" cols="50" rows="4"><?php echo stripslashes($events_template['archive_f_template']); ?></textarea></td>
		      	</tr>
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD"><strong>Page, today's list<strong></th>
				</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Header:</th>
			        <td><textarea name="daily_h_template" cols="50" rows="4"><?php echo stripslashes($events_template['daily_h_template']); ?></textarea><br /><em>Options: %daily_title%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Body:</th>
			        <td><textarea name="daily_template" cols="50" rows="4"><?php echo stripslashes($events_template['daily_template']); ?></textarea><br /><em>Options: %title% %event% %after% %link% %starttime% %endtime% %duration% %date% %author% %location%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Footer:</th>
			        <td><textarea name="daily_f_template" cols="50" rows="4"><?php echo stripslashes($events_template['daily_f_template']); ?></textarea></td>
		      	</tr>
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD"><strong>Global template values<strong></th>
				</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Location seperator:</th>
			        <td><input name="location_seperator" type="text" value="<?php echo $events_template['location_seperator'];?>" size="6" /> (Default: @ )<br /><em>Can be text also. Ending spaces allowed.</em></td>
		      	</tr>
			</table>
		    <p class="submit">
		      	<input type="submit" name="Submit" value="Update Options &raquo;" />
		    </p>
			
	    	<h3>Management</h3>	    	
	
	    	<table class="form-table">
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Set these options to prevent certain userlevels from editing, creating or deleting events. The options panel user level cannot be changed.<br />For more information on user roles go to <a href="http://codex.wordpress.org/Roles_and_Capabilities#Summary_of_Roles" target="_blank">the codex</a>.</td>
				</tr>
		      	<tr valign="top">
			        <th scope="row">Add/edit events?</th>
			        <td><select name="events_minlevel">
				        <option value="10" <?php if($events_config['minlevel'] == "10") { echo 'selected'; } ?>>Administrator</option>
				        <option value="7" <?php if($events_config['minlevel'] == "7") { echo 'selected'; } ?>>Editor (default)</option>
				        <option value="2" <?php if($events_config['minlevel'] == "2") { echo 'selected'; } ?>>Author</option>
				        <option value="1" <?php if($events_config['minlevel'] == "1") { echo 'selected'; } ?>>Contributor</option>
				        <option value="0" <?php if($events_config['minlevel'] == "0") { echo 'selected'; } ?>>Subscriber</option>
					</select> <em>Can add/edit/view events.</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Delete events?</th>
			        <td><select name="events_managelevel">
				        <option value="10" <?php if($events_config['managelevel'] == "10") { echo 'selected'; } ?>>Administrator (default)</option>
				        <option value="7" <?php if($events_config['managelevel'] == "7") { echo 'selected'; } ?>>Editor</option>
				        <option value="2" <?php if($events_config['managelevel'] == "2") { echo 'selected'; } ?>>Author</option>
				        <option value="1" <?php if($events_config['managelevel'] == "1") { echo 'selected'; } ?>>Contributor</option>
				        <option value="0" <?php if($events_config['managelevel'] == "0") { echo 'selected'; } ?>>Subscriber</option>
					</select> <em>Can view/delete events.</em></td>
		      	</tr>
			</table>
		    <p class="submit">
		      	<input type="submit" name="Submit" value="Update Options &raquo;" />
		    </p>
			
		    <h3>Language</h3>	    	
	
		    <table class="form-table">
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Here you set the language of the plugin. Change the fields below to match your language.
				</tr>
		      	<tr valign="top">
			        <th scope="row">Today:</th>
			        <td><input name="events_language_today" type="text" value="<?php echo $events_language['language_today'];?>" size="45" /> (default: today)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Hours:</th>
			        <td><input name="events_language_hours" type="text" value="<?php echo $events_language['language_hours'];?>" size="45" /> (default: hours)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Minutes:</th>
			        <td><input name="events_language_minutes" type="text" value="<?php echo $events_language['language_minutes'];?>" size="45" /> (default: minutes)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Day:</th>
			        <td><input name="events_language_day" type="text" value="<?php echo $events_language['language_day'];?>" size="45" /> (default: day)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Days:</th>
			        <td><input name="events_language_days" type="text" value="<?php echo $events_language['language_days'];?>" size="45" /> (default: days)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">And:</th>
			        <td><input name="events_language_and" type="text" value="<?php echo $events_language['language_and'];?>" size="45" /> (default: and)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">On:</th>
			        <td><input name="events_language_on" type="text" value="<?php echo $events_language['language_on'];?>" size="45" /> (default: on)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">In:</th>
			        <td><input name="events_language_in" type="text" value="<?php echo $events_language['language_in'];?>" size="45" /> (default: in)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Ago:</th>
			        <td><input name="events_language_ago" type="text" value="<?php echo $events_language['language_ago'];?>" size="45" /> (default: ago)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Sidebar link:</th>
			        <td><input name="events_language_sidelink" type="text" value="<?php echo $events_language['language_sidelink'];?>" size="45" /> (default: more &raquo;)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Page link:</th>
			        <td><input name="events_language_pagelink" type="text" value="<?php echo $events_language['language_pagelink'];?>" size="45" /> (default: More information &raquo;)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">If there are no events to show:</th>
			        <td><input name="events_language_noevents" type="text" value="<?php echo $events_language['language_noevents'];?>" size="45" /> (default: No events to show)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">If there are no events today:</th>
			        <td><input name="events_language_nodaily" type="text" value="<?php echo $events_language['language_nodaily'];?>" size="45" /> (default: No events today)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">If the archive is empty:</th>
			        <td><input name="events_language_noarchive" type="text" value="<?php echo $events_language['language_noarchive'];?>" size="45" /> (default: No events in the archive)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">If there is an error:</th>
			        <td><input name="events_language_e_config" type="text" value="<?php echo $events_language['language_e_config'];?>" size="45" /> (default: A configuration error occured)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">If no duration is set for an event:</th>
			        <td><input name="events_language_noduration" type="text" value="<?php echo $events_language['language_noduration'];?>" size="45" /> (default: No duration!)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">If event is an all-day event:</th>
			        <td><input name="events_language_allday" type="text" value="<?php echo $events_language['language_allday'];?>" size="45" /> (default: All-day event!)</td>
		      	</tr>
			</table>
		    <p class="submit">
		      	<input type="submit" name="Submit" value="Update Options &raquo;" />
		    </p>
			
	    	<h3>Localization</h3>	    	
	
	    	<table class="form-table">
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Set the timezone to your timezone this ensures that no mishaps occur when you set the time for an event. <br />
					Localization can usually be en_EN. Changing this value should translate the dates to your language.<br />
					On Linux/Mac Osx (Darwin) you should use 'en_EN' in the field. For windows just 'en' should suffice. Your server most likely uses <strong><?php echo PHP_OS; ?>.</td>
				</tr>
		      	<tr valign="top">
			        <th scope="row">Your timezone?</th>
			        <td><select name="events_timezone">
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
	  	
    	<form method="post" action="<?php echo $_SERVER['REQUEST_URI'];?>">
	    	<table class="form-table">
				<tr valign="top">
					<td colspan="2" bgcolor="#DDD">Events installs a table in MySQL. When you disable the plugin the table will not be deleted. To delete the table use the button below.<br />
					For the techies: Upon un-installation the wp_events table will be dropped along with the events_config record in the wp_options table.</td>
				</tr>
		      	<tr valign="top">
			        <th scope="row">WARNING!</th>
			        <td><b style="color: #f00;">This process is irreversible and will delete ALL scheduled events!</b></td>
				</tr>
			</table>
	  		<p class="submit">
		    	<input type="hidden" name="event_uninstall" value="true" />
		    	<input onclick="return confirm('You are about to uninstall the events plugin\n  All scheduled events will be lost!\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" name="Submit" value="Uninstall Plugin &raquo;" />
	  		</p>
	  	</form>
	</div>
<?php
}
?>