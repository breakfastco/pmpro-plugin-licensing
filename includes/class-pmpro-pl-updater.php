<?php
/**
 * PMPRO Plugin Licensing Updater
 *
 * @package pmpro-plugin-licensing
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PMPRO_PL_Updater' ) ) {
	/**
	 * Class PMPRO_Plugin_Licensing_Updater
	 *
	 * @since 0.1.0
	 */
	class PMPRO_PL_Updater {
		/**
		 * The single instance of the class.
		 *
		 * @var PMPRO_PL_Updater
		 */
		protected static $instance = null;

		/**
		 * The plugin slug.
		 *
		 * @var string
		 */
		protected $plugin_slug = '';

		/**
		 * The plugin version.
		 *
		 * @var string
		 */
		protected $plugin_version = '';

		/**
		 * The store URL.
		 *
		 * @var string
		 */
		protected $store_url = '';

		public function add_hooks() {
			// Only do this once.
			if ( has_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) ) ) {
				return;
			}

			// Notify users when an update is available.
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );

			// Make the View version x.x.x details link work.
			add_filter( 'plugins_api', array( $this, 'provide_plugin_info' ), 10, 3 );
		}

		/**
		 * Provide plugin info that will not be found in the wordpress.org API.
		 * This makes the View version x.x.x details link work.
		 *
		 * @param  bool   $api Always false.
		 * @param  string $action The type of information being requested from the Plugin Installation API.
		 * @param  array  $args Plugin API arguments.
		 * @return array
		 */
		public function provide_plugin_info( $api, $action, $args ) {
			// Not even looking for plugin information? Or not given slug?
			if ( 'plugin_information' !== $action
				|| empty( $args->slug )
				|| $this->get_plugin_slug() !== $args->slug ) {
				return $api;
			}

			$api                           = new stdClass();
			$api->name                     = __( 'Paid Memberships Pro - Plugin Licensing', 'pmpro-plugin-licensing' );
			$api->slug                     = $this->get_plugin_slug();
			$api->plugin                   = $this->get_plugin_slug();
			$api->version                  = ''; // This is the version available, not the current version.
			$api->author                   = __( 'Breakfast', 'pmpro-plugin-licensing' );
			$api->author_profile           = 'https://breakfastco.xyz';
			$api->tested                   = ''; // This is the version available, not the current version.
			$api->last_updated             = 'Recently';
			$api->homepage                 = 'https://breakfastco.xyz';
			$api->download_link            = 'https://breakfastco.xyz/downloads/downloads/downloads/pmpro-plugin-licensing.zip';
			$api->package                  = 'https://breakfastco.xyz/downloads/downloads/downloads/pmpro-plugin-licensing.zip';
			$api->sections['description']  = 'Description';
			$api->sections['installation'] = 'Installation';
			$api->sections['faq']          = 'FAQ';
			$api->sections['changelog']    = 'Changelog';
			// $api->requires              = '';

			$license_key = pmpro_plugin_licensing_get_license_key();
			if ( ! empty( $license_key ) && ! empty( $api->download_link ) ) {
				$api->download_link = add_query_arg( 'key', $license_key, $api->download_link );
			}
			if ( ! empty( $license_key ) && ! empty( $api->package ) ) {
				$api->package = add_query_arg( 'key', $license_key, $api->package );
			}
			if ( empty( $api->upgrade_notice ) && ! pmpro_plugin_licensing_license_is_valid( $license_key ) ) {
				$api->upgrade_notice = __( 'Important: This plugin requires a valid license key to update.', 'pmpro-plugin-licensing' );
			}

			return $api;
		}

		public function check_for_updates( $transient ) {
			// The filter pre_set_site_transient_update_plugins is called twice.
			// Act on the second run after the checked data is set.
			if ( empty( $transient->checked ) ) {
				return $transient;
			}

			// Create option name from plugin slug.
			$license_key = pmpro_plugin_licensing_get_license_key();
			if ( ! $license_key ) {
				return $transient;
			}

			// Before the REST API endpoint exists, pretend there is always an update.
			$plugin_slug                         = $this->get_plugin_slug();
			$transient->response[ $plugin_slug ] = (object) array(
				'icons'       => array(
					'default' => plugins_url( 'assets/icon.png', PMPRO_PLUGIN_LICENSING_PATH ),
				),
				'slug'        => $plugin_slug, // This slug is in the View details link.
				'plugin'      => $plugin_slug,
				'new_version' => '1.2.3',
				'package'     => 'https://breakfastco.xyz/downloads/downloads/downloads/pmpro-plugin-licensing.zip',
			);

			// // Check for updates from the store.
			// $response = wp_remote_post( $this->store_url . '/wp-json/pmpro-license/v1/update', array(
			// 	'body' => array(
			// 		'license_key' => get_option( 'your_plugin_license_key' ),
			// 		'plugin_slug' => $this->plugin_slug,
			// 	),
			// ) );

			// if ( is_wp_error( $response ) ) {
			// 	return $transient;
			// }

			// $data = json_decode( wp_remote_retrieve_body( $response ) );

			// if ( ! empty( $data->version ) && version_compare( $data->version, $this->plugin_version, '>' ) ) {
			// 	$transient->response[ $this->plugin_slug ] = (object) array(
			// 		'slug'        => $data->slug,
			// 		'plugin'      => $this->plugin_slug,
			// 		'new_version' => $data->version,
			// 		'package'     => $data->download_url,
			// 	);
			// }

			return $transient;
		}

		/**
		 * Gets an instance of this class.
		 *
		 * @return PMPRO_PL_Updater
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new PMPRO_PL_Updater();
			}
			return self::$instance;
		}

		public function get_plugin_slug() {
			return $this->plugin_slug;
		}

		public function set_store_url( $store_url ) {
			$this->store_url = $store_url;
		}

		public function set_plugin_slug( $plugin_slug ) {
			$this->plugin_slug = $plugin_slug;
		}

		public function set_plugin_version( $plugin_version ) {
			$this->plugin_version = $plugin_version;
		}
	}
}
