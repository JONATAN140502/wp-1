<?php
/**
 * Vista: Resumen de Asistencias (Admin)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<style>
.tutor-summary-header {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #fff;
	padding: 25px 30px;
	border-radius: 8px;
	margin-bottom: 25px;
	box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.tutor-summary-header h1 {
	color: #fff;
	margin: 0 0 8px 0;
	font-size: 28px;
	font-weight: 600;
}

.tutor-summary-header p {
	color: rgba(255, 255, 255, 0.9);
	margin: 0;
	font-size: 14px;
}

.tutor-attendance-filters {
	background: #fff;
	padding: 25px;
	margin-bottom: 25px;
	border-radius: 8px;
	border: 1px solid #e0e0e0;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.tutor-attendance-filters h2 {
	margin: 0 0 20px 0;
	font-size: 18px;
	font-weight: 600;
	color: #1d2327;
}

.tutor-attendance-results {
	background: #fff;
	padding: 25px;
	border-radius: 8px;
	border: 1px solid #e0e0e0;
	box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.tutor-attendance-results h2 {
	margin: 0 0 20px 0;
	font-size: 18px;
	font-weight: 600;
	color: #1d2327;
}

.export-btn {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 10px 20px;
	background: #28a745;
	color: #fff;
	text-decoration: none;
	border-radius: 6px;
	font-weight: 600;
	font-size: 14px;
	transition: all 0.3s ease;
	border: none;
	cursor: pointer;
}

.export-btn:hover {
	background: #218838;
	color: #fff;
	transform: translateY(-2px);
	box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
}

.export-btn .dashicons {
	font-size: 18px;
	width: 18px;
	height: 18px;
}

.wp-list-table {
	border-collapse: collapse;
	width: 100%;
}

.wp-list-table thead th {
	background: #f6f7f7;
	padding: 12px 15px;
	text-align: left;
	font-weight: 600;
	color: #1d2327;
	border-bottom: 2px solid #dcdcde;
}

.wp-list-table tbody td {
	padding: 12px 15px;
	border-bottom: 1px solid #f0f0f1;
}

.wp-list-table tbody tr:hover {
	background: #f9f9f9;
}

.tutor-results-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
	flex-wrap: wrap;
	gap: 15px;
}

.tutor-results-stats {
	display: flex;
	gap: 20px;
	margin-top: 20px;
	padding-top: 20px;
	border-top: 1px solid #e0e0e0;
}

.tutor-stat-box {
	flex: 1;
	background: #f6f7f7;
	padding: 15px;
	border-radius: 6px;
	text-align: center;
}

.tutor-stat-box .stat-value {
	font-size: 24px;
	font-weight: 700;
	color: #2271b1;
	margin-bottom: 5px;
}

.tutor-stat-box .stat-label {
	font-size: 13px;
	color: #646970;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
</style>

<div class="wrap">
	<div class="tutor-summary-header">
		<h1><?php esc_html_e( 'Resumen de Asistencias', 'tutor-attendance-calendar' ); ?></h1>
		<p><?php esc_html_e( 'Consulta y exporta los registros de asistencia de todos los cursos y estudiantes', 'tutor-attendance-calendar' ); ?></p>
	</div>

	<!-- Filtros -->
	<div class="tutor-attendance-filters" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
		<h2><?php esc_html_e( 'Filtros', 'tutor-attendance-calendar' ); ?></h2>
		<form method="GET" action="">
			<input type="hidden" name="page" value="tutor-attendance-summary">
			
			<div style="display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap;">
				<div style="flex: 1; min-width: 200px;">
					<label for="course_id" style="display: block; margin-bottom: 8px; font-weight: 600;">
						<?php esc_html_e( 'Curso', 'tutor-attendance-calendar' ); ?>
					</label>
					<select name="course_id" id="course_id" style="width: 100%; padding: 6px 8px;">
						<option value="0"><?php esc_html_e( 'Todos los cursos', 'tutor-attendance-calendar' ); ?></option>
						<?php foreach ( $courses as $course ) : ?>
							<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>>
								<?php echo esc_html( $course->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<div style="flex: 1; min-width: 200px;">
					<label for="instructor_id" style="display: block; margin-bottom: 8px; font-weight: 600;">
						<?php esc_html_e( 'Docente', 'tutor-attendance-calendar' ); ?>
					</label>
					<select name="instructor_id" id="instructor_id" style="width: 100%; padding: 6px 8px;">
						<option value="0"><?php esc_html_e( 'Todos los docentes', 'tutor-attendance-calendar' ); ?></option>
						<?php foreach ( $instructors as $instructor ) : ?>
							<option value="<?php echo esc_attr( $instructor->ID ); ?>" <?php selected( $instructor_id, $instructor->ID ); ?>>
								<?php echo esc_html( $instructor->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<div style="flex: 0 0 auto; min-width: 150px;">
					<label for="date_from" style="display: block; margin-bottom: 8px; font-weight: 600;">
						<?php esc_html_e( 'Fecha Desde', 'tutor-attendance-calendar' ); ?>
					</label>
					<input type="date" name="date_from" id="date_from" value="<?php echo esc_attr( $date_from ); ?>" style="width: 100%; padding: 6px 8px;">
				</div>
				
				<div style="flex: 0 0 auto; min-width: 150px;">
					<label for="date_to" style="display: block; margin-bottom: 8px; font-weight: 600;">
						<?php esc_html_e( 'Fecha Hasta', 'tutor-attendance-calendar' ); ?>
					</label>
					<input type="date" name="date_to" id="date_to" value="<?php echo esc_attr( $date_to ); ?>" style="width: 100%; padding: 6px 8px;">
				</div>
				
				<div style="flex: 0 0 auto; min-width: 180px;">
					<label for="status" style="display: block; margin-bottom: 8px; font-weight: 600;">
						<?php esc_html_e( 'Estado', 'tutor-attendance-calendar' ); ?>
					</label>
					<select name="status" id="status" style="width: 100%; padding: 6px 8px;">
						<option value=""><?php esc_html_e( 'Todos los estados', 'tutor-attendance-calendar' ); ?></option>
						<?php
						$states = get_option( 'tutor_attendance_states', array( 'Asistió', 'Falta', 'Tarde', 'Justificado' ) );
						foreach ( $states as $state ) :
							?>
							<option value="<?php echo esc_attr( $state ); ?>" <?php selected( $status_filter, $state ); ?>>
								<?php echo esc_html( $state ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				
				<div style="flex: 0 0 auto; display: flex; gap: 8px; align-items: flex-end;">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Aplicar Filtros', 'tutor-attendance-calendar' ); ?>" style="margin-top: 0;">
					<a href="?page=tutor-attendance-summary" class="button"><?php esc_html_e( 'Limpiar', 'tutor-attendance-calendar' ); ?></a>
				</div>
			</div>
		</form>
	</div>

	<!-- Tabla de resultados -->
	<div class="tutor-attendance-results">
		<div class="tutor-results-header">
			<h2 style="margin: 0;"><?php esc_html_e( 'Resultados', 'tutor-attendance-calendar' ); ?></h2>
			<?php if ( ! empty( $attendance_data ) ) : ?>
				<?php
				$export_url = add_query_arg( array(
					'page' => 'tutor-attendance-summary',
					'export' => 'excel',
					'course_id' => $course_id,
					'instructor_id' => $instructor_id,
					'date_from' => $date_from,
					'date_to' => $date_to,
					'status' => $status_filter,
				), admin_url( 'admin.php' ) );
				?>
				<a href="<?php echo esc_url( $export_url ); ?>" class="export-btn">
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<?php esc_html_e( 'Exportar a Excel', 'tutor-attendance-calendar' ); ?>
				</a>
			<?php endif; ?>
		</div>
		
		<?php if ( ! empty( $attendance_data ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Fecha', 'tutor-attendance-calendar' ); ?></th>
						<th><?php esc_html_e( 'Curso', 'tutor-attendance-calendar' ); ?></th>
						<th><?php esc_html_e( 'Alumno', 'tutor-attendance-calendar' ); ?></th>
						<th><?php esc_html_e( 'Email', 'tutor-attendance-calendar' ); ?></th>
						<th><?php esc_html_e( 'Docente', 'tutor-attendance-calendar' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'tutor-attendance-calendar' ); ?></th>
						<th><?php esc_html_e( 'Marcado por', 'tutor-attendance-calendar' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $attendance_data as $record ) : ?>
						<tr>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $record->attendance_date ) ) ); ?></td>
							<td><?php echo esc_html( $record->course_name ); ?></td>
							<td><?php echo esc_html( $record->student_name ); ?></td>
							<td><?php echo esc_html( $record->student_email ); ?></td>
							<td><?php echo esc_html( $record->instructor_name ); ?></td>
							<td>
								<span class="attendance-status status-<?php echo esc_attr( strtolower( $record->status ) ); ?>">
									<?php echo esc_html( $record->status ); ?>
								</span>
							</td>
							<td>
								<?php
								$marked_by_user = get_userdata( $record->marked_by );
								echo $marked_by_user ? esc_html( $marked_by_user->display_name ) : '-';
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="tutor-results-stats">
				<div class="tutor-stat-box">
					<div class="stat-value"><?php echo count( $attendance_data ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Total de Registros', 'tutor-attendance-calendar' ); ?></div>
				</div>
				<?php
				$states = get_option( 'tutor_attendance_states', array( 'Asistió', 'Falta', 'Tarde', 'Justificado' ) );
				foreach ( $states as $state ) :
					$count = count( array_filter( $attendance_data, function( $record ) use ( $state ) {
						return $record->status === $state;
					} ) );
					if ( $count > 0 ) :
				?>
					<div class="tutor-stat-box">
						<div class="stat-value"><?php echo $count; ?></div>
						<div class="stat-label"><?php echo esc_html( $state ); ?></div>
					</div>
				<?php
					endif;
				endforeach;
				?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'No se encontraron registros de asistencia con los filtros aplicados.', 'tutor-attendance-calendar' ); ?></p>
		<?php endif; ?>
	</div>
</div>
