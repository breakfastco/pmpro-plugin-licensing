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

require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-readme-parser.php';

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

/**
 * Determines whether a customer license key is valid for the file she is trying
 * to download.
 *
 * @param  array $args Array with keys 'license_key', 'user_id', or 'attachment_id'. License key and attachment ID are required.
 * @return bool
 */
function pmpro_pl_customer_license_is_valid( $args ) {
	$license_key   = $args['license_key'] ?? '';
	$user_id       = $args['user_id'] ?? 0;
	$attachment_id = $args['attachment_id'] ?? 0;

	if ( empty( $license_key ) && empty( $user_id ) ) {
		// Not enough info.
		return false;
	}

	if ( empty( $attachment_id ) ) {
		return false;
	}

	global $wpdb;
	$license_row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = 'pmpro_plugin_licensing_license_key' AND meta_value = %s LIMIT 1",
			$license_key
		)
	);

	if ( empty( $license_row ) ) {
		return false;
	}

	// Get active memberships for this user.
	if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
		return false;
	}

	$levels = pmpro_getMembershipLevelsForUser( $license_row->user_id );
	if ( empty( $levels ) ) {
		return false;
	}

	// Examine each of this user's memberships.
	foreach ( $levels as $level ) {
		// Is this membership expired?
		if ( null !== $level->enddate && (int) $level->enddate < time() ) {
			// Yes.
			continue;
		}

		// Does this level contain the attachment?
		$level_meta_files = get_pmpro_membership_level_meta( $level->id, 'plugin_licensing_files', true );
		if ( empty( $level_meta_files ) ) {
			continue;
		}
		if ( in_array( $attachment_id, $level_meta_files, true ) ) {
			// Yes.
			return true;
		}
	}

	// This user has memberships, but none of them have this file attached.
	return false;
}

/**
 * Returns the saved license key for this plugin.
 *
 * @return string
 */
function pmpro_plugin_licensing_get_license_key() {
	/**
	 * Your customers' license keys are stored in user meta. The key stored in
	 * this option is issued by breakfastco.xyz when you buy this plugin.
	 */
	return get_option( pmpro_plugin_licensing_get_license_key_option_name() );
}

/**
 * Returns the name of the option that stores a license key for this plugin
 * provided by breakfastco.xyz.
 *
 * @return string
 */
function pmpro_plugin_licensing_get_license_key_option_name() {
	/**
	 * This plugin stores its license key from breakfastco.xyz
	 * in an option so admins can buy their own products from
	 * their own stores using the license key stored in
	 * user meta.
	 */
	return 'pmpro_plugin_licensing_license_key';
}
