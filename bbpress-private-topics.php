<?php
/*
Plugin Name: bbPress Private Topics
plugin URI:
Description: WPsite bbPress private topics allows only admin and the author of that topic to view a topic. This makes it so that any logged in user cannot see other user's topics.
version: 1.0.0
Author: WPSITE.NET
Author URI: http://www.wpsite.net
License: GPL2
*/

add_action('pre_get_posts', 'wps_private_topics');

/**
 * Only display topics to admins and the author of that topic
 *
 * @access public
 * @param mixed $query
 * @return void
 */
function wps_private_topics( $query ) {

	if (isset($query->query['post_type']) && $query->query['post_type'] == 'topic' && !current_user_can('manage_options')) {
		$query->set( 'author__in', array(get_current_user_id()));
		return;
	}

}