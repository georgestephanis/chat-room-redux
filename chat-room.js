/* global chat_room_l10n */

(function( $, misc ){
	var $messages = $('.chat-room-scrollback .messages');

	$('form.chat-room-input textarea').autosize({append: "\n"});

	$('form.chat-room-input').submit(function(event){
		var $textarea   = $(this).closest('form').find('textarea'),
			$submit_btn = $(this).closest('form').find('button'),
			submit_data = {
				action  : 'chat_room_add_message',
				message : $textarea.val(),
				chat_id : misc.chat_id,
				nonce   : misc.nonce
			};

		$textarea.prop( 'readonly', true );
		$submit_btn.prop( 'disabled', true );

		$.getJSON( misc.ajax_url, submit_data, function( data ) {
			var new_html = '',
				msg      = data.data;

			if ( ! data.success ) {
				if ( console ) {
					console.log( msg );
					$textarea.prop( 'readonly', false ).focus();
					$submit_btn.prop( 'disabled', false );
				}
				return;
			}

			new_html += '<dt>' + msg.user;
			if ( misc.show_times ) {
				new_html += ' ( ' + msg.when + ' )';
			}
			new_html += '</dt><dd>' + msg.text + '</dd>';

			$messages.find('.no-messages-found').remove();
			$messages.append( new_html );
			$messages.parent().scrollTo( 'max' );
			$textarea.prop( 'readonly', false ).val('').focus();
			$submit_btn.prop( 'disabled', false );
		} );

		event.preventDefault();
	});

	function getNewMessages() {
		var new_html = '',
			submit_data = {
				action  : 'chat_room_get_new_messages',
				count   : $messages.find('dt:not(.no-messages-found)').length,
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

			if ( data.data ) {
				$.each( data.data, function( index, msg ) {
					new_html += '<dt>' + msg.user;
					if ( misc.show_times ) {
						new_html += ' ( ' + msg.when + ' )';
					}
					new_html += '</dt><dd>' + msg.text + '</dd>';
				});
				$messages.html( new_html );

				$messages.parent().scrollTo( 'max' );
			} else {
				console.log( 'No New Messages' );
			}
		} );
	}

	$(window).on('get-new-messages', getNewMessages);
	$messages.parent().scrollTo( 'max' );

}( jQuery, chat_room_l10n ));
