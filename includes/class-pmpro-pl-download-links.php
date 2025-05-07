<?php
/**
 * PMPRO Plugin Licensing Download Links
 *
 * @package pmpro-plugin-licensing
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PMPRO_PL_Download_Links' ) ) {
	/**
	 * Class PMPRO_PL_Download_Links
	 *
	 * @since 0.1.0
	 */
	class PMPRO_PL_Download_Links {

		protected function query_var() {
			return 'pmpro_pl_download';
		}

		protected function url_path() {
			return 'membership-account/download/';
		}

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
			// if ( has_filter( 'init', array( $this, 'add_rewrite_rules' ) ) ) {
			// 	return;
			// }

			// Make our URLs pretty.
			add_action( 'init', array( $this, 'add_rewrite_rules' ) );

			// Add a variable to hold the file name.
			add_filter( 'query_vars', array( $this, 'add_query_vars' ) );

			add_action( 'template_redirect', array( $this, 'deliver_file_download' ) );
		}

		public function deliver_file_download() {
			$file_name = get_query_var( $this->query_var() );
			if ( empty( $file_name ) ) {
				//global $wp_query;
				return;
			}

			// Does the file exist?
			$file_path = PMPRO_PL_Settings::upload_path() . '/' . $file_name;
			if ( ! file_exists( $file_path ) ) {
				wp_safe_redirect( site_url( '404.php' ) );
				exit;
			}

			// Is this user logged-in?
			if ( ! is_user_logged_in() ) {
				// No. Redirect to login page with the file as a referrer.
				wp_safe_redirect( wp_login_url( site_url( $this->url_path() . $file_name ) ) );
				exit;
			}

			// Is the file part of a membership level? That this user has?
			$user          = wp_get_current_user();
			$attachment_id = $this->find_attachment_id( $file_name );
			if ( empty( $attachment_id ) ) {
				// Cannot find an attachment ID for this file name.
				wp_safe_redirect( site_url( '404.php' ) );
				exit;
			}
			$has_access = pmpro_pl_customer_license_is_valid(
				array(
					'user_id'       => $user->ID,
					'attachment_id' => $attachment_id,
				)
			);

			// Write the file to the response so the user gets a download.
			$post = get_post( $attachment_id );
			header( 'Content-Type: ' . $post->post_mime_type );
			header( 'Content-Disposition: inline; filename="' . $file_name . '"' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate' );
			header( 'Pragma: public' );
			header( 'Content-Length: ' . filesize( $file_path ) );
			readfile( $file_path );
			exit;
		}

		protected function find_attachment_id( $file_name ) {
			global $wpdb;
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE guid LIKE %s AND post_type = 'attachment' LIMIT 1",
					'%' . $wpdb->esc_like( '/' . $file_name )
				)
			);
		}

		public function add_rewrite_rules() {
			// Pattern for "more-notification-types-v1.0.2.zip".
			add_rewrite_rule( $this->url_path() . '([a-zA-Z0-9\._\-]+)$', 'index.php?' . $this->query_var() . '=$matches[1]', 'top' );
		}

		public function add_query_vars( $query_vars ) {
			$query_vars[] = $this->query_var();
			return $query_vars;
		}

		/**
		 * Creates a download link for the file.
		 *
		 * @param WP_Post $file The file to create a download link for.
		 * @return string The download link.
		 */
		public static function create_download_link( $file ) {
			/**
			 * Requirements are
			 * - does not work for logged out users
			 * - does not indicate the files actual URL
			 */

			// https://breakfastco.test/membership-account/download/more-notification-types-v1.0.2.zip
			// https://breakfastco.xyz/membership-account/download/more-notification-types-v1.0.2.zip
			// https://breakfastco.xyz/membership-account/download/more-notification-types-v1.0.2.zip?hash=9234f4y9hf43fhgq34fh39f4h4
			return site_url( 'membership-account/download/' . basename( get_the_guid( $file ) ) );
		}
	}
	new PMPRO_PL_Download_Links();
}
