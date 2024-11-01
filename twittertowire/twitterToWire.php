<?php
/*
Plugin Name: TwitterToWire
Plugin URI: http://codewarrior.getpaidfrom.us
Description: This plugin will allow your twits to be automatically added to your Buddy Press wire.  You must have 2 things:  1.  <a href="http://mu.wordpress.org/">Wordpress MU</a>, 2.  <a href="http://buddypress.org">Buddy Press</a>
Version: 1.4
Author: David Aubin
Author URI: http://codewarrior.getpaidfrom.us
 */

// Copyright (c) 2008-2009 www.getpaidfrom.us Dynamic Endeavors LLC. All rights reserved.
//
// Released under the GPL license
// http://www.opensource.org/licenses/gpl-license.php
//
// This is an add-on for WordPress MU 
// http://wordpress.org/
//
// **********************************************************************
// This program is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// **********************************************************************

// Version 1.1 - Change the way options are stored to happen when plugin is activated.
// Version 1.2 - Updated to work with BuddyPress trunk.  Also use BuddyPress action in wire to update the wire, not the blog loop_start action.
//             - However, to use the wire's action, it only works when placed in muplugin folder.
// Version 1.3 - Removed test code error of a call to bp_wire_update.  
// Version 1.4 - Removed call to bp_setup_globals in bp_twitter_to_wire_update, that caused a bug in 1.3 of the group wire not to show.
//             - Converted options panel to be on BuddyPress Member's Setting menu.


define ( 'BP_TWITTERTOWIRE_VERSION', '1.4' );

/* Define a slug constant that will be used to view this components pages (http://example.org/SLUG) */
if ( !defined( 'BP_TWITTERTOWIRE_SLUG' ) )
	define ( 'BP_TWITTERTOWIRE_SLUG', 'twittertowire' );

/**
 * bp_example_setup_globals()
 *
 * Sets up global variables for your component.
 */
function bp_twittertowire_setup_globals() {
	global $bp, $wpdb;
	
	$bp->twittertowire->slug = BP_TWITTERTOWIRE_SLUG;

	$bp->version_numbers->twittertowire = BP_TWITTERTOWIRE_VERSION;
}
add_action( 'plugins_loaded', 'bp_twittertowire_setup_globals', 5 );	
add_action( 'admin_menu', 'bp_twittertowire_setup_globals', 1 );

### Load WP-Config File If This File Is Called Directly
if (!function_exists('add_action')) {
	$wp_root = '../../..';
	require_once($wp_root.'/wp-config.php');
	require_once($wp_root.'/wp-content/plugins/buddypress/bp-core.php');
	require_once($wp_root.'/wp-content/plugins/buddypress/bp-wire/bp-wire-classes.php');
}
require_once( 'twitter.php' );

function bp_twitter_to_wire_update()
{
	global $bp, $current_user;

	//Get wire's user page.  Get the user's id.  Then we can use meta as our storage like Twire.

	$user_id = bp_current_user_id();

	$twitterToWireUsername = get_usermeta( $user_id, 'twire_username');
	$twitterToWirePassword = get_usermeta( $user_id, 'twire_password');
	$twitterToWireUserId   = $user_id;
	$twitterToWireLastTwit = get_usermeta( $user_id, 'twire_lastTwit');
	$twitterToWirePrefix   = get_usermeta( $user_id, 'twire_fromTwitterPrefix');

	if (class_exists('BP_Wire_Post')) 
	{	
		$twitter =  new TwitterToWireTwitter();
		$twitter->username=$twitterToWireUsername;
		$twitter->password=$twitterToWirePassword;
		$twitter->type='xml';
		$status = $twitter->userTimeline();
		$currentTwit = (string)$status->status[0]->text;

		#echo "$currentTwit == $twitterToWireLastTwit";

		if ($currentTwit == $twitterToWireLastTwit)
		{
			return true;
		}
		update_usermeta( $user_id, 'twire_lastTwit', $currentTwit );

		$user_id = $twitterToWireUserId;
		$message = $twitterToWirePrefix . " " . $currentTwit;
		$wire_post = new BP_Wire_Post( $bp->profile->table_name_wire );
		$wire_post->item_id = $user_id;
		$wire_post->user_id = $user_id;
		$wire_post->date_posted = time();
	
		$message = strip_tags( $message, '<a>,<b>,<strong>,<i>,<em>,<img>' );
		$wire_post->content = $message;

		if ( !$wire_post->save() )
			return false;

		do_action( 'bp_wire_post_posted', $wire_post->id, $wire_post->item_id, $wire_post->user_id );
	}
	return true;
}

function init_twitter_to_wire()
{
	global $current_user, $bp;
	get_currentuserinfo();

	$user_id = $current_user->id;

	$prefix = "Tweet from Twitter:";
	$twitterToWirePrefix   = get_usermeta( $user_id, 'twire_fromTwitterPrefix');
	if ( $twitterToWirePrefix == "" )
	{
		update_usermeta( $user_id, 'twire_fromTwitterPrefix', $prefix );
	}
}
add_action('bp_options_bar_before', 'bp_twitter_to_wire_update');
add_action("plugins_loaded", "init_twitter_to_wire");

function bp_twittertowire_screen_settings_menu() {
	global $bp, $current_user, $bp_settings_updated, $pass_error;

	if ( isset( $_POST['submit'] ) && check_admin_referer('bp-twittertowire-admin') ) {
		$bp_settings_updated = true;

		/** 
		 * This is when the user has hit the save button on their settings. 
		 * The best place to store these settings is in wp_usermeta. 
		 */
		update_usermeta( (int)$bp->loggedin_user->id, 'twire_fromTwitterPrefix', attribute_escape($_POST['TwitterToWire_prefix']) );
		update_usermeta( (int)$bp->loggedin_user->id, 'twire_username', attribute_escape($_POST['TwitterToWire_username']) );
		update_usermeta( (int)$bp->loggedin_user->id, 'twire_password', attribute_escape($_POST['TwitterToWire_password']) );
	}

	add_action( 'bp_template_content_header', 'bp_twittertowire_screen_settings_menu_header' );
	add_action( 'bp_template_title', 'bp_twittertowire_screen_settings_menu_title' );
	add_action( 'bp_template_content', 'bp_twittertowire_screen_settings_menu_content' );

	bp_core_load_template('plugin-template');
}

	function bp_twittertowire_screen_settings_menu_header() {
		_e( 'Twitter To Wire Settings', 'bp-twittertowire' );
	}

	function bp_twittertowire_screen_settings_menu_title() {
		_e( 'Twitter To Wire Settings', 'bp-twittertowire' );
	}

	function bp_twittertowire_screen_settings_menu_content() {
		global $bp, $bp_settings_updated; ?>

		<?php if ( $bp_settings_updated ) { ?>
			<div id="message" class="updated fade">
				<p><?php _e( 'Changes Saved.', 'bp-twittertowire' ) ?></p>
			</div>
		<?php } ?>

<?php
		$user_id = $bp->loggedin_user->id;

		$twitterToWire_prefix = "Tweet from Twitter:";
		if ( get_usermeta( $user_id, 'twire_fromTwitterPrefix') != "" )
		{
			$twitterToWire_prefix = get_usermeta( $user_id, 'twire_fromTwitterPrefix');
		}
		if ( get_usermeta( $user_id, 'twire_username') != "" )
		{
			$twitterToWire_username = get_usermeta( $user_id, 'twire_username');
		}
		if ( get_usermeta( $user_id, 'twire_password') != "" )
		{
			$twitterToWire_password = get_usermeta( $user_id, 'twire_password');
		}
?>
		<form action="<?php echo $bp->loggedin_user->domain . 'settings/twittertowire-admin'; ?>" name="bp-twittertowire-admin-form" id="account-delete-form" class="bp-twittertowire-admin-form" method="post">
				<label for="TwitterToWire_prefix">Prefix</label>
				<input name="TwitterToWire_prefix" type="text" id="TwitterToWire_prefix" value="<?php echo $twitterToWire_prefix; ?>" class="settings-input" />
				<br />

				<label for="TwitterToWire_username">Twitter Username</label>
				<input name="TwitterToWire_username" type="text" id="TwitterToWire_username" value="<?php echo $twitterToWire_username; ?>" class="settings-input" />
				<br />

				<label for="TwitterToWire_password">Twitter Password</label>
       				<input name="TwitterToWire_password" type="password" id="TwitterToWire_password" value="<?php echo $twitterToWire_password; ?>" class="settings-input" />
				<br />


	  		<p class="submit">
				<input type="submit" value="<?php _e( 'Save Settings', 'bp-twittertowire' ) ?> &raquo;" id="submit" name="submit" />
			</p>
			<?php 
			/* This is very important, don't leave it out. */
			wp_nonce_field( 'bp-twittertowire-admin' );
			?>
		</form>
	<?php
	}

function bp_twittertowire_setup_nav() {
	global $bp;

	$twittertowire_link = $bp->loggedin_user->domain . $bp->twittertowire->slug . '/';

	/* Add a nav item for this component under the settings nav item. See bp_example_screen_settings_menu() for more info */
	bp_core_add_subnav_item( 'settings', 'twittertowire-admin', __( 'Twitter To Wire', 'bp-twittertowire' ), $bp->loggedin_user->domain . 'settings/', 'bp_twittertowire_screen_settings_menu', false, bp_is_home() );
	
	/* Only execute the following code if we are actually viewing this component (e.g. http://example.org/example) */
	if ( $bp->current_component == $bp->twittertowire->slug ) {
		if ( bp_is_home() ) {
			/* If the user is viewing their own profile area set the title to "My Example" */
			$bp->bp_options_title = __( 'Twitter To Wire', 'bp-twittertowire' );
		} else {
			/* If the user is viewing someone elses profile area, set the title to "[user fullname]" */
			$bp->bp_options_avatar = bp_core_get_avatar( $bp->displayed_user->id, 1 );
			$bp->bp_options_title = $bp->displayed_user->fullname;
		}
	}
}
add_action( 'wp', 'bp_twittertowire_setup_nav', 2 );
add_action( 'admin_menu', 'bp_twittertowire_setup_nav', 2 );
?>
