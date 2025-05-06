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

			add_action( 'pmpro_after_checkout', array( $this, 'after_checkout_generate_license_key' ), 10, 2 );

			// TODO filter this to protect Membership files.
			//$hasaccess = apply_filters("pmpro_has_membership_access_filter", $hasaccess, $mypost, $myuser, $post_membership_levels);
		}

		/**
		 * Creates a unique license key for the user if they do not already have
		 * one.
		 *
		 * @param int         $user_id ID of the user checking out.
		 * @param MemberOrder $order The order to complete the checkout for.
		 * @return void
		 */
		public function after_checkout_generate_license_key( $user_id, $order ) {
			$meta_key = 'pmpro_plugin_licensing_license_key';
			// Does this user have a license key?
			$license_key = get_user_meta( $user_id, $meta_key, true );
			if ( empty( $license_key ) ) {
				// No. Generate one.
				$license_key = md5( wp_json_encode( $order ) );
				// Does this license key already exist?
				global $wpdb;
				$query_pattern = "SELECT user_id FROM `{$wpdb->prefix}usermeta` WHERE meta_key = %s and meta_value = %s LIMIT 1";
				// @phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$key_exists = 0 < count( $wpdb->get_var( $wpdb->prepare( $query_pattern, $meta_key, $license_key ) ) ?? array() );
				while ( $key_exists ) {
					// Yes.
					$license_key = md5( uniqid() );
					// @phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$key_exists = 0 < count( $wpdb->get_var( $wpdb->prepare( $query_pattern, $meta_key, $license_key ) ) ?? array() );
				}
				// Save it.
				update_user_meta( $user_id, $meta_key, $license_key );
			}
		}
	}
	new PMPRO_PL_Membership_Features();
}
