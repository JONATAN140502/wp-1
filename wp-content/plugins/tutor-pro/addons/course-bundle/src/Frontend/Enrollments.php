<?php
/**
 * Handle Tutor enrollments
 *
 * @package TutorPro\CourseBundle
 * @subpackage Integrations
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.2.0
 */

namespace TutorPro\CourseBundle\Frontend;

use Tutor\Models\CourseModel;
use TutorPro\CourseBundle\CustomPosts\CourseBundle;
use TutorPro\CourseBundle\Models\BundleModel;
use TutorPro\CourseBundle\Utils;

/**
 * Integration with tutor enrollments
 */
class Enrollments {

	/**
	 * Register hooks
	 *
	 * @since 3.2.0
	 */
	public function __construct() {
		add_action( 'tutor_after_enroll', array( $this, 'enroll_the_user' ) );
		add_action( 'is_course_purchasable', array( $this, 'filter_purchaseable' ), 100, 2 );
		add_action( 'single_bundle_template_before_load', array( $this, 'before_bundle_template_load' ) );
		add_action( 'tutor_course_complete_after', array( $this, 'handle_course_completion' ), 10, 2 );
		add_action( 'tutor_course_completed_percent', array( $this, 'filter_course_completed_percent' ), 10, 4 );
	}

	/**
	 * Enroll the user to the bundle courses
	 *
	 * For the free purchase order hooks are not getting
	 * triggered that's why we need to enroll the user separately
	 *
	 * @since 3.2.0
	 *
	 * @param int $bundle_id Bundle course id.
	 *
	 * @return void
	 */
	public function enroll_the_user( $bundle_id ) {
		if ( CourseBundle::POST_TYPE === get_post_type( $bundle_id ) && Utils::is_free( $bundle_id ) ) {
			$user_id = get_current_user_id();
			BundleModel::enroll_to_bundle_courses( $bundle_id, $user_id );
		}
	}

	/**
	 * If there is no regular or sale price set bundle as free
	 *
	 * @since 3.2.0
	 *
	 * @param bool $is_purchaseable Is purchaseable.
	 * @param int  $course_id Course id.
	 *
	 * @return bool
	 */
	public function filter_purchaseable( $is_purchaseable, $course_id ) {
		if ( CourseBundle::POST_TYPE === get_post_type( $course_id ) ) {
			if ( Utils::is_free( $course_id ) ) {
				$is_purchaseable = false;
			}
		}

		return $is_purchaseable;
	}

	/**
	 * Before bundle template load
	 *
	 * @since 3.9.0
	 *
	 * @param int $bundle_id Bundle id.
	 *
	 * @return void
	 */
	public function before_bundle_template_load( $bundle_id ) {
		$user_id = get_current_user_id();

		if ( tutor_utils()->is_completed_course( $bundle_id, $user_id ) ) {
			return;
		}

		$this->mark_bundle_as_completed( $bundle_id, $user_id );
	}

	/**
	 * Handle course completion for bundles
	 *
	 * @since 3.9.0
	 *
	 * @param int $course_id Course id that was completed.
	 * @param int $user_id   User id who completed the course.
	 *
	 * @return void
	 */
	public function handle_course_completion( $course_id, $user_id ) {
		if ( CourseBundle::POST_TYPE === get_post_type( $course_id ) ) {
			return;
		}

		$bundle_id = BundleModel::get_enrolled_bundle_id_by_course( $course_id, $user_id );
		if ( ! $bundle_id ) {
			return;
		}

		$this->mark_bundle_as_completed( $bundle_id, $user_id );
	}

	/**
	 * Mark bundle as completed if all courses are completed
	 *
	 * @since 3.9.0
	 *
	 * @param int $bundle_id Bundle id.
	 * @param int $user_id   User id.
	 *
	 * @return void
	 */
	public function mark_bundle_as_completed( $bundle_id, $user_id ) {
		if ( ! tutor_utils()->is_enrolled( $bundle_id, $user_id ) ) {
			return;
		}

		$bundle_courses = BundleModel::get_bundle_courses( $bundle_id );
		if ( empty( $bundle_courses ) ) {
			return;
		}

		$all_completed = true;

		foreach ( $bundle_courses as $bundle_course ) {
			if ( ! tutor_utils()->is_completed_course( $bundle_course->ID, $user_id ) ) {
				$all_completed = false;
				break;
			}
		}

		if ( $all_completed ) {
			CourseModel::mark_course_as_completed( $bundle_id, $user_id );
		}
	}

	/**
	 * Calculate completion percentage for course bundles
	 *
	 * @since 3.9.0
	 *
	 * @param mixed $result result.
	 * @param int   $course_id  Course/Bundle ID.
	 * @param int   $user_id    User ID.
	 * @param bool  $get_stats  Whether to return detailed stats.
	 *
	 * @return mixed Completion percentage or stats array.
	 */
	public function filter_course_completed_percent( $result, $course_id, $user_id, $get_stats ) {
		if ( CourseBundle::POST_TYPE !== get_post_type( $course_id ) ) {
			return $result;
		}

		$courses   = BundleModel::get_bundle_courses( $course_id );
		$total     = count( $courses );
		$completed = 0;

		foreach ( $courses as $course ) {
			if ( tutor_utils()->is_completed_course( $course->ID, $user_id ) ) {
				$completed++;
			}
		}

		$percent_complete = $total > 0 ? ( $completed / $total ) * 100 : 0;

		if ( $get_stats ) {
			return array(
				'completed_percent' => round( $percent_complete ),
				'completed_count'   => $completed,
				'total_count'       => $total,
			);
		}

		return $percent_complete;
	}
}
