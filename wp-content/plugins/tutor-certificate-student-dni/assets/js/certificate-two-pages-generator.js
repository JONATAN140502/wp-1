/**
 * Generar dos páginas del certificado: primera página (certificado) y segunda página (fondo + temario + QR)
 */
(function($) {
	'use strict';

	// Esperar a que el certificado se genere
	$(document).ready(function() {
		// Obtener el hash del certificado desde los datos localizados o del elemento
		var certHash = tutorTwoPagesCert && tutorTwoPagesCert.cert_hash ? tutorTwoPagesCert.cert_hash : null;
		
		// Buscar el elemento del certificado
		var $certImg = $('#tutor-pro-certificate-preview');
		
		if ($certImg.length) {
			// Si no tenemos el hash, obtenerlo del elemento
			if (!certHash) {
				certHash = $certImg.data('cert_hash');
			}
			
			if (!certHash) {
				return;
			}
			
			var isGenerated = $certImg.data('is_generated');
			
			// Si el certificado ya está generado, verificar si existe la segunda página
			if (isGenerated === 'yes') {
				// Verificar inmediatamente
				checkAndShowSecondPage(certHash);
			} else {
				// Esperar a que se genere el certificado
				var checkInterval = setInterval(function() {
					var newIsGenerated = $certImg.data('is_generated');
					if (newIsGenerated === 'yes') {
						clearInterval(checkInterval);
						// Esperar un poco más para que se guarde la primera imagen
						setTimeout(function() {
							generateSecondPage(certHash);
						}, 2000);
					}
				}, 500);
				
				// Limpiar después de 30 segundos
				setTimeout(function() {
					clearInterval(checkInterval);
				}, 30000);
			}
		} else if (certHash) {
			// Si no encontramos el elemento pero tenemos el hash, esperar a que aparezca
			var waitForElement = setInterval(function() {
				$certImg = $('#tutor-pro-certificate-preview');
				if ($certImg.length) {
					clearInterval(waitForElement);
					var isGenerated = $certImg.data('is_generated');
					if (isGenerated === 'yes') {
						checkAndShowSecondPage(certHash);
					} else {
						var checkInterval = setInterval(function() {
							var newIsGenerated = $certImg.data('is_generated');
							if (newIsGenerated === 'yes') {
								clearInterval(checkInterval);
								setTimeout(function() {
									generateSecondPage(certHash);
								}, 2000);
							}
						}, 500);
						setTimeout(function() {
							clearInterval(checkInterval);
						}, 30000);
					}
				}
			}, 500);
			
			setTimeout(function() {
				clearInterval(waitForElement);
			}, 10000);
		}
	});

	/**
	 * Generar la segunda página del certificado
	 */
	function generateSecondPage(certHash) {
		// Obtener datos del temario y fondo
		$.ajax({
			url: window._tutorobject.ajaxurl,
			type: 'GET',
			data: {
				action: 'tutor_get_course_curriculum',
				cert_hash: certHash
			},
			success: function(response) {
				if (response.success && response.data) {
					var curriculum = response.data.COURSE_CURRICULUM || [];
					var qrUrl = response.data.QR_URL || '';
					var backgroundUrl = response.data.BACKGROUND_URL || '';
					
					// Obtener dimensiones del certificado
					var $certImg = $('#tutor-pro-certificate-preview');
					var certImgSrc = $certImg.attr('src') || '';
					var orientation = $certImg.data('orientation') === 'landscape' ? 'l' : 'p';
					var size = $certImg.data('size') || 'letter';
					
					// Calcular dimensiones
					var pageWidth, pageHeight;
					if (orientation === 'l') {
						pageWidth = 792; // landscape letter
						pageHeight = 612;
					} else {
						pageWidth = 612; // portrait letter
						pageHeight = 792;
					}
					
					// Usar SOLO el fondo original del certificado builder (sin variables)
					// NO usar la imagen del certificado generado (certImgSrc) porque ya tiene las variables llenadas
					// Si no hay fondo original, se usará fondo blanco en generateSecondPageImage
					generateSecondPageImage(backgroundUrl, curriculum, qrUrl, pageWidth, pageHeight, certHash);
				}
			},
			error: function() {
				console.error('Error obteniendo datos del temario');
			}
		});
	}

	/**
	 * Generar la imagen de la segunda página
	 */
	function generateSecondPageImage(backgroundUrl, curriculum, qrUrl, pageWidth, pageHeight, certHash) {
		// Crear un canvas para la segunda página con mayor resolución para mejor calidad
		var scale = 2; // Factor de escala para mejor calidad
		var canvas = document.createElement('canvas');
		canvas.width = pageWidth * scale;
		canvas.height = pageHeight * scale;
		var ctx = canvas.getContext('2d');
		
		// Escalar el contexto para mantener las dimensiones lógicas
		ctx.scale(scale, scale);
		
		// Mejorar la calidad del renderizado
		ctx.imageSmoothingEnabled = true;
		ctx.imageSmoothingQuality = 'high';

		// Cargar la imagen de fondo
		var bgImg = new Image();
		bgImg.crossOrigin = 'anonymous';
		
		bgImg.onload = function() {
			// Dibujar el fondo (ya está escalado por el contexto)
			ctx.drawImage(bgImg, 0, 0, pageWidth, pageHeight);
			
			// Agregar el temario y QR
			addCurriculumToCanvas(ctx, curriculum, qrUrl, pageWidth, pageHeight, function() {
				// Convertir el canvas a blob y enviarlo al servidor
				canvas.toBlob(function(blob) {
					if (blob) {
						// Asegurarse de que el blob tenga el tipo correcto
						var jpegBlob = new Blob([blob], { type: 'image/jpeg' });
						sendSecondPageToServer(jpegBlob, certHash);
					} else {
						console.error('Error generando blob de la segunda página');
					}
				}, 'image/jpeg', 0.95);
			});
		};
		
		bgImg.onerror = function() {
			console.warn('Error cargando fondo original del certificado builder, usando fondo blanco');
			// Si falla el fondo original, usar fondo blanco
			// NO usar la imagen del certificado generado porque ya tiene las variables llenadas
			ctx.fillStyle = '#FFFFFF';
			ctx.fillRect(0, 0, pageWidth, pageHeight);
			addCurriculumToCanvas(ctx, curriculum, qrUrl, pageWidth, pageHeight, function() {
				canvas.toBlob(function(blob) {
					if (blob) {
						var jpegBlob = new Blob([blob], { type: 'image/jpeg' });
						sendSecondPageToServer(jpegBlob, certHash);
					} else {
						console.error('Error generando blob de la segunda página');
					}
				}, 'image/jpeg', 0.95);
			});
		};
		
		// Solo cargar el fondo si existe (fondo original sin variables)
		if (backgroundUrl) {
			bgImg.src = backgroundUrl;
		} else {
			// Si no hay fondo original, usar fondo blanco directamente
			console.warn('No hay fondo original del certificado builder, usando fondo blanco');
			ctx.fillStyle = '#FFFFFF';
			ctx.fillRect(0, 0, pageWidth, pageHeight);
			addCurriculumToCanvas(ctx, curriculum, qrUrl, pageWidth, pageHeight, function() {
				canvas.toBlob(function(blob) {
					if (blob) {
						var jpegBlob = new Blob([blob], { type: 'image/jpeg' });
						sendSecondPageToServer(jpegBlob, certHash);
					} else {
						console.error('Error generando blob de la segunda página');
					}
				}, 'image/jpeg', 0.95);
			});
		}
	}

	/**
	 * Agregar el temario y QR al canvas
	 */
	function addCurriculumToCanvas(ctx, curriculum, qrUrl, pageWidth, pageHeight, callback) {
		var margin = 40;
		var qrSize = 80;
		var qrYPos = margin + 30; // Posición Y del QR (arriba)
		var contentStartY = margin + 30 + qrSize + 20;
		var maxWidth = pageWidth - (margin * 2);
		var availableHeight = pageHeight - contentStartY - margin;
		
		// Tamaños iniciales
		var titleFontSize = 24;
		var topicFontSize = 16;
		var contentFontSize = 12;
		var lineHeight = 20;
		var topicSpacing = 0.5;
		
		// Función para calcular el espacio necesario
		function calculateRequiredHeight(curriculum, topicFontSize, contentFontSize, lineHeight, topicSpacing, maxWidth) {
			var testCtx = document.createElement('canvas').getContext('2d');
			testCtx.font = 'bold ' + topicFontSize + 'px Arial, sans-serif';
			var totalHeight = 0;
			
			curriculum.forEach(function(topic) {
				// Altura del título del tema
				totalHeight += lineHeight * 1.5;
				
				// Altura de los contenidos
				if (topic.contents && topic.contents.length > 0) {
					testCtx.font = contentFontSize + 'px Arial, sans-serif';
					
					topic.contents.forEach(function(content) {
						var contentText = '   • ' + content.title;
						var words = contentText.split(' ');
						var line = '';
						var lines = 1;
						
						for (var i = 0; i < words.length; i++) {
							var testLine = line + words[i] + ' ';
							var metrics = testCtx.measureText(testLine);
							var testWidth = metrics.width;
							
							if (testWidth > maxWidth - 40 && i > 0) {
								lines++;
								line = words[i] + ' ';
							} else {
								line = testLine;
							}
						}
						totalHeight += lineHeight * lines;
					});
				}
				
				// Espacio entre temas
				totalHeight += lineHeight * topicSpacing;
			});
			
			return totalHeight;
		}
		
		// Ajustar tamaños si el contenido no cabe
		var requiredHeight = calculateRequiredHeight(curriculum, topicFontSize, contentFontSize, lineHeight, topicSpacing, maxWidth);
		var scaleFactor = 1;
		
		if (requiredHeight > availableHeight) {
			// Calcular factor de escala
			scaleFactor = availableHeight / requiredHeight;
			scaleFactor = Math.max(0.6, Math.min(1, scaleFactor)); // Limitar entre 60% y 100%
			
			// Aplicar escala
			topicFontSize = Math.max(12, Math.round(topicFontSize * scaleFactor));
			contentFontSize = Math.max(9, Math.round(contentFontSize * scaleFactor));
			lineHeight = Math.max(14, Math.round(lineHeight * scaleFactor));
			topicSpacing = Math.max(0.3, topicSpacing * scaleFactor);
		}
		
		// Mejorar la calidad del texto
		ctx.textBaseline = 'top';
		
		// Título de la página
		ctx.fillStyle = '#000000';
		ctx.font = 'bold ' + titleFontSize + 'px Arial, sans-serif';
		ctx.textAlign = 'center';
		ctx.fillText('Temario del Curso', pageWidth / 2, margin + 30);
		
		var yPos = contentStartY;
		
		// Agregar cada tema y sus contenidos
		curriculum.forEach(function(topic, index) {
			if (yPos > pageHeight - margin - lineHeight) {
				return; // No agregar más si no cabe
			}

			// Título del tema (sin numerar)
			ctx.font = 'bold ' + topicFontSize + 'px Arial, sans-serif';
			ctx.textAlign = 'left';
			var topicTitle = topic.topic_title;
			ctx.fillText(topicTitle, margin, yPos);
			yPos += lineHeight * 1.5;

			// Contenidos del tema (sin numerar, solo viñetas)
			if (topic.contents && topic.contents.length > 0) {
				ctx.font = contentFontSize + 'px Arial, sans-serif';
				
				topic.contents.forEach(function(content) {
					if (yPos > pageHeight - margin - lineHeight) {
						return;
					}

					var contentText = '   • ' + content.title;
					
					// Dividir el texto si es muy largo
					var words = contentText.split(' ');
					var line = '';
					var lineY = yPos;
					
					for (var i = 0; i < words.length; i++) {
						var testLine = line + words[i] + ' ';
						var metrics = ctx.measureText(testLine);
						var testWidth = metrics.width;
						
						if (testWidth > maxWidth - 40 && i > 0) {
							ctx.fillText(line, margin + 20, lineY);
							line = words[i] + ' ';
							lineY += lineHeight;
							
							if (lineY > pageHeight - margin - lineHeight) {
								return;
							}
						} else {
							line = testLine;
						}
					}
					ctx.fillText(line, margin + 20, lineY);
					yPos = lineY + lineHeight;
				});
			}

			yPos += lineHeight * topicSpacing;
		});
		
		// Agregar código QR en la parte superior derecha
		if (qrUrl) {
			generateQRCode(qrUrl, function(qrDataUrl) {
				if (qrDataUrl) {
					var qrImg = new Image();
					qrImg.onload = function() {
						var qrX = pageWidth - margin - qrSize;
						ctx.drawImage(qrImg, qrX, qrYPos, qrSize, qrSize);
						
						if (callback) {
							callback();
						}
					};
					qrImg.onerror = function() {
						if (callback) {
							callback();
						}
					};
					qrImg.src = qrDataUrl;
				} else {
					if (callback) {
						callback();
					}
				}
			});
		} else {
			if (callback) {
				callback();
			}
		}
	}

	/**
	 * Generar código QR
	 */
	function generateQRCode(url, callback) {
		// Aumentar el tamaño del QR para mejor calidad
		var qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' + encodeURIComponent(url);
		
		var img = new Image();
		img.crossOrigin = 'anonymous';
		img.onload = function() {
			var canvas = document.createElement('canvas');
			canvas.width = img.width;
			canvas.height = img.height;
			var ctx = canvas.getContext('2d');
			ctx.drawImage(img, 0, 0);
			var dataUrl = canvas.toDataURL('image/png');
			callback(dataUrl);
		};
		img.onerror = function() {
			callback(null);
		};
		img.src = qrApiUrl;
	}

	/**
	 * Enviar la segunda página al servidor
	 */
	function sendSecondPageToServer(blob, certHash) {
		// Asegurarse de que tenemos el nonce
		if (!tutorTwoPagesCert || !tutorTwoPagesCert.nonce) {
			console.error('Nonce no disponible');
			return;
		}
		
		var formData = new FormData();
		formData.append('action', 'tutor_store_certificate_second_page');
		formData.append('cert_hash', certHash);
		formData.append('certificate_image_page2', blob, 'page2.jpg');
		formData.append('_wpnonce', tutorTwoPagesCert.nonce);
		
		$.ajax({
			url: tutorTwoPagesCert.ajaxurl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					console.log('Segunda página guardada exitosamente');
					// Esperar un momento y luego mostrar la segunda página
					setTimeout(function() {
						checkAndShowSecondPage(certHash);
					}, 500);
				} else {
					console.error('Error guardando segunda página:', response.data ? response.data.message : 'Error desconocido');
				}
			},
			error: function(xhr, status, error) {
				console.error('Error guardando segunda página:', error);
				console.error('Response:', xhr.responseText);
			}
		});
	}

	/**
	 * Verificar y mostrar la segunda página si existe
	 */
	function checkAndShowSecondPage(certHash) {
		var $certImg = $('#tutor-pro-certificate-preview');
		if (!$certImg.length) {
			return;
		}
		
		var certImgSrc = $certImg.attr('src');
		// Extraer la ruta base
		var basePath = certImgSrc.substring(0, certImgSrc.lastIndexOf('/'));
		var fileName = certImgSrc.split('/').pop();
		var secondPageUrl = basePath + '/' + fileName.replace('.jpg', '-page2.jpg');
		
		// Verificar si la imagen existe
		var testImg = new Image();
		testImg.onload = function() {
			// La segunda página existe, mostrarla
			var $certContainer = $certImg.closest('.tutor-certificate-demo');
			if (!$certContainer.length) {
				$certContainer = $('.tutor-certificate-demo').first();
			}
			
			if ($certContainer.length) {
				// Verificar que no se haya agregado ya
				if ($certContainer.next('.tutor-certificate-demo[data-second-page]').length === 0) {
					var $secondPage = $('<div class="tutor-certificate-demo tutor-pb-44 tutor-mt-24" data-second-page="1"><span class="tutor-dc-demo-img"><img src="' + secondPageUrl + '" alt="Temario del Curso" style="width:100%;max-width:100%;height:auto;" /></span></div>');
					$certContainer.after($secondPage);
				}
			}
		};
		testImg.onerror = function() {
			// La segunda página no existe aún, intentar generarla
			setTimeout(function() {
				generateSecondPage(certHash);
			}, 1000);
		};
		testImg.src = secondPageUrl;
	}

})(jQuery);

