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
$student_courses_raw = $plugin->get_student_courses( $student_id );
// Convertir a array si es un WP_Query u otro objeto
if ( is_object( $student_courses_raw ) && method_exists( $student_courses_raw, 'get_posts' ) ) {
	$student_courses = $student_courses_raw->get_posts();
} elseif ( is_object( $student_courses_raw ) && isset( $student_courses_raw->posts ) ) {
	$student_courses = $student_courses_raw->posts;
} elseif ( is_array( $student_courses_raw ) ) {
	$student_courses = $student_courses_raw;
} else {
	$student_courses = array();
}
$today = date( 'Y-m-d' );

// Obtener curso seleccionado
$selected_course_id = isset( $_GET['attendance_course'] ) ? intval( $_GET['attendance_course'] ) : 0;
$selected_date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );
$selected_date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : date( 'Y-m-d' );

// Paginación
$per_page = 20; // Asistencias por página
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset = ( $current_page - 1 ) * $per_page;

// Obtener asistencias con paginación
if ( $selected_course_id > 0 ) {
	$my_attendance = $plugin->get_student_attendance( $student_id, $selected_course_id, $selected_date_from, $selected_date_to, $per_page, $offset );
	$total_attendance_count = $plugin->get_student_attendance_count( $student_id, $selected_course_id, $selected_date_from, $selected_date_to );
} else {
	// Si no hay curso seleccionado, obtener todas las asistencias
	$my_attendance = $plugin->get_student_attendance( $student_id, 0, $selected_date_from, $selected_date_to, $per_page, $offset );
	$total_attendance_count = $plugin->get_student_attendance_count( $student_id, 0, $selected_date_from, $selected_date_to );
}

$total_pages = ceil( $total_attendance_count / $per_page );

// Preparar información de cursos
$courses_can_mark = array();
$course_info = array();

if ( ! empty( $student_courses ) ) {
	foreach ( $student_courses as $course ) {
		$course_id = isset( $course->ID ) ? $course->ID : ( isset( $course->course_id ) ? $course->course_id : 0 );
		if ( $course_id > 0 ) {
			$course_obj = get_post( $course_id );
			if ( ! $course_obj ) continue;
			
			$course_student_can_mark = get_post_meta( $course_id, '_tutor_attendance_student_can_mark', true );
			if ( $course_student_can_mark === '' ) {
				$course_student_can_mark = true;
			} else {
				$course_student_can_mark = (bool) $course_student_can_mark;
			}
			
			// Obtener horarios del curso
			$schedules_raw = $plugin->get_course_schedules( $course_id );
			$schedules = array();
			foreach ( $schedules_raw as $schedule ) {
				$schedules[] = array(
					'day_of_week' => intval( $schedule->day_of_week ),
					'start_time' => $schedule->start_time,
					'end_time' => $schedule->end_time,
				);
			}
			
			$course_date_from = get_post_meta( $course_id, '_tutor_attendance_date_from', true );
			$course_date_to = get_post_meta( $course_id, '_tutor_attendance_date_to', true );
			$deadline_hours = get_post_meta( $course_id, '_tutor_attendance_deadline_hours', true );
			$deadline_minutes = get_post_meta( $course_id, '_tutor_attendance_deadline_minutes', true );
			
			if ( $deadline_hours === '' ) $deadline_hours = 24;
			if ( $deadline_minutes === '' ) $deadline_minutes = 0;
			
			// Verificar si ya tiene asistencia hoy
			$today_attendance_check = $plugin->get_student_attendance( $student_id, $course_id, $today, $today );
			$has_attendance_today = ! empty( $today_attendance_check );
			
			// Verificar límite de tiempo
			$deadline_seconds = ( intval( $deadline_hours ) * 3600 ) + ( intval( $deadline_minutes ) * 60 );
			$deadline_timestamp = strtotime( $today . ' 23:59:59' ) + $deadline_seconds;
			$can_mark_now = ( time() <= $deadline_timestamp && ! $has_attendance_today && $course_student_can_mark );
			
			$current_status = '';
			if ( $has_attendance_today && ! empty( $today_attendance_check ) ) {
				$current_status = $today_attendance_check[0]->status;
			}
			
			$course_info[ $course_id ] = array(
				'title' => $course_obj->post_title,
				'can_mark' => $course_student_can_mark,
				'schedules' => $schedules,
				'schedules_raw' => $schedules_raw,
				'date_from' => $course_date_from,
				'date_to' => $course_date_to,
				'deadline_hours' => intval( $deadline_hours ),
				'deadline_minutes' => intval( $deadline_minutes ),
				'has_attendance_today' => $has_attendance_today,
				'can_mark_now' => $can_mark_now,
				'current_status' => $current_status,
			);
			
			if ( $course_student_can_mark ) {
				$courses_can_mark[] = $course_obj;
			}
		}
	}
}

// Contar estadísticas
$total_attendance = $total_attendance_count;
// Contar asistencias (estado "Asistió") - obtener todas para contar correctamente
$all_attendance_for_stats = $plugin->get_student_attendance( $student_id, $selected_course_id, $selected_date_from, $selected_date_to, -1, 0 );
$attended_count = 0;
if ( ! empty( $all_attendance_for_stats ) ) {
	foreach ( $all_attendance_for_stats as $att ) {
		if ( stripos( $att->status, 'asistió' ) !== false || stripos( $att->status, 'asistio' ) !== false ) {
			$attended_count++;
		}
	}
}
?>

<div class="tutor-student-attendance-wrapper">
	<!-- Header con Estadísticas -->
	<div class="attendance-welcome-card">
		<div class="welcome-content">
			<h1 class="welcome-title">
				<span class="welcome-icon">📅</span>
				<?php esc_html_e( 'Mi Asistencia', 'tutor-attendance-calendar' ); ?>
			</h1>
			<p class="welcome-description">
				<?php esc_html_e( 'Gestiona tu asistencia de manera sencilla y consulta tus horarios', 'tutor-attendance-calendar' ); ?>
			</p>
		</div>
		<div class="welcome-stats">
			<div class="stat-card">
				<div class="stat-icon">✅</div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( $attended_count ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Asistencias', 'tutor-attendance-calendar' ); ?></div>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon">📚</div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( count( $student_courses ) ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Cursos', 'tutor-attendance-calendar' ); ?></div>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon">📊</div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( $total_attendance ); ?></div>
					<div class="stat-label"><?php esc_html_e( 'Total Registros', 'tutor-attendance-calendar' ); ?></div>
				</div>
			</div>
		</div>
	</div>

	<!-- Cards Grandes para Marcar Asistencia -->
	<?php if ( ! empty( $courses_can_mark ) ) : ?>
		<div class="mark-attendance-section">
			<h2 class="section-header">
				<span class="header-icon">✅</span>
				<?php esc_html_e( 'Marcar Asistencia de Hoy', 'tutor-attendance-calendar' ); ?>
			</h2>
			<div class="mark-attendance-cards">
				<?php foreach ( $courses_can_mark as $course ) :
					$course_id = $course->ID;
					$info = isset( $course_info[ $course_id ] ) ? $course_info[ $course_id ] : null;
					if ( ! $info ) continue;
					
					$has_attendance = $info['has_attendance_today'];
					$can_mark = $info['can_mark_now'];
					$current_status = $info['current_status'];
					
					// Obtener horario de hoy
					$today_schedule = null;
					$current_day = date( 'w' );
					if ( ! empty( $info['schedules_raw'] ) ) {
						foreach ( $info['schedules_raw'] as $schedule ) {
							if ( intval( $schedule->day_of_week ) == $current_day ) {
								$today_schedule = $schedule;
								break;
							}
						}
					}
					
					$days_short = array( 'Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb' );
				?>
					<div class="mark-card <?php echo $has_attendance ? 'marked' : ''; ?>" data-course-id="<?php echo esc_attr( $course_id ); ?>">
						<div class="mark-card-header">
							<h3 class="course-title"><?php echo esc_html( $info['title'] ); ?></h3>
							<?php if ( $has_attendance ) : ?>
								<span class="status-indicator marked-indicator">
									<span class="indicator-icon">✓</span>
									<?php esc_html_e( 'Marcada', 'tutor-attendance-calendar' ); ?>
								</span>
							<?php elseif ( ! $can_mark ) : ?>
								<span class="status-indicator timeout-indicator">
									<span class="indicator-icon">⏱</span>
									<?php esc_html_e( 'Tiempo agotado', 'tutor-attendance-calendar' ); ?>
								</span>
							<?php else : ?>
								<span class="status-indicator available-indicator">
									<span class="indicator-icon">●</span>
									<?php esc_html_e( 'Disponible', 'tutor-attendance-calendar' ); ?>
								</span>
							<?php endif; ?>
						</div>
						
						<?php if ( $today_schedule ) : ?>
							<div class="mark-card-schedule">
								<div class="schedule-icon">🕐</div>
								<div class="schedule-info">
									<div class="schedule-day"><?php echo esc_html( $days_short[ $current_day ] ); ?></div>
									<div class="schedule-time">
										<?php echo esc_html( substr( $today_schedule->start_time, 0, 5 ) . ' - ' . substr( $today_schedule->end_time, 0, 5 ) ); ?>
									</div>
								</div>
							</div>
						<?php endif; ?>
						
						<?php if ( $has_attendance && ! empty( $current_status ) ) : ?>
							<div class="mark-card-status">
								<span class="status-badge-large status-<?php echo esc_attr( strtolower( sanitize_title( $current_status ) ) ); ?>">
									<?php echo esc_html( $current_status ); ?>
								</span>
							</div>
						<?php else : ?>
							<button type="button" class="mark-btn <?php echo ! $can_mark ? 'disabled' : ''; ?>" 
								data-course-id="<?php echo esc_attr( $course_id ); ?>"
								<?php echo ! $can_mark ? 'disabled' : ''; ?>>
								<span class="btn-icon">✓</span>
								<span class="btn-text"><?php esc_html_e( 'Marcar Asistencia', 'tutor-attendance-calendar' ); ?></span>
							</button>
						<?php endif; ?>
						
						<div class="mark-card-message" style="display: none;"></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Filtros Compactos -->
	<div class="attendance-filters-section">
		<div class="filters-container">
			<div class="filter-group">
				<label class="filter-label"><?php esc_html_e( 'Curso', 'tutor-attendance-calendar' ); ?></label>
				<select name="attendance_course" id="attendance_course" class="filter-field">
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
			
			<div class="filter-group">
				<label class="filter-label"><?php esc_html_e( 'Desde', 'tutor-attendance-calendar' ); ?></label>
				<input type="date" name="date_from" id="date_from" value="<?php echo esc_attr( $selected_date_from ); ?>" class="filter-field">
			</div>
			
			<div class="filter-group">
				<label class="filter-label"><?php esc_html_e( 'Hasta', 'tutor-attendance-calendar' ); ?></label>
				<input type="date" name="date_to" id="date_to" value="<?php echo esc_attr( $selected_date_to ); ?>" class="filter-field">
			</div>
			
			<div class="filter-group">
				<button type="button" id="apply-filters-btn" class="filter-btn">
					<span class="btn-icon-filter">🔍</span>
					<?php esc_html_e( 'Buscar', 'tutor-attendance-calendar' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Historial Visual -->
	<div class="attendance-history-section">
		<h2 class="section-header">
			<span class="header-icon">📋</span>
			<?php esc_html_e( 'Historial de Asistencias', 'tutor-attendance-calendar' ); ?>
		</h2>
		
		<?php if ( ! empty( $my_attendance ) ) : ?>
			<div class="attendance-timeline">
				<?php foreach ( $my_attendance as $attendance ) : 
					$course = get_post( $attendance->course_id );
					$day_number = date( 'd', strtotime( $attendance->attendance_date ) );
					$month_name = date_i18n( 'M', strtotime( $attendance->attendance_date ) );
					$day_name = date_i18n( 'l', strtotime( $attendance->attendance_date ) );
					$is_today = ( $attendance->attendance_date === $today );
				?>
					<div class="timeline-entry <?php echo $is_today ? 'today' : ''; ?>">
						<div class="timeline-date-box">
							<div class="date-number"><?php echo esc_html( $day_number ); ?></div>
							<div class="date-month"><?php echo esc_html( $month_name ); ?></div>
						</div>
						<div class="timeline-content-box">
							<div class="content-main">
								<h4 class="course-name-timeline"><?php echo $course ? esc_html( $course->post_title ) : '-'; ?></h4>
								<span class="status-badge-timeline status-<?php echo esc_attr( strtolower( sanitize_title( $attendance->status ) ) ); ?>">
									<?php echo esc_html( $attendance->status ); ?>
								</span>
							</div>
							<div class="content-meta">
								<span class="day-name"><?php echo esc_html( $day_name ); ?></span>
								<span class="date-full"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $attendance->attendance_date ) ) ); ?></span>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			
			<!-- Paginación -->
			<?php if ( $total_pages > 1 ) : ?>
				<div class="attendance-pagination">
					<?php
					$base_url = tutor_utils()->tutor_dashboard_url( 'attendance' );
					$query_args = array();
					if ( $selected_course_id > 0 ) {
						$query_args['attendance_course'] = $selected_course_id;
					}
					if ( ! empty( $selected_date_from ) ) {
						$query_args['date_from'] = $selected_date_from;
					}
					if ( ! empty( $selected_date_to ) ) {
						$query_args['date_to'] = $selected_date_to;
					}
					
					// Primera página
					if ( $current_page > 1 ) {
						$first_url = add_query_arg( array_merge( $query_args, array( 'paged' => 1 ) ), $base_url );
						echo '<a href="' . esc_url( $first_url ) . '" class="pagination-link first-page">&laquo;</a>';
					}
					
					// Página anterior
					if ( $current_page > 1 ) {
						$prev_url = add_query_arg( array_merge( $query_args, array( 'paged' => $current_page - 1 ) ), $base_url );
						echo '<a href="' . esc_url( $prev_url ) . '" class="pagination-link prev-page">&lsaquo; ' . esc_html__( 'Anterior', 'tutor-attendance-calendar' ) . '</a>';
					}
					
					// Números de página
					$start_page = max( 1, $current_page - 2 );
					$end_page = min( $total_pages, $current_page + 2 );
					
					if ( $start_page > 1 ) {
						$url = add_query_arg( array_merge( $query_args, array( 'paged' => 1 ) ), $base_url );
						echo '<a href="' . esc_url( $url ) . '" class="pagination-link">1</a>';
						if ( $start_page > 2 ) {
							echo '<span class="pagination-dots">...</span>';
						}
					}
					
					for ( $i = $start_page; $i <= $end_page; $i++ ) {
						if ( $i == $current_page ) {
							echo '<span class="pagination-link current">' . esc_html( $i ) . '</span>';
						} else {
							$url = add_query_arg( array_merge( $query_args, array( 'paged' => $i ) ), $base_url );
							echo '<a href="' . esc_url( $url ) . '" class="pagination-link">' . esc_html( $i ) . '</a>';
						}
					}
					
					if ( $end_page < $total_pages ) {
						if ( $end_page < $total_pages - 1 ) {
							echo '<span class="pagination-dots">...</span>';
						}
						$url = add_query_arg( array_merge( $query_args, array( 'paged' => $total_pages ) ), $base_url );
						echo '<a href="' . esc_url( $url ) . '" class="pagination-link">' . esc_html( $total_pages ) . '</a>';
					}
					
					// Página siguiente
					if ( $current_page < $total_pages ) {
						$next_url = add_query_arg( array_merge( $query_args, array( 'paged' => $current_page + 1 ) ), $base_url );
						echo '<a href="' . esc_url( $next_url ) . '" class="pagination-link next-page">' . esc_html__( 'Siguiente', 'tutor-attendance-calendar' ) . ' &rsaquo;</a>';
					}
					
					// Última página
					if ( $current_page < $total_pages ) {
						$last_url = add_query_arg( array_merge( $query_args, array( 'paged' => $total_pages ) ), $base_url );
						echo '<a href="' . esc_url( $last_url ) . '" class="pagination-link last-page">&raquo;</a>';
					}
					?>
					<div class="pagination-info">
						<?php
						printf(
							esc_html__( 'Mostrando %1$d - %2$d de %3$d asistencias', 'tutor-attendance-calendar' ),
							$offset + 1,
							min( $offset + $per_page, $total_attendance_count ),
							$total_attendance_count
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<div class="empty-state-visual">
				<div class="empty-icon-large">📭</div>
				<h3><?php esc_html_e( 'No hay registros', 'tutor-attendance-calendar' ); ?></h3>
				<p><?php esc_html_e( 'No se encontraron asistencias para los filtros seleccionados.', 'tutor-attendance-calendar' ); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<!-- Horarios Visuales -->
	<?php if ( ! empty( $student_courses ) ) : 
		$has_schedules = false;
		foreach ( $student_courses as $course ) {
			$course_id = isset( $course->ID ) ? $course->ID : ( isset( $course->course_id ) ? $course->course_id : 0 );
			if ( $course_id > 0 && isset( $course_info[ $course_id ] ) && ! empty( $course_info[ $course_id ]['schedules_raw'] ) ) {
				$has_schedules = true;
				break;
			}
		}
		
		if ( $has_schedules ) :
	?>
		<div class="schedules-visual-section">
			<h2 class="section-header">
				<span class="header-icon">🕐</span>
				<?php esc_html_e( 'Mis Horarios de Clase', 'tutor-attendance-calendar' ); ?>
			</h2>
			
			<div class="schedules-grid-modern">
				<?php
				$days_of_week = array(
					0 => __( 'Domingo', 'tutor-attendance-calendar' ),
					1 => __( 'Lunes', 'tutor-attendance-calendar' ),
					2 => __( 'Martes', 'tutor-attendance-calendar' ),
					3 => __( 'Miércoles', 'tutor-attendance-calendar' ),
					4 => __( 'Jueves', 'tutor-attendance-calendar' ),
					5 => __( 'Viernes', 'tutor-attendance-calendar' ),
					6 => __( 'Sábado', 'tutor-attendance-calendar' ),
				);
				
				$days_short = array( 'Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb' );
				
				foreach ( $student_courses as $course ) :
					$course_id = isset( $course->ID ) ? $course->ID : ( isset( $course->course_id ) ? $course->course_id : 0 );
					if ( $course_id <= 0 || ! isset( $course_info[ $course_id ] ) ) continue;
					
					$info = $course_info[ $course_id ];
					$schedules = $info['schedules_raw'];
					
					if ( empty( $schedules ) ) continue;
					
					// Organizar horarios por día
					$schedules_by_day = array();
					foreach ( $schedules as $schedule ) {
						$day = intval( $schedule->day_of_week );
						if ( ! isset( $schedules_by_day[ $day ] ) ) {
							$schedules_by_day[ $day ] = array();
						}
						$schedules_by_day[ $day ][] = $schedule;
					}
				?>
					<div class="schedule-card-modern">
						<div class="schedule-card-top">
							<h3 class="schedule-course-title"><?php echo esc_html( $info['title'] ); ?></h3>
							<?php if ( ! empty( $info['date_from'] ) || ! empty( $info['date_to'] ) ) : ?>
								<div class="schedule-course-period">
									<span class="period-icon">📅</span>
									<span>
										<?php
										if ( ! empty( $info['date_from'] ) ) {
											echo esc_html( date_i18n( 'd/m/Y', strtotime( $info['date_from'] ) ) );
										}
										if ( ! empty( $info['date_from'] ) && ! empty( $info['date_to'] ) ) {
											echo ' - ';
										}
										if ( ! empty( $info['date_to'] ) ) {
											echo esc_html( date_i18n( 'd/m/Y', strtotime( $info['date_to'] ) ) );
										}
										?>
									</span>
								</div>
							<?php endif; ?>
						</div>
						<div class="schedule-card-body">
							<?php foreach ( $schedules_by_day as $day_num => $day_schedules ) : ?>
								<div class="schedule-day-modern">
									<div class="day-name-modern"><?php echo esc_html( $days_short[ $day_num ] ); ?></div>
									<div class="day-times">
										<?php foreach ( $day_schedules as $schedule ) : ?>
											<span class="time-badge">
												<?php echo esc_html( substr( $schedule->start_time, 0, 5 ) . ' - ' . substr( $schedule->end_time, 0, 5 ) ); ?>
											</span>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Marcar asistencia desde las cards
	$('.mark-btn').on('click', function() {
		var $btn = $(this);
		var courseId = $btn.data('course-id');
		var $card = $btn.closest('.mark-card');
		var $message = $card.find('.mark-card-message');
		
		if (!courseId || $btn.hasClass('disabled')) {
			return;
		}

		$btn.prop('disabled', true).html('<span class="loading-spinner-inline"></span> <?php esc_attr_e( 'Marcando...', 'tutor-attendance-calendar' ); ?>');
		$message.hide();

		var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		if (typeof tutorAttendance !== 'undefined' && tutorAttendance.ajaxurl) {
			ajaxUrl = tutorAttendance.ajaxurl;
		} else if (typeof ajaxurl !== 'undefined') {
			ajaxUrl = ajaxurl;
		}

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'tutor_mark_my_attendance',
				nonce: (typeof tutorAttendance !== 'undefined' && tutorAttendance.nonce) ? tutorAttendance.nonce : '<?php echo wp_create_nonce( 'tutor_attendance_nonce' ); ?>',
				course_id: courseId,
				attendance_date: '<?php echo date( 'Y-m-d' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					$card.addClass('marked');
					$btn.replaceWith('<span class="status-badge-large status-asistio"><?php esc_attr_e( 'Asistencia marcada', 'tutor-attendance-calendar' ); ?></span>');
					$message.text(response.data.message).addClass('success').show();
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					$message.text(response.data.message || '<?php esc_attr_e( 'Error', 'tutor-attendance-calendar' ); ?>').addClass('error').show();
					$btn.prop('disabled', false).html('<span class="btn-icon">✓</span><span class="btn-text"><?php esc_attr_e( 'Marcar Asistencia', 'tutor-attendance-calendar' ); ?></span>');
				}
			},
			error: function() {
				$message.text('<?php esc_attr_e( 'Error de conexión', 'tutor-attendance-calendar' ); ?>').addClass('error').show();
				$btn.prop('disabled', false).html('<span class="btn-icon">✓</span><span class="btn-text"><?php esc_attr_e( 'Marcar Asistencia', 'tutor-attendance-calendar' ); ?></span>');
			}
		});
	});
	
	// Aplicar filtros
	$('#apply-filters-btn').on('click', function() {
		var courseId = $('#attendance_course').val();
		var dateFrom = $('#date_from').val();
		var dateTo = $('#date_to').val();
		
		var url = '<?php echo esc_url( tutor_utils()->tutor_dashboard_url( 'attendance' ) ); ?>';
		var params = new URLSearchParams();
		
		if (courseId && courseId != '0') {
			params.append('attendance_course', courseId);
		}
		if (dateFrom) {
			params.append('date_from', dateFrom);
		}
		if (dateTo) {
			params.append('date_to', dateTo);
		}
		
		var queryString = params.toString();
		if (queryString) {
			url += '?' + queryString;
		}
		
		window.location.href = url;
	});
	
	// Enter en los filtros
	$('#attendance_course, #date_from, #date_to').on('keypress', function(e) {
		if (e.which === 13) {
			$('#apply-filters-btn').click();
		}
	});
	
	// Spinner
	$('<style>@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } } .loading-spinner-inline { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 5px; }</style>').appendTo('head');
});
</script>
