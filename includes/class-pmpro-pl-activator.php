<?php
/**
 * PMPRO Plugin Licensing Activator
 *
 * @package pmpro-plugin-licensing
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PMPRO_PL_Activator' ) ) {
	/**
	 * Class PMPRO_PL_Activator
	 */
	class PMPRO_PL_Activator extends PMPRO_PL_Activator_Base {
		/**
		 * The single instance of the class.
		 *
		 * @var PMPRO_PL_Activator
		 */
		protected static $instance = null;

		/**
		 * Constructor.
		 */
		private function __construct() {
			// Prevent direct instantiation with private constructor.
		}

		/**
		 * Get the singleton instance.
		 *
		 * @return PMPRO_PL_Activator
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new PMPRO_PL_Activator();
			}
			return self::$instance;
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
		 * Get the updater class name for building store URLs.
		 *
		 * @return string Class name with namespace.
		 */
		protected function get_updater_class() {
			return 'PMPRO_PL_Updater';
		}
	}
}