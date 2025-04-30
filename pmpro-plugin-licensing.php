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
	define( 'PMPRO_PLUGIN_LICENSING_VERISON', '0.1.0' );
}
if ( ! defined( 'PMPRO_PLUGIN_LICENSING_PATH' ) ) {
	define( 'PMPRO_PLUGIN_LICENSING_PATH', __FILE__ );
}

// Load our updater so users see pending updates for this plugin.
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-updater.php';
$updater = PMPRO_PL_Updater::get_instance();
$updater->set_plugin_slug( 'pmpro-plugin-licensing/pmpro-plugin-licensing.php' );
$updater->set_plugin_version( PMPRO_PLUGIN_LICENSING_VERISON );
$updater->set_store_url( 'https://breakfastco.xyz' );
$updater->add_hooks();

// Manage this plugin's settings and provide UI controls.
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-settings.php';

// Load the membership features, generate and store license keys.
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-membership-features.php';

// Load the REST API endpoints to which sold plugins will connect from customer sites.
require_once dirname( PMPRO_PLUGIN_LICENSING_PATH ) . '/includes/class-pmpro-pl-rest-api.php';

function pmpro_plugin_licensing_license_is_valid( $license_key ) {
	// TODO custom database query? Looking at user for the key, and then subscriptions to membership levels.
	return true;
}

function pmpro_plugin_licensing_get_license_key() {
	return get_option( pmpro_plugin_licensing_get_license_key_option_name() );
}

function pmpro_plugin_licensing_get_license_key_option_name() {
	/**
	 * For plugin_slug pmpro-plugin-licensing/pmpro-plugin-licensing.php,
	 * we want
	 * pmpro_plugin_licensing__{$slug} but without the file extension or slash.
	 * so
	 * pmpro_plugin_licensing__pmpro-plugin-licensing_pmpro-plugin-licensing
	 */
	return 'pmpro_plugin_licensing__pmpro-plugin-licensing_pmpro-plugin-licensing';
}
