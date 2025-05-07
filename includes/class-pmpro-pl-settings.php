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
			// Only do this once.
			if ( has_filter( 'admin_menu', array( $this, 'add_settings_tab' ) ) ) {
				return;
			}

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

		public function extract_readme_data_during_uploads( $level_id ) {
			if ( ! isset( $_POST['plugin_licensing_files'] ) ) {
				return;
			}
			$attachment_ids = array_map( 'intval', (array) $_POST['plugin_licensing_files'] );
			foreach ( $attachment_ids as $post_id ) {

				// Is it a zip file?
				$mime_type = get_post_mime_type( $post_id );
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
				foreach( $temp_plugins as $plugin_basename => $plugin_data ) {

					// Save this plugin's version number in the attachment post meta.
					if ( ! empty( $plugin_data['Version'] ) ) {
						update_post_meta( $post_id, '_pmpro_pl_version', $plugin_data['Version'] );
					}

					// Generate a hash checksum for this attachment that can represent it in URLs.
					update_post_meta( $post_id, '_pmpro_pl_hash', md5( wp_json_encode( $plugin_data ) ) );

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

		public function maybe_change_upload_path( $uploads ) {
			if ( ! isset( $_POST['is_pmpro_pl'] ) ) {
				return $uploads;
			}

			$uploads['path'] = self::upload_path( $uploads );
			$uploads['url'] .= '/pmpro-plugin-licensing';
			return $uploads;
		}

		/**
		 * Saves list of files in meta when membership levels are saved.
		 *
		 * @param  int $level_id
		 * @return void
		 */
		public function membership_level_save_files( $level_id ) {
			// TODO does this need a nonce check?
			if ( isset( $_POST['plugin_licensing_files'] ) ) {
				$attachment_ids = array_map( 'intval', (array) $_POST['plugin_licensing_files'] );
				update_pmpro_membership_level_meta( $level_id, 'plugin_licensing_files', $attachment_ids );
			} else {
				// User erased all downloads from this level.
				delete_pmpro_membership_level_meta( $level_id, 'plugin_licensing_files' );
			}
		}

		/**
		 * Adds controls while editing a Membership level to add and remove
		 * files.
		 *
		 * @param  mixed $level
		 * @return void
		 */
		public function membership_level_add_files_controls( $level ) {
			$level_id       = $level->id;
			$attachment_ids = get_pmpro_membership_level_meta( $level_id, 'plugin_licensing_files', true );
			if ( ! is_array( $attachment_ids ) ) {
				$attachment_ids = array();
			}

			wp_enqueue_style( 'pmpro-pl-settings' );

			?>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row" valign="top">
							<label>Files</label></th>
						<td>
							<p class="submit"><button type="button" class="button" id="add-file-attachment">Add File</button></p>
							<table id="pmpro-file-attachments-list" class="wp-list-table widefat fixed striped table-view-list">
								<thead>
									<tr>
										<th scope="row" valign="top"><label><?php esc_html_e('ID', 'pmpro-plugin-licensing' );?></label></th>
										<th scope="row" valign="top"><label><?php esc_html_e('File', 'pmpro-plugin-licensing' );?></label></th>
										<th>Edit</th>
										<th>Remove</th>
									</tr>
								</thead>
								<tbody>
							<?php
							foreach ( $attachment_ids as $aid ) :
								$file = get_post( $aid );
								?>
									<tr data-id="<?php echo esc_attr( $aid ); ?>">
										<td><p class="description"><?php echo esc_html( $aid ); ?></p></td>
										<td><a href="<?php echo esc_url( wp_get_attachment_url( $aid ) ); ?>"><b><?php echo esc_html( basename( $file->guid ) ); ?></b></a></td>
										<td>
											<?php // Edit link. ?>
											<a href="<?php echo esc_url( get_edit_post_link( $aid ) ?? '' ); ?>" target="_blank"><?php esc_html_e( 'Edit', 'pmpro-plugin-licensing' ); ?></a>
										</td>
										<td>
											<?php // Remove button. ?>
											<button type="button" class="button remove-file-attachment">Remove</button>
											<input type="hidden" name="plugin_licensing_files[]" value="<?php echo esc_attr( $aid ); ?>">
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
			<script>
			jQuery(document).ready(function($){
				var file_frame;

				$('#add-file-attachment').on('click', function(e) {
					e.preventDefault();

					// If the media frame already exists, reopen it.
					if (file_frame) {
						file_frame.open();
						return;
					}

					// Create the media frame.
					file_frame = wp.media({
						title: 'Select or Upload a .zip file',
						library: {
							type: 'application/zip',
						},
						button: {
							text: 'Attach this file'
						},
						frame: 'select',
						multiple: true
					});
					// Identify as uploaded with Plugin Licensing.
					file_frame.uploader.options.uploader.params.is_pmpro_pl = true;

					// When a file is selected, run a callback.
					file_frame.on('select', function() {
						var attachment = file_frame.state().get('selection').first().toJSON();
						var tableRow = '<tr data-id="' + attachment.id + '">'
							+ '<td><p class="description">' + attachment.id + '</p></td>'
							+ '<td><a href="' + attachment.url + '"><b>' + attachment.filename + '</b></a></td>'
							+ '<td>'
							+ '<a href="' + attachment.editLink + '" target="_blank">Edit</a>'
							+ '</td>'
							+ '<td>'
							+ '<button type="button" class="button remove-file-attachment">Remove</button>'
							+ '<input type="hidden" name="plugin_licensing_files[]" value="' + attachment.id + '">'
							+ '</td>'
							+ '</tr>';
						$('#pmpro-file-attachments-list tbody').append(tableRow);
					});

					// Finally, open the modal
					file_frame.open();
				});

				$('#pmpro-file-attachments-list').on('click', '.remove-file-attachment', function(){
					$(this).closest('tr').remove();
				});
			});
			</script>
			<?php
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
				PMPRO_PLUGIN_LICENSING_VERISON
			);
		}

		/**
		 * Save the settings.
		 *
		 * @return void
		 */
		public function save_settings() {
			$license_key = isset( $_POST['pmpro_plugin_licensing_license_key'] ) ? sanitize_text_field( $_POST['pmpro_plugin_licensing_license_key'] ) : '';
			update_option( pmpro_plugin_licensing_get_license_key_option_name(), $license_key );
		}

		public function settings_page() {
			if ( ! defined( 'PMPRO_VERSION' ) ) {
				wp_die( esc_html__( 'Paid Memberships Pro is not active.', 'pmpro-plugin-licensing' ) );
			}
			// Check nonce for saving settings.
			if ( ! empty( $_POST['pmpro_plugin_licensing_save'] ) && ( empty( $_POST['pmpro_pl_settings_nonce'] ) || ! check_admin_referer( 'save_pl_settings', 'pmpro_pl_settings_nonce' ) ) ) {
				wp_die( esc_html__( 'Please try again.', 'pmpro-plugin-licensing' ) );
			}

			// Check if the user has the required capability.
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// Save settings if form is submitted.
			if ( isset( $_POST['pmpro_plugin_licensing_save'] ) ) {
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
				<span class="pmpro_version">v0.1.1</span>
				for&nbsp;Paid&nbsp;Memberships&nbsp;Pro
				<div class="pmpro_meta">
					<a href="https://breakfastco.xyz" target="_blank">Documentation</a>
				</div>				
			</div> <!-- end pmpro_banner_wrapper -->		
		</div>
		<div class="wrap pmpro_admin pmpro_admin-pmpro-membershiplevels">
		<form action="" method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'save_pl_settings', 'pmpro_pl_settings_nonce' ); ?>
		<hr class="wp-header-end">
		<h1>Settings</h1>
		<div id="" class="pmpro_section" data-visibility="shown" data-activated="true">
			<div class="pmpro_section_toggle">
				<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
					<span class="dashicons dashicons-arrow-up-alt2"></span>
					License Key
				</button>
			</div>
			<div class="pmpro_section_inside">
				<p>Save the license key you purchased from <a href="https://breakfastco.xyz">Breakfast</a> for <b>Plugin Licensing for Paid Memberships Pro</b>.</p>
				<table class="form-table">
				<tbody>                
					<tr>
						<th scope="row" valign="top">
							<label for="pmpro_plugin_licensing_license_key">License Key</label>
						</th>
						<td>
							<input type="text" id="pmpro_plugin_licensing_license_key" name="pmpro_plugin_licensing_license_key" value="<?php echo esc_attr( $license_key ); ?>" class="regular-text">
						</td>
					</tr>
				</tbody>
				</table>
				<p class="submit"><input name="pmpro_plugin_licensing_save" type="submit" class="button-primary" value="Save All Settings"></p>
			</div> <!-- end pmpro_section_inside -->
		</div> <!-- end pmpro_section -->
	</form>
		</div>
			<?php
		}

		public static function upload_path( $uploads = null ) {
			if ( null === $uploads ) {
				$uploads = wp_upload_dir();
			}
			return $uploads['path'] .= '/pmpro-plugin-licensing';
		}
	}
	new PMPRO_PL_Settings();
}
