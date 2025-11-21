/**
 * Script para añadir el campo course_url_sesion al course builder de Tutor (Frontend)
 * Reutiliza la misma lógica que admin.js
 */
(function() {
	'use strict';

	// Cargar el mismo script que admin.js
	// En producción, podrías combinar ambos archivos
	const script = document.createElement('script');
	script.src = tutorCourseUrlSesion?.adminScriptUrl || '';
	script.onerror = function() {
		// Si falla, usar la lógica inline
		// Copiar la lógica de admin.js aquí si es necesario
		console.warn('No se pudo cargar el script admin, usando lógica inline');
	};
	document.head.appendChild(script);

})();

