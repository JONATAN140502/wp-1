<?php
/**
 * Vista: Tomar Asistencia (Docente)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Obtener configuración del curso si está seleccionado
$course_config = array();
$course_schedule_info = '';
if ( $selected_course_id > 0 ) {
	$course_config['use_schedules'] = get_post_meta( $selected_course_id, '_tutor_attendance_use_schedules', true );
	$course_config['date_from'] = get_post_meta( $selected_course_id, '_tutor_attendance_date_from', true );
	$course_config['date_to'] = get_post_meta( $selected_course_id, '_tutor_attendance_date_to', true );
	
	// Obtener información de horarios
	if ( $course_config['use_schedules'] ) {
		$plugin = Tutor_Attendance_Calendar::get_instance();
		$schedules = $plugin->get_course_schedules( $selected_course_id );
		if ( ! empty( $schedules ) ) {
			$days_of_week = array( 'Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb' );
			$days_of_week_full = array( 'Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado' );
			$schedule_items = array();
			$schedules_by_day = array();
			
			foreach ( $schedules as $schedule ) {
				$day = intval( $schedule->day_of_week );
				if ( ! isset( $schedules_by_day[ $day ] ) ) {
					$schedules_by_day[ $day ] = array();
				}
				$schedules_by_day[ $day ][] = $schedule;
			}
			
			// Ordenar por día de la semana
			ksort( $schedules_by_day );
			
			foreach ( $schedules_by_day as $day_num => $day_schedules ) {
				$day_name = isset( $days_of_week[ $day_num ] ) ? $days_of_week[ $day_num ] : '';
				$time_ranges = array();
				foreach ( $day_schedules as $schedule ) {
					$start = substr( $schedule->start_time, 0, 5 );
					$end = substr( $schedule->end_time, 0, 5 );
					$time_ranges[] = $start . '-' . $end;
				}
				if ( $day_name && ! empty( $time_ranges ) ) {
					$schedule_items[] = '<span class="schedule-day-item"><strong>' . esc_html( $day_name ) . ':</strong> ' . esc_html( implode( ', ', $time_ranges ) ) . '</span>';
				}
			}
			
			if ( ! empty( $schedule_items ) ) {
				$course_schedule_info = '<div class="course-schedule-info-compact">';
				$course_schedule_info .= '<span class="schedule-icon">📅</span>';
				$course_schedule_info .= '<span class="schedule-label">Horarios:</span>';
				$course_schedule_info .= '<div class="schedule-days-list">' . implode( ' • ', $schedule_items ) . '</div>';
				$course_schedule_info .= '</div>';
			}
		}
	}
}
?>

<style>
.take-attendance-page {
	max-width: 1600px;
	margin: 0 auto;
	padding: 0 20px 20px 20px;
}

.take-attendance-header {
	background: #fff;
	padding: 25px 30px;
	border-radius: 8px;
	margin-bottom: 25px;
	border-left: 4px solid #2271b1;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.take-attendance-header h1 {
	margin: 0 0 8px 0;
	font-size: 24px;
	font-weight: 600;
	color: #1d2327;
}

.take-attendance-header p {
	margin: 0;
	color: #646970;
	font-size: 14px;
}

.take-attendance-select-card {
	background: #fff;
	padding: 25px 30px;
	border-radius: 8px;
	margin-bottom: 25px;
	border: 1px solid #d1d5db;
	box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.take-attendance-select-card h2 {
	margin: 0 0 20px 0;
	font-size: 18px;
	font-weight: 600;
	color: #1d2327;
	display: flex;
	align-items: center;
	gap: 10px;
}

.take-attendance-select-form {
	display: flex;
	align-items: flex-end;
	gap: 20px;
	flex-wrap: wrap;
}

.take-attendance-select-form .form-field {
	flex: 1;
	min-width: 250px;
}

.take-attendance-select-form label {
	display: block;
	margin-bottom: 8px;
	font-weight: 600;
	color: #1d2327;
	font-size: 13px;
}

.take-attendance-select-form select,
.take-attendance-select-form input[type="date"] {
	width: 100%;
	padding: 10px 14px;
	border: 1px solid #d1d5db;
	border-radius: 6px;
	font-size: 14px;
	background: #fff;
	color: #2c3338;
	transition: all 0.2s ease;
}

.take-attendance-select-form select:focus,
.take-attendance-select-form input[type="date"]:focus {
	outline: none;
	border-color: #2271b1;
	box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
}

.take-attendance-select-form .btn-continue {
	padding: 10px 24px;
	background: #2271b1;
	color: #fff;
	border: 1px solid #2271b1;
	border-radius: 6px;
	font-size: 14px;
	font-weight: 600;
	cursor: pointer;
	white-space: nowrap;
	height: 42px;
	transition: all 0.2s ease;
}

.take-attendance-select-form .btn-continue:hover {
	background: #135e96;
	border-color: #135e96;
	transform: translateY(-1px);
	box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.take-attendance-form-card {
	background: #fff;
	border-radius: 8px;
	border: 1px solid #d1d5db;
	overflow: hidden;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
}

.take-attendance-form-header {
	background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
	padding: 24px 30px;
	border-bottom: 1px solid #e5e7eb;
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	flex-wrap: wrap;
	gap: 20px;
}

.take-attendance-form-header h2 {
	margin: 0;
	font-size: 20px;
	font-weight: 700;
	color: #111827;
	line-height: 1.3;
}

.take-attendance-course-info {
	margin-top: 10px;
	font-size: 13px;
	color: #646970;
	line-height: 1.6;
	display: flex;
	align-items: center;
	gap: 8px;
}

.course-schedule-info-compact {
	display: flex;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
	margin-top: 10px;
	padding: 10px 14px;
	background: #eff6ff;
	border: 1px solid #bfdbfe;
	border-radius: 6px;
	font-size: 12px;
}

.course-schedule-info-compact .schedule-icon {
	font-size: 16px;
}

.course-schedule-info-compact .schedule-label {
	font-weight: 700;
	color: #1e40af;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	font-size: 11px;
}

.course-schedule-info-compact .schedule-days-list {
	display: flex;
	align-items: center;
	gap: 16px;
	flex-wrap: wrap;
	color: #475569;
}

.course-schedule-info-compact .schedule-day-item {
	white-space: nowrap;
	font-size: 12px;
}

.course-schedule-info-compact .schedule-day-item strong {
	color: #1e293b;
	margin-right: 4px;
	font-weight: 700;
}

.quick-actions {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
	align-items: center;
}

.quick-actions .button {
	font-size: 12px;
	padding: 7px 14px;
	height: auto;
	border-radius: 5px;
	transition: all 0.2s ease;
	font-weight: 600;
}

.quick-actions .quick-mark-btn.button-primary {
	background: #2271b1;
	border-color: #2271b1;
	color: #fff;
}

.quick-actions .quick-mark-btn:hover {
	transform: translateY(-1px);
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.take-attendance-form-body {
	padding: 30px;
	background: #fafafa;
}

.take-attendance-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	margin-bottom: 20px;
	background: #fff;
	border-radius: 8px;
	overflow: hidden;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.take-attendance-table thead th {
	background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
	padding: 16px 20px;
	text-align: left;
	font-weight: 700;
	font-size: 12px;
	color: #374151;
	border-bottom: 2px solid #e5e7eb;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	position: sticky;
	top: 0;
	z-index: 10;
}

.take-attendance-table tbody td {
	padding: 16px 20px;
	border-bottom: 1px solid #f3f4f6;
	vertical-align: middle;
	transition: background-color 0.15s ease;
}

.take-attendance-table tbody tr:hover {
	background: #f9fafb;
}

.take-attendance-table tbody tr:last-child td {
	border-bottom: none;
}

.take-attendance-table tbody tr.has-attendance-marked {
	background: #f0fdf4;
	border-left: 4px solid #10b981;
	transition: all 0.15s ease;
}

.take-attendance-table tbody tr.has-attendance-marked:hover {
	background: #d1fae5;
}

.take-attendance-table tbody tr.has-changes {
	background: #fffbeb;
	border-left: 4px solid #f59e0b;
}

.take-attendance-table tbody tr.has-changes:hover {
	background: #fef3c7;
}

.student-name-cell {
	font-weight: 600;
	color: #111827;
	display: flex;
	align-items: center;
	gap: 10px;
}

.attendance-indicator {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 24px;
	height: 24px;
	border-radius: 50%;
	background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
	color: #065f46;
	font-weight: bold;
	font-size: 13px;
	line-height: 1;
	box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
	flex-shrink: 0;
}

.status-select-wrapper {
	display: flex;
	align-items: center;
	gap: 12px;
	flex-wrap: wrap;
}

.attendance-status-badge {
	padding: 6px 14px;
	border-radius: 6px;
	font-size: 12px;
	font-weight: 700;
	display: inline-block;
	text-transform: capitalize;
	white-space: nowrap;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.attendance-status-badge.status-present {
	background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
	color: #065f46;
	border: 1px solid #86efac;
}

.attendance-status-badge.status-absent {
	background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
	color: #991b1b;
	border: 1px solid #fca5a5;
}

.attendance-status-badge.status-late {
	background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
	color: #92400e;
	border: 1px solid #fde047;
}

.attendance-status-badge.status-justified {
	background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
	color: #3730a3;
	border: 1px solid #a5b4fc;
}

.attendance-status-select {
	min-width: 200px;
	padding: 9px 12px;
	border: 2px solid #d1d5db;
	border-radius: 6px;
	font-size: 14px;
	background: #fff;
	color: #2c3338;
	font-weight: 500;
	transition: all 0.2s ease;
	cursor: pointer;
}

.attendance-status-select.has-value {
	border-color: #10b981;
	background: #f0fdf4;
	font-weight: 600;
}

.attendance-status-select:focus {
	outline: none;
	border-color: #2271b1;
	box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
	background: #fff;
}

.take-attendance-footer {
	padding: 20px 30px;
	background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
	border-top: 1px solid #e5e7eb;
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 15px;
}

.take-attendance-actions {
	display: flex;
	gap: 12px;
	align-items: center;
}

.btn-save-attendance {
	padding: 12px 28px;
	background: linear-gradient(135deg, #2271b1 0%, #1e5a8f 100%);
	color: #fff;
	border: none;
	border-radius: 6px;
	font-size: 14px;
	font-weight: 700;
	cursor: pointer;
	height: auto;
	box-shadow: 0 2px 4px rgba(34, 113, 177, 0.3);
	transition: all 0.2s ease;
	display: inline-flex;
	align-items: center;
	gap: 8px;
}

.btn-save-attendance:hover {
	background: linear-gradient(135deg, #135e96 0%, #0d4a73 100%);
	transform: translateY(-1px);
	box-shadow: 0 4px 8px rgba(34, 113, 177, 0.4);
}

.btn-save-attendance:active {
	transform: translateY(0);
}

.save-message {
	font-weight: 600;
	font-size: 14px;
}

.save-message.success {
	color: #10b981;
}

.save-message.error {
	color: #ef4444;
}

.pagination-wrapper {
	display: flex;
	gap: 10px;
	align-items: center;
	font-size: 13px;
	color: #6b7280;
}

@media (max-width: 768px) {
	.take-attendance-select-form {
		flex-direction: column;
	}
	
	.take-attendance-select-form .form-field {
		width: 100%;
	}
	
	.take-attendance-form-header {
		flex-direction: column;
		align-items: flex-start;
	}
	
	.course-schedule-info-compact {
		flex-direction: column;
		align-items: flex-start;
	}
	
	.status-select-wrapper {
		flex-direction: column;
		align-items: flex-start;
	}
	
	.take-attendance-footer {
		flex-direction: column;
		align-items: flex-start;
	}
}
</style>

<div class="wrap">
	<div class="take-attendance-page">
		<!-- Header -->
		<div class="take-attendance-header">
			<h1><?php esc_html_e( 'Tomar Asistencia', 'tutor-attendance-calendar' ); ?></h1>
			<p><?php esc_html_e( 'Gestiona la asistencia de tus estudiantes por curso y fecha.', 'tutor-attendance-calendar' ); ?></p>
		</div>

		<!-- Paso 1: Seleccionar Curso y Fecha -->
		<div class="take-attendance-select-card">
			<h2>
				<span class="dashicons dashicons-filter" style="font-size: 20px; width: 20px; height: 20px;"></span>
				<?php esc_html_e( 'Seleccionar Curso y Fecha', 'tutor-attendance-calendar' ); ?>
			</h2>
			<form method="GET" action="" class="take-attendance-select-form">
				<?php if ( isset( $is_frontend_dashboard ) && $is_frontend_dashboard ) : ?>
					<!-- Frontend: no necesitamos page -->
				<?php else : ?>
					<input type="hidden" name="page" value="tutor-attendance-take">
				<?php endif; ?>
				
				<div class="form-field">
					<label for="course_id"><?php esc_html_e( 'Curso', 'tutor-attendance-calendar' ); ?></label>
					<select name="course_id" id="course_id" required>
						<option value=""><?php esc_html_e( '-- Seleccionar Curso --', 'tutor-attendance-calendar' ); ?></option>
						<?php foreach ( $instructor_courses as $course ) : ?>
							<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $selected_course_id, $course->ID ); ?>>
								<?php echo esc_html( $course->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<div class="form-field" style="flex: 0 0 200px;">
					<label for="attendance_date"><?php esc_html_e( 'Fecha', 'tutor-attendance-calendar' ); ?></label>
					<input type="date" name="attendance_date" id="attendance_date" value="<?php echo esc_attr( $selected_date ); ?>" required>
				</div>
				
				<div style="flex: 0 0 auto;">
					<button type="submit" class="btn-continue">
						<?php esc_html_e( 'Continuar', 'tutor-attendance-calendar' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- Paso 2: Marcar Asistencia -->
		<?php if ( $selected_course_id > 0 && ! empty( $students ) ) : ?>
			<?php
			$selected_course = get_post( $selected_course_id );
			$day_name = date_i18n( 'l', strtotime( $selected_date ) );
			$day_name_es = array(
				'Monday' => 'Lunes',
				'Tuesday' => 'Martes',
				'Wednesday' => 'Miércoles',
				'Thursday' => 'Jueves',
				'Friday' => 'Viernes',
				'Saturday' => 'Sábado',
				'Sunday' => 'Domingo',
			);
			$day_name_display = isset( $day_name_es[ $day_name ] ) ? $day_name_es[ $day_name ] : $day_name;
			?>
			
			<div class="take-attendance-form-card">
				<div class="take-attendance-form-header">
					<div style="flex: 1;">
						<h2>
							<?php echo esc_html( $selected_course->post_title ); ?>
						</h2>
						<div class="take-attendance-course-info">
							<span class="dashicons dashicons-calendar-alt" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle; color: #6366f1;"></span>
							<strong style="color: #111827; font-size: 14px;"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $selected_date ) ) ); ?></strong>
							<span style="margin: 0 10px; color: #9ca3af;">•</span>
							<span style="color: #6b7280; font-size: 13px; text-transform: capitalize;"><?php echo esc_html( $day_name_display ); ?></span>
						</div>
						<?php echo $course_schedule_info; ?>
					</div>
					
					<div class="quick-actions">
						<?php foreach ( $attendance_states as $state ) : ?>
							<button type="button" 
								class="button quick-mark-btn" 
								data-status="<?php echo esc_attr( $state ); ?>"
								title="<?php echo esc_attr( sprintf( __( 'Marcar todos como: %s', 'tutor-attendance-calendar' ), $state ) ); ?>">
								<?php echo esc_html( sprintf( __( 'Todos: %s', 'tutor-attendance-calendar' ), $state ) ); ?>
							</button>
						<?php endforeach; ?>
						<button type="button" class="button quick-clear-btn" title="<?php esc_attr_e( 'Limpiar todos los estados', 'tutor-attendance-calendar' ); ?>">
							<?php esc_html_e( 'Limpiar', 'tutor-attendance-calendar' ); ?>
						</button>
					</div>
				</div>

				<div class="take-attendance-form-body">
					<form id="attendance-form">
						<input type="hidden" name="course_id" value="<?php echo esc_attr( $selected_course_id ); ?>">
						<input type="hidden" name="attendance_date" value="<?php echo esc_attr( $selected_date ); ?>">
						<input type="hidden" name="action" value="tutor_save_attendance">
						<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'tutor_attendance_nonce' ); ?>">
						<?php if ( isset( $is_frontend_dashboard ) && $is_frontend_dashboard ) : ?>
							<input type="hidden" name="is_frontend" value="1">
						<?php endif; ?>
						<input type="hidden" name="current_page" value="<?php echo esc_attr( $current_page ); ?>">

						<table class="take-attendance-table">
							<thead>
								<tr>
									<th style="width: 35%;"><?php esc_html_e( 'Estudiante', 'tutor-attendance-calendar' ); ?></th>
									<th style="width: 30%;"><?php esc_html_e( 'Email', 'tutor-attendance-calendar' ); ?></th>
									<th style="width: 35%;"><?php esc_html_e( 'Estado de Asistencia', 'tutor-attendance-calendar' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $students as $student ) : 
									// Normalizar el objeto estudiante
									$student_id = 0;
									$student_name = '';
									$student_email = '';
									
									if ( is_object( $student ) ) {
										$student_id = isset( $student->ID ) ? intval( $student->ID ) : 0;
										$student_name = isset( $student->display_name ) ? $student->display_name : '';
										$student_email = isset( $student->user_email ) ? $student->user_email : '';
										
										// Si falta información, obtener del usuario
										if ( $student_id > 0 && ( empty( $student_name ) || empty( $student_email ) ) ) {
											$user = get_userdata( $student_id );
											if ( $user ) {
												if ( empty( $student_name ) ) {
													$student_name = $user->display_name;
												}
												if ( empty( $student_email ) ) {
													$student_email = $user->user_email;
												}
											}
										}
									} elseif ( is_numeric( $student ) ) {
										// Si solo es un ID, obtener los datos del usuario
										$user = get_userdata( intval( $student ) );
										if ( $user ) {
											$student_id = $user->ID;
											$student_name = $user->display_name;
											$student_email = $user->user_email;
										}
									}
									
									// Si no tenemos datos válidos, saltar este estudiante
									if ( empty( $student_id ) ) {
										continue;
									}
									
									$current_status = isset( $existing_attendance[ $student_id ] ) ? $existing_attendance[ $student_id ] : '';
									$has_attendance = ! empty( $current_status );
									
									// Clases CSS según el estado
									$row_class = $has_attendance ? 'has-attendance-marked' : '';
									$status_badge_class = '';
									if ( $has_attendance ) {
										$status_lower = strtolower( trim( $current_status ) );
										switch ( $status_lower ) {
											case 'asistió':
											case 'asistio':
												$status_badge_class = 'status-present';
												break;
											case 'falta':
												$status_badge_class = 'status-absent';
												break;
											case 'tarde':
												$status_badge_class = 'status-late';
												break;
											case 'justificado':
												$status_badge_class = 'status-justified';
												break;
											default:
												$status_badge_class = 'status-default';
										}
									}
									?>
									<tr class="<?php echo esc_attr( $row_class ); ?>" data-student-id="<?php echo esc_attr( $student_id ); ?>">
										<td>
											<div class="student-name-cell">
												<?php if ( $has_attendance ) : ?>
													<span class="attendance-indicator" title="<?php esc_attr_e( 'Asistencia ya marcada', 'tutor-attendance-calendar' ); ?>">✓</span>
												<?php endif; ?>
												<span><?php echo esc_html( $student_name ); ?></span>
											</div>
										</td>
										<td>
											<span style="color: #6b7280; font-size: 13px;"><?php echo esc_html( $student_email ); ?></span>
										</td>
										<td>
											<div class="status-select-wrapper">
												<?php if ( $has_attendance ) : ?>
													<span class="attendance-status-badge <?php echo esc_attr( $status_badge_class ); ?>">
														<?php echo esc_html( $current_status ); ?>
													</span>
												<?php endif; ?>
												<select name="attendance[<?php echo esc_attr( $student_id ); ?>]" 
													class="attendance-status-select <?php echo $has_attendance ? 'has-value' : ''; ?>">
													<option value=""><?php esc_html_e( '-- Seleccionar --', 'tutor-attendance-calendar' ); ?></option>
													<?php foreach ( $attendance_states as $state ) : ?>
														<option value="<?php echo esc_attr( $state ); ?>" <?php selected( $current_status, $state ); ?>>
															<?php echo esc_html( $state ); ?>
														</option>
													<?php endforeach; ?>
												</select>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</form>
				</div>

				<div class="take-attendance-footer">
					<!-- Paginación -->
					<?php if ( $total_pages > 1 ) : ?>
						<div class="pagination-wrapper" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin: 20px 0;">
							<span class="displaying-num" style="font-weight: 600; color: #374151; font-size: 13px;">
								<?php
								$start = $offset + 1;
								$end = min( $offset + $per_page, $total_students );
								printf( 
									esc_html__( 'Mostrando %d-%d de %d estudiantes', 'tutor-attendance-calendar' ),
									$start,
									$end,
									$total_students
								);
								?>
							</span>
							<span class="pagination-links" style="display: flex; align-items: center; gap: 8px;">
								<?php
								if ( isset( $is_frontend_dashboard ) && $is_frontend_dashboard ) {
									// Frontend: usar dashboard de Tutor LMS
									// Remover cualquier parámetro paged de la URL base para evitar conflictos
									$base_url = tutor_utils()->tutor_dashboard_url( 'attendance-take' );
									$base_url = remove_query_arg( array( 'paged' ), $base_url );
									// Construir URL base sin rewrite rules (forzar query string)
									$base_url = add_query_arg( array(
										'course_id' => $selected_course_id,
										'attendance_date' => $selected_date,
									), $base_url );
								} else {
									// Admin
									$base_url = add_query_arg( array(
										'page' => 'tutor-attendance-take',
										'course_id' => $selected_course_id,
										'attendance_date' => $selected_date,
									), admin_url( 'admin.php' ) );
								}
								
								// Función helper para crear URLs de paginación con paged como query parameter
								$create_page_url = function( $page_num ) use ( $base_url ) {
									// Remover paged existente y agregar el nuevo
									$url = remove_query_arg( 'paged', $base_url );
									if ( $page_num > 1 ) {
										$url = add_query_arg( 'paged', $page_num, $url );
									}
									return $url;
								};
								
								// Botón Primera página
								if ( $current_page > 1 ) {
									echo '<a class="first-page button" href="' . esc_url( $create_page_url( 1 ) ) . '" title="' . esc_attr__( 'Primera página', 'tutor-attendance-calendar' ) . '" style="padding: 6px 10px; min-width: 36px; text-align: center; text-decoration: none;">«</a>';
								} else {
									echo '<span class="tablenav-pages-navspan button disabled" style="padding: 6px 10px; min-width: 36px; text-align: center; opacity: 0.5; cursor: not-allowed;">«</span>';
								}
								
								// Botón Página anterior
								if ( $current_page > 1 ) {
									$prev_page = $current_page - 1;
									echo '<a class="prev-page button" href="' . esc_url( $create_page_url( $prev_page ) ) . '" title="' . esc_attr__( 'Página anterior', 'tutor-attendance-calendar' ) . '" style="padding: 6px 10px; min-width: 36px; text-align: center; text-decoration: none;">‹</a>';
								} else {
									echo '<span class="tablenav-pages-navspan button disabled" style="padding: 6px 10px; min-width: 36px; text-align: center; opacity: 0.5; cursor: not-allowed;">‹</span>';
								}
								
								// Información de página
								echo '<span class="paging-input" style="display: flex; align-items: center; gap: 6px; margin: 0 8px;">';
								echo '<input class="current-page" type="text" name="paged" value="' . esc_attr( $current_page ) . '" size="2" style="width: 50px; text-align: center; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 4px; font-size: 13px;" data-total-pages="' . esc_attr( $total_pages ) . '">';
								echo '<span class="tablenav-paging-text" style="color: #6b7280; font-size: 13px;">de <span class="total-pages">' . esc_html( $total_pages ) . '</span></span>';
								echo '</span>';
								
								// Botón Página siguiente
								if ( $current_page < $total_pages ) {
									$next_page = $current_page + 1;
									echo '<a class="next-page button" href="' . esc_url( $create_page_url( $next_page ) ) . '" title="' . esc_attr__( 'Página siguiente', 'tutor-attendance-calendar' ) . '" style="padding: 6px 10px; min-width: 36px; text-align: center; text-decoration: none;">›</a>';
								} else {
									echo '<span class="tablenav-pages-navspan button disabled" style="padding: 6px 10px; min-width: 36px; text-align: center; opacity: 0.5; cursor: not-allowed;">›</span>';
								}
								
								// Botón Última página
								if ( $current_page < $total_pages ) {
									echo '<a class="last-page button" href="' . esc_url( $create_page_url( $total_pages ) ) . '" title="' . esc_attr__( 'Última página', 'tutor-attendance-calendar' ) . '" style="padding: 6px 10px; min-width: 36px; text-align: center; text-decoration: none;">»</a>';
								} else {
									echo '<span class="tablenav-pages-navspan button disabled" style="padding: 6px 10px; min-width: 36px; text-align: center; opacity: 0.5; cursor: not-allowed;">»</span>';
								}
								?>
							</span>
						</div>
					<?php else : ?>
						<div></div>
					<?php endif; ?>
					
					<div class="take-attendance-actions">
						<button type="button" class="button auto-save-btn" style="display: none;">
							<?php esc_html_e( 'Guardar antes de cambiar', 'tutor-attendance-calendar' ); ?>
						</button>
						<button type="submit" form="attendance-form" class="btn-save-attendance">
							<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-right: 5px;"></span>
							<?php esc_html_e( 'Guardar Asistencia', 'tutor-attendance-calendar' ); ?>
						</button>
						<span class="save-message"></span>
					</div>
				</div>
			</div>
		<?php elseif ( $selected_course_id > 0 && empty( $students ) ) : ?>
			<div class="notice notice-info" style="margin: 20px 0; padding: 15px; border-left: 4px solid #2271b1;">
				<p><?php esc_html_e( 'Este curso no tiene estudiantes inscritos.', 'tutor-attendance-calendar' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<script type="text/javascript">
// Asegurar que ajaxurl esté disponible en frontend ANTES de que jQuery esté listo
if (typeof ajaxurl === 'undefined') {
	var ajaxurl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
}

jQuery(document).ready(function($) {
	var form = $('#attendance-form');
	var isSaving = false;
	
	// Función para guardar asistencia
	function saveAttendance(callback) {
		if (isSaving) return;
		
		isSaving = true;
		var submitBtn = form.find('button[type="submit"]');
		var messageSpan = $('.save-message');
		
		submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite; vertical-align: middle; margin-right: 5px;"></span><?php esc_attr_e( 'Guardando...', 'tutor-attendance-calendar' ); ?>');
		messageSpan.text('<?php esc_attr_e( 'Guardando...', 'tutor-attendance-calendar' ); ?>').removeClass('error success').css('color', '#666');
		
		var formData = form.serialize();
		
		// Determinar URL de AJAX (frontend o admin)
		var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		if (typeof tutorAttendance !== 'undefined' && tutorAttendance.ajaxurl) {
			ajaxUrl = tutorAttendance.ajaxurl;
		} else if (typeof ajaxurl !== 'undefined' && ajaxurl) {
			ajaxUrl = ajaxurl;
		}
		
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: formData,
			success: function(response) {
				isSaving = false;
				if (response.success) {
					messageSpan.text(response.data.message).addClass('success');
					// Limpiar indicadores de cambios
					form.find('tr.has-changes').removeClass('has-changes');
					if (typeof callback === 'function') {
						callback();
					} else {
						// Después de guardar, avanzar a la siguiente página automáticamente si existe
						var nextPageLink = $('.next-page:not(.disabled)');
						
						if (nextPageLink.length > 0) {
							// Hay siguiente página, usar su URL directamente
							var nextPageUrl = nextPageLink.attr('href');
							setTimeout(function() {
								window.location.href = nextPageUrl;
							}, 1000);
						} else {
							// No hay más páginas, solo recargar
							setTimeout(function() {
								location.reload();
							}, 1500);
						}
					}
				} else {
					var errorMsg = response.data && response.data.message ? response.data.message : '<?php esc_attr_e( 'Error al guardar', 'tutor-attendance-calendar' ); ?>';
					messageSpan.text(errorMsg).addClass('error');
					submitBtn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-right: 5px;"></span><?php esc_attr_e( 'Guardar Asistencia', 'tutor-attendance-calendar' ); ?>');
				}
			},
			error: function(xhr, status, error) {
				isSaving = false;
				var errorMsg = '<?php esc_attr_e( 'Error de conexión', 'tutor-attendance-calendar' ); ?>';
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					errorMsg = xhr.responseJSON.data.message;
				}
				messageSpan.text(errorMsg).addClass('error');
				submitBtn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-right: 5px;"></span><?php esc_attr_e( 'Guardar Asistencia', 'tutor-attendance-calendar' ); ?>');
				console.error('Error al guardar asistencia:', error, xhr);
			}
		});
	}
	
	// Guardar al enviar el formulario
	form.on('submit', function(e) {
		e.preventDefault();
		saveAttendance();
	});
	
	// Botones de acción rápida - Marcar todos con un estado
	$('.quick-mark-btn').on('click', function() {
		var status = $(this).data('status');
		form.find('select.attendance-status-select').each(function() {
			$(this).val(status).trigger('change');
		});
		// Resaltar visualmente
		$(this).addClass('button-primary').siblings('.quick-mark-btn').removeClass('button-primary');
	});
	
	// Botón limpiar todo
	$('.quick-clear-btn').on('click', function() {
		if (confirm('<?php esc_attr_e( '¿Estás seguro de limpiar todos los estados?', 'tutor-attendance-calendar' ); ?>')) {
			form.find('select.attendance-status-select').val('').trigger('change');
			$('.quick-mark-btn').removeClass('button-primary');
		}
	});
	
	// Guardar automáticamente antes de cambiar de página
	$('.first-page, .prev-page, .next-page, .last-page').on('click', function(e) {
		var href = $(this).attr('href');
		if (href && !$(this).hasClass('disabled')) {
			e.preventDefault();
			saveAttendance(function() {
				window.location.href = href;
			});
		}
	});
	
	// Atajos de teclado
	$(document).on('keydown', function(e) {
		if (!form.length) return;
		
		// Ctrl/Cmd + S para guardar
		if ((e.ctrlKey || e.metaKey) && e.key === 's') {
			e.preventDefault();
			form.submit();
		}
		
		// Números 1-4 para marcar rápidamente (si hay 4 estados o menos)
		var states = <?php echo json_encode( $attendance_states ); ?>;
		if (states.length <= 4 && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
			var num = parseInt(e.key);
			if (num >= 1 && num <= states.length) {
				var status = states[num - 1];
				var focusedSelect = $('select.attendance-status-select:focus');
				if (focusedSelect.length) {
					focusedSelect.val(status).trigger('change');
				}
			}
		}
	});
	
	// Resaltar cambios no guardados
	form.find('select.attendance-status-select').on('change', function() {
		$(this).closest('tr').addClass('has-changes');
		$(this).addClass('has-value');
	});
	
	// Permitir escribir número de página y redirigir al presionar Enter
	$('.current-page').on('keypress', function(e) {
		if (e.which === 13) { // Enter
			e.preventDefault();
			var pageNum = parseInt($(this).val()) || 1;
			var totalPages = parseInt($(this).data('total-pages')) || 1;
			pageNum = Math.max(1, Math.min(pageNum, totalPages));
			
			// Obtener la URL base del botón siguiente/anterior
			var baseUrl = window.location.href.split('?')[0];
			var urlParams = new URLSearchParams(window.location.search);
			urlParams.set('paged', pageNum);
			
			// Mantener los otros parámetros
			var courseId = urlParams.get('course_id');
			var attendanceDate = urlParams.get('attendance_date');
			
			// Construir nueva URL
			var newUrl = baseUrl + '?';
			if (courseId) newUrl += 'course_id=' + courseId + '&';
			if (attendanceDate) newUrl += 'attendance_date=' + attendanceDate + '&';
			newUrl += 'paged=' + pageNum;
			
			window.location.href = newUrl;
		}
	});
	
	// Animación de spinner
	$('<style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>').appendTo('head');
});
</script>
