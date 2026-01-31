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
	 * Class PMPRO_PL_Updater
	 *
	 * @since 0.1.0
	 */
	class PMPRO_PL_Updater extends PMPRO_PL_Updater_Base {
		/**
		 * The single instance of the class.
		 *
		 * @var PMPRO_PL_Updater
		 */
		protected static $instance = null;

		/**
		 * Constructor.
		 */
		private function __construct() {
			// Prevent direct instantiation with private constructor.
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

		/**
		 * Creates a store URL where this plugin finds updates.
		 *
		 * @param  string $path A URL path to append to the store URL.
		 * @param  array  $params Additional parameters to add to the store URL.
		 * @return string
		 */
		public static function get_store_url( $path = '', $params = array() ) {
			return self::build_store_url( PMPRO_PLUGIN_LICENSING_STORE_URL, $path, $params );
		}

		/**
		 * Get the plugin hash constant.
		 *
		 * @return string
		 */
		protected function get_plugin_hash() {
			return defined( 'PMPRO_PLUGIN_LICENSING_HASH' ) ? PMPRO_PLUGIN_LICENSING_HASH : '';
		}

		/**
		 * Get the store URL constant value.
		 *
		 * @return string
		 */
		protected function get_store_url_constant() {
			return defined( 'PMPRO_PLUGIN_LICENSING_STORE_URL' ) ? PMPRO_PLUGIN_LICENSING_STORE_URL : '';
		}

		/**
		 * Get the plugin file path constant.
		 *
		 * @return string
		 */
		protected function get_plugin_file() {
			return defined( 'PMPRO_PLUGIN_LICENSING_PATH' ) ? PMPRO_PLUGIN_LICENSING_PATH : '';
		}

		/**
		 * Get the plugin version constant.
		 *
		 * @return string
		 */
		protected function get_plugin_version() {
			return defined( 'PMPRO_PLUGIN_LICENSING_VERSION' ) ? PMPRO_PLUGIN_LICENSING_VERSION : '';
		}

		/**
		 * Get the plugin text domain.
		 *
		 * @return string
		 */
		protected function get_text_domain() {
			return 'pmpro-plugin-licensing';
		}

		/**
		 * Get the transient key name.
		 *
		 * @return string
		 */
		protected function get_transient_key() {
			return 'pmpro_pl_plugin_licensing_update_response';
		}

		/**
		 * Get the license key.
		 *
		 * @return string
		 */
		protected function get_license_key() {
			if ( function_exists( 'pmpro_plugin_licensing_get_license_key' ) ) {
				return pmpro_plugin_licensing_get_license_key();
			}
			return '';
		}
	}
}
