<?php
/**
 * Change email modal
 *
 * @package TutorPro\Auth
 * @subpackage Views
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.8.2
 */

defined( 'ABSPATH' ) || exit;

$is_pending = self::has_pending_email( get_current_user_id() );
if ( ! $is_pending ) { ?>
<span class="tutor-color-primary tutor-cursor-pointer tutor-ml-8" data-tutor-modal-target="tutor-change-email-modal">
	<?php esc_html_e( 'Update', 'tutor-pro' ); ?>
</span>
	<?php
} else {
	$pending_email = self::get_pending_email( get_current_user_id() );
	?>
<div class="tutor-mt-2 tutor-color-muted tutor-fs-7 tutor-fw-normal">
	<?php echo esc_html( $pending_email ); ?> <span class="tutor-badge-label label-warning"><?php esc_html_e( 'Verification pending', 'tutor-pro' ); ?></span>
</div>
<?php } ?>

<?php if ( ! $is_pending ) : ?>
<!-- Change Email Modal -->
<form class="tutor-modal tutor-modal-scrollable" id="tutor-change-email-modal">
	<div class="tutor-modal-overlay"></div>
	<div class="tutor-modal-window">
		<div class="tutor-modal-content">
			<div class="tutor-modal-header">
				<div class="tutor-modal-title">
					<?php esc_html_e( 'Update Email', 'tutor-pro' ); ?>
				</div>
				<button class="tutor-modal-close tutor-iconic-btn" data-tutor-modal-close role="button">
					<span class="tutor-icon-times" area-hidden="true"></span>
				</button>
			</div>

			<div class="tutor-modal-body tutor-bg-white">
				<?php tutor_nonce_field(); ?>
				<input type="hidden" name="action" value="tutor_change_email">

				<div class="tutor-mb-32">
					<label class="tutor-form-label">
						<?php esc_html_e( 'New Email', 'tutor-pro' ); ?>
					</label>
					<input class="tutor-form-control" type="email" name="new_email" value="" placeholder="<?php esc_html_e( 'New email', 'tutor-pro' ); ?>" required>
				</div>

				<div class="tutor-mb-32">
					<label class="tutor-form-label">
						<?php esc_html_e( 'Confirm New Email', 'tutor-pro' ); ?>
					</label>
					<input class="tutor-form-control" type="email" name="new_email_confirmation" value="" placeholder="<?php esc_html_e( 'Confirm new email', 'tutor-pro' ); ?>" required>
				</div>

				<div class="tutor-mb-32">
					<label class="tutor-form-label">
						<?php esc_html_e( 'Current Password', 'tutor-pro' ); ?>
					</label>
					<input class="tutor-form-control" type="password" name="current_password" value="" placeholder="<?php esc_html_e( 'Current password', 'tutor-pro' ); ?>" required>
				</div>

				<?php tutor_alert( __( 'Note: This will update your login email address for all future logins.', 'tutor-pro' ), 'warning' ); ?>
			</div>

			<div class="tutor-modal-footer">
				<button data-tutor-modal-close type="button" data-action="back" class="tutor-btn tutor-btn-outline-primary">
					<?php esc_html_e( 'Cancel', 'tutor-pro' ); ?>
				</button>
				<button type="submit" data-action="next" class="tutor-btn tutor-btn-primary">
					<?php esc_html_e( 'Submit', 'tutor-pro' ); ?>
				</button>
			</div>
		</div>
	</div>
</form>
<!-- End Change Email Modal -->
<?php endif; ?>
