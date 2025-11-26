/**
 * JavaScript para administración de recursos - Interfaz tipo Google Drive
 */

jQuery(document).ready(function($) {
	// Asegurar que la función loadLessonsForCourse esté disponible globalmente
	window.loadLessonsForCourse = function(courseId, targetSelectId, selectedLessonIds) {
			if (!courseId || courseId == 0 || courseId == '0') {
				// Ocultar el campo de lecciones
				if (targetSelectId === 'folder-lesson-ids') {
					$('#folder-lessons-wrapper').hide();
					$('#folder-lessons-wrapper tr').hide(); // Para wp-admin que usa <tr>
				} else if (targetSelectId === 'file-lesson-ids') {
					$('#file-lessons-wrapper').hide();
				} else if (targetSelectId === 'link-lesson-ids') {
					$('#link-lessons-wrapper').hide();
				}
				$('#' + targetSelectId).html('');
				return;
			}
		
		$.ajax({
			url: tutorResources.ajaxurl,
			type: 'POST',
			data: {
				action: 'tutor_get_course_lessons',
				nonce: tutorResources.nonce,
				course_id: courseId,
			},
			success: function(response) {
				console.log('Lecciones cargadas:', response);
				if (response.success && response.data.lessons) {
					var select = $('#' + targetSelectId);
					select.html('');
					
					if (response.data.lessons.length > 0) {
						// Convertir selectedLessonIds a array de strings para comparación
						var selectedIds = [];
						if (selectedLessonIds && selectedLessonIds.length > 0) {
							selectedIds = selectedLessonIds.map(function(id) {
								return String(id);
							});
						}
						
						$.each(response.data.lessons, function(index, lesson) {
							var lessonIdStr = String(lesson.ID);
							var isSelected = selectedIds.indexOf(lessonIdStr) !== -1;
							var selectedAttr = isSelected ? 'selected' : '';
							select.append('<option value="' + lesson.ID + '" ' + selectedAttr + '>' + lesson.post_title + '</option>');
						});
						
						// Mostrar el campo de lecciones (funciona con <tr> en wp-admin y <div> en frontend)
						if (targetSelectId === 'folder-lesson-ids') {
							$('#folder-lessons-wrapper').show();
							$('#folder-lessons-wrapper tr').show(); // Para wp-admin
						} else if (targetSelectId === 'file-lesson-ids') {
							$('#file-lessons-wrapper').show();
							$('#file-lessons-wrapper tr').show(); // Para wp-admin
						} else if (targetSelectId === 'link-lesson-ids') {
							$('#link-lessons-wrapper').show();
							$('#link-lessons-wrapper tr').show(); // Para wp-admin
						}
					} else {
						// Ocultar si no hay lecciones
						if (targetSelectId === 'folder-lesson-ids') {
							$('#folder-lessons-wrapper').hide();
						} else if (targetSelectId === 'file-lesson-ids') {
							$('#file-lessons-wrapper').hide();
						} else if (targetSelectId === 'link-lesson-ids') {
							$('#link-lessons-wrapper').hide();
						}
					}
				} else {
					// Ocultar si hay error
					if (targetSelectId === 'folder-lesson-ids') {
						$('#folder-lessons-wrapper').hide();
					} else if (targetSelectId === 'file-lesson-ids') {
						$('#file-lessons-wrapper').hide();
					} else if (targetSelectId === 'link-lesson-ids') {
						$('#link-lessons-wrapper').hide();
					}
				}
			},
			error: function(xhr, status, error) {
				console.error('Error al cargar lecciones:', error);
				// Ocultar si hay error
				if (targetSelectId === 'folder-lesson-ids') {
					$('#folder-lessons-wrapper').hide();
				} else if (targetSelectId === 'file-lesson-ids') {
					$('#file-lessons-wrapper').hide();
				} else if (targetSelectId === 'link-lesson-ids') {
					$('#link-lessons-wrapper').hide();
				}
			}
		});
	};
	
	// Modales (funciona en wp-admin y frontend)
	var folderModal = $('#folder-modal, .tutor-drive-modal-frontend#folder-modal');
	var fileModal = $('#file-modal, .tutor-drive-modal-frontend#file-modal');
	var driveLinkModal = $('#drive-link-modal, .tutor-drive-modal-frontend#drive-link-modal');
	
	// Si no se encuentran los modales, buscar con otras clases
	if (folderModal.length === 0) {
		folderModal = $('.tutor-drive-modal-frontend:has(#folder-form)');
	}
	if (fileModal.length === 0) {
		fileModal = $('.tutor-drive-modal-frontend:has(#file-form)');
	}
	if (driveLinkModal.length === 0) {
		driveLinkModal = $('.tutor-drive-modal-frontend:has(#drive-link-form)');
	}
	
	// Ocultar campos de lecciones inicialmente
	$('#folder-lessons-wrapper, #file-lessons-wrapper, #link-lessons-wrapper').hide();
	
	// Botones para abrir modales
	$('#create-folder-btn').on('click', function() {
		$('#folder-form')[0].reset();
		$('#folder-id').val('');
		$('#folder-modal-title').text('Nueva Carpeta');
		$('#folder-lessons-wrapper').hide(); // Ocultar lecciones inicialmente
		$('#folder-lesson-ids').html('');
		
		// Verificar si hay un curso ya seleccionado (heredado de carpeta padre)
		setTimeout(function() {
			var parentCourseId = $('#folder-course-id').val();
			if (parentCourseId && parentCourseId != 0) {
				loadLessonsForCourse(parentCourseId, 'folder-lesson-ids', []);
			}
		}, 300);
		
		folderModal.show();
	});
	
	$('#upload-file-btn').on('click', function() {
		$('#file-form')[0].reset();
		$('#file-id').val('');
		$('#selected-file-name').text('');
		$('#file-lesson-ids').html('');
		$('#file-lessons-wrapper').hide();
		fileModal.show();
		
		// Cargar lecciones de la carpeta padre si existe (después de mostrar el modal)
		setTimeout(function() {
			loadFolderLessonsForResource('file');
		}, 200);
	});
	
	// Función para cargar lecciones de la carpeta padre para recursos
	function loadFolderLessonsForResource(resourceType) {
		var folderId = resourceType === 'file' ? $('#file-folder-id').val() : $('#link-folder-id').val();
		var selectId = resourceType === 'file' ? 'file-lesson-ids' : 'link-lesson-ids';
		
		if (!folderId || folderId == 0) {
			return;
		}
		
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
								var folderLessonIds = [];
								if (lessonsResponse.success && lessonsResponse.data.lesson_ids) {
									folderLessonIds = Array.isArray(lessonsResponse.data.lesson_ids) 
										? lessonsResponse.data.lesson_ids 
										: [lessonsResponse.data.lesson_ids];
								}
								
								// Convertir a array de strings para comparación
								folderLessonIds = folderLessonIds.map(function(id) {
									return String(id);
								});
								
								// Cargar lecciones del curso y preseleccionar las de la carpeta
								if (folderLessonIds.length > 0) {
									loadLessonsForCourse(folder.course_id, selectId, folderLessonIds);
								} else {
									loadLessonsForCourse(folder.course_id, selectId, []);
								}
							},
							error: function() {
								console.error('Error al cargar lecciones de la carpeta');
							}
						});
					}
				}
			},
			error: function() {
				console.error('Error al cargar datos de la carpeta');
			}
		});
	}
	
	$('#add-drive-link-btn').on('click', function() {
		$('#drive-link-form')[0].reset();
		$('#link-lesson-ids').html('');
		$('#link-lessons-wrapper').hide();
		driveLinkModal.show();
		
		// Cargar lecciones de la carpeta padre si existe (después de mostrar el modal)
		setTimeout(function() {
			loadFolderLessonsForResource('link');
		}, 200);
	});
	
	// Cerrar modales (funciona en wp-admin y frontend)
	$(document).on('click', '.modal-close, .cancel-modal, .cancel-folder', function() {
		$('.tutor-drive-modal, .tutor-drive-modal-frontend').hide();
	});
	
	// Cerrar modal al hacer clic fuera
	$(window).on('click', function(e) {
		if ($(e.target).hasClass('tutor-drive-modal') || $(e.target).hasClass('tutor-drive-modal-frontend')) {
			$('.tutor-drive-modal, .tutor-drive-modal-frontend').hide();
		}
	});
	
	// Toggle de acceso a docentes
	$('#folder-access-teachers, #file-access-teachers, #link-access-teachers').on('change', function() {
		var container = $(this).closest('fieldset').find('[id$="-teachers-list-container"]');
		if ($(this).is(':checked')) {
			container.show();
		} else {
			container.hide();
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
			title: 'Seleccionar Archivo',
			button: {
				text: 'Usar este archivo'
			},
			multiple: false
		});
		
		file_frame.on('select', function() {
			var attachment = file_frame.state().get('selection').first().toJSON();
			$('#file-id').val(attachment.id);
			$('#selected-file-name').text(' - ' + attachment.filename);
		});
		
		file_frame.open();
	});
	
	// Toggle vista (grid/list)
	$('.view-btn').on('click', function() {
		var view = $(this).data('view');
		$('#drive-content').attr('data-view', view);
		$('.view-btn').removeClass('active');
		$(this).addClass('active');
	});
	
	// Cuando cambie el curso en el formulario de carpeta (wp-admin y frontend)
	$(document).on('change', '#folder-course-id', function() {
		var courseId = $(this).val();
		var folderId = $('#folder-id').val();
		var selectedLessons = [];
		
		// Si estamos editando, obtener las lecciones actuales
		if (folderId && folderId != '' && folderId != '0') {
			selectedLessons = $('#folder-lesson-ids').val() || [];
		}
		
		console.log('Curso seleccionado:', courseId);
		
		// Si no hay curso seleccionado, ocultar el campo de lecciones
		if (!courseId || courseId == 0 || courseId == '0') {
			$('#folder-lessons-wrapper').hide();
			$('#folder-lesson-ids').html('');
			return;
		}
		
		// Cargar las lecciones del curso
		loadLessonsForCourse(courseId, 'folder-lesson-ids', selectedLessons);
	});
	
	// Cuando cambie el curso en el formulario de archivo
	$(document).on('change', '#file-course-id', function() {
		var courseId = $(this).val();
		loadLessonsForCourse(courseId, 'file-lesson-ids', []);
	});
	
	// Cuando cambie el curso en el formulario de enlace Drive
	$(document).on('change', '#link-course-id', function() {
		var courseId = $(this).val();
		loadLessonsForCourse(courseId, 'link-lesson-ids', []);
	});
	
	
	// Cuando se seleccione una carpeta padre con curso, cargar sus lecciones también
	if ($('#folder-parent-id').val() > 0) {
		// Si hay carpeta padre, puede que tenga curso
		// Esto se manejará cuando se abra el modal de edición
	}
	
	// Guardar carpeta
	$('#folder-form').on('submit', function(e) {
		e.preventDefault();
		
		var parentId = $('#folder-parent-id').val();
		var courseId = $('#folder-course-id').val();
		var isLibre = $('#folder-is-libre').is(':checked') ? 1 : 0;
		var lessonIds = $('#folder-lesson-ids').val() || [];
		
		// Si hay parent_id y no se especificó course_id, el servidor lo heredará automáticamente
		// Pero enviamos 0 para que el servidor lo detecte y herede
		if (parentId > 0 && courseId == 0) {
			// El servidor heredará el curso de la carpeta padre
		}
		
		var formData = {
			action: 'tutor_create_folder',
			nonce: tutorResources.nonce,
			folder_id: $('#folder-id').val(),
			name: $('#folder-name').val(),
			parent_id: parentId,
			course_id: courseId || 0,
			is_libre: isLibre,
			lesson_ids: lessonIds,
			access_students: $('#folder-access-students').is(':checked') ? 1 : 0,
			access_teachers: $('#folder-access-teachers').is(':checked') ? 1 : 0,
			access_teachers_list: $('#folder-access-teachers-list').val() ? $('#folder-access-teachers-list').val().join(',') : '',
			access_students_list: $('#folder-access-students-list').val() ? $('#folder-access-students-list').val().join(',') : '',
		};
		
		$.ajax({
			url: tutorResources.ajaxurl,
			type: 'POST',
			data: formData,
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message || 'Error al guardar la carpeta.');
				}
			},
			error: function() {
				alert('Error de conexión.');
			}
		});
	});
	
	// Guardar archivo
	$('#file-form').on('submit', function(e) {
		e.preventDefault();
		
		var courseId = $('#file-course-id').val() || 0;
		var folderId = $('#file-folder-id').val();
		
		// Si hay carpeta padre, el curso puede venir de ahí
		if (!courseId && folderId > 0) {
			// El curso se obtendrá del servidor desde la carpeta padre
		}
		
		var formData = {
			action: 'tutor_save_file_to_folder',
			nonce: tutorResources.nonce,
			title: $('#file-title').val(),
			folder_id: folderId,
			course_id: courseId,
			file_id: $('#file-id').val(),
			resource_type: 'file',
			lesson_ids: $('#file-lesson-ids').val() || [],
			access_students: $('#file-access-students').is(':checked') ? 1 : 0,
			access_teachers: $('#file-access-teachers').is(':checked') ? 1 : 0,
			access_students_list: $('#file-access-students-list').val() ? $('#file-access-students-list').val() : '',
		};
		
		$.ajax({
			url: tutorResources.ajaxurl,
			type: 'POST',
			data: formData,
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message || 'Error al guardar el archivo.');
				}
			},
			error: function() {
				alert('Error de conexión.');
			}
		});
	});
	
	// Guardar enlace de Google Drive
	$('#drive-link-form').on('submit', function(e) {
		e.preventDefault();
		
		var courseId = $('#link-course-id').val() || 0;
		var folderId = $('#link-folder-id').val();
		
		// Si hay carpeta padre, el curso puede venir de ahí
		if (!courseId && folderId > 0) {
			// El curso se obtendrá del servidor desde la carpeta padre
		}
		
		var formData = {
			action: 'tutor_save_file_to_folder',
			nonce: tutorResources.nonce,
			title: $('#link-title').val(),
			folder_id: folderId,
			course_id: courseId,
			resource_url: $('#link-url').val(),
			resource_type: 'drive',
			lesson_ids: $('#link-lesson-ids').val() || [],
			access_students: $('#link-access-students').is(':checked') ? 1 : 0,
			access_teachers: $('#link-access-teachers').is(':checked') ? 1 : 0,
			access_students_list: $('#link-access-students-list').val() ? $('#link-access-students-list').val() : '',
		};
		
		$.ajax({
			url: tutorResources.ajaxurl,
			type: 'POST',
			data: formData,
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message || 'Error al guardar el enlace.');
				}
			},
			error: function() {
				alert('Error de conexión.');
			}
		});
	});
	
	// Editar carpeta
	$(document).on('click', '.edit-folder', function(e) {
		e.preventDefault();
		e.stopPropagation();
		
		var folderId = $(this).data('folder-id');
		
		// Cargar datos de la carpeta mediante AJAX
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
					$('#folder-form')[0].reset();
					$('#folder-id').val(folder.id);
					$('#folder-name').val(folder.name);
					$('#folder-parent-id').val(folder.parent_id || 0);
					$('#folder-course-id').val(folder.course_id || 0);
					$('#folder-is-libre').prop('checked', folder.is_libre == 1);
					$('#folder-access-students').prop('checked', folder.access_students == 1);
					$('#folder-access-teachers').prop('checked', folder.access_teachers == 1);
					
					// Cargar lista de docentes seleccionados
					if (folder.access_teachers_list) {
						var teachers = folder.access_teachers_list.split(',');
						$('#folder-access-teachers-list').val(teachers);
					}
					
					// Cargar lista de estudiantes seleccionados
					if (folder.access_students_list) {
						var students = folder.access_students_list.split(',');
						$('#folder-access-students-list').val(students);
					}
					
					// Cargar lecciones relacionadas
					var folderId = folder.id;
					if (folder.course_id && folder.course_id > 0) {
						// Cargar lecciones del curso
						$.ajax({
							url: tutorResources.ajaxurl,
							type: 'POST',
							data: {
								action: 'tutor_get_course_lessons',
								nonce: tutorResources.nonce,
								course_id: folder.course_id,
							},
							success: function(lessonsResponse) {
								if (lessonsResponse.success && lessonsResponse.data.lessons) {
									// Obtener lecciones relacionadas con esta carpeta
									$.ajax({
										url: tutorResources.ajaxurl,
										type: 'POST',
										data: {
											action: 'tutor_get_folder_lessons',
											nonce: tutorResources.nonce,
											folder_id: folderId,
										},
										success: function(folderLessonsResponse) {
											var selectedLessonIds = folderLessonsResponse.success && folderLessonsResponse.data.lesson_ids ? folderLessonsResponse.data.lesson_ids : [];
											loadLessonsForCourse(folder.course_id, 'folder-lesson-ids', selectedLessonIds);
										}
									});
								}
							}
						});
					}
					
					$('#folder-modal-title').text('Editar Carpeta');
					folderModal.show();
				} else {
					alert('Error al cargar los datos de la carpeta.');
				}
			},
			error: function() {
				alert('Error de conexión.');
			}
		});
	});
	
	// Eliminar carpeta
	$(document).on('click', '.delete-folder', function(e) {
		e.preventDefault();
		e.stopPropagation();
		
		var folderId = $(this).data('folder-id');
		var folderName = $(this).closest('.folder-item').find('.item-name').text().trim();
		
		var confirmMessage = '¿Estás seguro de eliminar la carpeta "' + folderName + '"?\n\n';
		confirmMessage += 'Esta acción eliminará la carpeta y TODO su contenido (subcarpetas y archivos).\n\n';
		confirmMessage += 'Esta acción no se puede deshacer.';
		
		if (!confirm(confirmMessage)) {
			return;
		}
		
		
		$.ajax({
			url: tutorResources.ajaxurl,
			type: 'POST',
			data: {
				action: 'tutor_delete_folder',
				nonce: tutorResources.nonce,
				folder_id: folderId,
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message || 'Error al eliminar la carpeta.');
				}
			},
			error: function() {
				alert('Error de conexión.');
			}
		});
	});
	
	// Eliminar recurso
	$(document).on('click', '.delete-resource', function(e) {
		e.preventDefault();
		e.stopPropagation();
		
		if (!confirm('¿Estás seguro de eliminar este recurso?')) {
			return;
		}
		
		var resourceId = $(this).data('resource-id');
		
		$.ajax({
			url: tutorResources.ajaxurl,
			type: 'POST',
			data: {
				action: 'tutor_delete_resource',
				nonce: tutorResources.nonce,
				resource_id: resourceId,
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message || 'Error al eliminar el recurso.');
				}
			},
			error: function() {
				alert('Error de conexión.');
			}
		});
	});
	
	// Modo selección múltiple
	var selectionMode = false;
	
	// Toggle modo selección (activar checkboxes)
	function toggleSelectionMode() {
		selectionMode = !selectionMode;
		if (selectionMode) {
			$('.item-checkbox').show();
			$('#select-all-btn, #delete-selected-btn').show();
			$('#toggle-selection-mode-btn').text('Cancelar Selección');
		} else {
			$('.item-checkbox').hide();
			$('.item-select').prop('checked', false);
			$('#select-all-btn, #delete-selected-btn').hide();
			$('#toggle-selection-mode-btn').text('Seleccionar');
		}
	}
	
	// Activar/desactivar modo selección
	$('#toggle-selection-mode-btn').on('click', function() {
		toggleSelectionMode();
	});
	
	// Seleccionar todo
	$('#select-all-btn').on('click', function() {
		$('.item-select').prop('checked', true);
	});
	
	// Eliminar seleccionados
	$('#delete-selected-btn').on('click', function() {
		var selectedItems = [];
		$('.item-select:checked').each(function() {
			var $item = $(this);
			selectedItems.push({
				type: $item.data('item-type'),
				id: $item.data('item-id')
			});
		});
		
		if (selectedItems.length === 0) {
			alert('Por favor, selecciona al menos un elemento para eliminar.');
			return;
		}
		
		var message = '¿Estás seguro de eliminar ' + selectedItems.length + ' elemento(s)?';
		var folderCount = selectedItems.filter(function(item) { return item.type === 'folder'; }).length;
		if (folderCount > 0) {
			message += '\n\nLas carpetas se eliminarán junto con todo su contenido.';
		}
		
		if (!confirm(message)) {
			return;
		}
		
		$.ajax({
			url: tutorResources.ajaxurl,
			type: 'POST',
			data: {
				action: 'tutor_delete_multiple_items',
				nonce: tutorResources.nonce,
				items: selectedItems,
			},
			success: function(response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message || 'Error al eliminar los elementos.');
				}
			},
			error: function() {
				alert('Error de conexión.');
			}
		});
	});
	
	// Activar modo selección con tecla (Shift + Click en un elemento o botón específico)
	// Por ahora, agregamos un atajo: click derecho o mantener Shift y click
	$(document).on('contextmenu', '.drive-item', function(e) {
		e.preventDefault();
		if (!selectionMode) {
			toggleSelectionMode();
		}
		$(this).find('.item-select').prop('checked', true);
	});
	
	// Editar recurso
	$(document).on('click', '.edit-resource', function(e) {
		e.preventDefault();
		e.stopPropagation();
		
		var resourceId = $(this).data('resource-id');
		
		// Cargar datos del recurso mediante AJAX
		$.ajax({
			url: tutorResources.ajaxurl,
			type: 'POST',
			data: {
				action: 'tutor_get_resource_data',
				nonce: tutorResources.nonce,
				resource_id: resourceId,
			},
			success: function(response) {
				if (response.success && response.data.resource) {
					var resource = response.data.resource;
					
						// Obtener lecciones relacionadas con el recurso
						$.ajax({
							url: tutorResources.ajaxurl,
							type: 'POST',
							data: {
								action: 'tutor_get_resource_lessons',
								nonce: tutorResources.nonce,
								resource_id: resource.id,
							},
							success: function(resourceLessonsResponse) {
								var selectedLessonIds = resourceLessonsResponse.success && resourceLessonsResponse.data.lesson_ids ? resourceLessonsResponse.data.lesson_ids : [];
								
								if (resource.resource_type === 'drive') {
									$('#drive-link-form')[0].reset();
									$('#link-title').val(resource.title);
									$('#link-url').val(resource.resource_url || '');
									$('#link-folder-id').val(resource.folder_id || 0);
									$('#link-course-id').val(resource.course_id || 0);
									$('#link-access-students').prop('checked', resource.access_students == 1);
									$('#link-access-teachers').prop('checked', resource.access_teachers == 1);
									
									if (resource.access_students_list) {
										var students = resource.access_students_list.split(',');
										$('#link-access-students-list').val(students);
									}
									
									// Cargar lecciones si hay curso
									if (resource.course_id && resource.course_id > 0) {
										loadLessonsForCourse(resource.course_id, 'link-lesson-ids', selectedLessonIds);
									}
									
									// Agregar campo hidden para ID del recurso (remover si existe primero)
									$('#drive-link-form input[name="resource_id"]').remove();
									$('#drive-link-form').append('<input type="hidden" name="resource_id" value="' + resource.id + '">');
									
									driveLinkModal.show();
								} else {
									$('#file-form')[0].reset();
									$('#file-title').val(resource.title);
									$('#file-id').val(resource.file_id || '');
									$('#file-folder-id').val(resource.folder_id || 0);
									$('#file-course-id').val(resource.course_id || 0);
									$('#file-access-students').prop('checked', resource.access_students == 1);
									$('#file-access-teachers').prop('checked', resource.access_teachers == 1);
									
									if (resource.file_id) {
										$.ajax({
											url: tutorResources.ajaxurl,
											type: 'POST',
											data: {
												action: 'tutor_get_file_name',
												nonce: tutorResources.nonce,
												file_id: resource.file_id,
											},
											success: function(fileResponse) {
												if (fileResponse.success) {
													$('#selected-file-name').text(' - ' + fileResponse.data.file_name);
												}
											}
										});
									}
									
									if (resource.access_students_list) {
										var students = resource.access_students_list.split(',');
										$('#file-access-students-list').val(students);
									}
									
									// Cargar lecciones si hay curso
									if (resource.course_id && resource.course_id > 0) {
										loadLessonsForCourse(resource.course_id, 'file-lesson-ids', selectedLessonIds);
									}
									
									// Agregar campo hidden para ID del recurso (remover si existe primero)
									$('#file-form input[name="resource_id"]').remove();
									$('#file-form').append('<input type="hidden" name="resource_id" value="' + resource.id + '">');
									
									fileModal.show();
								}
							}
						});
				} else {
					alert('Error al cargar los datos del recurso.');
				}
			},
			error: function() {
				alert('Error de conexión.');
			}
		});
	});
	
	// Abrir carpeta al hacer clic (solo si no está en modo selección)
	$(document).on('click', '.folder-item:not(.item-action):not(.item-actions)', function(e) {
		if (selectionMode || $(e.target).closest('.item-action, .item-actions, .item-checkbox').length > 0) {
			return;
		}
		var folderId = $(this).data('item-id');
		if (window.location.href.indexOf('admin.php') !== -1) {
			window.location.href = 'admin.php?page=tutor-course-resources&folder_id=' + folderId;
		} else {
			window.location.href = window.location.pathname + '?folder_id=' + folderId;
		}
	});
});
