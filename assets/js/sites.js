/**
 * Scripts that power the [pmpro_pl_sites] shortcode.
 *
 * @package pmpro-plugin-licensing
 * @since 0.1.0
 */

const { __, sprintf } = wp.i18n;

(function($) {
	'use strict';

	/**
	 * Initialize the sites management functionality.
	 */
	function initSitesManagement() {
		// Add site button click handler.
		$(document).on('click', '.pmpro_btn-add-site', function(e) {
			e.preventDefault();
			addSite();
		});

		// Remove site button click handler - using event delegation for dynamically added elements.
		$(document).on('click', '.pmpro_btn-remove-site', function(e) {
			e.preventDefault();
			const siteUrl = $(this).data('site-url');
			removeSite(siteUrl);
		});

		// Enter key handler for the input field.
		$(document).on('keypress', '#new_site_url', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				addSite();
			}
		});
	}

	/**
	 * Add a new site to the user's license.
	 */
	function addSite() {
		const siteUrl = $('#new_site_url').val().trim();
		
		if (!siteUrl) {
			showMessage( __( 'Please enter a valid site URL.', 'pmpro-plugin-licensing' ), 'error' );
			return;
		}

		// Validate URL format.
		if (!isValidUrl(siteUrl)) {
			showMessage( __( 'Please enter a valid URL (e.g., https://example.com).', 'pmpro-plugin-licensing' ), 'error' );
			return;
		}

		// Check if we're at the limit.
		if ( ! pmpro_pl_sites.can_manage_options && pmpro_pl_sites.limit > 0 && pmpro_pl_sites.current >= pmpro_pl_sites.limit) {
			showMessage( __( 'You have reached the maximum number of active sites for this plugin.', 'pmpro-plugin-licensing' ), 'error' );
			return;
		}

		// Show loading state.
		const $button = $('.pmpro_btn-add-site');
		const originalText = $button.text();
		$button.prop('disabled', true).text( __( 'Adding...', 'pmpro-plugin-licensing' ) );

		// Make AJAX request.
		$.ajax({
			url: pmpro_pl_sites.ajaxurl,
			type: 'POST',
			data: {
				action: 'pmpro_pl_add_site',
				nonce: pmpro_pl_sites.nonce,
				hash: pmpro_pl_sites.hash,
				site_url: siteUrl,
				user_id: pmpro_pl_sites.user_id
			},
			success: function(response) {
				if (response.success) {
					// Add the site to the list.
					addSiteToList(siteUrl);
					
					// Clear the input.
					$('#new_site_url').val('');
					
					// Update the count.
					pmpro_pl_sites.current++;
					updateSiteCount();
					
					// Check if we've reached the limit.
					if ( ! pmpro_pl_sites.can_manage_options
						&& pmpro_pl_sites.limit > 0
						&& pmpro_pl_sites.current >= pmpro_pl_sites.limit ) {
						hideAddSiteForm();
					}
					
					showMessage( __( 'Site added successfully!', 'pmpro-plugin-licensing' ), 'success' );
				} else {
					showMessage(response.data.message || __( 'Failed to add site. Please try again.', 'pmpro-plugin-licensing' ), 'error');
				}
			},
			error: function() {
				showMessage( __( 'An error occurred. Please try again.', 'pmpro-plugin-licensing' ), 'error' );
			},
			complete: function() {
				$button.prop('disabled', false).text(originalText);
			}
		});
	}

	/**
	 * Remove a site from the user's license.
	 */
	function removeSite(siteUrl) {
		if (!confirm( __( 'Are you sure you want to remove this site from your license?', 'pmpro-plugin-licensing' ) )) {
			return;
		}

		// Show loading state.
		const $button = $(`.pmpro_btn-remove-site[data-site-url="${siteUrl}"]`);
		const originalText = $button.text();
		$button.prop('disabled', true).text( __( 'Removing...', 'pmpro-plugin-licensing' ) );

		// Make AJAX request.
		$.ajax({
			url: pmpro_pl_sites.ajaxurl,
			type: 'POST',
			data: {
				action: 'pmpro_pl_remove_site',
				nonce: pmpro_pl_sites.nonce,
				hash: pmpro_pl_sites.hash,
				site_url: siteUrl,
				user_id: pmpro_pl_sites.user_id
			},
			success: function(response) {
				if (response.success) {
					// Remove the site from the list.
					removeSiteFromList(siteUrl);
					
					// Update the count.
					pmpro_pl_sites.current--;
					updateSiteCount();
					
					// Show the add form if we were at the limit.
					if (pmpro_pl_sites.limit === 0 || pmpro_pl_sites.current < pmpro_pl_sites.limit) {
						showAddSiteForm();
					}
					
					showMessage( __( 'Site removed successfully!', 'pmpro-plugin-licensing' ), 'success' );
				} else {
					showMessage(response.data.message || __( 'Failed to remove site. Please try again.', 'pmpro-plugin-licensing' ), 'error');
				}
			},
			error: function() {
				showMessage( __( 'An error occurred. Please try again.', 'pmpro-plugin-licensing' ), 'error' );
			},
			complete: function() {
				$button.prop('disabled', false).text(originalText);
			}
		});
	}

	/**
	 * Add a site to the visual list.
	 */
	function addSiteToList(siteUrl) {
		const $sitesTable = $('.pmpro-pl-sites');
		const $noSitesMessage = $('.pmpro_pl_no_sites');
		
		// Hide the "no sites" message if it exists.
		$noSitesMessage.hide();
		
		// Show the table if it was hidden.
		$sitesTable.show();
		
		// Create the new site row with proper link structure.
		const siteRow = `
			<tr class="pmpro_pl_site_item" data-site-url="${siteUrl}">
				<td class="pmpro_pl_site_url">
					<a href="${siteUrl}" target="_blank">${siteUrl}</a>
				</td>
				<td>
					<button type="button" class="pmpro_btn pmpro_btn-remove-site" data-site-url="${siteUrl}">
						${ __( 'Remove', 'pmpro-plugin-licensing' ) }
					</button>
				</td>
			</tr>
		`;
		
		// Add to the table.
		$sitesTable.find('tbody').append(siteRow);
	}

	/**
	 * Remove a site from the visual list.
	 */
	function removeSiteFromList(siteUrl) {
		const $siteRow = $(`.pmpro_pl_site_item[data-site-url="${siteUrl}"]`);
		$siteRow.remove();
		
		// Show "no sites" message if the table is empty.
		if ($('.pmpro_pl_site_item').length === 0) {
			$('.pmpro_pl_no_sites').show();
			$('.pmpro-pl-sites').hide();
		}
		
		// Show the add site form if we're under the limit.
		if (pmpro_pl_sites.limit === 0 || pmpro_pl_sites.current < pmpro_pl_sites.limit) {
			showAddSiteForm();
		}
	}

	/**
	 * Update the site count display.
	 */
	function updateSiteCount() {
		const $countElement = $('.pmpro_pl_sites_count');
		if ($countElement.length > 0 && pmpro_pl_sites.limit > 0) {
			$countElement.text(`${pmpro_pl_sites.current} ${ __( 'of', 'pmpro-plugin-licensing' ) } ${pmpro_pl_sites.limit}`);
		}
	}

	/**
	 * Hide the add site form when limit is reached.
	 */
	function hideAddSiteForm() {
		$('.pmpro_pl_add_site_form').hide();
		$('.pmpro_pl_limit_reached').show();
	}

	/**
	 * Show the add site form when under the limit.
	 */
	function showAddSiteForm() {
		$('.pmpro_pl_add_site_form').show();
		$('.pmpro_pl_limit_reached').hide();
	}

	/**
	 * Validate URL format.
	 */
	function isValidUrl(string) {
		try {
			new URL(string);
			return true;
		} catch (_) {
			return false;
		}
	}

	/**
	 * Show a message to the user.
	 */
	function showMessage(message, type) {
		// Remove any existing messages.
		$('.pmpro_pl_message').remove();
		
		// Create the message element.
		const messageClass = type === 'error' ? 'pmpro_error' : 'pmpro_success';
		const messageHtml = `
			<div class="pmpro_message ${messageClass} pmpro_pl_message">
				<p>${message}</p>
			</div>
		`;
		
		// Insert the message at the top of the section.
		$('#pmpro_pl_sites').prepend(messageHtml);
		
		// Auto-hide after 5 seconds.
		setTimeout(function() {
			$('.pmpro_pl_message').fadeOut();
		}, 5000);
	}

	// Initialize when document is ready.
	$(document).ready(function() {
		initSitesManagement();
	});

})(jQuery);