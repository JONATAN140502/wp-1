/**
 * Script para inyectar el campo course_url_sesion directamente en el formulario React de Tutor
 * Inyecta el campo en el DOM cuando React renderiza el formulario
 */
(function() {
	'use strict';

	let fieldInjected = false;
	let reactFormContext = null;
	let currentStep = null;
	let currentCourseId = null;
	// Variable global para mantener el valor del campo entre pasos
	let courseUrlSesionValue = '';

	/**
	 * Obtener el ID del curso actual
	 */
	function getCurrentCourseId() {
		// Si ya lo tenemos, devolverlo
		if (currentCourseId) {
			return currentCourseId;
		}

		// Intentar desde tutorCourseUrlSesion
		if (typeof tutorCourseUrlSesion !== 'undefined' && tutorCourseUrlSesion.courseId) {
			currentCourseId = parseInt(tutorCourseUrlSesion.courseId) || 0;
			if (currentCourseId) {
				return currentCourseId;
			}
		}

		// Intentar desde la URL (GET parameter)
		const urlParams = new URLSearchParams(window.location.search);
		const courseIdFromUrl = urlParams.get('course_id');
		if (courseIdFromUrl) {
			currentCourseId = parseInt(courseIdFromUrl) || 0;
			if (currentCourseId) {
				return currentCourseId;
			}
		}

		// Intentar desde inputs hidden en el formulario
		const courseIdInput = document.querySelector('input[name="course_id"], input[name="course_ID"], input[name="post_ID"], input[id="course_ID"], input[id="post_ID"]');
		if (courseIdInput && courseIdInput.value) {
			currentCourseId = parseInt(courseIdInput.value) || 0;
			if (currentCourseId) {
				return currentCourseId;
			}
		}

		// Intentar desde window.tutorCourseData
		if (window.tutorCourseData && window.tutorCourseData.course_id) {
			currentCourseId = parseInt(window.tutorCourseData.course_id) || 0;
			if (currentCourseId) {
				return currentCourseId;
			}
		}

		// Si es un curso nuevo, usar 0 (se actualizará cuando se cree)
		return 0;
	}

	/**
	 * Obtener valor guardado desde los datos que vienen del servidor
	 * NO hacer peticiones AJAX - solo usar los datos que ya vienen cargados
	 * Si el usuario ha modificado el campo y lo dejó vacío, respetar ese valor vacío
	 */
	function getSavedValue() {
		// Si el usuario ha modificado el campo y lo dejó vacío, devolver vacío
		if (window._tutorCourseUrlSesionUserModified && !courseUrlSesionValue) {
			return '';
		}

		// PRIMERO: Si tenemos un valor guardado en la variable global, usarlo
		if (courseUrlSesionValue) {
			return courseUrlSesionValue;
		}

		// SEGUNDO: Intentar desde tutorCourseUrlSesion (viene del servidor/BD)
		if (typeof tutorCourseUrlSesion !== 'undefined' && tutorCourseUrlSesion.courseData) {
			const value = tutorCourseUrlSesion.courseData.course_url_sesion || '';
			if (value) {
				courseUrlSesionValue = value; // Guardar en variable global
				return value;
			}
		}

		// TERCERO: Intentar desde window.tutorCourseData (viene del servidor/BD)
		if (window.tutorCourseData && window.tutorCourseData.course_url_sesion) {
			const value = window.tutorCourseData.course_url_sesion;
			if (value) {
				courseUrlSesionValue = value; // Guardar en variable global
				return value;
			}
		}

		// NO hacer peticiones AJAX - el valor debe venir del servidor
		return '';
	}

	/**
	 * Guardar el valor del campo en la variable global
	 */
	function saveFieldValue(value) {
		courseUrlSesionValue = (value || '').trim();
		// Marcar que el usuario ha interactuado con el campo
		window._tutorCourseUrlSesionUserModified = true;
	}


	/**
	 * Verificar si estamos en el paso Basic (solo este paso debe mostrar el campo)
	 */
	function isBasicStep() {
		// Buscar indicadores de que estamos en el paso Basic
		const stepIndicator = document.querySelector('[data-step="Basic"]') ||
			document.querySelector('[class*="Basic"][class*="step"]') ||
			document.querySelector('[class*="basic"][class*="step"]') ||
			document.querySelector('[aria-label*="Basic"]') ||
			document.querySelector('[aria-label*="basic"]');

		// Verificar si hay campos típicos del paso Basic
		const hasBasicFields = document.querySelector('input[name="post_title"]') ||
			document.querySelector('input[name="tags"]') ||
			document.querySelector('textarea[name="post_content"]') ||
			document.querySelector('input[name="post_excerpt"]');

		// Verificar que NO estemos en otros pasos
		const isOtherStep = document.querySelector('[data-step="Curriculum"]') ||
			document.querySelector('[data-step="Settings"]') ||
			document.querySelector('[data-step="Quiz"]') ||
			document.querySelector('[class*="Curriculum"]') ||
			document.querySelector('[class*="Settings"]') ||
			document.querySelector('[class*="Quiz"]');

		return hasBasicFields && !isOtherStep;
	}

	/**
	 * Buscar el contexto de React Hook Form y registrar el campo
	 */
	function findAndRegisterField() {
		// SOLO inyectar en el paso Basic
		if (!isBasicStep()) {
			// Si no estamos en Basic, eliminar el campo si existe
			const existingField = document.querySelector('input[name="course_url_sesion"]');
			if (existingField) {
				const wrapper = existingField.closest('.tutor-course-url-sesion-field-wrapper');
				if (wrapper) {
					wrapper.remove();
					fieldInjected = false;
				}
			}
			return;
		}

		// Actualizar el courseId antes de verificar
		const newCourseId = getCurrentCourseId();
		if (newCourseId !== currentCourseId) {
			currentCourseId = newCourseId;
		}

		// Verificar si el campo ya existe
		const existingField = document.querySelector('input[name="course_url_sesion"]');
		if (existingField) {
			fieldInjected = true;
			// Sincronizar el valor guardado
			syncFieldValue();
			return;
		}

		// Debug: Log para ver qué está pasando
		console.log('🔍 Buscando formulario React de Tutor en paso Basic...');

		// Buscar el contenedor principal del course builder
		const courseBuilderContainer = document.querySelector('#tutor-course-builder-root') ||
			document.querySelector('[id*="course-builder"]') ||
			document.querySelector('[class*="course-builder"]') ||
			document.querySelector('main') ||
			document.body;

		// Buscar el contenedor de campos de la sección Basic
		// Buscar específicamente en el paso Basic
		const basicSection = courseBuilderContainer.querySelector('[data-section="Basic"]') || 
			courseBuilderContainer.querySelector('[class*="Basic"]') ||
			courseBuilderContainer.querySelector('[class*="basic"]') ||
			courseBuilderContainer;

		console.log('📋 Sección Basic encontrada:', basicSection ? 'Sí' : 'No');

		// Buscar el campo de tags con múltiples estrategias
		let tagsField = null;
		let tagsContainer = null;

		// Estrategia 1: Buscar por name="tags"
		tagsField = basicSection.querySelector('input[name="tags"]');
		if (tagsField) {
			console.log('✅ Campo tags encontrado');
			tagsContainer = tagsField.closest('div') || tagsField.parentElement;
			// Subir hasta encontrar el contenedor del campo completo
			while (tagsContainer && tagsContainer.parentElement) {
				const parent = tagsContainer.parentElement;
				// Buscar un contenedor que tenga otros campos similares
				if (parent.querySelectorAll('input, select, textarea').length > 1) {
					tagsContainer = parent;
					break;
				}
				tagsContainer = parent;
			}
		} else {
			console.log('⚠️ Campo tags no encontrado');
		}

		// Estrategia 2: Buscar por placeholder que contenga "tag"
		if (!tagsField) {
			const allInputs = basicSection.querySelectorAll('input[type="text"], input[type="url"]');
			for (let input of allInputs) {
				const placeholder = (input.placeholder || '').toLowerCase();
				if (placeholder.includes('tag') || placeholder.includes('etiqueta')) {
					tagsField = input;
					tagsContainer = input.closest('div') || input.parentElement;
					break;
				}
			}
		}

		// Estrategia 3: Buscar el campo post_author y insertar antes
		if (!tagsField) {
			const authorField = basicSection.querySelector('input[name="post_author"]') || 
				basicSection.querySelector('select[name="post_author"]') ||
				basicSection.querySelector('[name="post_author"]');
			
			if (authorField) {
				console.log('✅ Campo post_author encontrado, insertando antes');
				let authorContainer = authorField.closest('div') || authorField.parentElement;
				// Subir hasta encontrar el contenedor del campo completo
				while (authorContainer && authorContainer.parentElement) {
					const parent = authorContainer.parentElement;
					if (parent.querySelectorAll('input, select, textarea').length > 1) {
						authorContainer = parent;
						break;
					}
					authorContainer = parent;
				}
				injectFieldBefore(authorContainer);
				return;
			} else {
				console.log('⚠️ Campo post_author no encontrado');
			}
		}

		// Si encontramos tags, insertar después
		if (tagsField && tagsContainer && !fieldInjected) {
			// Buscar el siguiente hermano que sea un campo
			let nextSibling = tagsContainer.nextElementSibling;
			while (nextSibling && !nextSibling.querySelector('input, select, textarea')) {
				nextSibling = nextSibling.nextElementSibling;
			}
			
			if (nextSibling) {
				injectFieldBefore(nextSibling);
			} else {
				// Si no hay siguiente hermano, insertar después del contenedor de tags
				injectFieldAfter(tagsContainer);
			}
			return;
		}

		// Estrategia 4: Buscar cualquier input/select/textarea y encontrar su contenedor padre
		if (!fieldInjected) {
			const allInputs = basicSection.querySelectorAll('input:not([name="course_url_sesion"]), select:not([name="course_url_sesion"]), textarea:not([name="course_url_sesion"])');
			if (allInputs.length > 0) {
				// Buscar el último input que no sea nuestro campo
				const lastInput = allInputs[allInputs.length - 1];
				// Encontrar su contenedor padre (normalmente un div que contiene el campo completo)
				let container = lastInput.closest('div');
				// Subir hasta encontrar un contenedor que tenga otros campos
				while (container && container.parentElement) {
					const siblings = Array.from(container.parentElement.children);
					const hasOtherFields = siblings.some(sibling => 
						sibling !== container && 
						sibling.querySelector('input, select, textarea') &&
						!sibling.querySelector('input[name="course_url_sesion"]')
					);
					if (hasOtherFields) {
						break;
					}
					container = container.parentElement;
				}
				if (container) {
					injectFieldAfter(container);
					return;
				}
			}
		}

		// Estrategia 5: Buscar el contenedor fieldsWrapper y añadir al final
		if (!fieldInjected) {
			const fieldsWrapper = basicSection.querySelector('[class*="fieldsWrapper"]') ||
				basicSection.querySelector('[class*="fields-wrapper"]') ||
				basicSection.querySelector('[class*="form-group"]');
			
			if (fieldsWrapper && fieldsWrapper.lastElementChild) {
				injectFieldAfter(fieldsWrapper.lastElementChild);
				return;
			}
		}

		// Estrategia 6: Buscar cualquier div que contenga inputs y añadir después del último
		if (!fieldInjected) {
			// Buscar todos los divs que contengan inputs
			const allDivs = Array.from(basicSection.querySelectorAll('div'));
			const divsWithInputs = allDivs.filter(div => {
				const hasInput = div.querySelector('input, select, textarea');
				const isNotOurField = !div.querySelector('input[name="course_url_sesion"]');
				const hasParent = div.parentElement;
				return hasInput && isNotOurField && hasParent;
			});
			
			if (divsWithInputs.length > 0) {
				// Tomar el último div que tenga inputs
				const lastDiv = divsWithInputs[divsWithInputs.length - 1];
				console.log('✅ Usando estrategia 6: Insertando después del último div con inputs');
				injectFieldAfter(lastDiv);
			} else {
				console.log('⚠️ No se encontraron divs con inputs para insertar el campo');
			}
		}
	}

	/**
	 * Inyectar el campo después de un elemento
	 */
	function injectFieldAfter(afterElement) {
		if (!afterElement || fieldInjected) return;

		// Verificar que el elemento tenga un parentNode
		if (!afterElement.parentNode) {
			console.warn('⚠️ No se puede insertar el campo: el elemento no tiene parentNode');
			return;
		}

		const fieldWrapper = document.createElement('div');
		fieldWrapper.className = 'tutor-course-url-sesion-field-wrapper';
		fieldWrapper.setAttribute('data-field-injected', 'true');
		fieldWrapper.style.cssText = 'margin: 20px 0; width: 100%;';
		
		// Obtener el valor guardado desde BD
		const savedValue = getSavedValue();
		const escapedValue = (savedValue || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

		fieldWrapper.innerHTML = `
			<div class="tutor-form-group" style="display: flex; flex-direction: column; gap: 8px; width: 100%;">
				<label for="course_url_sesion" style="font-weight: 500; color: #1e1e1e; font-size: 14px; margin-bottom: 4px;">
					${tutorCourseUrlSesion?.labels?.fieldLabel || 'URL de Clases (Meet/Zoom)'}
				</label>
				<input 
					type="url" 
					id="course_url_sesion" 
					name="course_url_sesion" 
					class="tutor-form-control tutor-course-url-sesion-input" 
					style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box; background-color: #fff;"
					placeholder="${tutorCourseUrlSesion?.labels?.placeholder || 'https://meet.google.com/... o https://zoom.us/j/...'}"
					value="${escapedValue}"
					autocomplete="url"
				/>
				<small class="tutor-form-help" style="display: block; margin-top: 4px; color: #666; font-size: 12px;">
					${tutorCourseUrlSesion?.labels?.helpText || 'Ingresa el enlace de la clase en vivo (Google Meet o Zoom)'}
				</small>
			</div>
		`;

		// Insertar después del elemento
		try {
			if (afterElement.nextSibling) {
				afterElement.parentNode.insertBefore(fieldWrapper, afterElement.nextSibling);
			} else {
				afterElement.parentNode.appendChild(fieldWrapper);
			}

			fieldInjected = true;
			console.log('✅ Campo course_url_sesion inyectado en el formulario después de:', afterElement);
			
			// Agregar listener para guardar el valor cuando cambie
			const input = fieldWrapper.querySelector('input[name="course_url_sesion"]');
			if (input) {
				// Restaurar el valor guardado SOLO si:
				// 1. Existe un valor guardado
				// 2. El input está vacío
				// 3. El usuario NO ha modificado el campo intencionalmente (no lo borró)
				if (courseUrlSesionValue && !input.value && !window._tutorCourseUrlSesionUserModified) {
					input.value = courseUrlSesionValue;
				}
				
				// Asegurar que el campo sea completamente editable
				input.removeAttribute('readonly');
				input.removeAttribute('disabled');
				input.readOnly = false;
				input.disabled = false;
				input.style.pointerEvents = 'auto';
				input.style.cursor = 'text';
				input.style.backgroundColor = '#fff';
				
				// Forzar que sea editable usando setAttribute
				input.setAttribute('contenteditable', 'false'); // No usar contenteditable, usar input normal
				
				// Asegurar que no tenga estilos que lo bloqueen
				const computedStyle = window.getComputedStyle(input);
				if (computedStyle.pointerEvents === 'none') {
					input.style.pointerEvents = 'auto';
				}
				
				// Guardar el valor cuando el usuario escribe (mantiene el valor entre pasos)
				input.addEventListener('input', function(e) {
					saveFieldValue(e.target.value); // Esto marca _tutorCourseUrlSesionUserModified = true
				});
				
				input.addEventListener('change', function(e) {
					saveFieldValue(e.target.value);
				});
				
				input.addEventListener('blur', function(e) {
					saveFieldValue(e.target.value);
				});
				
				// Log para debug
				console.log('✅ Campo course_url_sesion creado y editable. Course ID:', getCurrentCourseId(), 'Valor:', courseUrlSesionValue || '(vacío)');
			}
			
			// Conectar con React Hook Form si está disponible
			connectToReactHookForm();
		} catch(e) {
			console.error('❌ Error al insertar el campo:', e);
		}
	}

	/**
	 * Inyectar el campo antes de un elemento
	 */
	function injectFieldBefore(beforeElement) {
		if (!beforeElement || fieldInjected) return;

		// Verificar que el elemento tenga un parentNode
		if (!beforeElement.parentNode) {
			console.warn('⚠️ No se puede insertar el campo: el elemento no tiene parentNode');
			return;
		}

		const fieldWrapper = document.createElement('div');
		fieldWrapper.className = 'tutor-course-url-sesion-field-wrapper';
		fieldWrapper.setAttribute('data-field-injected', 'true');
		fieldWrapper.style.cssText = 'margin: 20px 0; width: 100%;';
		
		// Obtener el valor guardado desde BD
		const savedValue = getSavedValue();
		const escapedValue = (savedValue || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

		fieldWrapper.innerHTML = `
			<div class="tutor-form-group" style="display: flex; flex-direction: column; gap: 8px; width: 100%;">
				<label for="course_url_sesion" style="font-weight: 500; color: #1e1e1e; font-size: 14px; margin-bottom: 4px;">
					${tutorCourseUrlSesion?.labels?.fieldLabel || 'URL de Clases (Meet/Zoom)'}
				</label>
				<input 
					type="url" 
					id="course_url_sesion" 
					name="course_url_sesion" 
					class="tutor-form-control tutor-course-url-sesion-input" 
					style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; box-sizing: border-box; background-color: #fff;"
					placeholder="${tutorCourseUrlSesion?.labels?.placeholder || 'https://meet.google.com/... o https://zoom.us/j/...'}"
					value="${escapedValue}"
					autocomplete="url"
				/>
				<small class="tutor-form-help" style="display: block; margin-top: 4px; color: #666; font-size: 12px;">
					${tutorCourseUrlSesion?.labels?.helpText || 'Ingresa el enlace de la clase en vivo (Google Meet o Zoom)'}
				</small>
			</div>
		`;

		// Insertar antes del elemento
		try {
			beforeElement.parentNode.insertBefore(fieldWrapper, beforeElement);

			fieldInjected = true;
			console.log('✅ Campo course_url_sesion inyectado en el formulario antes de:', beforeElement);
			
			// Agregar listener para guardar el valor cuando cambie
			const input = fieldWrapper.querySelector('input[name="course_url_sesion"]');
			if (input) {
				// Restaurar el valor guardado SOLO si:
				// 1. Existe un valor guardado
				// 2. El input está vacío
				// 3. El usuario NO ha modificado el campo intencionalmente (no lo borró)
				if (courseUrlSesionValue && !input.value && !window._tutorCourseUrlSesionUserModified) {
					input.value = courseUrlSesionValue;
				}
				
				// Asegurar que el campo sea completamente editable
				input.removeAttribute('readonly');
				input.removeAttribute('disabled');
				input.readOnly = false;
				input.disabled = false;
				input.style.pointerEvents = 'auto';
				input.style.cursor = 'text';
				input.style.backgroundColor = '#fff';
				
				// Forzar que sea editable usando setAttribute
				input.setAttribute('contenteditable', 'false'); // No usar contenteditable, usar input normal
				
				// Asegurar que no tenga estilos que lo bloqueen
				const computedStyle = window.getComputedStyle(input);
				if (computedStyle.pointerEvents === 'none') {
					input.style.pointerEvents = 'auto';
				}
				
				// Guardar el valor cuando el usuario escribe (mantiene el valor entre pasos)
				input.addEventListener('input', function(e) {
					saveFieldValue(e.target.value); // Esto marca _tutorCourseUrlSesionUserModified = true
				});
				
				input.addEventListener('change', function(e) {
					saveFieldValue(e.target.value);
				});
				
				input.addEventListener('blur', function(e) {
					saveFieldValue(e.target.value);
				});
				
				// Log para debug
				console.log('✅ Campo course_url_sesion creado y editable. Course ID:', getCurrentCourseId(), 'Valor:', courseUrlSesionValue || '(vacío)');
			}
			
			// Conectar con React Hook Form si está disponible
			connectToReactHookForm();
		} catch(e) {
			console.error('❌ Error al insertar el campo:', e);
		}
	}

	/**
	 * Intentar conectar el campo con React Hook Form
	 */
	function connectToReactHookForm() {
		const input = document.querySelector('input[name="course_url_sesion"]');
		if (!input) return;

		// Intentar encontrar el contexto de React Hook Form en el objeto window
		// Tutor puede exponer el form context globalmente
		if (window.tutorFormContext) {
			reactFormContext = window.tutorFormContext;
			registerFieldWithReactHookForm(input);
		}

		// También intentar mediante eventos personalizados
		input.addEventListener('change', function(e) {
			// Disparar evento personalizado para que React lo capture
			const event = new CustomEvent('tutorCourseUrlSesionChange', {
				detail: { value: e.target.value }
			});
			document.dispatchEvent(event);
		});
	}

	/**
	 * Registrar el campo con React Hook Form
	 */
	function registerFieldWithReactHookForm(input) {
		if (!reactFormContext) return;

		try {
			// Intentar registrar el campo
			if (reactFormContext.register) {
				reactFormContext.register('course_url_sesion', {
					required: false
				});
			}
		} catch (e) {
			console.warn('No se pudo registrar el campo con React Hook Form:', e);
		}
	}

	/**
	 * Sincronizar el valor del campo con el valor guardado en BD
	 * Solo actualiza si el input está vacío Y el usuario NO ha modificado el campo intencionalmente
	 */
	function syncFieldValue() {
		// Actualizar el courseId por si cambió
		const newCourseId = getCurrentCourseId();
		if (newCourseId !== currentCourseId) {
			currentCourseId = newCourseId;
		}
		
		const input = document.querySelector('input[name="course_url_sesion"]');
		if (input) {
			// Solo actualizar si:
			// 1. El input está vacío
			// 2. El usuario NO está escribiendo
			// 3. El usuario NO ha modificado el campo intencionalmente (no lo borró)
			if (input.value === '' && 
				document.activeElement !== input && 
				!window._tutorCourseUrlSesionUserModified) {
				const currentValue = getSavedValue(); // Obtiene desde BD
				if (currentValue) {
					input.value = currentValue;
					courseUrlSesionValue = currentValue; // Actualizar variable global
				}
			}
			// Asegurar que el campo sea completamente editable
			input.removeAttribute('readonly');
			input.removeAttribute('disabled');
			input.readOnly = false;
			input.disabled = false;
			input.style.pointerEvents = 'auto';
			input.style.cursor = 'text';
			input.style.backgroundColor = '#fff';
			
			// Verificar que no esté bloqueado por CSS
			const computedStyle = window.getComputedStyle(input);
			if (computedStyle.pointerEvents === 'none') {
				input.style.pointerEvents = 'auto';
			}
		}
	}

	/**
	 * Obtener el valor del campo course_url_sesion desde el input o variable global
	 * Mantiene el valor entre pasos del formulario
	 */
	function getCourseUrlSesionValue() {
		// PRIMERO: Buscar el input directamente por name (valor actual del usuario)
		let urlInput = document.querySelector('input[name="course_url_sesion"]');
		if (urlInput && urlInput.value) {
			const value = (urlInput.value || '').trim();
			saveFieldValue(value); // Guardar en variable global
			return value;
		}
		
		// SEGUNDO: Buscar por tipo URL
		urlInput = document.querySelector('input[type="url"][name*="course_url"]');
		if (urlInput && urlInput.value) {
			const value = (urlInput.value || '').trim();
			saveFieldValue(value); // Guardar en variable global
			return value;
		}
		
		// TERCERO: Usar el valor guardado en la variable global (mantiene el valor entre pasos)
		if (courseUrlSesionValue) {
			return courseUrlSesionValue;
		}
		
		// CUARTO: Obtener desde la BD
		const savedValue = getSavedValue();
		if (savedValue) {
			saveFieldValue(savedValue); // Guardar en variable global
		}
		return savedValue;
	}

	/**
	 * Interceptar React Hook Form getValues para asegurar que course_url_sesion esté incluido
	 */
	function interceptReactHookForm() {
		// Buscar el contexto de React Hook Form
		if (typeof window.React === 'undefined' || !window.React) {
			return;
		}

		// Interceptar getValues si está disponible
		const originalGetValues = window.React?.useFormContext?.getValues;
		if (originalGetValues) {
			window.React.useFormContext.getValues = function(...args) {
				const values = originalGetValues.apply(this, args);
				const urlValue = getCourseUrlSesionValue();
				
				// Asegurar que course_url_sesion esté incluido
				if (!values.hasOwnProperty('course_url_sesion')) {
					values.course_url_sesion = urlValue || '';
				}
				
				return values;
			};
		}
	}

	/**
	 * Interceptar fetch calls para asegurar que course_url_sesion esté incluido
	 * Solo cuando se guarda el curso (no en cada petición)
	 */
	function interceptFetch() {
		const originalFetch = window.fetch;
		
		window.fetch = function(...args) {
			const [url, options = {}] = args;
			
			// Detectar si es una petición de guardado de curso
			const urlStr = String(url);
			const isCourseSave = (urlStr.includes('admin-ajax.php') || 
				urlStr.includes('tutor/v1/courses') ||
				urlStr.includes('tutor_update_course') || 
				urlStr.includes('tutor_create_course') ||
				urlStr.includes('tutor_add_course_builder')) &&
				(options.method === 'POST' || options.method === 'PUT' || !options.method);

			if (isCourseSave) {
				// Obtener el valor: primero del input si existe, sino de la variable global (mantiene valor entre pasos)
				const input = document.querySelector('input[name="course_url_sesion"]');
				let urlValue = '';
				if (input && input.value) {
					urlValue = (input.value || '').trim();
					saveFieldValue(urlValue); // Guardar en variable global
				} else {
					// Si no hay input visible (estamos en otro paso), usar el valor guardado
					urlValue = getCourseUrlSesionValue();
				}
				
				console.log('🔄 [FETCH] Interceptando guardado de curso - URL:', urlStr, 'Method:', options.method, 'Valor del campo:', urlValue || '(vacío)');
				console.log('🔄 [FETCH] Body type:', options.body ? options.body.constructor.name : 'null');
				
				// SIEMPRE agregar el campo, incluso si está vacío (para que se elimine de BD)
				if (options.body instanceof FormData) {
					// Agregar directamente (vacío si no hay valor)
					options.body.set('course_url_sesion', urlValue);
					
					// Agregar en additional_content también
					options.body.set('additional_content[course_url_sesion]', urlValue);
					
					console.log('✅ [FETCH] FormData modificado - course_url_sesion añadido:', urlValue || '(vacío)');
					
					// Debug: Verificar que se añadió correctamente y mostrar todas las claves
					if (options.body.has('course_url_sesion')) {
						console.log('✅ [FETCH] Verificado: course_url_sesion está en FormData');
						// Mostrar todas las claves del FormData para debug
						const keys = [];
						for (let key of options.body.keys()) {
							keys.push(key);
						}
						console.log('📋 [FETCH] Claves en FormData:', keys);
					} else {
						console.error('❌ [FETCH] ERROR: course_url_sesion NO está en FormData después de añadirlo');
					}
				} else if (typeof options.body === 'string') {
					// Es un string (probablemente JSON o URL-encoded)
					try {
						// Intentar parsear como JSON
						const bodyData = JSON.parse(options.body);
						bodyData.course_url_sesion = urlValue; // Puede estar vacío
						if (!bodyData.additional_content) {
							bodyData.additional_content = {};
						}
						bodyData.additional_content.course_url_sesion = urlValue; // Puede estar vacío
						options.body = JSON.stringify(bodyData);
					} catch(e) {
						// No es JSON, probablemente es URL-encoded
						if (!options.body.includes('course_url_sesion=')) {
							options.body += '&course_url_sesion=' + encodeURIComponent(urlValue);
						} else {
							options.body = options.body.replace(/course_url_sesion=[^&]*/, 'course_url_sesion=' + encodeURIComponent(urlValue));
						}
						if (!options.body.includes('additional_content%5Bcourse_url_sesion%5D=')) {
							options.body += '&additional_content%5Bcourse_url_sesion%5D=' + encodeURIComponent(urlValue);
						} else {
							options.body = options.body.replace(/additional_content%5Bcourse_url_sesion%5D=[^&]*/, 'additional_content%5Bcourse_url_sesion%5D=' + encodeURIComponent(urlValue));
						}
					}
				}
			}
			
			return originalFetch.apply(this, args);
		};
	}

	/**
	 * Interceptar XMLHttpRequest para asegurar que course_url_sesion esté incluido
	 * Solo cuando se guarda el curso (no en cada petición)
	 */
	function interceptXMLHttpRequest() {
		const originalOpen = XMLHttpRequest.prototype.open;
		const originalSend = XMLHttpRequest.prototype.send;
		
		XMLHttpRequest.prototype.open = function(method, url, ...args) {
			this._method = method;
			this._url = String(url);
			return originalOpen.apply(this, [method, url, ...args]);
		};
		
		XMLHttpRequest.prototype.send = function(data) {
			// Detectar si es una petición de guardado de curso
			const urlStr = this._url || '';
			const method = this._method || '';
			const isCourseSave = (urlStr.includes('admin-ajax.php') || 
				urlStr.includes('tutor/v1/courses') ||
				urlStr.includes('tutor_update_course') ||
				urlStr.includes('tutor_create_course') ||
				urlStr.includes('tutor_add_course_builder') ||
				urlStr.includes('action=tutor_update_course') ||
				urlStr.includes('action=tutor_create_course') ||
				urlStr.includes('tutor_action=tutor_add_course_builder')) &&
				(method === 'POST' || method === 'PUT' || !method);
			
			if (isCourseSave) {
				// Obtener el valor: primero del input si existe, sino de la variable global (mantiene valor entre pasos)
				const input = document.querySelector('input[name="course_url_sesion"]');
				let urlValue = '';
				if (input && input.value) {
					urlValue = (input.value || '').trim();
					saveFieldValue(urlValue); // Guardar en variable global
				} else {
					// Si no hay input visible (estamos en otro paso), usar el valor guardado
					urlValue = getCourseUrlSesionValue();
				}
				
				console.log('🔄 [XHR] Interceptando guardado de curso - URL:', urlStr, 'Method:', method, 'Valor del campo:', urlValue || '(vacío)');
				console.log('🔄 [XHR] Data type:', data ? data.constructor.name : 'null');
				
				if (data instanceof FormData) {
					// Añadir directamente al FormData (vacío si no hay valor)
					data.set('course_url_sesion', urlValue);
					data.set('additional_content[course_url_sesion]', urlValue);
					
					console.log('✅ [XHR] FormData modificado - course_url_sesion añadido:', urlValue || '(vacío)');
					
					// Debug: Verificar que se añadió correctamente y mostrar todas las claves
					if (data.has('course_url_sesion')) {
						console.log('✅ [XHR] Verificado: course_url_sesion está en FormData');
						// Mostrar todas las claves del FormData para debug
						const keys = [];
						for (let key of data.keys()) {
							keys.push(key);
						}
						console.log('📋 [XHR] Claves en FormData:', keys);
					} else {
						console.error('❌ [XHR] ERROR: course_url_sesion NO está en FormData después de añadirlo');
					}
				} else if (typeof data === 'string') {
					// Es un string (probablemente URL-encoded)
					if (data.includes('action=tutor_update_course') || 
						data.includes('action=tutor_create_course') ||
						data.includes('tutor_action=tutor_add_course_builder')) {
						// Añadir los parámetros si no existen (vacío si no hay valor)
						if (!data.includes('course_url_sesion=')) {
							data += '&course_url_sesion=' + encodeURIComponent(urlValue);
						} else {
							data = data.replace(/course_url_sesion=[^&]*/, 'course_url_sesion=' + encodeURIComponent(urlValue));
						}
						
						if (!data.includes('additional_content%5Bcourse_url_sesion%5D=')) {
							data += '&additional_content%5Bcourse_url_sesion%5D=' + encodeURIComponent(urlValue);
						} else {
							data = data.replace(/additional_content%5Bcourse_url_sesion%5D=[^&]*/, 'additional_content%5Bcourse_url_sesion%5D=' + encodeURIComponent(urlValue));
						}
						
						console.log('✅ [XHR] String modificado - course_url_sesion añadido:', urlValue || '(vacío)');
					}
				}
			}
			
			return originalSend.apply(this, arguments);
		};
	}

	/**
	 * Inyectar el campo en el DOM cuando el course builder esté listo
	 * Este método intenta múltiples estrategias para añadir el campo
	 */
	function injectFieldIntoDOM() {
		// Esperar a que el DOM esté listo
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', injectFieldIntoDOM);
			return;
		}

		// Verificar si el campo ya existe
		if (document.querySelector('input[name="course_url_sesion"]')) {
			return;
		}

		// Estrategia 1: Buscar el contenedor del formulario de campos adicionales
		const additionalSection = document.querySelector('[data-section="additional"]') || 
			document.querySelector('.tutor-course-builder-additional') ||
			document.querySelector('[class*="Additional"]') ||
			document.querySelector('[class*="additional"]');

		if (additionalSection) {
			// Crear el campo
			const fieldWrapper = document.createElement('div');
			fieldWrapper.className = 'tutor-course-url-sesion-field';
			fieldWrapper.setAttribute('data-field-name', 'course_url_sesion');
			fieldWrapper.innerHTML = `
				<div class="tutor-form-group" style="margin-bottom: 20px;">
					<label for="course_url_sesion" style="display: block; margin-bottom: 8px; font-weight: 500;">
						${tutorCourseUrlSesion?.labels?.fieldLabel || 'URL de Clases (Meet/Zoom)'}
					</label>
					<input 
						type="url" 
						id="course_url_sesion" 
						name="course_url_sesion" 
						class="tutor-form-control" 
						style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
						placeholder="${tutorCourseUrlSesion?.labels?.placeholder || 'https://meet.google.com/... o https://zoom.us/j/...'}"
						value=""
					/>
					<small class="tutor-form-help" style="display: block; margin-top: 4px; color: #666; font-size: 12px;">
						${tutorCourseUrlSesion?.labels?.helpText || 'Ingresa el enlace de la clase en vivo (Google Meet o Zoom)'}
					</small>
				</div>
			`;

			// Insertar el campo en la sección adicional
			additionalSection.appendChild(fieldWrapper);
			return;
		}

		// Estrategia 2: Buscar cualquier contenedor de formulario y añadir al final
		const formContainers = document.querySelectorAll('form, [class*="form"], [class*="Form"]');
		if (formContainers.length > 0) {
			const lastContainer = formContainers[formContainers.length - 1];
			const fieldWrapper = document.createElement('div');
			fieldWrapper.className = 'tutor-course-url-sesion-field';
			fieldWrapper.setAttribute('data-field-name', 'course_url_sesion');
			fieldWrapper.innerHTML = `
				<div class="tutor-form-group" style="margin: 20px 0;">
					<label for="course_url_sesion" style="display: block; margin-bottom: 8px; font-weight: 500;">
						${tutorCourseUrlSesion?.labels?.fieldLabel || 'URL de Clases (Meet/Zoom)'}
					</label>
					<input 
						type="url" 
						id="course_url_sesion" 
						name="course_url_sesion" 
						class="tutor-form-control" 
						style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
						placeholder="${tutorCourseUrlSesion?.labels?.placeholder || 'https://meet.google.com/... o https://zoom.us/j/...'}"
						value=""
					/>
					<small class="tutor-form-help" style="display: block; margin-top: 4px; color: #666; font-size: 12px;">
						${tutorCourseUrlSesion?.labels?.helpText || 'Ingresa el enlace de la clase en vivo (Google Meet o Zoom)'}
					</small>
				</div>
			`;
			lastContainer.appendChild(fieldWrapper);
			return;
		}

		// Si no se encontró ningún contenedor, intentar de nuevo después de un delay
		setTimeout(injectFieldIntoDOM, 1000);
	}

	/**
	 * Interceptar la creación de FormData para añadir nuestro campo automáticamente
	 */
	function interceptFormData() {
		const originalFormData = window.FormData;
		
		window.FormData = function(form) {
			const formData = new originalFormData(form);
			
			// Si se crea desde un formulario, asegurar que nuestro campo esté en el formulario
			if (form && form.nodeName === 'FORM') {
				const urlInput = form.querySelector('input[name="course_url_sesion"]');
				if (!urlInput) {
					// Buscar el input en el DOM (puede estar fuera del form)
					const globalInput = document.querySelector('input[name="course_url_sesion"]');
					if (globalInput) {
						const urlValue = (globalInput.value || '').trim();
						formData.append('course_url_sesion', urlValue);
						formData.append('additional_content[course_url_sesion]', urlValue);
						console.log('✅ [FormData] course_url_sesion añadido desde formulario:', urlValue || '(vacío)');
					}
				}
			}
			
			// Interceptar cuando se añaden campos al FormData
			const originalAppend = formData.append.bind(formData);
			const originalSet = formData.set.bind(formData);
			
			// Flag para evitar añadir múltiples veces en la misma instancia
			let urlFieldAdded = false;
			
			formData.append = function(name, value, filename) {
				// Llamar primero al append original con los parámetros correctos
				// Si filename está definido, pasarlo; si no, no pasarlo
				let result;
				if (filename !== undefined) {
					result = originalAppend(name, value, filename);
				} else {
					result = originalAppend(name, value);
				}
				
				// Si se está añadiendo un campo relacionado con cursos (y no es un Blob), asegurar que course_url_sesion esté incluido
				if (name && 
					typeof name === 'string' && 
					(name.includes('course') || name.includes('action') || name.includes('tutor')) && 
					!(value instanceof Blob) && 
					!(value instanceof File) &&
					typeof value !== 'object' &&
					!urlFieldAdded) {
					// Obtener el valor: primero del input si existe, sino de la variable global
					const input = document.querySelector('input[name="course_url_sesion"]');
					let urlValue = '';
					if (input && input.value) {
						urlValue = (input.value || '').trim();
						saveFieldValue(urlValue);
					} else {
						urlValue = getCourseUrlSesionValue();
					}
					
					if (urlValue !== undefined) {
						// Usar set en lugar de append para sobrescribir si ya existe
						originalSet('course_url_sesion', urlValue);
						originalSet('additional_content[course_url_sesion]', urlValue);
						urlFieldAdded = true;
						console.log('✅ [FormData.append] course_url_sesion añadido automáticamente:', urlValue || '(vacío)');
					}
				}
				return result;
			};
			
			formData.set = function(name, value, filename) {
				// Llamar primero al set original con los parámetros correctos
				// Si filename está definido, pasarlo; si no, no pasarlo
				let result;
				if (filename !== undefined) {
					result = originalSet(name, value, filename);
				} else {
					result = originalSet(name, value);
				}
				
				// Si se está estableciendo un campo relacionado con cursos (y no es un Blob), asegurar que course_url_sesion esté incluido
				if (name && 
					typeof name === 'string' && 
					(name.includes('course') || name.includes('action') || name.includes('tutor')) && 
					!(value instanceof Blob) && 
					!(value instanceof File) &&
					typeof value !== 'object' &&
					!urlFieldAdded) {
					// Obtener el valor: primero del input si existe, sino de la variable global
					const input = document.querySelector('input[name="course_url_sesion"]');
					let urlValue = '';
					if (input && input.value) {
						urlValue = (input.value || '').trim();
						saveFieldValue(urlValue);
					} else {
						urlValue = getCourseUrlSesionValue();
					}
					
					if (urlValue !== undefined) {
						originalSet('course_url_sesion', urlValue);
						originalSet('additional_content[course_url_sesion]', urlValue);
						urlFieldAdded = true;
						console.log('✅ [FormData.set] course_url_sesion añadido automáticamente:', urlValue || '(vacío)');
					}
				}
				return result;
			};
			
			return formData;
		};
		
		// Copiar propiedades estáticas si existen
		Object.setPrototypeOf(window.FormData, originalFormData);
		Object.getOwnPropertyNames(originalFormData).forEach(prop => {
			if (prop !== 'prototype' && prop !== 'length' && prop !== 'name') {
				try {
					window.FormData[prop] = originalFormData[prop];
				} catch (e) {
					// Ignorar errores al copiar propiedades
				}
			}
		});
	}

	/**
	 * Inicializar todo cuando el DOM esté listo
	 */
	function init() {
		// Interceptar FormData primero (más temprano)
		interceptFormData();
		
		// Interceptar fetch y XMLHttpRequest para asegurar que el campo se envíe
		interceptFetch();
		interceptXMLHttpRequest();
		
		// Intentar inyectar el campo inmediatamente
		findAndRegisterField();
		
		// Observar cambios en el DOM para cuando React renderice el formulario
		const observer = new MutationObserver(function(mutations) {
			// Verificar si cambió el course_id
			const newCourseId = getCurrentCourseId();
			if (newCourseId !== currentCourseId) {
				if (newCourseId > 0 && currentCourseId > 0 && newCourseId !== currentCourseId) {
					// Cambió de curso, resetear el valor guardado y el flag de modificación
					currentCourseId = newCourseId;
					courseUrlSesionValue = ''; // Resetear el valor al cambiar de curso
					window._tutorCourseUrlSesionUserModified = false; // Resetear flag de modificación
					fieldInjected = false; // Resetear para que se vuelva a inyectar con el nuevo curso
					
					// Eliminar el campo anterior si existe
					const oldField = document.querySelector('input[name="course_url_sesion"]');
					if (oldField) {
						const wrapper = oldField.closest('.tutor-course-url-sesion-field-wrapper');
						if (wrapper) {
							wrapper.remove();
						}
					}
				} else if (newCourseId > 0) {
					// Primera vez que detectamos un course_id válido
					currentCourseId = newCourseId;
					// Cargar el valor desde la BD para este curso
					courseUrlSesionValue = getSavedValue();
					window._tutorCourseUrlSesionUserModified = false; // Resetear flag de modificación
				}
			}

			// Verificar si cambió el paso
			const nowInBasic = isBasicStep();
			if (currentStep !== nowInBasic) {
				currentStep = nowInBasic;
				fieldInjected = false; // Resetear para que se vuelva a inyectar si estamos en Basic
			}

			// Solo inyectar si estamos en Basic y no está inyectado
			if (!nowInBasic || fieldInjected) {
				return;
			}

			mutations.forEach(function(mutation) {
				if (mutation.addedNodes.length) {
					// Verificar si se añadió algún input, select, textarea o contenedor de formulario
					const hasFormElements = Array.from(mutation.addedNodes).some(node => {
						if (node.nodeType === 1) { // Element node
							// Verificar si es un input, select o textarea
							if (node.tagName === 'INPUT' || node.tagName === 'SELECT' || node.tagName === 'TEXTAREA') {
								return node.name !== 'course_url_sesion';
							}
							// Verificar si contiene inputs
							if (node.querySelector && node.querySelector('input, select, textarea')) {
								return !node.querySelector('input[name="course_url_sesion"]');
							}
						}
						return false;
					});
					
					if (hasFormElements) {
						// Esperar un poco para que React termine de renderizar
						setTimeout(() => {
							if (!fieldInjected && isBasicStep()) {
								findAndRegisterField();
							}
						}, 500);
					}
				}
			});
		});
		
		observer.observe(document.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: ['value', 'data-course-id']
		});

		// Observer adicional para asegurar que el campo sea siempre editable
		const editableObserver = new MutationObserver(function() {
			const input = document.querySelector('input[name="course_url_sesion"]');
			if (input) {
				// Forzar que sea editable cada vez que se detecte
				input.removeAttribute('readonly');
				input.removeAttribute('disabled');
				input.readOnly = false;
				input.disabled = false;
				input.style.pointerEvents = 'auto';
				input.style.cursor = 'text';
				input.style.backgroundColor = '#fff';
				input.style.opacity = '1';
				
				// Verificar estilos computados
				const computedStyle = window.getComputedStyle(input);
				if (computedStyle.pointerEvents === 'none') {
					input.style.setProperty('pointer-events', 'auto', 'important');
				}
				if (computedStyle.opacity === '0' || computedStyle.opacity === '0.5') {
					input.style.setProperty('opacity', '1', 'important');
				}
			}
		});

		editableObserver.observe(document.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: ['readonly', 'disabled', 'style', 'class']
		});

		// Verificación periódica para asegurar que el campo sea editable y tenga el valor correcto
		setInterval(function() {
			const input = document.querySelector('input[name="course_url_sesion"]');
			if (input) {
				// Asegurar que sea editable
				input.removeAttribute('readonly');
				input.removeAttribute('disabled');
				input.readOnly = false;
				input.disabled = false;
				input.style.pointerEvents = 'auto';
				input.style.cursor = 'text';
				input.style.backgroundColor = '#fff';
				input.style.opacity = '1';
				
				// Verificar que el valor sea correcto para el curso actual (desde BD)
				const currentCourseId = getCurrentCourseId();
				if (currentCourseId > 0) {
					// Solo actualizar si:
					// 1. El input está vacío
					// 2. No está enfocado (usuario no está escribiendo)
					// 3. El usuario NO ha modificado el campo intencionalmente (no lo borró)
					if (input.value === '' && 
						document.activeElement !== input && 
						!window._tutorCourseUrlSesionUserModified) {
						const correctValue = getSavedValue();
						if (correctValue) {
							input.value = correctValue;
							courseUrlSesionValue = correctValue; // Actualizar variable global
						}
					}
				}
			}
		}, 2000); // Cada 2 segundos

		// También intentar periódicamente (por si React tarda en renderizar)
		let attempts = 0;
		const maxAttempts = 60; // 30 segundos máximo (más tiempo para React)
		const interval = setInterval(() => {
			attempts++;
			if (fieldInjected || attempts >= maxAttempts) {
				clearInterval(interval);
				if (!fieldInjected && isBasicStep()) {
					console.warn('⚠️ No se pudo inyectar el campo course_url_sesion después de', maxAttempts * 500, 'ms');
					console.warn('⚠️ Elementos encontrados en la página:', {
						inputs: document.querySelectorAll('input').length,
						forms: document.querySelectorAll('form').length,
						basicSection: document.querySelector('[data-section="Basic"]') ? 'Sí' : 'No',
						tagsField: document.querySelector('input[name="tags"]') ? 'Sí' : 'No',
						authorField: document.querySelector('input[name="post_author"], select[name="post_author"]') ? 'Sí' : 'No',
						isBasicStep: isBasicStep()
					});
				}
				return;
			}
			// Solo intentar si estamos en el paso Basic
			if (isBasicStep()) {
			findAndRegisterField();
			}
		}, 500);

		// También intentar cuando cambie la ruta (SPA de React)
		if (window.addEventListener) {
			// Detectar cambios de hash (React Router puede usar hash)
			window.addEventListener('hashchange', () => {
				fieldInjected = false; // Resetear para que se vuelva a intentar
				currentStep = null;
				setTimeout(() => {
					if (isBasicStep()) {
					findAndRegisterField();
					}
				}, 1500);
			});

			// Detectar cambios de popstate
			window.addEventListener('popstate', () => {
				fieldInjected = false; // Resetear para que se vuelva a intentar
				currentStep = null;
				setTimeout(() => {
					if (isBasicStep()) {
					findAndRegisterField();
					}
				}, 1500);
			});

			// Interceptar pushState y replaceState de React Router
			const originalPushState = history.pushState;
			const originalReplaceState = history.replaceState;
			
			history.pushState = function(...args) {
				originalPushState.apply(history, args);
				fieldInjected = false;
				currentStep = null;
				setTimeout(() => {
					if (isBasicStep()) {
					findAndRegisterField();
					}
				}, 1500);
			};
			
			history.replaceState = function(...args) {
				originalReplaceState.apply(history, args);
				fieldInjected = false;
				currentStep = null;
				setTimeout(() => {
					if (isBasicStep()) {
					findAndRegisterField();
					}
				}, 1500);
			};
		}
	}

	// El campo funciona como los demás campos del formulario
	// Se envía con el formulario y se guarda en la BD automáticamente

	// Inicializar cuando el script se carga
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();

