<?php
/**
 * PMPRO Plugin Licensing Updater Base Class
 *
 * Abstract base class for plugin updaters. Contains all common logic
 * for checking updates and providing plugin information.
 *
 * @package pmpro-plugin-licensing
 * @since 1.0.78
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PMPRO_PL_Updater_Base' ) ) {
	/**
	 * Abstract base class for plugin updaters.
	 *
	 * @abstract
	 */
	abstract class PMPRO_PL_Updater_Base {
		/**
		 * Adds hooks for update checking and plugin info.
		 *
		 * @return void
		 */
		public function add_hooks() {
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
		 * @return object|false
		 */
		public function provide_plugin_info( $api, $action, $args ) {
			// Not even looking for plugin information? Or not given slug?
			if ( 'plugin_information' !== $action
				|| empty( $args->slug )
				|| $this->get_plugin_slug() !== $args->slug ) {
				return $api;
			}

			$update_response = get_transient( $this->get_transient_key() );

			// If no cached response, fetch fresh data.
			if ( empty( $update_response ) ) {
				$update_response = $this->fetch_update_data();
				if ( $update_response ) {
					set_transient( $this->get_transient_key(), $update_response, 24 * HOUR_IN_SECONDS );
				}
			}

			$api                 = new stdClass();
			$api->slug           = $update_response->slug ?? '';
			$api->plugin         = $update_response->plugin ?? '';
			$api->version        = $update_response->new_version ?? '';
			$api->name           = $update_response->name ?? '';
			$api->author         = $update_response->author ?? '';
			$api->author_profile = $update_response->author_profile ?? '';
			$api->tested         = $update_response->tested ?? '';
			$api->last_updated   = $update_response->last_updated ?? ''; // this is passed into strtotime().
			$api->homepage       = $update_response->homepage ?? '';
			$api->sections       = array(
				'description'  => $update_response->sections->description ?? '',
				'installation' => $update_response->sections->installation ?? '',
				'faq'          => $update_response->sections->faq ?? '',
				'changelog'    => $update_response->sections->changelog ?? '',
			);
			if ( ! empty( $update_response->package ) ) {
				$package_url          = $this->resolve_package_url( $update_response->package );
				$api->download_link = $package_url;
				$api->package       = $package_url;
			}
			if ( empty( $api->upgrade_notice ) && empty( $this->get_license_key() ) ) {
				// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
				$api->upgrade_notice = __( 'This plugin requires a valid license key to update.', $this->get_text_domain() );
			}
			return $api;
		}

		/**
		 * Check for plugin updates.
		 *
		 * @param  mixed $transient The update transient.
		 * @return mixed
		 */
		public function check_for_updates( $transient ) {
			if ( ! current_user_can( 'update_plugins' ) ) {
				return $transient;
			}
			// The filter pre_set_site_transient_update_plugins is called twice.
			// Act on the second run after the checked data is set.
			if ( empty( $transient->checked ) ) {
				return $transient;
			}
			$plugin_slug = $this->get_plugin_slug();

			// Connect to the store and get update data.
			$response_data = $this->fetch_update_data();

			// Is a new version available?
			if ( ! empty( $response_data->new_version ) && version_compare( $response_data->new_version, $this->get_plugin_version(), '>' ) ) {
				// Yes.
				$obj = (object) array(
					'icons'       => array(),
					'slug'        => $plugin_slug,
					'plugin'      => $plugin_slug,
					'new_version' => $response_data->new_version,
				);

				if ( ! empty( $response_data->package ) ) {
					$obj->package = $this->resolve_package_url( $response_data->package );
				}

				$transient->response[ $plugin_slug ] = $obj;
			}

			if ( false !== $response_data ) {
				// Save the response somewhere that provide_plugin_info() can find it.
				set_transient( $this->get_transient_key(), $response_data, 24 * HOUR_IN_SECONDS );
			}

			return $transient;
		}

		/**
		 * Fetch update data from the store.
		 *
		 * @return object|false The response data object on success, false on failure.
		 */
		protected function fetch_update_data() {
			$hash = $this->get_plugin_hash();
			if ( empty( $hash ) ) {
				return false;
			}
			// Connect to the store and get update data.
			$params = array(
				'site' => site_url(),
			);
			// Show available updates even if the user has no license key.
			$license_key = $this->get_license_key();
			if ( ! empty( $license_key ) ) {
				$params['license'] = $license_key;
			}
			$store_url = $this->get_store_url_constant();
			$url       = self::build_store_url( $store_url, '/wp-json/pmpro/v1/files/' . $hash, $params );
			$response  = wp_remote_get( $url );

			// Was the request successful?
			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				return json_decode( wp_remote_retrieve_body( $response ) );
			}

			return false;
		}

		/**
		 * Get the plugin slug for this plugin.
		 *
		 * @return string  The plugin slug.
		 */
		protected function get_plugin_slug() {
			$plugin_file = $this->get_plugin_file();
			if ( empty( $plugin_file ) || ! function_exists( 'plugin_basename' ) ) {
				return '';
			}
			return plugin_basename( $plugin_file );
		}

		/**
		 * Creates a store URL with optional path and parameters.
		 *
		 * @param  string $store_url The base store URL.
		 * @param  string $path A URL path to append to the store URL.
		 * @param  array  $params Additional parameters to add to the store URL.
		 * @return string
		 */
		public static function build_store_url( $store_url, $path = '', $params = array() ) {
			if ( empty( $store_url ) ) {
				return '';
			}
			$url = trailingslashit( $store_url ) . ltrim( $path, '/' );
			if ( ! empty( $params ) ) {
				return add_query_arg( $params, $url );
			}
			return $url;
		}

		/**
		 * Ensures the package URL can be fetched by WordPress during updates.
		 *
		 * Stores may return a REST download URL directly. Older stores and
		 * cached update responses may still return membership-account download
		 * links that require a logged-in browser session on the store.
		 *
		 * @param  string $package_url The package URL from the store response.
		 * @return string
		 */
		protected function resolve_package_url( $package_url ) {
			if ( empty( $package_url ) ) {
				return '';
			}

			if ( false !== strpos( $package_url, '/wp-json/pmpro/v1/files/' )
				&& false !== strpos( $package_url, '/download' ) ) {
				return $package_url;
			}

			if ( preg_match( '#/membership-account/download/([a-fA-F0-9]{32})/?$#', $package_url, $matches ) ) {
				$license_key = $this->get_license_key();
				if ( empty( $license_key ) ) {
					return $package_url;
				}

				return self::build_store_url(
					$this->get_store_url_constant(),
					'/wp-json/pmpro/v1/files/' . $matches[1] . '/download',
					array(
						'license' => $license_key,
						'site'    => site_url(),
					)
				);
			}

			return $package_url;
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
		 * Get the plugin file path constant.
		 *
		 * @abstract
		 * @return string
		 */
		abstract protected function get_plugin_file();

		/**
		 * Get the plugin version constant.
		 *
		 * @abstract
		 * @return string
		 */
		abstract protected function get_plugin_version();

		/**
		 * Get the plugin text domain.
		 *
		 * @abstract
		 * @return string
		 */
		abstract protected function get_text_domain();

		/**
		 * Get the transient key name.
		 *
		 * @abstract
		 * @return string
		 */
		abstract protected function get_transient_key();

		/**
		 * Get the license key.
		 *
		 * @abstract
		 * @return string
		 */
		abstract protected function get_license_key();
	}
}
