<?php
/**
 * Template: Otros archivos del gestor de recursos en lección
 *
 * @package TutorCourseResources
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin = Tutor_Course_Resources::get_instance();
$lesson_id = isset( $lesson_id ) ? $lesson_id : ( isset( $post ) ? $post->ID : get_the_ID() );

// Obtener el curso padre de la lección
$course_id = isset( $course_id ) ? $course_id : 0;
if ( ! $course_id && function_exists( 'tutor_utils' ) ) {
	$course_id = tutor_utils()->get_course_id_by_content( $lesson_id );
}

// Obtener recursos relacionados específicamente con esta lección
$lesson_resources = array();
if ( $course_id && $lesson_id ) {
	$lesson_resources = $plugin->get_resources_by_lesson( $lesson_id );
	
	// También obtener recursos del curso que no estén asociados a lecciones específicas
	$course_resources = $plugin->get_course_resources( $course_id, 0 );
} else {
	$course_resources = array();
}

// Combinar y filtrar recursos duplicados
$all_resources = array();
$resource_ids = array();

// Primero agregar recursos específicos de la lección
foreach ( $lesson_resources as $resource ) {
	if ( ! in_array( $resource->id, $resource_ids ) ) {
		$all_resources[] = $resource;
		$resource_ids[] = $resource->id;
	}
}

// Luego agregar recursos del curso que no tengan lecciones asociadas
foreach ( $course_resources as $resource ) {
	$resource_lessons = $plugin->get_resource_lessons( $resource->id );
	
	// Si el recurso no tiene lecciones asociadas, mostrarlo en todas las lecciones del curso
	if ( empty( $resource_lessons ) ) {
		if ( ! in_array( $resource->id, $resource_ids ) ) {
			$all_resources[] = $resource;
			$resource_ids[] = $resource->id;
		}
	}
}

// Filtrar recursos por acceso
$accessible_resources = array();
foreach ( $all_resources as $resource ) {
	if ( $plugin->can_user_access_resource( $resource ) ) {
		$accessible_resources[] = $resource;
	}
}
?>
<div id="tutor-course-spotlight-other-files" class="tutor-tab-item<?php echo esc_attr( $is_active ? ' is-active' : '' ); ?>">
	<div class="tutor-container">
		<div class="tutor-row tutor-justify-center">
			<div class="tutor-col-xl-8">
				<div class="tutor-fs-5 tutor-fw-medium tutor-color-black"><?php esc_html_e( 'Otros archivos', 'tutor-course-resources' ); ?></div>
				<div class="tutor-lesson-resources-list" style="margin-top: 20px;">
					<?php if ( ! empty( $accessible_resources ) ) : ?>
						<?php foreach ( $accessible_resources as $resource ) : 
						$file_url = '';
						if ( $resource->resource_type === 'file' && $resource->file_id ) {
							$file_url = wp_get_attachment_url( $resource->file_id );
						} elseif ( $resource->resource_type === 'drive' && $resource->resource_url ) {
							$file_url = $resource->resource_url;
						}
						
						if ( empty( $file_url ) ) {
							continue;
						}
						
						$file_icon = 'dashicons-media-document';
						$file_name = $resource->title;
						
						if ( $resource->resource_type === 'drive' ) {
							$file_icon = 'dashicons-google';
						} elseif ( $resource->file_id ) {
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
							
							// Obtener nombre del archivo
							$attachment = get_post( $resource->file_id );
							if ( $attachment ) {
								$file_name = $attachment->post_title ? $attachment->post_title : basename( get_attached_file( $resource->file_id ) );
							}
						}
					?>
						<div class="tutor-resource-item" style="display: flex; align-items: center; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; background: #fff;">
							<div class="resource-icon" style="font-size: 32px; margin-right: 15px; color: #3e64de;">
								<span class="dashicons <?php echo esc_attr( $file_icon ); ?>"></span>
							</div>
							<div class="resource-content" style="flex: 1;">
								<h4 style="margin: 0 0 5px 0; font-size: 16px; font-weight: 600;">
									<?php echo esc_html( $resource->title ); ?>
								</h4>
								<?php if ( ! empty( $resource->description ) ) : ?>
									<p class="resource-description" style="margin: 0 0 10px 0; color: #666; font-size: 14px;">
										<?php echo esc_html( $resource->description ); ?>
									</p>
								<?php endif; ?>
								<?php if ( $resource->file_id ) : 
									$file_path = get_attached_file( $resource->file_id );
									if ( $file_path && file_exists( $file_path ) ) :
								?>
									<p style="margin: 0 0 10px 0; color: #999; font-size: 12px;">
										<?php echo esc_html( size_format( filesize( $file_path ) ) ); ?>
									</p>
								<?php endif; endif; ?>
								<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="tutor-btn tutor-btn-primary" style="display: inline-block; padding: 8px 16px; text-decoration: none;">
									<?php esc_html_e( 'Descargar/Ver', 'tutor-course-resources' ); ?>
								</a>
							</div>
						</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'No hay archivos disponibles en este momento.', 'tutor-course-resources' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

