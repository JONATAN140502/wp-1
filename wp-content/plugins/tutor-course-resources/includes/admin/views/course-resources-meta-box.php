<?php
/**
 * Meta box para recursos del curso
 *
 * @package TutorCourseResources
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin = Tutor_Course_Resources::get_instance();
?>

<div class="tutor-course-resources-meta-box">
	<div id="course-resources-container">
		<div class="resources-list">
			<?php if ( ! empty( $resources ) ) : ?>
				<?php foreach ( $resources as $resource ) : ?>
					<div class="resource-item" data-resource-id="<?php echo esc_attr( $resource->id ); ?>">
						<div class="resource-header">
							<h4><?php echo esc_html( $resource->title ); ?></h4>
							<div class="resource-actions">
								<button type="button" class="button edit-resource" data-resource-id="<?php echo esc_attr( $resource->id ); ?>">
									<?php esc_html_e( 'Editar', 'tutor-course-resources' ); ?>
								</button>
								<button type="button" class="button delete-resource" data-resource-id="<?php echo esc_attr( $resource->id ); ?>">
									<?php esc_html_e( 'Eliminar', 'tutor-course-resources' ); ?>
								</button>
							</div>
						</div>
						<div class="resource-content">
							<p><strong><?php esc_html_e( 'Tipo:', 'tutor-course-resources' ); ?></strong> 
								<?php echo $resource->resource_type === 'drive' ? esc_html__( 'Google Drive', 'tutor-course-resources' ) : esc_html__( 'Archivo', 'tutor-course-resources' ); ?>
							</p>
							<?php if ( ! empty( $resource->description ) ) : ?>
								<p><strong><?php esc_html_e( 'Descripción:', 'tutor-course-resources' ); ?></strong> 
									<?php echo esc_html( $resource->description ); ?>
								</p>
							<?php endif; ?>
							<p><strong><?php esc_html_e( 'Acceso:', 'tutor-course-resources' ); ?></strong>
								<?php
								$access_list = array();
								if ( $resource->access_students ) {
									$access_list[] = esc_html__( 'Estudiantes', 'tutor-course-resources' );
								}
								if ( $resource->access_teachers ) {
									$access_list[] = esc_html__( 'Docentes', 'tutor-course-resources' );
								}
								echo esc_html( implode( ', ', $access_list ) );
								?>
							</p>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="no-resources"><?php esc_html_e( 'No hay recursos agregados aún.', 'tutor-course-resources' ); ?></p>
			<?php endif; ?>
		</div>
		
		<button type="button" class="button button-primary add-resource-btn" id="add-resource-btn">
			<?php esc_html_e( '+ Añadir Recurso', 'tutor-course-resources' ); ?>
		</button>
	</div>
</div>

<!-- Modal para agregar/editar recurso -->
<div id="resource-modal" class="resource-modal" style="display: none;">
	<div class="resource-modal-content">
		<div class="resource-modal-header">
			<h2 id="modal-title"><?php esc_html_e( 'Añadir Recurso', 'tutor-course-resources' ); ?></h2>
			<span class="close-modal">&times;</span>
		</div>
		<form id="resource-form">
			<input type="hidden" id="resource-id" value="">
			<input type="hidden" id="course-id" value="<?php echo esc_attr( $course_id ); ?>">
			
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="resource-title"><?php esc_html_e( 'Título', 'tutor-course-resources' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input type="text" id="resource-title" class="regular-text" required>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="resource-type"><?php esc_html_e( 'Tipo de Recurso', 'tutor-course-resources' ); ?></label>
					</th>
					<td>
						<select id="resource-type" class="regular-text">
							<option value="drive"><?php esc_html_e( 'Google Drive (Enlace)', 'tutor-course-resources' ); ?></option>
							<option value="file"><?php esc_html_e( 'Archivo Físico', 'tutor-course-resources' ); ?></option>
						</select>
					</td>
				</tr>
				
				<tr id="drive-url-row">
					<th scope="row">
						<label for="resource-url"><?php esc_html_e( 'URL de Google Drive', 'tutor-course-resources' ); ?></label>
					</th>
					<td>
						<input type="url" id="resource-url" class="regular-text" placeholder="https://drive.google.com/...">
						<p class="description"><?php esc_html_e( 'Pega aquí el enlace compartido de Google Drive.', 'tutor-course-resources' ); ?></p>
					</td>
				</tr>
				
				<tr id="file-upload-row" style="display: none;">
					<th scope="row">
						<label><?php esc_html_e( 'Archivo', 'tutor-course-resources' ); ?></label>
					</th>
					<td>
						<input type="hidden" id="resource-file-id" value="">
						<button type="button" class="button" id="select-file-btn"><?php esc_html_e( 'Seleccionar Archivo', 'tutor-course-resources' ); ?></button>
						<span id="selected-file-name"></span>
						<p class="description"><?php esc_html_e( 'Selecciona un archivo desde la biblioteca de medios o sube uno nuevo.', 'tutor-course-resources' ); ?></p>
					</td>
				</tr>
				
				<tr>
					<th scope="row">
						<label for="resource-description"><?php esc_html_e( 'Descripción', 'tutor-course-resources' ); ?></label>
					</th>
					<td>
						<textarea id="resource-description" rows="4" class="large-text"></textarea>
					</td>
				</tr>
				
				<tr>
					<th scope="row"><?php esc_html_e( 'Control de Acceso', 'tutor-course-resources' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" id="access-students" value="1" checked>
								<?php esc_html_e( 'Permitir acceso a estudiantes inscritos en el curso', 'tutor-course-resources' ); ?>
							</label>
							<br><br>
							<label>
								<input type="checkbox" id="access-teachers" value="1">
								<?php esc_html_e( 'Permitir acceso a otros docentes', 'tutor-course-resources' ); ?>
							</label>
							<div id="teachers-list-container" style="display: none; margin-top: 10px;">
								<p class="description"><?php esc_html_e( 'Selecciona docentes específicos (dejar vacío para permitir a todos):', 'tutor-course-resources' ); ?></p>
								<?php
								// Obtener todos los docentes
								$teachers = get_users( array( 'role' => 'tutor_instructor' ) );
								if ( ! empty( $teachers ) ) :
								?>
									<select id="access-teachers-list" multiple class="regular-text" style="min-height: 100px;">
										<?php foreach ( $teachers as $teacher ) : ?>
											<option value="<?php echo esc_attr( $teacher->ID ); ?>">
												<?php echo esc_html( $teacher->display_name ); ?>
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
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar Recurso', 'tutor-course-resources' ); ?></button>
				<button type="button" class="button cancel-resource"><?php esc_html_e( 'Cancelar', 'tutor-course-resources' ); ?></button>
			</p>
		</form>
	</div>
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
			$('#resource-file-id').val(attachment.id);
			$('#selected-file-name').text(attachment.filename);
		});
		
		file_frame.open();
	});
});
</script>

