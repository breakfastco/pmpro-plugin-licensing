<?php
/**
 * Display a list of active sites for a specific plugin hash.
 * Allows users to add and remove sites from their license.
 *
 * @package pmpro-plugin-licensing
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode to display and manage active sites for a plugin.
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $content Shortcode content.
 * @param string $code     Shortcode name.
 * @return string HTML output.
 */
function shortcode_pmpro_pl_sites( $atts, $content = null, $code = '' ) {
	// Was a hash passed?
	if ( empty( $atts['hash'] ) ) {
		// No. Is there a hash in the URL?
		$hash = isset( $_GET['hash'] ) ? sanitize_text_field( wp_unslash( $_GET['hash'] ) ) : '';
		if ( empty( $hash ) ) {
			// No.
			return '';
		}
	} else {
		$hash = sanitize_text_field( $atts['hash'] );
	}
	$user = wp_get_current_user();

	if ( ! $user->exists() ) {
		return '<div class="pmpro_message pmpro_error">' . esc_html__( 'Please log in to manage your sites.', 'pmpro-plugin-licensing' ) . '</div>';
	}

	// Get the user's active sites for this hash.
	$active_sites = get_user_meta( $user->ID, 'pmpro_plugin_licensing_active_sites', true );
	if ( ! is_array( $active_sites ) ) {
		$active_sites = array();
	}

	$sites_for_hash = isset( $active_sites[ $hash ] ) ? $active_sites[ $hash ] : array();

	// Get the activation limit for this hash.
	$attachment_id    = PMPRO_PL_Download_Links::find_attachment_id( $hash );
	$activation_limit = 0;
	if ( $attachment_id ) {
		$activation_limit = (int) get_post_meta( $attachment_id, '_pmpro_pl_activation_limit', true );
	}

	// Check if user has reached the limit.
	$current_count = count( $sites_for_hash );
	$can_add_sites = ( 0 === $activation_limit ) || ( $current_count < $activation_limit ) || current_user_can( 'manage_options' );

	$min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

	// Enqueue the CSS and JavaScript files.
	wp_enqueue_style(
		'pmpro-pl-sites',
		plugins_url( "assets/css/sites{$min}.css", PMPRO_PLUGIN_LICENSING_PATH ),
		array(),
		PMPRO_PLUGIN_LICENSING_VERSION
	);

	wp_enqueue_script(
		'pmpro-pl-sites',
		plugins_url( "assets/js/sites{$min}.js", PMPRO_PLUGIN_LICENSING_PATH ),
		array( 'jquery' ),
		PMPRO_PLUGIN_LICENSING_VERSION,
		true
	);

	// Pass data to the script via an inline JSON config variable.
	$data = array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'pmpro_pl_sites_nonce' ),
		'hash'    => $hash,
		'user_id' => $user->ID,
		'can_manage_options' => current_user_can( 'manage_options' ),
		'limit'   => $activation_limit,
		'current' => $current_count,
	);

	wp_add_inline_script(
		'pmpro-pl-sites',
		'const pmpro_pl_sites = ' . wp_json_encode( $data ) . ';',
		'before'
	);

	ob_start();
	?>
	<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro' ) ); ?>">
		<section id="pmpro_pl_sites" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_section', 'pmpro_pl_sites' ) ); ?>">
			<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_section_title pmpro_font-x-large' ) ); ?>">
				<?php echo esc_html( get_post_meta( $attachment_id, '_pmpro_pl_name', true ) ); ?>
			</h2>
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_section_content' ) ); ?>">
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card' ) ); ?>">
					<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
						<fieldset id="pmpro_pl_sites-list" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fieldset', 'pmpro_pl_sites-list' ) ); ?>">
							<p>	<?php if ( $activation_limit > 0 ) : ?>
										<?php
										printf(
											/* translators: 1. Opening <span> element. 2. Current number of sites. 3. Maximum number of sites. 4. Closing </span> element. */
											esc_html__( 'This plugin is active on %1$s%2$d of %3$d%4$s sites.', 'pmpro-plugin-licensing' ),
											'<span class="pmpro_pl_sites_count">',
											esc_html( $current_count ),
											esc_html( $activation_limit ),
											'</span>',
										);
										?>										
								<?php endif; ?>
								<?php if ( ! $can_add_sites ) : ?>
									<?php esc_html_e( 'You have reached the maximum number of active sites for this plugin.', 'pmpro-plugin-licensing' ); ?>
								<?php endif; ?>
							</p>

								<table class="pmpro-pl-sites <?php echo esc_attr( pmpro_get_element_class( 'pmpro_table' ) ); ?>">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Site', 'pmpro-plugin-licensing' ); ?></th>
											<th><?php esc_html_e( 'Actions', 'pmpro-plugin-licensing' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $sites_for_hash as $index => $site_url ) : ?>
											<tr class="pmpro_pl_site_item" data-site-url="<?php echo esc_attr( $site_url ); ?>">
												<td class="pmpro_pl_site_url">
													<a href="<?php echo esc_url( $site_url ); ?>" target="_blank"><?php echo esc_html( $site_url ); ?></a>
												</td>
												<td>
													<button type="button" class="pmpro_btn pmpro_btn-remove-site" data-site-url="<?php echo esc_attr( $site_url ); ?>">
														<?php esc_html_e( 'Remove', 'pmpro-plugin-licensing' ); ?>
													</button>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>

							<?php if ( $can_add_sites ) : ?>
								<div class="pmpro_pl_add_site_form">
									<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_field' ) ); ?>">
										<label for="new_site_url" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_label' ) ); ?>">
											<?php esc_html_e( 'Add Site', 'pmpro-plugin-licensing' ); ?>
										</label>
										<input type="url" name="new_site_url" id="new_site_url" 
											   placeholder="<?php esc_attr_e( 'https://example.com', 'pmpro-plugin-licensing' ); ?>"
											   class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-url', 'new_site_url' ) ); ?>" />
										<button type="button" class="pmpro_btn pmpro_btn-add-site">
											<?php esc_html_e( 'Add Site', 'pmpro-plugin-licensing' ); ?>
										</button>
									</div>
								</div>
							<?php endif; ?>
						</fieldset>
					</div>
					<div class="pmpro_card_actions">
						<span class="pmpro_card_action"><a href="<?php echo esc_url( site_url( 'membership-account/' ) ); ?>" aria-label="Go to Membership Account">Go to Membership Account</a></span>
					</div>
				</div>
			</div>
		</section>
	</div>
	<?php
	$html = ob_get_contents();
	ob_end_clean();

	return $html;
}
add_shortcode( 'pmpro_pl_sites', 'shortcode_pmpro_pl_sites' );