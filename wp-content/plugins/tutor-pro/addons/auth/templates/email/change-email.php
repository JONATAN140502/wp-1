<?php
/**
 * Change email template
 *
 * @package TutorPro\Auth
 * @subpackage Templates
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.8.2
 */

?>
<!DOCTYPE html>
<html>

<head>
	<meta http-equiv="Content-Type" content="text/html charset=UTF-8" />
	<?php require TUTOR()->path . 'templates/email/email_styles.php'; ?>
</head>

<body>
	<div class="tutor-email-body">
		<div class="tutor-email-wrapper" style="background-color: #fff;">

			<?php require TUTOR_PRO()->path . 'templates/email/email_header.php'; ?>

			<div class="tutor-email-content">
				<div>
					<p><?php esc_html_e( 'Hi', 'tutor-pro' ); ?> {display_name},</p>
					<p><?php esc_html_e( 'We have received a request to update the email address for your {site_name} account. To confirm this change, verify your new email address by clicking the link below:', 'tutor-pro' ); ?></p>
					<p style="word-wrap: break-word; word-break: break-all;">{link}</p>
					<p><?php esc_html_e( 'If you did not request this change, please disregard this email. For any questions or assistance, feel free to contact our support team.', 'tutor-pro' ); ?></p>
				</div>

				<div style="margin-top: 20px;">
					<p><?php esc_html_e( 'Thank you!', 'tutor-pro' ); ?></p>
					<p><?php esc_html_e( 'Regards', 'tutor-pro' ); ?>,
						<br />
						<?php esc_html_e( 'The {site_name} Team', 'tutor-pro' ); ?>
					</p>
				</div>
			</div>

			<div class="tutor-email-footer-text">
				<div data-source="email-footer-text">{additional_footer}</div>
			</div>

		</div>
		<!-- wrapper end -->
	</div>
	<!-- email body end -->

	<!-- global footer from tutor settings > email -->
	{footer_text}

</body>
</html>
