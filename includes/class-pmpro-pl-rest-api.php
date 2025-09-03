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

		/**
		 * Constructor.
		 *
		 * @return void
		 */
		public function __construct() {
			// Add hooks.
			$this->add_hooks();
		}

		/**
		 * Adds hooks.
		 *
		 * @return void
		 */
		public function add_hooks() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
			add_action( 'init', array( $this, 'register_meta' ) );
		}

		/**
		 * Registers meta.
		 *
		 * @return void
		 */
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
		 * Handles REST requests for activating license keys.
		 *
		 * @param  WP_REST_Request $request The request object.
		 * @return array|WP_Error
		 */
		public function activate_license( $request ) {
			$params  = $request->get_params();
			$hash    = $params['file'];
			$license = $params['license'];

			$status_array = $this->get_license_status_array( $license, $hash, $params['site'] );
			switch ( $status_array['status'] ) {
				case 'active':
					// License is valid and the site is active.
					return new WP_REST_Response(
						array(
							'status'  => 'active',
							'message' => '',
						)
					);
				case 'expired':
				case 'invalid':
					return new WP_REST_Response(
						$status_array
					);
			}

			// Is the site is already active or just activated?
			if ( ! $this->maybe_add_active_site( $hash, $params['site'], pmpro_pl_get_user_id_from_license_key( $license ) ) ) {
				// No. License is good but site limit reached.
				return new WP_REST_Response(
					array(
						'status'  => 'inactive',
						'message' => __( 'Activation failed.', 'pmpro-plugin-licensing' ),
					)
				);
			}

			// License is valid and the site is active.
			return new WP_REST_Response(
				array(
					'status'  => 'active',
					'message' => '',
				)
			);
		}

		/**
		 * Handles REST requests for deactivating sites on license keys.
		 *
		 * @param  WP_REST_Request $request The request object.
		 * @return array|WP_Error
		 */
		public function deactivate_license( $request ) {
			$params        = $request->get_params();
			$hash          = $params['file'];
			$attachment_id = PMPRO_PL_Download_Links::find_attachment_id( $hash );
			if ( 0 === $attachment_id ) {
				// Sorry, invalid file.
				return new WP_REST_Response(
					array(
						'status'  => 'invalid',
						'message' => __( 'File not found.', 'pmpro-plugin-licensing' ),
					)
				);
			}

			// Get the referring URL from the request.
			$referrer = $params['site'];
			if ( empty( $referrer ) ) {
				// No referrer.
				return new WP_REST_Response(
					array(
						'status'  => 'invalid',
						'message' => __( 'Site not found.', 'pmpro-plugin-licensing' ),
					)
				);
			}

			// Is the license key associated with a user?
			$license = $params['license'];
			$user_id = pmpro_pl_get_user_id_from_license_key( $license );
			if ( empty( $user_id ) ) {
				// License key is not associated with a user.
				return new WP_REST_Response(
					array(
						'status'  => 'invalid',
						'message' => __( 'Account failure.', 'pmpro-plugin-licensing' ),
					)
				);
			}

			// License is associated with a user. Check if it's valid.
			$license_check_args = array(
				'license_key'   => $license,
				'attachment_id' => $attachment_id,
				'user_id'       => $user_id,
			);
			// Is the license valid?
			if ( ! pmpro_pl_customer_license_is_valid( $license_check_args ) ) {
				// No.
				return new WP_REST_Response(
					array(
						'status'  => 'invalid',
						'message' => __( 'Validation failed.', 'pmpro-plugin-licensing' ),
					)
				);
			}
			// Yes.
			// Is the site is even active? Who cares.
			$this->maybe_remove_active_site( $hash, $referrer, $user_id );
			return new WP_REST_Response(
				array(
					'status'  => 'inactive',
					'message' => '',
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
				'icons'          => array(
					'default' => plugins_url( 'assets/icon.png', PMPRO_PLUGIN_LICENSING_PATH ),
				),
				'slug'           => $meta['_pmpro_pl_slug'][0] ?? '', // This slug is in the View details link.
				'plugin'         => $meta['_pmpro_pl_slug'][0] ?? '',
				'new_version'    => $meta['_pmpro_pl_version'][0] ?? '',

				// More stuff that's unlike the .org API responses.
				'sections'       => array(
					'description'  => $meta['_pmpro_pl_description'][0] ?? '',
					'installation' => $meta['_pmpro_pl_installation'][0] ?? '',
					'faq'          => $meta['_pmpro_pl_faq'][0] ?? '',
					'changelog'    => $meta['_pmpro_pl_changelog'][0] ?? '',
				),

				'name'           => $meta['_pmpro_pl_name'][0] ?? '',
				'homepage'       => $meta['_pmpro_pl_homepage'][0] ?? '',
				'author'         => $meta['_pmpro_pl_author'][0] ?? '',
				'author_profile' => $meta['_pmpro_pl_author_profile'][0] ?? '',
				'tested'         => $meta['_pmpro_pl_tested'][0] ?? '',
				'last_updated'   => $meta['_pmpro_pl_last_updated'][0] ?? '',
			);

			if ( $include_package ) {
				$result['package'] = PMPRO_PL_Download_Links::create_download_link( $hash );
			}

			return $result;
		}

		public function get_license_status( $request ) {
			$params = $request->get_params();
			return new WP_REST_Response( $this->get_license_status_array( $params['license'], $params['file'], $params['site'] ) );
		}

		protected function get_license_status_array( $license, $hash, $referrer ) {
			$attachment_id = PMPRO_PL_Download_Links::find_attachment_id( $hash );
			if ( 0 === $attachment_id ) {
				// Sorry, invalid file.
				return array(
					'status'  => 'invalid',
					'message' => __( 'File not found.', 'pmpro-plugin-licensing' ),
				);
			}

			// Get the referring URL from the request.
			if ( empty( $referrer ) ) {
				// No referrer.
				return array(
					'status'  => 'invalid',
					'message' => __( 'Site not found.', 'pmpro-plugin-licensing' ),
				);
			}

			// Is the license key valid/associated with a user?
			$user_id = pmpro_pl_get_user_id_from_license_key( $license );
			if ( empty( $user_id ) ) {
				// License key is not associated with a user.
				return array(
					'status'  => 'invalid',
					'message' => __( 'Account failure.', 'pmpro-plugin-licensing' ),
				);
			}

			// Check if license is expired or for other files.
			$license_check_args = array(
				'license_key'   => $license,
				'attachment_id' => $attachment_id,
				'user_id'       => $user_id,
			);
			// Is the license valid for the file the user is using it with?
			if ( ! pmpro_pl_customer_license_is_valid( $license_check_args ) ) {
				// No.
				return array(
					'status'  => 'expired',
					'message' => __( 'License is expired or does not provide access to this file.', 'pmpro-plugin-licensing' ),
				);
			}

			// Is the site already active?
			if ( ! self::site_is_active( $hash, $referrer, $user_id ) ) {
				// No.
				return array(
					'status'  => 'inactive',
					'message' => __( 'Activation failed.', 'pmpro-plugin-licensing' ),
				);
			}

			// Looks good.
			return array(
				'status'  => 'active',
				'message' => '',
			);
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
			if ( self::site_is_active( $hash, $referrer, $user_id ) ) {
				// Yes, this site is already active.
				return true;
			}

			// No. Is there room to add it?
			$attachment_id = PMPRO_PL_Download_Links::find_attachment_id( $hash );
			$limit         = get_post_meta( $attachment_id, '_pmpro_pl_activation_limit', true );
			$active_sites  = PMPRO_PL_Membership_Features::get_active_sites( $user_id, $hash );
			if ( $limit > count( $active_sites ) ) {
				// Yes. Add it.
				$active_sites[] = $referrer;
				PMPRO_PL_Membership_Features::set_active_sites( $user_id, $hash, $active_sites );
				return true;
			}
			return false;
		}

		/**
		 * Determines whether the given site is already active for a file.
		 *
		 * @param  string $hash     The hash of the file.
		 * @param  string $referrer The referring URL.
		 * @param  int    $user_id  The ID of the user.
		 * @return bool True when already active, false otherwise.
		 */
		public static function site_is_active( $hash, $referrer, $user_id ) {
			$active_sites = PMPRO_PL_Membership_Features::get_active_sites( $user_id, $hash );
			return in_array( $referrer, $active_sites, true );
		}

		/**
		 * Removes a site from the list of active sites.
		 *
		 * @param  string $hash The hash of the file.
		 * @param  string $referrer The referring URL.
		 * @param  int    $user_id The ID of the user.
		 * @return void
		 */
		public function maybe_remove_active_site( $hash, $referrer, $user_id ) {
			// Is the site requesting this file already among the active sites?
			$site_is_active = false;
			$active_sites   = PMPRO_PL_Membership_Features::get_active_sites( $user_id, $hash );
			if ( empty( $active_sites ) || ! in_array( $referrer, $active_sites, true ) ) {
				// No active sites for this file, we're done.
				return;
			}

			if ( in_array( $referrer, $active_sites, true ) ) {
				// Yes, this site is active. Remove it.
				$active_sites = array_diff( $active_sites, array( $referrer ) );
				PMPRO_PL_Membership_Features::set_active_sites( $user_id, $hash, $active_sites );
			}
		}

		/**
		 * Registers REST route to respond to update checks from customer sites.
		 *
		 * @return void
		 */
		public function register_routes() {
			$rest_namespace = 'pmpro/v1';
			/**
			 * Route to check for updates.
			 * Example: https://example.com/wp-json/pmpro/v1/files/xxx/?license=yyy&site=zzz
			 */
			register_rest_route(
				$rest_namespace,
				'/files/(?P<file>[a-zA-Z0-9]+)',
				array(
					array(
						'methods'  => WP_REST_Server::READABLE, // GET.
						'callback' => array( $this, 'file_handler' ),
						'args'     => array(
							'file' => array(
								'required' => true,
							),
							'site' => array(
								'required' => true,
							),
						),
					),
				)
			);

			/**
			 * Route to activate license keys.
			 * Example: https://example.com/wp-json/pmpro/v1/activate?file=vvv&license=xxx&site=yyy
			 *
			 * @param WP_REST_Request $request The request object.
			 * @return array|WP_Error
			 */
			register_rest_route(
				$rest_namespace,
				'/activate',
				array(
					array(
						'methods'  => WP_REST_Server::CREATABLE, // POST.
						'callback' => array( $this, 'activate_license' ),
						'args'     => array(
							'file' => array(
								'required' => true,
							),
							'license' => array(
								'required' => true,
							),
							'site'    => array(
								'required' => true,
							),
						),
					),
				)
			);

			/**
			 * Route to deactivate sites on a license key.
			 * Example: https://example.com/wp-json/pmpro/v1/deactivate?file=vvv&license=xxx&site=yyy
			 *
			 * @param WP_REST_Request $request The request object.
			 * @return array|WP_Error
			 */
			register_rest_route(
				$rest_namespace,
				'/deactivate',
				array(
					array(
						'methods'  => WP_REST_Server::CREATABLE, // POST.
						'callback' => array( $this, 'deactivate_license' ),
						'args'     => array(
							'file' => array(
								'required' => true,
							),
							'license' => array(
								'required' => true,
							),
							'site'    => array(
								'required' => true,
							),
						),
					),
				)
			);

			/**
			 * Route to check a license key's status.
			 * Example: https://example.com/wp-json/pmpro/v1/status?file=vvv&license=xxx&site=yyy
			 *
			 * @param WP_REST_Request $request The request object.
			 * @return array|WP_Error
			 */
			register_rest_route(
				$rest_namespace,
				'/status',
				array(
					array(
						'methods'  => WP_REST_Server::READABLE, // GET.
						'callback' => array( $this, 'get_license_status' ),
						'args'     => array(
							'file'    => array(
								'required' => true,
							),
							'license' => array(
								'required' => true,
							),
							'site'    => array(
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
