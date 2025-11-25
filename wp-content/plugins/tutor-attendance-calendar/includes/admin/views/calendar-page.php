<?php
/**
 * Vista: Calendario de Horarios (Docente)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$days_of_week = array(
	0 => __( 'Domingo', 'tutor-attendance-calendar' ),
	1 => __( 'Lunes', 'tutor-attendance-calendar' ),
	2 => __( 'Martes', 'tutor-attendance-calendar' ),
	3 => __( 'Miércoles', 'tutor-attendance-calendar' ),
	4 => __( 'Jueves', 'tutor-attendance-calendar' ),
	5 => __( 'Viernes', 'tutor-attendance-calendar' ),
	6 => __( 'Sábado', 'tutor-attendance-calendar' ),
);
?>

<style>
.attendance-calendar-page {
	max-width: 1600px;
	margin: 0 auto;
	padding: 20px;
}

.attendance-calendar-header {
	background: #3e64de;
	color: #fff;
	padding: 30px;
	border-radius: 12px;
	margin-bottom: 30px;
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.attendance-calendar-header h1 {
	margin: 0 0 10px 0;
	font-size: 28px;
	font-weight: 700;
	color: #fff;
}

.attendance-calendar-header p {
	margin: 0;
	font-size: 15px;
	opacity: 0.95;
}

.calendar-week {
	display: grid;
	grid-template-columns: repeat(7, 1fr);
	gap: 15px;
	margin-bottom: 20px;
}

.calendar-day-card {
	background: #fff;
	border-radius: 8px;
	padding: 15px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
	border: 2px solid #e5e7eb;
	transition: all 0.3s ease;
	min-height: 200px;
}

.calendar-day-card:hover {
	border-color:#3e64de;
	box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
	transform: translateY(-2px);
}

.calendar-day-header {
	margin-bottom: 15px;
	padding-bottom: 10px;
	border-bottom: 2px solid #f3f4f6;
}

.calendar-day-name {
	font-size: 16px;
	font-weight: 700;
	color: #111827;
	margin: 0 0 5px 0;
}

.calendar-day-date {
	font-size: 12px;
	color: #6b7280;
	font-weight: 500;
}

.calendar-schedules-list {
	list-style: none;
	padding: 0;
	margin: 0;
}

.calendar-schedule-item {
	background: linear-gradient(135deg, #f0f4ff 0%, #e0e7ff 100%);
	border-left: 4px solid #667eea;
	padding: 10px 12px;
	margin-bottom: 8px;
	border-radius: 6px;
	transition: all 0.2s ease;
}

.calendar-schedule-item:hover {
	background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
	transform: translateX(3px);
}

.calendar-schedule-time {
	font-size: 13px;
	font-weight: 700;
	color:#3e64de;
	margin-bottom: 4px;
	display: flex;
	align-items: center;
	gap: 5px;
}

.calendar-schedule-course {
	font-size: 12px;
	color: #4b5563;
	font-weight: 500;
	line-height: 1.4;
}

.calendar-empty-day {
	text-align: center;
	color: #9ca3af;
	font-size: 13px;
	padding: 30px 10px;
	font-style: italic;
}

.calendar-stats {
	background: #fff;
	border-radius: 8px;
	padding: 20px;
	margin-bottom: 25px;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
	gap: 20px;
}

.calendar-stat-item {
	text-align: center;
	padding: 15px;
	background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
	border-radius: 8px;
}

.calendar-stat-number {
	font-size: 32px;
	font-weight: 700;
	color: #667eea;
	margin-bottom: 5px;
}

.calendar-stat-label {
	font-size: 13px;
	color: #6b7280;
	font-weight: 500;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}

@media (max-width: 1200px) {
	.calendar-week {
		grid-template-columns: repeat(4, 1fr);
	}
}

@media (max-width: 768px) {
	.calendar-week {
		grid-template-columns: repeat(2, 1fr);
	}
	
	.attendance-calendar-header {
		padding: 20px;
	}
	
	.attendance-calendar-header h1 {
		font-size: 22px;
	}
}

@media (max-width: 480px) {
	.calendar-week {
		grid-template-columns: 1fr;
	}
}
</style>

<div class="wrap">
	<div class="attendance-calendar-page">
		<!-- Header -->
		<div class="attendance-calendar-header">
			<h1><?php esc_html_e( 'Calendario de Horarios', 'tutor-attendance-calendar' ); ?></h1>
			<p><?php esc_html_e( 'Visualiza todos los horarios de tus cursos en un calendario semanal.', 'tutor-attendance-calendar' ); ?></p>
		</div>

		<!-- Estadísticas -->
		<?php
		$total_schedules = count( $all_schedules );
		$total_courses = count( $courses_with_schedules );
		$days_with_classes = count( $schedules_by_day );
		?>
		<div class="calendar-stats">
			<div class="calendar-stat-item">
				<div class="calendar-stat-number"><?php echo esc_html( $total_courses ); ?></div>
				<div class="calendar-stat-label"><?php esc_html_e( 'Cursos', 'tutor-attendance-calendar' ); ?></div>
			</div>
			<div class="calendar-stat-item">
				<div class="calendar-stat-number"><?php echo esc_html( $total_schedules ); ?></div>
				<div class="calendar-stat-label"><?php esc_html_e( 'Horarios', 'tutor-attendance-calendar' ); ?></div>
			</div>
			<div class="calendar-stat-item">
				<div class="calendar-stat-number"><?php echo esc_html( $days_with_classes ); ?></div>
				<div class="calendar-stat-label"><?php esc_html_e( 'Días con Clases', 'tutor-attendance-calendar' ); ?></div>
			</div>
		</div>

		<!-- Calendario Semanal -->
		<div class="calendar-week">
			<?php for ( $day = 0; $day <= 6; $day++ ) : 
				$day_name = isset( $days_of_week[ $day ] ) ? $days_of_week[ $day ] : '';
				$day_schedules = isset( $schedules_by_day[ $day ] ) ? $schedules_by_day[ $day ] : array();
				?>
				<div class="calendar-day-card">
					<div class="calendar-day-header">
						<h3 class="calendar-day-name"><?php echo esc_html( $day_name ); ?></h3>
					</div>
					
					<?php if ( ! empty( $day_schedules ) ) : ?>
						<ul class="calendar-schedules-list">
							<?php foreach ( $day_schedules as $schedule ) : 
								$start_time = substr( $schedule->start_time, 0, 5 );
								$end_time = substr( $schedule->end_time, 0, 5 );
								$course_title = isset( $schedule->course_title ) ? $schedule->course_title : get_the_title( $schedule->course_id );
								?>
								<li class="calendar-schedule-item">
									<div class="calendar-schedule-time">
										<span class="tutor-icon-clock-line" area-hidden="true" style="font-size: 14px;"></span>
										<span><?php echo esc_html( $start_time . ' - ' . $end_time ); ?></span>
									</div>
									<div class="calendar-schedule-course">
										<?php echo esc_html( $course_title ); ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<div class="calendar-empty-day">
							<?php esc_html_e( 'Sin clases programadas', 'tutor-attendance-calendar' ); ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endfor; ?>
		</div>

		<?php if ( empty( $all_schedules ) ) : ?>
			<div style="background: #fff; padding: 30px; border-radius: 8px; text-align: center; box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);">
				<p style="margin: 0; color: #6b7280; font-size: 15px;">
					<?php esc_html_e( 'No hay horarios configurados para tus cursos. Ve a "Horarios de Cursos" para agregar horarios.', 'tutor-attendance-calendar' ); ?>
				</p>
				<?php if ( isset( $is_frontend_dashboard ) && $is_frontend_dashboard ) : ?>
					<a href="<?php echo esc_url( tutor_utils()->tutor_dashboard_url( 'attendance-schedules' ) ); ?>" 
						class="tutor-btn tutor-btn-primary tutor-mt-20" 
						style="display: inline-block;">
						<?php esc_html_e( 'Configurar Horarios', 'tutor-attendance-calendar' ); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tutor-attendance-schedules' ) ); ?>" 
						class="button button-primary tutor-mt-20" 
						style="display: inline-block;">
						<?php esc_html_e( 'Configurar Horarios', 'tutor-attendance-calendar' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>


