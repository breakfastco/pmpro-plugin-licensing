<?php
/**
 * Plugin Name: Paid Memberships Pro - Plugin Licensing
 * Description: Attaches file downloads to Membership levels. Generates license keys for each order. Delivers updates.
 * Version: 0.1.0
 * Author: Breakfast
 * Author URI: https://breakfastco.xyz
 * GitHub Plugin URI: https://github.com/breakfastco/pmpro-plugin-licensing
 * Primary Branch: main
 * License: GPLv2
 * Text Domain: pmpro-plugin-licensing
 *
 * @author Corey Salzano <csalzano@duck.com>
 * @package pmpro-plugin-licensing
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'PMPRO_PLUGIN_LICENSING_VERSION' ) ) {
	define( 'PMPRO_PLUGIN_LICENSING_VERSION', '0.1.0' );
}
if ( ! defined( 'PMPRO_PLUGIN_LICENSING_PATH' ) ) {
	define( 'PMPRO_PLUGIN_LICENSING_PATH', __FILE__ );
}

/**
 * Load dependencies. A library to parse plugin readme.txt files into HTML.
 * michaelf/php-markdown 1.6.0 as specified at plugin-directory/libs.
 *
 * @link https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/libs/michelf-php-markdown-1.6.0
 */
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/vendor-prefixed/autoload.php';

// Load our updater so users see pending updates for this plugin.
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-updater.php';
$updater = PMPRO_PL_Updater::get_instance();
$updater->set_plugin_hash( '9c43f2e5857b1032374052dbfb8357b6' ); // Send this to the store instead of plugin_basename().
$updater->set_store_url( 'https://breakfastco.test' ); // TODO change this back to the live store.
$updater->add_hooks();

// Manage this plugin's settings and provide UI controls.
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-settings.php';

// Load the membership features, generate and store license keys.
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-membership-features.php';

// Load the REST API endpoints to which sold plugins will connect from customer sites.
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-rest-api.php';

// Create download links and deliver file downloads to customers.
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-download-links.php';

/**
 * This is plugin-directory/readme/class-parser.php.
 *
 * @link https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/readme/class-parser.php
 */
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-readme-parser.php';

require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-api.php';

// Classes used on .org to parse plugin readme.txt files into HTML.
/**
 * This is plugin-directory/class-markdown.php.
 *
 * @link https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/class-markdown.php
 */
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-markdown.php';

// Load the shortcode to display a list of sites a user has activated a plugin on.
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/shortcodes/pmpro_pl_sites.php';

// Add AJAX handlers for site management.
add_action( 'wp_ajax_pmpro_pl_add_site', 'pmpro_pl_ajax_add_site' );
add_action( 'wp_ajax_pmpro_pl_remove_site', 'pmpro_pl_ajax_remove_site' );

// Flush rewrite rules when the plugin is activated.
register_activation_hook( PMPRO_PLUGIN_LICENSING_PATH, 'pmpro_pl_flush_rewrite' );
/**
 * Flushes rewrite rules.
 *
 * @param  boolean $network_wide True if this plugin is being Network Activated or Network Deactivated by the multisite admin.
 * @return void
 */
function pmpro_pl_flush_rewrite( $network_wide ) {
	if ( ! is_multisite() || ! $network_wide ) {
		flush_rewrite_rules();
		return;
	}

	$sites = get_sites(
		array(
			'network' => 1,
			'limit'   => apply_filters( 'pmpro_pl_query_limit', 1000, __METHOD__ ),
		)
	);
	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );
		global $wp_rewrite;
		$wp_rewrite->init(); // important...
		$wp_rewrite->flush_rules();
		restore_current_blog();
	}
}

/**
 * AJAX handler for adding a site to a user's license.
 */
function pmpro_pl_ajax_add_site() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pmpro_pl_sites_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'pmpro-plugin-licensing' ) ) );
	}

	// Get and sanitize parameters.
	$hash = isset( $_POST['hash'] ) ? sanitize_text_field( wp_unslash( $_POST['hash'] ) ) : '';
	$site_url = isset( $_POST['site_url'] ) ? esc_url_raw( wp_unslash( $_POST['site_url'] ) ) : '';
	$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

	// Verify the current user can modify this user's data.
	if ( get_current_user_id() !== $user_id ) {
		wp_send_json_error( array( 'message' => __( 'You can only modify your own sites.', 'pmpro-plugin-licensing' ) ) );
	}

	// Validate URL.
	if ( empty( $site_url ) ) {
		wp_send_json_error( array( 'message' => __( 'Please provide a valid site URL.', 'pmpro-plugin-licensing' ) ) );
	}

	// Get the activation limit for this hash.
	$attachment_id = PMPRO_PL_Download_Links::find_attachment_id( $hash );
	if ( ! $attachment_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid plugin hash.', 'pmpro-plugin-licensing' ) ) );
	}

	$activation_limit = (int) get_post_meta( $attachment_id, '_pmpro_pl_activation_limit', true );

	// Get current active sites.
	$active_sites = get_user_meta( $user_id, 'pmpro_plugin_licensing_active_sites', true );
	if ( ! is_array( $active_sites ) ) {
		$active_sites = array();
	}

	if ( ! isset( $active_sites[ $hash ] ) ) {
		$active_sites[ $hash ] = array();
	}

	// Check if site is already active.
	if ( in_array( $site_url, $active_sites[ $hash ], true ) ) {
		wp_send_json_error( array( 'message' => __( 'This site is already active for this plugin.', 'pmpro-plugin-licensing' ) ) );
	}

	// Check if user has reached the limit.
	if ( $activation_limit > 0 && count( $active_sites[ $hash ] ) >= $activation_limit ) {
		wp_send_json_error( array( 'message' => __( 'You have reached the maximum number of active sites for this plugin.', 'pmpro-plugin-licensing' ) ) );
	}

	// Add the site.
	$active_sites[ $hash ][] = $site_url;
	update_user_meta( $user_id, 'pmpro_plugin_licensing_active_sites', $active_sites );

	wp_send_json_success();
}

/**
 * AJAX handler for removing a site from a user's license.
 */
function pmpro_pl_ajax_remove_site() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pmpro_pl_sites_nonce' ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'pmpro-plugin-licensing' ) ) );
	}

	// Get and sanitize parameters.
	$hash = isset( $_POST['hash'] ) ? sanitize_text_field( wp_unslash( $_POST['hash'] ) ) : '';
	$site_url = isset( $_POST['site_url'] ) ? esc_url_raw( wp_unslash( $_POST['site_url'] ) ) : '';
	$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

	// Verify the current user can modify this user's data.
	if ( get_current_user_id() !== $user_id ) {
		wp_send_json_error( array( 'message' => __( 'You can only modify your own sites.', 'pmpro-plugin-licensing' ) ) );
	}

	// Get current active sites.
	$active_sites = get_user_meta( $user_id, 'pmpro_plugin_licensing_active_sites', true );
	if ( ! is_array( $active_sites ) ) {
		wp_send_json_error( array( 'message' => __( 'No active sites found.', 'pmpro-plugin-licensing' ) ) );
	}

	if ( ! isset( $active_sites[ $hash ] ) ) {
		wp_send_json_error( array( 'message' => __( 'No active sites found for this plugin.', 'pmpro-plugin-licensing' ) ) );
	}

	// Remove the site.
	$key = array_search( $site_url, $active_sites[ $hash ], true );
	if ( false === $key ) {
		wp_send_json_error( array( 'message' => __( 'Site not found in active sites list.', 'pmpro-plugin-licensing' ) ) );
	}

	unset( $active_sites[ $hash ][ $key ] );
	$active_sites[ $hash ] = array_values( $active_sites[ $hash ] ); // Re-index array.

	update_user_meta( $user_id, 'pmpro_plugin_licensing_active_sites', $active_sites );

	wp_send_json_success();
}

// Delete an option during deactivation.
register_deactivation_hook( PMPRO_PLUGIN_LICENSING_PATH, 'pmpro_pl_delete_rewrite_rules_option' );
/**
 * Deletes the rewrite_rules option so the rewrite rules are generated
 * on the next page load without ours. Called during deactivation.
 *
 * @see https://wordpress.stackexchange.com/a/44337/13090
 *
 * @param  bool $network_wide True if this plugin is being Network Activated or Network Deactivated by the multisite admin.
 * @return void
 */
function pmpro_pl_delete_rewrite_rules_option( $network_wide ) {
	if ( ! is_multisite() || ! $network_wide ) {
		delete_option( 'rewrite_rules' );
		return;
	}

	$sites = get_sites(
		array(
			'network' => 1,
			'limit'   => apply_filters( 'pmpro_pl_query_limit', 1000, __METHOD__ ),
		)
	);
	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );
		delete_option( 'rewrite_rules' );
		restore_current_blog();
	}
}
