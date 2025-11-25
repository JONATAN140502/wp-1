/**
 * Scripts para el área de administración
 */

jQuery(document).ready(function($) {
	// Inicializar datepicker si está disponible
	if ($.fn.datepicker) {
		$('.datepicker').datepicker({
			dateFormat: 'yy-mm-dd',
			changeMonth: true,
			changeYear: true
		});
	}
});
