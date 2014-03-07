<?php
/**
 * Plugin Name: Chat Room Redux
 * Plugin URI: http://stephanis.info
 * Description: An easy way to add a native chatroom to WordPress.
 * Version: 0.9
 * Author: George Stephanis
 * Author URI: http://stephanis.info
 * License: GPLv2+
 */

add_action( 'init', array( 'Chat_Room_Redux', 'init' ) );

class Chat_Room_Redux {

	const POST_TYPE    = 'chat-room';
	const MSG_META_KEY = 'chat-room-message';
	const SHORTCODE    = 'chat-room';
	const SHOW_TIMES   = false;

	/**
	 * Kicks everything off.
	 */
	public static function init() {
		self::register_post_type();
		self::register_scripts_styles();

		add_filter( 'the_content', array( __CLASS__, 'add_chat_room' ), 0 );
		add_action( 'wp_ajax_chat_room_add_message', array( __CLASS__, 'wp_ajax_chat_room_add_message' ) );
		add_action( 'wp_ajax_chat_room_get_new_messages', array( __CLASS__, 'wp_ajax_chat_room_get_new_messages' ) );

		add_shortcode( self::SHORTCODE, array( __CLASS__, 'chat_room' ) );
	}

	/**
	 * Registers our post type. Runs directly from self::init()
	 */
	public static function register_post_type() {
		$args = array(
			'label'           => esc_html__( 'Chat Rooms' ),
			'public'          => true,
			'menu_position'   => 5,
			'menu_icon'       => 'dashicons-format-chat',
			'capability_type' => 'page',
			'supports'        => array( 'title', 'editor' ),
			'register_meta_box_cb' => array( __CLASS__, 'meta_box_cb' ),
			
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register scripts and styles.
	 */
	public static function register_scripts_styles() {
		wp_register_script( 'autosize', plugins_url( 'jquery.autosize.min.js', __FILE__ ), array( 'jquery' ), '1.18.4' );
		wp_register_script( 'scrollto', plugins_url( 'jquery.scrollto.min.js', __FILE__ ), array( 'jquery' ), '1.4.11' );
		wp_register_script( 'chat-room', plugins_url( 'chat-room.js', __FILE__ ), array( 'jquery', 'scrollto', 'autosize' ), false, true );

		wp_register_style( 'chat-room', plugins_url( 'chat-room.css', __FILE__ ) );
	}

	/**
	 * Adds our meta box to the edit screen -- specified in self::register_post_type()
	 */
	public static function meta_box_cb( $post ) {
		if ( $post->ID && ! in_array( $post->post_status, array( 'auto-draft' ) ) ) {
			add_meta_box( 'chat-room-contents', esc_html__( 'Chat Room Contents' ), array( __CLASS__, 'chat_room_contents_cb' ) );
		}
	}

	/**
	 * Does the actual meta box on the edit screen -- added by self::meta_box_cb()
	 */
	public static function chat_room_contents_cb( $post, $metabox ) {
		self::display_messages( self::get_messages( $post->ID ) );
	}

	/**
	 * Utility function. Adds a new message to a given chat. Assumes the current user and time.
	 */
	public static function add_message( $message, $chat_id ) {
		$data = array(
			'user' => get_current_user_id(),
			'when' => time(),
			'text' => $message,
		);
		return add_post_meta( $chat_id, self::MSG_META_KEY, (object) $data );
	}

	/**
	 * Utility function. Gets messages for a given chat room.
	 */
	public static function get_messages( $chat_id ) {
		return get_post_meta( $chat_id, self::MSG_META_KEY );
	}

	/**
	 * Displays the messages in a definition list, or a paragraph
	 * remarking that there were none found.
	 */
	public static function display_messages( $messages ) {
		?>

		<dl class="messages">
		<?php if ( $messages && is_array( $messages ) ) : ?>
			<?php foreach ( $messages as $msg ) : ?>
				<dt><?php echo esc_html( $msg->user ); ?>
					<?php if ( self::SHOW_TIMES ) : ?>
						( <?php echo esc_html( $msg->when ); ?> )
					<?php endif; ?>
				</dt>
				<dd><?php echo esc_html( $msg->text ); ?></dd>
			<?php endforeach; ?>
		<?php else : ?>
			<dt class="no-messages-found"></dt>
			<dd class="no-messages-found"><?php esc_html_e( 'No messages … yet!' ); ?></dd>
		<?php endif; ?>
		</dl>

		<?php
	}

	/**
	 * Adds the chat room shortcode to the end of the post if it's not there yet.
	 */
	public static function add_chat_room( $content ) {
		if ( self::POST_TYPE == get_post_type() && ! has_shortcode( $content, self::SHORTCODE ) ) {
			$content .= "\n\n[" . self::SHORTCODE . "]";
		}
		return $content;
	}

	/**
	 * Apply formatting to a raw message from the db.
	 */
	public static function prettify_message( $msg ) {
		$msg->user = get_userdata( $msg->user )->display_name;
		$msg->when = date( get_option( 'time_format' ), $msg->when );
		$msg->text = stripslashes( $msg->text );

		return $msg;
	}

	/**
	 * Displays the chat room. If the shortcode is used, will display that way.
	 * Otherwise, will be appended to the end.
	 *
	 * Also, does the [chat-room] shortcode.
	 */
	public static function chat_room( $atts ) {
		$pairs = array(
			'chat_id' => get_the_ID(),
		);

		$atts = shortcode_atts( $pairs, $atts, self::SHORTCODE );

		if ( ! is_user_logged_in() ) {
			return '<p>' . sprintf( _x( 'You must be <a href="%s">logged in</a> to view or participate in this chat.', 'Link goes to login page.' ), wp_login_url( get_permalink() ) ) . '</p>';
		}

		$messages = self::get_messages( intval( $atts['chat_id'] ) );
		$messages = array_map( array( __CLASS__, 'prettify_message' ), $messages );

		// If we're not on the single post page, we don't
		// want to display it as a chat room.
		if ( ! is_singular( self::POST_TYPE ) ) {
			ob_start();
			self::display_messages( $messages );
			return ob_get_clean();
		}

		wp_localize_script( 'chat-room', 'chat_room_l10n', array(
			'chat_id'      => $atts['chat_id'],
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'chat-room-' . $atts['chat_id'] ),
			'display_name' => wp_get_current_user()->display_name,
			'time_format'  => get_option( 'time_format' ),
			'chk_interval' => 5,
			'show_times'   => self::SHOW_TIMES,
		) );
		wp_enqueue_script( 'chat-room' );
		wp_enqueue_style( 'chat-room' );
		ob_start();
		?>
		<div class="chat-room-wrapper">
			<div class="chat-room-scrollback">
				<?php self::display_messages( $messages ); ?>
			</div>
			<form class="chat-room-input">
				<textarea id="chat-room-input" placeholder="<?php esc_attr_e( 'What would you like to say?' ); ?>"></textarea>
				<button type="submit"><?php esc_html_e( 'Submit Message' ); ?></button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * The catcher for our add message ajax request.
	 */
	public static function wp_ajax_chat_room_add_message() {
		if ( ! isset( $_REQUEST['message'], $_REQUEST['chat_id'], $_REQUEST['nonce'] ) ) {
			wp_send_json_error( __( 'Error: Missing Arguments.' ) );
		}

		$message = trim( wp_kses( $_REQUEST['message'], wp_kses_allowed_html( 'data' ) ) );
		$chat_id = (int) $_REQUEST['chat_id'];
		$nonce   = $_REQUEST['nonce'];

		if ( empty( $message ) ) {
			wp_send_json_error( __( 'Error: Empty Message.' ) );
		}

		if ( empty( $chat_id ) ) {
			wp_send_json_error( __( 'Error: Invalid Chat ID.' ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'chat-room-' . $chat_id ) ) {
			wp_send_json_error( __( 'Error: Invalid Nonce.' ) );
		}

		self::add_message( $message, $chat_id );

		wp_send_json_success( array(
			'user' => wp_get_current_user()->display_name,
			'when' => date( get_option( 'time_format' ) ),
			'text' => stripslashes( $message ),
		) );
	}

	public static function wp_ajax_chat_room_get_new_messages() {
		if ( ! isset( $_REQUEST['count'], $_REQUEST['chat_id'], $_REQUEST['nonce'] ) ) {
			wp_send_json_error( __( 'Error: Missing Arguments.' ) );
		}

		$count   = (int) $_REQUEST['count'];
		$chat_id = (int) $_REQUEST['chat_id'];
		$nonce   = $_REQUEST['nonce'];

		if ( empty( $chat_id ) ) {
			wp_send_json_error( __( 'Error: Invalid Chat ID.' ) );
		}
		
		if ( ! wp_verify_nonce( $nonce, 'chat-room-' . $chat_id ) ) {
			wp_send_json_error( __( 'Error: Invalid Nonce.' ) );
		}

		$messages = self::get_messages( intval( $_REQUEST['chat_id'] ) );
		$messages = array_map( array( __CLASS__, 'prettify_message' ), $messages );

		if ( sizeof( $messages ) == $count ) {
			wp_send_json_success( 0 );
		}

		wp_send_json_success( $messages );
	}

}
