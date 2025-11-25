<?php
/**
 * Vista: Configuración
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Configuración de Asistencia', 'tutor-attendance-calendar' ); ?></h1>

	<form method="POST" action="" id="attendance-settings-form">
		<?php wp_nonce_field( 'tutor_attendance_settings' ); ?>

		<!-- Estados de Asistencia -->
		<div class="settings-section" style="background: #fff; padding: 25px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); border-radius: 4px;">
			<h2 style="margin-top: 0; padding-bottom: 15px; border-bottom: 2px solid #f0f0f1;">
				<span class="dashicons dashicons-admin-settings" style="vertical-align: middle; margin-right: 8px;"></span>
				<?php esc_html_e( 'Estados de Asistencia', 'tutor-attendance-calendar' ); ?>
			</h2>
			<p class="description" style="margin-bottom: 20px; color: #646970;">
				<?php esc_html_e( 'Define los estados disponibles para marcar asistencia. Los estados se mostrarán en el mismo orden que los definas aquí.', 'tutor-attendance-calendar' ); ?>
			</p>
			
			<div id="attendance-states-container" style="margin: 20px 0;">
				<?php
				if ( ! empty( $attendance_states ) ) {
					foreach ( $attendance_states as $index => $state ) {
						?>
						<div class="attendance-state-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; padding: 12px; background: #f9f9f9; border-radius: 4px; border: 1px solid #e0e0e0; transition: all 0.2s ease;">
							<span class="dashicons dashicons-move" style="color: #787c82; cursor: move; flex-shrink: 0;" title="<?php esc_attr_e( 'Arrastrar para reordenar', 'tutor-attendance-calendar' ); ?>"></span>
							<input type="text" 
								name="attendance_states[]" 
								value="<?php echo esc_attr( $state ); ?>" 
								class="regular-text attendance-state-input" 
								placeholder="<?php esc_attr_e( 'Ej: Asistió, Falta, Tarde...', 'tutor-attendance-calendar' ); ?>"
								required
								style="flex: 1; max-width: 400px;">
							<button type="button" 
								class="button remove-state" 
								title="<?php esc_attr_e( 'Eliminar estado', 'tutor-attendance-calendar' ); ?>"
								style="color: #b32d2e; border-color: #b32d2e;">
								<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
								<?php esc_html_e( 'Eliminar', 'tutor-attendance-calendar' ); ?>
							</button>
						</div>
						<?php
					}
				} else {
					?>
					<div class="attendance-state-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; padding: 12px; background: #f9f9f9; border-radius: 4px; border: 1px solid #e0e0e0;">
						<span class="dashicons dashicons-move" style="color: #787c82; cursor: move; flex-shrink: 0;"></span>
						<input type="text" 
							name="attendance_states[]" 
							value="" 
							class="regular-text attendance-state-input" 
							placeholder="<?php esc_attr_e( 'Ej: Asistió, Falta, Tarde...', 'tutor-attendance-calendar' ); ?>"
							required
							style="flex: 1; max-width: 400px;">
						<button type="button" class="button remove-state" style="color: #b32d2e; border-color: #b32d2e;">
							<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
							<?php esc_html_e( 'Eliminar', 'tutor-attendance-calendar' ); ?>
						</button>
					</div>
					<?php
				}
				?>
			</div>
			
			<button type="button" 
				id="add-state" 
				class="button" 
				style="margin-top: 10px;">
				<span class="dashicons dashicons-plus-alt" style="vertical-align: middle; margin-right: 5px;"></span>
				<?php esc_html_e( 'Agregar Estado', 'tutor-attendance-calendar' ); ?>
			</button>
			
			<p class="description" style="margin-top: 15px; color: #646970; font-style: italic;">
				<strong><?php esc_html_e( 'Nota:', 'tutor-attendance-calendar' ); ?></strong> 
				<?php esc_html_e( 'Debe haber al menos un estado de asistencia. El primer estado será el predeterminado cuando los estudiantes marquen su asistencia.', 'tutor-attendance-calendar' ); ?>
			</p>
		</div>


		<p class="submit" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
			<button type="submit" name="save_settings" class="button button-primary button-large" style="padding: 8px 20px; font-size: 14px; height: auto;">
				<span class="dashicons dashicons-yes-alt" style="vertical-align: middle; margin-right: 5px;"></span>
				<?php esc_html_e( 'Guardar Configuración', 'tutor-attendance-calendar' ); ?>
			</button>
			<span class="save-message" style="margin-left: 15px; font-weight: 600;"></span>
		</p>
	</form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Agregar nuevo estado
	$('#add-state').on('click', function() {
		var newRow = $('<div class="attendance-state-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; padding: 12px; background: #f9f9f9; border-radius: 4px; border: 1px solid #e0e0e0; animation: slideIn 0.3s ease;">' +
			'<span class="dashicons dashicons-move" style="color: #787c82; cursor: move; flex-shrink: 0;" title="<?php esc_attr_e( 'Arrastrar para reordenar', 'tutor-attendance-calendar' ); ?>"></span>' +
			'<input type="text" name="attendance_states[]" value="" class="regular-text attendance-state-input" placeholder="<?php esc_attr_e( 'Ej: Asistió, Falta, Tarde...', 'tutor-attendance-calendar' ); ?>" required style="flex: 1; max-width: 400px;">' +
			'<button type="button" class="button remove-state" title="<?php esc_attr_e( 'Eliminar estado', 'tutor-attendance-calendar' ); ?>" style="color: #b32d2e; border-color: #b32d2e;">' +
			'<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span> <?php esc_html_e( 'Eliminar', 'tutor-attendance-calendar' ); ?>' +
			'</button></div>');
		$('#attendance-states-container').append(newRow);
		
		// Enfocar el nuevo input y hacer scroll suave
		newRow.find('input').focus();
		$('html, body').animate({
			scrollTop: newRow.offset().top - 100
		}, 300);
	});

	// Eliminar estado
	$(document).on('click', '.remove-state', function() {
		if ($('.attendance-state-row').length > 1) {
			var row = $(this).closest('.attendance-state-row');
			row.fadeOut(200, function() {
				$(this).remove();
			});
		} else {
			alert('<?php esc_attr_e( 'Debe haber al menos un estado de asistencia.', 'tutor-attendance-calendar' ); ?>');
		}
	});

	// Validación del formulario
	$('#attendance-settings-form').on('submit', function(e) {
		var hasEmpty = false;
		$('.attendance-state-input').each(function() {
			if ($(this).val().trim() === '') {
				hasEmpty = true;
				$(this).css('border-color', '#b32d2e').focus();
				return false;
			}
		});

		if (hasEmpty) {
			e.preventDefault();
			alert('<?php esc_attr_e( 'Por favor, completa todos los campos de estados de asistencia o elimina los vacíos.', 'tutor-attendance-calendar' ); ?>');
			return false;
		}

		// Limpiar estilos de validación
		$('.attendance-state-input').css('border-color', '');

		// Mostrar mensaje de guardado
		var messageSpan = $('.save-message');
		messageSpan.text('<?php esc_attr_e( 'Guardando...', 'tutor-attendance-calendar' ); ?>').css('color', '#666');
	});

	// Efecto hover en las filas
	$(document).on('mouseenter', '.attendance-state-row', function() {
		$(this).css({
			'background-color': '#f0f0f1',
			'border-color': '#c3c4c7'
		});
	}).on('mouseleave', '.attendance-state-row', function() {
		$(this).css({
			'background-color': '#f9f9f9',
			'border-color': '#e0e0e0'
		});
	});

	// Limpiar estilo de error al escribir
	$(document).on('input', '.attendance-state-input', function() {
		$(this).css('border-color', '');
	});
});

// Animación CSS
jQuery(document).ready(function($) {
	if (!$('style#attendance-settings-animations').length) {
		$('<style id="attendance-settings-animations">' +
			'@keyframes slideIn {' +
			'  from { opacity: 0; transform: translateY(-10px); }' +
			'  to { opacity: 1; transform: translateY(0); }' +
			'}' +
			'.attendance-state-row { transition: all 0.2s ease; }' +
			'</style>').appendTo('head');
	}
});
</script>
