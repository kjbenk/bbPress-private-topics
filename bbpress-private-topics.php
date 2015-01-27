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

	// Check if we are trying to retireve topics and that user is not an admin

	if (isset($query->query['post_type']) && $query->query['post_type'] == 'topic' && !current_user_can('manage_options')) {

		// Add parameter for dislaying posts only created by the current user

		$query->set( 'author__in', array(get_current_user_id()));
		return;
	}
}

add_action( 'bbp_new_topic_pre_extras', 'wps_check_topics_in_progress' );

/**
 * Return an Error message if user currently has one topic in progress and
 * 	attempts to create another.
 *
 * @access public
 * @param mixed $post_id
 * @return void
 */
function wps_check_topics_in_progress( $forum_id ) {

	// Get current user ID

	$user_id = get_current_user_id();

	if ($user_id) {

		// Get all topics that are in progress

		$topics_in_progress = get_posts( array(
			'post_type'      => 'topic',
			'author'		 => $user_id,
			'post_status' 	 => array( 'publish', 'pending', 'open' ),
			'posts_per_page' => -1
		) );

		// If there is a topic in progress then thrown an error

		if (count($topics_in_progress) > 0) {

			bbp_add_error( 'bbp_topic_error', __( '<strong>ERROR</strong>: You can only have one topic in progress at a time.  Please wait until your current topic has been completed before submitting another one.', 'bbpress' ) );

			return;
		}

	}

}