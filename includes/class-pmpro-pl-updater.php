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
	class PMPRO_PL_Updater {
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
		 * Adds hooks.
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
		 * @return array
		 */
		public function provide_plugin_info( $api, $action, $args ) {
			// Not even looking for plugin information? Or not given slug?
			if ( 'plugin_information' !== $action
				|| empty( $args->slug )
				|| $this->get_plugin_slug() !== $args->slug ) {
				return $api;
			}

			$update_response = get_transient( $this->transient_key() );

			// If no cached response, fetch fresh data.
			if ( empty( $update_response ) ) {
				$update_response = $this->fetch_update_data();
				if ( $update_response ) {
					set_transient( $this->transient_key(), $update_response, 24 * HOUR_IN_SECONDS );
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
				$api->download_link = $update_response->package;
				$api->package       = $update_response->package;
			}
			if ( empty( $api->upgrade_notice ) && empty( $this->license_key ) ) {
				$api->upgrade_notice = __( 'This plugin requires a valid license key to update.', 'pmpro-plugin-licensing' );
			}
			return $api;
		}

		// 	$license_key = pmpro_plugin_licensing_get_license_key();
		// 	if ( ! empty( $license_key ) && ! empty( $api->download_link ) ) {
		// 		$api->download_link = add_query_arg( 'key', $license_key, $api->download_link );
		// 	}
		// 	if ( ! empty( $license_key ) && ! empty( $api->package ) ) {
		// 		$api->package = add_query_arg( 'key', $license_key, $api->package );
		// 	}
		// 	if ( empty( $api->upgrade_notice ) && ! pmpro_plugin_licensing_license_is_valid( $license_key ) ) {
		// 		$api->upgrade_notice = __( 'Important: This plugin requires a valid license key to update.', 'pmpro-plugin-licensing' );
		// 	}

		// 	return $api;
		// }

		/**
		 * Check for updates to this plugin. This phones home to Breakfast to
		 * find new versions of Paid Memberships Pro - Plugin Licensing.
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
			if ( ! empty( $response_data->new_version ) && version_compare( $response_data->new_version, PMPRO_PLUGIN_LICENSING_VERSION, '>' ) ) {
				// Yes.
				$obj = (object) array(
					'icons'       => array(),
					'slug'        => $plugin_slug,
					'plugin'      => $plugin_slug,
					'new_version' => $response_data->new_version,
				);

				if ( ! empty( $response_data->package ) ) {
					$obj->package = $response_data->package;
				}

				$transient->response[ $plugin_slug ] = $obj;
			}

			if ( false !== $response_data ) {
				// Save the response somewhere that provide_plugin_info() can find it.
				set_transient( $this->transient_key(), $response_data, 24 * HOUR_IN_SECONDS );
			}

			return $transient;
		}

		/**
		 * Fetch update data from the store.
		 *
		 * @return object|false The response data object on success, false on failure.
		 */
		protected function fetch_update_data() {
			if ( ! defined( 'PMPRO_PLUGIN_LICENSING_HASH' ) ) {
				return false;
			}
			// Connect to the store and get update data.
			$params = array(
				'site' => site_url(),
			);
			// Show available updates even if the user has no license key.
			$license_key = pmpro_plugin_licensing_get_license_key();
			if ( ! empty( $license_key ) ) {
				$params['license'] = $license_key;
			}
			$url      = self::get_store_url( '/wp-json/pmpro/v1/files/' . PMPRO_PLUGIN_LICENSING_HASH, $params );
			$response = wp_remote_get( $url );

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
			if ( ! defined( 'PMPRO_PLUGIN_LICENSING_PATH' )
				|| ! function_exists( 'plugin_basename' ) ) {
				return '';
			}
			return plugin_basename( PMPRO_PLUGIN_LICENSING_PATH );
		}

		/**
		 * Creates a store URL where this plugin finds updates.
		 *
		 * @param  string $path A URL path to append to the store URL.
		 * @param  array  $params Additional parameters to add to the store URL.
		 * @return string
		 */
		public static function get_store_url( $path = '', $params = array() ) {
			if ( ! defined( 'PMPRO_PLUGIN_LICENSING_STORE_URL' ) ) {
				return '';
			}
			$url = trailingslashit( PMPRO_PLUGIN_LICENSING_STORE_URL ) . ltrim( $path, '/' );
			if ( ! empty( $params ) ) {
				return add_query_arg( $params, $url );
			}
			return $url;
		}

		/**
		 * Returns the name of the transient used to store the update response.
		 *
		 * @return string
		 */
		protected function transient_key() {
			return 'pmpro_pl_plugin_licensing_update_response';
		}
	}
}
