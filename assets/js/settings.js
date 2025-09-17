/**
 * This script powers the file UI controls while editing Membership Levels.
 */
const { __, sprintf } = wp.i18n;
jQuery(document).ready(function($){

	// One Add File button.
	$('#file-attachment-add').on('click', function(e) {
		/**
		 * Create a row with a hash but no file yet. The user needs
		 * to put the hash into their plugin and never change it so
		 * connections to the store find the right file.
		 */
		// Go server-side to generate the hash to make sure it's not in use.
		var hashData = {
			action: 'pmpro_pl_generate_hash',
			type: 'file', // File or license? They're both unique hashes.
			_ajax_nonce: pmpro_pl_settings.nonce,
		};

		$.post( pmpro_pl_settings.ajaxurl, hashData )
			.done( function( response ) {
				if ( response ) {
					var tableRow = '<tr>'
						+ '<td id="file_name_' + response.data.hash + '" class="file-name">'
							+ '<button type="button" class="button attach-file-to-row" data-hash="' + response.data.hash + '">' + __( 'Attach plugin .zip', 'pmpro-plugin-licensing' ) + '</button>'
						+ '</td>'
						+ '<td>'
							+ '<input type="text" readonly="readonly" class="file-hash" id="plugin_licensing_hash_' + response.data.hash + '" value="' + response.data.hash + '" />'
							+ '\n<button type="button" class="button copy-hash" onclick="pmproPlCopyClipboard(\'plugin_licensing_hash_' + response.data.hash + '\');">' + __( 'Copy hash', 'pmpro-plugin-licensing' ) + '</button>'
						+ '</td>'
						+ '<td id="file_version_' + response.data.hash + '"></td>' // Version.
						+ '<td>' // Activation limit.
							+ '<input type="number" class="file-activation-limit" title="' + __( 'The number of sites this plugin can be activated. Leave empty for unlimited.', 'pmpro-plugin-licensing' ) + '" name="plugin_licensing_limits[' + response.data.hash + ']"  value="" />'
						+ '</td>'
						+ '<td>'
							+ '<button type="button" class="button remove-file-attachment">' + __( 'Remove', 'pmpro-plugin-licensing' ) + '</button>'
							+ '<input type="hidden" name="plugin_licensing_files[' + response.data.hash + ']" id="plugin_licensing_file_' + response.data.hash + '" value="">'
						+ '</td>'
						+ '</tr>';
					$('#pmpro-file-attachments-list tbody').append(tableRow);
					// Add click handler to new buttons.
					pmproPlAddClickHanders();

				}
			} )
			.fail( function( response ) {
				console.log( response );
			} );

	});

	// Add click handlers in files table saved & loaded with Membership Level.
	pmproPlAddClickHanders();
});

function pmproPlCopyClipboard( elementID ) {
	document.querySelector('#'+elementID).select();
	document.execCommand('copy');
}

var pmpro_pl_file_frame;
function pmproPlAddClickHanders() {
	jQuery('.attach-file-to-row').on('click', function(e) {
		var hash = jQuery(this).data('hash');
		e.preventDefault();

		// Can't do this if they share a file frame variable.
		// // If the media frame already exists, reopen it.
		// if (pmpro_pl_file_frame) {
		// 	pmpro_pl_file_frame.open();
		// 	return;
		// }

		// Create the media frame.
		pmpro_pl_file_frame = wp.media({
			title: __( 'Select or Upload a .zip file', 'pmpro-plugin-licensing' ),
			library: {
				type: 'application/zip',
			},
			button: {
				text: __( 'Attach this file', 'pmpro-plugin-licensing' )
			},
			frame: 'select',
			multiple: true
		});
		// Identify as uploaded with Plugin Licensing.
		pmpro_pl_file_frame.uploader.options.uploader.params.is_pmpro_pl = true;

		// When a file is selected, run a callback.
		pmpro_pl_file_frame.on('select', function() {
			var attachment = pmpro_pl_file_frame.state().get('selection').first().toJSON();
			// Get meta data for the attachment that was just selected.
			var post = new wp.api.models.Media( { id: attachment.id } );
			post.fetch().done( () => {
				// Does this attachment already have a hash stored in post meta?
				if( '' !== post.attributes.meta.pmpro_pl_hash ) {
					// Yes. Tell the user we will adopt the previously created hash.
					alert( __( 'The file you have chosen already has a hash. Perhaps one was generated while attaching this file to another Membership Level. We will use the existing hash and replace the hash in this file\'s row in the Files table.', 'pmpro-plugin-licensing' ) );
					// Replace throughout the table row.
					jQuery('#file_name_' + hash).attr( 'id', 'file_name_' + post.attributes.meta.pmpro_pl_hash );
					jQuery('.attach-file-to-row[data-hash="' + hash + '"]').attr( 'data-hash', post.attributes.meta.pmpro_pl_hash );
					var hashInput = jQuery('#plugin_licensing_hash_' + hash);
					hashInput.attr( 'value', post.attributes.meta.pmpro_pl_hash );
					hashInput.attr( 'id', 'plugin_licensing_hash_' + post.attributes.meta.pmpro_pl_hash );
					hashInput.siblings('button').attr( 'onclick', 'pmproPlCopyClipboard(\'plugin_licensing_hash_' + post.attributes.meta.pmpro_pl_hash + '\');' );
					jQuery('input#plugin_licensing_file_' + hash).attr( 'name', 'plugin_licensing_files[' + post.attributes.meta.pmpro_pl_hash + ']' ).attr( 'id', 'plugin_licensing_file_' + post.attributes.meta.pmpro_pl_hash );
					hash = post.attributes.meta.pmpro_pl_hash;
				}

				// Populate the version number if we have it.
				var version = '' !== post.attributes.meta.pmpro_pl_version ? post.attributes.meta.pmpro_pl_version : '<i>' + __( 'Save Level to populate', 'pmpro-plugin-licensing' ) + '</i>';
				jQuery('#file_version_' + hash).html( version );
			} );
			// Replace attach button with the file name and a Replace button.
			jQuery('#file_name_' + hash ).html('<a href="' + attachment.url + '"><b>' + attachment.filename + '</b></a><button type="button" class="button file-attachment-replace" title="' + __( 'Replace the file associated with this hash', 'pmpro-plugin-licensing' ) + '">' + __( 'Replace', 'pmpro-plugin-licensing' ) + '</button>');
			// Add click handler to the new Replace button.
			jQuery('#file_name_' + hash + ' .file-attachment-replace').on('click', pmproPlReplaceFile );

			// Populate attachment ID into hidden form field.
			jQuery('#plugin_licensing_file_' + hash).val( attachment.id );
		});

		// Finally, open the modal
		pmpro_pl_file_frame.open();
	});

	// Add click handlers to many Replace buttons.
	jQuery('.file-attachment-replace').on('click', pmproPlReplaceFile );

	// Add click handlers to many Remove buttons.
	jQuery('#pmpro-file-attachments-list').on('click', '.remove-file-attachment', function(){
		jQuery(this).closest('tr').remove();
	});
}

function pmproPlReplaceFile(e) {
	/**
	 * Launch the media modal so the user can select a file to replace. The
	 * user is publishing an update to a plugin sold with this Membership Level.
	 */

	// Create the media frame.
	pmpro_pl_file_frame = wp.media({
		title: __( 'Select or Upload a .zip file', 'pmpro-plugin-licensing' ),
		library: {
			type: 'application/zip',
		},
		button: {
			text: __( 'Attach this file', 'pmpro-plugin-licensing' )
		},
		frame: 'select',
		multiple: true
	});
	// Identify as uploaded with Plugin Licensing.
	pmpro_pl_file_frame.uploader.options.uploader.params.is_pmpro_pl = true;

	var rowHash = e.target.parentNode.id.replace('file_name_', '');

	// When a file is selected, run a callback.
	pmpro_pl_file_frame.on('select', function() {
		var attachment = pmpro_pl_file_frame.state().get('selection').first().toJSON();
		// Does this attachment already have a hash stored in post meta?
		var post = new wp.api.models.Media( { id: attachment.id } );
		post.fetch().done( function( result ) {
			if( '' !== post.attributes.meta.pmpro_pl_hash ) {
				/**
				 * Yes. Tell the user the file already has a hash. Ask if we
				 * should discard the file's hash and assign this download's
				 * hash to the file.
				 */
				var discard = confirm( sprintf( __( 'The file you have chosen already has a hash. Changing this download\'s hash will break updates for anyone who already purchased this Membership Level. Discard this file\'s hash and assign %s?', 'pmpro-plugin-licensing' ), rowHash ) );
				if ( discard ) {
					// Change the hash in attachment post meta.
				} else {
					// Abort? Shopkeeper should never change the row hash.
				}
			}
		} );
		// Replace the old file name with the new file name. Keep the Replace button.
		jQuery('#file_name_' + rowHash + ' a' ).remove();
		jQuery('#file_name_' + rowHash ).prepend( '<a href="' + attachment.url + '"><b>' + attachment.filename + '</b></a>' );
		// Clear the version. It's a hidden meta key and not in post.
		jQuery('#file_version_' + rowHash).html( '' );
		// Populate attachment ID into hidden form field.
		jQuery('#plugin_licensing_file_' + rowHash).val( attachment.id );
	});

	// Finally, open the modal
	pmpro_pl_file_frame.open();
}