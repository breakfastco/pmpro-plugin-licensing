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
	class PMPRO_PL_Activator {
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
		 * Activate a license key.
		 *
		 * @param string $license_key The license key to activate.
		 * @return string The status of the license key.
		 */
		public function activate_license( $license_key ) {
			if ( empty( $license_key )
				|| ! defined( 'PMPRO_PLUGIN_LICENSING_HASH' )
				|| ! defined( 'PMPRO_PLUGIN_LICENSING_STORE_URL' ) ) {
				return 'invalid';
			}

			// Hit the store API to try and activate this site.
			$params   = array(
				'file'    => PMPRO_PLUGIN_LICENSING_HASH,
				'license' => $license_key,
				'site'    => rawurlencode( site_url() ),
			);
			$response = wp_remote_post( PMPRO_PL_Updater::get_store_url( '/wp-json/pmpro/v1/activate/', $params ) );
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ) );
				return $body->status;
			}
			return 'inactive';
		}

		/**
		 * Deactivate a license key.
		 *
		 * @param string $license_key The license key to deactivate.
		 * @return void
		 */
		public function deactivate_license( $license_key ) {
			if ( empty( $license_key )
				|| ! defined( 'PMPRO_PLUGIN_LICENSING_HASH' )
				|| ! defined( 'PMPRO_PLUGIN_LICENSING_STORE_URL' ) ) {
				return;
			}

			// Hit the store API to try and deactivate this site.
			$params = array(
				'file'    => PMPRO_PLUGIN_LICENSING_HASH,
				'license' => $license_key,
				'site'    => rawurlencode( site_url() ),
			);
			wp_remote_post( PMPRO_PL_Updater::get_store_url( '/wp-json/pmpro/v1/deactivate/', $params ) );
		}

		/**
		 * Gets the status of a license key.
		 *
		 * @param string $license_key The license key to get the status of.
		 * @return string
		 */
		public function get_status( $license_key ) {
			if ( empty( $license_key )
				|| ! defined( 'PMPRO_PLUGIN_LICENSING_HASH' )
				|| ! defined( 'PMPRO_PLUGIN_LICENSING_STORE_URL' ) ) {
				return 'invalid';
			}

			// Hit the store API to get license status.
			$params   = array(
				'file'    => PMPRO_PLUGIN_LICENSING_HASH,
				'license' => $license_key,
				'site'    => rawurlencode( site_url() ),
			);
			$response = wp_remote_get( PMPRO_PL_Updater::get_store_url( '/wp-json/pmpro/v1/status/', $params ) );
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ) );
				return $body->status;
			}
			return 'invalid';
		}
	}
}