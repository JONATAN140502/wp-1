<?php
/**
 * Handle Lesson Notes
 *
 * @package TutorPro\Classes
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.9.0
 */

namespace TUTOR_PRO;

use Tutor\Helpers\HttpHelper;
use TUTOR\Icon;
use TUTOR\Input;
use Tutor\Traits\JsonResponse;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lesson Notes
 */
class LessonNotes {
	use JsonResponse;

	/**
	 * Lesson Notes Meta Key
	 *
	 * @since 3.9.0
	 */
	const COMMENT_TYPE = 'lesson_note';

	const TYPE_REGULAR   = 'regular';
	const TYPE_HIGHLIGHT = 'highlight';
	const TYPE_VIDEO     = 'video';

	const NOTE_META_KEY = '_tutor_note_info';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		/**
		 * Lesson Notes feature
		 *
		 * @since 3.3.0
		 */
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_note_scripts' ) );
		add_filter( 'tutor_lesson_single_nav_items', array( $this, 'filter_lesson_single_nav_item' ) );
		add_filter( 'tutor_lesson_single_nav_contents', array( $this, 'filter_lesson_notes_tab_content' ) );
		add_action( 'tutor_lesson_single_after_nav_items', array( $this, 'load_lesson_notes_nav_button' ), 10, 2 );
		add_action( 'wp_ajax_tutor_pro_save_lesson_note', array( $this, 'ajax_save_lesson_note' ) );
		add_action( 'wp_ajax_tutor_pro_update_lesson_note', array( $this, 'ajax_update_lesson_note' ) );
		add_action( 'wp_ajax_tutor_pro_delete_lesson_note', array( $this, 'ajax_delete_lesson_note' ) );
		add_action( 'wp_ajax_tutor_pro_get_lesson_notes_html', array( $this, 'ajax_get_lesson_notes_html' ) );
		add_action( 'wp_ajax_tutor_pro_get_single_lesson_note_html', array( $this, 'ajax_get_single_lesson_note_html' ) );
		add_action( 'wp_ajax_tutor_pro_lesson_notes_load_more', array( $this, 'ajax_lesson_notes_load_more' ) );
	}

	/**
	 * Enqueue lesson notes js and css
	 *
	 * @since 3.9.0
	 */
	public function enqueue_note_scripts() {
		header( 'Content-Type: text/html; charset=utf-8' );
		if ( is_single() && tutor()->lesson_post_type === get_post_type() ) {
			wp_enqueue_script( 'tutor-pro-lesson-notes', tutor_pro()->url . 'assets/js/lesson-notes.js', array( 'jquery', 'wp-i18n' ), TUTOR_PRO_VERSION, true );

			$lesson_notes = $this->get_lesson_notes( get_the_ID(), get_current_user_id(), 0, 1000 );

			$lesson_notes = $this->lesson_decode_unicode_sequences( $lesson_notes );

			wp_localize_script( 'tutor-pro-lesson-notes', 'lesson_notes', $lesson_notes );

			wp_enqueue_style( 'tutor-pro-lesson-notes', tutor_pro()->url . 'assets/css/lesson-notes.css', array(), TUTOR_PRO_VERSION );
		}
	}

	/**
	 * Lesson decode unicode sequences.
	 *
	 * @param array $lesson_notes Lesson notes to decode.
	 *
	 * @return array Decoded lesson notes.
	 */
	public function lesson_decode_unicode_sequences( $lesson_notes ) {
		foreach ( $lesson_notes as $note ) {
			$note->highlight_text = tutor_decode_unicode_sequences( $note->highlight_text );
			if ( 'highlight' === $note->type && ! empty( $note->highlight_serialized['text'] ) ) {
				$note->highlight_serialized['text'] = tutor_decode_unicode_sequences( $note->highlight_serialized['text'] );
			}
		}
		return $lesson_notes;
	}

	/**
	 * Add Lesson Notes Nav Item
	 *
	 * @since 3.9.0
	 *
	 * @param array $nav_items Nav Items.
	 *
	 * @return array
	 */
	public function filter_lesson_single_nav_item( $nav_items ) {
		if ( ! self::is_notes_tab_available() ) {
			return $nav_items;
		}

		$notes_tab = array(
			'label'     => esc_html__( 'Notes', 'tutor-pro' ),
			'value'     => 'notes',
			'icon'      => Icon::CALENDAR_LINES,
			'icon_type' => 'svg',
		);

		$new_nav_items = array();
		$inserted      = false;

		foreach ( $nav_items as $key => $item ) {
			// Insert before comments tab if it exists.
			if ( isset( $item['value'] ) && 'comments' === $item['value'] && ! $inserted ) {
				$new_nav_items['notes'] = $notes_tab;
				$inserted               = true;
			}

			$new_nav_items[ $key ] = $item;
		}

		// If 'comments' wasn't found, append at the end.
		if ( ! $inserted ) {
			$new_nav_items['notes'] = $notes_tab;
		}

		return $new_nav_items;
	}

	/**
	 * Add Lesson Notes Tab Content
	 *
	 * @since 3.9.0
	 *
	 * @param array $nav_contents Nav Contents.
	 *
	 * @return array
	 */
	public function filter_lesson_notes_tab_content( $nav_contents ) {
		if ( ! self::is_notes_tab_available() ) {
			return $nav_contents;
		}

		$nav_contents['notes'] = array(
			'label'         => esc_html__( 'Notes', 'tutor-pro' ),
			'value'         => 'notes',
			'template_path' => 'lesson-notes.tab-content',
			'is_pro'        => true,
		);

		return $nav_contents;
	}

	/**
	 * Load Lesson Notes Nav Button
	 *
	 * @since 3.9.0
	 *
	 * @param int    $lesson_id Lesson ID.
	 * @param string $active_tab Active Page Tab.
	 */
	public function load_lesson_notes_nav_button( $lesson_id, $active_tab ) {
		if ( ! self::is_notes_tab_available() ) {
			return;
		}

		?>
		<button id="tutor-lesson-nav-take-note-btn" class="tutor-btn tutor-btn-sm <?php echo esc_attr( ( 'notes' === $active_tab ) ? 'tutor-d-none' : '' ); ?>">
			<?php tutor_utils()->render_svg_icon( Icon::FEATHER, 20, 20 ); ?>
			<?php esc_html_e( 'Take Note', 'tutor-pro' ); ?>
		</button>
		<?php
	}

	/**
	 * Save Lesson Note
	 *
	 * @since 3.9.0
	 */
	public function ajax_save_lesson_note() {
		tutor_utils()->check_nonce();

		$current_user         = wp_get_current_user();
		$lesson_id            = Input::post( 'lesson_id', 0, Input::TYPE_INT );
		$note_text            = Input::post( 'note_text', '', Input::TYPE_TEXTAREA );
		$highlight_text       = Input::post( 'highlight_text', '', Input::TYPE_KSES_POST );
		$highlight_serialized = Input::post( 'highlight_serialized', Input::TYPE_KSES_POST );
		$video_start          = Input::post( 'video_start_time', '' );
		$video_end            = Input::post( 'video_end_time', '' );

		if ( empty( $lesson_id ) || empty( $note_text ) ) {
			$this->response_bad_request( __( 'Invalid lesson or note text', 'tutor-pro' ) );
		}

		$lesson = get_post( $lesson_id );
		if ( ! $lesson || tutor()->lesson_post_type !== $lesson->post_type ) {
			$this->response_bad_request( __( 'Lesson not found', 'tutor-pro' ) );
		}

		if ( ! tutor_utils()->has_enrolled_content_access( 'lesson', $lesson_id ) ) {
			$this->json_response( __( 'You do not have access to this lesson', 'tutor-pro' ), null, HttpHelper::STATUS_FORBIDDEN );
		}

		$comment_data = array(
			'comment_post_ID'      => $lesson_id,
			'comment_content'      => $note_text,
			'comment_type'         => self::COMMENT_TYPE,
			'comment_agent'        => 'TutorLMSPlugin',
			'comment_approved'     => 1,
			'user_id'              => $current_user->ID,
			'comment_author'       => $current_user->user_login,
			'comment_author_email' => $current_user->user_email,
			'comment_author_url'   => $current_user->user_url,
		);

		$comment_id = wp_insert_comment( $comment_data );
		if ( is_wp_error( $comment_id ) ) {
			$this->json_response( __( 'Failed to save note', 'tutor-pro' ), null, HttpHelper::STATUS_INTERNAL_SERVER_ERROR );
		}

		$note_type = self::TYPE_REGULAR;
		if ( $highlight_serialized ) {
			$note_type = self::TYPE_HIGHLIGHT;
		}
		if ( self::has_video_time( $video_start ) ) {
			$note_type = self::TYPE_VIDEO;
		}

		// Save meta data.
		$note_meta = array();

		if ( self::TYPE_HIGHLIGHT === $note_type ) {
			$note_meta = array(
				'type'       => $note_type,
				'text'       => $highlight_text,
				'serialized' => json_decode( $highlight_serialized ),
			);
		}

		if ( self::TYPE_VIDEO === $note_type ) {
			$note_meta = array(
				'type'        => $note_type,
				'video_start' => $video_start,
				'video_end'   => $video_end,
			);
		}

		if ( ! empty( $note_meta ) ) {
			update_comment_meta( $comment_id, self::NOTE_META_KEY, wp_json_encode( $note_meta ) );
		}

		$data = array(
			'comment_ID'           => $comment_id,
			'comment_post_ID'      => $lesson_id,
			'comment_content'      => $note_text,
			'highlight_text'       => $highlight_text,
			'highlight_serialized' => $highlight_serialized,
			'video_start_time'     => $video_start,
			'video_end_time'       => $video_end,
		);

		$this->json_response( __( 'Note saved successfully', 'tutor-pro' ), $data );
	}

	/**
	 * Update Lesson Note
	 *
	 * @since 3.9.0
	 */
	public function ajax_update_lesson_note() {
		tutor_utils()->check_nonce();

		$note_id   = Input::post( 'note_id', 0, Input::TYPE_INT );
		$note_text = Input::post( 'note_text', '', Input::TYPE_TEXTAREA );

		if ( empty( $note_id ) || empty( $note_text ) ) {
			$this->response_bad_request( __( 'Invalid comment or note text', 'tutor-pro' ) );
		}

		$comment = get_comment( $note_id );
		if ( ! $comment || get_current_user_id() !== (int) $comment->user_id ) {
			$this->json_response( __( 'You are not authorized to update this note', 'tutor-pro' ), null, HttpHelper::STATUS_FORBIDDEN );
		}

		$updated = wp_update_comment(
			array(
				'comment_ID'      => $note_id,
				'comment_content' => $note_text,
			)
		);

		if ( is_wp_error( $updated ) ) {
			$this->json_response( __( 'Failed to update note', 'tutor-pro' ), null, HttpHelper::STATUS_INTERNAL_SERVER_ERROR );
		}

		$this->json_response( __( 'Note updated successfully', 'tutor-pro' ), array( 'note_id' => $note_id ) );
	}

	/**
	 * Delete Lesson Note
	 *
	 * @since 3.9.0
	 */
	public function ajax_delete_lesson_note() {
		tutor_utils()->check_nonce();

		$note_id = Input::post( 'note_id', 0, Input::TYPE_INT );

		if ( empty( $note_id ) ) {
			$this->response_bad_request( __( 'Invalid comment', 'tutor-pro' ) );
		}

		$comment = get_comment( $note_id );
		if ( ! $comment || self::COMMENT_TYPE !== $comment->comment_type ) {
			$this->json_response( __( 'Note not found', 'tutor-pro' ), null, HttpHelper::STATUS_NOT_FOUND );
		}

		if ( get_current_user_id() !== (int) $comment->user_id ) {
			$this->json_response( __( 'You are not authorized to delete this note', 'tutor-pro' ), null, HttpHelper::STATUS_FORBIDDEN );
		}

		$deleted = wp_delete_comment( $note_id, true );

		if ( ! $deleted ) {
			$this->json_response( __( 'Failed to delete note', 'tutor-pro' ), null, HttpHelper::STATUS_INTERNAL_SERVER_ERROR );
		}

		$this->json_response( __( 'Note deleted successfully', 'tutor-pro' ), array( 'note_id' => $note_id ) );
	}

	/**
	 * Get Lesson Notes HTML
	 *
	 * @since 3.9.0
	 */
	public function ajax_get_lesson_notes_html() {
		tutor_utils()->check_nonce();

		$lesson_id = Input::post( 'lesson_id', 0, Input::TYPE_INT );

		if ( empty( $lesson_id ) ) {
			$this->response_bad_request( __( 'Invalid lesson', 'tutor-pro' ) );
		}

		if ( ! tutor_utils()->has_enrolled_content_access( 'lesson', $lesson_id ) ) {
			$this->json_response( __( 'You do not have access to this lesson', 'tutor-pro' ), null, HttpHelper::STATUS_FORBIDDEN );
		}

		ob_start();
		tutor_load_template(
			'lesson-notes/note-list',
			array(
				'lesson_id' => $lesson_id,
			),
			true
		);
		$html = ob_get_clean();

		$this->json_response( __( 'Notes fetched successfully', 'tutor-pro' ), array( 'html' => $html ) );
	}

	/**
	 * Get Single Lesson Note HTML
	 *
	 * @since 3.9.0
	 */
	public function ajax_get_single_lesson_note_html() {
		tutor_utils()->check_nonce();

		$lesson_id = Input::post( 'lesson_id', 0, Input::TYPE_INT );
		$note_id   = Input::post( 'note_id', 0, Input::TYPE_INT );
		if ( empty( $lesson_id ) || empty( $note_id ) ) {
			$this->response_bad_request( __( 'Invalid lesson or comment', 'tutor-pro' ) );
		}

		if ( ! tutor_utils()->has_enrolled_content_access( 'lesson', $lesson_id ) ) {
			$this->json_response( __( 'You do not have access to this lesson', 'tutor-pro' ), null, HttpHelper::STATUS_FORBIDDEN );
		}

		$note = $this->get_single_lesson_note( $note_id );
		if ( ! $note ) {
			$this->json_response( __( 'Note not found', 'tutor-pro' ), null, HttpHelper::STATUS_NOT_FOUND );
		}

		ob_start();
		tutor_load_template(
			'lesson-notes/note-item',
			array(
				'lesson_id' => $lesson_id,
				'note'      => $note,
			),
			true
		);
		$html = ob_get_clean();
		$this->json_response( __( 'Note fetched successfully', 'tutor-pro' ), array( 'html' => $html ) );
	}

	/**
	 * Lesson Notes Load More
	 *
	 * @since 3.9.0
	 */
	public function ajax_lesson_notes_load_more() {
		tutor_utils()->check_nonce();

		$lesson_id = Input::post( 'lesson_id', 0, Input::TYPE_INT );
		$offset    = Input::post( 'offset', 0, Input::TYPE_INT );

		if ( empty( $lesson_id ) ) {
			$this->response_bad_request( __( 'Invalid lesson', 'tutor-pro' ) );
		}

		if ( ! tutor_utils()->has_enrolled_content_access( 'lesson', $lesson_id ) ) {
			$this->json_response( __( 'You do not have access to this lesson', 'tutor-pro' ), null, HttpHelper::STATUS_FORBIDDEN );
		}

		$items_per_page = tutor_utils()->get_option( 'pagination_per_page' );

		$note_list = $this->get_lesson_notes( $lesson_id, get_current_user_id(), $offset, $items_per_page );
		if ( empty( $note_list ) ) {
			$this->json_response( __( 'No more notes found', 'tutor-pro' ), null, HttpHelper::STATUS_NOT_FOUND );
		}

		ob_start();
		foreach ( $note_list as $note ) {
			tutor_load_template(
				'lesson-notes/note-item',
				array(
					'lesson_id' => $lesson_id,
					'note'      => $note,
				),
				true
			);
		}
		$html = ob_get_clean();
		$this->json_response(
			__( 'Notes fetched successfully', 'tutor-pro' ),
			array(
				'html'        => $html,
				'notes_count' => count( $note_list ),
			)
		);
	}

	/**
	 * Get Lesson Notes
	 *
	 * @since 3.9.0
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $user_id User ID.
	 * @param int $offset Offset.
	 * @param int $item_per_page Items Per Page.
	 */
	public function get_lesson_notes( $lesson_id, $user_id, $offset = 0, $item_per_page = 20 ) {
		if ( ! $lesson_id || ! $user_id ) {
			return array();
		}

		$paged = $offset > 0 ? (int) floor( $offset / $item_per_page ) + 1 : 1;

		$args = array(
			'post_id' => $lesson_id,
			'user_id' => $user_id,
			'type'    => self::COMMENT_TYPE,
			'status'  => 'approve',
			'number'  => $item_per_page,
			'offset'  => $offset,
			'paged'   => $paged,
		);

		$comments = get_comments( $args );

		return array_map( array( $this, 'add_note_meta_to_comment' ), $comments );
	}

	/**
	 * Get Single Lesson Note
	 *
	 * @since 3.9.0
	 *
	 * @param int $note_id Note ID.
	 *
	 * @return object|null Comment object or null if not found.
	 */
	public function get_single_lesson_note( $note_id ) {
		$comment = get_comment( $note_id );
		if ( ! $comment || self::COMMENT_TYPE !== $comment->comment_type ) {
			return null;
		}
		return $this->add_note_meta_to_comment( $comment );
	}

	/**
	 * Add note meta to comment
	 *
	 * @since 3.9.0
	 *
	 * @param object $comment Note comment.
	 *
	 * @return object The note.
	 */
	private function add_note_meta_to_comment( $comment ) {
		$highlight_data_json = get_comment_meta( $comment->comment_ID, self::NOTE_META_KEY, true );

		if ( ! empty( $highlight_data_json ) ) {
			$highlight_data = json_decode( $highlight_data_json, true );

			if ( is_array( $highlight_data ) ) {
				$comment->type                 = $highlight_data['type'] ?? '';
				$comment->highlight_text       = $highlight_data['text'] ?? '';
				$comment->highlight_serialized = $highlight_data['serialized'] ?? '';
				$comment->video_start_time     = $highlight_data['video_start'] ?? '';
				$comment->video_end_time       = $highlight_data['video_end'] ?? '';
			}
		}

		return $comment;
	}

	/**
	 * Get Lesson Notes Count
	 *
	 * @since 3.9.0
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $user_id User ID.
	 *
	 * @return int Number of notes taken for a lesson by a user.
	 */
	public function get_lesson_notes_count( $lesson_id, $user_id ) {
		$args = array(
			'post_id' => $lesson_id,
			'user_id' => $user_id,
			'type'    => self::COMMENT_TYPE,
			'status'  => 'approve',
			'count'   => true,
		);

		return get_comments( $args );
	}

	/**
	 * Check if video time is valid
	 *
	 * @since 3.9.0
	 *
	 * @param string|null $time Video time.
	 *
	 * @return bool True if time is valid, false otherwise.
	 */
	public static function has_video_time( $time ) {
		return null !== $time && '' !== $time;
	}

	/**
	 * Check if notes tab is available for current user
	 *
	 * @since 3.9.0
	 *
	 * @return bool True if notes tab is available, false otherwise.
	 */
	public static function is_notes_tab_available() {
		$is_user_logged_in = is_user_logged_in();

		if ( ! $is_user_logged_in ) {
			return false;
		}

		$user_id                     = get_current_user_id();
		$lesson_id                   = get_the_ID();
		$course_id                   = tutor_utils()->get_course_id_by_lesson( $lesson_id );
		$is_public_course            = 'yes' === get_post_meta( $course_id, '_tutor_is_public_course', true );
		$has_enrolled_content_access = tutor_utils()->has_enrolled_content_access( 'lesson', $lesson_id, $user_id );

		return $is_public_course || $has_enrolled_content_access;
	}
}
