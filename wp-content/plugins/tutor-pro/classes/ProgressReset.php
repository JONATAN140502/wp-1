<?php
/**
 * Manage Course Progress Reset
 *
 * @package TutorPro
 * @subpackage Frontend
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.9.0
 */

namespace TUTOR_PRO;

use TUTOR\Input;
use Tutor\Traits\JsonResponse;
use TUTOR\User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Progress Reset Class
 *
 * @since 3.9.0
 */
class ProgressReset {

	use JsonResponse;

	/**
	 * Register hooks
	 *
	 * @since 3.9.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'tutor/options/extend/attr', array( $this, 'setting_field' ), 11 );
		add_action( 'tutor_after_enrollment_list_course_progress', array( $this, 'reset_student_progress_button' ), 10, 3 );
		add_action( 'tutor_after_student_profile_course_progress', array( $this, 'reset_student_progress_button' ), 10, 3 );
		add_action( 'tutor_after_student_details_view_progress', array( $this, 'dashboard_reset_student_progress_button' ), 10, 3 );
		add_action( 'wp_ajax_tutor_reset_student_course_progress', array( $this, 'ajax_reset_student_course_progress' ) );
	}

	/**
	 * Settings field.
	 *
	 * @since 3.9.0
	 *
	 * @param array $attr attr.
	 *
	 * @return array
	 */
	public function setting_field( $attr ) {
		$attr['general']['blocks'][3]['fields'][] = array(
			'key'         => 'instructor_can_reset_course_progress',
			'type'        => 'toggle_switch',
			'label'       => __( 'Allow Instructors to Reset Student Progress', 'tutor-pro' ),
			'label_title' => '',
			'default'     => 'off',
			'desc'        => __( 'Enable to allow instructors to reset a student’s course progress.', 'tutor-pro' ),
		);

		return $attr;
	}

	/**
	 * Can reset students progress.
	 *
	 * @since 3.9.0
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return bool
	 */
	public static function can_reset_progress( $user_id = 0 ) {
		$can_instructor_reset = (bool) tutor_utils()->get_option( 'instructor_can_reset_course_progress' );
		if ( User::is_admin( $user_id ) || ( User::is_only_instructor( $user_id ) && $can_instructor_reset ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Load progress modal template
	 *
	 * @since 3.9.0
	 *
	 * @return void
	 */
	public static function load_progress_modal() {
		tutor_load_template_from_custom_path( tutor_pro()->path . '/views/modals/reset-progress-modal.php' );
	}

	/**
	 * Add reset progress button
	 *
	 * @since 3.9.0
	 *
	 * @param int $course_id id The course id.
	 * @param int $student_id id The student id.
	 * @param int $progress The progress.
	 *
	 * @return void
	 */
	public function reset_student_progress_button( $course_id, $student_id, $progress ) {
		if ( ! $progress ) {
			return;
		}
		?>
		<button type="button" 
				class="tutor-btn tutor-btn-secondary tutor-btn-sm tutor-reset-progress-btn" 
				data-tutor-modal-target="tutor-reset-progress-modal"
				data-course_id="<?php echo esc_attr( $course_id ); ?>"
				data-student_id="<?php echo esc_attr( $student_id ); ?>">
			<?php esc_html_e( 'Reset Progress', 'tutor-pro' ); ?>
		</button>
		<?php
	}


	/**
	 * Add reset progress button to student course actions.
	 *
	 * @since 3.9.0
	 *
	 * @param int $course_id id The course id.
	 * @param int $student_id id The student id.
	 * @param int $progress The progress.
	 *
	 * @return void
	 */
	public function dashboard_reset_student_progress_button( $course_id, $student_id, $progress ) {
		if ( $progress && $this->can_reset_progress() ) {
			?>
			<button type="button" 
					class="tutor-btn tutor-btn-outline-primary tutor-btn-sm tutor-reset-progress-btn" 
					data-tutor-modal-target="tutor-reset-progress-modal"
					data-course_id="<?php echo esc_attr( $course_id ); ?>"
					data-student_id="<?php echo esc_attr( $student_id ); ?>">
				<?php esc_html_e( 'Reset', 'tutor-pro' ); ?>
			</button>
			<?php
		}
	}

	/**
	 * Reset course progress.
	 *
	 * @since 3.9.0
	 *
	 * @return void
	 */
	public function ajax_reset_student_course_progress() {
		tutor_utils()->check_nonce();

		$course_id  = Input::post( 'course_id', 0, Input::TYPE_INT );
		$student_id = Input::post( 'student_id', 0, Input::TYPE_INT );

		if ( ! $course_id || ! $student_id || ! tutor_utils()->is_enrolled( $course_id, $student_id, false ) ) {
			$this->response_bad_request( __( 'Invalid request', 'tutor-pro' ) );
		}

		if ( $this->can_reset_progress() ) {
			tutor_utils()->delete_course_progress( $course_id, $student_id );
			$this->json_response( __( 'Progress has been successfully reset.', 'tutor-pro' ) );
		} else {
			$this->response_bad_request( tutor_utils()->error_message() );
		}
	}
}
