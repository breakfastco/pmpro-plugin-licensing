<?php
/**
 * PMPRO Plugin Licensing API functions in the global namespace.
 *
 * @package pmpro-plugin-licensing
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the user ID for a given license key.
 *
 * @param  string $license_key The license key to search for.
 * @return int
 */
function pmpro_pl_get_user_id_from_license_key( $license_key ) {
	if ( empty( $license_key ) ) {
		return false;
	}

	// Use the license key to find the user.
	$user_query = new WP_User_Query(
		array(
			'meta_key'   => 'pmpro_plugin_licensing_license_key',
			'meta_value' => $license_key,
		)
	);
	if ( empty( $user_query->results ) ) {
		return false;
	}
	return $user_query->results[0]->ID;
}

/**
 * Determines whether a customer license key is valid for the file she is trying
 * to download.
 *
 * @param  array $args Array with keys 'license_key', 'user_id', or 'attachment_id'. License key and attachment ID are required.
 * @return bool
 */
function pmpro_pl_customer_license_is_valid( $args ) {
	$license_key   = $args['license_key'] ?? '';
	$user_id       = $args['user_id'] ?? 0;
	$attachment_id = $args['attachment_id'] ?? 0;

	if ( empty( $license_key ) && empty( $user_id ) ) {
		// Not enough info.
		return false;
	}

	if ( empty( $attachment_id ) ) {
		return false;
	}

	if ( empty( $user_id ) ) {
		$user_id = pmpro_pl_get_user_id_from_license_key( $license_key );
		if ( empty( $user_id ) ) {
			return false;
		}
	}

	// Get active memberships for this user.
	if ( ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
		return false;
	}

	$levels = pmpro_getMembershipLevelsForUser( $user_id );
	if ( empty( $levels ) ) {
		return false;
	}

	// Examine each of this user's memberships.
	foreach ( $levels as $level ) {
		// Is this membership expired?
		if ( null !== $level->enddate && (int) $level->enddate < time() ) {
			// Yes.
			continue;
		}

		// Does this level contain the attachment?
		$level_meta_files = get_pmpro_membership_level_meta( $level->id, 'plugin_licensing_files', true );
		if ( empty( $level_meta_files ) ) {
			continue;
		}
		if ( in_array( $attachment_id, $level_meta_files, true ) ) {
			// Yes.
			return true;
		}
	}

	// This user has memberships, but none of them have this file attached.
	return false;
}

/**
 * Returns the saved license key for this plugin.
 *
 * @return string
 */
function pmpro_plugin_licensing_get_license_key() {
	/**
	 * Your customers' license keys are stored in user meta. The key stored in
	 * this option is issued by breakfastco.xyz when you buy this plugin.
	 */
	return get_option( pmpro_plugin_licensing_get_license_key_option_name() );
}

/**
 * Returns the name of the option that stores a license key for this plugin
 * provided by breakfastco.xyz.
 *
 * @return string
 */
function pmpro_plugin_licensing_get_license_key_option_name() {
	/**
	 * This plugin stores its license key from breakfastco.xyz
	 * in an option so admins can buy their own products from
	 * their own stores using the license key stored in
	 * user meta.
	 */
	return 'pmpro_plugin_licensing_license_key';
}
