<?php
/*
Plugin Name: Events
Plugin URI: http://meandmymac.net/plugins/events/
Description: Enables the user to show a list of events with a static countdown to date. Sidebar widget and page template options. And more...
Author: Arnan de Gans
Version: 1.5.7
Author URI: http://meandmymac.net/
*/

#---------------------------------------------------
# Load other plugin files and configuration
#---------------------------------------------------
include_once(ABSPATH.'wp-content/plugins/wp-events/wp-events-install.php');
include_once(ABSPATH.'wp-content/plugins/wp-events/wp-events-functions.php');
include_once(ABSPATH.'wp-content/plugins/wp-events/wp-events-manage.php');
include_once(ABSPATH.'wp-content/plugins/wp-events/wp-events-widget.php');
register_activation_hook(__FILE__, 'events_activate');
events_check_config();

// Add filters for adding the tags in the WP page/post field
add_shortcode('events_list', 'events_list');
add_shortcode('events_today', 'events_today');
add_shortcode('events_archive', 'events_archive');

events_clear_old(); // Remove non archived old events

add_action('widgets_init', 'widget_wp_events_init'); //Initialize the widget
add_action('admin_menu', 'events_dashboard'); //Add page menu links
	
if(isset($_POST['events_submit'])) {
	add_action('init', 'events_insert_input'); //Save event
}

if(isset($_POST['add_category_submit'])) {
	add_action('init', 'events_create_category'); //Add a category
}

if(isset($_POST['delete_events']) OR isset($_POST['delete_categories'])) {
	add_action('init', 'events_request_delete'); //Delete events/categories
}

if(isset($_POST['events_submit_options'])) {
	add_action('init', 'events_options_submit'); //Update Options
}

if(isset($_POST['event_uninstall'])) {
	add_action('init', 'events_plugin_uninstall'); //Uninstall
}

// Load Options
$events_config = get_option('events_config');
$events_template = get_option('events_template');
$events_language = get_option('events_language');
setlocale(LC_TIME, $events_config['localization']);	

/*-------------------------------------------------------------
 Name:      events_dashboard

 Purpose:   Add pages to admin menus
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_dashboard() {
	global $events_config;

	add_submenu_page('post-new.php', 'Events', 'Event', $events_config['minlevel'], 'wp-events', 'events_schedule');
	add_submenu_page('edit.php', 'Events', 'Events', $events_config['minlevel'], 'wp-events', 'events_manage');
	add_submenu_page('options-general.php', 'Events', 'Events', $events_config['minlevel'], 'wp-events', 'events_options');
}

/*-------------------------------------------------------------
 Name:      events_manage

 Purpose:   Admin management page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_manage() {
	global $wpdb, $userdata, $events_config;
	
	$action = $_GET['action'];
	if(isset($_POST['order'])) { 
		$order = $_POST['order']; 
	} else { 
		$order = 'thetime DESC'; 
	} 
	if(isset($_POST['catorder'])) { 
		$catorder = $_POST['catorder']; 
	} else { 
		$catorder = 'id ASC'; 
	} ?>
	
	<?php if ($action == 'delete-event') { ?>
		<div id="message" class="updated fade"><p>Event <strong>deleted</strong></p></div>
	<?php } else if ($action == 'delete-category') { ?>
		<div id="message" class="updated fade"><p>Category <strong>deleted</strong></p></div>
	<?php } else if ($action == 'updated') { ?>
		<div id="message" class="updated fade"><p>Event <strong>updated</strong></p></div>
	<?php } else if ($action == 'no_access') { ?>
		<div id="message" class="updated fade"><p>Action prohibited</p></div>
	<?php } else if ($action == 'category_new') { ?>
		<div id="message" class="updated fade"><p>Category <strong>created</strong></p></div>
	<?php } else if ($action == 'category_field_error') { ?>
		<div id="message" class="updated fade"><p>No category name filled in</p></div>
	<?php } ?>

	<div class="wrap">
		<h2>Manage Events (<a href="post-new.php?page=wp-events">add new</a>)</h2>

		<form name="events" id="post" method="post" action="edit.php?page=wp-events">
			<div class="tablenav">

				<div class="alignleft">
					<input onclick="return confirm('You are about to delete multiple events!\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" value="Delete events" name="delete_events" class="button-secondary delete" />
					<select name='order' id='cat' class='postform' >
				        <option value="thetime DESC" <?php if($order == "thetime DESC") { echo 'selected'; } ?>>by date (descending, default)</option>
				        <option value="thetime ASC" <?php if($order == "thetime ASC") { echo 'selected'; } ?>>by date (ascending)</option>
				        <option value="ID ASC" <?php if($order == "ID ASC") { echo 'selected'; } ?>>in the order you made them (ascending)</option>
				        <option value="ID DESC" <?php if($order == "ID DESC") { echo 'selected'; } ?>>in the order you made them (descending)</option>
				        <option value="title ASC" <?php if($order == "title ASC") { echo 'selected'; } ?>>by title (A-Z)</option>
				        <option value="title DESC" <?php if($order == "title DESC") { echo 'selected'; } ?>>by title (Z-A)</option>
				        <option value="location ASC" <?php if($order == "location ASC") { echo 'selected'; } ?>>by location (A-Z)</option>
				        <option value="location DESC" <?php if($order == "location DESC") { echo 'selected'; } ?>>by location (Z-A)</option>
				        <option value="category ASC" <?php if($order == "category ASC") { echo 'selected'; } ?>>by category (A-Z)</option>
				        <option value="category DESC" <?php if($order == "category DESC") { echo 'selected'; } ?>>by category (Z-A)</option>
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
					<th scope="col" width="10%">Location</th>
					<th scope="col" width="10%">Category</th>
					<th scope="col">Title</th>
					<th scope="col" width="20%">Starts when</th>
					<th scope="col" width="20%">Ends after</th>
				</tr>
  			</thead>
  			<tbody>
		<?php 
		if(events_mysql_table_exists($wpdb->prefix.'events')) {
			$events = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."events` ORDER BY $order");
			if ($events) {
				foreach($events as $event) {
					$cat = $wpdb->get_row("SELECT name FROM " . $wpdb->prefix . "events_categories WHERE id = '".$event->category."'");
					$class = ('alternate' != $class) ? 'alternate' : ''; ?>
				    <tr id='event-<?php echo $event->id; ?>' class=' <?php echo $class; ?>'>
						<th scope="row" class="check-column"><input type="checkbox" name="eventcheck[]" value="<?php echo $event->id; ?>" /></th>
						<td><?php echo gmdate("F d Y H:i", $event->thetime);?></td>
						<td><?php echo stripslashes(html_entity_decode($event->location));?></td>
						<td><?php echo $cat->name; ?></td>
						<td><strong><a class="row-title" href="<?php echo get_option('siteurl').'/wp-admin/post-new.php?page=wp-events.php&amp;edit_event='.$event->id;?>" title="Edit"><?php echo stripslashes(html_entity_decode($event->title));?></a></strong></td>
						<td><?php echo events_countdown($event->thetime, $event->theend, $event->post_message, $event->allday); ?></td>
						<td><?php echo events_duration($event->thetime, $event->theend, $event->allday);?></td>
					</tr>
	 			<?php } ?>
	 		<?php } else { ?>
				<tr id='no-id'><td scope="row" colspan="7"><em>No Events yet!</em></td></tr>
			<?php 
			} 
		} else { ?>
			<tr id='no-id'><td scope="row" colspan="7"><span style="font-weight: bold; color: #f00;">There was an error locating the main database table for Events. Please deactivate and re-activate Events from the plugin page!!<br />If this does not solve the issue please seek support at <a href="http://forum.at.meandmymac.net">http://forum.at.meandmymac.net</a></span></td></tr>
		<?php }	?>
			</tbody>
		</table>
		</form>

		<br />
		<h2>Categories</h2>

		<form name="groups" id="post" method="post" action="edit.php?page=wp-events">
		<div class="tablenav">

			<div class="alignleft">
				<input onclick="return confirm('You are about to delete categories! Make sure there are no events in those categories or they will not show on the website\n\'OK\' to continue, \'Cancel\' to stop.')" type="submit" value="Delete category" name="delete_categories" class="button-secondary delete" />
				<select name='catorder' id='cat' class='postform' >
			        <option value="id ASC" <?php if($catorder == "id ASC") { echo 'selected'; } ?>>in the order you made them (ascending)</option>
			        <option value="id DESC" <?php if($catorder == "id DESC") { echo 'selected'; } ?>>in the order you made them (descending)</option>
			        <option value="name ASC" <?php if($catorder == "name ASC") { echo 'selected'; } ?>>by name (A-Z)</option>
			        <option value="name DESC" <?php if($catorder == "name DESC") { echo 'selected'; } ?>>by name (Z-A)</option>
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
					<th scope="col" width="5%">ID</th>
					<th scope="col">Name</th>
				</tr>
  			</thead>
  			<tbody>
		<?php 
		if(events_mysql_table_exists($wpdb->prefix.'events_categories')) {
		$categories = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "events_categories ORDER BY $catorder");
			if ($categories) {
				foreach($categories as $category) {
					$class = ('alternate' != $class) ? 'alternate' : ''; ?>
				    <tr id='group-<?php echo $category->id; ?>' class=' <?php echo $class; ?>'>
						<th scope="row" class="check-column"><input type="checkbox" name="categorycheck[]" value="<?php echo $category->id; ?>" /></th>
						<td><?php echo $category->id;?></td>
						<td><?php echo $category->name;?></td>
					</tr>
	 			<?php } ?>
			<?php 
			} 
		} else { ?>
			<tr id='no-id'><td scope="row" colspan="3"><span style="font-weight: bold; color: #f00;">There was an error locating the database table for the Events categories. Please deactivate and re-activate Events from the plugin page!!<br />If this does not solve the issue please seek support at <a href="http://forum.at.meandmymac.net">http://forum.at.meandmymac.net</a></span></td></tr>
		<?php }	?>
			    <tr id='category-new'>
					<th scope="row" class="check-column">&nbsp;</th>
					<td colspan="2"><input name="events_category" type="text" size="40" maxlength="255" value="" /> <input type="submit" id="post-query-submit" name="add_category_submit" value="Add" class="button-secondary" /></td>
				</tr>
 			</tbody>
		</table>
		</form>
	</div>
	<?php	 
}

/*-------------------------------------------------------------
 Name:      events_schedule

 Purpose:   Create new or edit events
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_schedule() {
	global $wpdb, $userdata, $events_config;
	
	$timezone = get_option('gmt_offset')*3600;
	
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
			list($sday, $smonth, $syear, $shour, $sminute) = split(" ", gmdate("d m Y H i", $edit_event->thetime));
			list($eday, $emonth, $eyear, $ehour, $eminute) = split(" ", gmdate("d m Y H i", $edit_event->theend));
		}
		
		$SQL2 = "SELECT * FROM ".$wpdb->prefix."events_categories ORDER BY id";
		$categories = $wpdb->get_results($SQL2);
		if($categories) { ?>
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
				        <th scope="row">Event description (optional):</th>
				        <td colspan="3"><textarea name="events_pre_event" cols="70" rows="8"><?php echo $edit_event->pre_message;?></textarea><br /><em>Maximum <?php echo $events_config['length'];?> characters. HTML allowed.</em></td>
			      	</tr>
			      	<tr>
				        <th scope="row">Location (optional):</th>
				        <td width="25%"><input name="events_location" type="text" size="25" maxlength="255" value="<?php echo $edit_event->location;?>" /><br /><em>Maximum 255 characters.</em></td>
				        <th scope="row">Category:</th>
				        <td width="25%" valign="top"><select name='events_category' id='cat' class='postform'>
						<?php foreach($categories as $category) { ?>
						    <option value="<?php echo $category->id; ?>" <?php if($category->id == $edit_event->category) { echo 'selected'; } ?>><?php echo $category->name; ?></option>
				    	<?php } ?>
				    	</select></td>
			      	</tr>
			      	<tr>
				        <th scope="row">Startdate Day/Month/Year:</th>
				        <td width="25%">
				        	<input id="title" name="events_sday" type="text" size="4" maxlength="2" value="<?php echo $sday;?>" /> / 
							<select name="events_smonth">
								<option value="01" <?php if($smonth == "01") { echo 'selected'; } ?>>January</option>
								<option value="02" <?php if($smonth == "02") { echo 'selected'; } ?>>February</option>
								<option value="03" <?php if($smonth == "03") { echo 'selected'; } ?>>March</option>
								<option value="04" <?php if($smonth == "04") { echo 'selected'; } ?>>April</option>
								<option value="05" <?php if($smonth == "05") { echo 'selected'; } ?>>May</option>
								<option value="06" <?php if($smonth == "06") { echo 'selected'; } ?>>June</option>
								<option value="07" <?php if($smonth == "07") { echo 'selected'; } ?>>July</option>
								<option value="08" <?php if($smonth == "08") { echo 'selected'; } ?>>August</option>
								<option value="09" <?php if($smonth == "09") { echo 'selected'; } ?>>September</option>
								<option value="10" <?php if($smonth == "10") { echo 'selected'; } ?>>October</option>
								<option value="11" <?php if($smonth == "11") { echo 'selected'; } ?>>November</option>
								<option value="12" <?php if($smonth == "12") { echo 'selected'; } ?>>December</option>
							</select> / 
							<input name="events_syear" type="text" size="4" maxlength="4" value="<?php echo $syear;?>" />	
						</td>
				        <th scope="row">Hour/Minute (optional):</th>
				        <td width="25%"><select name="events_shour">
				        <option value="00" <?php if($shour == "00") { echo 'selected'; } ?>>00</option>
				        <option value="01" <?php if($shour == "01") { echo 'selected'; } ?>>01</option>
				        <option value="02" <?php if($shour == "02") { echo 'selected'; } ?>>02</option>
				        <option value="03" <?php if($shour == "03") { echo 'selected'; } ?>>03</option>
				        <option value="04" <?php if($shour == "04") { echo 'selected'; } ?>>04</option>
				        <option value="05" <?php if($shour == "05") { echo 'selected'; } ?>>05</option>
				        <option value="06" <?php if($shour == "06") { echo 'selected'; } ?>>06</option>
				        <option value="07" <?php if($shour == "07") { echo 'selected'; } ?>>07</option>
				        <option value="08" <?php if($shour == "08") { echo 'selected'; } ?>>08</option>
				        <option value="09" <?php if($shour == "09") { echo 'selected'; } ?>>09</option>
				        <option value="10" <?php if($shour == "10") { echo 'selected'; } ?>>10</option>
				        <option value="11" <?php if($shour == "11") { echo 'selected'; } ?>>11</option>
				        <option value="12" <?php if($shour == "12") { echo 'selected'; } ?>>12</option>
				        <option value="13" <?php if($shour == "13") { echo 'selected'; } ?>>13</option>
				        <option value="14" <?php if($shour == "14") { echo 'selected'; } ?>>14</option>
				        <option value="15" <?php if($shour == "15") { echo 'selected'; } ?>>15</option>
				        <option value="16" <?php if($shour == "16") { echo 'selected'; } ?>>16</option>
				        <option value="17" <?php if($shour == "17") { echo 'selected'; } ?>>17</option>
				        <option value="18" <?php if($shour == "18") { echo 'selected'; } ?>>18</option>
				        <option value="19" <?php if($shour == "19") { echo 'selected'; } ?>>19</option>
				        <option value="20" <?php if($shour == "20") { echo 'selected'; } ?>>20</option>
				        <option value="21" <?php if($shour == "21") { echo 'selected'; } ?>>21</option>
				        <option value="22" <?php if($shour == "22") { echo 'selected'; } ?>>22</option>
				        <option value="23" <?php if($shour == "23") { echo 'selected'; } ?>>23</option>
					</select> / <select name="events_sminute">
				        <option value="00" <?php if($sminute == "00") { echo 'selected'; } ?>>00</option>
				        <option value="01" <?php if($sminute == "01") { echo 'selected'; } ?>>01</option>
				        <option value="02" <?php if($sminute == "02") { echo 'selected'; } ?>>02</option>
				        <option value="03" <?php if($sminute == "03") { echo 'selected'; } ?>>03</option>
				        <option value="04" <?php if($sminute == "04") { echo 'selected'; } ?>>04</option>
				        <option value="05" <?php if($sminute == "05") { echo 'selected'; } ?>>05</option>
				        <option value="06" <?php if($sminute == "06") { echo 'selected'; } ?>>06</option>
				        <option value="07" <?php if($sminute == "07") { echo 'selected'; } ?>>07</option>
				        <option value="08" <?php if($sminute == "08") { echo 'selected'; } ?>>08</option>
				        <option value="09" <?php if($sminute == "09") { echo 'selected'; } ?>>09</option>
				        <option value="10" <?php if($sminute == "10") { echo 'selected'; } ?>>10</option>
				        <option value="11" <?php if($sminute == "11") { echo 'selected'; } ?>>11</option>
				        <option value="12" <?php if($sminute == "12") { echo 'selected'; } ?>>12</option>
				        <option value="13" <?php if($sminute == "13") { echo 'selected'; } ?>>13</option>
				        <option value="14" <?php if($sminute == "14") { echo 'selected'; } ?>>14</option>
				        <option value="15" <?php if($sminute == "15") { echo 'selected'; } ?>>15</option>
				        <option value="16" <?php if($sminute == "16") { echo 'selected'; } ?>>16</option>
				        <option value="17" <?php if($sminute == "17") { echo 'selected'; } ?>>17</option>
				        <option value="18" <?php if($sminute == "18") { echo 'selected'; } ?>>18</option>
				        <option value="19" <?php if($sminute == "19") { echo 'selected'; } ?>>19</option>
				        <option value="20" <?php if($sminute == "20") { echo 'selected'; } ?>>20</option>
				        <option value="21" <?php if($sminute == "21") { echo 'selected'; } ?>>21</option>
				        <option value="22" <?php if($sminute == "22") { echo 'selected'; } ?>>22</option>
				        <option value="23" <?php if($sminute == "23") { echo 'selected'; } ?>>23</option>
				        <option value="24" <?php if($sminute == "24") { echo 'selected'; } ?>>24</option>
				        <option value="25" <?php if($sminute == "25") { echo 'selected'; } ?>>25</option>
				        <option value="26" <?php if($sminute == "26") { echo 'selected'; } ?>>26</option>
				        <option value="27" <?php if($sminute == "27") { echo 'selected'; } ?>>27</option>
				        <option value="28" <?php if($sminute == "28") { echo 'selected'; } ?>>28</option>
				        <option value="29" <?php if($sminute == "29") { echo 'selected'; } ?>>29</option>
				        <option value="30" <?php if($sminute == "30") { echo 'selected'; } ?>>30</option>
				        <option value="31" <?php if($sminute == "31") { echo 'selected'; } ?>>31</option>
				        <option value="32" <?php if($sminute == "32") { echo 'selected'; } ?>>32</option>
				        <option value="33" <?php if($sminute == "33") { echo 'selected'; } ?>>33</option>
				        <option value="34" <?php if($sminute == "34") { echo 'selected'; } ?>>34</option>
				        <option value="35" <?php if($sminute == "35") { echo 'selected'; } ?>>35</option>
				        <option value="36" <?php if($sminute == "36") { echo 'selected'; } ?>>36</option>
				        <option value="37" <?php if($sminute == "37") { echo 'selected'; } ?>>37</option>
				        <option value="38" <?php if($sminute == "38") { echo 'selected'; } ?>>38</option>
				        <option value="39" <?php if($sminute == "39") { echo 'selected'; } ?>>39</option>
				        <option value="40" <?php if($sminute == "40") { echo 'selected'; } ?>>40</option>
				        <option value="41" <?php if($sminute == "41") { echo 'selected'; } ?>>41</option>
				        <option value="42" <?php if($sminute == "42") { echo 'selected'; } ?>>42</option>
				        <option value="43" <?php if($sminute == "43") { echo 'selected'; } ?>>43</option>
				        <option value="44" <?php if($sminute == "44") { echo 'selected'; } ?>>44</option>
				        <option value="45" <?php if($sminute == "45") { echo 'selected'; } ?>>45</option>
				        <option value="46" <?php if($sminute == "46") { echo 'selected'; } ?>>46</option>
				        <option value="47" <?php if($sminute == "47") { echo 'selected'; } ?>>47</option>
				        <option value="48" <?php if($sminute == "48") { echo 'selected'; } ?>>48</option>
				        <option value="49" <?php if($sminute == "49") { echo 'selected'; } ?>>49</option>
				        <option value="50" <?php if($sminute == "50") { echo 'selected'; } ?>>50</option>
				        <option value="51" <?php if($sminute == "51") { echo 'selected'; } ?>>51</option>
				        <option value="52" <?php if($sminute == "52") { echo 'selected'; } ?>>52</option>
				        <option value="53" <?php if($sminute == "53") { echo 'selected'; } ?>>53</option>
				        <option value="54" <?php if($sminute == "54") { echo 'selected'; } ?>>54</option>
				        <option value="55" <?php if($sminute == "55") { echo 'selected'; } ?>>55</option>
				        <option value="56" <?php if($sminute == "56") { echo 'selected'; } ?>>56</option>
				        <option value="57" <?php if($sminute == "57") { echo 'selected'; } ?>>57</option>
				        <option value="58" <?php if($sminute == "58") { echo 'selected'; } ?>>58</option>
				        <option value="59" <?php if($sminute == "59") { echo 'selected'; } ?>>59</option>
				        <option value="60" <?php if($sminute == "60") { echo 'selected'; } ?>>60</option>
					</select></td>
			      	</tr>
			      	<tr>
				        <th scope="row">Enddate Day/Month/Year (optional):</th>
				        <td width="25%">
				        	<input id="title" name="events_eday" type="text" size="4" maxlength="2" value="<?php echo $eday;?>" /> / 
							<select name="events_emonth">
								<option value="" <?php if($emonth == "") { echo 'selected'; } ?>>--</option>
								<option value="01" <?php if($emonth == "01") { echo 'selected'; } ?>>January</option>
								<option value="02" <?php if($emonth == "02") { echo 'selected'; } ?>>February</option>
								<option value="03" <?php if($emonth == "03") { echo 'selected'; } ?>>March</option>
								<option value="04" <?php if($emonth == "04") { echo 'selected'; } ?>>April</option>
								<option value="05" <?php if($emonth == "05") { echo 'selected'; } ?>>May</option>
								<option value="06" <?php if($emonth == "06") { echo 'selected'; } ?>>June</option>
								<option value="07" <?php if($emonth == "07") { echo 'selected'; } ?>>July</option>
								<option value="08" <?php if($emonth == "08") { echo 'selected'; } ?>>August</option>
								<option value="09" <?php if($emonth == "09") { echo 'selected'; } ?>>September</option>
								<option value="10" <?php if($emonth == "10") { echo 'selected'; } ?>>October</option>
								<option value="11" <?php if($emonth == "11") { echo 'selected'; } ?>>November</option>
								<option value="12" <?php if($emonth == "12") { echo 'selected'; } ?>>December</option>
							</select> / 
							<input name="events_eyear" type="text" size="4" maxlength="4" value="<?php echo $eyear;?>" /></td>
				        <th scope="row">Hour/Minute (optional):</th>
				        <td width="25%"><select name="events_ehour">
				        <option value="" <?php if($ehour == "") { echo 'selected'; } ?>>--</option>
				        <option value="00" <?php if($ehour == "00") { echo 'selected'; } ?>>00</option>
				        <option value="01" <?php if($ehour == "01") { echo 'selected'; } ?>>01</option>
				        <option value="02" <?php if($ehour == "02") { echo 'selected'; } ?>>02</option>
				        <option value="03" <?php if($ehour == "03") { echo 'selected'; } ?>>03</option>
				        <option value="04" <?php if($ehour == "04") { echo 'selected'; } ?>>04</option>
				        <option value="05" <?php if($ehour == "05") { echo 'selected'; } ?>>05</option>
				        <option value="06" <?php if($ehour == "06") { echo 'selected'; } ?>>06</option>
				        <option value="07" <?php if($ehour == "07") { echo 'selected'; } ?>>07</option>
				        <option value="08" <?php if($ehour == "08") { echo 'selected'; } ?>>08</option>
				        <option value="09" <?php if($ehour == "09") { echo 'selected'; } ?>>09</option>
				        <option value="10" <?php if($ehour == "10") { echo 'selected'; } ?>>10</option>
				        <option value="11" <?php if($ehour == "11") { echo 'selected'; } ?>>11</option>
				        <option value="12" <?php if($ehour == "12") { echo 'selected'; } ?>>12</option>
				        <option value="13" <?php if($ehour == "13") { echo 'selected'; } ?>>13</option>
				        <option value="14" <?php if($ehour == "14") { echo 'selected'; } ?>>14</option>
				        <option value="15" <?php if($ehour == "15") { echo 'selected'; } ?>>15</option>
				        <option value="16" <?php if($ehour == "16") { echo 'selected'; } ?>>16</option>
				        <option value="17" <?php if($ehour == "17") { echo 'selected'; } ?>>17</option>
				        <option value="18" <?php if($ehour == "18") { echo 'selected'; } ?>>18</option>
				        <option value="19" <?php if($ehour == "19") { echo 'selected'; } ?>>19</option>
				        <option value="20" <?php if($ehour == "20") { echo 'selected'; } ?>>20</option>
				        <option value="21" <?php if($ehour == "21") { echo 'selected'; } ?>>21</option>
				        <option value="22" <?php if($ehour == "22") { echo 'selected'; } ?>>22</option>
				        <option value="23" <?php if($ehour == "23") { echo 'selected'; } ?>>23</option>
					</select> / <select name="events_eminute">
				        <option value="" <?php if($eminute == "") { echo 'selected'; } ?>>--</option>
				        <option value="00" <?php if($eminute == "00") { echo 'selected'; } ?>>00</option>
				        <option value="01" <?php if($eminute == "01") { echo 'selected'; } ?>>01</option>
				        <option value="02" <?php if($eminute == "02") { echo 'selected'; } ?>>02</option>
				        <option value="03" <?php if($eminute == "03") { echo 'selected'; } ?>>03</option>
				        <option value="04" <?php if($eminute == "04") { echo 'selected'; } ?>>04</option>
				        <option value="05" <?php if($eminute == "05") { echo 'selected'; } ?>>05</option>
				        <option value="06" <?php if($eminute == "06") { echo 'selected'; } ?>>06</option>
				        <option value="07" <?php if($eminute == "07") { echo 'selected'; } ?>>07</option>
				        <option value="08" <?php if($eminute == "08") { echo 'selected'; } ?>>08</option>
				        <option value="09" <?php if($eminute == "09") { echo 'selected'; } ?>>09</option>
				        <option value="10" <?php if($eminute == "10") { echo 'selected'; } ?>>10</option>
				        <option value="11" <?php if($eminute == "11") { echo 'selected'; } ?>>11</option>
				        <option value="12" <?php if($eminute == "12") { echo 'selected'; } ?>>12</option>
				        <option value="13" <?php if($eminute == "13") { echo 'selected'; } ?>>13</option>
				        <option value="14" <?php if($eminute == "14") { echo 'selected'; } ?>>14</option>
				        <option value="15" <?php if($eminute == "15") { echo 'selected'; } ?>>15</option>
				        <option value="16" <?php if($eminute == "16") { echo 'selected'; } ?>>16</option>
				        <option value="17" <?php if($eminute == "17") { echo 'selected'; } ?>>17</option>
				        <option value="18" <?php if($eminute == "18") { echo 'selected'; } ?>>18</option>
				        <option value="19" <?php if($eminute == "19") { echo 'selected'; } ?>>19</option>
				        <option value="20" <?php if($eminute == "20") { echo 'selected'; } ?>>20</option>
				        <option value="21" <?php if($eminute == "21") { echo 'selected'; } ?>>21</option>
				        <option value="22" <?php if($eminute == "22") { echo 'selected'; } ?>>22</option>
				        <option value="23" <?php if($eminute == "23") { echo 'selected'; } ?>>23</option>
				        <option value="24" <?php if($eminute == "24") { echo 'selected'; } ?>>24</option>
				        <option value="25" <?php if($eminute == "25") { echo 'selected'; } ?>>25</option>
				        <option value="26" <?php if($eminute == "26") { echo 'selected'; } ?>>26</option>
				        <option value="27" <?php if($eminute == "27") { echo 'selected'; } ?>>27</option>
				        <option value="28" <?php if($eminute == "28") { echo 'selected'; } ?>>28</option>
				        <option value="29" <?php if($eminute == "29") { echo 'selected'; } ?>>29</option>
				        <option value="30" <?php if($eminute == "30") { echo 'selected'; } ?>>30</option>
				        <option value="31" <?php if($eminute == "31") { echo 'selected'; } ?>>31</option>
				        <option value="32" <?php if($eminute == "32") { echo 'selected'; } ?>>32</option>
				        <option value="33" <?php if($eminute == "33") { echo 'selected'; } ?>>33</option>
				        <option value="34" <?php if($eminute == "34") { echo 'selected'; } ?>>34</option>
				        <option value="35" <?php if($eminute == "35") { echo 'selected'; } ?>>35</option>
				        <option value="36" <?php if($eminute == "36") { echo 'selected'; } ?>>36</option>
				        <option value="37" <?php if($eminute == "37") { echo 'selected'; } ?>>37</option>
				        <option value="38" <?php if($eminute == "38") { echo 'selected'; } ?>>38</option>
				        <option value="39" <?php if($eminute == "39") { echo 'selected'; } ?>>39</option>
				        <option value="40" <?php if($eminute == "40") { echo 'selected'; } ?>>40</option>
				        <option value="41" <?php if($eminute == "41") { echo 'selected'; } ?>>41</option>
				        <option value="42" <?php if($eminute == "42") { echo 'selected'; } ?>>42</option>
				        <option value="43" <?php if($eminute == "43") { echo 'selected'; } ?>>43</option>
				        <option value="44" <?php if($eminute == "44") { echo 'selected'; } ?>>44</option>
				        <option value="45" <?php if($eminute == "45") { echo 'selected'; } ?>>45</option>
				        <option value="46" <?php if($eminute == "46") { echo 'selected'; } ?>>46</option>
				        <option value="47" <?php if($eminute == "47") { echo 'selected'; } ?>>47</option>
				        <option value="48" <?php if($eminute == "48") { echo 'selected'; } ?>>48</option>
				        <option value="49" <?php if($eminute == "49") { echo 'selected'; } ?>>49</option>
				        <option value="50" <?php if($eminute == "50") { echo 'selected'; } ?>>50</option>
				        <option value="51" <?php if($eminute == "51") { echo 'selected'; } ?>>51</option>
				        <option value="52" <?php if($eminute == "52") { echo 'selected'; } ?>>52</option>
				        <option value="53" <?php if($eminute == "53") { echo 'selected'; } ?>>53</option>
				        <option value="54" <?php if($eminute == "54") { echo 'selected'; } ?>>54</option>
				        <option value="55" <?php if($eminute == "55") { echo 'selected'; } ?>>55</option>
				        <option value="56" <?php if($eminute == "56") { echo 'selected'; } ?>>56</option>
				        <option value="57" <?php if($eminute == "57") { echo 'selected'; } ?>>57</option>
				        <option value="58" <?php if($eminute == "58") { echo 'selected'; } ?>>58</option>
				        <option value="59" <?php if($eminute == "59") { echo 'selected'; } ?>>59</option>
				        <option value="60" <?php if($eminute == "60") { echo 'selected'; } ?>>60</option>
					</select></td>
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
				        <td colspan="3"><textarea name="events_post_event" cols="70" rows="2"><?php echo $edit_event->post_message;?></textarea><br />
				        	<em>Maximum <?php echo $events_config['length'];?> characters. HTML allowed.</em></td>
			      	</tr>
			      	<tr>
				        <th scope="row">Link to page (optional):</th>
				        <td colspan="3"><input name="events_link" type="text" size="52 " maxlength="10000" value="<?php echo $edit_event->link;?>" /><br />
				        	<em>Include full url and http://, this can be any page. Required if checkbox above is checked!</em></td>
			      	</tr>
		    	</table>
		    	
		    	<p class="submit">
					<?php if($event_edit_id) { ?>
					<input type="submit" name="submit_save" value="Edit event" /> 
					<input type="submit" name="submit_new" value="Duplicate event" /> 
					<?php } else { ?>
					<input type="submit" name="submit_save" value="Save event" />
					<?php } ?>
		    	</p>
	
		  	</form>
		<?php } else { ?>
		    <table class="form-table">
				<tr valign="top">
					<td bgcolor="#DDD"><strong>You should create atleast one category before adding events! <a href="edit.php?page=wp-events">Add a category now</a>.</strong><br />Tip: If you do not want to use categories create one "uncategorized" and put all events in there. You don't have to show the categories on your blog.</td>
				</tr>
			</table>
		<?php } ?>
	</div>
<?php }


/*-------------------------------------------------------------
 Name:      events_options

 Purpose:   Admin options page
 Receive:   -none-
 Return:    -none-
-------------------------------------------------------------*/
function events_options() {
	$events_config = get_option('events_config');
	$events_template = get_option('events_template');
	$events_language = get_option('events_language');
	
	$gmt_offset = (get_option('gmt_offset')*3600);
	$timezone = gmdate("U") + $gmt_offset;
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
			        <th scope="row">Show</th>
			        <td colspan="3"><select name="events_sideshow">
				        <option value="1" <?php if($events_config['sideshow'] == "1") { echo 'selected'; } ?>>Future events including events that happen today (default)</option>
				        <option value="2" <?php if($events_config['sideshow'] == "2") { echo 'selected'; } ?>>Events that didn't start yet</option>
				        <option value="3" <?php if($events_config['sideshow'] == "3") { echo 'selected'; } ?>>Events that didn't end yet</option>
				        <option value="4" <?php if($events_config['sideshow'] == "4") { echo 'selected'; } ?>>The archive</option>
					</select></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Date format</th>
			        <?php if($events_config['custom_date_sidebar'] == 'no') { ?>
			        <td><select name="events_dateformat_sidebar">
				        <option disabled="disabled">-- day month year --</option>
				        <option value="%d %m %Y" <?php if($events_config['dateformat_sidebar'] == "%d %m %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%d %m %Y", $timezone)); ?></option>
				        <option value="%d %b %Y" <?php if($events_config['dateformat_sidebar'] == "%d %b %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%d %b %Y", $timezone)); ?> (default)</option>
				        <option value="%d %B %Y" <?php if($events_config['dateformat_sidebar'] == "%d %B %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%d %B %Y", $timezone)); ?></option>
				        <option disabled="disabled">-- month day year --</option>
				        <option value="%m %d %Y" <?php if($events_config['dateformat_sidebar'] == "%m %d %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%m %d %Y", $timezone)); ?></option>
				        <option value="%b %d %Y" <?php if($events_config['dateformat_sidebar'] == "%b %d %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%b %d %Y", $timezone)); ?></option>
				        <option value="%B %d %Y" <?php if($events_config['dateformat_sidebar'] == "%B %d %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%B %d %Y", $timezone)); ?></option>
				        <option disabled="disabled">-- weekday day/month/year --</option>
				        <option value="%a, %d %B %Y" <?php if($events_config['dateformat_sidebar'] == "%a, %d %B %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%a, %d %B %Y", $timezone)); ?></option>
				        <option value="%A, %d %B %Y" <?php if($events_config['dateformat_sidebar'] == "%A, %d %B %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%A, %d %B %Y", $timezone)); ?></option>
				        <option disabled="disabled">-- preferred by locale --</option>
				        <option value="%x" <?php if($events_config['dateformat_sidebar'] == "%x") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%x", $timezone)); ?></option>
					</select></td>
					<?php } else { ?>
 			        <td><input name="events_dateformat_sidebar" type="text" value="<?php echo $events_config['dateformat_sidebar'];?>" size="30" /><br />Careful what you put here! Learn: <a href="http://www.php.net/manual/en/function.gmstrftime.php" target="_blank">php manual</a>.</td>
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
					</select><br />Save options to see the result!</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Time format</th>
			        <td colspan="3"><select name="events_timeformat_sidebar">
				        <option disabled="disabled">-- 24-hour clock --</option>
				        <option value="%H:%M" <?php if($events_config['timeformat_sidebar'] == "%H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%H:%M", $timezone)); ?> (default)</option>
				        <option value="%H:%M:%S" <?php if($events_config['timeformat_sidebar'] == "%H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%H:%M:%S", $timezone)); ?></option>
				        <option disabled="disabled">-- 12-hour clock --</option>
				        <option value="%I:%M %p" <?php if($events_config['timeformat_sidebar'] == "%I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%I:%M %p", $timezone)); ?></option>
				        <option value="%I:%M:%S %p" <?php if($events_config['timeformat_sidebar'] == "%I:%M:%S %p") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%I:%M:%S %p", $timezone)); ?></option>
				        <option disabled="disabled">-- preferred by locale --</option>
				        <option value="%X" <?php if($events_config['timeformat_sidebar'] == "%X") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%X", $timezone)); ?></option>
					</select></td>
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
				        <option value="%d %m %Y" <?php if($events_config['dateformat'] == "%d %m %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%d %m %Y", $timezone)); ?></option>
				        <option value="%d %b %Y" <?php if($events_config['dateformat'] == "%d %b %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%d %b %Y", $timezone)); ?></option>
				        <option value="%d %B %Y" <?php if($events_config['dateformat'] == "%d %B %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%d %B %Y", $timezone)); ?> (default)</option>
				        <option disabled="disabled">-- month day year --</option>
				        <option value="%m %d %Y" <?php if($events_config['dateformat'] == "%m %d %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%m %d %Y", $timezone)); ?></option>
				        <option value="%b %d %Y" <?php if($events_config['dateformat'] == "%d %b %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%d %b %Y", $timezone)); ?></option>
				        <option value="%B %d %Y" <?php if($events_config['dateformat'] == "%B %d %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%B %d %Y", $timezone)); ?></option>
				        <option disabled="disabled">-- weekday day/month/year --</option>
				        <option value="%a, %d %B %Y" <?php if($events_config['dateformat'] == "%a, %d %B %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%a, %d %B %Y", $timezone)); ?></option>
				        <option value="%A, %d %B %Y" <?php if($events_config['dateformat'] == "%A, %d %B %Y") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%A, %d %B %Y", $timezone)); ?></option>
				        <option disabled="disabled">-- preferred by locale --</option>
				        <option value="%x" <?php if($events_config['dateformat'] == "%x") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%x", $timezone)); ?></option>
					</select></td>
					<?php } else { ?>
 			        <td><input name="events_dateformat" type="text" value="<?php echo $events_config['dateformat'];?>" size="30" /><br />Careful what you put here. Learn: <a href="http://www.php.net/manual/en/function.gmstrftime.php" target="_blank">php manual</a>.</td>
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
					</select><br />Save options to see the result!</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Time format</th>
			        <td colspan="3"><select name="events_timeformat">
				        <option disabled="disabled">-- 24-hour clock --</option>
				        <option value="%H:%M" <?php if($events_config['timeformat'] == "%H:%M") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%H:%M", $timezone)); ?> (default)</option>
				        <option value="%H:%M:%S" <?php if($events_config['timeformat'] == "%H:%M:%S") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%H:%M:%S", $timezone)); ?></option>
				        <option disabled="disabled">-- 12-hour clock --</option>
				        <option value="%I:%M %p" <?php if($events_config['timeformat'] == "%I:%M %p") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%I:%M %p", $timezone)); ?></option>
				        <option value="%I:%M:%S %p" <?php if($events_config['timeformat'] == "%I:%M:%S %p") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%I:%M:%S %p", $timezone)); ?></option>
				        <option disabled="disabled">-- preferred by locale --</option>
				        <option value="%X" <?php if($events_config['timeformat'] == "%X") { echo 'selected'; } ?>><?php echo utf8_encode(gmstrftime("%X", $timezone)); ?></option>
					</select></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Character limit</th>
			        <td colspan="3"><input name="events_length" type="text" value="<?php echo $events_config['length'];?>" size="6" /> (default: 1000)</td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row">Date parsing</th>
			        <td colspan="3"><select name="events_hideend">
				        <option value="hide" <?php if($events_config['hideend'] == "hide") { echo 'selected'; } ?>>Hide the ending date if it's the same as the starting date</option>
				        <option value="show" <?php if($events_config['hideend'] == "show") { echo 'selected'; } ?>>Show the ending date even if it's the same as the starting date</option>
					</select></td>
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
			        <td><textarea name="sidebar_h_template" cols="50" rows="4"><?php echo stripslashes($events_template['sidebar_h_template']); ?></textarea></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Body:</th>
			        <td><textarea name="sidebar_template" cols="50" rows="4"><?php echo stripslashes($events_template['sidebar_template']); ?></textarea><br /><em>Options: %title% %event% %link% %countdown% %startdate% %starttime% %author% %location% %category%</em></td>
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
			        <td><textarea name="page_h_template" cols="50" rows="4"><?php echo stripslashes($events_template['page_h_template']); ?></textarea><br /><em>Options: %category%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Body:</th>
			        <td><textarea name="page_template" cols="50" rows="4"><?php echo stripslashes($events_template['page_template']); ?></textarea><br /><em>Options: %title% %event% %after% %link% %startdate% %starttime% %enddate% %endtime% %duration% %countdown% %author% %location% %category%</em></td>
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
			        <td><textarea name="archive_h_template" cols="50" rows="4"><?php echo stripslashes($events_template['archive_h_template']); ?></textarea><br /><em>Options: %category%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Body:</th>
			        <td><textarea name="archive_template" cols="50" rows="4"><?php echo stripslashes($events_template['archive_template']); ?></textarea><br /><em>Options: %title% %event% %after% %link% %startdate% %starttime% %enddate% %endtime% %duration% %countup% %author% %location% %category%</em></td>
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
			        <td><textarea name="daily_h_template" cols="50" rows="4"><?php echo stripslashes($events_template['daily_h_template']); ?></textarea><br /><em>Options: %category%</em></td>
		      	</tr>
		      	<tr valign="top">
			        <th scope="row" valign="top">Body:</th>
			        <td><textarea name="daily_template" cols="50" rows="4"><?php echo stripslashes($events_template['daily_template']); ?></textarea><br /><em>Options: %title% %event% %after% %link% %startdate% %starttime% %enddate% %endtime% %duration% %countdown% %author% %location% %category%</em></td>
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
			        <th scope="row">If the event already happened:</th>
			        <td><input name="events_language_past" type="text" value="<?php echo $events_language['language_past'];?>" size="45" /> (default: Past event!)</td>
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
					<td colspan="2" bgcolor="#DDD">Localization can usually be en_EN. Changing this value should translate the dates to your language.<br />
					On Linux/Mac Osx (Darwin) you should use 'en_EN' in the field. For windows just 'en' should suffice. Your server most likely uses <strong><?php echo PHP_OS; ?>.</td>
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