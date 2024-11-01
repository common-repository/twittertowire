<?php
/*
Plugin Name: TwitterToWire
Plugin URI: http://daveaubin.getpaidfrom.us
Description: This plugin will allow your twits to be automatically added to your Buddy Press wire.  You must have 3 things:  1.  <a href="http://mu.wordpress.org/">Wordpress MU</a>, 2.  <a href="http://buddypress.org">Buddy Press</a>, 3.  <a href="http://alexking.org/projects/wordpress">Twitter Tools Plugin</a>
Version: 1.0
Author: David Aubin
Author URI: http://daveaubin.getpaidfrom.us
 */

// Copyright (c) 2008 www.getpaidfrom.us Dynamic Endeavors LLC. All rights reserved.
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


### Load WP-Config File If This File Is Called Directly
if (!function_exists('add_action')) {
	$wp_root = '../..';
	require_once($wp_root.'/wp-config.php');
	require_once($wp_root.'/wp-content/mu-plugins/bp-core.php');
}

function ttw_plugin_options()
{
	$prefix = "Tweet from Twitter:";
	if ( get_option('TwitterToWire_prefix') != "" )
	{
		$prefix = get_option('TwitterToWire_prefix');
	}
?>
	<div class="wrap">
		<h2>Twitter To Wire Options</h2>
		<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
	
			<fieldset class="options">
				<p><strong>Twitter To Wire</strong></p>
				<label for="prefix">Prefix:</label>
        			<input name="prefix" type="text" id="prefix" value="<?php echo $prefix; ?>" size="25" />
       			</fieldset>

	  		<p><div class="submit"><input type="submit" name="update_twitterToWire" value="<?php _e('Update TwitterToWire', 'update_twitterToWire') ?>"  style="font-weight:bold;" /></div></p>
       		</form>       
	</div>
<?php
}
function ttw_options_page()
{
     if (isset($_POST['prefix'])) {
       global $current_user;
       get_currentuserinfo();

       $option_prefix = $_POST['prefix'];
       update_option('TwitterToWire_prefix', $option_prefix);
       update_option('TwitterToWire_userId', $current_user->ID);
       ?> <div class="updated"><p>Options changes saved.</p></div> <?php
     }
     ttw_plugin_options();
}

function bp_wire_update($tweet)
{
	global $bp;
	
	bp_core_setup_globals();

	$twitterToWirePrefix = trim(get_option('TwitterToWire_prefix'));
	$twitterToWireUserId = trim(get_option('TwitterToWire_userId'));

	if (class_exists('BP_Wire_Post')) 
	{	
		$user_id = $twitterToWireUserId;
		$message = $twitterToWirePrefix . " " . $tweet->tw_text;
		$wire_post = new BP_Wire_Post( $bp['profile']['table_name_wire'] );
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

function ttw_admin_menu() {
    $pagefile = basename(__FILE__);
    add_options_page('Twitter To Wire Options Page', 'Twitter To Wire', 8, $pagefile, 'ttw_options_page');
}
        
add_action('admin_menu', 'ttw_admin_menu');
add_action('aktt_add_tweet', 'bp_wire_update');
?>
