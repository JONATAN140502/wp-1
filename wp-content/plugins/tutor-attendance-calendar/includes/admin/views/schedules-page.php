<?php
/**
 * Vista: Horarios de Cursos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<style>
.tutor-schedules-page {
	max-width: 1600px;
	margin: 0 auto;
}

.tutor-schedules-header-card {
	background: #fff;
	color: #1d2327;
	padding: 20px 25px;
	border-radius: 4px;
	margin-bottom: 20px;
	border-left: 4px solid #2271b1;
}

.tutor-schedules-header-card h1 {
	margin: 0 0 8px 0;
	font-size: 23px;
	font-weight: 600;
	color: #1d2327;
}

.tutor-schedules-header-card p {
	margin: 0;
	color: #646970;
	font-size: 14px;
}

.tutor-course-select-card {
	background: #fff;
	padding: 20px 25px;
	border-radius: 4px;
	margin-bottom: 20px;
	border: 1px solid #c3c4c7;
}

.tutor-course-select-card h2 {
	margin: 0 0 15px 0;
	font-size: 16px;
	font-weight: 600;
	color: #1d2327;
}

.tutor-course-select-form {
	display: flex;
	gap: 12px;
	align-items: flex-end;
	flex-wrap: wrap;
}

.tutor-course-select-form .form-field {
	flex: 1;
	min-width: 320px;
}

.tutor-course-select-form label {
	display: block;
	margin-bottom: 7px;
	font-weight: 600;
	color: #1d2327;
	font-size: 13px;
}

.tutor-course-select-form select {
	width: 100%;
	padding: 8px 12px;
	border: 1px solid #8c8f94;
	border-radius: 3px;
	font-size: 14px;
	background: #fff;
	color: #2c3338;
}

.tutor-course-select-form select:focus {
	outline: none;
	border-color: #2271b1;
	box-shadow: 0 0 0 1px #2271b1;
}

.tutor-course-select-form .btn-submit {
	padding: 8px 20px;
	background: #2271b1;
	color: #fff;
	border: 1px solid #2271b1;
	border-radius: 3px;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	white-space: nowrap;
}

.tutor-course-select-form .btn-submit:hover {
	background: #135e96;
	border-color: #135e96;
}

.tutor-schedules-main-card {
	background: #fff;
	padding: 0;
	border-radius: 4px;
	border: 1px solid #c3c4c7;
	overflow: hidden;
}

.tutor-schedules-card-header {
	background: #f0f0f1;
	color: #1d2327;
	padding: 15px 20px;
	margin: 0;
	border-bottom: 1px solid #c3c4c7;
}

.tutor-schedules-card-header h2 {
	margin: 0 0 5px 0;
	font-size: 18px;
	font-weight: 600;
	color: #1d2327;
}

.tutor-schedules-card-header p {
	margin: 0;
	color: #646970;
	font-size: 13px;
}

.tutor-course-period-section {
	background: #f6f7f7;
	padding: 20px 25px;
	margin: 0;
	border-bottom: 1px solid #c3c4c7;
}

.tutor-course-period-section h3 {
	margin: 0 0 12px 0;
	font-size: 16px;
	font-weight: 600;
	color: #1d2327;
	display: flex;
	align-items: center;
	gap: 8px;
}

.tutor-course-period-section p {
	margin: 0 0 18px 0;
	color: #646970;
	font-size: 13px;
}

.tutor-course-period-section .period-fields {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
	gap: 18px;
}

.tutor-course-period-section .period-field {
	position: relative;
}

.tutor-course-period-section label {
	display: block;
	margin-bottom: 7px;
	font-weight: 600;
	font-size: 13px;
	color: #1d2327;
}

.tutor-course-period-section input[type="date"] {
	width: 100%;
	padding: 8px 12px;
	border: 1px solid #8c8f94;
	border-radius: 3px;
	font-size: 14px;
	background: #fff;
	color: #2c3338;
}

.tutor-course-period-section input[type="date"]:focus {
	outline: none;
	border-color: #2271b1;
	box-shadow: 0 0 0 1px #2271b1;
}

.tutor-course-period-section small {
	display: block;
	margin-top: 5px;
	color: #646970;
	font-size: 11px;
}

.tutor-schedules-days-container {
	padding: 25px 28px;
}

.tutor-schedules-days-container h3 {
	margin: 0 0 20px 0;
	font-size: 18px;
	font-weight: 600;
	color: #1d2327;
	padding-bottom: 12px;
	border-bottom: 1px solid #c3c4c7;
}

.tutor-days-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
	gap: 15px;
}

.tutor-day-card {
	background: #fff;
	padding: 15px 18px;
	border-radius: 4px;
	border: 1px solid #c3c4c7;
}

.tutor-day-card h4 {
	margin: 0 0 15px 0;
	font-size: 15px;
	font-weight: 600;
	color: #1d2327;
	padding-bottom: 10px;
	border-bottom: 1px solid #dcdcde;
	display: flex;
	align-items: center;
	gap: 8px;
}

.tutor-day-card .day-icon {
	font-size: 18px;
}

.tutor-day-schedules {
	min-height: 60px;
}

.tutor-schedule-item {
	background: #f9f9f9;
	padding: 12px 15px;
	border-radius: 3px;
	margin-bottom: 10px;
	border: 1px solid #c3c4c7;
}

.tutor-schedule-item .schedule-fields {
	display: grid;
	grid-template-columns: 1fr 1fr auto;
	gap: 12px;
	align-items: end;
}

.tutor-schedule-item .time-field {
	position: relative;
}

.tutor-schedule-item label {
	display: block;
	margin-bottom: 6px;
	font-weight: 600;
	font-size: 11px;
	color: #1d2327;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

.tutor-schedule-item input[type="time"] {
	width: 100%;
	padding: 8px 10px;
	border: 1px solid #8c8f94;
	border-radius: 3px;
	font-size: 14px;
	background: #fff;
	color: #2c3338;
	font-weight: 500;
}

.tutor-schedule-item input[type="time"]:focus {
	outline: none;
	border-color: #2271b1;
	box-shadow: 0 0 0 1px #2271b1;
}

.tutor-schedule-item .btn-remove {
	padding: 6px 12px;
	background: #fff;
	color: #b32d2e;
	border: 1px solid #b32d2e;
	border-radius: 3px;
	font-size: 12px;
	font-weight: 600;
	cursor: pointer;
	white-space: nowrap;
	display: flex;
	align-items: center;
	gap: 5px;
	height: fit-content;
}

.tutor-schedule-item .btn-remove:hover {
	background: #b32d2e;
	color: #fff;
}

.tutor-day-card .btn-add {
	padding: 8px 16px;
	background: #2271b1;
	color: #fff;
	border: 1px solid #2271b1;
	border-radius: 3px;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	width: 100%;
	justify-content: center;
	margin-top: 5px;
}

.tutor-day-card .btn-add:hover {
	background: #135e96;
	border-color: #135e96;
}

.tutor-schedules-submit {
	padding: 20px 25px;
	background: #f6f7f7;
	border-top: 1px solid #c3c4c7;
	text-align: right;
}

.tutor-schedules-submit .btn-save {
	padding: 10px 25px;
	background: #2271b1;
	color: #fff;
	border: 1px solid #2271b1;
	border-radius: 3px;
	font-size: 14px;
	font-weight: 600;
	cursor: pointer;
	display: inline-flex;
	align-items: center;
	gap: 8px;
}

.tutor-schedules-submit .btn-save:hover {
	background: #135e96;
	border-color: #135e96;
}

@keyframes slideIn {
	from {
		opacity: 0;
		transform: translateY(-8px);
	}
	to {
		opacity: 1;
		transform: translateY(0);
	}
}

@media (max-width: 1200px) {
	.tutor-days-grid {
		grid-template-columns: repeat(2, 1fr);
	}
}

@media (max-width: 768px) {
	.tutor-days-grid {
		grid-template-columns: 1fr;
	}
	
	.tutor-course-select-form {
		flex-direction: column;
	}
	
	.tutor-course-select-form .form-field {
		min-width: 100%;
	}
	
	.tutor-schedule-item .schedule-fields {
		grid-template-columns: 1fr;
	}
	
	.tutor-schedules-submit {
		text-align: center;
	}
}
</style>

<div class="wrap">
	<div class="tutor-schedules-page">
		<div class="tutor-schedules-header-card">
			<h1><?php esc_html_e( 'Horarios de Cursos', 'tutor-attendance-calendar' ); ?></h1>
			<p><?php esc_html_e( 'Gestiona los horarios de clases y períodos de cada curso de forma eficiente.', 'tutor-attendance-calendar' ); ?></p>
		</div>

		<!-- Seleccionar Curso -->
		<div class="tutor-course-select-card">
			<h2><?php esc_html_e( 'Seleccionar Curso', 'tutor-attendance-calendar' ); ?></h2>
			<?php if ( isset( $is_frontend_dashboard ) && $is_frontend_dashboard ) : ?>
				<?php
				// Frontend: usar URL del dashboard de Tutor LMS
				$form_action = tutor_utils()->tutor_dashboard_url( 'attendance-schedules' );
				?>
				<form method="GET" action="<?php echo esc_url( $form_action ); ?>" class="tutor-course-select-form">
			<?php else : ?>
				<?php
				// Admin
				$form_action = admin_url( 'admin.php' );
				?>
				<form method="GET" action="<?php echo esc_url( $form_action ); ?>" class="tutor-course-select-form">
				<input type="hidden" name="page" value="tutor-attendance-schedules">
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
				
				<button type="submit" class="btn-submit">
					<?php esc_html_e( 'Continuar', 'tutor-attendance-calendar' ); ?>
				</button>
			</form>
		</div>

		<!-- Formulario de Horarios -->
		<?php if ( $selected_course_id > 0 ) : ?>
			<?php
			// Asegurar que las variables estén definidas
			if ( ! isset( $schedules_by_day ) ) {
				$schedules_by_day = array();
			}
			if ( ! isset( $days_of_week ) ) {
				$days_of_week = array(
					0 => __( 'Domingo', 'tutor-attendance-calendar' ),
					1 => __( 'Lunes', 'tutor-attendance-calendar' ),
					2 => __( 'Martes', 'tutor-attendance-calendar' ),
					3 => __( 'Miércoles', 'tutor-attendance-calendar' ),
					4 => __( 'Jueves', 'tutor-attendance-calendar' ),
					5 => __( 'Viernes', 'tutor-attendance-calendar' ),
					6 => __( 'Sábado', 'tutor-attendance-calendar' ),
				);
			}
			
			$selected_course = get_post( $selected_course_id );
			$course_date_from = get_post_meta( $selected_course_id, '_tutor_attendance_date_from', true );
			$course_date_to = get_post_meta( $selected_course_id, '_tutor_attendance_date_to', true );
			$course_use_schedules = get_post_meta( $selected_course_id, '_tutor_attendance_use_schedules', true );
			
			// Opciones de alumno del curso
			$course_student_can_mark = get_post_meta( $selected_course_id, '_tutor_attendance_student_can_mark', true );
			if ( $course_student_can_mark === '' ) {
				$course_student_can_mark = true; // Valor por defecto
			} else {
				$course_student_can_mark = (bool) $course_student_can_mark;
			}
			
			$course_deadline_hours = get_post_meta( $selected_course_id, '_tutor_attendance_deadline_hours', true );
			if ( $course_deadline_hours === '' ) {
				$course_deadline_hours = 24; // Valor por defecto
			} else {
				$course_deadline_hours = intval( $course_deadline_hours );
			}
			
			$course_deadline_minutes = get_post_meta( $selected_course_id, '_tutor_attendance_deadline_minutes', true );
			if ( $course_deadline_minutes === '' ) {
				$course_deadline_minutes = 0; // Valor por defecto
			} else {
				$course_deadline_minutes = intval( $course_deadline_minutes );
			}
			
			$day_icons = array(
				0 => '🌅',
				1 => '📅',
				2 => '📆',
				3 => '🗓️',
				4 => '📋',
				5 => '📊',
				6 => '🎯',
			);
			?>
			
			<div class="tutor-schedules-main-card">
				<div class="tutor-schedules-card-header">
					<h2><?php echo esc_html( $selected_course->post_title ); ?></h2>
					<p><?php esc_html_e( 'Configura los horarios de clase y el período del curso', 'tutor-attendance-calendar' ); ?></p>
				</div>

				<form method="POST" action="" id="schedules-form">
					<?php wp_nonce_field( 'tutor_attendance_schedules' ); ?>
					<input type="hidden" name="course_id" value="<?php echo esc_attr( $selected_course_id ); ?>">

					<!-- Fechas del curso -->
					<div class="tutor-course-period-section">
						<h3>
							<span class="dashicons dashicons-calendar-alt" style="vertical-align: middle;"></span>
							<?php esc_html_e( 'Período del Curso', 'tutor-attendance-calendar' ); ?>
						</h3>
						<p><?php esc_html_e( 'Define el rango de fechas en el que este curso está activo. La asistencia solo podrá marcarse dentro de este período.', 'tutor-attendance-calendar' ); ?></p>
						
						<div class="period-fields">
							<div class="period-field">
								<label for="course_date_from"><?php esc_html_e( 'Fecha de inicio', 'tutor-attendance-calendar' ); ?></label>
								<input 
									type="date" 
									name="course_date_from" 
									id="course_date_from"
									value="<?php echo ! empty( $course_date_from ) ? esc_attr( $course_date_from ) : ''; ?>">
								<small><?php esc_html_e( 'Opcional - Dejar vacío para sin límite', 'tutor-attendance-calendar' ); ?></small>
							</div>
							
							<div class="period-field">
								<label for="course_date_to"><?php esc_html_e( 'Fecha de fin', 'tutor-attendance-calendar' ); ?></label>
								<input 
									type="date" 
									name="course_date_to" 
									id="course_date_to"
									value="<?php echo ! empty( $course_date_to ) ? esc_attr( $course_date_to ) : ''; ?>">
								<small><?php esc_html_e( 'Opcional - Dejar vacío para sin límite', 'tutor-attendance-calendar' ); ?></small>
							</div>
						</div>
						
						<!-- Opción de usar horarios para este curso -->
						<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #c3c4c7;">
							<label for="course_use_schedules" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
								<input 
									type="checkbox" 
									name="course_use_schedules" 
									id="course_use_schedules" 
									value="1" 
									<?php checked( $course_use_schedules, 1 ); ?>
									style="margin: 0; width: 18px; height: 18px;">
								<span style="font-weight: 600; font-size: 14px; color: #1d2327;">
									<?php esc_html_e( 'Usar horarios de este curso para validar asistencia', 'tutor-attendance-calendar' ); ?>
								</span>
							</label>
							<p class="description" style="margin-top: 8px; margin-left: 28px; color: #646970; font-size: 13px;">
								<?php esc_html_e( 'Si está habilitado, los estudiantes y docentes solo podrán marcar asistencia durante los horarios definidos arriba para este curso. Si está deshabilitado, se podrá marcar asistencia en cualquier momento dentro del período del curso.', 'tutor-attendance-calendar' ); ?>
							</p>
						</div>
					</div>
					
					<!-- Opciones de Alumno para este curso -->
					<div class="tutor-course-period-section" style="background: #fff; border-bottom: 0;">
						<h3>
							<span class="dashicons dashicons-groups" style="vertical-align: middle;"></span>
							<?php esc_html_e( 'Opciones de Alumno para este Curso', 'tutor-attendance-calendar' ); ?>
						</h3>
						<p style="margin: 0 0 18px 0; color: #646970; font-size: 13px;">
							<?php esc_html_e( 'Configura si los alumnos pueden marcar su propia asistencia y el tiempo límite para hacerlo en este curso específico.', 'tutor-attendance-calendar' ); ?>
						</p>
						
						<table class="form-table" style="margin-top: 15px;">
							<tbody>
								<tr>
									<th scope="row" style="width: 250px; padding: 10px 0;">
										<label for="course_student_can_mark" style="font-weight: 600;">
											<?php esc_html_e( 'Alumno puede marcar asistencia', 'tutor-attendance-calendar' ); ?>
										</label>
									</th>
									<td style="padding: 10px 0;">
										<fieldset>
											<label for="course_student_can_mark" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
												<input 
													type="checkbox" 
													name="course_student_can_mark" 
													id="course_student_can_mark" 
													value="1" 
													<?php checked( $course_student_can_mark, true ); ?>
													style="margin: 0;">
												<span style="font-size: 14px;">
													<?php esc_html_e( 'Permitir que los alumnos marquen su propia asistencia', 'tutor-attendance-calendar' ); ?>
												</span>
											</label>
											<p class="description" style="margin-top: 8px; margin-left: 0; color: #646970;">
												<?php esc_html_e( 'Si está habilitado, los estudiantes podrán marcar su asistencia desde su panel o desde la página de lección.', 'tutor-attendance-calendar' ); ?>
											</p>
										</fieldset>
									</td>
								</tr>
								<tr>
									<th scope="row" style="width: 250px; padding: 10px 0;">
										<label for="course_deadline_hours" style="font-weight: 600;">
											<?php esc_html_e( 'Tiempo máximo límite', 'tutor-attendance-calendar' ); ?>
										</label>
									</th>
									<td style="padding: 10px 0;">
										<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
											<div style="display: flex; align-items: center; gap: 5px;">
												<input 
													type="number" 
													name="course_deadline_hours" 
													id="course_deadline_hours" 
													value="<?php echo esc_attr( $course_deadline_hours ); ?>" 
													min="0" 
													max="168" 
													class="small-text" 
													style="width: 80px; padding: 6px 10px;"
													required>
												<span style="color: #646970; font-weight: 500;">
													<?php esc_html_e( 'horas', 'tutor-attendance-calendar' ); ?>
												</span>
											</div>
											
											<span style="color: #646970;">y</span>
											
											<div style="display: flex; align-items: center; gap: 5px;">
												<input 
													type="number" 
													name="course_deadline_minutes" 
													id="course_deadline_minutes" 
													value="<?php echo esc_attr( $course_deadline_minutes ); ?>" 
													min="0" 
													max="59" 
													class="small-text" 
													style="width: 80px; padding: 6px 10px;"
													required>
												<span style="color: #646970; font-weight: 500;">
													<?php esc_html_e( 'minutos', 'tutor-attendance-calendar' ); ?>
												</span>
											</div>
										</div>
										<p class="description" style="margin-top: 8px; margin-left: 0; color: #646970;">
											<?php esc_html_e( 'Tiempo límite después de la fecha para que el alumno pueda marcar asistencia. Puedes configurar horas y minutos para mayor precisión.', 'tutor-attendance-calendar' ); ?>
										</p>
										<div style="margin-top: 10px; padding: 10px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 2px;">
											<p style="margin: 0; font-size: 13px; color: #0a4b78;">
												<strong><?php esc_html_e( 'Ejemplos:', 'tutor-attendance-calendar' ); ?></strong><br>
												<?php esc_html_e( '• 24 horas y 0 minutos = hasta el día siguiente a las 23:59', 'tutor-attendance-calendar' ); ?><br>
												<?php esc_html_e( '• 12 horas y 30 minutos = hasta 12 horas y media después del día', 'tutor-attendance-calendar' ); ?><br>
												<?php esc_html_e( '• 0 horas y 0 minutos = solo el mismo día hasta las 23:59', 'tutor-attendance-calendar' ); ?>
											</p>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
					</div>

					<div class="tutor-schedules-days-container">
						<h3><?php esc_html_e( 'Horarios Semanales', 'tutor-attendance-calendar' ); ?></h3>

						<div class="tutor-days-grid">
							<?php foreach ( $days_of_week as $day_num => $day_name ) : ?>
								<?php
								$day_schedules = isset( $schedules_by_day[ $day_num ] ) ? $schedules_by_day[ $day_num ] : array();
								?>
								<div class="tutor-day-card">
									<h4>
										<span class="day-icon"><?php echo esc_html( $day_icons[ $day_num ] ); ?></span>
										<?php echo esc_html( $day_name ); ?>
									</h4>
									
									<div class="tutor-day-schedules schedule-rows" data-day="<?php echo esc_attr( $day_num ); ?>">
										<?php if ( ! empty( $day_schedules ) ) : ?>
											<?php foreach ( $day_schedules as $index => $schedule ) : ?>
												<div class="tutor-schedule-item">
													<input type="hidden" name="schedules[<?php echo esc_attr( $day_num ); ?>][<?php echo esc_attr( $index ); ?>][day]" value="<?php echo esc_attr( $day_num ); ?>">
													
													<div class="schedule-fields">
														<div class="time-field">
															<label><?php esc_html_e( 'Inicio', 'tutor-attendance-calendar' ); ?></label>
															<input 
																type="time" 
																name="schedules[<?php echo esc_attr( $day_num ); ?>][<?php echo esc_attr( $index ); ?>][start_time]" 
																value="<?php echo esc_attr( substr( $schedule->start_time, 0, 5 ) ); ?>" 
																required>
														</div>
														
														<div class="time-field">
															<label><?php esc_html_e( 'Fin', 'tutor-attendance-calendar' ); ?></label>
															<input 
																type="time" 
																name="schedules[<?php echo esc_attr( $day_num ); ?>][<?php echo esc_attr( $index ); ?>][end_time]" 
																value="<?php echo esc_attr( substr( $schedule->end_time, 0, 5 ) ); ?>" 
																required>
														</div>
														
														<button type="button" class="btn-remove remove-schedule">
															<span class="dashicons dashicons-trash"></span>
														</button>
													</div>
												</div>
											<?php endforeach; ?>
										<?php endif; ?>
									</div>
									
									<button type="button" class="btn-add add-schedule-btn" data-day="<?php echo esc_attr( $day_num ); ?>">
										<span class="dashicons dashicons-plus-alt"></span>
										<?php esc_html_e( 'Agregar Horario', 'tutor-attendance-calendar' ); ?>
									</button>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="tutor-schedules-submit">
						<button type="submit" name="save_schedules" class="btn-save">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Guardar Horarios', 'tutor-attendance-calendar' ); ?>
						</button>
					</div>
				</form>
			</div>
		<?php endif; ?>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	var scheduleIndex = {};
	
	// Inicializar índices por día
	<?php 
	if ( ! isset( $schedules_by_day ) ) {
		$schedules_by_day = array();
	}
	foreach ( $days_of_week as $day_num => $day_name ) : 
		$day_count = isset( $schedules_by_day[ $day_num ] ) && is_array( $schedules_by_day[ $day_num ] ) ? count( $schedules_by_day[ $day_num ] ) : 0;
	?>
		scheduleIndex[<?php echo esc_js( $day_num ); ?>] = <?php echo intval( $day_count ); ?>;
	<?php endforeach; ?>

	// Agregar nuevo horario
	$(document).on('click', '.add-schedule-btn', function() {
		var day = $(this).data('day');
		var index = scheduleIndex[day] || 0;
		scheduleIndex[day] = index + 1;
		
		var newRow = $('<div class="tutor-schedule-item" style="animation: slideIn 0.3s ease;">' +
			'<input type="hidden" name="schedules[' + day + '][' + index + '][day]" value="' + day + '">' +
			'<div class="schedule-fields">' +
			'<div class="time-field">' +
			'<label><?php esc_html_e( "Inicio", "tutor-attendance-calendar" ); ?></label>' +
			'<input type="time" name="schedules[' + day + '][' + index + '][start_time]" required>' +
			'</div>' +
			'<div class="time-field">' +
			'<label><?php esc_html_e( "Fin", "tutor-attendance-calendar" ); ?></label>' +
			'<input type="time" name="schedules[' + day + '][' + index + '][end_time]" required>' +
			'</div>' +
			'<button type="button" class="btn-remove remove-schedule">' +
			'<span class="dashicons dashicons-trash"></span>' +
			'</button></div></div>');
		
		$(this).siblings('.schedule-rows').append(newRow);
		newRow.find('input[type="time"]').first().focus();
		$('html, body').animate({
			scrollTop: newRow.offset().top - 100
		}, 300);
	});

	// Eliminar horario
	$(document).on('click', '.remove-schedule', function() {
		$(this).closest('.tutor-schedule-item').fadeOut(250, function() {
			$(this).remove();
		});
	});

	// Validación del formulario
	$('#schedules-form').on('submit', function(e) {
		var hasError = false;
		
		// Validar fechas del curso
		var courseDateFrom = $('#course_date_from').val();
		var courseDateTo = $('#course_date_to').val();
		
		if (courseDateFrom && courseDateTo && courseDateFrom > courseDateTo) {
			hasError = true;
			$('#course_date_from, #course_date_to').css('border-color', '#b32d2e');
			alert('<?php esc_attr_e( "La fecha de inicio del curso debe ser menor o igual que la fecha de fin.", "tutor-attendance-calendar" ); ?>');
		}
		
		// Validar horarios
		$('.tutor-schedule-item').each(function() {
			var $row = $(this);
			var startTime = $row.find('input[name*="[start_time]"]').val();
			var endTime = $row.find('input[name*="[end_time]"]').val();
			
			if (startTime && endTime && startTime >= endTime) {
				hasError = true;
				$row.find('input').css('border-color', '#b32d2e');
				alert('<?php esc_attr_e( "La hora de inicio debe ser menor que la hora de fin.", "tutor-attendance-calendar" ); ?>');
				return false;
			}
		});

		if (hasError) {
			e.preventDefault();
			return false;
		}
	});
});
</script>
