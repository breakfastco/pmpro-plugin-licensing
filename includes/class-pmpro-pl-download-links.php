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

		protected static function query_var() {
			return 'pmpro_pl_download';
		}

		protected static function url_path() {
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
			// Make our URLs pretty.
			add_action( 'init', array( $this, 'add_rewrite_rules' ) );

			// Add a variable to hold the file name.
			add_filter( 'query_vars', array( $this, 'add_query_vars' ) );

			add_action( 'template_redirect', array( $this, 'deliver_file_download' ) );
		}

		public function add_query_vars( $query_vars ) {
			$query_vars[] = self::query_var();
			return $query_vars;
		}

		public function add_rewrite_rules() {
			$pattern = self::url_path() . '([a-fA-F0-9]{32})/?$';
			add_rewrite_rule( $pattern, 'index.php?' . self::query_var() . '=$matches[1]', 'top' );
		}

		/**
		 * Creates a download link for the file.
		 *
		 * @param  string $hash The string that links Membership Levels and Media Library attachments.
		 * @return string The download link.
		 */
		public static function create_download_link( $hash ) {
			if ( empty( $hash ) ) {
				return '';
			}

			// https://example.com/membership-account/download/9c43f2e5857b1032374052dbfb8357b6.
			return site_url( self::url_path() . $hash );
		}

		/**
		 * Creates a REST download URL for automatic plugin updates.
		 *
		 * Used by client sites during wp-admin updates. Unlike
		 * create_download_link(), this URL accepts license and site query args
		 * so server-to-server requests can authenticate without a store login.
		 *
		 * @param  string $hash    The file hash.
		 * @param  string $license The customer's license key.
		 * @param  string $site    The requesting site URL.
		 * @return string
		 */
		public static function create_update_download_link( $hash, $license, $site ) {
			if ( empty( $hash ) || empty( $license ) || empty( $site ) ) {
				return '';
			}

			return add_query_arg(
				array(
					'license' => $license,
					'site'    => $site,
				),
				rest_url( 'pmpro/v1/files/' . rawurlencode( $hash ) . '/download' )
			);
		}

		/**
		 * Creates a REST download URL for a file that needs no license key.
		 *
		 * @param  string $hash The file hash.
		 * @return string
		 */
		public static function create_free_update_download_link( $hash ) {
			if ( empty( $hash ) ) {
				return '';
			}

			return rest_url( 'pmpro/v1/files/' . rawurlencode( $hash ) . '/download' );
		}

		/**
		 * Whether a file is attached to a free Membership Level. Files in free
		 * levels are delivered to updaters without a license key.
		 *
		 * @param  string $hash The file hash.
		 * @return bool
		 */
		public static function file_is_free( $hash ) {
			if ( empty( $hash ) || ! function_exists( 'pmpro_getAllLevels' ) || ! function_exists( 'pmpro_isLevelFree' ) ) {
				return false;
			}
			$levels = pmpro_getAllLevels( true );
			foreach ( $levels as $level ) {
				$level_meta_files = get_pmpro_membership_level_meta( (int) $level->id, 'plugin_licensing_files', true );
				if ( empty( $level_meta_files ) || ! isset( $level_meta_files[ $hash ] ) ) {
					continue;
				}
				if ( pmpro_isLevelFree( $level ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Streams an attachment file to the browser.
		 *
		 * @param  int  $attachment_id Attachment post ID.
		 * @param  bool $attachment    When true, send Content-Disposition: attachment.
		 * @return bool True when the file was streamed, false otherwise.
		 */
		public static function stream_attachment( $attachment_id, $attachment = true ) {
			$file_path = get_attached_file( $attachment_id );
			if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
				return false;
			}

			$file_name = basename( $file_path );
			$post      = get_post( $attachment_id );
			$mime_type = ( $post && ! empty( $post->post_mime_type ) ) ? $post->post_mime_type : 'application/zip';
			$disposition = $attachment ? 'attachment' : 'inline';

			if ( ! headers_sent() ) {
				status_header( 200 );
				header( 'Content-Type: ' . $mime_type );
				header( 'Content-Disposition: ' . $disposition . '; filename="' . $file_name . '"' );
				header( 'Expires: 0' );
				header( 'Cache-Control: must-revalidate' );
				header( 'Pragma: public' );
				header( 'Content-Length: ' . filesize( $file_path ) );
			}

			readfile( $file_path );
			return true;
		}

		/**
		 * Finds a file's attachment post ID given its hash or zero.
		 *
		 * @param  string $hash
		 * @return int
		 */
		public static function find_attachment_id( $hash ) {
			// Get all Membership Levels.
			if ( ! function_exists( 'pmpro_getAllLevels' ) ) {
				return 0;
			}
			$levels = pmpro_getAllLevels( true );
			foreach ( $levels as $level ) {
				// Does this Membership Level have files?
				$level_meta_files = get_pmpro_membership_level_meta( (int) $level->id, 'plugin_licensing_files', true );
				if ( empty( $level_meta_files ) ) {
					// No.
					continue;
				}
				foreach ( $level_meta_files as $level_meta_hash => $attachment_id ) {
					if ( $hash === $level_meta_hash ) {
						return $attachment_id;
					}
				}
			}
			// Oh no, we didn't find it.
			return 0;
		}

		/**
		 * Redirects to a file or an error page.
		 *
		 * @return void
		 */
		public function deliver_file_download() {
			$hash = get_query_var( self::query_var() );
			if ( empty( $hash ) ) {
				return;
			}

			// Use the hash to find the attachment ID.
			$attachment_id = self::find_attachment_id( $hash );

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
				wp_safe_redirect( wp_login_url( site_url( self::url_path() . $file_name ) ) );
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
			if ( ! $has_access ) {
				wp_safe_redirect( site_url( '404.php' ) );
				exit;
			}

			// Write the file to the response so the user gets a download.
			self::stream_attachment( $attachment_id, false );
			exit;
		}
	}
	new PMPRO_PL_Download_Links();
}
