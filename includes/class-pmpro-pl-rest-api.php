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
		}

		public function file_handler( $request ) {
			$params = $request->get_params();

			// license_key
			// hash?

			if ( empty( $params['file'] ) ) {
				// Return the same 404 as an invalid route.
				return new WP_Error(
					'rest_no_route',
					__( 'No route was found matching the URL and request method.' ),
					array( 'status' => 404 )
				);
			}

			// Get the latest version info from attachment meta.
			// $file is a hash we can find in attachment meta.
			$attachment_id = PMPRO_PL_Download_Links::find_attachment_id( $hash );
			$slug          = get_post_meta( $attachment_id, '_pmpro_pl_slug', true );

			$result = array(
				'icons'       => array(
					'default' => plugins_url( 'assets/icon.png', PMPRO_PLUGIN_LICENSING_PATH ),
				),
				'slug'        => $slug, // This slug is in the View details link.
				'plugin'      => $slug,
				'new_version' => get_post_meta( $attachment_id, '_pmpro_pl_version', true ),
				'package'     => PMPRO_PL_Download_Links::create_download_link( get_post( $attachment_id ) ),
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
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'file_handler' ),
						//'permission_callback' => true, // Let logged out users use this route.
					),
				)
			);
		}
	}
	new PMPRO_PL_REST_API();
}
