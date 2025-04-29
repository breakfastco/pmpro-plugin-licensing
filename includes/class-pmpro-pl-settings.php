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
	 * Class PMPRO_Plugin_Licensing_Settings
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
			add_action( 'admin_menu', array( $this, 'add_settings_tab' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
		}

		public function add_settings_tab() {
			add_submenu_page(
				'pmpro-dashboard', // parent slug.
				__( 'Plugin Licensing', 'pmpro-plugin-licensing' ), // page title.
				__( 'Plugin Licensing', 'pmpro-plugin-licensing' ), // menu title.
				'manage_options', // capability.
				'pmpro_plugin_licensing', // menu slug.
				array( $this, 'settings_page' ) // function to call.
			);
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

		public function settings_page() {
			// Check nonce for saving settings.
			if ( ! empty( $_POST['pmpro_plugin_licensing_save'] ) && ( empty($_POST['pmpro_pl_settings_nonce'] ) || ! check_admin_referer( 'save_pl_settings', 'pmpro_pl_settings_nonce' ) ) ) {
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
		<?php wp_nonce_field( 'save_pl_settings', 'pmpro_pl_settings_nonce'); ?>
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
		</div><?php
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
	}
	new PMPRO_PL_Settings();
}
