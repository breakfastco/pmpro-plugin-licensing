<?php
/**
 * PMPRO Plugin Licensing Settings
 *
 * @package pmpro-plugin-licensing
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PMPRO_PL_Settings' ) ) {
	/**
	 * Class PMPRO_PL_Settings
	 *
	 * @since 0.1.0
	 */
	class PMPRO_PL_Settings {
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
			// Add a tab to the PMPro settings page.
			add_action( 'admin_menu', array( $this, 'add_submenu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );

			// Save meta when membership level settings are saved.
			add_action( 'pmpro_save_membership_level', array( $this, 'membership_level_save_files' ), 10, 1 );
			// Extract changelogs and other text from readme.txt files in uploaded .zips.
			add_action( 'pmpro_save_membership_level', array( $this, 'extract_readme_data_during_uploads' ), 10, 1, );
			// Add a file upload UI in the level edit screen.
			add_action( 'pmpro_membership_level_after_other_settings', array( $this, 'membership_level_add_files_controls' ), 10, 1 );
			// Change the upload path for files uploaded to Membership levels.
			add_filter( 'upload_dir', array( $this, 'maybe_change_upload_path' ), 99 );

			// Add an AJAX handler.
			add_action( 'wp_ajax_pmpro_pl_generate_hash', array( $this, 'ajax_handler_generate_hash' ) );
		}

		public function add_submenu() {
			add_submenu_page(
				'pmpro-dashboard', // parent slug.
				__( 'Plugin Licensing', 'pmpro-plugin-licensing' ), // page title.
				__( 'Plugin Licensing', 'pmpro-plugin-licensing' ), // menu title.
				'manage_options', // capability.
				'pmpro_plugin_licensing', // menu slug.
				array( $this, 'settings_page' ) // function to call.
			);
		}

		public function ajax_handler_generate_hash() {
			// Verify nonce.
			if ( ! wp_verify_nonce( $_POST['_ajax_nonce'], 'pmpro-pl-generate-hash' ) ) {
				wp_send_json_error();
				wp_die();
			}

			/**
			 * Generate an md5 hash checksum and make sure it has not yet been
			 * used by another file.
			 */
			$salt_string = 'pmpro_pl_hash_salt_';
			$hash        = md5( $salt_string . wp_json_encode( $_POST ) );
			while ( ! empty( PMPRO_PL_Download_Links::find_attachment_id( $hash ) ) ) {
				$hash = md5( $salt_string . uniqid() );
			}
			wp_send_json_success( array( 'hash' => $hash ) );
		}

		public function extract_readme_data_during_uploads( $level_id ) {
			if ( ! isset( $_POST['plugin_licensing_files'] ) ) {
				return;
			}
			$level_meta_files = array_map( 'intval', (array) $_POST['plugin_licensing_files'] );
			foreach ( $level_meta_files as $hash => $post_id ) {

				// Is it a zip file?
				$attachment_post = get_post( $post_id );
				$mime_type       = $attachment_post->post_mime_type;
				if ( 'application/zip' !== $mime_type ) {
					// No.
					return;
				}

				// Unzip the file, read the plugin info, and then delete the unzipped data.
				if ( ! class_exists( 'WP_Filesystem' ) ) {
					require_once ABSPATH . '/wp-admin/includes/file.php';
				}

				global $wp_filesystem;
				if ( ! $wp_filesystem ) {
					WP_Filesystem();
				}

				$file_path = get_attached_file( $post_id );
				// File could not exist.
				$result = unzip_file( $file_path, dirname( $file_path ) . '/temp' );
				if ( is_wp_error( $result ) ) {
					continue;
				}
				$temp_plugins = $this->get_temp_plugins( dirname( $file_path ) . '/temp/' );

				/**
				 * There is only one, but use a loop because we do not know what
				 * the array key is.
				 */
				foreach ( $temp_plugins as $plugin_basename => $plugin_data ) {

					// Save this plugin's version number in the attachment post meta.
					if ( ! empty( $plugin_data['Version'] ) ) {
						update_post_meta( $post_id, '_pmpro_pl_version', $plugin_data['Version'] );
					}

					// Save the slug.
					if ( ! empty( $plugin_basename ) ) {
						update_post_meta( $post_id, '_pmpro_pl_slug', $plugin_basename );
					}

					// These come from the root PHP file magic header comment.
					update_post_meta( $post_id, '_pmpro_pl_homepage', $plugin_data['PluginURI'] );
					update_post_meta( $post_id, '_pmpro_pl_author', $plugin_data['Author'] );
					update_post_meta( $post_id, '_pmpro_pl_author_profile', $plugin_data['AuthorURI'] );

					// Media Library upload date of the plugin .zip file.
					if ( $attachment_post ) {
						update_post_meta( $post_id, '_pmpro_pl_last_updated', $attachment_post->post_date );
					}

					/**
					 * Need to readme.txt data so the REST API can it available
					 * to customer sites. Stash the description, installation
					 * instructions, FAQ, and changelog in attachment post meta.
					 */
					$parser = new PMPRO_PL_Readme_Parser( dirname( $file_path ) . '/temp/' . dirname( $plugin_basename ) . '/readme.txt' );
					update_post_meta( $post_id, '_pmpro_pl_description', $parser->sections['description'] ?? '' );
					update_post_meta( $post_id, '_pmpro_pl_installation', $parser->sections['installation'] ?? '' );
					update_post_meta( $post_id, '_pmpro_pl_faq', $parser->sections['faq'] ?? '' );
					update_post_meta( $post_id, '_pmpro_pl_changelog', $parser->sections['changelog'] ?? '' );
					update_post_meta( $post_id, '_pmpro_pl_name', $parser->name ?? '' );
					update_post_meta( $post_id, '_pmpro_pl_tested', $parser->tested ?? '' );
				}

				// Delete the temp folder.
				$wp_filesystem->rmdir( dirname( $file_path ) . '/temp', true );
			}
		}

		/**
		 * This is like core WordPress' get_plugins() but it works on any path
		 * given. Used to extract info from a plugin unzipped to a temp folder.
		 *
		 * @param  string $plugin_root File path where plugins should be found.
		 * @return array
		 */
		protected function get_temp_plugins( $plugin_root ) {
			$plugins_dir = @opendir( $plugin_root );
			if ( $plugins_dir ) {
				while ( ( $file = readdir( $plugins_dir ) ) !== false ) {
					if ( str_starts_with( $file, '.' ) ) {
						continue;
					}

					if ( is_dir( $plugin_root . '/' . $file ) ) {
						$plugins_subdir = @opendir( $plugin_root . '/' . $file );

						if ( $plugins_subdir ) {
							while ( ( $subfile = readdir( $plugins_subdir ) ) !== false ) {
								if ( str_starts_with( $subfile, '.' ) ) {
									continue;
								}

								if ( str_ends_with( $subfile, '.php' ) ) {
									$plugin_files[] = "$file/$subfile";
								}
							}

							closedir( $plugins_subdir );
						}
					} elseif ( str_ends_with( $file, '.php' ) ) {
						$plugin_files[] = $file;
					}
				}

				closedir( $plugins_dir );
			}

			if ( empty( $plugin_files ) ) {
				return array();
			}

			$wp_plugins = array();
			foreach ( $plugin_files as $plugin_file ) {
				if ( ! is_readable( "$plugin_root/$plugin_file" ) ) {
					continue;
				}

				// Do not apply markup/translate as it will be cached.
				$plugin_data = get_plugin_data( "$plugin_root/$plugin_file", false, false );

				if ( empty( $plugin_data['Name'] ) ) {
					continue;
				}

				$wp_plugins[ plugin_basename( $plugin_file ) ] = $plugin_data;
			}
			return $wp_plugins;
		}

		/**
		 * Ignores the year-month sub-directory. Uploaded plugin zip files
		 * are always stored in wp-content/uploads/pmpro-plugin-licensing.
		 *
		 * @param  array $uploads Uploads directory information.
		 * @return array
		 */
		public function maybe_change_upload_path( $uploads ) {
			// Is a flag set telling us this is a Plugin Licensing upload?
			if ( ! isset( $_POST['is_pmpro_pl'] ) ) {
				return $uploads;
			}

			// Overwrite the path and URL that may contain year-months.
			$directory       = '/pmpro-plugin-licensing';
			$uploads['path'] = $uploads['basedir'] . $directory;
			$uploads['url']  = $uploads['baseurl'] . $directory;

			return $uploads;
		}

		/**
		 * Saves list of files in meta when membership levels are saved.
		 *
		 * @param  int $level_id Membership Level ID.
		 * @return void
		 */
		public function membership_level_save_files( $level_id ) {
			if ( isset( $_POST['plugin_licensing_files'] ) ) {
				$level_meta_files = array_map( 'intval', (array) $_POST['plugin_licensing_files'] );
				update_pmpro_membership_level_meta( $level_id, 'plugin_licensing_files', $level_meta_files );
				foreach ( $level_meta_files as $hash => $attachment_id ) {
					if ( 0 === $attachment_id ) {
						continue;
					}
					// Store the hash in each file's post meta.
					update_post_meta( $attachment_id, 'pmpro_pl_hash', $hash );
					// Store the activation limit in each file's post meta.
					update_post_meta( $attachment_id, '_pmpro_pl_activation_limit', $_POST['plugin_licensing_limits'][ $hash ] );
				}
			} else {
				// User erased all downloads from this level.
				delete_pmpro_membership_level_meta( $level_id, 'plugin_licensing_files' );
			}
		}

		/**
		 * Adds file controls while editing a Membership level.
		 *
		 * @param  mixed $level
		 * @return void
		 */
		public function membership_level_add_files_controls( $level ) {
			$level_id         = $level->id;
			$level_meta_files = get_pmpro_membership_level_meta( $level_id, 'plugin_licensing_files', true );
			if ( ! is_array( $level_meta_files ) ) {
				$level_meta_files = array();
			}

			wp_enqueue_style( 'pmpro-pl-settings' );
			wp_enqueue_script( 'pmpro-pl-settings' );

			?>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row" valign="top">
							<label>Files</label></th>
						<td>
							<p>Use the <b>Add File</b> button below to create a hash for the plugin you'd like to sell. Use this hash in your plugin's code when it connects to this store to check for updates. The hash links plugins to this Membership Level.</p>
							<p class="submit"><button type="button" class="button" id="file-attachment-add" title="Upload or choose a file to provide with this Membership Level">Add File</button></p>
							<table id="pmpro-file-attachments-list" class="wp-list-table widefat fixed striped table-view-list">
								<thead>
									<tr>
										<th scope="row" valign="top"><label><?php esc_html_e( 'File', 'pmpro-plugin-licensing' ); ?></label></th>
										<th scope="row" valign="top" class="file-hash"><?php esc_html_e( 'Hash', 'pmpro-plugin-licensing' ); ?></th>
										<th scope="row" valign="top" class="file-version"><?php esc_html_e( 'Version', 'pmpro-plugin-licensing' ); ?></th>
										<th scope="row" valign="top" class="file-activation-limit"><?php esc_html_e( 'Activation Limit', 'pmpro-plugin-licensing' ); ?></th>
										<th scope="row" valign="top" class="file-remove"><?php esc_html_e( 'Remove', 'pmpro-plugin-licensing' ); ?></th>
									</tr>
								</thead>
								<tbody>
							<?php
							foreach ( $level_meta_files as $hash => $attachment_id ) :
								$file      = get_post( $attachment_id );
								$file_name = ( null !== $file ) ? basename( $file->guid ) : __( 'File not found!', 'pmpro-plugin-licensing' );
								?>
									<tr>
										<td id="file_name_<?php echo esc_attr( $hash ); ?>" class="file-name">
											<?php
											// Link to file or Attach button if there is no file.
											if ( 0 !== $attachment_id ) {
												// File name linked to download it.
												// Button to replace the file with a new version.
												printf(
													'<a href="%s"><b>%s</b></a><button type="button" class="button file-attachment-replace" title="%s">%s</button>',
													esc_url( wp_get_attachment_url( $attachment_id ) ),
													esc_html( $file_name ),
													esc_attr__( 'Replace the file associated with this hash', 'pmpro-plugin-licensing' ),
													esc_html__( 'Replace', 'pmpro-plugin-licensing' )
												);
											} else {
												printf(
													'<button type="button" class="button attach-file-to-row" data-hash="%s">%s</button>',
													esc_attr( $hash ),
													esc_attr( __( 'Attach plugin .zip', 'pmpro-pl-licensing' ) )
												);
											}
											?>
										<td>
											<?php // Hash. ?>
											<input type="text" readonly="readonly" class="file-hash" id="plugin_licensing_hash_<?php echo esc_attr( $hash ); ?>" value="<?php echo esc_attr( $hash ); ?>" />
											<button type="button" class="button copy-hash" title="<?php esc_attr_e( 'Copy this hash to the clipboard', 'pmrpo-plugin-licensing' ); ?>" onclick="pmproPlCopyClipboard('plugin_licensing_hash_<?php echo esc_attr( $hash ); ?>');">Copy hash</button>
										</td>
										<td id="file_version_<?php echo esc_attr( $hash ); ?>">
											<?php // Version. ?>
											<?php echo esc_html( get_post_meta( $attachment_id, '_pmpro_pl_version', true ) ); ?>
										</td>
										<td>
											<?php // Activation limit. ?>
											<input type="number" class="file-activation-limit" title="<?php esc_attr_e( 'The number of sites this plugin can be activated. Leave empty for unlimited.', 'pmpro-plugin-licensing' ); ?>" name="plugin_licensing_limits[<?php echo esc_attr( $hash ); ?>]"  value="<?php echo esc_attr( get_post_meta( $attachment_id, '_pmpro_pl_activation_limit', true ) ); ?>" />
										</td>
										<td>
											<?php // Remove button. ?>
											<button type="button" class="button remove-file-attachment" title="<?php esc_attr_e( 'Remove this hash and file from this Membership Level', 'pmpro-plugin-licensing' ); ?>"><?php esc_html_e( 'Remove', 'pmpro-plugin-licensing' ); ?></button>
											<input type="hidden" name="plugin_licensing_files[<?php echo esc_attr( $hash ); ?>]"  id="plugin_licensing_file_<?php echo esc_attr( $hash ); ?>" value="<?php echo esc_attr( $attachment_id ); ?>">
										</td>
									</tr>
								<?php
							endforeach;
							?>
								</tbody>
							</table>

						<?php do_action( 'pmpro_pl_after_settings' ); ?>

						</td>
					</tr>
				</tbody>
			</table>
			<?php
		}

		protected function obfuscate_license_key( $license_key ) {
			if ( ! empty( $license_key ) ) {
				return str_replace(
					substr( $license_key, 0, 28 ),
					str_repeat( '*', 28 ),
					$license_key
				);
			}
			return '';
		}

		/**
		 * Register scripts and styles.
		 *
		 * @return void
		 */
		public function register_scripts() {
			$min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			wp_register_style(
				'pmpro-pl-settings',
				plugins_url( "assets/css/settings{$min}.css", PMPRO_PLUGIN_LICENSING_PATH ),
				array(),
				PMPRO_PLUGIN_LICENSING_VERSION
			);

			wp_register_script(
				'pmpro-pl-settings',
				plugins_url( "assets/js/settings{$min}.js", PMPRO_PLUGIN_LICENSING_PATH ),
				array( 'wp-api', 'jquery' ),
				PMPRO_PLUGIN_LICENSING_VERSION,
				true
			);

			wp_add_inline_script(
				'pmpro-pl-settings',
				sprintf(
					'const pmpro_pl_settings = %s;',
					wp_json_encode(
						(object) array(
							'ajaxurl' => admin_url( 'admin-ajax.php' ),
							'nonce'   => wp_create_nonce( 'pmpro-pl-generate-hash' ),
						)
					)
				),
				'before'
			);
		}

		/**
		 * Processes the license key depending on which button was pressed.
		 *
		 * @return void
		 */
		public function save_settings() {
			$license_key = isset( $_POST['pmpro_plugin_licensing_license_key'] ) ? sanitize_text_field( $_POST['pmpro_plugin_licensing_license_key'] ) : '';

			// Which button was pressed?
			$submit = isset( $_POST['submit'] ) ? sanitize_text_field( wp_unslash( $_POST['submit'] ) ) : '';
			if ( $submit === 'deactivate-license' ) {
				PMPRO_PL_Activator::get_instance()->deactivate_license( $license_key );
				return;
			}

			if ( $submit === 'delete-license-key' ) {
				$license_key = '';
			}

			// User pressed Save Changes button.
			$activator      = PMPRO_PL_Activator::get_instance();
			$license_status = $activator->activate_license( $license_key );
			update_option( pmpro_plugin_licensing_get_license_key_option_name(), $license_key );
		}

		public function settings_page() {
			if ( ! defined( 'PMPRO_VERSION' ) ) {
				wp_die( esc_html__( 'Paid Memberships Pro is not active.', 'pmpro-plugin-licensing' ) );
			}
			// Check nonce for saving settings.
			if ( ! empty( $_POST['submit'] ) && ( empty( $_POST['pmpro_pl_settings_nonce'] ) || ! check_admin_referer( 'save_pl_settings', 'pmpro_pl_settings_nonce' ) ) ) {
				wp_die( esc_html__( 'Please try again.', 'pmpro-plugin-licensing' ) );
			}

			// Check if the user has the required capability.
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Save settings if form is submitted.
			if ( isset( $_POST['submit'] ) ) {
				$this->save_settings();
			}

			// Get the current license key.
			$license_key = pmpro_plugin_licensing_get_license_key();

			wp_enqueue_style( 'pmpro-pl-settings' );

			// Render the settings page.
			?><div class="pmpro_banner">
			<div class="pmpro_banner_wrapper">
				<img class="pmpro-pl-icon" src="<?php echo esc_url( plugins_url( 'assets/icon.png', PMPRO_PLUGIN_LICENSING_PATH ) ); ?>" width="50" alt="" />
				<h1>Plugin&nbsp;Licensing</h1>
				<span class="pmpro_version">v<?php echo esc_html( PMPRO_PLUGIN_LICENSING_VERSION ); ?></span>
				for&nbsp;Paid&nbsp;Memberships&nbsp;Pro
				<div class="pmpro_meta">
					<a href="https://breakfastco.xyz" target="_blank"><?php esc_html_e( 'Documentation', 'pmpro-plugin-licensing' ); ?></a>
				</div>				
			</div> <!-- end pmpro_banner_wrapper -->		
		</div>
		<div class="wrap pmpro_admin pmpro_admin-pmpro-membershiplevels">
		<form action="" method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'save_pl_settings', 'pmpro_pl_settings_nonce' ); ?>
		<hr class="wp-header-end">
		<h1><?php esc_html_e( 'Settings', 'pmpro-plugin-licensing' ); ?></h1>
		<div id="" class="pmpro_section" data-visibility="shown" data-activated="true">
			<div class="pmpro_section_toggle">
				<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
					<span class="dashicons dashicons-arrow-up-alt2"></span>
					<?php esc_html_e( 'License Key', 'pmpro-plugin-licensing' ); ?>
				</button>
			</div>
			<div class="pmpro_section_inside">
				<p>
				<?php
				printf(
					/* translators: 1. Opening anchor tag <a>. 2. Closing anchor tag </a>. */
					esc_html__( 'Enter your license key and press the Save Changes button to activate this site. Only sites with a valid license key from %1$sBreakfast%2$s will receive updates.', 'pmpro-plugin-licensing' ),
					'<a href="https://breakfastco.xyz">',
					'</a>'
				);
				?>
				</p>
				<table class="form-table">
				<tbody>                
					<tr>
						<th scope="row" valign="top">
							<label for="pmpro_plugin_licensing_license_key"><?php esc_html_e( 'License Key', 'pmpro-plugin-licensing' ); ?></label>
						</th>
						<td>
							<?php
							// License key field(s).
							// Does the user have an active license key?
							$license_key    = pmpro_plugin_licensing_get_license_key();
							$activator      = PMPRO_PL_Activator::get_instance();
							$license_status = $activator->get_status( $license_key );

							if ( empty( $license_key ) ){
								// No. Show input field.
								?>
								<input type="text" id="pmpro_plugin_licensing_license_key" name="pmpro_plugin_licensing_license_key" value="<?php echo esc_attr( $license_key ); ?>" class="regular-text">
								<?php
							} else {
								// Have a license. Show an obfuscated key and the license status.
								// Stash the license key in a hidden field so it can be submitted.
								printf(
									'<input type="text" class="regular-text code" style="letter-spacing: 2px;" disabled="disabled" value="%s" />'
									. ' <span class="license-status license-status-%s" title="%s">%s</span>'
									. '<input type="hidden" name="pmpro_plugin_licensing_license_key" value="%s" />',
									esc_html( $this->obfuscate_license_key( $license_key ) ),
									esc_attr( $license_status ),
									esc_attr( __( 'License Status', 'pmpro-plugin-licensing' ) ),
									esc_html( ucwords( $license_status ?? '' ) ),
									esc_html( $license_key )
								);
							}

							// Is the license active? Show Deactivate button.
							// If not active, show Delete License Key button. Could be expired.
							echo '<div class="license-key-action-buttons"><p>';
							if ( 'active' === $license_status ) {
								// Show Deactivate button.
								printf(
									'<button type="submit" name="submit" value="deactivate-license" id="deactivate-license" class="button button-secondary">%s</button>',
									esc_html__( 'Deactivate License', 'pmpro-plugin-licensing' )
								);
							} elseif ( ! empty( $license_key ) ) {
								// Show Delete License Key button.
								printf(
									'<button type="submit" name="submit" value="delete-license-key" id="delete-license-key" class="button button-secondary">%s</button>',
									esc_html__( 'Delete License Key', 'pmpro-plugin-licensing' )
								);
							}

							if ( 'expired' === $license_status ) {
								printf(
									' &nbsp;<a href="%s">%s</a>',
									esc_url( PMPRO_PL_Updater::get_store_url( PMPRO_PLUGIN_LICENSING_STORE_URL . '/membership-account/' ) ),
									esc_html__( 'Renew', 'pmpro-plugin-licensing' )
								);
							}
							echo '</p></div>';
							?>
						</td>
					</tr>
				</tbody>
				</table>
				<p class="submit"><input name="submit" type="submit" class="button-primary" value="<?php esc_html_e( 'Save Changes', 'pmpro-plugin-licensing' ); ?>"></p>
			</div> <!-- end pmpro_section_inside -->
		</div> <!-- end pmpro_section -->
	</form>
		</div>
			<?php
		}
	}
	new PMPRO_PL_Settings();
}
