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
			$hash = get_query_var( $this->query_var() );
			if ( empty( $hash ) ) {
				return;
			}

			// Use the hash to find the attachment ID.
			global $wpdb;
			$attachment_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM `{$wpdb->prefix}postmeta` WHERE meta_value = %s and meta_key = '_pmpro_pl_hash' LIMIT 1",
					$hash
				)
			);

			if ( empty( $attachment_id ) ) {
				wp_safe_redirect( site_url( '404.php' ) );
				exit;
			}

			// Get the file path from the attachment ID.
			$file_path = get_attached_file( $attachment_id );

			// Does the file exist?
			if ( ! file_exists( $file_path ) ) {
				wp_safe_redirect( site_url( '404.php' ) );
				exit;
			}

			// Is this user logged-in?
			$file_name = basename( $file_path );
			if ( ! is_user_logged_in() ) {
				// No. Redirect to login page with the file as a referrer.
				wp_safe_redirect( wp_login_url( site_url( $this->url_path() . $file_name ) ) );
				exit;
			}

			// Is the file part of a membership level? That this user has?
			$user = wp_get_current_user();
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
			if ( ! isset( $file->ID ) ) {
				return '';
			}
			/**
			 * Requirements are
			 * - does not work for logged out users
			 * - does not indicate the files actual URL
			 */

			// https://breakfastco.xyz/membership-account/download/9c43f2e5857b1032374052dbfb8357b6
			$hash = get_post_meta( $file->ID, '_pmpro_pl_hash', true );
			return site_url( 'membership-account/download/' . $hash );
		}
	}
	new PMPRO_PL_Download_Links();
}
