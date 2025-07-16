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
		 * @param  WP_REST_Request $request The request object.
		 * @return array|WP_Error
		 */
		public function file_handler( $request ) {
			$params = $request->get_params();
			$hash   = $params['file'];
			if ( empty( $hash ) ) {
				// Return the same 404 as an invalid route.
				return new WP_Error(
					'rest_forbidden',
					__( 'Request failed, invalid file.' ),
					array( 'status' => 401 )
				);
			}

			// Get the latest version info from attachment meta.
			$attachment_id = PMPRO_PL_Download_Links::find_attachment_id( $hash );
			if ( 0 === $attachment_id ) {
				// Sorry, invalid file.
				return new WP_Error(
					'rest_forbidden',
					__( 'Request failed, invalid file.' ),
					array( 'status' => 401 )
				);
			}

			// Get the referring URL from the request.
			$referrer = $params['site'];
			if ( empty( $referrer ) ) {
				// No referrer.
				return new WP_Error(
					'rest_forbidden',
					__( 'Request failed, invalid referrer.' ),
					array( 'status' => 401 )
				);
			}

			/**
			 * Only paying customers with license keys get updates, but anyone
			 * can request the file to get the latest version info. The
			 * 'package' download URL will be missing for those who do not have
			 * access to the new file. The package URL is predictable anyways!
			 */
			$include_package = false;

			// Was a license key provided?
			if ( ! empty( $params['license'] ) ) {
				// Yes.
				// Is the license key associated with a user?
				$user_id = pmpro_pl_get_user_id_from_license_key( $params['license'] );
				if ( ! empty( $user_id ) ) {
					// Yes.
					$license_check_args = array(
						'license_key'   => $params['license'],
						'attachment_id' => $attachment_id,
						'user_id'       => $user_id,
					);
					// Is the license valid and the site is already active or just activated?
					if ( pmpro_pl_customer_license_is_valid( $license_check_args ) && $this->maybe_add_active_site( $hash, $referrer, $user_id ) ) {
						// Yes.
						$include_package = true;
					}
				}
			}

			$meta   = get_post_meta( $attachment_id );
			$result = array(
				'icons'       => array(
					'default' => plugins_url( 'assets/icon.png', PMPRO_PLUGIN_LICENSING_PATH ),
				),
				'slug'        => $meta['_pmpro_pl_slug'][0] ?? '', // This slug is in the View details link.
				'plugin'      => $meta['_pmpro_pl_slug'][0] ?? '',
				'new_version' => $meta['_pmpro_pl_version'][0] ?? '',
				'sections'    => array(
					'description'  => $meta['_pmpro_pl_description'][0] ?? '',
					'installation' => $meta['_pmpro_pl_installation'][0] ?? '',
					'faq'          => $meta['_pmpro_pl_faq'][0] ?? '',
					'changelog'    => $meta['_pmpro_pl_changelog'][0] ?? '',
				),
			);

			if ( $include_package ) {
				$result['package'] = PMPRO_PL_Download_Links::create_download_link( $hash );
			}

			return $result;
		}

		/**
		 * Checks if the site is already active and adds it if not.
		 *
		 * @param  string $hash The hash of the file.
		 * @param  string $referrer The referring URL.
		 * @param  int    $user_id The ID of the user.
		 * @return bool True if the site is already active, false otherwise.
		 */
		public function maybe_add_active_site( $hash, $referrer, $user_id ) {
			// Is the site requesting this file already among the active sites?
			$site_is_active = false;
			$active_sites   = get_user_meta( $user_id, 'pmpro_plugin_licensing_active_sites', true );
			if ( ! is_array( $active_sites ) ) {
				$active_sites = array();
			}

			if ( ! is_array( $active_sites[ $hash ] ) ) {
				$active_sites[ $hash ] = array();
			}

			if ( is_array( $active_sites[ $hash ] ) && in_array( $referrer, $active_sites[ $hash ], true ) ) {
				// Yes, this site is already active.
				$site_is_active = true;
			} else {
				// No. Add it.
				// TODO does this membership limit the active sites?
				if ( ! is_array( $active_sites[ $hash ] ) ) {
					$active_sites[ $hash ] = array();
				}
				$active_sites[ $hash ][] = $referrer;
				update_user_meta( $user_id, 'pmpro_plugin_licensing_active_sites', $active_sites );
			}

			return $site_is_active;
		}

		/**
		 * Registers REST route to respond to update checks from customer sites.
		 *
		 * @return void
		 */
		public function register_routes() {
			$rest_namespace = 'pmpro/v1';
			/**
			 * Get user access for a specific post.
			 * Example: https://example.com/wp-json/pmpro/v1/files
			 */
			register_rest_route(
				$rest_namespace,
				'/files/(?P<file>[a-zA-Z0-9]+)',
				array(
					array(
						'methods'  => WP_REST_Server::READABLE,
						'callback' => array( $this, 'file_handler' ),
						'args'     => array(
							'file' => array(
								'required' => true,
							),
						),
					),
				)
			);
		}
	}
	new PMPRO_PL_REST_API();
}
