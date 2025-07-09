<?php
/**
 * PMPRO Plugin Licensing Membership Features
 *
 * @package pmpro-plugin-licensing
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PMPRO_PL_Membership_Features' ) ) {
	/**
	 * Class PMPRO_PL_Membership_Features
	 *
	 * @since 0.1.0
	 */
	class PMPRO_PL_Membership_Features {
		/**
		 * Constructor.
		 *
		 * @since 0.1.0
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
			// Only do this once.
			if ( has_filter( 'pmpro_after_checkout', array( $this, 'after_checkout_generate_license_key' ), 10, 2 ) ) {
				return;
			}

			// Generate license keys during orders.
			add_action( 'pmpro_after_checkout', array( $this, 'after_checkout_generate_license_key' ), 10, 2 );
			// Generate license keys when users are manually assigned to memberships.
			add_action( 'pmpro_after_change_membership_level', array( $this, 'after_membership_change_generate_license_key' ), 10, 3 );
			// Display users' license keys to them on their account page.
			add_action( 'pmpro_account_bullets_bottom', array( $this, 'show_users_their_license_key' ) );
			// Display file downloads associated with membership levels on user account page.
			add_action( 'pmpro_membership_account_after_level_card_content', array( $this, 'show_users_their_downloads' ), 10, 1 );
			add_action( 'wp_enqueue_scripts', array( $this, 'membership_account_register_scripts' ) );

			// TODO filter this to protect Membership files.
			// $hasaccess = apply_filters("pmpro_has_membership_access_filter", $hasaccess, $mypost, $myuser, $post_membership_levels).
		}

		/**
		 * Generate and save a unique license key for a user if they don't already have one.
		 *
		 * @param int $user_id
		 * @param string $context Optional. String to help make the key unique (e.g., order or level info).
		 * @return void
		 */
		private function maybe_generate_license_key_for_user( $user_id, $context = '' ) {
			$meta_key = 'pmpro_plugin_licensing_license_key';
			$license_key = get_user_meta( $user_id, $meta_key, true );
			if ( empty( $license_key ) ) {
				// No. Generate one.
				$license_key = md5( uniqid() . $user_id . $context );
				// Does this license key already exist?.
				global $wpdb;
				$query_pattern = "SELECT user_id FROM `{$wpdb->prefix}usermeta` WHERE meta_key = %s and meta_value = %s LIMIT 1";
				// @phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared.
				$key_exists = 0 < count( $wpdb->get_var( $wpdb->prepare( $query_pattern, $meta_key, $license_key ) ) ?? array() );
				while ( $key_exists ) {
					// Yes.
					$license_key = md5( uniqid() );
					// @phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared.
					$key_exists = 0 < count( $wpdb->get_var( $wpdb->prepare( $query_pattern, $meta_key, $license_key ) ) ?? array() );
				}
				// Save it.
				update_user_meta( $user_id, $meta_key, $license_key );
			}
		}

		public function after_checkout_generate_license_key( $user_id, $order ) {
			// Get the user's current membership levels.
			$levels = pmpro_getMembershipLevelsForUser( $user_id );
			$has_files = false;
			foreach ( $levels as $level ) {
				$level_meta_files = get_pmpro_membership_level_meta( (int) $level->id, 'plugin_licensing_files', true );
				if ( ! empty( $level_meta_files ) ) {
					$has_files = true;
					break;
				}
			}
			if ( ! $has_files ) {
				return;
			}
			$this->maybe_generate_license_key_for_user( $user_id, wp_json_encode( $order ) );
		}

		public function after_membership_change_generate_license_key( $level_id, $user_id, $cancel_level ) {
			// Only generate a license key if this level has files associated with it.
			$level_meta_files = get_pmpro_membership_level_meta( (int) $level_id, 'plugin_licensing_files', true );
			if ( empty( $level_meta_files ) ) {
				return;
			}
			$this->maybe_generate_license_key_for_user( $user_id, $level_id );
		}

		/**
		 * Registers scripts and styles for the membership account page.
		 *
		 * @since 0.1.0
		 * @return void
		 */
		public function membership_account_register_scripts() {
			$min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			wp_register_style(
				'pmpro-pl-account',
				plugins_url( "assets/css/membership-account{$min}.css", PMPRO_PLUGIN_LICENSING_PATH ),
				array(),
				PMPRO_PLUGIN_LICENSING_VERSION
			);
		}

		/**
		 * Shows users their downloads for a specific membership level.
		 *
		 * @since 0.1.0
		 * @param object $level The membership level object.
		 * @return void
		 */
		public function show_users_their_downloads( $level ) {
			// Are threre any files associated with this membership level?
			$level_meta_files = get_pmpro_membership_level_meta( (int) $level->id, 'plugin_licensing_files', true );
			if ( empty( $level_meta_files ) ) {
				return;
			}

			wp_enqueue_style( 'pmpro-pl-account' );

			// Show the files in a table similar to the one on the admin side.
			?>
			<table class="pmpro-pl-downloads <?php echo esc_attr( pmpro_get_element_class( 'pmpro_table' ) ); ?>">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Download', 'paid-memberships-pro' ); ?></th>
						<th><?php esc_html_e( 'Version', 'paid-memberships-pro' ); ?></th>
						<th><?php esc_html_e( 'Active Sites', 'paid-memberships-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $level_meta_files as $hash => $attachment_id ) {
						// Is there a file attached to this hash?
						if ( 0 === $attachment_id ) {
							// No.
							continue;
						}
						$file = get_post( $attachment_id );

						// TODO active sites.
						?>
						<tr>
							<td><a href="<?php echo esc_url( PMPRO_PL_Download_Links::create_download_link( $hash ) ); ?>" target="_blank"><?php echo esc_html( basename( $file->guid ) ); ?></a></td>
							<td><?php echo esc_html( get_post_meta( $attachment_id, '_pmpro_pl_version', true ) ); ?></td>
							<td><a>5 sites</a></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Shows users their license key on the account page.
		 *
		 * @since 0.1.0
		 * @return void
		 */
		public function show_users_their_license_key() {
			// Get the current user.
			$user = wp_get_current_user();
			if ( ! $user ) {
				return;
			}
			// Get the license key.
			$license_key = get_user_meta( $user->ID, 'pmpro_plugin_licensing_license_key', true );
			if ( empty( $license_key ) ) {
				return;
			}
			// Display it.
			?><li class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_list_item' ) ); ?>"><strong><?php esc_html_e( 'License Key', 'paid-memberships-pro' ); ?>:</strong> <input type="text" readonly="readonly" id="pmpro_pl_license_key" value="<?php echo esc_attr( $license_key ); ?>" /><button type="button" class="pmpro_btn" onclick="javascript:document.querySelector('#pmpro_pl_license_key').select();document.execCommand('copy');">Copy</button></li><?php
		}
	}
	new PMPRO_PL_Membership_Features();
}
