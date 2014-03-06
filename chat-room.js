/* global chat_room_l10n */

(function( $, misc ){

	$('form.chat-room-input textarea').autosize({append: "\n"});

	$('form.chat-room-input').submit(function(event){
		var $textarea   = $(this).closest('form').find('textarea'),
			submit_data = {
					action  : 'chat_room_add_message',
					message : $textarea.val(),
					chat_id : misc.chat_id,
					nonce   : misc.nonce
				};

		$.getJSON( misc.ajax_url, submit_data, function( data ) {
			if ( ! data.success ) {
				if ( console ) {
					console.log( data.data );
				}
				return;
			}
			console.log( data );
		} );

		event.preventDefault();
		$textarea.val('').focus();
	});

}( jQuery, chat_room_l10n ));
