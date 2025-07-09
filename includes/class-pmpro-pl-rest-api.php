<?php
/**
 * PMPRO Plugin Licensing REST API
 *
 * @package pmpro-plugin-licensing
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PMPRO_PL_REST_API' ) ) {
	/**
	 * Class PMPRO_PL_REST_API
	 *
	 * @since 0.1.0
	 */
	class PMPRO_PL_REST_API {

		public function __construct() {
			// Add hooks.
			$this->add_hooks();
		}

		public function add_hooks() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
			add_action( 'init', array( $this, 'register_meta' ) );
		}

		public function register_meta() {
			register_meta(
				'post',
				'pmpro_pl_hash',
				array(
					'object_subtype' => 'attachment',
					'type'           => 'string',
					'single'         => true,
					'show_in_rest'   => true,
				)
			);
		}

		/**
		 * Handles REST requests for files at /wp-json/pmpro/v1/files.
		 *
		 * @param  mixed $request
		 * @return void
		 */
		public function file_handler( $request ) {
			$params = $request->get_params();

			// Only paying customers with license keys get updates.
			if ( empty( $params['license'] ) || empty( $params['file'] ) ) {
				// Return the same 404 as an invalid route.
				return new WP_Error(
					'rest_no_route',
					__( 'No route was found matching the URL and request method.' ),
					array( 'status' => 404 )
				);
			}

			// Get the latest version info from attachment meta.
			$hash          = $params['file'];
			$attachment_id = PMPRO_PL_Download_Links::find_attachment_id( $hash );
			if ( 0 === $attachment_id ) {
				// Sorry, invalid file.
				return new WP_Error(
					'rest_forbidden',
					__( 'Request failed.' ),
					array( 'status' => 401 )
				);
			}

			// Is the site requesting this file already among the active sites?
			$user_id = pmpro_pl_get_user_id_from_license_key( $params['license'] );

			// Is the license valid?
			$license_check_args = array(
				'license_key'   => $params['license'],
				'attachment_id' => $attachment_id,
				'user_id'       => $user_id,
			);
			if ( ! pmpro_pl_customer_license_is_valid( $license_check_args ) ) {
				// Sorry, invalid license.
				return new WP_Error(
					'rest_forbidden',
					__( 'Request failed.' ),
					array( 'status' => 401 )
				);
			}

			if ( empty( $user_id ) ) {
				// Sorry, license cannot be found associated with any user.
				return new WP_Error(
					'rest_forbidden',
					__( 'Request failed.' ),
					array( 'status' => 401 )
				);
			}

			// Is the site requesting this file already among the active sites?
			// Get the referring URL from the request.
			$referrer            = $request->get_header( 'referer' );
			$site_already_active = false;
			$active_sites        = get_user_meta( $user_id, 'pmpro_plugin_licensing_active_sites', true );
			if ( empty( $active_sites ) ) {
				// No.
				$site_already_active = false;
			}

			if ( is_array( $active_sites[ $hash ] ) && in_array( $referrer, $active_sites[ $hash ], true ) ) {
				// Yes, this site is already active.
				$site_already_active = true;
			} else {
				// No. Add it.
				// TODO does this membership limit the active sites?
				if ( ! is_array( $active_sites[ $hash ] ) ) {
					$active_sites[ $hash ] = array();
				}
				$active_sites[ $hash ] = $referrer;
				update_user_meta( $user_id, 'pmpro_plugin_licensing_active_sites', $active_sites );
			}

			$slug   = get_post_meta( $attachment_id, '_pmpro_pl_slug', true );
			$result = array(
				'icons'       => array(
					'default' => plugins_url( 'assets/icon.png', PMPRO_PLUGIN_LICENSING_PATH ),
				),
				'slug'        => $slug, // This slug is in the View details link.
				'plugin'      => $slug,
				'new_version' => get_post_meta( $attachment_id, '_pmpro_pl_version', true ),
				'package'     => PMPRO_PL_Download_Links::create_download_link( $hash ),
			);

			wp_send_json_success( $result );
		}

		public function register_routes() {
			$rest_namespace = 'pmpro/v1';
			/**
			 * Get user access for a specific post.
			 * Example: https://example.com/wp-json/pmpro/v1/files
			 */
			register_rest_route(
				$rest_namespace,
				'/files',
				array(
					array(
						'methods'  => WP_REST_Server::READABLE,
						'callback' => array( $this, 'file_handler' ),
					),
				)
			);
		}
	}
	new PMPRO_PL_REST_API();
}
