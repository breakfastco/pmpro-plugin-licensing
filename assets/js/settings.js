/**
 * This script powers the file UI controls while editing Membership Levels.
 */
jQuery(document).ready(function($){

	$('#add-file-attachment').on('click', function(e) {
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
					var tableRow = '<tr data-hash="' + response.data.hash + '">'
						+ '<td id="file_name_' + response.data.hash + '" class="file-name"><button type="button" class="button attach-file-to-row" data-hash="' + response.data.hash + '">Attach plugin .zip</button></td>'
						+ '<td>'
						+ '<input type="text" readonly="readonly" class="file-hash" id="plugin_licensing_hash_' + response.data.hash + '" value="' + response.data.hash + '" />'
						+ '<button type="button" class="button copy-hash" onclick="pmproPlCopyClipboard(\'plugin_licensing_hash_' + response.data.hash + '">Copy hash</button>'
						+ '</td>'
						+ '<td>'
						+ '<button type="button" class="button remove-file-attachment">Remove</button>'
						+ '<input type="hidden" name="plugin_licensing_files[' + response.data.hash + '] id="plugin_licensing_file_' + response.data.hash + '" value="">'
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

var file_frame;
function pmproPlAddClickHanders() {
	jQuery('.attach-file-to-row').on('click', function(e) {
		var hash = jQuery(this).data('hash');
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
			// Replace attach button with the file name.
			jQuery('#file_name_' + hash ).html('<a href="' + attachment.url + '"><b>' + attachment.filename + '</b></a>');
			// Populate attachment ID into hidden form field.
			console.log( attachment, hash );
			console.log( jQuery('#plugin_licensing_file_' + hash) );
			jQuery('#plugin_licensing_file_' + hash).val( attachment.id );
		});

		// Finally, open the modal
		file_frame.open();
	});

	jQuery('#pmpro-file-attachments-list').on('click', '.remove-file-attachment', function(){
		jQuery(this).closest('tr').remove();
	});
}