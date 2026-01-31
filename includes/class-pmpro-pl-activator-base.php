<?php
/**
 * PMPRO Plugin Licensing Activator Base Class
 *
 * Abstract base class for license activators. Contains all common logic
 * for activating, deactivating, and checking license status.
 *
 * @package pmpro-plugin-licensing
 * @since 1.0.78
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PMPRO_PL_Activator_Base' ) ) {
	/**
	 * Abstract base class for license activators.
	 *
	 * @abstract
	 */
	abstract class PMPRO_PL_Activator_Base {
		/**
		 * Activate a license key.
		 *
		 * @param string $license_key The license key to activate.
		 * @return string The status of the license key.
		 */
		public function activate_license( $license_key ) {
			if ( empty( $license_key ) ) {
				return 'invalid';
			}

			$hash      = $this->get_plugin_hash();
			$store_url = $this->get_store_url_constant();
			if ( empty( $hash ) || empty( $store_url ) ) {
				return 'invalid';
			}

			// Hit the store API to try and activate this site.
			$params       = array(
				'file'    => $hash,
				'license' => $license_key,
				'site'    => rawurlencode( site_url() ),
			);
			$updater_class = $this->get_updater_class();
			$url           = call_user_func( array( $updater_class, 'build_store_url' ), $store_url, '/wp-json/pmpro/v1/activate/', $params );
			$response      = wp_remote_post( $url );
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
			if ( empty( $license_key ) ) {
				return;
			}

			$hash      = $this->get_plugin_hash();
			$store_url = $this->get_store_url_constant();
			if ( empty( $hash ) || empty( $store_url ) ) {
				return;
			}

			// Hit the store API to try and deactivate this site.
			$params        = array(
				'file'    => $hash,
				'license' => $license_key,
				'site'    => rawurlencode( site_url() ),
			);
			$updater_class = $this->get_updater_class();
			$url           = call_user_func( array( $updater_class, 'build_store_url' ), $store_url, '/wp-json/pmpro/v1/deactivate/', $params );
			wp_remote_post( $url );
		}

		/**
		 * Gets the status of a license key.
		 *
		 * @param string $license_key The license key to get the status of.
		 * @return string
		 */
		public function get_status( $license_key ) {
			if ( empty( $license_key ) ) {
				return 'invalid';
			}

			$hash      = $this->get_plugin_hash();
			$store_url = $this->get_store_url_constant();
			if ( empty( $hash ) || empty( $store_url ) ) {
				return 'invalid';
			}

			// Hit the store API to get license status.
			$params        = array(
				'file'    => $hash,
				'license' => $license_key,
				'site'    => rawurlencode( site_url() ),
			);
			$updater_class = $this->get_updater_class();
			$url           = call_user_func( array( $updater_class, 'build_store_url' ), $store_url, '/wp-json/pmpro/v1/status/', $params );
			$response      = wp_remote_get( $url );
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ) );
				return $body->status;
			}
			return 'invalid';
		}

		/**
		 * Get the plugin hash constant.
		 *
		 * @abstract
		 * @return string
		 */
		abstract protected function get_plugin_hash();

		/**
		 * Get the store URL constant value.
		 *
		 * @abstract
		 * @return string
		 */
		abstract protected function get_store_url_constant();

		/**
		 * Get the updater class name for building store URLs.
		 *
		 * @abstract
		 * @return string Class name with namespace.
		 */
		abstract protected function get_updater_class();
	}
}
