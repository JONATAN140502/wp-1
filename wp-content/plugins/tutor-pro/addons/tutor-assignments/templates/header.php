<?php
/**
 * Single attempt page
 *
 * @package TutorPro
 * @subpackage Addons\TutorAssignments
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.8.2
 */

$total_mark           = $data['total_mark'] ?? 0;
$pass_mark            = $data['pass_mark'] ?? 0;
$earned_marks         = $data['earned_marks'] ?? 0;
$duration             = $data['duration'] ?? '';
$deadline             = $data['deadline'] ?? '';
$is_retry_allowed     = $data['is_retry_allowed'] ?? 1;
$attempts_allowed     = $data['attempts_allowed'] ?? 5;
$submitted_assignment = $data['submitted_assignment'] ?? 0;
$has_back_url         = ! empty( $data['has_back_url'] );
$back_url             = $has_back_url ? remove_query_arg( 'view_assignment_attempt_id', get_permalink() ) : '';
?>

<?php if ( ! empty( $has_back_url ) ) : ?>
	<div class="tutor-mb-24">
		<a class="tutor-btn tutor-btn-ghost" href="<?php echo esc_url( $back_url ); ?>">
			<span class="tutor-icon-previous tutor-mr-8" area-hidden="true"></span>
			<?php esc_html_e( 'Back', 'tutor-pro' ); ?>
		</a>
	</div>
<?php endif; ?>

<div class="tutor-fs-7 tutor-color-secondary">
	<?php esc_html_e( 'Assignment', 'tutor-pro' ); ?>
</div>

<div class="header-title tutor-fs-5 tutor-fw-medium tutor-color-black tutor-mt-12 tutor-mb-32">
	<?php the_title(); ?>
</div>

<div class="tutor-assignment-meta-info tutor-d-flex tutor-justify-between tutor-mt-24 tutor-mt-sm-32 tutor-py-16 tutor-py-sm-24">
	<div class="tutor-assignment-detail-info tutor-d-flex">
		<div class="tutor-assignment-duration">
			<span class="tutor-fs-6 tutor-color-muted"><?php esc_html_e( 'Duration:', 'tutor-pro' ); ?></span>
			<span class="tutor-fs-6 tutor-fw-medium  tutor-color-black">
				<?php echo esc_html( $duration ); ?>
			</span>
		</div>
		<div class="tutor-assignmetn-deadline">
			<span class="tutor-fs-6 tutor-color-muted"><?php esc_html_e( 'Deadline:', 'tutor-pro' ); ?></span>
			<span class="tutor-fs-6 tutor-fw-medium  tutor-color-black">
				<?php echo esc_html( $deadline ); ?>
			</span>
		</div>
	</div>
	<div class="tutor-assignment-detail-info tutor-d-flex">
		<?php if ( $is_retry_allowed && $attempts_allowed > 0 ) : ?>
		<div class="tutor-assignment-marks">
			<span class="tutor-fs-6 tutor-color-muted"><?php esc_html_e( 'Total Attempts:', 'tutor-pro' ); ?></span>
			<span class="tutor-fs-6 tutor-fw-medium  tutor-color-black"><?php echo esc_html( count( $submitted_assignment ) . '/' . ( $attempts_allowed + 1 ) ); ?></span>
		</div>
		<?php endif; ?>
		<div class="tutor-assignment-marks">
			<span class="tutor-fs-6 tutor-color-muted"><?php esc_html_e( 'Total Marks:', 'tutor-pro' ); ?></span>
			<span class="tutor-fs-6 tutor-fw-medium  tutor-color-black"><?php echo esc_html( $earned_marks . '/' . $total_mark ); ?></span>
		</div>
		<div class="tutor-assignmetn-pass-mark">
			<span class="tutor-fs-6 tutor-color-muted"><?php esc_html_e( 'Passing Mark:', 'tutor-pro' ); ?></span>
			<span class="tutor-fs-6 tutor-fw-medium  tutor-color-black"><?php echo esc_html( $pass_mark ); ?></span>
		</div>
	</div>
</div>