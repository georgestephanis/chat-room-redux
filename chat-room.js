/* global chat_room_l10n */

(function( $, misc ){

	$('form.chat-room-input textarea').autosize({append: "\n"});

	$('form.chat-room-input').submit(function(event){
		var $textarea = $(this).closest('form').find('textarea'),
			input     = $textarea.val();

		console.log( input );
		console.log( ajax_url );

		event.preventDefault();
		$textarea.val('').focus();
	});

}( jQuery, chat_room_l10n ));
