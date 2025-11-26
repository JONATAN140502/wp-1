<?php
/**
 * Template: Recursos para Estudiantes - Vista por Lecciones
 *
 * @package TutorCourseResources
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin = Tutor_Course_Resources::get_instance();
$student_id = get_current_user_id();

// Ver si estamos viendo una lección específica o vista general
$view_lesson_id = isset( $_GET['lesson_id'] ) ? intval( $_GET['lesson_id'] ) : 0;
$view_course_id = isset( $_GET['course_id'] ) ? intval( $_GET['course_id'] ) : 0;

// Obtener cursos del estudiante
$student_courses = array();
if ( function_exists( 'tutor_utils' ) ) {
	$enrolled_courses = tutor_utils()->get_enrolled_courses_by_user( $student_id );
	if ( ! empty( $enrolled_courses ) ) {
		foreach ( $enrolled_courses as $course ) {
			$course_id = is_object( $course ) ? $course->ID : ( is_numeric( $course ) ? $course : 0 );
			if ( $course_id > 0 ) {
				$student_courses[ $course_id ] = get_post( $course_id );
			}
		}
	}
}

// Obtener lecciones de cada curso
$courses_with_lessons = array();
foreach ( $student_courses as $course_id => $course ) {
	$lessons = $plugin->get_course_lessons( $course_id );
	if ( ! empty( $lessons ) ) {
		$courses_with_lessons[ $course_id ] = array(
			'course' => $course,
			'lessons' => $lessons,
		);
	}
}

// Si estamos viendo una lección específica, obtener sus recursos
$lesson_resources = array();
if ( $view_lesson_id > 0 ) {
	$lesson_resources = $plugin->get_resources_by_lesson( $view_lesson_id );
	// Filtrar por acceso
	$accessible_lesson_resources = array();
	foreach ( $lesson_resources as $resource ) {
		if ( $plugin->can_user_access_resource( $resource, $student_id ) ) {
			$accessible_lesson_resources[] = $resource;
		}
	}
	$lesson_resources = $accessible_lesson_resources;
}
?>

<div class="tutor-drive-wrapper-frontend">
	<!-- Header -->
	<div class="tutor-drive-header-frontend">
		<div class="drive-header-left">
			<h1 class="drive-title">
				<span class="dashicons dashicons-media-document"></span>
				<?php esc_html_e( 'Mis Recursos', 'tutor-course-resources' ); ?>
			</h1>
			<?php if ( $view_lesson_id > 0 ) : 
				$lesson = get_post( $view_lesson_id );
				$course = get_post( $view_course_id );
			?>
				<div class="breadcrumb">
					<a href="<?php echo esc_url( tutor_utils()->tutor_dashboard_url( 'my-resources' ) ); ?>">
						<?php esc_html_e( 'Inicio', 'tutor-course-resources' ); ?>
					</a>
					<span class="separator">/</span>
					<?php if ( $course ) : ?>
						<a href="<?php echo esc_url( tutor_utils()->tutor_dashboard_url( 'my-resources' ) . '?course_id=' . $course->ID ); ?>">
							<?php echo esc_html( $course->post_title ); ?>
						</a>
						<span class="separator">/</span>
					<?php endif; ?>
					<span><?php echo esc_html( $lesson ? $lesson->post_title : '' ); ?></span>
				</div>
			<?php elseif ( $view_course_id > 0 ) : 
				$course = get_post( $view_course_id );
			?>
				<div class="breadcrumb">
					<a href="<?php echo esc_url( tutor_utils()->tutor_dashboard_url( 'my-resources' ) ); ?>">
						<?php esc_html_e( 'Inicio', 'tutor-course-resources' ); ?>
					</a>
					<span class="separator">/</span>
					<span><?php echo esc_html( $course ? $course->post_title : '' ); ?></span>
				</div>
			<?php endif; ?>
		</div>
	</div>
	
	<?php if ( $view_lesson_id > 0 ) : ?>
		<!-- Vista de recursos de una lección específica -->
		<div class="tutor-drive-content-frontend" id="drive-content" data-view="grid">
			<?php if ( ! empty( $lesson_resources ) ) : ?>
				<div class="drive-items files-section">
					<?php foreach ( $lesson_resources as $resource ) : 
						$file_url = '';
						$file_name = $resource->title;
						$file_icon = 'dashicons-media-document';
						
						if ( $resource->resource_type === 'drive' && $resource->resource_url ) {
							$file_url = $resource->resource_url;
							$file_icon = 'dashicons-google';
						} elseif ( $resource->resource_type === 'file' && $resource->file_id ) {
							$file_url = wp_get_attachment_url( $resource->file_id );
							$mime_type = get_post_mime_type( $resource->file_id );
							
							if ( strpos( $mime_type, 'image' ) !== false ) {
								$file_icon = 'dashicons-format-image';
							} elseif ( strpos( $mime_type, 'video' ) !== false ) {
								$file_icon = 'dashicons-format-video';
							} elseif ( strpos( $mime_type, 'audio' ) !== false ) {
								$file_icon = 'dashicons-format-audio';
							} elseif ( strpos( $mime_type, 'pdf' ) !== false ) {
								$file_icon = 'dashicons-media-text';
							} elseif ( strpos( $mime_type, 'zip' ) !== false || strpos( $mime_type, 'rar' ) !== false ) {
								$file_icon = 'dashicons-archive';
							}
						}
						
						if ( empty( $file_url ) ) continue;
					?>
						<div class="drive-item file-item" data-item-id="<?php echo esc_attr( $resource->id ); ?>" data-item-type="file">
							<div class="item-icon">
								<span class="dashicons <?php echo esc_attr( $file_icon ); ?>"></span>
							</div>
							<div class="item-name" title="<?php echo esc_attr( $resource->title ); ?>">
								<?php echo esc_html( $resource->title ); ?>
							</div>
							<div class="item-actions">
								<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="item-action" title="<?php esc_attr_e( 'Descargar/Ver', 'tutor-course-resources' ); ?>">
									<span class="dashicons dashicons-download"></span>
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="drive-empty-state">
					<div class="empty-icon">
						<span class="dashicons dashicons-media-document" style="font-size: 64px; color: #ccc;"></span>
					</div>
					<h3><?php esc_html_e( 'No hay recursos en esta lección', 'tutor-course-resources' ); ?></h3>
					<p><?php esc_html_e( 'Tu docente aún no ha compartido recursos para esta clase.', 'tutor-course-resources' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php elseif ( $view_course_id > 0 ) : ?>
		<!-- Vista de lecciones de un curso -->
		<div class="tutor-course-lessons-view">
			<?php 
			$course_data = isset( $courses_with_lessons[ $view_course_id ] ) ? $courses_with_lessons[ $view_course_id ] : null;
			if ( $course_data && ! empty( $course_data['lessons'] ) ) :
			?>
				<div class="lessons-grid">
					<?php foreach ( $course_data['lessons'] as $lesson ) : 
						// Obtener recursos de esta lección
						$lesson_res = $plugin->get_resources_by_lesson( $lesson->ID );
						$accessible_lesson_res = array();
						foreach ( $lesson_res as $res ) {
							if ( $plugin->can_user_access_resource( $res, $student_id ) ) {
								$accessible_lesson_res[] = $res;
							}
						}
						$resources_count = count( $accessible_lesson_res );
					?>
						<div class="lesson-card">
							<div class="lesson-header">
								<h3>
									<span class="dashicons dashicons-book-alt"></span>
									<?php echo esc_html( $lesson->post_title ); ?>
								</h3>
								<?php if ( $resources_count > 0 ) : ?>
									<span class="resources-badge"><?php echo esc_html( $resources_count ); ?> <?php echo $resources_count == 1 ? esc_html__( 'recurso', 'tutor-course-resources' ) : esc_html__( 'recursos', 'tutor-course-resources' ); ?></span>
								<?php endif; ?>
							</div>
							<?php if ( $resources_count > 0 ) : ?>
								<div class="lesson-resources-preview">
									<ul>
										<?php foreach ( array_slice( $accessible_lesson_res, 0, 3 ) as $res ) : ?>
											<li>
												<span class="dashicons dashicons-media-document"></span>
												<?php echo esc_html( $res->title ); ?>
											</li>
										<?php endforeach; ?>
										<?php if ( $resources_count > 3 ) : ?>
											<li class="more-resources">
												<?php echo esc_html( sprintf( __( 'y %d más...', 'tutor-course-resources' ), $resources_count - 3 ) ); ?>
											</li>
										<?php endif; ?>
									</ul>
								</div>
							<?php endif; ?>
							<div class="lesson-actions">
								<a href="<?php echo esc_url( tutor_utils()->tutor_dashboard_url( 'my-resources' ) . '?lesson_id=' . $lesson->ID . '&course_id=' . $view_course_id ); ?>" class="tutor-btn tutor-btn-primary">
									<?php esc_html_e( 'Ver Recursos', 'tutor-course-resources' ); ?>
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="drive-empty-state">
					<div class="empty-icon">
						<span class="dashicons dashicons-book-alt" style="font-size: 64px; color: #ccc;"></span>
					</div>
					<h3><?php esc_html_e( 'No hay lecciones en este curso', 'tutor-course-resources' ); ?></h3>
				</div>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<!-- Vista general: Cursos con lecciones -->
		<div class="tutor-courses-resources-view">
			<?php if ( ! empty( $courses_with_lessons ) ) : ?>
				<?php foreach ( $courses_with_lessons as $course_id => $course_data ) : 
					$course = $course_data['course'];
					$lessons = $course_data['lessons'];
					
					// Contar recursos totales del curso
					$total_resources = 0;
					foreach ( $lessons as $lesson ) {
						$lesson_res = $plugin->get_resources_by_lesson( $lesson->ID );
						foreach ( $lesson_res as $res ) {
							if ( $plugin->can_user_access_resource( $res, $student_id ) ) {
								$total_resources++;
							}
						}
					}
				?>
					<div class="course-card">
						<div class="course-header">
							<h2>
								<span class="dashicons dashicons-welcome-learn-more"></span>
								<?php echo esc_html( $course->post_title ); ?>
							</h2>
							<div class="course-stats">
								<span class="stat-item">
									<span class="dashicons dashicons-book-alt"></span>
									<?php echo esc_html( count( $lessons ) ); ?> <?php esc_html_e( 'clases', 'tutor-course-resources' ); ?>
								</span>
								<?php if ( $total_resources > 0 ) : ?>
									<span class="stat-item">
										<span class="dashicons dashicons-media-document"></span>
										<?php echo esc_html( $total_resources ); ?> <?php echo $total_resources == 1 ? esc_html__( 'recurso', 'tutor-course-resources' ) : esc_html__( 'recursos', 'tutor-course-resources' ); ?>
									</span>
								<?php endif; ?>
							</div>
						</div>
						<div class="course-actions">
							<a href="<?php echo esc_url( tutor_utils()->tutor_dashboard_url( 'my-resources' ) . '?course_id=' . $course_id ); ?>" class="tutor-btn tutor-btn-primary">
								<?php esc_html_e( 'Ver Clases', 'tutor-course-resources' ); ?>
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="drive-empty-state">
					<div class="empty-icon">
						<span class="dashicons dashicons-welcome-learn-more" style="font-size: 64px; color: #ccc;"></span>
					</div>
					<h3><?php esc_html_e( 'No estás matriculado en ningún curso', 'tutor-course-resources' ); ?></h3>
					<p><?php esc_html_e( 'Cuando te matricules en un curso, aquí verás los recursos organizados por clase.', 'tutor-course-resources' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

