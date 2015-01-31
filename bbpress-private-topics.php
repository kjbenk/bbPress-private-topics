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

/**
 * Global Definitions
 */

// Single Task Groups ID

if (!defined('SINGLE_TASK_GROUP_ID'))
    define('SINGLE_TASK_GROUP_ID', 4);

// Single Task Forum ID

if (!defined('SINGLE_TASK_FORUM_ID'))
    define('SINGLE_TASK_FORUM_ID', 19);

// Gold Membership Product ID

if (!defined('GOLD_MEMBERSHIP_PRODUCT_ID'))
    define('GOLD_MEMBERSHIP_PRODUCT_ID', 8);

// Silver Membership Product ID

if (!defined('SILVER_MEMBERSHIP_PRODUCT_ID'))
    define('SILVER_MEMBERSHIP_PRODUCT_ID', 9);

add_action('pre_get_posts', 'wps_private_topics');

/**
 * Only display topics to admins and the author of that topic
 *
 * @access public
 * @param mixed $query
 * @return void
 */
function wps_private_topics( $query ) {

	// If user is admin then just return null because they can see all topics and fourms

	if (current_user_can('manage_options')) {
		return;
	}

	// Check if user has a single task membership

	global $wpdb;

	$groups = $wpdb->get_results(
		$wpdb->prepare('SELECT * FROM `' . $wpdb->prefix . 'groups_user_group` WHERE `user_id` = %d',
			get_current_user_id()
		)
	);

	$single_task = false;

	foreach ($groups as $group) {
		if ($group->group_id == SINGLE_TASK_GROUP_ID) {
			$single_task = true;
		}
	}

	// Check if displaying Single Task form

	if ($single_task) {

		// Check if we are trying to retireve topics and that user is not an admin

		if (isset($query->query['post_type']) && $query->query['post_type'] == 'topic') {

			// Add parameter for dislaying posts only created by the current user

			$query->set( 'author__in', array(get_current_user_id()));
			return;
		}
	}

	else {

		// Check if we are trying to retireve topics and that user is not an admin

		if (isset($query->query['post_type']) && ($query->query['post_type'] == 'topic' || $query->query['post_type'] == 'forum')) {

			// Add parameter for dislaying posts only created by the current user

			$query->set( 'author__in', array(get_current_user_id()));
			return;
		}
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

	if ( !current_user_can('manage_options') && $user_id && $forum_id != SINGLE_TASK_FORUM_ID) {

		// Get all topics that are in progress

		$topics_in_progress = get_posts( array(
			'post_type'      => 'topic',
			'post_parent'	 => $forum_id,
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

add_action( 'bbp_new_topic_pre_extras', 'wps_check_single_topic_count' );

/**
 * Return an Error message if user tries to create more topics then
 *  the number of single topics they have purchased
 *
 * @access public
 * @param mixed $post_id
 * @return void
 */
function wps_check_single_topic_count( $forum_id ) {

	// Get current user ID

	$user_id = get_current_user_id();

	if ( $user_id && $forum_id == SINGLE_TASK_FORUM_ID ) {

		// Get all topics that are in progress

		$topics = get_posts( array(
			'post_type'      	=> 'topic',
			'post_parent'	 	=> $forum_id,
			'author'		 	=> $user_id,
			'posts_per_page' 	=> -1
		) );

		$orders = get_posts( array(
			'post_type'			=> 'shop_order',
			'post_status'		=> 'wc-completed',
			'author'		 	=> $user_id,
			'posts_per_page' 	=> -1
		) );

		global $wpdb;

		$order_quantity = 0;

		foreach ($orders as $order) {

			$item = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `' . $wpdb->prefix . 'woocommerce_order_items` WHERE `order_id` = %d AND `order_item_type` = %s',
					$order->ID,
					'line_item'
				)
			);

			$quantity = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta` WHERE `order_item_id` = %d AND `meta_key` = %s',
					$item[0]->order_item_id,
					'_qty'
				)
			);

			$order_quantity += $quantity[0]->meta_value;

		}

		// If there is a topic in progress then thrown an error

		if (count($topics) >= $order_quantity) {

			bbp_add_error( 'bbp_topic_error', __( '<strong>ERROR</strong>: You must purchase another single task before you can create another support ticket.  If you have already purchased another tasks your order might still be processing, so please bear with us.', 'bbpress' ) );

			return;
		}

	}

}

add_action( 'woocommerce_add_order_item_meta', 'wps_add_forum', 10, 3);

/**
 * Add a new forum when user completes an order
 *
 * @access public
 * @param mixed $order_id
 * @return void
 */
function wps_add_forum( $item_id, $values, $cart_item_key ) {

	// Get the domain name form the post meta data

	foreach ($values['addons'] as $addons) {

		$doamin_name = $addons['value'];

	}

	$topic_data = array(
		'post_title'    => $doamin_name,
	);

	// Gold Membership

	if ($values['data']->post->ID == GOLD_MEMBERSHIP_PRODUCT_ID) {

		// Create a new topic from the domain name

		$topic_id = bbp_insert_forum( $topic_data );

		//update_post_meta($topic_id, 'groups-groups_read_post', 'gold membership');

	}

	// Silver Membership

	else if ($values['data']->post->ID == SILVER_MEMBERSHIP_PRODUCT_ID) {

		// Create a new topic from the domain name

		$topic_id = bbp_insert_forum( $topic_data );

		//update_post_meta($topic_id, 'groups-groups_read_post', 'silver membership');

	}

}

add_filter( 'woocommerce_new_order_data', 'wps_change_post_author_for_order');

/**
 * Change the post author to the current user when an order is created
 *
 * @access public
 * @param mixed $order_data
 * @return void
 */
function wps_change_post_author_for_order( $order_data ) {

	$order_data['post_author'] = get_current_user_id();

	return $order_data;

}