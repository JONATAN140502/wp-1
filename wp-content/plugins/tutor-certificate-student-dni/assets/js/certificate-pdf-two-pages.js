/**
 * Modificar la generación del PDF del certificado para incluir dos páginas:
 * - Página 1: El certificado actual
 * - Página 2: El temario del curso
 */
(function($) {
	'use strict';

	// Esperar a que jsPDF esté disponible
	function waitForJSPDF(callback) {
		if (typeof jspdf !== 'undefined' && typeof jspdf.jsPDF !== 'undefined') {
			callback();
		} else if (typeof window.jspdf !== 'undefined' && typeof window.jspdf.jsPDF !== 'undefined') {
			callback();
		} else {
			setTimeout(function() {
				waitForJSPDF(callback);
			}, 100);
		}
	}

	$(document).ready(function() {
		waitForJSPDF(function() {
			// Remover el evento click original del botón
			$('.tutor-certificate-pdf').off('click');
			
			// Interceptar el clic en el botón de PDF
			$(document).on('click', '.tutor-certificate-pdf', function(e) {
				e.preventDefault();
				e.stopPropagation();
			
			var $certImg = $('#tutor-pro-certificate-preview');
			if (!$certImg.length) {
				return;
			}

			var certImgSrc = $certImg.attr('src');
			var orientation = $certImg.data('orientation') === 'landscape' ? 'l' : 'p';
			var size = $certImg.data('size') || 'letter';
			var certHash = $certImg.data('cert_hash');
			var courseId = $certImg.data('course_id');

			// Obtener el temario del curso y QR desde nuestro endpoint AJAX
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
						// Usar SOLO el fondo original del certificado builder (sin variables)
						// NO usar la imagen del certificado generado porque ya tiene las variables llenadas
						generatePdfWithCurriculum(certImgSrc, orientation, size, curriculum, qrUrl, backgroundUrl);
					} else {
						// Si no se puede obtener el temario, generar PDF normal
						generatePdfWithCurriculum(certImgSrc, orientation, size, [], '', '');
					}
				},
				error: function() {
					// Si hay error, generar PDF normal
					generatePdfWithCurriculum(certImgSrc, orientation, size, [], '', '');
				}
			});
			});
		});
	});

	/**
	 * Generar PDF con dos páginas: certificado y temario
	 */
	function generatePdfWithCurriculum(certImgSrc, orientation, size, curriculum, qrUrl, backgroundUrl) {
		// Obtener jsPDF de la forma correcta
		var jsPDF = (typeof jspdf !== 'undefined' && typeof jspdf.jsPDF !== 'undefined') 
			? jspdf.jsPDF 
			: (typeof window.jspdf !== 'undefined' && typeof window.jspdf.jsPDF !== 'undefined')
				? window.jspdf.jsPDF
				: null;
		
		if (!jsPDF) {
			console.error('jsPDF no está disponible');
			return;
		}
		
		var pdf = new jsPDF(orientation, 'px', size, true);
		var pageWidth = pdf.internal.pageSize.getWidth();
		var pageHeight = pdf.internal.pageSize.getHeight();

		// Página 1: Certificado (primera imagen)
		var img = new Image();
		img.crossOrigin = 'anonymous';
		img.onload = function() {
			// Agregar la imagen del certificado en la primera página
			pdf.addImage(certImgSrc, 'PNG', 0, 0, pageWidth, pageHeight, undefined, 'NONE');

			// Generar la segunda imagen (fondo + temario + QR) si hay temario
			if (curriculum && curriculum.length > 0) {
				// Usar SOLO el fondo original del certificado builder (sin variables)
				// NO usar la imagen del certificado generado porque ya tiene las variables llenadas
				generateSecondPageImage(backgroundUrl, curriculum, qrUrl, pageWidth, pageHeight, function(secondPageDataUrl) {
					if (secondPageDataUrl) {
						// Agregar la segunda página al PDF
						pdf.addPage();
						pdf.addImage(secondPageDataUrl, 'PNG', 0, 0, pageWidth, pageHeight, undefined, 'NONE');
					}
					// Guardar el PDF
					var timestamp = new Date().getTime();
					pdf.save('certificate-' + timestamp + '.pdf');
				});
			} else {
				// Guardar el PDF si no hay temario
				var timestamp = new Date().getTime();
				pdf.save('certificate-' + timestamp + '.pdf');
			}
		};
		img.onerror = function() {
			// Si hay error cargando la imagen, intentar de todas formas
			pdf.addImage(certImgSrc, 'PNG', 0, 0, pageWidth, pageHeight, undefined, 'NONE');
			
			if (curriculum && curriculum.length > 0) {
				// Usar SOLO el fondo original del certificado builder (sin variables)
				// NO usar la imagen del certificado generado porque ya tiene las variables llenadas
				generateSecondPageImage(backgroundUrl, curriculum, qrUrl, pageWidth, pageHeight, function(secondPageDataUrl) {
					if (secondPageDataUrl) {
						pdf.addPage();
						pdf.addImage(secondPageDataUrl, 'PNG', 0, 0, pageWidth, pageHeight, undefined, 'NONE');
					}
					var timestamp = new Date().getTime();
					pdf.save('certificate-' + timestamp + '.pdf');
				});
			} else {
				var timestamp = new Date().getTime();
				pdf.save('certificate-' + timestamp + '.pdf');
			}
		};
		img.src = certImgSrc;
	}

	/**
	 * Generar la segunda imagen (fondo del certificado + temario + QR) usando canvas
	 */
	function generateSecondPageImage(backgroundUrl, curriculum, qrUrl, pageWidth, pageHeight, callback) {
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

		// Cargar la imagen de fondo (fondo original del certificado builder o imagen del certificado)
		if (backgroundUrl) {
			var bgImg = new Image();
			bgImg.crossOrigin = 'anonymous';
			
			bgImg.onload = function() {
				// Dibujar el fondo
				ctx.drawImage(bgImg, 0, 0, pageWidth, pageHeight);
				
				// Agregar el temario y QR
				addCurriculumToCanvas(ctx, curriculum, qrUrl, pageWidth, pageHeight, function() {
					// Convertir el canvas a data URL
					var dataUrl = canvas.toDataURL('image/png');
					callback(dataUrl);
				});
			};
			
			bgImg.onerror = function() {
				// Si falla el fondo, usar fondo blanco
				console.warn('Error cargando imagen de fondo, usando fondo blanco');
				ctx.fillStyle = '#FFFFFF';
				ctx.fillRect(0, 0, pageWidth, pageHeight);
				addCurriculumToCanvas(ctx, curriculum, qrUrl, pageWidth, pageHeight, function() {
					var dataUrl = canvas.toDataURL('image/png');
					callback(dataUrl);
				});
			};
			
			bgImg.src = backgroundUrl;
		} else {
			// Si no hay fondo disponible, usar fondo blanco
			ctx.fillStyle = '#FFFFFF';
			ctx.fillRect(0, 0, pageWidth, pageHeight);
			addCurriculumToCanvas(ctx, curriculum, qrUrl, pageWidth, pageHeight, function() {
				var dataUrl = canvas.toDataURL('image/png');
				callback(dataUrl);
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
			// Verificar si necesitamos una nueva página (en este caso solo una página)
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
						return; // No agregar más si no cabe
					}

					// Solo mostrar el título de la lección
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
								return; // No agregar más si no cabe
							}
						} else {
							line = testLine;
						}
					}
					ctx.fillText(line, margin + 20, lineY);
					yPos = lineY + lineHeight;
				});
			}

			yPos += lineHeight * topicSpacing; // Espacio entre temas
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
	 * Generar código QR desde una URL
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

})(jQuery);

