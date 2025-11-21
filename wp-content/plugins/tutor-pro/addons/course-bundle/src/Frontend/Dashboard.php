<?php
/**
 * Manage dashboard for course bundle.
 *
 * @package TutorPro\CourseBundle
 * @subpackage Frontend
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 2.2.0
 */

namespace TutorPro\CourseBundle\Frontend;

use TUTOR\Input;
use TutorPro\CourseBundle\CustomPosts\CourseBundle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard Class
 *
 * @since 2.2.0
 */
class Dashboard {

	/**
	 * Register hooks
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'tutor_wishlist_post_types', array( $this, 'add_wishlist_post_types' ) );
		add_action( 'tutor_course_create_button', array( $this, 'create_bundle_button' ) );
		add_filter( 'tutor_get_enrolled_courses_by_user', array( $this, 'filter_courses_by_user_args' ) );
		add_filter( 'tutor_get_active_courses_by_user', array( $this, 'filter_courses_by_user_args' ) );
		add_filter( 'tutor_get_completed_courses_by_user', array( $this, 'filter_courses_by_user_args' ) );
		add_action( 'tutor_dashboard_enrolled_courses_filter', array( $this, 'add_course_bundle_filter' ) );
		add_action( 'tutor_enrolled_courses_page_tabs', array( $this, 'filter_enrolled_courses_page_tabs' ), 10, 2 );
	}

	/**
	 * Add create new bundle button on dashboard page.
	 *
	 * @since 3.5.0
	 *
	 * @return void
	 */
	public function create_bundle_button() {
		?>
		<a href="#" data-source="frontend" class="tutor-add-new-course-bundle tutor-mr-8 tutor-btn tutor-btn-outline-primary">
			<i class="tutor-icon-bundle tutor-mr-8"></i>
			<?php esc_html_e( 'New Bundle', 'tutor-pro' ); ?>
		</a>
		<?php
	}

	/**
	 * Add course bundle post type to wishlist post types.
	 *
	 * @since 2.2.0
	 *
	 * @param array $post_types post types.
	 *
	 * @return array
	 */
	public function add_wishlist_post_types( $post_types ) {
		$post_types[] = CourseBundle::POST_TYPE;
		return $post_types;
	}

	/**
	 * Add course bundle post type to dashboard courses.
	 *
	 * @since 3.9.0
	 *
	 * @param array $args Args.
	 *
	 * @return array
	 */
	public function filter_courses_by_user_args( $args ) {
		$post_type = Input::get( 'type' );

		if ( ! empty( $post_type ) ) {
			$args['post_type'] = $post_type;
		}

		return $args;
	}

	/**
	 * Add Filter in dashboard my courses page for filtering course and bundles.
	 *
	 * @since 3.9.0
	 *
	 * @return void
	 */
	public function add_course_bundle_filter() {
		$data = array(
			'options'  => array(
				'courses'       => __( 'Courses', 'tutor-pro' ),
				'course-bundle' => __( 'Bundles', 'tutor-pro' ),
			),
			'selected' => Input::get( 'type' ),
			'classes'  => 'tutor-dashboard-enrolled-courses-filter',
		);
		tutor_load_template( 'dashboard.tab-filter-dropdown', $data, true );
	}

	/**
	 * Add Filter in dashboard my courses page to filter tabs.
	 *
	 * @since 3.9.0
	 *
	 * @param array  $tabs Tabs.
	 * @param string $post_type the post type.
	 *
	 * @return array
	 */
	public function filter_enrolled_courses_page_tabs( $tabs, $post_type ) {
		if ( CourseBundle::POST_TYPE !== $post_type ) {
			return $tabs;
		}

		return array(
			'enrolled-courses'                   => __( 'Enrolled Bundles', 'tutor-pro' ),
			'enrolled-courses/active-courses'    => __( 'Active Bundles', 'tutor-pro' ),
			'enrolled-courses/completed-courses' => __( 'Completed Bundles', 'tutor-pro' ),
		);
	}
}
