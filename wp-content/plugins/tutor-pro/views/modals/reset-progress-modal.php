<?php
/**
 * Reset course progress modal template
 *
 * @author themeum
 * @link https://themeum.com
 * @package TutorLMS/Templates
 *
 * @since 3.9.0
 */

?>
<div id="tutor-reset-progress-modal" class="tutor-modal">
	<div class="tutor-modal-overlay"></div>
	<div class="tutor-modal-window">
		<div class="tutor-modal-content tutor-modal-content-white">
			<button class="tutor-iconic-btn tutor-modal-close-o" data-tutor-modal-close>
				<span class="tutor-icon-times" area-hidden="true"></span>
			</button>

			<div class="tutor-modal-body tutor-text-center">
				<div class="tutor-mt-48">
					<img class="tutor-d-inline-block" src="<?php echo esc_url( trailingslashit( tutor()->url ) . 'assets/images/icon-gear.svg' ); ?>" />
				</div>

				<div class="tutor-fs-3 tutor-fw-medium tutor-color-black tutor-mb-12"><?php esc_html_e( 'Reset Course Progress?', 'tutor-pro' ); ?></div>
				<div class="tutor-fs-6 tutor-color-muted"><?php esc_html_e( 'Resetting will erase this student’s completed lessons, quizzes, and assignment records for the selected course.', 'tutor-pro' ); ?></div>

				<div class="tutor-d-flex tutor-justify-center tutor-gap-2 tutor-my-48">
					<button data-tutor-modal-close class="tutor-btn tutor-btn-outline-primary">
						<?php esc_html_e( 'Cancel', 'tutor-pro' ); ?>
					</button>
					<button class="tutor-btn tutor-btn-primary tutor-reset-progress-action">
						<?php esc_html_e( 'Yes, Reset', 'tutor-pro' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>
