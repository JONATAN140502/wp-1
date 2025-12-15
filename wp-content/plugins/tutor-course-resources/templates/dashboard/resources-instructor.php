<?php
/**
 * Template: Recursos para Instructores - Vista tipo Google Drive
 *
 * @package TutorCourseResources
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin = Tutor_Course_Resources::get_instance();

// Carpeta actual
$current_folder_id = isset( $_GET['folder_id'] ) ? intval( $_GET['folder_id'] ) : 0;
$current_folder = null;
if ( $current_folder_id > 0 ) {
	$current_folder = $plugin->get_folder( $current_folder_id );
}

// Obtener cursos del instructor
$user_id = get_current_user_id();
$is_admin = current_user_can( 'administrator' );

if ( $is_admin ) {
	$courses = $plugin->get_all_courses();
} else {
	// Obtener cursos donde es instructor
	$courses = array();
	
	// Intentar usar el método del plugin primero
	if ( method_exists( $plugin, 'get_instructor_courses' ) ) {
		$courses = $plugin->get_instructor_courses( $user_id );
	}
	
	// Si no hay cursos, intentar método directo de Tutor LMS
	if ( empty( $courses ) && class_exists( '\Tutor\Models\CourseModel' ) ) {
		$courses = \Tutor\Models\CourseModel::get_courses_by_instructor( $user_id, 'publish', 0, 0, false, array( tutor()->course_post_type ) );
	}
	
	// Si aún no hay cursos, intentar método alternativo
	if ( empty( $courses ) && function_exists( 'tutor' ) ) {
		$args = array(
			'post_type' => tutor()->course_post_type,
			'post_status' => 'publish',
			'author' => $user_id,
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		);
		$courses = get_posts( $args );
	}
	
	// Convertir a objetos WP_Post si es necesario
	if ( ! empty( $courses ) && is_array( $courses ) ) {
		$wp_posts = array();
		foreach ( $courses as $course ) {
			if ( is_object( $course ) && isset( $course->ID ) ) {
				// Ya es un objeto con ID, obtener el post completo
				$post = get_post( $course->ID );
				if ( $post ) {
					$wp_posts[] = $post;
				}
			} elseif ( is_numeric( $course ) ) {
				// Es solo un ID
				$post = get_post( $course );
				if ( $post ) {
					$wp_posts[] = $post;
				}
			} elseif ( is_object( $course ) && isset( $course->post_type ) ) {
				// Ya es un WP_Post
				$wp_posts[] = $course;
			}
		}
		$courses = array_filter( $wp_posts ); // Eliminar nulls
	}
}

// Obtener carpetas y recursos de la carpeta actual
$folders = $plugin->get_folders( $current_folder_id );
$resources = $plugin->get_resources_by_folder( $current_folder_id );

// Filtrar carpetas y recursos por acceso
$accessible_folders = array();
foreach ( $folders as $folder ) {
	if ( $plugin->can_user_access_folder( $folder ) ) {
		$accessible_folders[] = $folder;
	}
}

$accessible_resources = array();
foreach ( $resources as $resource ) {
	if ( $plugin->can_user_access_resource( $resource ) ) {
		$accessible_resources[] = $resource;
	}
}
?>

<div class="tutor-drive-wrapper-frontend">
	<!-- Header tipo Drive -->
	<div class="tutor-drive-header-frontend">
		<div class="drive-header-left">
			<h1 class="drive-title">
				<span class="dashicons dashicons-google"></span>
				<?php esc_html_e( 'Recursos de Cursos', 'tutor-course-resources' ); ?>
			</h1>
			<?php if ( $current_folder ) : ?>
				<div class="breadcrumb">
					<a href="<?php echo esc_url( tutor_utils()->tutor_dashboard_url( 'course-resources' ) ); ?>">
						<?php esc_html_e( 'Inicio', 'tutor-course-resources' ); ?>
					</a>
					<span class="separator">/</span>
					<span><?php echo esc_html( $current_folder->name ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<div class="drive-header-right">
			<button type="button" class="tutor-btn" id="select-all-btn" style="display: none;">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Seleccionar Todo', 'tutor-course-resources' ); ?>
			</button>
			<button type="button" class="tutor-btn tutor-btn-danger" id="delete-selected-btn" style="display: none;">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Eliminar Seleccionados', 'tutor-course-resources' ); ?>
			</button>
			<button type="button" class="tutor-btn tutor-btn-primary" id="create-folder-btn">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php esc_html_e( 'Nueva Carpeta', 'tutor-course-resources' ); ?>
			</button>
			<button type="button" class="tutor-btn tutor-btn-primary" id="upload-file-btn">
				<span class="dashicons dashicons-upload"></span>
				<?php esc_html_e( 'Subir Archivo', 'tutor-course-resources' ); ?>
			</button>
			<button type="button" class="tutor-btn tutor-btn-primary" id="add-drive-link-btn">
				<span class="dashicons dashicons-admin-links"></span>
				<?php esc_html_e( 'Enlace Drive', 'tutor-course-resources' ); ?>
			</button>
		</div>
	</div>
	
	<!-- Barra de herramientas -->
	<div class="tutor-drive-toolbar-frontend">
		<div class="toolbar-left">
			<button type="button" class="tutor-btn" id="toggle-selection-mode-btn" title="<?php esc_attr_e( 'Activar modo selección múltiple', 'tutor-course-resources' ); ?>">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Seleccionar', 'tutor-course-resources' ); ?>
			</button>
			<select id="filter-course" class="drive-filter">
				<option value="0"><?php esc_html_e( 'Todos los cursos', 'tutor-course-resources' ); ?></option>
				<?php if ( ! empty( $courses ) ) : ?>
					<?php foreach ( $courses as $course ) : 
						$course_id = is_object( $course ) ? $course->ID : ( is_numeric( $course ) ? $course : 0 );
						$course_title = is_object( $course ) ? $course->post_title : get_the_title( $course_id );
					?>
						<option value="<?php echo esc_attr( $course_id ); ?>">
							<?php echo esc_html( $course_title ); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>
		</div>
		<div class="toolbar-right">
			<div class="view-toggle">
				<button type="button" class="view-btn active" data-view="grid" title="<?php esc_attr_e( 'Vista de cuadrícula', 'tutor-course-resources' ); ?>">
					<span class="dashicons dashicons-grid-view"></span>
				</button>
				<button type="button" class="view-btn" data-view="list" title="<?php esc_attr_e( 'Vista de lista', 'tutor-course-resources' ); ?>">
					<span class="dashicons dashicons-list-view"></span>
				</button>
			</div>
		</div>
	</div>
	
	<!-- Contenido tipo Drive -->
	<div class="tutor-drive-content-frontend" id="drive-content" data-view="grid">
		<!-- Carpetas -->
		<?php if ( ! empty( $accessible_folders ) ) : ?>
			<div class="drive-items folders-section">
				<?php foreach ( $accessible_folders as $folder ) : 
					$folder_url = tutor_utils()->tutor_dashboard_url( 'course-resources' ) . '?folder_id=' . $folder->id;
					$course_name = '';
					if ( $folder->course_id > 0 ) {
						$course = get_post( $folder->course_id );
						$course_name = $course ? $course->post_title : '';
					}
					
					// Obtener información del creador
					$creator = null;
					$creator_name = '';
					$creator_email = '';
					if ( ! empty( $folder->created_by ) ) {
						$creator = get_userdata( $folder->created_by );
						if ( $creator ) {
							$creator_name = $creator->display_name ? $creator->display_name : $creator->user_login;
							$creator_email = $creator->user_email;
						}
					}
					
					// Formatear fecha de creación
					$created_date = '';
					if ( ! empty( $folder->created_at ) ) {
						$created_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $folder->created_at ) );
					}
				?>
					<div class="drive-item folder-item" data-item-id="<?php echo esc_attr( $folder->id ); ?>" data-item-type="folder">
						<div class="item-checkbox" style="display: none;">
							<input type="checkbox" class="item-select" data-item-id="<?php echo esc_attr( $folder->id ); ?>" data-item-type="folder">
						</div>
						<div class="item-icon">
							<span class="dashicons dashicons-category"></span>
						</div>
						<div class="item-name" title="<?php echo esc_attr( $folder->name ); ?>">
							<?php echo esc_html( $folder->name ); ?>
						</div>
						<div class="item-meta">
							<?php if ( $course_name ) : ?>
								<span class="course-badge"><?php echo esc_html( $course_name ); ?></span>
							<?php elseif ( $folder->is_libre ) : ?>
								<span class="libre-badge"><?php esc_html_e( 'Libre', 'tutor-course-resources' ); ?></span>
							<?php endif; ?>
							<?php if ( $creator_name ) : ?>
								<span class="creator-info" title="<?php echo esc_attr( sprintf( __( 'Creado por: %s (%s)', 'tutor-course-resources' ), $creator_name, $creator_email ) ); ?>" style="margin-left: 8px; color: #666; font-size: 12px;">
									<span class="dashicons dashicons-admin-users" style="font-size: 14px; vertical-align: middle;"></span>
									<?php echo esc_html( $creator_name ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $created_date ) : ?>
								<span class="created-date" style="margin-left: 8px; color: #999; font-size: 11px;">
									<?php echo esc_html( $created_date ); ?>
								</span>
							<?php endif; ?>
						</div>
						<div class="item-actions">
							<a href="<?php echo esc_url( $folder_url ); ?>" class="item-action" title="<?php esc_attr_e( 'Abrir', 'tutor-course-resources' ); ?>">
								<span class="dashicons dashicons-visibility"></span>
							</a>
							<button type="button" class="item-action edit-folder" data-folder-id="<?php echo esc_attr( $folder->id ); ?>" title="<?php esc_attr_e( 'Editar', 'tutor-course-resources' ); ?>">
								<span class="dashicons dashicons-edit"></span>
							</button>
							<button type="button" class="item-action delete-folder" data-folder-id="<?php echo esc_attr( $folder->id ); ?>" title="<?php esc_attr_e( 'Eliminar', 'tutor-course-resources' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		
		<!-- Archivos y Enlaces -->
		<?php if ( ! empty( $accessible_resources ) ) : ?>
			<div class="drive-items files-section">
				<?php foreach ( $accessible_resources as $resource ) : 
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
					
					// Obtener información del creador
					$creator = null;
					$creator_name = '';
					$creator_email = '';
					if ( ! empty( $resource->created_by ) ) {
						$creator = get_userdata( $resource->created_by );
						if ( $creator ) {
							$creator_name = $creator->display_name ? $creator->display_name : $creator->user_login;
							$creator_email = $creator->user_email;
						}
					}
					
					// Formatear fecha de creación
					$created_date = '';
					if ( ! empty( $resource->created_at ) ) {
						$created_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $resource->created_at ) );
					}
					
					// Obtener tamaño del archivo
					$file_size = '';
					if ( $resource->file_id ) {
						$file_path = get_attached_file( $resource->file_id );
						if ( $file_path && file_exists( $file_path ) ) {
							$file_size = size_format( filesize( $file_path ) );
						}
					} elseif ( $resource->resource_type === 'drive' ) {
						$file_size = __( 'Enlace externo', 'tutor-course-resources' );
					}
				?>
					<div class="drive-item file-item" data-item-id="<?php echo esc_attr( $resource->id ); ?>" data-item-type="resource">
						<div class="item-checkbox" style="display: none;">
							<input type="checkbox" class="item-select" data-item-id="<?php echo esc_attr( $resource->id ); ?>" data-item-type="resource">
						</div>
						<div class="item-icon">
							<span class="dashicons <?php echo esc_attr( $file_icon ); ?>"></span>
						</div>
						<div class="item-name" title="<?php echo esc_attr( $resource->title ); ?>">
							<?php echo esc_html( $resource->title ); ?>
						</div>
						<div class="item-meta">
							<?php if ( $file_size ) : ?>
								<span class="file-size"><?php echo esc_html( $file_size ); ?></span>
							<?php endif; ?>
							<?php if ( $creator_name ) : ?>
								<span class="creator-info" title="<?php echo esc_attr( sprintf( __( 'Creado por: %s (%s)', 'tutor-course-resources' ), $creator_name, $creator_email ) ); ?>" style="margin-left: 8px; color: #666; font-size: 12px;">
									<span class="dashicons dashicons-admin-users" style="font-size: 14px; vertical-align: middle;"></span>
									<?php echo esc_html( $creator_name ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $created_date ) : ?>
								<span class="created-date" style="margin-left: 8px; color: #999; font-size: 11px;">
									<?php echo esc_html( $created_date ); ?>
								</span>
							<?php endif; ?>
						</div>
						<div class="item-actions">
							<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="item-action" title="<?php esc_attr_e( 'Abrir', 'tutor-course-resources' ); ?>">
								<span class="dashicons dashicons-visibility"></span>
							</a>
							<button type="button" class="item-action edit-resource" data-resource-id="<?php echo esc_attr( $resource->id ); ?>" title="<?php esc_attr_e( 'Editar', 'tutor-course-resources' ); ?>">
								<span class="dashicons dashicons-edit"></span>
							</button>
							<button type="button" class="item-action delete-resource" data-resource-id="<?php echo esc_attr( $resource->id ); ?>" title="<?php esc_attr_e( 'Eliminar', 'tutor-course-resources' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		
		<!-- Estado vacío -->
		<?php if ( empty( $accessible_folders ) && empty( $accessible_resources ) ) : ?>
			<div class="drive-empty-state">
				<div class="empty-icon">
					<span class="dashicons dashicons-category" style="font-size: 64px; color: #ccc;"></span>
				</div>
				<h3><?php esc_html_e( 'No hay elementos', 'tutor-course-resources' ); ?></h3>
				<p><?php esc_html_e( 'Crea una carpeta o sube un archivo para comenzar.', 'tutor-course-resources' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>

<!-- Modal: Crear/Editar Carpeta -->
<div id="folder-modal" class="tutor-drive-modal-frontend" style="display: none;">
	<div class="modal-content-frontend">
		<div class="modal-header-frontend">
			<h2 id="folder-modal-title"><?php esc_html_e( 'Nueva Carpeta', 'tutor-course-resources' ); ?></h2>
			<button type="button" class="modal-close">&times;</button>
		</div>
		<form id="folder-form">
			<input type="hidden" id="folder-id" value="">
			<input type="hidden" id="folder-parent-id" value="<?php echo esc_attr( $current_folder_id ); ?>">
			
			<div class="form-group">
				<label for="folder-name"><?php esc_html_e( 'Nombre de la Carpeta', 'tutor-course-resources' ); ?> <span class="required">*</span></label>
				<input type="text" id="folder-name" class="tutor-form-control" required>
			</div>
			
			<?php
			// Si estamos dentro de una carpeta con curso, heredar automáticamente
			$parent_course_id = 0;
			$parent_course_name = '';
			if ( $current_folder && $current_folder->course_id > 0 ) {
				$parent_course_id = $current_folder->course_id;
				$parent_course = get_post( $parent_course_id );
				$parent_course_name = $parent_course ? $parent_course->post_title : '';
			}
			?>
			
			<?php if ( $parent_course_id > 0 ) : ?>
				<!-- Si la carpeta padre tiene curso, heredarlo automáticamente -->
				<div class="form-group">
					<label><?php esc_html_e( 'Curso Asociado', 'tutor-course-resources' ); ?></label>
					<div style="padding: 10px; background: #e8f0fe; border-radius: 6px; color: #1a73e8; font-weight: 500;">
						<span class="dashicons dashicons-info" style="vertical-align: middle; margin-right: 5px;"></span>
						<?php echo esc_html( $parent_course_name ); ?>
						<input type="hidden" id="folder-course-id" value="<?php echo esc_attr( $parent_course_id ); ?>">
					</div>
					<p class="description"><?php esc_html_e( 'Esta carpeta heredará automáticamente el curso de la carpeta padre.', 'tutor-course-resources' ); ?></p>
				</div>
				<script>
				jQuery(document).ready(function($) {
					// Cargar lecciones cuando se herede el curso de la carpeta padre
					var parentCourseId = <?php echo esc_js( $parent_course_id ); ?>;
					if (parentCourseId > 0) {
						setTimeout(function() {
							if (typeof loadLessonsForCourse === 'function') {
								loadLessonsForCourse(parentCourseId, 'folder-lesson-ids', []);
							}
						}, 500);
					}
				});
				</script>
			<?php else : ?>
				<!-- Si no hay carpeta padre con curso, permitir seleccionar -->
				<div class="form-group">
					<label for="folder-course-id"><?php esc_html_e( 'Asociar a Curso', 'tutor-course-resources' ); ?></label>
					<select id="folder-course-id" class="tutor-form-control">
						<option value="0"><?php esc_html_e( 'Carpeta Libre (sin curso)', 'tutor-course-resources' ); ?></option>
						<?php if ( ! empty( $courses ) && is_array( $courses ) ) : ?>
							<?php foreach ( $courses as $course ) : 
								$course_id = 0;
								$course_title = '';
								
								if ( is_object( $course ) && isset( $course->ID ) ) {
									$course_id = intval( $course->ID );
									$course_title = isset( $course->post_title ) ? $course->post_title : get_the_title( $course_id );
								} elseif ( is_numeric( $course ) ) {
									$course_id = intval( $course );
									$course_title = get_the_title( $course_id );
								}
								
								if ( $course_id > 0 && ! empty( $course_title ) ) :
							?>
								<option value="<?php echo esc_attr( $course_id ); ?>">
									<?php echo esc_html( $course_title ); ?>
								</option>
							<?php 
								endif;
							endforeach; ?>
						<?php else : ?>
							<option value="0" disabled><?php esc_html_e( 'No hay cursos disponibles', 'tutor-course-resources' ); ?></option>
						<?php endif; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Selecciona un curso o déjalo libre para usar en cualquier curso.', 'tutor-course-resources' ); ?></p>
					<?php if ( empty( $courses ) || ! is_array( $courses ) ) : ?>
						<p class="description" style="color: #d63638;">
							<?php esc_html_e( 'No se encontraron cursos. Asegúrate de que eres instructor de al menos un curso.', 'tutor-course-resources' ); ?>
						</p>
					<?php endif; ?>
				</div>
				
				<div class="form-group">
					<label for="folder-is-libre" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" id="folder-is-libre" value="1">
						<span><?php esc_html_e( 'Marcar como carpeta libre', 'tutor-course-resources' ); ?></span>
					</label>
				</div>
			<?php endif; ?>
			
			<!-- Campo para seleccionar múltiples lecciones - Siempre disponible -->
			<div class="form-group" id="folder-lessons-wrapper" style="display: none;">
				<label for="folder-lesson-ids"><?php esc_html_e( 'Asociar a Lecciones (opcional)', 'tutor-course-resources' ); ?></label>
				<p class="description"><?php esc_html_e( 'Selecciona las lecciones donde esta carpeta estará disponible. Si no seleccionas ninguna, estará disponible en todo el curso.', 'tutor-course-resources' ); ?></p>
				<select id="folder-lesson-ids" multiple class="tutor-form-control" style="min-height: 150px;">
					<!-- Las lecciones se cargarán dinámicamente mediante JavaScript -->
				</select>
				<p class="description"><?php esc_html_e( 'Mantén presionada la tecla Ctrl (Cmd en Mac) para seleccionar múltiples lecciones.', 'tutor-course-resources' ); ?></p>
			</div>
			
			<div class="form-group">
				<label><?php esc_html_e( 'Control de Acceso', 'tutor-course-resources' ); ?></label>
				<fieldset>
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" id="folder-access-students" value="1" checked>
						<span><?php esc_html_e( 'Permitir acceso a estudiantes inscritos en el curso', 'tutor-course-resources' ); ?></span>
					</label>
					<br><br>
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" id="folder-access-teachers" value="1">
						<span><?php esc_html_e( 'Permitir acceso a otros docentes', 'tutor-course-resources' ); ?></span>
					</label>
				</fieldset>
			</div>
			
			<div class="form-group">
				<label for="folder-access-students-list"><?php esc_html_e( 'Acceso Individual por Estudiante', 'tutor-course-resources' ); ?></label>
				<p class="description"><?php esc_html_e( 'Selecciona estudiantes específicos para acceso individual. Si dejas vacío, se aplicarán las reglas generales.', 'tutor-course-resources' ); ?></p>
				<select id="folder-access-students-list" multiple class="tutor-form-control" style="min-height: 120px;">
					<?php
					// Obtener todos los estudiantes de los cursos del instructor
					$all_students = array();
					foreach ( $courses as $course ) {
						$course_id = is_object( $course ) ? $course->ID : ( is_numeric( $course ) ? $course : 0 );
						if ( $course_id > 0 ) {
							$course_students = $plugin->get_course_students( $course_id );
							foreach ( $course_students as $student ) {
								$student_id = is_object( $student ) ? $student->ID : ( is_numeric( $student ) ? $student : 0 );
								if ( $student_id > 0 && ! isset( $all_students[ $student_id ] ) ) {
									$all_students[ $student_id ] = get_userdata( $student_id );
								}
							}
						}
					}
					foreach ( $all_students as $student_id => $student ) :
						if ( ! $student ) continue;
					?>
						<option value="<?php echo esc_attr( $student_id ); ?>">
							<?php echo esc_html( $student->display_name . ' (' . $student->user_email . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Mantén presionada la tecla Ctrl (Cmd en Mac) para seleccionar múltiples estudiantes.', 'tutor-course-resources' ); ?></p>
			</div>
			
			<div class="form-actions">
				<button type="submit" class="tutor-btn tutor-btn-primary"><?php esc_html_e( 'Guardar Carpeta', 'tutor-course-resources' ); ?></button>
				<button type="button" class="tutor-btn tutor-btn-outline cancel-modal"><?php esc_html_e( 'Cancelar', 'tutor-course-resources' ); ?></button>
			</div>
		</form>
	</div>
</div>

<!-- Modal: Subir Archivo -->
<div id="file-modal" class="tutor-drive-modal-frontend" style="display: none;">
	<div class="modal-content-frontend">
		<div class="modal-header-frontend">
			<h2><?php esc_html_e( 'Subir Archivo', 'tutor-course-resources' ); ?></h2>
			<button type="button" class="modal-close">&times;</button>
		</div>
		<form id="file-form">
			<input type="hidden" id="file-folder-id" value="<?php echo esc_attr( $current_folder_id ); ?>">
			
			<div class="form-group">
				<label for="file-title"><?php esc_html_e( 'Título', 'tutor-course-resources' ); ?> <span class="required">*</span></label>
				<input type="text" id="file-title" class="tutor-form-control" required>
			</div>
			
			<?php
			// Si estamos dentro de una carpeta con curso, heredarlo automáticamente
			$file_parent_course_id = 0;
			$file_parent_course_name = '';
			if ( $current_folder && $current_folder->course_id > 0 ) {
				$file_parent_course_id = $current_folder->course_id;
				$file_parent_course = get_post( $file_parent_course_id );
				$file_parent_course_name = $file_parent_course ? $file_parent_course->post_title : '';
			}
			?>
			
			<?php if ( $file_parent_course_id > 0 ) : ?>
				<!-- Si la carpeta padre tiene curso, heredarlo automáticamente -->
				<div class="form-group">
					<label><?php esc_html_e( 'Curso Asociado', 'tutor-course-resources' ); ?></label>
					<div style="padding: 10px; background: #e8f0fe; border-radius: 6px; color: #1a73e8; font-weight: 500;">
						<span class="dashicons dashicons-info" style="vertical-align: middle; margin-right: 5px;"></span>
						<?php echo esc_html( $file_parent_course_name ); ?>
						<input type="hidden" id="file-course-id" value="<?php echo esc_attr( $file_parent_course_id ); ?>">
					</div>
				</div>
				<script>
				jQuery(document).ready(function($) {
					// Esta funcionalidad ya está manejada por la función loadFolderLessonsForResource en admin.js
					// que se llama cuando se abre el modal de archivo
				});
				</script>
			<?php else : ?>
				<!-- Si no hay carpeta padre con curso, permitir seleccionar -->
				<div class="form-group">
					<label for="file-course-id"><?php esc_html_e( 'Asociar a Curso', 'tutor-course-resources' ); ?></label>
					<select id="file-course-id" class="tutor-form-control">
						<option value="0"><?php esc_html_e( 'Sin curso específico', 'tutor-course-resources' ); ?></option>
						<?php if ( ! empty( $courses ) ) : ?>
							<?php foreach ( $courses as $course ) : 
								$course_id = is_object( $course ) ? $course->ID : ( is_numeric( $course ) ? $course : 0 );
								$course_title = is_object( $course ) ? $course->post_title : get_the_title( $course_id );
							?>
								<option value="<?php echo esc_attr( $course_id ); ?>">
									<?php echo esc_html( $course_title ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Opcional: Selecciona un curso para asociar este archivo.', 'tutor-course-resources' ); ?></p>
				</div>
			<?php endif; ?>
			
			<div class="form-group">
				<label><?php esc_html_e( 'Archivo', 'tutor-course-resources' ); ?></label>
				<input type="hidden" id="file-id" value="">
				<button type="button" class="tutor-btn tutor-btn-outline" id="select-file-btn"><?php esc_html_e( 'Seleccionar Archivo', 'tutor-course-resources' ); ?></button>
				<span id="selected-file-name"></span>
			</div>
			
			<!-- Campo para seleccionar múltiples lecciones -->
			<div class="form-group" id="file-lessons-wrapper">
				<label for="file-lesson-ids"><?php esc_html_e( 'Asociar a Lecciones (opcional)', 'tutor-course-resources' ); ?></label>
				<p class="description"><?php esc_html_e( 'Selecciona las lecciones donde este archivo estará disponible. Si no seleccionas ninguna, heredará las lecciones de la carpeta padre (si tiene).', 'tutor-course-resources' ); ?></p>
				<select id="file-lesson-ids" multiple class="tutor-form-control" style="min-height: 150px;">
					<!-- Las lecciones se cargarán dinámicamente mediante JavaScript -->
				</select>
				<p class="description"><?php esc_html_e( 'Mantén presionada la tecla Ctrl (Cmd en Mac) para seleccionar múltiples lecciones.', 'tutor-course-resources' ); ?></p>
			</div>
			
			<script>
			jQuery(document).ready(function($) {
				// Ocultar los campos de lecciones inicialmente
				$('#file-lessons-wrapper, #link-lessons-wrapper').hide();
				
				// Cargar lecciones de la carpeta padre cuando se abra el modal de enlace
				var currentFolderId = <?php echo esc_js( $current_folder_id ); ?>;
				if (currentFolderId > 0) {
					$('#drive-link-modal').on('shown', function() {
						var folderId = $('#link-folder-id').val();
						if (folderId && folderId > 0) {
							// Obtener datos de la carpeta para heredar lecciones
							$.ajax({
								url: tutorResources.ajaxurl,
								type: 'POST',
								data: {
									action: 'tutor_get_folder_data',
									nonce: tutorResources.nonce,
									folder_id: folderId,
								},
								success: function(response) {
									if (response.success && response.data.folder) {
										var folder = response.data.folder;
										if (folder.course_id && folder.course_id > 0) {
											// Obtener lecciones de la carpeta
											$.ajax({
												url: tutorResources.ajaxurl,
												type: 'POST',
												data: {
													action: 'tutor_get_folder_lessons',
													nonce: tutorResources.nonce,
													folder_id: folderId,
												},
												success: function(lessonsResponse) {
													var folderLessonIds = lessonsResponse.success && lessonsResponse.data.lesson_ids ? lessonsResponse.data.lesson_ids : [];
													// Cargar lecciones del curso y preseleccionar las de la carpeta
													loadLessonsForCourse(folder.course_id, 'link-lesson-ids', folderLessonIds);
												}
											});
										}
									}
								}
							});
						}
					});
				}
			});
			</script>
			
			<div class="form-group">
				<label><?php esc_html_e( 'Control de Acceso', 'tutor-course-resources' ); ?></label>
				<fieldset>
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" id="file-access-students" value="1" checked>
						<span><?php esc_html_e( 'Permitir acceso a estudiantes', 'tutor-course-resources' ); ?></span>
					</label>
					<br><br>
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" id="file-access-teachers" value="1">
						<span><?php esc_html_e( 'Permitir acceso a otros docentes', 'tutor-course-resources' ); ?></span>
					</label>
				</fieldset>
			</div>
			
			<div class="form-group">
				<label for="file-access-students-list"><?php esc_html_e( 'Acceso Individual por Estudiante', 'tutor-course-resources' ); ?></label>
				<p class="description"><?php esc_html_e( 'Selecciona estudiantes específicos para acceso individual. Si dejas vacío, se aplicarán las reglas generales.', 'tutor-course-resources' ); ?></p>
				<select id="file-access-students-list" multiple class="tutor-form-control" style="min-height: 120px;">
					<?php
					$all_students = array();
					foreach ( $courses as $course ) {
						$course_id = is_object( $course ) ? $course->ID : ( is_numeric( $course ) ? $course : 0 );
						if ( $course_id > 0 ) {
							$course_students = $plugin->get_course_students( $course_id );
							foreach ( $course_students as $student ) {
								$student_id = is_object( $student ) ? $student->ID : ( is_numeric( $student ) ? $student : 0 );
								if ( $student_id > 0 && ! isset( $all_students[ $student_id ] ) ) {
									$all_students[ $student_id ] = get_userdata( $student_id );
								}
							}
						}
					}
					foreach ( $all_students as $student_id => $student ) :
						if ( ! $student ) continue;
					?>
						<option value="<?php echo esc_attr( $student_id ); ?>">
							<?php echo esc_html( $student->display_name . ' (' . $student->user_email . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			
			<div class="form-actions">
				<button type="submit" class="tutor-btn tutor-btn-primary"><?php esc_html_e( 'Guardar Archivo', 'tutor-course-resources' ); ?></button>
				<button type="button" class="tutor-btn tutor-btn-outline cancel-modal"><?php esc_html_e( 'Cancelar', 'tutor-course-resources' ); ?></button>
			</div>
		</form>
	</div>
</div>

<!-- Modal: Enlace Google Drive -->
<div id="drive-link-modal" class="tutor-drive-modal-frontend" style="display: none;">
	<div class="modal-content-frontend">
		<div class="modal-header-frontend">
			<h2><?php esc_html_e( 'Agregar Enlace de Google Drive', 'tutor-course-resources' ); ?></h2>
			<button type="button" class="modal-close">&times;</button>
		</div>
		<form id="drive-link-form">
			<input type="hidden" id="link-folder-id" value="<?php echo esc_attr( $current_folder_id ); ?>">
			
			<div class="form-group">
				<label for="link-title"><?php esc_html_e( 'Título', 'tutor-course-resources' ); ?> <span class="required">*</span></label>
				<input type="text" id="link-title" class="tutor-form-control" required>
			</div>
			
			<?php
			// Si estamos dentro de una carpeta con curso, heredarlo automáticamente
			$link_parent_course_id = 0;
			$link_parent_course_name = '';
			if ( $current_folder && $current_folder->course_id > 0 ) {
				$link_parent_course_id = $current_folder->course_id;
				$link_parent_course = get_post( $link_parent_course_id );
				$link_parent_course_name = $link_parent_course ? $link_parent_course->post_title : '';
			}
			?>
			
			<?php if ( $link_parent_course_id > 0 ) : ?>
				<!-- Si la carpeta padre tiene curso, heredarlo automáticamente -->
				<div class="form-group">
					<label><?php esc_html_e( 'Curso Asociado', 'tutor-course-resources' ); ?></label>
					<div style="padding: 10px; background: #e8f0fe; border-radius: 6px; color: #1a73e8; font-weight: 500;">
						<span class="dashicons dashicons-info" style="vertical-align: middle; margin-right: 5px;"></span>
						<?php echo esc_html( $link_parent_course_name ); ?>
						<input type="hidden" id="link-course-id" value="<?php echo esc_attr( $link_parent_course_id ); ?>">
					</div>
					<p class="description"><?php esc_html_e( 'Este enlace heredará automáticamente el curso de la carpeta padre.', 'tutor-course-resources' ); ?></p>
				</div>
				<script>
				jQuery(document).ready(function($) {
					// Cargar lecciones cuando se herede el curso de la carpeta padre
					var parentCourseId = <?php echo esc_js( $link_parent_course_id ); ?>;
					if (parentCourseId > 0) {
						// Cargar lecciones cuando se abra el modal (usar evento personalizado o timeout)
						$(document).on('click', '#add-drive-link-btn', function() {
							setTimeout(function() {
								if (typeof loadLessonsForCourse === 'function') {
									loadLessonsForCourse(parentCourseId, 'link-lesson-ids', []);
								}
							}, 500);
						});
					}
				});
				</script>
			<?php else : ?>
				<!-- Si no hay carpeta padre con curso, permitir seleccionar -->
				<div class="form-group">
					<label for="link-course-id"><?php esc_html_e( 'Asociar a Curso', 'tutor-course-resources' ); ?></label>
					<select id="link-course-id" class="tutor-form-control">
						<option value="0"><?php esc_html_e( 'Sin curso específico', 'tutor-course-resources' ); ?></option>
						<?php if ( ! empty( $courses ) ) : ?>
							<?php foreach ( $courses as $course ) : 
								$course_id = is_object( $course ) ? $course->ID : ( is_numeric( $course ) ? $course : 0 );
								$course_title = is_object( $course ) ? $course->post_title : get_the_title( $course_id );
							?>
								<option value="<?php echo esc_attr( $course_id ); ?>">
									<?php echo esc_html( $course_title ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Opcional: Selecciona un curso para asociar este enlace.', 'tutor-course-resources' ); ?></p>
				</div>
			<?php endif; ?>
			
			<div class="form-group">
				<label for="link-url"><?php esc_html_e( 'URL de Google Drive', 'tutor-course-resources' ); ?> <span class="required">*</span></label>
				<input type="url" id="link-url" class="tutor-form-control" placeholder="https://drive.google.com/..." required>
			</div>
			
			<!-- Campo para seleccionar múltiples lecciones en enlace Drive -->
			<div class="form-group" id="link-lessons-wrapper" style="display: none;">
				<label for="link-lesson-ids"><?php esc_html_e( 'Asociar a Lecciones (opcional)', 'tutor-course-resources' ); ?></label>
				<p class="description"><?php esc_html_e( 'Selecciona las lecciones donde este enlace estará disponible. Si no seleccionas ninguna, heredará las lecciones de la carpeta padre (si tiene).', 'tutor-course-resources' ); ?></p>
				<select id="link-lesson-ids" multiple class="tutor-form-control" style="min-height: 150px;">
					<!-- Las lecciones se cargarán dinámicamente mediante JavaScript -->
				</select>
				<p class="description"><?php esc_html_e( 'Mantén presionada la tecla Ctrl (Cmd en Mac) para seleccionar múltiples lecciones.', 'tutor-course-resources' ); ?></p>
			</div>
			
			<div class="form-group">
				<label><?php esc_html_e( 'Control de Acceso', 'tutor-course-resources' ); ?></label>
				<fieldset>
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" id="link-access-students" value="1" checked>
						<span><?php esc_html_e( 'Permitir acceso a estudiantes', 'tutor-course-resources' ); ?></span>
					</label>
					<br><br>
					<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
						<input type="checkbox" id="link-access-teachers" value="1">
						<span><?php esc_html_e( 'Permitir acceso a otros docentes', 'tutor-course-resources' ); ?></span>
					</label>
				</fieldset>
			</div>
			
			<div class="form-group">
				<label for="link-access-students-list"><?php esc_html_e( 'Acceso Individual por Estudiante', 'tutor-course-resources' ); ?></label>
				<p class="description"><?php esc_html_e( 'Selecciona estudiantes específicos para acceso individual.', 'tutor-course-resources' ); ?></p>
				<select id="link-access-students-list" multiple class="tutor-form-control" style="min-height: 120px;">
					<?php
					$all_students = array();
					foreach ( $courses as $course ) {
						$course_id = is_object( $course ) ? $course->ID : ( is_numeric( $course ) ? $course : 0 );
						if ( $course_id > 0 ) {
							$course_students = $plugin->get_course_students( $course_id );
							foreach ( $course_students as $student ) {
								$student_id = is_object( $student ) ? $student->ID : ( is_numeric( $student ) ? $student : 0 );
								if ( $student_id > 0 && ! isset( $all_students[ $student_id ] ) ) {
									$all_students[ $student_id ] = get_userdata( $student_id );
								}
							}
						}
					}
					foreach ( $all_students as $student_id => $student ) :
						if ( ! $student ) continue;
					?>
						<option value="<?php echo esc_attr( $student_id ); ?>">
							<?php echo esc_html( $student->display_name . ' (' . $student->user_email . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			
			<div class="form-actions">
				<button type="submit" class="tutor-btn tutor-btn-primary"><?php esc_html_e( 'Guardar Enlace', 'tutor-course-resources' ); ?></button>
				<button type="button" class="tutor-btn tutor-btn-outline cancel-modal"><?php esc_html_e( 'Cancelar', 'tutor-course-resources' ); ?></button>
			</div>
		</form>
	</div>
</div>

