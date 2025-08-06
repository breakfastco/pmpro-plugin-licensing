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
