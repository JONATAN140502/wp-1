<?php
/**
 * Template: Pestaña de Asistencia en Dashboard de Estudiante
 *
 * @package TutorAttendanceCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin = Tutor_Attendance_Calendar::get_instance();
$student_id = get_current_user_id();
$student_courses = $plugin->get_student_courses( $student_id );

// Obtener curso seleccionado
$selected_course_id = isset( $_GET['attendance_course'] ) ? intval( $_GET['attendance_course'] ) : 0;
$selected_date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );
$selected_date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : date( 'Y-m-d' );

$my_attendance = array();
if ( $selected_course_id > 0 ) {
	$my_attendance = $plugin->get_student_attendance( $student_id, $selected_course_id, $selected_date_from, $selected_date_to );
}
?>

<div class="tutor-dashboard-attendance">
	<div class="tutor-fs-5 tutor-fw-medium tutor-color-black tutor-mb-16">
		<?php esc_html_e( 'Mi Asistencia', 'tutor-attendance-calendar' ); ?>
	</div>
	
	<!-- Filtros -->
	<div class="tutor-attendance-filters tutor-mb-32" style="padding: 20px; background: #f9f9f9; border-radius: 8px;">
		<form method="GET" action="" id="attendance-filter-form">
			<?php
			$current_url = tutor_utils()->tutor_dashboard_url( 'attendance' );
			?>
			<input type="hidden" name="tutor_dashboard_page" value="attendance">
			
			<div class="tutor-row">
				<div class="tutor-col-md-4 tutor-mb-16">
					<label for="attendance_course" class="tutor-form-label">
						<?php esc_html_e( 'Curso:', 'tutor-attendance-calendar' ); ?>
					</label>
					<select name="attendance_course" id="attendance_course" class="tutor-form-control">
						<option value="0"><?php esc_html_e( 'Todos los cursos', 'tutor-attendance-calendar' ); ?></option>
						<?php
						if ( ! empty( $student_courses ) ) {
							foreach ( $student_courses as $course ) :
								$course_id = isset( $course->ID ) ? $course->ID : ( isset( $course->course_id ) ? $course->course_id : 0 );
								$course_title = isset( $course->post_title ) ? $course->post_title : ( isset( $course->course_title ) ? $course->course_title : '' );
								if ( $course_id > 0 && ! empty( $course_title ) ) {
									?>
									<option value="<?php echo esc_attr( $course_id ); ?>" <?php selected( $selected_course_id, $course_id ); ?>>
										<?php echo esc_html( $course_title ); ?>
									</option>
									<?php
								}
							endforeach;
						}
						?>
					</select>
				</div>
				
				<div class="tutor-col-md-3 tutor-mb-16">
					<label for="date_from" class="tutor-form-label">
						<?php esc_html_e( 'Desde:', 'tutor-attendance-calendar' ); ?>
					</label>
					<input type="date" name="date_from" id="date_from" value="<?php echo esc_attr( $selected_date_from ); ?>" class="tutor-form-control">
				</div>
				
				<div class="tutor-col-md-3 tutor-mb-16">
					<label for="date_to" class="tutor-form-label">
						<?php esc_html_e( 'Hasta:', 'tutor-attendance-calendar' ); ?>
					</label>
					<input type="date" name="date_to" id="date_to" value="<?php echo esc_attr( $selected_date_to ); ?>" class="tutor-form-control">
				</div>
				
				<div class="tutor-col-md-2 tutor-mb-16 tutor-d-flex tutor-align-end">
					<button type="submit" class="tutor-btn tutor-btn-primary tutor-btn-block">
						<?php esc_html_e( 'Filtrar', 'tutor-attendance-calendar' ); ?>
					</button>
				</div>
			</div>
		</form>
	</div>

	<!-- Botón para marcar asistencia (si está permitido) -->
	<?php
	// Filtrar cursos donde el alumno puede marcar asistencia
	$courses_can_mark = array();
	if ( ! empty( $student_courses ) ) {
		foreach ( $student_courses as $course ) {
			$course_id = isset( $course->ID ) ? $course->ID : ( isset( $course->course_id ) ? $course->course_id : 0 );
			if ( $course_id > 0 ) {
				$course_student_can_mark = get_post_meta( $course_id, '_tutor_attendance_student_can_mark', true );
				// Si no está configurado, usar valor por defecto (true)
				if ( $course_student_can_mark === '' ) {
					$course_student_can_mark = true;
				} else {
					$course_student_can_mark = (bool) $course_student_can_mark;
				}
				if ( $course_student_can_mark ) {
					$courses_can_mark[] = $course;
				}
			}
		}
	}
	?>
	<?php if ( ! empty( $courses_can_mark ) ) : ?>
		<div class="tutor-attendance-mark-section tutor-mb-32" style="padding: 20px; background: #e7f3ff; border-left: 4px solid #0073aa; border-radius: 5px;">
			<h4 class="tutor-mb-16"><?php esc_html_e( 'Marcar Mi Asistencia', 'tutor-attendance-calendar' ); ?></h4>
			<p class="tutor-mb-16"><?php esc_html_e( 'Puedes marcar tu asistencia para el día de hoy. Selecciona el curso:', 'tutor-attendance-calendar' ); ?></p>
			
			<div class="tutor-d-flex tutor-align-center tutor-gap-3">
				<select id="mark-attendance-course" class="tutor-form-control" style="max-width: 300px;">
					<option value=""><?php esc_html_e( '-- Seleccionar Curso --', 'tutor-attendance-calendar' ); ?></option>
					<?php
					foreach ( $courses_can_mark as $course ) :
						$course_id = isset( $course->ID ) ? $course->ID : ( isset( $course->course_id ) ? $course->course_id : 0 );
						$course_title = isset( $course->post_title ) ? $course->post_title : ( isset( $course->course_title ) ? $course->course_title : '' );
						if ( $course_id > 0 && ! empty( $course_title ) ) {
							?>
							<option value="<?php echo esc_attr( $course_id ); ?>">
								<?php echo esc_html( $course_title ); ?>
							</option>
							<?php
						}
					endforeach;
					?>
				</select>
				<button type="button" id="mark-attendance-btn" class="tutor-btn tutor-btn-primary">
					<?php esc_html_e( 'Marcar Asistencia', 'tutor-attendance-calendar' ); ?>
				</button>
				<span id="mark-attendance-message" class="tutor-ml-16"></span>
			</div>
		</div>
	<?php endif; ?>

	<!-- Lista de asistencias -->
	<div class="tutor-attendance-list">
		<h4 class="tutor-mb-16"><?php esc_html_e( 'Historial de Asistencias', 'tutor-attendance-calendar' ); ?></h4>
		
		<?php if ( ! empty( $my_attendance ) ) : ?>
			<div class="tutor-table-responsive">
				<table class="tutor-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Fecha', 'tutor-attendance-calendar' ); ?></th>
							<th><?php esc_html_e( 'Curso', 'tutor-attendance-calendar' ); ?></th>
							<th><?php esc_html_e( 'Estado', 'tutor-attendance-calendar' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $my_attendance as $attendance ) : 
							$course = get_post( $attendance->course_id );
							?>
							<tr>
								<td>
									<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $attendance->attendance_date ) ) ); ?>
								</td>
								<td>
									<?php echo $course ? esc_html( $course->post_title ) : '-'; ?>
								</td>
								<td>
									<span class="attendance-status status-<?php echo esc_attr( strtolower( $attendance->status ) ); ?>">
										<?php echo esc_html( $attendance->status ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else : ?>
			<div class="tutor-empty-state tutor-mt-32">
				<p><?php esc_html_e( 'No hay registros de asistencia para los filtros seleccionados.', 'tutor-attendance-calendar' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Marcar asistencia
	$('#mark-attendance-btn').on('click', function() {
		var courseId = $('#mark-attendance-course').val();
		if (!courseId) {
			alert('<?php esc_attr_e( 'Por favor selecciona un curso.', 'tutor-attendance-calendar' ); ?>');
			return;
		}

		var btn = $(this);
		var message = $('#mark-attendance-message');
		
		btn.prop('disabled', true).text('<?php esc_attr_e( 'Marcando...', 'tutor-attendance-calendar' ); ?>');
		message.text('').removeClass('error success');

		$.ajax({
			url: tutorAttendance.ajaxurl,
			type: 'POST',
			data: {
				action: 'tutor_mark_my_attendance',
				nonce: tutorAttendance.nonce,
				course_id: courseId,
				attendance_date: '<?php echo date( 'Y-m-d' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					message.text(response.data.message).addClass('success').css('color', 'green');
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					message.text(response.data.message || '<?php esc_attr_e( 'Error', 'tutor-attendance-calendar' ); ?>').addClass('error').css('color', 'red');
					btn.prop('disabled', false).text('<?php esc_attr_e( 'Marcar Asistencia', 'tutor-attendance-calendar' ); ?>');
				}
			},
			error: function() {
				message.text('<?php esc_attr_e( 'Error de conexión', 'tutor-attendance-calendar' ); ?>').addClass('error').css('color', 'red');
				btn.prop('disabled', false).text('<?php esc_attr_e( 'Marcar Asistencia', 'tutor-attendance-calendar' ); ?>');
			}
		});
	});
});
</script>
