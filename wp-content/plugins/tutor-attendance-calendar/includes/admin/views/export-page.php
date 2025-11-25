<?php
/**
 * Vista: Exportar Reportes de Asistencia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<style>
.tutor-export-header {
	background: #667eea;
	color: #fff;
	padding: 30px 35px;
	border-radius: 8px;
	margin-bottom: 25px;
	box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.tutor-export-header h1 {
	color: #fff;
	margin: 0 0 10px 0;
	font-size: 28px;
	font-weight: 600;
}

.tutor-export-header p {
	color: rgba(255, 255, 255, 0.9);
	margin: 0;
	font-size: 14px;
}

.tutor-export-card {
	background: #fff;
	padding: 30px;
	border-radius: 8px;
	border: 1px solid #e0e0e0;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
	margin-bottom: 25px;
}

.tutor-export-card h2 {
	margin: 0 0 20px 0;
	font-size: 20px;
	font-weight: 600;
	color: #1d2327;
	padding-bottom: 15px;
	border-bottom: 2px solid #f0f0f1;
}

.tutor-export-form {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 20px;
	margin-bottom: 25px;
}

.tutor-export-form .form-field {
	display: flex;
	flex-direction: column;
}

.tutor-export-form label {
	display: block;
	margin-bottom: 8px;
	font-weight: 600;
	color: #1d2327;
	font-size: 13px;
}

.tutor-export-form select,
.tutor-export-form input[type="date"] {
	width: 100%;
	padding: 10px 12px;
	border: 1px solid #8c8f94;
	border-radius: 4px;
	font-size: 14px;
	background: #fff;
	color: #2c3338;
	transition: border-color 0.2s;
}

.tutor-export-form select:focus,
.tutor-export-form input[type="date"]:focus {
	outline: none;
	border-color: #2271b1;
	box-shadow: 0 0 0 1px #2271b1;
}

.tutor-export-actions {
	display: flex;
	gap: 15px;
	align-items: center;
	padding-top: 25px;
	border-top: 1px solid #e0e0e0;
}

.btn-export-excel {
	display: inline-flex;
	align-items: center;
	gap: 10px;
	padding: 12px 30px;
	background: #28a745;
	color: #fff;
	border: none;
	border-radius: 6px;
	font-weight: 600;
	font-size: 15px;
	cursor: pointer;
	transition: all 0.3s ease;
	text-decoration: none;
}

.btn-export-excel:hover {
	background: #218838;
	transform: translateY(-2px);
	box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
	color: #fff;
}

.btn-export-excel .dashicons {
	font-size: 20px;
	width: 20px;
	height: 20px;
}

.tutor-export-info {
	background: #f0f6fc;
	border-left: 4px solid #2271b1;
	padding: 15px 20px;
	border-radius: 4px;
	margin-top: 20px;
}

.tutor-export-info p {
	margin: 0 0 10px 0;
	color: #0a4b78;
	font-size: 13px;
}

.tutor-export-info p:last-child {
	margin-bottom: 0;
}

.tutor-export-info strong {
	color: #1d2327;
}

.tutor-help-box {
	background: #fff3cd;
	border-left: 4px solid #ffb900;
	padding: 15px 20px;
	border-radius: 4px;
	margin-top: 20px;
}

.tutor-help-box p {
	margin: 0;
	color: #856404;
	font-size: 13px;
}

.tutor-help-box ul {
	margin: 10px 0 0 0;
	padding-left: 20px;
	color: #856404;
	font-size: 13px;
}

.tutor-help-box li {
	margin-bottom: 5px;
}
</style>

<div class="wrap">
	<div class="tutor-export-header">
		<h1><?php esc_html_e( 'Exportar Reportes de Asistencia', 'tutor-attendance-calendar' ); ?></h1>
		<p><?php esc_html_e( 'Genera y descarga reportes detallados de asistencia en formato Excel para análisis y archivo', 'tutor-attendance-calendar' ); ?></p>
	</div>

	<div class="tutor-export-card">
		<h2>
			<span class="dashicons dashicons-media-spreadsheet" style="vertical-align: middle; margin-right: 8px; color: #2271b1;"></span>
			<?php esc_html_e( 'Configurar Filtros de Exportación', 'tutor-attendance-calendar' ); ?>
		</h2>

		<form method="POST" action="" id="export-form">
			<?php wp_nonce_field( 'tutor_export_attendance', 'export_nonce' ); ?>
			
			<div class="tutor-export-form">
				<div class="form-field">
					<label for="course_id">
						<span class="dashicons dashicons-book" style="font-size: 16px; vertical-align: middle;"></span>
						<?php esc_html_e( 'Curso', 'tutor-attendance-calendar' ); ?>
					</label>
					<select name="course_id" id="course_id">
						<option value="0"><?php esc_html_e( 'Todos los cursos', 'tutor-attendance-calendar' ); ?></option>
						<?php foreach ( $courses as $course ) : ?>
							<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( isset( $_GET['course_id'] ) ? intval( $_GET['course_id'] ) : 0, $course->ID ); ?>>
								<?php echo esc_html( $course->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<?php if ( isset( $is_admin ) && $is_admin ) : ?>
				<div class="form-field">
					<label for="instructor_id">
						<span class="dashicons dashicons-admin-users" style="font-size: 16px; vertical-align: middle;"></span>
						<?php esc_html_e( 'Docente', 'tutor-attendance-calendar' ); ?>
					</label>
					<select name="instructor_id" id="instructor_id">
						<option value="0"><?php esc_html_e( 'Todos los docentes', 'tutor-attendance-calendar' ); ?></option>
						<?php 
						$all_instructors = isset( $instructors ) ? $instructors : array();
						if ( empty( $all_instructors ) && function_exists( 'get_users' ) ) {
							$all_instructors = get_users( array( 'role' => tutor()->instructor_role ) );
						}
						foreach ( $all_instructors as $instructor ) : 
							$instructor_obj = is_object( $instructor ) ? $instructor : get_userdata( $instructor );
							if ( ! $instructor_obj ) continue;
						?>
							<option value="<?php echo esc_attr( $instructor_obj->ID ); ?>" <?php selected( isset( $_GET['instructor_id'] ) ? intval( $_GET['instructor_id'] ) : 0, $instructor_obj->ID ); ?>>
								<?php echo esc_html( $instructor_obj->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php endif; ?>
				
				<div class="form-field">
					<label for="date_from">
						<span class="dashicons dashicons-calendar-alt" style="font-size: 16px; vertical-align: middle;"></span>
						<?php esc_html_e( 'Fecha Desde', 'tutor-attendance-calendar' ); ?>
					</label>
					<input 
						type="date" 
						name="date_from" 
						id="date_from" 
						value="<?php echo esc_attr( isset( $_GET['date_from'] ) ? $_GET['date_from'] : date( 'Y-m-d', strtotime( '-30 days' ) ) ); ?>"
						required>
				</div>
				
				<div class="form-field">
					<label for="date_to">
						<span class="dashicons dashicons-calendar-alt" style="font-size: 16px; vertical-align: middle;"></span>
						<?php esc_html_e( 'Fecha Hasta', 'tutor-attendance-calendar' ); ?>
					</label>
					<input 
						type="date" 
						name="date_to" 
						id="date_to" 
						value="<?php echo esc_attr( isset( $_GET['date_to'] ) ? $_GET['date_to'] : date( 'Y-m-d' ) ); ?>"
						required>
				</div>
				
				<div class="form-field">
					<label for="status">
						<span class="dashicons dashicons-yes-alt" style="font-size: 16px; vertical-align: middle;"></span>
						<?php esc_html_e( 'Estado', 'tutor-attendance-calendar' ); ?>
					</label>
					<select name="status" id="status">
						<option value=""><?php esc_html_e( 'Todos los estados', 'tutor-attendance-calendar' ); ?></option>
						<?php
						$states = get_option( 'tutor_attendance_states', array( 'Asistió', 'Falta', 'Tarde', 'Justificado' ) );
						foreach ( $states as $state ) :
							?>
							<option value="<?php echo esc_attr( $state ); ?>" <?php selected( isset( $_GET['status'] ) ? $_GET['status'] : '', $state ); ?>>
								<?php echo esc_html( $state ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="tutor-export-info">
				<p><strong><?php esc_html_e( 'Información de Exportación:', 'tutor-attendance-calendar' ); ?></strong></p>
				<p>
					<?php esc_html_e( 'El reporte se generará en formato CSV compatible con Microsoft Excel y Google Sheets.', 'tutor-attendance-calendar' ); ?>
					<?php esc_html_e( 'Incluirá todas las columnas: Fecha, Curso, Alumno, Email, Docente, Estado y Marcado por.', 'tutor-attendance-calendar' ); ?>
				</p>
				<?php if ( isset( $is_admin ) && ! $is_admin ) : ?>
					<p style="margin-top: 10px; font-weight: 600;">
						<?php esc_html_e( 'Nota: Solo podrás exportar reportes de los cursos donde eres instructor.', 'tutor-attendance-calendar' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="tutor-export-actions">
				<button type="submit" name="export_excel" class="btn-export-excel">
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<?php esc_html_e( 'Exportar a Excel', 'tutor-attendance-calendar' ); ?>
				</button>
			</div>
		</form>
	</div>

	<div class="tutor-help-box">
		<p><strong><?php esc_html_e( 'Consejos para la exportación:', 'tutor-attendance-calendar' ); ?></strong></p>
		<ul>
			<li><?php esc_html_e( 'Puedes filtrar por curso, docente, rango de fechas o estado específico', 'tutor-attendance-calendar' ); ?></li>
			<li><?php esc_html_e( 'Si no seleccionas filtros, se exportarán todos los registros disponibles', 'tutor-attendance-calendar' ); ?></li>
			<li><?php esc_html_e( 'El archivo se descargará automáticamente con un nombre único basado en la fecha y hora', 'tutor-attendance-calendar' ); ?></li>
			<li><?php esc_html_e( 'Los archivos CSV son compatibles con Excel, Google Sheets y otros programas de hojas de cálculo', 'tutor-attendance-calendar' ); ?></li>
		</ul>
	</div>
</div>

