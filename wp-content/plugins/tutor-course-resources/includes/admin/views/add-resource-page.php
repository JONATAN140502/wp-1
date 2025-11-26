<?php
/**
 * Vista: Añadir nuevo recurso
 *
 * @package TutorCourseResources
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin = Tutor_Course_Resources::get_instance();

// Procesar formulario
if ( isset( $_POST['save_resource'] ) && check_admin_referer( 'tutor_save_resource' ) ) {
	$resource_data = array(
		'course_id' => isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0,
		'lesson_id' => isset( $_POST['lesson_id'] ) ? intval( $_POST['lesson_id'] ) : 0,
		'resource_type' => isset( $_POST['resource_type'] ) ? sanitize_text_field( $_POST['resource_type'] ) : 'file',
		'resource_url' => isset( $_POST['resource_url'] ) ? esc_url_raw( $_POST['resource_url'] ) : '',
		'file_id' => isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0,
		'title' => isset( $_POST['resource_title'] ) ? sanitize_text_field( $_POST['resource_title'] ) : '',
		'description' => isset( $_POST['resource_description'] ) ? sanitize_textarea_field( $_POST['resource_description'] ) : '',
		'access_students' => isset( $_POST['access_students'] ) ? 1 : 0,
		'access_teachers' => isset( $_POST['access_teachers'] ) ? 1 : 0,
		'access_teachers_list' => isset( $_POST['access_teachers_list'] ) ? sanitize_text_field( implode( ',', array_map( 'intval', $_POST['access_teachers_list'] ) ) ) : '',
	);
	
	if ( ! empty( $resource_data['title'] ) && $resource_data['course_id'] > 0 ) {
		$resource_id = $plugin->save_resource( $resource_data );
		if ( $resource_id ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Recurso guardado correctamente.', 'tutor-course-resources' ) . '</p></div>';
			// Redirigir después de guardar
			wp_redirect( admin_url( 'admin.php?page=tutor-course-resources' ) );
			exit;
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error al guardar el recurso.', 'tutor-course-resources' ) . '</p></div>';
		}
	} else {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Por favor, completa todos los campos requeridos.', 'tutor-course-resources' ) . '</p></div>';
	}
}

$courses = $plugin->get_all_courses();
$instructors = get_users( array( 'role' => 'tutor_instructor' ) );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Añadir Nuevo Recurso', 'tutor-course-resources' ); ?></h1>
	
	<form method="POST" action="">
		<?php wp_nonce_field( 'tutor_save_resource' ); ?>
		<input type="hidden" name="save_resource" value="1">
		
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="course_id"><?php esc_html_e( 'Curso', 'tutor-course-resources' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<select name="course_id" id="course_id" required style="width: 100%; max-width: 400px;">
						<option value=""><?php esc_html_e( '-- Seleccionar Curso --', 'tutor-course-resources' ); ?></option>
						<?php if ( ! empty( $courses ) ) : ?>
							<?php foreach ( $courses as $course ) : ?>
								<option value="<?php echo esc_attr( $course->ID ); ?>">
									<?php echo esc_html( $course->post_title ); ?>
								</option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</td>
			</tr>
			
			<tr>
				<th scope="row">
					<label for="resource_title"><?php esc_html_e( 'Título del Recurso', 'tutor-course-resources' ); ?> <span class="required">*</span></label>
				</th>
				<td>
					<input type="text" name="resource_title" id="resource_title" class="regular-text" required>
				</td>
			</tr>
			
			<tr>
				<th scope="row">
					<label for="resource_type"><?php esc_html_e( 'Tipo de Recurso', 'tutor-course-resources' ); ?></label>
				</th>
				<td>
					<select name="resource_type" id="resource_type">
						<option value="drive"><?php esc_html_e( 'Google Drive (Enlace)', 'tutor-course-resources' ); ?></option>
						<option value="file"><?php esc_html_e( 'Archivo Físico', 'tutor-course-resources' ); ?></option>
					</select>
				</td>
			</tr>
			
			<tr id="drive-url-row">
				<th scope="row">
					<label for="resource_url"><?php esc_html_e( 'URL de Google Drive', 'tutor-course-resources' ); ?></label>
				</th>
				<td>
					<input type="url" name="resource_url" id="resource_url" class="regular-text" placeholder="https://drive.google.com/...">
					<p class="description"><?php esc_html_e( 'Pega aquí el enlace compartido de Google Drive.', 'tutor-course-resources' ); ?></p>
				</td>
			</tr>
			
			<tr id="file-upload-row" style="display: none;">
				<th scope="row">
					<label><?php esc_html_e( 'Archivo', 'tutor-course-resources' ); ?></label>
				</th>
				<td>
					<input type="hidden" name="file_id" id="file_id" value="">
					<button type="button" class="button" id="select-file-btn"><?php esc_html_e( 'Seleccionar Archivo', 'tutor-course-resources' ); ?></button>
					<span id="selected-file-name"></span>
					<p class="description"><?php esc_html_e( 'Selecciona un archivo desde la biblioteca de medios o sube uno nuevo.', 'tutor-course-resources' ); ?></p>
				</td>
			</tr>
			
			<tr>
				<th scope="row">
					<label for="resource_description"><?php esc_html_e( 'Descripción', 'tutor-course-resources' ); ?></label>
				</th>
				<td>
					<textarea name="resource_description" id="resource_description" rows="5" class="large-text"></textarea>
				</td>
			</tr>
			
			<tr>
				<th scope="row"><?php esc_html_e( 'Control de Acceso', 'tutor-course-resources' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="access_students" value="1" checked>
							<?php esc_html_e( 'Permitir acceso a estudiantes inscritos en el curso', 'tutor-course-resources' ); ?>
						</label>
						<br><br>
						<label>
							<input type="checkbox" name="access_teachers" id="access-teachers" value="1">
							<?php esc_html_e( 'Permitir acceso a otros docentes', 'tutor-course-resources' ); ?>
						</label>
						<div id="teachers-list-container" style="display: none; margin-top: 10px;">
							<p class="description"><?php esc_html_e( 'Selecciona docentes específicos (dejar vacío para permitir a todos):', 'tutor-course-resources' ); ?></p>
							<?php if ( ! empty( $instructors ) ) : ?>
								<select name="access_teachers_list[]" id="access-teachers-list" multiple class="regular-text" style="min-height: 100px;">
									<?php foreach ( $instructors as $instructor ) : ?>
										<option value="<?php echo esc_attr( $instructor->ID ); ?>">
											<?php echo esc_html( $instructor->display_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Mantén presionada la tecla Ctrl (Cmd en Mac) para seleccionar múltiples docentes.', 'tutor-course-resources' ); ?></p>
							<?php endif; ?>
						</div>
					</fieldset>
				</td>
			</tr>
		</table>
		
		<p class="submit">
			<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Guardar Recurso', 'tutor-course-resources' ); ?>">
			<a href="<?php echo admin_url( 'admin.php?page=tutor-course-resources' ); ?>" class="button">
				<?php esc_html_e( 'Cancelar', 'tutor-course-resources' ); ?>
			</a>
		</p>
	</form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Mostrar/ocultar campos según el tipo de recurso
	$('#resource-type').on('change', function() {
		if ($(this).val() === 'drive') {
			$('#drive-url-row').show();
			$('#file-upload-row').hide();
		} else {
			$('#drive-url-row').hide();
			$('#file-upload-row').show();
		}
	});
	
	// Mostrar/ocultar lista de docentes
	$('#access-teachers').on('change', function() {
		if ($(this).is(':checked')) {
			$('#teachers-list-container').show();
		} else {
			$('#teachers-list-container').hide();
		}
	});
	
	// Selector de archivos
	var file_frame;
	$('#select-file-btn').on('click', function(e) {
		e.preventDefault();
		
		if (file_frame) {
			file_frame.open();
			return;
		}
		
		file_frame = wp.media({
			title: '<?php esc_attr_e( 'Seleccionar Archivo', 'tutor-course-resources' ); ?>',
			button: {
				text: '<?php esc_attr_e( 'Usar este archivo', 'tutor-course-resources' ); ?>'
			},
			multiple: false
		});
		
		file_frame.on('select', function() {
			var attachment = file_frame.state().get('selection').first().toJSON();
			$('#file_id').val(attachment.id);
			$('#selected-file-name').text(' - ' + attachment.filename);
		});
		
		file_frame.open();
	});
});
</script>

