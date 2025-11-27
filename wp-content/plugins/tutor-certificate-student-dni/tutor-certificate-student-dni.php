<?php
/**
 * Plugin Name: Tutor Certificate Student DNI
 * Plugin URI: https://example.com
 * Description: Agrega el campo DNI del estudiante al constructor de certificados de Tutor LMS sin modificar el plugin original
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tutor-certificate-student-dni
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase principal del plugin
 */
class Tutor_Certificate_Student_DNI {

	/**
	 * Instancia única del plugin
	 */
	private static $instance = null;

	/**
	 * Ruta del archivo main.min.js
	 */
	private $main_js_file = '';

	/**
	 * Ruta del archivo de backup
	 */
	private $backup_file = '';

	/**
	 * Ruta del archivo Ajax.php
	 */
	private $ajax_file = '';

	/**
	 * Ruta del archivo de backup de Ajax.php
	 */
	private $ajax_backup_file = '';

	/**
	 * Obtener instancia única
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->main_js_file = WP_PLUGIN_DIR . '/tutor-lms-certificate-builder/assets/editor/dist/main.min.js';
		$this->backup_file = WP_PLUGIN_DIR . '/tutor-certificate-student-dni/backup-main.min.js';
		$this->ajax_file = WP_PLUGIN_DIR . '/tutor-lms-certificate-builder/src/Ajax.php';
		$this->ajax_backup_file = WP_PLUGIN_DIR . '/tutor-certificate-student-dni/backup-Ajax.php';

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Inicializar el plugin
	 */
	public function init() {
		// Verificar que Tutor LMS Certificate Builder esté activo
		if ( ! $this->is_certificate_builder_active() ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_missing_plugin' ) );
			return;
		}

		// Agregar el elemento STUDENT_DNI al editor (ya está en Editor.php, pero lo verificamos)
		add_filter( 'tutor_certificate_builder_elements', array( $this, 'add_student_dni_element' ), 10, 1 );

		// Aplicar modificaciones al archivo JavaScript (verificar y aplicar cada vez)
		$this->apply_js_modifications();

		// Aplicar modificaciones al archivo Ajax.php (verificar y aplicar cada vez)
		$this->apply_ajax_modifications();

		// Agregar campo DNI en el perfil de usuario
		$this->init_user_profile_fields();
		
		// Agregar funcionalidad de búsqueda de certificados
		$this->init_certificate_search();
		
		// Agregar funcionalidad para generar dos páginas del certificado
		$this->init_two_page_certificate();
		
		// Cargar script para generar PDF con dos páginas
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_certificate_pdf_script' ) );
		
		// Agregar endpoint AJAX para obtener el temario del curso
		add_action( 'wp_ajax_tutor_get_course_curriculum', array( $this, 'ajax_get_course_curriculum' ) );
		add_action( 'wp_ajax_nopriv_tutor_get_course_curriculum', array( $this, 'ajax_get_course_curriculum' ) );
		
		// Crear/verificar la página de certificados en Tutor LMS
		// Solo verificar que la página configurada exista, no crear automáticamente
		add_action( 'admin_init', array( $this, 'ensure_certificate_page_exists' ), 5 );
		
		// Interceptar la visualización de certificados (igual que Tutor LMS)
		// Prioridad 5 para ejecutarse después de Tutor LMS (que usa sin prioridad o 10)
		add_filter( 'template_include', array( $this, 'view_certificate_template' ), 5 );
		
		// Interceptar antes de que WordPress genere el 404
		add_action( 'parse_request', array( $this, 'fix_certificate_page_404' ), 5 );
		
		// Interceptar el filtro de Tutor LMS para generar URLs de certificados (para QR y emisión)
		// Si hay una ruta configurada en el plugin, usarla. Si no, dejar que Tutor LMS use su ruta
		add_filter( 'tutor_certificate_public_url', array( $this, 'modify_certificate_public_url' ), 10, 1 );
		
		// Verificar periódicamente que el código esté insertado (solo en admin)
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'ensure_js_modifications' ), 5 );
			add_action( 'admin_init', array( $this, 'ensure_ajax_modifications' ), 5 );
		}
	}
	
	/**
	 * Asegurar que la página de certificados exista en Tutor LMS
	 * Si no existe, crearla automáticamente usando el mismo método que Tutor LMS
	 */
	public function ensure_certificate_page_exists() {
		// Solo ejecutar si Tutor LMS está activo
		if ( ! function_exists( 'tutor_utils' ) ) {
			return;
		}
		
		// Verificar si ya está configurada en Tutor LMS (igual que Tutor LMS lo hace)
		$tutor_certificate_page_id = (int) tutor_utils()->get_option( 'tutor_certificate_page', 0 );
		
		// Si ya existe y la página es válida, verificar también la página del plugin
		if ( $tutor_certificate_page_id > 0 ) {
			$page = get_post( $tutor_certificate_page_id );
			if ( $page && is_a( $page, 'WP_Post' ) && isset( $page->ID ) && $page->ID > 0 ) {
				// La página de Tutor LMS existe, pero aún necesitamos verificar la página del plugin
			}
		}
		
		// IMPORTANTE: NO crear ni sobrescribir la configuración del plugin automáticamente
		// Solo crear la página si NO hay ninguna configuración guardada y si el usuario lo necesita
		// Si el usuario ha guardado una ruta diferente, NO cambiarla
		$saved_url_base = get_option( 'certificate_search_url_base', '' );
		
		// Si ya hay una ruta guardada por el usuario, NO hacer nada - respetar su configuración
		if ( ! empty( $saved_url_base ) ) {
			// Hay una configuración guardada, verificar que la página existe
			$saved_page = get_page_by_path( $saved_url_base );
			if ( ! $saved_page || ! is_a( $saved_page, 'WP_Post' ) || ! isset( $saved_page->ID ) || $saved_page->ID <= 0 ) {
				// La página configurada no existe, crearla
				$page_data = array(
					'post_title'   => __( 'Certificado de Tutor', 'tutor-certificate-student-dni' ),
					'post_name'    => $saved_url_base,
					'post_content' => '',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				);
				
				$page_id = wp_insert_post( $page_data, true );
				if ( $page_id && ! is_wp_error( $page_id ) ) {
					update_option( 'certificate_search_page_id', $page_id, true );
					clean_post_cache( $page_id );
				}
			} else {
				// La página existe, asegurarse de que el ID esté guardado
				$current_page_id = get_option( 'certificate_search_page_id', 0 );
				if ( $current_page_id != $saved_page->ID ) {
					update_option( 'certificate_search_page_id', $saved_page->ID, true );
				}
			}
			// NO sobrescribir el valor guardado - salir aquí
			return;
		}
		
		// Si NO hay configuración guardada, NO crear ninguna página automáticamente
		// El usuario debe configurar la ruta manualmente en la página de configuración
		// Si quiere usar la ruta de Tutor LMS, simplemente deja el campo vacío
	}
	
	/**
	 * Modificar la URL pública del certificado generada por Tutor LMS
	 * Si hay una ruta configurada en el plugin, usarla. Si no, usar la ruta por defecto de Tutor LMS
	 * Este filtro se usa para QR codes, emisión de certificados, búsqueda, etc.
	 * 
	 * @param string $cert_hash Hash del certificado
	 * @return string URL del certificado
	 */
	public function modify_certificate_public_url( $cert_hash ) {
		if ( empty( $cert_hash ) ) {
			return '';
		}
		
		// LIMPIAR el cert_hash: si viene como URL completa, extraer solo el hash
		// Ejemplo: "http://localhost/wp/tutor-certificate-2?cert_hash=a16668720286f2ca" -> "a16668720286f2ca"
		$hash_clean = $cert_hash;
		
		// Si contiene "cert_hash=", extraer el valor después del igual
		if ( strpos( $hash_clean, 'cert_hash=' ) !== false ) {
			$parts = parse_url( $hash_clean );
			if ( isset( $parts['query'] ) ) {
				parse_str( $parts['query'], $query_params );
				if ( isset( $query_params['cert_hash'] ) ) {
					$hash_clean = $query_params['cert_hash'];
				}
			} else {
				// Si no tiene estructura de URL válida, intentar extraer después de "cert_hash="
				$hash_position = strpos( $hash_clean, 'cert_hash=' );
				if ( $hash_position !== false ) {
					$hash_clean = substr( $hash_clean, $hash_position + 10 ); // 10 = longitud de "cert_hash="
					// Limpiar cualquier parámetro adicional o carácter de URL
					$hash_clean = strtok( $hash_clean, '&' );
					$hash_clean = strtok( $hash_clean, '?' );
					$hash_clean = trim( $hash_clean, '/ ' );
				}
			}
		}
		
		// Si aún parece una URL completa (contiene http:// o https://), intentar extraer el hash de otra forma
		if ( strpos( $hash_clean, 'http://' ) === 0 || strpos( $hash_clean, 'https://' ) === 0 ) {
			// Es una URL completa, intentar extraer el hash del query string
			$url_parts = parse_url( $hash_clean );
			if ( isset( $url_parts['query'] ) ) {
				parse_str( $url_parts['query'], $params );
				if ( isset( $params['cert_hash'] ) ) {
					$hash_clean = $params['cert_hash'];
				} else {
					// Si no hay query string válido, buscar el último segmento que parezca un hash
					$path_parts = explode( '/', trim( $url_parts['path'], '/' ) );
					if ( ! empty( $path_parts ) ) {
						$last_part = end( $path_parts );
						// Un hash de certificado típicamente tiene 16-32 caracteres alfanuméricos
						if ( preg_match( '/^[a-zA-Z0-9]{16,32}$/', $last_part ) ) {
							$hash_clean = $last_part;
						}
					}
				}
			}
		}
		
		// Validar que el hash limpio tenga el formato esperado (al menos 16 caracteres alfanuméricos)
		if ( ! preg_match( '/^[a-zA-Z0-9]{16,}$/', $hash_clean ) ) {
			// Si no es un hash válido, intentar usar el original
			$hash_clean = $cert_hash;
		}
		
		// Verificar si hay una ruta configurada en el plugin
		$plugin_url_base = get_option( 'certificate_search_url_base', '' );
		$plugin_page_id = get_option( 'certificate_search_page_id', 0 );
		
		// Si hay una ruta configurada en el plugin, usar esa (para QR, emisión, búsqueda)
		if ( ! empty( $plugin_url_base ) ) {
			// Obtener el slug de la página del plugin
			$page_slug = $plugin_url_base;
			if ( $plugin_page_id > 0 ) {
				$page = get_post( $plugin_page_id );
				if ( $page && is_a( $page, 'WP_Post' ) && isset( $page->post_name ) && ! empty( $page->post_name ) ) {
					$page_slug = $page->post_name;
				}
			}
			
			$page_slug_clean = trim( $page_slug, '/' );
			$new_url = trailingslashit( home_url( '/' . $page_slug_clean . '/' ) );
			$new_url = add_query_arg( 'cert_hash', $hash_clean, $new_url );
			return $new_url;
		}
		
		// Si NO hay ruta configurada en el plugin, usar la ruta por defecto de Tutor LMS
		// Esto es igual que el método tutor_certificate_public_url() de Tutor LMS
		if ( function_exists( 'tutor_utils' ) ) {
			$page_id = (int) tutor_utils()->get_option( 'tutor_certificate_page', 0 );
			
			if ( ! in_array( $page_id, array( 0, -1 ) ) ) {
				$page = get_post( $page_id );
				if ( $page && is_a( $page, 'WP_Post' ) && isset( $page->post_name ) && ! empty( $page->post_name ) ) {
					$page_slug_clean = trim( $page->post_name, '/' );
					$default_url = trailingslashit( home_url( '/' . $page_slug_clean . '/' ) );
					$default_url = add_query_arg( 'cert_hash', $hash_clean, $default_url );
					return $default_url;
				}
			}
		}
		
		// Fallback: devolver URL por defecto con hash limpio
		return trailingslashit( home_url( '/' ) ) . '?cert_hash=' . $hash_clean;
	}
	
	/**
	 * Corregir el 404 antes de que WordPress lo muestre
	 * 
	 * @param WP_Query $wp_query Query de WordPress
	 */
	public function fix_certificate_page_404( $wp_query ) {
		if ( ! isset( $_GET['cert_hash'] ) || empty( $_GET['cert_hash'] ) ) {
			return;
		}
		
		$cert_hash = sanitize_text_field( $_GET['cert_hash'] );
		if ( empty( $cert_hash ) ) {
			return;
		}
		
		// Verificar si la URL coincide con nuestra página de certificados
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '';
		if ( empty( $request_uri ) ) {
			return;
		}
		
		$request_uri = trim( parse_url( $request_uri, PHP_URL_PATH ), '/' );
		$parts = explode( '/', $request_uri );
		$page_slug = ! empty( $parts ) ? end( $parts ) : '';
		$page_slug = strtok( $page_slug, '?' ); // Remover query string
		
		// Verificar si el slug coincide con nuestra página de certificados
		$certificate_url_base = get_option( 'certificate_search_url_base', 'certificado-de-tutor' );
		
		if ( ! empty( $page_slug ) && ( $page_slug === $certificate_url_base || $page_slug === 'certificado-de-tutor' ) ) {
			// Encontrar la página por slug
			$page = get_page_by_path( $page_slug );
			if ( $page && is_a( $page, 'WP_Post' ) && isset( $page->ID ) && $page->ID > 0 ) {
				// Configurar el query para que WordPress reconozca la página
				global $wp;
				$wp->query_vars['page_id'] = $page->ID;
				$wp->query_vars['pagename'] = $page_slug;
			}
		}
	}
	
	/**
	 * Interceptar la visualización del certificado (igual que Tutor LMS)
	 * Esto permite que la página configurada en el plugin muestre certificados igual que la página de Tutor LMS
	 * 
	 * @param string $template Template actual
	 * @return string Template a usar
	 */
	public function view_certificate_template( $template ) {
		// Solo funcionar si Tutor LMS Certificate está activo
		if ( ! function_exists( 'TUTOR_CERT' ) || ! class_exists( '\TUTOR_CERT\Certificate' ) ) {
			return $template;
		}
		
		// Obtener el cert_hash de la URL
		$cert_hash = isset( $_GET['cert_hash'] ) ? sanitize_text_field( $_GET['cert_hash'] ) : '';
		
		if ( empty( $cert_hash ) || ! empty( $_GET['tutor_action'] ) ) {
			return $template;
		}
		
		// Obtener el objeto Certificate de Tutor LMS
		$cert_obj = new \TUTOR_CERT\Certificate( true );
		
		// Verificar que el certificado existe y es válido (igual que Tutor LMS)
		$completed = $cert_obj->completed_course( $cert_hash );
		if ( ! is_object( $completed ) || ! property_exists( $completed, 'completed_user_id' ) ) {
			return $template;
		}
		
		// Verificar acceso al certificado (igual que Tutor LMS)
		$has_access = (bool) apply_filters( 'tutor_pro_certificate_access', true, $completed );
		if ( ! $has_access ) {
			return $template;
		}
		
		// Verificar si estamos en una página (igual que Tutor LMS)
		global $post, $wp_query;
		
		// Si hay un 404, intentar encontrar la página por slug
		if ( is_404() || ( isset( $wp_query->query_vars['pagename'] ) && ! empty( $wp_query->query_vars['pagename'] ) ) ) {
			$page_slug = isset( $wp_query->query_vars['pagename'] ) ? $wp_query->query_vars['pagename'] : '';
			
			// Si no hay pagename, intentar obtenerlo de la URL
			if ( empty( $page_slug ) && isset( $_SERVER['REQUEST_URI'] ) ) {
				$request_uri = sanitize_text_field( $_SERVER['REQUEST_URI'] );
				$request_uri = trim( parse_url( $request_uri, PHP_URL_PATH ), '/' );
				$parts = explode( '/', $request_uri );
				if ( ! empty( $parts ) ) {
					$page_slug = end( $parts );
					// Remover query string si existe
					$page_slug = strtok( $page_slug, '?' );
				}
			}
			
			// Verificar si el slug coincide con nuestra página de certificados
			$certificate_url_base = get_option( 'certificate_search_url_base', 'certificado-de-tutor' );
			
			if ( ! empty( $page_slug ) && ( $page_slug === $certificate_url_base || $page_slug === 'certificado-de-tutor' ) ) {
				// Encontrar la página por slug
				$page = get_page_by_path( $page_slug );
				if ( $page && is_a( $page, 'WP_Post' ) && isset( $page->ID ) && $page->ID > 0 ) {
					// Configurar el post global para que WordPress lo reconozca
					$post = $page;
					$wp_query->is_404 = false;
					$wp_query->is_page = true;
					$wp_query->is_singular = true;
					$wp_query->queried_object = $page;
					$wp_query->queried_object_id = $page->ID;
					
					// Usar el template de Tutor LMS
					set_transient( 'tutor_cert_hash', $cert_hash );
					$tutor_cert_path = TUTOR_CERT()->path . '/views/single-certificate.php';
					if ( file_exists( $tutor_cert_path ) ) {
						return $tutor_cert_path;
					}
				}
			}
		}
		
		// Verificar si ya tenemos un post cargado
		if ( isset( $post ) && is_a( $post, 'WP_Post' ) && 'page' === $post->post_type ) {
			// Verificar si es la página configurada en Tutor LMS
			$tutor_certificate_page_id = (int) tutor_utils()->get_option( 'tutor_certificate_page', 0 );
			
			// Si es la página de Tutor LMS, dejar que Tutor LMS la maneje (prioridad)
			if ( $post->ID === $tutor_certificate_page_id ) {
				return $template;
			}
			
			// Verificar si es una página configurada en el plugin
			$certificate_url_base = get_option( 'certificate_search_url_base', 'certificado-de-tutor' );
			
			// Verificar si el slug de la página actual coincide
			$should_show_certificate = false;
			
			if ( isset( $post->post_name ) ) {
				// Verificar por slug directo
				if ( $post->post_name === $certificate_url_base || $post->post_name === 'certificado-de-tutor' ) {
					$should_show_certificate = true;
				}
				
				// Verificar por ID de página si tenemos la opción configurada
				$plugin_page_id = get_option( 'certificate_search_page_id', 0 );
				if ( ! $should_show_certificate && $plugin_page_id > 0 && $post->ID === (int) $plugin_page_id ) {
					$should_show_certificate = true;
				}
			}
			
			if ( $should_show_certificate ) {
				// Es una página válida para mostrar certificados, usar el template de Tutor LMS
				set_transient( 'tutor_cert_hash', $cert_hash );
				$tutor_cert_path = TUTOR_CERT()->path . '/views/single-certificate.php';
				if ( file_exists( $tutor_cert_path ) ) {
					return $tutor_cert_path;
				}
			}
		}
		
		return $template;
	}
	
	/**
	 * Endpoint AJAX para obtener el temario del curso
	 */
	public function ajax_get_course_curriculum() {
		$cert_hash = isset( $_GET['cert_hash'] ) ? sanitize_text_field( $_GET['cert_hash'] ) : '';
		
		if ( empty( $cert_hash ) ) {
			wp_send_json_error( array( 'message' => __( 'Cert hash requerido', 'tutor-certificate-student-dni' ) ) );
			return;
		}
		
		// Obtener datos de finalización del curso
		if ( ! function_exists( 'TUTOR_CERT' ) || ! class_exists( '\TUTOR_CERT\Certificate' ) ) {
			wp_send_json_error( array( 'message' => __( 'Certificate plugin no disponible', 'tutor-certificate-student-dni' ) ) );
			return;
		}
		
		$cert_obj = new \TUTOR_CERT\Certificate( true );
		$completed = apply_filters( 'tutor_certificate_completion_data', $cert_hash );
		
		if ( ! is_object( $completed ) || ! property_exists( $completed, 'course_id' ) ) {
			wp_send_json_error( array( 'message' => __( 'Certificado no válido', 'tutor-certificate-student-dni' ) ) );
			return;
		}
		
		// Obtener el temario del curso (topics y sus contenidos)
		$course_curriculum = array();
		if ( function_exists( 'tutor_utils' ) ) {
			$topics = tutor_utils()->get_topics( $completed->course_id );
			if ( $topics && $topics->have_posts() ) {
				while ( $topics->have_posts() ) {
					$topics->the_post();
					$topic_id = get_the_ID();
					$topic_title = get_the_title();
					
					// Obtener solo las lecciones (no quizzes ni tareas)
					$topic_contents = tutor_utils()->get_course_contents_by_topic( $topic_id, -1 );
					$contents = array();
					
					if ( $topic_contents && $topic_contents->have_posts() ) {
						while ( $topic_contents->have_posts() ) {
							$topic_contents->the_post();
							$post_type = get_post_type();
							// Solo incluir lecciones (lesson)
							if ( $post_type === 'lesson' ) {
								$contents[] = array(
									'title' => get_the_title(),
									'type' => $post_type,
								);
							}
						}
						wp_reset_postdata();
					}
					
					$course_curriculum[] = array(
						'topic_title' => $topic_title,
						'contents' => $contents,
					);
				}
				wp_reset_postdata();
			}
		}
		
		// Obtener el QR URL del certificado
		$qr_url = apply_filters( 'tutor_certificate_public_url', $cert_hash );
		
		// Obtener la URL del fondo del template del certificado (ANTES de renderizar variables)
		$background_url = '';
		
		// PRIORIDAD 1: Verificar si hay un fondo personalizado configurado
		$custom_background_url = get_option( 'certificate_second_page_background_url', '' );
		if ( ! empty( $custom_background_url ) ) {
			$background_url = $custom_background_url;
		}
		
		// PRIORIDAD 2: Si no hay fondo personalizado, usar el fondo del certificado builder
		if ( empty( $background_url ) && function_exists( 'TUTOR_CERT' ) ) {
			$cert_obj = new \TUTOR_CERT\Certificate( true );
			$template = $cert_obj->get_course_certificate_template( $completed->course_id );
			
			if ( $template ) {
				// Verificar si es un certificado del builder (tutor_cb_XXX)
				$template_key = isset( $template['key'] ) ? $template['key'] : '';
				
				if ( strpos( $template_key, 'tutor_cb_' ) === 0 ) {
					// Es un certificado del builder, obtener la imagen de fondo del canvas
					$certificate_id = str_replace( 'tutor_cb_', '', $template_key );
					$certificate_id = (int) $certificate_id;
					
					if ( $certificate_id > 0 ) {
						// Función auxiliar para convertir URL a absoluta
						$convert_to_absolute_url = function( $url ) {
							if ( empty( $url ) || ! is_string( $url ) ) {
								return '';
							}
							
							// Si ya es una URL absoluta, retornarla
							if ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
								return $url;
							}
							
							// Si es una URL relativa que empieza con /, usar home_url
							if ( strpos( $url, '/' ) === 0 ) {
								return home_url( $url );
							}
							
							// Si es una URL relativa sin /, puede ser una ruta de WordPress
							// Intentar obtener la URL del attachment si es un ID numérico
							if ( is_numeric( $url ) ) {
								$attachment_url = wp_get_attachment_url( (int) $url );
								if ( $attachment_url ) {
									return $attachment_url;
								}
							}
							
							// Si contiene wp-content, puede ser una ruta relativa
							if ( strpos( $url, 'wp-content' ) !== false ) {
								return home_url( '/' . ltrim( $url, '/' ) );
							}
							
							// Si contiene uploads, puede ser una ruta relativa
							if ( strpos( $url, 'uploads' ) !== false ) {
								$upload_dir = wp_upload_dir();
								return $upload_dir['baseurl'] . '/' . ltrim( str_replace( $upload_dir['basedir'], '', $url ), '/' );
							}
							
							// Por defecto, intentar con home_url
							return home_url( '/' . ltrim( $url, '/' ) );
						};
						
						// Función auxiliar para procesar los datos del certificado
						$process_certificate_data = function( $certificate_data ) use ( $convert_to_absolute_url ) {
							$background_url = '';
							
							if ( ! $certificate_data ) {
								return $background_url;
							}
							
							$cert_data = is_serialized( $certificate_data ) ? unserialize( $certificate_data ) : json_decode( $certificate_data, true );
							
							if ( ! $cert_data || ! is_array( $cert_data ) ) {
								return $background_url;
							}
							
							// PRIORIDAD 1: Buscar en canvas.backdrop.value (fondo principal del template)
							if ( isset( $cert_data['canvas']['backdrop']['value'] ) && ! empty( $cert_data['canvas']['backdrop']['value'] ) ) {
								$bg_image = $cert_data['canvas']['backdrop']['value'];
								$background_url = $convert_to_absolute_url( $bg_image );
								if ( ! empty( $background_url ) ) {
									return $background_url;
								}
							}
							
							// PRIORIDAD 2: Buscar en canvas.settings.backgroundImage
							if ( isset( $cert_data['canvas']['settings']['backgroundImage'] ) && ! empty( $cert_data['canvas']['settings']['backgroundImage'] ) ) {
								$bg_image = $cert_data['canvas']['settings']['backgroundImage'];
								$background_url = $convert_to_absolute_url( $bg_image );
								if ( ! empty( $background_url ) ) {
									return $background_url;
								}
							}
								
							// PRIORIDAD 3: Buscar en canvas.objects la imagen de fondo
							// Buscar imágenes que cubran todo el canvas (son el fondo)
							if ( empty( $background_url ) && isset( $cert_data['canvas']['objects'] ) && is_array( $cert_data['canvas']['objects'] ) ) {
									$canvas_width = isset( $cert_data['canvas']['settings']['width'] ) ? (int) $cert_data['canvas']['settings']['width'] : 800;
									$canvas_height = isset( $cert_data['canvas']['settings']['height'] ) ? (int) $cert_data['canvas']['settings']['height'] : 600;
									
									$background_obj = null;
									$lowest_z_index = null;
									
									// Primero: buscar imágenes que cubran todo el canvas y estén en (0,0) o cerca
									// Estas son las imágenes de fondo (no tienen texto ni variables)
									foreach ( $cert_data['canvas']['objects'] as $obj ) {
										if ( isset( $obj['type'] ) && $obj['type'] === 'image' && isset( $obj['src'] ) ) {
											// Ignorar objetos que no sean imágenes puras (pueden tener texto o variables)
											// Solo buscar imágenes que sean el fondo
											$obj_width = isset( $obj['width'] ) ? (int) $obj['width'] : 0;
											$obj_height = isset( $obj['height'] ) ? (int) $obj['height'] : 0;
											$obj_left = isset( $obj['left'] ) ? (int) $obj['left'] : 0;
											$obj_top = isset( $obj['top'] ) ? (int) $obj['top'] : 0;
											$z_index = isset( $obj['zIndex'] ) ? (int) $obj['zIndex'] : 0;
											
											// Buscar imágenes que cubran todo el canvas (son el fondo)
											// Debe estar en (0,0) o muy cerca y cubrir todo el canvas
											// Y debe ser una imagen (no un texto con variables)
											$covers_canvas = ( $obj_left <= 10 && $obj_top <= 10 && 
											                  $obj_width >= ( $canvas_width * 0.95 ) && 
											                  $obj_height >= ( $canvas_height * 0.95 ) );
											
											if ( $covers_canvas ) {
												// Si cubre el canvas, preferir la que tenga el z-index más bajo
												// (las imágenes de fondo suelen tener el z-index más bajo)
												if ( $lowest_z_index === null || $z_index < $lowest_z_index ) {
													$lowest_z_index = $z_index;
													$background_obj = $obj;
												}
											}
										}
									}
									
									// Si no se encontró una imagen que cubra todo el canvas, buscar la de z-index más bajo
									// (puede ser que la imagen de fondo no cubra exactamente todo el canvas)
									if ( ! $background_obj ) {
										foreach ( $cert_data['canvas']['objects'] as $obj ) {
											if ( isset( $obj['type'] ) && $obj['type'] === 'image' && isset( $obj['src'] ) ) {
												$z_index = isset( $obj['zIndex'] ) ? (int) $obj['zIndex'] : 0;
												
												// Buscar la imagen con el z-index más bajo (está al fondo)
												// Esta debería ser la imagen de fondo original
												if ( $lowest_z_index === null || $z_index < $lowest_z_index ) {
													$lowest_z_index = $z_index;
													$background_obj = $obj;
												}
											}
										}
									}
									
								if ( $background_obj && isset( $background_obj['src'] ) ) {
									$bg_src = $background_obj['src'];
									// Convertir a URL absoluta y asegurarse de que sea la imagen original
									$background_url = $convert_to_absolute_url( $bg_src );
									
									// Verificar que la URL no contenga parámetros de renderizado
									// (algunos sistemas agregan parámetros como ?render=true)
									if ( strpos( $background_url, '?' ) !== false ) {
										$background_url = strtok( $background_url, '?' );
									}
								}
							}
							
							return $background_url;
						};
						
						// Primero intentar obtener de tutor_certificate_data (publicado)
						$certificate_data = get_post_meta( $certificate_id, 'tutor_certificate_data', true );
						$background_url = $process_certificate_data( $certificate_data );
						
						// Si no se encuentra, intentar con tutor_certificate_draft_data (borrador)
						if ( empty( $background_url ) ) {
							$certificate_draft_data = get_post_meta( $certificate_id, 'tutor_certificate_draft_data', true );
							$background_url = $process_certificate_data( $certificate_draft_data );
						}
					}
				} else {
					// Es un template tradicional, usar background.png
					if ( isset( $template['background_src'] ) && ! empty( $template['background_src'] ) ) {
						$background_url = $template['background_src'];
					} elseif ( isset( $template['url'] ) ) {
						// Si no hay background_src, usar la URL del template + background.png
						$background_url = $template['url'] . 'background.png';
					}
				}
			}
		}
		
		wp_send_json_success( array( 
			'COURSE_CURRICULUM' => $course_curriculum,
			'QR_URL' => $qr_url,
			'BACKGROUND_URL' => $background_url
		) );
	}

	/**
	 * Inicializar funcionalidad de dos páginas del certificado
	 */
	public function init_two_page_certificate() {
		// Interceptar cuando se guarda la imagen del certificado para generar también la segunda página
		add_action( 'wp_ajax_tutor_store_certificate_image', array( $this, 'store_second_page_image' ), 5 );
		add_action( 'wp_ajax_nopriv_tutor_store_certificate_image', array( $this, 'store_second_page_image' ), 5 );
		
		// Encolar script para generar dos imágenes del certificado
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_two_page_certificate_script' ) );
		
		// Agregar endpoint AJAX para guardar la segunda página
		add_action( 'wp_ajax_tutor_store_certificate_second_page', array( $this, 'ajax_store_second_page' ) );
		add_action( 'wp_ajax_nopriv_tutor_store_certificate_second_page', array( $this, 'ajax_store_second_page' ) );
		
		// Agregar la segunda página a la vista del certificado usando output buffering
		add_action( 'wp_footer', array( $this, 'display_second_page_script' ) );
	}
	
	/**
	 * Agregar script para mostrar la segunda página del certificado
	 */
	public function display_second_page_script() {
		// Solo en la página de certificados
		if ( ! isset( $_GET['cert_hash'] ) || empty( $_GET['cert_hash'] ) ) {
			return;
		}
		
		$cert_hash = sanitize_text_field( $_GET['cert_hash'] );
		
		if ( ! function_exists( 'TUTOR_CERT' ) ) {
			return;
		}
		
		$cert_obj = new \TUTOR_CERT\Certificate( true );
		$completed = $cert_obj->completed_course( $cert_hash );
		
		if ( ! $completed ) {
			return;
		}
		
		$upload_dir = wp_upload_dir();
		$certificate_dir_url = $upload_dir['baseurl'] . '/' . $cert_obj->certificates_dir_name;
		$rand_string = get_comment_meta( $completed->certificate_id, $cert_obj->certificate_stored_key, true );
		
		if ( empty( $rand_string ) ) {
			return;
		}
		
		$second_page_path = '/' . $rand_string . '-' . $cert_hash . '-page2.jpg';
		$second_page_url = $certificate_dir_url . $second_page_path;
		$second_page_file = $upload_dir['basedir'] . '/' . $cert_obj->certificates_dir_name . $second_page_path;
		
		// Pasar datos al JavaScript
		?>
		<script>
		jQuery(document).ready(function($) {
			var certHash = '<?php echo esc_js( $cert_hash ); ?>';
			var secondPageUrl = '<?php echo esc_js( $second_page_url ); ?>';
			var secondPageExists = <?php echo file_exists( $second_page_file ) ? 'true' : 'false'; ?>;
			
			// Función para mostrar la segunda página
			function showSecondPage() {
				var $certContainer = $('#tutor-pro-certificate-preview').closest('.tutor-certificate-demo');
				if (!$certContainer.length) {
					// Intentar encontrar el contenedor de otra forma
					$certContainer = $('.tutor-certificate-demo').first();
				}
				
				if ($certContainer.length) {
					// Verificar que no se haya agregado ya
					if ($certContainer.next('.tutor-certificate-demo[data-second-page]').length === 0) {
						var secondPageHtml = '<div class="tutor-certificate-demo tutor-pb-44 tutor-mt-24" data-second-page="1"><span class="tutor-dc-demo-img"><img src="' + secondPageUrl + '" alt="Temario del Curso" style="width:100%;max-width:100%;height:auto;" onerror="this.style.display=\'none\'" /></span></div>';
						$certContainer.after(secondPageHtml);
					}
				}
			}
			
			// Si la segunda página existe, mostrarla inmediatamente
			if (secondPageExists) {
				showSecondPage();
			} else {
				// Si no existe, verificar periódicamente si se genera
				var checkInterval = setInterval(function() {
					var testImg = new Image();
					testImg.onload = function() {
						secondPageExists = true;
						clearInterval(checkInterval);
						showSecondPage();
					};
					testImg.onerror = function() {
						// No existe aún, continuar verificando
					};
					testImg.src = secondPageUrl;
				}, 2000);
				
				// Limpiar después de 60 segundos
				setTimeout(function() {
					clearInterval(checkInterval);
				}, 60000);
			}
		});
		</script>
		<?php
	}

	/**
	 * Interceptar el guardado de la imagen del certificado para generar también la segunda página
	 */
	public function store_second_page_image() {
		// No hacer nada aquí, solo dejar que el proceso normal continúe
		// La segunda página se generará desde JavaScript
	}

	/**
	 * Endpoint AJAX para guardar la segunda página del certificado
	 */
	public function ajax_store_second_page() {
		// Verificar nonce
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'tutor-certificate-two-pages' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'tutor-certificate-student-dni' ) ) );
			return;
		}
		
		$hash = sanitize_text_field( $_POST['cert_hash'] ?? '' );
		if ( empty( $hash ) ) {
			wp_send_json_error( array( 'message' => __( 'Certificate hash is required', 'tutor-certificate-student-dni' ) ) );
			return;
		}
		
		$completed = function_exists( 'TUTOR_CERT' ) ? ( new \TUTOR_CERT\Certificate( true ) )->completed_course( $hash ) : null;
		
		if ( ! $completed ) {
			wp_send_json_error( array( 'message' => __( 'Course not yet completed', 'tutor-pro' ) ) );
			return;
		}
		
		// Verificar si hay una imagen
		if ( ! isset( $_FILES['certificate_image_page2'] ) || $_FILES['certificate_image_page2']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => __( 'Certificate Image Error: ' . ( $_FILES['certificate_image_page2']['error'] ?? 'No file uploaded' ), 'tutor-certificate-student-dni' ) ) );
			return;
		}
		
		// Verificar el tipo de archivo (puede ser image/jpeg o image/png)
		$file_type = $_FILES['certificate_image_page2']['type'];
		if ( ! in_array( $file_type, array( 'image/jpeg', 'image/jpg', 'image/png' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Expected JPEG or PNG.', 'tutor-certificate-student-dni' ) ) );
			return;
		}
		
		$certificates_dir = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'tutor-certificates';
		$rand_string = get_comment_meta( $completed->certificate_id, 'tutor_certificate_has_image', true );
		
		if ( empty( $rand_string ) ) {
			wp_send_json_error( array( 'message' => __( 'First page not generated yet', 'tutor-pro' ) ) );
			return;
		}
		
		// Asegurar que el directorio existe
		if ( ! wp_mkdir_p( $certificates_dir ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not create certificates directory', 'tutor-certificate-student-dni' ) ) );
			return;
		}
		
		$file_dest = $certificates_dir . DIRECTORY_SEPARATOR . $rand_string . '-' . $hash . '-page2.jpg';
		
		// Si el archivo ya existe, eliminarlo primero
		if ( file_exists( $file_dest ) ) {
			@unlink( $file_dest );
		}
		
		// Mover el archivo
		if ( ! move_uploaded_file( $_FILES['certificate_image_page2']['tmp_name'], $file_dest ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save certificate image', 'tutor-certificate-student-dni' ) ) );
			return;
		}
		
		// Verificar que el archivo se guardó correctamente
		if ( ! file_exists( $file_dest ) ) {
			wp_send_json_error( array( 'message' => __( 'File was not saved correctly', 'tutor-certificate-student-dni' ) ) );
			return;
		}
		
		wp_send_json_success( array( 
			'message' => __( 'Second page saved successfully', 'tutor-certificate-student-dni' ),
			'file_url' => wp_upload_dir()['baseurl'] . '/tutor-certificates/' . $rand_string . '-' . $hash . '-page2.jpg'
		) );
	}

	/**
	 * Encolar script para generar dos páginas del certificado
	 */
	public function enqueue_two_page_certificate_script() {
		// Solo cargar en la página de certificados
		if ( ! isset( $_GET['cert_hash'] ) || empty( $_GET['cert_hash'] ) ) {
			return;
		}
		
		// Verificar que existe el elemento del certificado en la página
		// Esto asegura que estamos en una página de certificado
		$cert_hash = sanitize_text_field( $_GET['cert_hash'] );
		if ( empty( $cert_hash ) ) {
			return;
		}
		
		$script_url = plugin_dir_url( __FILE__ ) . 'assets/js/certificate-two-pages-generator.js';
		wp_enqueue_script(
			'tutor-certificate-two-pages-generator',
			$script_url,
			array( 'jquery' ),
			'1.0.1',
			true
		);
		
		// Pasar datos al JavaScript
		wp_localize_script( 'tutor-certificate-two-pages-generator', 'tutorTwoPagesCert', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'tutor-certificate-two-pages' ),
			'cert_hash' => $cert_hash,
		) );
	}

	/**
	 * Cargar script para generar PDF con dos páginas
	 */
	public function enqueue_certificate_pdf_script() {
		// Solo cargar en la página de certificados
		if ( ! isset( $_GET['cert_hash'] ) || empty( $_GET['cert_hash'] ) ) {
			return;
		}

		$script_url = plugin_dir_url( __FILE__ ) . 'assets/js/certificate-pdf-two-pages.js';
		wp_enqueue_script(
			'tutor-certificate-pdf-two-pages',
			$script_url,
			array( 'jquery' ),
			'1.0.0',
			true
		);
		
		// Asegurar que se ejecute después de html-to-image.js
		global $wp_scripts;
		if ( isset( $wp_scripts->registered['html-to-image'] ) ) {
			$wp_scripts->registered['tutor-certificate-pdf-two-pages']->deps[] = 'html-to-image';
		}
	}

	/**
	 * Inicializar funcionalidad de búsqueda de certificados
	 */
	private function init_certificate_search() {
		// Agregar menú de administración para configuración
		add_action( 'admin_menu', array( $this, 'add_certificate_search_menu' ) );
		
		// Agregar shortcode para el formulario de búsqueda
		add_shortcode( 'buscar_certificado_dni', array( $this, 'certificate_search_shortcode' ) );
		
		// Insertar CSS personalizado en el frontend
		add_action( 'wp_head', array( $this, 'insert_certificate_search_css' ) );
		
		// Insertar CSS personalizado en el admin para la vista previa
		add_action( 'admin_head', array( $this, 'insert_certificate_search_css' ) );
	}
	
	/**
	 * Agregar menú de administración para configuración de búsqueda de certificados
	 */
	public function add_certificate_search_menu() {
		add_menu_page(
			__( 'Gestión de Certificados DNI', 'tutor-certificate-student-dni' ), // Título de la página
			__( 'Certificados DNI', 'tutor-certificate-student-dni' ), // Texto del menú
			'manage_options', // Capacidad requerida
			'certificate-search-dni', // Slug del menú
			array( $this, 'certificate_search_settings_page' ), // Función de contenido
			'dashicons-awards', // Ícono del menú (Dashicon de certificado)
			2 // Posición en el menú
		);
	}
	
	/**
	 * Página de configuración de búsqueda de certificados
	 */
	public function certificate_search_settings_page() {
		$settings_saved = false;
		$error_message = '';
		
		// Guardar la configuración si se envió el formulario
		if ( isset( $_POST['submit'] ) && check_admin_referer( 'certificate_search_settings' ) ) {
			// Guardar URL base del certificado (siempre procesar, incluso si está vacío)
			$url_base = isset( $_POST['certificate_url_base'] ) ? sanitize_text_field( $_POST['certificate_url_base'] ) : '';
			$url_base = trim( $url_base, ' /' ); // Limpiar barras y espacios
			
			// Guardar la URL base SIEMPRE (incluso si está vacía, para que el usuario pueda limpiar la configuración)
			// Usar update_option que siempre guarda, incluso si el valor es el mismo
			update_option( 'certificate_search_url_base', $url_base, true ); // El tercer parámetro fuerza el guardado
			
			// Si se proporcionó una URL base, buscar o crear la página
			if ( ! empty( $url_base ) ) {
				// Buscar la página existente por slug
				$page = get_page_by_path( $url_base );
				
				if ( $page && is_a( $page, 'WP_Post' ) && isset( $page->ID ) && $page->ID > 0 ) {
					// La página existe, guardar su ID
					update_option( 'certificate_search_page_id', $page->ID, true );
					$settings_saved = true;
				} else {
					// La página no existe, intentar crearla
					$page_data = array(
						'post_title'   => __( 'Certificado de Tutor', 'tutor-certificate-student-dni' ),
						'post_name'    => $url_base,
						'post_content' => '',
						'post_status'  => 'publish',
						'post_type'    => 'page',
					);
					
					$page_id = wp_insert_post( $page_data, true );
					
					if ( $page_id && ! is_wp_error( $page_id ) ) {
						update_option( 'certificate_search_page_id', $page_id, true );
						clean_post_cache( $page_id );
						wp_cache_flush();
						$settings_saved = true;
					} else {
						$error_message = __( 'Error al crear la página. Verifica que el slug no esté en uso.', 'tutor-certificate-student-dni' );
						if ( is_wp_error( $page_id ) ) {
							$error_message .= ' ' . $page_id->get_error_message();
						}
					}
				}
			} else {
				// Si está vacío, limpiar también el ID de la página
				update_option( 'certificate_search_page_id', 0, true );
				$settings_saved = true;
			}
			
			// Guardar CSS personalizado si se envió
			if ( isset( $_POST['certificate_search_css'] ) ) {
				update_option( 'certificate_search_custom_css', wp_unslash( $_POST['certificate_search_css'] ), true );
			}
			
			// Procesar subida de fondo personalizado para la segunda página
			if ( ! empty( $_FILES['certificate_second_page_background']['name'] ) ) {
				// Verificar que es una imagen
				$file_type = wp_check_filetype( $_FILES['certificate_second_page_background']['name'] );
				$allowed_types = array( 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif' );
				
				if ( in_array( $file_type['type'], $allowed_types ) ) {
					// Usar la función de WordPress para subir el archivo
					require_once( ABSPATH . 'wp-admin/includes/file.php' );
					require_once( ABSPATH . 'wp-admin/includes/image.php' );
					
					$upload_overrides = array( 'test_form' => false );
					$uploaded_file = wp_handle_upload( $_FILES['certificate_second_page_background'], $upload_overrides );
					
					if ( $uploaded_file && ! isset( $uploaded_file['error'] ) ) {
						// Guardar la URL de la imagen
						update_option( 'certificate_second_page_background_url', $uploaded_file['url'], true );
						$settings_saved = true;
					} else {
						$error_message = isset( $uploaded_file['error'] ) ? $uploaded_file['error'] : __( 'Error al subir la imagen de fondo.', 'tutor-certificate-student-dni' );
					}
				} else {
					$error_message = __( 'El archivo debe ser una imagen (JPG, PNG o GIF).', 'tutor-certificate-student-dni' );
				}
			}
			
			// Si se envió el campo para eliminar el fondo
			if ( isset( $_POST['remove_certificate_background'] ) && $_POST['remove_certificate_background'] === '1' ) {
				delete_option( 'certificate_second_page_background_url' );
				$settings_saved = true;
			}
			
			// Mostrar mensaje de éxito o error
			if ( $settings_saved && empty( $error_message ) ) {
				echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( '✅ Configuración guardada correctamente.', 'tutor-certificate-student-dni' ) . '</strong></p></div>';
				
				// Mostrar la URL que se guardó
				if ( ! empty( $url_base ) ) {
					$example_url = trailingslashit( home_url( '/' . $url_base . '/' ) ) . '?cert_hash=XXXXX';
					echo '<div class="notice notice-info is-dismissible"><p>';
					echo '<strong>' . esc_html__( '✅ Ruta guardada:', 'tutor-certificate-student-dni' ) . '</strong> ';
					echo '<code>' . esc_html( $example_url ) . '</code>';
					echo '</p></div>';
					echo '<div class="notice notice-success is-dismissible"><p>';
					echo '<strong>' . esc_html__( 'ℹ️ IMPORTANTE:', 'tutor-certificate-student-dni' ) . '</strong> ';
					echo esc_html__( 'Esta ruta se usará para TODOS los enlaces de certificados en el listado de búsqueda. Puedes cambiar esta ruta en cualquier momento.', 'tutor-certificate-student-dni' );
					echo '</p></div>';
				}
				
				// Limpiar caché para que los cambios se reflejen inmediatamente
				wp_cache_flush();
				delete_transient( 'certificate_search_url_base' ); // Eliminar cualquier transient relacionado
			} elseif ( ! empty( $error_message ) ) {
				echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( '❌ Error:', 'tutor-certificate-student-dni' ) . '</strong> ' . esc_html( $error_message ) . '</p></div>';
			}
		}

		// Obtener valores actuales guardados (SIEMPRE usar la configuración guardada del plugin)
		// NO sobrescribir con valores de Tutor LMS - solo usar lo que el usuario guardó explícitamente
		// El campo debe iniciar VACÍO por defecto
		$certificate_url_base = get_option( 'certificate_search_url_base', '' );
		$certificate_page_id = get_option( 'certificate_search_page_id', 0 );
		
		// IMPORTANTE: No sobrescribir el valor guardado si existe
		// El campo debe iniciar VACÍO por defecto
		// Si está vacío, NO llenarlo automáticamente - dejar que el usuario decida
		// Cuando está vacío, se usará la ruta de Tutor LMS por defecto en las funciones
		
		$certificate_custom_css = get_option( 'certificate_search_custom_css', $this->get_default_certificate_search_css() );
		$certificate_background_url = get_option( 'certificate_second_page_background_url', '' );
		
		// Obtener información de la página de certificados de Tutor LMS
		$tutor_certificate_page_id = 0;
		$tutor_certificate_page_url = '';
		if ( function_exists( 'tutor_utils' ) ) {
			$tutor_certificate_page_id = (int) tutor_utils()->get_option( 'tutor_certificate_page', 0 );
			if ( $tutor_certificate_page_id > 0 ) {
				$page = get_post( $tutor_certificate_page_id );
				// Verificar que la página existe y tiene un slug válido
				// NO usar get_permalink() para evitar errores si la página no existe
				if ( $page && is_a( $page, 'WP_Post' ) && isset( $page->ID ) && $page->ID > 0 && isset( $page->post_name ) && ! empty( $page->post_name ) ) {
					// Construir la URL directamente usando home_url() con el slug (más seguro)
					$tutor_certificate_page_url = trailingslashit( home_url( '/' . $page->post_name . '/' ) );
				}
			}
		}
		
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Configuración de Certificados', 'tutor-certificate-student-dni' ); ?></h1>
			<form method="POST" action="" enctype="multipart/form-data">
				<?php wp_nonce_field( 'certificate_search_settings' ); ?>
				<h2><?php esc_html_e( 'Configuración General', 'tutor-certificate-student-dni' ); ?></h2>
				
				<?php if ( $tutor_certificate_page_id > 0 && ! empty( $tutor_certificate_page_url ) ) : ?>
					<div class="notice notice-success" style="margin: 15px 0;">
						<p>
							<strong><?php esc_html_e( '✅ Página de Certificados de Tutor LMS detectada:', 'tutor-certificate-student-dni' ); ?></strong><br>
							<?php esc_html_e( 'Página ID:', 'tutor-certificate-student-dni' ); ?> <strong><?php echo esc_html( $tutor_certificate_page_id ); ?></strong><br>
							<?php esc_html_e( 'Slug de la página:', 'tutor-certificate-student-dni' ); ?> <strong><?php 
								$page_detected = get_post( $tutor_certificate_page_id );
								if ( $page_detected && isset( $page_detected->post_name ) ) {
									echo esc_html( $page_detected->post_name );
								}
							?></strong><br>
							<?php esc_html_e( 'URL completa:', 'tutor-certificate-student-dni' ); ?> 
							<a href="<?php echo esc_url( $tutor_certificate_page_url . '?cert_hash=test' ); ?>" target="_blank"><?php echo esc_html( $tutor_certificate_page_url ); ?></a>
						</p>
						<p>
							<em><?php esc_html_e( 'El plugin usará automáticamente esta página para los enlaces de certificados. No necesitas configurar nada más.', 'tutor-certificate-student-dni' ); ?></em>
						</p>
					</div>
				<?php else : ?>
					<div class="notice notice-error" style="margin: 15px 0;">
						<p>
							<strong><?php esc_html_e( '⚠️ IMPORTANTE:', 'tutor-certificate-student-dni' ); ?></strong> 
							<?php esc_html_e( 'No se detectó una página de certificados de Tutor LMS configurada.', 'tutor-certificate-student-dni' ); ?>
						</p>
						<p>
							<strong><?php esc_html_e( 'Pasos para configurar:', 'tutor-certificate-student-dni' ); ?></strong>
						</p>
						<ol style="margin-left: 20px;">
							<li><?php esc_html_e( 'Ve a Tutor LMS > Settings > Certificates y configura la página de certificados.', 'tutor-certificate-student-dni' ); ?></li>
							<li><?php esc_html_e( 'O crea una página nueva con el slug "certificado-de-tutor" o el que prefieras.', 'tutor-certificate-student-dni' ); ?></li>
							<li><?php esc_html_e( 'Configura el slug de la página abajo en "URL Base del Certificado".', 'tutor-certificate-student-dni' ); ?></li>
						</ol>
					</div>
				<?php endif; ?>
				
				<label for="certificate_url_base"><strong><?php esc_html_e( 'URL Base del Certificado (Ruta del Plugin):', 'tutor-certificate-student-dni' ); ?></strong></label>
				<input type="text" id="certificate_url_base" name="certificate_url_base" value="<?php echo esc_attr( $certificate_url_base ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Dejar vacío para usar la ruta de Tutor LMS', 'tutor-certificate-student-dni' ); ?>" style="min-width: 300px;" />
				<p class="description">
					<strong><?php esc_html_e( '📋 Instrucciones:', 'tutor-certificate-student-dni' ); ?></strong><br>
					<?php esc_html_e( '• <strong>Dejar vacío:</strong> Usará automáticamente la ruta de Tutor LMS configurada (por defecto: tutor-certificate-2)', 'tutor-certificate-student-dni' ); ?><br>
					<?php esc_html_e( '• <strong>Configurar una ruta:</strong> Se usará esa ruta para búsqueda, emisión de certificados y actualización de QR', 'tutor-certificate-student-dni' ); ?><br><br>
					<?php esc_html_e( 'Ingresa solo el slug de la página (sin barras ni prefijos). Por ejemplo: "mi-certificado-custom".', 'tutor-certificate-student-dni' ); ?><br>
					<?php esc_html_e( 'El plugin construirá automáticamente la URL completa respetando la estructura de WordPress (incluyendo subdirectorios como /wp/).', 'tutor-certificate-student-dni' ); ?><br>
					<?php esc_html_e( 'Si la página no existe, se creará automáticamente cuando guardes esta configuración.', 'tutor-certificate-student-dni' ); ?><br><br>
					<?php if ( ! empty( $certificate_url_base ) ) : ?>
						<strong style="color: green;"><?php esc_html_e( '✅ Ruta configurada en el plugin:', 'tutor-certificate-student-dni' ); ?></strong> 
						<code style="background: #f0f0f0; padding: 5px 10px; border-radius: 3px;"><?php echo esc_html( trailingslashit( home_url( '/' . $certificate_url_base . '/' ) ) . '?cert_hash=XXXXX' ); ?></code><br>
						<em><?php esc_html_e( 'Esta ruta se usará para búsqueda, emisión y QR de certificados.', 'tutor-certificate-student-dni' ); ?></em>
					<?php else : ?>
						<strong style="color: blue;"><?php esc_html_e( 'ℹ️ Ruta por defecto (Tutor LMS):', 'tutor-certificate-student-dni' ); ?></strong>
						<?php if ( $tutor_certificate_page_id > 0 && ! empty( $tutor_certificate_page_url ) ) : ?>
							<code style="background: #f0f0f0; padding: 5px 10px; border-radius: 3px;"><?php echo esc_html( $tutor_certificate_page_url . '?cert_hash=XXXXX' ); ?></code>
						<?php else : ?>
							<code style="background: #f0f0f0; padding: 5px 10px; border-radius: 3px;"><?php echo esc_html( trailingslashit( home_url( '/tutor-certificate-2/' ) ) . '?cert_hash=XXXXX' ); ?></code>
						<?php endif; ?>
						<br><em><?php esc_html_e( 'Si configuras una ruta nueva arriba, esta se cambiará automáticamente.', 'tutor-certificate-student-dni' ); ?></em>
					<?php endif; ?>
				</p>

				<h2><?php esc_html_e( 'Fondo Personalizado para Segunda Página', 'tutor-certificate-student-dni' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Sube una imagen de fondo personalizada que se usará en la segunda página del certificado (página del temario). Si no subes ninguna imagen, se usará el fondo original del certificado.', 'tutor-certificate-student-dni' ); ?>
				</p>
				
				<?php if ( ! empty( $certificate_background_url ) ) : ?>
					<div style="margin: 15px 0; padding: 15px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px;">
						<p><strong><?php esc_html_e( '✅ Fondo personalizado configurado:', 'tutor-certificate-student-dni' ); ?></strong></p>
						<img src="<?php echo esc_url( $certificate_background_url ); ?>" alt="<?php esc_attr_e( 'Fondo personalizado', 'tutor-certificate-student-dni' ); ?>" style="max-width: 300px; max-height: 200px; border: 1px solid #ccc; margin: 10px 0;" />
						<p>
							<label>
								<input type="checkbox" name="remove_certificate_background" value="1" />
								<?php esc_html_e( 'Eliminar fondo personalizado y usar el fondo original del certificado', 'tutor-certificate-student-dni' ); ?>
							</label>
						</p>
					</div>
				<?php endif; ?>
				
				<label for="certificate_second_page_background"><strong><?php esc_html_e( 'Subir Imagen de Fondo:', 'tutor-certificate-student-dni' ); ?></strong></label>
				<input type="file" id="certificate_second_page_background" name="certificate_second_page_background" accept="image/jpeg,image/jpg,image/png,image/gif" />
				<p class="description">
					<?php esc_html_e( 'Formatos permitidos: JPG, PNG, GIF. Se recomienda usar una imagen con las mismas dimensiones que el certificado.', 'tutor-certificate-student-dni' ); ?>
				</p>

				<h2><?php esc_html_e( 'Shortcode', 'tutor-certificate-student-dni' ); ?></h2>
				<p><?php esc_html_e( 'Utiliza el siguiente shortcode para mostrar el formulario de búsqueda en cualquier página o entrada:', 'tutor-certificate-student-dni' ); ?></p>
				<code>[buscar_certificado_dni]</code>

				<h2><?php esc_html_e( 'CSS Personalizado', 'tutor-certificate-student-dni' ); ?></h2>
				<label for="certificate_search_css"><?php esc_html_e( 'CSS Personalizado:', 'tutor-certificate-student-dni' ); ?></label>
				<textarea id="certificate_search_css" name="certificate_search_css" rows="15" class="large-text code"><?php echo esc_textarea( $certificate_custom_css ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Puedes personalizar el diseño del formulario y los resultados con CSS aquí.', 'tutor-certificate-student-dni' ); ?></p>

				<?php submit_button( __( 'Guardar Cambios', 'tutor-certificate-student-dni' ), 'primary', 'submit', false ); ?>
			</form>
			
			<hr style="margin: 30px 0;">
			
			<h2><?php esc_html_e( 'Vista Previa del Formulario', 'tutor-certificate-student-dni' ); ?></h2>
			<p><?php esc_html_e( 'Esta es una vista previa de cómo se verá el formulario de búsqueda en el frontend:', 'tutor-certificate-student-dni' ); ?></p>
			<div style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-top: 20px;">
				<?php echo do_shortcode( '[buscar_certificado_dni]' ); ?>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Obtener CSS por defecto para la búsqueda de certificados
	 */
	private function get_default_certificate_search_css() {
		return '
.hb-btn-buscar-certificado{
	background: #0073aa;
	color: #fff;
	width: 100%;
	padding: 10px 20px;
	border: none;
	border-radius: 4px;
	cursor: pointer;
	font-size: 16px;
}
.hb-btn-buscar-certificado:hover{
	background: #005a87;
}
.hb-form-certificado{
	display: flex;
	flex-direction: row;
	justify-content: space-between;
	gap: 20px;
	margin-bottom: 20px;
}
.hb-form-certificado > div{
	width: calc(50% - 10px);
}
.hb-form-certificado label{
	display: block;
	margin-bottom: 5px;
	font-weight: bold;
}
.hb-form-certificado input{
	width: 100%;
	padding: 8px;
	border: 1px solid #ddd;
	border-radius: 4px;
}
.resultado-certificados-dni{
	margin-top: 20px;
}
.resultado-certificados-dni table{
	width: 100%;
	border-collapse: collapse;
	margin-top: 20px;
}
.resultado-certificados-dni table th,
.resultado-certificados-dni table td{
	padding: 12px;
	text-align: left;
	border-bottom: 1px solid #ddd;
}
.resultado-certificados-dni table th{
	background-color: #f5f5f5;
	font-weight: bold;
}
.resultado-certificados-dni table tr:hover{
	background-color: #f9f9f9;
}
@media screen and (max-width: 768px){
	.hb-form-certificado{
		flex-direction: column;
	}
	.hb-form-certificado > div{
		width: 100%;
	}
	.resultado-certificados-dni table {
		border-collapse: collapse;
		width: 100%;
		border: 0 !important;
	}
	.resultado-certificados-dni table thead {
		display: none; 
	}
	.resultado-certificados-dni table tr {
		display: block;
		margin-bottom: 1rem;
		border: 1px solid #ddd;
		border-radius: 5px;
		padding: 10px;
	}
	.resultado-certificados-dni table td {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 5px 10px;
		font-size: 14px;
		text-align: right;
	}
	.resultado-certificados-dni table td:before {
		content: attr(data-label); 
		font-weight: bold;
		color: #333;
		width: 50%;
		flex-shrink: 0;
		text-align: left;
		font-size: 12px;
	}
	.resultado-certificados-dni table td a {
		color: #0073aa;
		text-decoration: none;
	}
	.resultado-certificados-dni table td a:hover {
		text-decoration: underline;
	}
}';
	}
	
	/**
	 * Shortcode para el formulario de búsqueda de certificados
	 */
	public function certificate_search_shortcode() {
		ob_start();
		?>
		<form method="GET" action="">
			<div class="hb-form-certificado">
				<div>
					<label for="dni_buscar"><?php esc_html_e( 'Buscar por DNI:', 'tutor-certificate-student-dni' ); ?></label>
					<input type="text" id="dni_buscar" name="dni" value="<?php echo esc_attr( isset( $_GET['dni'] ) ? $_GET['dni'] : '' ); ?>" />
				</div>
				<div>
					<label for="id_certificado_buscar"><?php esc_html_e( 'Buscar por ID del Certificado:', 'tutor-certificate-student-dni' ); ?></label>
					<input type="text" id="id_certificado_buscar" name="id_certificado" value="<?php echo esc_attr( isset( $_GET['id_certificado'] ) ? $_GET['id_certificado'] : '' ); ?>" />
				</div>
			</div>
			<button type="submit" class="hb-btn-buscar-certificado"><?php esc_html_e( 'Realizar Búsqueda', 'tutor-certificate-student-dni' ); ?></button>
		</form>
		<?php
		$this->display_certificate_search_results();
		return ob_get_clean();
	}
	
	/**
	 * Mostrar resultados de la búsqueda de certificados
	 */
	private function display_certificate_search_results() {
		global $wpdb;

		$id_certificado = isset( $_GET['id_certificado'] ) ? sanitize_text_field( $_GET['id_certificado'] ) : '';
		$dni = isset( $_GET['dni'] ) ? sanitize_text_field( $_GET['dni'] ) : '';
		
		// Obtener la URL base configurada en el plugin
		$certificado_url_base = get_option( 'certificate_search_url_base', '' );
		$certificado_page_id = get_option( 'certificate_search_page_id', 0 );
		
		// Si hay un ID de página configurado, obtener el slug de esa página
		if ( $certificado_page_id > 0 && empty( $certificado_url_base ) ) {
			$page = get_post( $certificado_page_id );
			if ( $page && is_a( $page, 'WP_Post' ) && isset( $page->post_name ) && ! empty( $page->post_name ) ) {
				$certificado_url_base = $page->post_name;
			}
		}
		
		// Si NO hay URL base configurada en el plugin, usar la ruta de Tutor LMS por defecto
		if ( empty( $certificado_url_base ) && function_exists( 'tutor_utils' ) ) {
			$tutor_certificate_page_id = (int) tutor_utils()->get_option( 'tutor_certificate_page', 0 );
			if ( $tutor_certificate_page_id > 0 ) {
				$tutor_page = get_post( $tutor_certificate_page_id );
				if ( $tutor_page && is_a( $tutor_page, 'WP_Post' ) && isset( $tutor_page->post_name ) && ! empty( $tutor_page->post_name ) ) {
					$certificado_url_base = $tutor_page->post_name; // Usar la ruta de Tutor LMS por defecto
				}
			}
		}

		if ( empty( $id_certificado ) && empty( $dni ) ) {
			return;
		}

		$query = "
			SELECT 
				c.comment_content AS idCertificado,
				DATE_FORMAT(c.comment_date, '%d-%m-%Y') AS fecha_emision,
				um_first_name.meta_value AS first_name,
				um_last_name.meta_value AS last_name,
				COALESCE(um_dni_new.meta_value, um_dni_old.meta_value) AS dni,
				p.post_title AS curso
			FROM {$wpdb->comments} c
			LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
			LEFT JOIN {$wpdb->usermeta} um_first_name ON (um_first_name.user_id = u.ID AND um_first_name.meta_key = 'first_name')
			LEFT JOIN {$wpdb->usermeta} um_last_name ON (um_last_name.user_id = u.ID AND um_last_name.meta_key = 'last_name')
			LEFT JOIN {$wpdb->usermeta} um_dni_new ON (um_dni_new.user_id = u.ID AND um_dni_new.meta_key = '_tutor_user_dni')
			LEFT JOIN {$wpdb->usermeta} um_dni_old ON (um_dni_old.user_id = u.ID AND um_dni_old.meta_key = 'dni')
			LEFT JOIN {$wpdb->posts} p ON c.comment_post_ID = p.ID
			WHERE c.comment_type = 'course_completed'
		";

		if ( ! empty( $id_certificado ) ) {
			$query .= $wpdb->prepare( " AND c.comment_content = %s", $id_certificado );
		} elseif ( ! empty( $dni ) ) {
			// Buscar en ambas meta keys (_tutor_user_dni y dni)
			$query .= $wpdb->prepare( 
				" AND (um_dni_new.meta_value = %s OR um_dni_old.meta_value = %s)", 
				$dni, 
				$dni 
			);
		}

		$resultados = $wpdb->get_results( $query );

		echo '<div class="resultado-certificados-dni">';
		if ( ! empty( $resultados ) ) {
			echo '<table>';
			echo '<thead><tr><th>' . esc_html__( 'ID', 'tutor-certificate-student-dni' ) . '</th><th>' . esc_html__( 'Fecha', 'tutor-certificate-student-dni' ) . '</th><th>' . esc_html__( 'Alumno', 'tutor-certificate-student-dni' ) . '</th><th>' . esc_html__( 'DNI', 'tutor-certificate-student-dni' ) . '</th><th>' . esc_html__( 'Curso', 'tutor-certificate-student-dni' ) . '</th><th>' . esc_html__( 'Enlace', 'tutor-certificate-student-dni' ) . '</th></tr></thead><tbody>';
			foreach ( $resultados as $resultado ) {
				// Obtener la URL del certificado usando el filtro de Tutor LMS (igual que Tutor lo hace)
				$cert_hash = esc_html( $resultado->idCertificado );
				$certificado_url = '#';
				
				// Usar la URL base determinada al inicio de la función
				// Si hay configuración del plugin, la usa. Si no, usa la de Tutor LMS por defecto
				if ( ! empty( $certificado_url_base ) ) {
					$certificado_url_base_clean = trim( $certificado_url_base, '/' );
					$certificado_url = trailingslashit( home_url( '/' . $certificado_url_base_clean . '/' ) );
					$certificado_url = add_query_arg( 'cert_hash', $cert_hash, $certificado_url );
				} else {
					// Fallback final - usar el filtro de Tutor LMS o un default
					if ( has_filter( 'tutor_certificate_public_url' ) ) {
						$certificado_url = apply_filters( 'tutor_certificate_public_url', $cert_hash );
						if ( empty( $certificado_url ) || $certificado_url === '#' ) {
							$certificado_url = '#';
						}
					}
					
					// Si aún no hay URL, usar un default seguro
					if ( empty( $certificado_url ) || $certificado_url === '#' ) {
						$certificado_url = trailingslashit( home_url( '/certificado-de-tutor/' ) );
						$certificado_url = add_query_arg( 'cert_hash', $cert_hash, $certificado_url );
					}
				}
				
				$nombre_completo = trim( $resultado->first_name . ' ' . $resultado->last_name );
				$dni_value = $resultado->dni ? $resultado->dni : '-';
				
				echo '<tr>';
				echo '<td data-label="' . esc_attr__( 'ID', 'tutor-certificate-student-dni' ) . '">' . esc_html( $resultado->idCertificado ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Fecha', 'tutor-certificate-student-dni' ) . '">' . esc_html( $resultado->fecha_emision ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Alumno', 'tutor-certificate-student-dni' ) . '">' . esc_html( $nombre_completo ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'DNI', 'tutor-certificate-student-dni' ) . '">' . esc_html( $dni_value ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Curso', 'tutor-certificate-student-dni' ) . '">' . esc_html( $resultado->curso ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Enlace', 'tutor-certificate-student-dni' ) . '"><a href="' . esc_url( $certificado_url ) . '" target="_blank">' . esc_html__( 'Ver', 'tutor-certificate-student-dni' ) . '</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>' . esc_html__( 'No se encontraron resultados.', 'tutor-certificate-student-dni' ) . '</p>';
		}
		echo '</div>';
	}
	
	/**
	 * Insertar CSS personalizado en el frontend y admin
	 */
	public function insert_certificate_search_css() {
		$custom_css = get_option( 'certificate_search_custom_css', '' );
		// Si no hay CSS personalizado, usar el por defecto
		if ( empty( $custom_css ) ) {
			$custom_css = $this->get_default_certificate_search_css();
		}
		if ( ! empty( $custom_css ) ) {
			echo '<style>' . wp_strip_all_tags( $custom_css ) . '</style>';
		}
	}
	
	/**
	 * Asegurar que las modificaciones JavaScript estén aplicadas
	 * Se ejecuta en cada carga de admin para verificar y corregir si es necesario
	 */
	public function ensure_js_modifications() {
		// Solo verificar una vez por minuto para no sobrecargar
		$last_check = get_transient( 'tutor_dni_js_check' );
		if ( $last_check ) {
			return;
		}
		
		// Verificar si el código está insertado
		if ( ! file_exists( $this->main_js_file ) ) {
			return;
		}
		
		$content = file_get_contents( $this->main_js_file );
		$has_pgdni = strpos( $content, 'PgDni' ) !== false;
		$has_lgdni_wrapper = strpos( $content, 'LgDniWrapper' ) !== false;
		$has_student_dni = strpos( $content, 'STUDENT_DNI' ) !== false;
		
		// Si no está completo, aplicar modificaciones
		if ( ! $has_pgdni || ! $has_lgdni_wrapper || ! $has_student_dni ) {
			$this->apply_js_modifications();
		}
		
		// Guardar timestamp para no verificar de nuevo en 1 minuto
		set_transient( 'tutor_dni_js_check', time(), 60 );
	}
	
	/**
	 * Asegurar que las modificaciones en Ajax.php estén aplicadas
	 * Se ejecuta en cada carga de admin para verificar y corregir si es necesario
	 */
	public function ensure_ajax_modifications() {
		// Solo verificar una vez por minuto para no sobrecargar
		$last_check = get_transient( 'tutor_dni_ajax_check' );
		if ( $last_check ) {
			return;
		}
		
		// Verificar si el código está insertado
		if ( ! file_exists( $this->ajax_file ) ) {
			return;
		}
		
		$content = file_get_contents( $this->ajax_file );
		$has_student_dni = strpos( $content, "STUDENT_DNI'" ) !== false || strpos( $content, 'STUDENT_DNI"' ) !== false;
		$has_get_user_meta_dni = strpos( $content, "get_user_meta( \$student->ID, '_tutor_user_dni'" ) !== false;
		
		// Si no está completo, aplicar modificaciones
		if ( ! $has_student_dni || ! $has_get_user_meta_dni ) {
			$this->apply_ajax_modifications();
		}
		
		// Guardar timestamp para no verificar de nuevo en 1 minuto
		set_transient( 'tutor_dni_ajax_check', time(), 60 );
	}
	
	/**
	 * Aplicar modificaciones al archivo Ajax.php para agregar STUDENT_DNI
	 */
	private function apply_ajax_modifications() {
		if ( ! file_exists( $this->ajax_file ) ) {
			return false;
		}

		// Leer el contenido del archivo
		$content = file_get_contents( $this->ajax_file );
		
		if ( $content === false ) {
			return false;
		}

		// Verificar si ya está modificado correctamente
		$has_student_dni = strpos( $content, "'STUDENT_DNI'" ) !== false || strpos( $content, '"STUDENT_DNI"' ) !== false;
		$has_get_user_meta_dni = strpos( $content, "get_user_meta( \$student->ID, '_tutor_user_dni'" ) !== false;
		
		if ( $has_student_dni && $has_get_user_meta_dni ) {
			// Ya está modificado correctamente, no hacer nada
			return true;
		}

		// Buscar el patrón donde agregar el código
		// Buscar: "Get bundle courses" y luego el array wp_send_json_success
		$pattern = '/\/\/ Get bundle courses.*?wp_send_json_success\s*\(\s*array\s*\(\s*\'COURSE_TITLE\'\s*=>/s';
		
		if ( preg_match( $pattern, $content ) ) {
			// Código a insertar antes de wp_send_json_success
			$insert_code = "\t\t// Obtener DNI del estudiante\n\t\t\$student_dni = '';\n\t\tif ( \$student && \$student->ID ) {\n\t\t\t\$student_dni = get_user_meta( \$student->ID, '_tutor_user_dni', true );\n\t\t\tif ( empty( \$student_dni ) ) {\n\t\t\t\t// Fallback: buscar en la meta antigua 'dni'\n\t\t\t\t\$student_dni = get_user_meta( \$student->ID, 'dni', true );\n\t\t\t}\n\t\t}\n\n\t\t";
			
			// Reemplazar antes de wp_send_json_success
			$content = preg_replace( '/(\/\/ Get bundle courses.*?)(\t\twp_send_json_success\s*\(\s*array\s*\(\s*\'COURSE_TITLE\'\s*=>)/s', '$1' . $insert_code . '$2', $content );
			
			// Buscar el array y agregar STUDENT_DNI después de STUDENT_NAME
			$pattern2 = '/(\'STUDENT_NAME\'\s*=>\s*\$this->get_user_name\(\s*\$student\s*\),)/';
			$replacement2 = "$1\n\t\t\t\t'STUDENT_DNI'     => \$student_dni ? \$student_dni : '',";
			
			if ( preg_match( $pattern2, $content ) ) {
				$content = preg_replace( $pattern2, $replacement2, $content );
			} else {
				// Si no encuentra el patrón exacto, buscar variaciones
				$pattern2_alt = '/(\'STUDENT_NAME\'\s*=>[^,]+),/';
				if ( preg_match( $pattern2_alt, $content ) ) {
					$content = preg_replace( $pattern2_alt, "$1,\n\t\t\t\t'STUDENT_DNI'     => \$student_dni ? \$student_dni : '',", $content );
				}
			}

			// Guardar el archivo modificado
			if ( file_put_contents( $this->ajax_file, $content ) === false ) {
				return false;
			}
			
			// Verificar que se insertó correctamente
			$verify_content = file_get_contents( $this->ajax_file );
			if ( $verify_content && ( strpos( $verify_content, "'STUDENT_DNI'" ) !== false || strpos( $verify_content, '"STUDENT_DNI"' ) !== false ) && 
			     strpos( $verify_content, "get_user_meta( \$student->ID, '_tutor_user_dni'" ) !== false ) {
				// Éxito
				return true;
			} else {
				// No se insertó correctamente
				return false;
			}
		} else {
			// Buscar patrón alternativo: buscar directamente wp_send_json_success con STUDENT_NAME
			$pattern_alt = '/(\'STUDENT_NAME\'\s*=>\s*\$this->get_user_name\(\s*\$student\s*\),)/';
			if ( preg_match( $pattern_alt, $content ) ) {
				// Verificar si ya tiene STUDENT_DNI
				if ( strpos( $content, "'STUDENT_DNI'" ) === false && strpos( $content, '"STUDENT_DNI"' ) === false ) {
					// Buscar dónde insertar el código para obtener el DNI
					// Buscar antes de wp_send_json_success
					$pattern_before = '/(\t\t)(wp_send_json_success\s*\(\s*array\s*\(\s*\'COURSE_TITLE\')/';
					$insert_before = "\t\t// Obtener DNI del estudiante\n\t\t\$student_dni = '';\n\t\tif ( \$student && \$student->ID ) {\n\t\t\t\$student_dni = get_user_meta( \$student->ID, '_tutor_user_dni', true );\n\t\t\tif ( empty( \$student_dni ) ) {\n\t\t\t\t\$student_dni = get_user_meta( \$student->ID, 'dni', true );\n\t\t\t}\n\t\t}\n\n\t\t";
					
					if ( preg_match( $pattern_before, $content ) ) {
						$content = preg_replace( $pattern_before, $insert_before . '$2', $content );
					}
					
					// Agregar STUDENT_DNI al array
					$content = preg_replace( $pattern_alt, "$1\n\t\t\t\t'STUDENT_DNI'     => \$student_dni ? \$student_dni : '',", $content );
					
					// Guardar
					if ( file_put_contents( $this->ajax_file, $content ) !== false ) {
						return true;
					}
				}
			}
			
			return false;
		}
	}

	/**
	 * Activar el plugin
	 */
	public function activate() {
		// Crear backup del archivo original JavaScript
		if ( file_exists( $this->main_js_file ) && ! file_exists( $this->backup_file ) ) {
			copy( $this->main_js_file, $this->backup_file );
		}

		// Crear backup del archivo Ajax.php
		if ( file_exists( $this->ajax_file ) && ! file_exists( $this->ajax_backup_file ) ) {
			copy( $this->ajax_file, $this->ajax_backup_file );
		}

		// Aplicar modificaciones
		$this->apply_js_modifications();
		$this->apply_ajax_modifications();
		
		// Limpiar variables dni duplicadas y migrar a _tutor_user_dni
		$this->cleanup_duplicate_dni_meta();
		
		// Crear/verificar la página de certificados en Tutor LMS
		$this->ensure_certificate_page_exists();
	}
	
	/**
	 * Limpiar variables dni duplicadas y migrar a _tutor_user_dni
	 */
	private function cleanup_duplicate_dni_meta() {
		global $wpdb;
		
		// Obtener todos los usuarios que tienen la variable 'dni'
		$users_with_dni = $wpdb->get_results(
			"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'dni'"
		);
		
		foreach ( $users_with_dni as $user_meta ) {
			$user_id = $user_meta->user_id;
			$dni_value = $user_meta->meta_value;
			
			// Si no tiene _tutor_user_dni, migrar el valor
			$existing_tutor_dni = get_user_meta( $user_id, '_tutor_user_dni', true );
			if ( empty( $existing_tutor_dni ) && ! empty( $dni_value ) ) {
				update_user_meta( $user_id, '_tutor_user_dni', $dni_value );
			}
			
			// Eliminar la variable dni duplicada
			delete_user_meta( $user_id, 'dni' );
		}
	}

	/**
	 * Desactivar el plugin
	 */
	public function deactivate() {
		// Restaurar el archivo JavaScript original desde el backup
		if ( file_exists( $this->backup_file ) && file_exists( $this->main_js_file ) ) {
			copy( $this->backup_file, $this->main_js_file );
		}

		// Restaurar el archivo Ajax.php original desde el backup
		if ( file_exists( $this->ajax_backup_file ) && file_exists( $this->ajax_file ) ) {
			copy( $this->ajax_backup_file, $this->ajax_file );
		}
	}

	/**
	 * Verificar si el plugin Certificate Builder está activo
	 */
	private function is_certificate_builder_active() {
		return class_exists( '\Tutor\Certificate\Builder\Plugin' );
	}

	/**
	 * Notificación de administrador si falta el plugin
	 */
	public function admin_notice_missing_plugin() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Tutor Certificate Student DNI requiere que el plugin Tutor LMS Certificate Builder esté instalado y activo.', 'tutor-certificate-student-dni' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Agregar elemento STUDENT_DNI al editor
	 */
	public function add_student_dni_element( $elements ) {
		// Verificar si ya existe
		if ( ! isset( $elements['STUDENT_DNI'] ) ) {
			$elements['STUDENT_DNI'] = array(
				'type'     => 'text',
				'label'    => __( 'DNI del Estudiante', 'tutor-certificate-student-dni' ),
				'value'    => '{STUDENT_DNI}',
				'field'    => 'STUDENT_DNI',
				'category' => 'user',
				'variable' => 'STUDENT_DNI',
			);
		}
		return $elements;
	}

	/**
	 * Aplicar modificaciones al archivo JavaScript
	 */
	private function apply_js_modifications() {
		if ( ! file_exists( $this->main_js_file ) ) {
			return;
		}

		// Leer el contenido del archivo
		$content = file_get_contents( $this->main_js_file );
		
		if ( $content === false ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'No se pudo leer el archivo main.min.js. Verifique que el archivo exista y tenga permisos de lectura.', 'tutor-certificate-student-dni' ) . '</p></div>';
			} );
			return;
		}

		// Verificar si ya está modificado correctamente
		$has_pgdni = strpos( $content, 'PgDni' ) !== false;
		$has_lgdni_wrapper = strpos( $content, 'LgDniWrapper' ) !== false;
		$has_student_dni = strpos( $content, 'STUDENT_DNI' ) !== false;
		
		if ( $has_pgdni && $has_lgdni_wrapper && $has_student_dni ) {
			// Ya está modificado correctamente, no hacer nada
			return;
		}

		// Si ya tiene PgDni pero no LgDniWrapper, restaurar desde backup primero
		if ( strpos( $content, 'PgDni' ) !== false && strpos( $content, 'LgDniWrapper' ) === false ) {
			if ( file_exists( $this->backup_file ) ) {
				$content = file_get_contents( $this->backup_file );
			}
		}

		// Buscar el patrón donde agregar el código
		// El archivo está minificado: Component:Pg};const Tg=function(t){
		// IMPORTANTE: El patrón debe incluir (t){ para capturar todo y evitar duplicación
		$patterns = array(
			'/Component:\s*Pg\s*\}\s*;\s*const\s+Tg\s*=\s*function\s*\(\s*t\s*\)\s*\{/',        // Minificado: Component:Pg};const Tg=function(t){
			'/Component:\s*Pg\s*,\s*\}\s*;\s*const\s+Tg\s*=\s*function\s*\(\s*t\s*\)\s*\{/',    // Con coma: Component:Pg,};const Tg=function(t){
			'/Component:\s*Pg\s*\}\s*;\s*const\s+Tg\s*=\s*function\s*\(/',                      // Sin (t){: Component:Pg};const Tg=function(
			'/Component:\s*Pg\s*\}\s*;\s*const\s+Tg\s*=/',                                     // Sin function: Component:Pg};const Tg=
		);
		
		$pattern = null;
		foreach ( $patterns as $test_pattern ) {
			if ( preg_match( $test_pattern, $content ) ) {
				$pattern = $test_pattern;
				break;
			}
		}
		
		if ( $pattern ) {
			// Código a insertar (componente PgDni y configuración LgDni)
			// El archivo está minificado, así que insertamos código minificado también
			// IMPORTANTE: NO incluir "const Tg=function(t){" al final porque el patrón ya lo incluye
			// Formato: Component:Pg};[código nuevo];const Tg=function(t){
			// El patrón busca: Component:Pg};const Tg=function(t){
			// Lo reemplazamos con: Component:Pg};[código nuevo];const Tg=function(t){
			// Por lo tanto, el código nuevo NO debe incluir "const Tg=function(t){" al final
			$insert_code = 'Component:Pg};const PgDni=function(t){var e,n=t.id,i=t.name,r=t.componentRef,a=t.attributes,s=a.content,o=(a.position,a.textAlignment),A=a.isEditable,l=a.style,c=a.transparency,f=void 0===c?1:c,u=a.flippedType,d=(0,E.wA)(),p=(0,E.d4)(function(t){return t.certificateData}),v=(0,g.useState)((null==p?void 0:p.STUDENT_DNI)||s),b=(0,h.A)(v,2),y=b[0],B=b[1];(0,g.useEffect)(function(){B((null==p?void 0:p.STUDENT_DNI)||s)},[p]);var k=m()("".concat(tcb_prefix,"-element"),"".concat(tcb_prefix,"-element-").concat(i),"tcb-element-".concat(n),(0,w.A)({},"has-text-".concat(o),o),(0,w.A)({},"tcb-flip-".concat(u),void 0!==u)),F=null==l||null===(e=l.typography)||void 0===e?void 0:e.spacing;return(0,C.jsx)("div",{className:k,ref:r,style:{letterSpacing:F,opacity:f},children:(0,C.jsx)(vg,{textContent:y,isEditable:A,attributes:t.attributes,onChange:function(t){d({type:x.Nm,payload:{id:n,attributes:t}})},name:i})})},LgDni={name:"student_dni",title:window.wp.i18n.__("Student DNI","tutor-lms-certificate-builder"),icon:"student",category:"element",attributes:{content:"[ student_dni ]",formats:[],position:{top:20,left:80},size:{width:200,height:50},rotate:{value:0,unit:"deg"},style:{typography:{family:"Lexend",type:"sans-serif",height:1.4,weight:400,spacing:0,size:20,fontFamily:"Lexend"},color:{textColor:"#000"}},textAlignment:"align_left",flippedType:null,align:"center",transparency:100,isEditable:!1},Component:PgDni};const Tg=function(t){';

			// Reemplazar
			$content = preg_replace( $pattern, $insert_code, $content );

			// Buscar el array de elementos y agregar LgDniWrapper
			// Buscar el patrón del array: [a, e, c, s, l, d, A, f, n, o, i, u]
			$pattern2 = '/\[a,\s*e,\s*c,\s*s,\s*l,\s*d,\s*A,\s*f,\s*n,\s*o,\s*i,\s*u\]/';
			$replacement2 = '[a, e, c, s, l, d, A, f, n, o, i, u, LgDniWrapper]';
			
			if ( preg_match( $pattern2, $content ) ) {
				// Buscar donde se define LgDniWrapper o agregarlo antes del array
				if ( strpos( $content, 'LgDniWrapper' ) === false ) {
					// Agregar la definición de LgDniWrapper antes de const Gg
					$pattern3 = '/const\s+Gg\s*=\s*function\s*\(\)\s*\{/';
					$replacement3 = 'var LgDniWrapper = { default: LgDni };
      const Gg = function () {';
					$content = preg_replace( $pattern3, $replacement3, $content );
				}
				
				// Reemplazar el array solo si no tiene LgDniWrapper
				if ( strpos( $content, '[a, e, c, s, l, d, A, f, n, o, i, u, LgDniWrapper]' ) === false ) {
					$content = preg_replace( $pattern2, $replacement2, $content );
				}
			} else {
				// Si no se encuentra el patrón del array, buscar variaciones
				$pattern2_alt = '/\[a,\s*e,\s*c,\s*s,\s*l,\s*d,\s*A,\s*f,\s*n,\s*o,\s*i,\s*u(?:,\s*LgDniWrapper)?\]/';
				if ( preg_match( $pattern2_alt, $content ) && strpos( $content, 'LgDniWrapper' ) === false ) {
					// Agregar LgDniWrapper al array existente
					$content = preg_replace( '/\[a,\s*e,\s*c,\s*s,\s*l,\s*d,\s*A,\s*f,\s*n,\s*o,\s*i,\s*u\]/', $replacement2, $content );
					
					// Agregar la definición de LgDniWrapper si no existe
					if ( strpos( $content, 'var LgDniWrapper' ) === false ) {
						$pattern3 = '/const\s+Gg\s*=\s*function\s*\(\)\s*\{/';
						$replacement3 = 'var LgDniWrapper = { default: LgDni };
      const Gg = function () {';
						$content = preg_replace( $pattern3, $replacement3, $content );
					}
				}
			}

			// Guardar el archivo modificado
			if ( file_put_contents( $this->main_js_file, $content ) === false ) {
				// Error al guardar
				add_action( 'admin_notices', function() {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Error al modificar el archivo main.min.js. Verifique los permisos del archivo.', 'tutor-certificate-student-dni' ) . '</p></div>';
				} );
				return;
			}
			
			// Verificar que se insertó correctamente
			$verify_content = file_get_contents( $this->main_js_file );
			if ( strpos( $verify_content, 'PgDni' ) !== false && strpos( $verify_content, 'LgDniWrapper' ) !== false ) {
				// Éxito
				return;
			} else {
				// No se insertó correctamente
				add_action( 'admin_notices', function() {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'El código DNI se intentó insertar pero no se verificó correctamente. Por favor, desactiva y reactiva el plugin.', 'tutor-certificate-student-dni' ) . '</p></div>';
				} );
			}
		} else {
			// No se encontró el patrón
			// Verificar si el código ya está insertado (puede que el patrón haya cambiado pero el código ya esté)
			$has_pgdni = strpos( $content, 'PgDni' ) !== false;
			$has_lgdni_wrapper = strpos( $content, 'LgDniWrapper' ) !== false;
			$has_student_dni = strpos( $content, 'STUDENT_DNI' ) !== false;
			
			if ( $has_pgdni && $has_lgdni_wrapper && $has_student_dni ) {
				// Ya está insertado correctamente, no hacer nada (el patrón cambió pero el código está bien)
				return;
			}
			
			// Si tiene alguna parte pero no todas, puede estar parcialmente insertado
			if ( $has_pgdni || $has_lgdni_wrapper ) {
				add_action( 'admin_notices', function() {
					echo '<div class="notice notice-warning"><p>' . esc_html__( 'El código DNI está parcialmente insertado en main.min.js. Por favor, desactiva y reactiva el plugin para completar la inserción.', 'tutor-certificate-student-dni' ) . '</p></div>';
				} );
				return;
			}
			
			// Mostrar advertencia solo si realmente no está insertado
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'No se pudo encontrar el patrón para insertar el código DNI en main.min.js. El archivo puede haber sido actualizado. Por favor, verifica manualmente o contacta al soporte.', 'tutor-certificate-student-dni' ) . '</p></div>';
			} );
		}
	}

	/**
	 * Inicializar campos de perfil de usuario
	 */
	private function init_user_profile_fields() {
		// Agregar campo DNI ANTES del campo first_name - wp-admin
		// Usar prioridad alta para que se ejecute después de que WordPress agregue los campos
		add_action( 'show_user_profile', array( $this, 'add_dni_field_before_name' ), 25 );
		add_action( 'edit_user_profile', array( $this, 'add_dni_field_before_name' ), 25 );

		// Agregar campo DNI en el formulario de nuevo usuario - wp-admin
		add_action( 'user_new_form', array( $this, 'add_dni_field_new_user' ), 25 );

		// Agregar JavaScript para mover el campo DNI después del nombre y eliminar duplicados
		add_action( 'admin_footer', array( $this, 'add_dni_field_script' ) );

		// Guardar DNI cuando se actualiza el perfil propio - wp-admin
		add_action( 'personal_options_update', array( $this, 'save_dni_field' ), 10, 1 );

		// Guardar DNI cuando se actualiza el perfil de otro usuario - wp-admin
		add_action( 'edit_user_profile_update', array( $this, 'save_dni_field' ), 10, 1 );

		// Guardar DNI cuando se crea un nuevo usuario - wp-admin
		add_action( 'user_register', array( $this, 'save_dni_field_new_user' ), 10, 1 );
		
		// Hook adicional para cuando se crea un usuario desde admin
		add_action( 'edit_user_created_user', array( $this, 'save_dni_field_new_user' ), 10, 1 );

		// Hook adicional para asegurar que se guarde - con prioridad más alta
		add_action( 'profile_update', array( $this, 'save_dni_field' ), 5, 1 );
		
		// Hook adicional después de wp_insert_user para nuevos usuarios
		add_action( 'wp_insert_user', array( $this, 'save_dni_field_new_user' ), 20, 1 );
		
		// Hook adicional después de edit_user para asegurar que se guarde
		add_action( 'edit_user_profile_update', array( $this, 'save_dni_field' ), 20, 1 );
		add_action( 'personal_options_update', array( $this, 'save_dni_field' ), 20, 1 );
		
		// Agregar validación de errores
		add_action( 'user_profile_update_errors', array( $this, 'validate_dni_field' ), 10, 3 );
	}
	
	/**
	 * Validar campo DNI antes de guardar
	 *
	 * @param WP_Error $errors Errores de WordPress
	 * @param bool     $update Si es actualización o creación
	 * @param WP_User  $user   Objeto de usuario
	 */
	public function validate_dni_field( $errors, $update, $user ) {
		if ( isset( $_POST['user_dni'] ) && ! empty( $_POST['user_dni'] ) ) {
			$dni = trim( sanitize_text_field( $_POST['user_dni'] ) );
			
			// Validar que solo contenga números
			if ( ! preg_match( '/^[0-9]+$/', $dni ) ) {
				$errors->add( 'dni_invalid', __( 'El DNI solo puede contener números.', 'tutor-certificate-student-dni' ) );
			}
			
			// Validar que tenga exactamente 8 dígitos
			if ( strlen( $dni ) !== 8 ) {
				$errors->add( 'dni_length', __( 'El DNI debe tener exactamente 8 dígitos.', 'tutor-certificate-student-dni' ) );
			}
		}
	}

	/**
	 * Agregar campo DNI ANTES del campo first_name (wp-admin)
	 *
	 * @param WP_User $user Objeto de usuario
	 */
	public function add_dni_field_before_name( $user ) {
		// Solo mostrar en wp-admin
		if ( ! is_admin() ) {
			return;
		}

		// Obtener el valor del DNI
		$dni = '';
		if ( is_object( $user ) && isset( $user->ID ) ) {
			$dni = get_user_meta( $user->ID, '_tutor_user_dni', true );
			if ( empty( $dni ) ) {
				$dni = get_user_meta( $user->ID, 'dni', true );
			}
		}

		// Agregar el campo - JavaScript lo moverá a la posición correcta (antes de first_name)
		?>
		<tr class="user-dni-wrap">
			<th>
				<label for="user_dni"><?php esc_html_e( 'DNI', 'tutor-certificate-student-dni' ); ?></label>
			</th>
			<td>
				<input 
					type="text" 
					name="user_dni" 
					id="user_dni" 
					value="<?php echo esc_attr( $dni ); ?>" 
					class="regular-text" 
					placeholder="<?php esc_attr_e( 'Ingrese 8 dígitos', 'tutor-certificate-student-dni' ); ?>"
					maxlength="8"
					pattern="[0-9]{8}"
					inputmode="numeric"
					autocomplete="off"
				/>
				<p class="description">
					<?php esc_html_e( 'Documento Nacional de Identidad (8 dígitos numéricos). Este campo se utilizará en los certificados de Tutor LMS.', 'tutor-certificate-student-dni' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Agregar campo DNI en el formulario de nuevo usuario (wp-admin)
	 *
	 * @param string $context Contexto del formulario
	 */
	public function add_dni_field_new_user( $context = '' ) {
		// Solo mostrar en wp-admin
		if ( ! is_admin() ) {
			return;
		}
		?>
		<tr class="form-field user-dni-wrap">
			<th scope="row">
				<label for="user_dni"><?php esc_html_e( 'DNI', 'tutor-certificate-student-dni' ); ?></label>
			</th>
			<td>
				<input 
					type="text" 
					name="user_dni" 
					id="user_dni" 
					value="" 
					class="regular-text" 
					placeholder="<?php esc_attr_e( 'Ingrese 8 dígitos', 'tutor-certificate-student-dni' ); ?>"
					maxlength="8"
					pattern="[0-9]{8}"
					inputmode="numeric"
					autocomplete="off"
				/>
				<p class="description">
					<?php esc_html_e( 'Documento Nacional de Identidad (8 dígitos numéricos). Este campo se utilizará en los certificados de Tutor LMS.', 'tutor-certificate-student-dni' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}


	/**
	 * Guardar campo DNI cuando se actualiza el perfil
	 *
	 * @param int $user_id ID del usuario
	 */
	public function save_dni_field( $user_id ) {
		// Verificar que el user_id sea válido
		if ( ! $user_id || ! is_numeric( $user_id ) ) {
			return false;
		}

		// Verificar permisos básicos
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		// Obtener el valor del DNI del POST
		$dni = '';
		
		if ( isset( $_POST['user_dni'] ) ) {
			$dni = trim( sanitize_text_field( $_POST['user_dni'] ) );
			
			// Validar que solo contenga números
			$dni = preg_replace( '/[^0-9]/', '', $dni );
			
			// Validar que tenga exactamente 8 dígitos (solo si no está vacío)
			if ( ! empty( $dni ) && strlen( $dni ) !== 8 ) {
				// Si no tiene 8 dígitos, no guardar
				return false;
			}
		}

		// Guardar siempre, incluso si está vacío (para poder limpiar el campo)
		// Solo guardar en _tutor_user_dni (eliminar dni duplicado)
		if ( ! empty( $dni ) || isset( $_POST['user_dni'] ) ) {
			// Guardar solo en _tutor_user_dni
			$result = update_user_meta( $user_id, '_tutor_user_dni', $dni );
			if ( false === $result ) {
				delete_user_meta( $user_id, '_tutor_user_dni' );
				add_user_meta( $user_id, '_tutor_user_dni', $dni, true );
			}
			
			// Eliminar la variable dni duplicada si existe
			delete_user_meta( $user_id, 'dni' );
		}

		return true;
	}

	/**
	 * Guardar campo DNI cuando se crea un nuevo usuario
	 *
	 * @param int $user_id ID del usuario
	 */
	public function save_dni_field_new_user( $user_id ) {
		// Verificar que el user_id sea válido
		if ( ! $user_id || ! is_numeric( $user_id ) ) {
			return false;
		}

		// Verificar permisos
		if ( ! current_user_can( 'create_users' ) && ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		// Obtener el valor del DNI del POST
		$dni = '';
		
		if ( isset( $_POST['user_dni'] ) ) {
			$dni = trim( sanitize_text_field( $_POST['user_dni'] ) );
			
			// Validar que solo contenga números
			$dni = preg_replace( '/[^0-9]/', '', $dni );
			
			// Validar que tenga exactamente 8 dígitos
			if ( ! empty( $dni ) && strlen( $dni ) !== 8 ) {
				// Si no tiene 8 dígitos, no guardar
				return false;
			}
		}

		// Guardar siempre, incluso si está vacío
		// Solo guardar en _tutor_user_dni (eliminar dni duplicado)
		if ( ! empty( $dni ) || isset( $_POST['user_dni'] ) ) {
			// Guardar solo en _tutor_user_dni
			$result = update_user_meta( $user_id, '_tutor_user_dni', $dni );
			if ( false === $result ) {
				delete_user_meta( $user_id, '_tutor_user_dni' );
				add_user_meta( $user_id, '_tutor_user_dni', $dni, true );
			}
			
			// Eliminar la variable dni duplicada si existe
			delete_user_meta( $user_id, 'dni' );
		}

		return true;
	}

	/**
	 * Agregar JavaScript para mover el campo DNI antes del campo first_name
	 */
	public function add_dni_field_script() {
		// Solo en páginas de usuario (profile.php, user-edit.php, user-new.php)
		$screen = get_current_screen();
		$is_user_page = false;
		
		if ( $screen ) {
			$is_user_page = in_array( $screen->id, array( 'user-edit', 'profile', 'user', 'user-new' ), true );
		}
		
		// También verificar por la URL
		if ( ! $is_user_page ) {
			global $pagenow;
			$is_user_page = in_array( $pagenow, array( 'profile.php', 'user-edit.php', 'user-new.php' ), true );
		}
		
		if ( ! $is_user_page ) {
			return;
		}
		?>
		<script type="text/javascript">
		// Ejecutar inmediatamente, antes de que jQuery esté listo
		(function() {
			function moveDniFieldImmediate() {
				if (typeof jQuery === 'undefined') {
					setTimeout(moveDniFieldImmediate, 50);
					return;
				}
				
				var $ = jQuery;
				var dniRow = $('.user-dni-wrap').first();
				if (!dniRow.length) {
					setTimeout(moveDniFieldImmediate, 100);
					return;
				}
				
				// Buscar el campo first_name
				var firstNameInput = $('#first_name');
				if (!firstNameInput.length) {
					firstNameInput = $('input[name="first_name"]');
				}
				
				if (firstNameInput.length) {
					var firstNameRow = firstNameInput.closest('tr');
					if (firstNameRow.length) {
						// Verificar si el DNI ya está antes del first_name
						var dniInSameTable = dniRow.closest('table.form-table')[0] === firstNameRow.closest('table.form-table')[0];
						var dniBeforeFirst = dniInSameTable && dniRow.index() < firstNameRow.index();
						
						if (!dniBeforeFirst) {
							// Mover el DNI ANTES del campo first_name
							dniRow.detach();
							firstNameRow.before(dniRow);
						}
						dniRow.css('display', '');
						return;
					}
				}
				
				// Fallback: buscar tabla "Name" y ponerlo como primera fila
				var nameTable = null;
				$('h2').each(function() {
					var $h2 = $(this);
					var text = $h2.text().trim();
					if (text === 'Name' || text === 'Nombre') {
						var $nextElement = $h2.next();
						if ($nextElement.is('table.form-table')) {
							nameTable = $nextElement;
							return false;
						}
						$h2.nextAll().each(function() {
							var $elem = $(this);
							if ($elem.is('table.form-table') && $elem.find('input#first_name, input[name="first_name"], tr.user-first-name-wrap').length) {
								nameTable = $elem;
								return false;
							}
						});
						return false;
					}
				});
				
				if (nameTable && nameTable.length) {
					var tbody = nameTable.find('tbody');
					if (!tbody.length) {
						tbody = nameTable;
					}
					
					var firstRow = tbody.find('tr').first();
					if (!firstRow.hasClass('user-dni-wrap')) {
						dniRow.detach();
						if (firstRow.length) {
							firstRow.before(dniRow);
						} else {
							tbody.prepend(dniRow);
						}
					}
				}
				dniRow.css('display', '');
			}
			
			// Intentar mover inmediatamente
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', moveDniFieldImmediate);
			} else {
				moveDniFieldImmediate();
			}
		})();
		
		jQuery(document).ready(function($) {
			// Validación en tiempo real del campo DNI
			$(document).on('input keyup paste', '#user_dni', function() {
				var $input = $(this);
				var value = $input.val();
				
				// Solo permitir números
				value = value.replace(/[^0-9]/g, '');
				
				// Limitar a 8 dígitos
				if (value.length > 8) {
					value = value.substring(0, 8);
				}
				
				// Actualizar el valor del campo
				$input.val(value);
				
				// Mostrar mensaje de validación
				var $description = $input.closest('td').find('.description');
				var originalText = 'Documento Nacional de Identidad (8 dígitos numéricos). Este campo se utilizará en los certificados de Tutor LMS.';
				
				if (value.length > 0 && value.length < 8) {
					$description.css('color', '#d63638');
					$description.text('El DNI debe tener 8 dígitos. Faltan ' + (8 - value.length) + ' dígito(s).');
				} else if (value.length === 8) {
					$description.css('color', '#00a32a');
					$description.text('DNI válido (8 dígitos).');
				} else {
					$description.css('color', '');
					$description.text(originalText);
				}
			});
			
			// Validación al enviar el formulario (incluye profile.php, user-edit.php y user-new.php)
			$('form#your-profile, form#createuser, form#adduser, form#addnewuser').on('submit', function(e) {
				var dniValue = $('#user_dni').val();
				
				if (dniValue.length > 0 && dniValue.length !== 8) {
					e.preventDefault();
					alert('El DNI debe tener exactamente 8 dígitos numéricos.');
					$('#user_dni').focus();
					return false;
				}
				
				if (dniValue.length > 0 && !/^[0-9]+$/.test(dniValue)) {
					e.preventDefault();
					alert('El DNI solo puede contener números.');
					$('#user_dni').focus();
					return false;
				}
			});
			
			// Función para mover el campo DNI - Versión mejorada para profile.php
			function moveDniField() {
				// Eliminar campos DNI duplicados, dejar solo el primero
				var dniRows = $('.user-dni-wrap');
				if (dniRows.length > 1) {
					dniRows.slice(1).remove();
				}
				
				// Obtener el campo DNI (debe quedar solo uno)
				var dniRow = $('.user-dni-wrap').first();
				
				if (!dniRow.length) {
					return false;
				}
				
				// Asegurar que el campo tenga el atributo name correcto
				var dniInput = dniRow.find('input');
				dniInput.attr('name', 'user_dni');
				dniInput.attr('id', 'user_dni');
				
				// ESTRATEGIA PRINCIPAL: Buscar el campo first_name y poner el DNI ANTES de él
				var firstNameInput = $('#first_name');
				if (!firstNameInput.length) {
					firstNameInput = $('input[name="first_name"]');
				}
				
				if (firstNameInput.length) {
					var firstNameRow = firstNameInput.closest('tr');
					if (firstNameRow.length) {
						// Verificar si el DNI ya está antes del first_name
						var dniInSameTable = dniRow.closest('table.form-table')[0] === firstNameRow.closest('table.form-table')[0];
						var dniBeforeFirst = dniInSameTable && dniRow.index() < firstNameRow.index();
						
						if (!dniBeforeFirst) {
							// Mover el DNI ANTES del campo first_name
							dniRow.detach();
							firstNameRow.before(dniRow);
						}
						dniRow.css('display', '');
						return true;
					}
				}
				
				// ESTRATEGIA FALLBACK: Buscar h2 "Name" o "Nombre" y la tabla que le sigue inmediatamente
				var nameTable = null;
				
				// Buscar todos los h2 y encontrar el que dice "Name" o "Nombre"
				$('h2').each(function() {
					var $h2 = $(this);
					var text = $h2.text().trim();
					
					// Verificar si es el h2 "Name" o "Nombre"
					if (text === 'Name' || text === 'Nombre') {
						// Buscar la tabla que está JUSTO DESPUÉS del h2
						var $nextElement = $h2.next();
						
						// Si el siguiente elemento es una tabla, usarla
						if ($nextElement.is('table.form-table')) {
							nameTable = $nextElement;
							return false; // break
						}
						
						// Si no, buscar en los siguientes hermanos
						$h2.nextAll().each(function() {
							var $elem = $(this);
							if ($elem.is('table.form-table')) {
								// Verificar que esta tabla tenga campos de nombre
								if ($elem.find('input#first_name, input[name="first_name"], tr.user-first-name-wrap').length) {
									nameTable = $elem;
									return false; // break
								}
							}
						});
						
						return false; // break del each de h2
					}
				});
				
				// Si encontramos el h2 y la tabla, mover el DNI
				if (nameTable && nameTable.length) {
					// Obtener el tbody de la tabla
					var tbody = nameTable.find('tbody');
					if (!tbody.length) {
						tbody = nameTable;
					}
					
					// Verificar si el DNI ya está en la primera posición de esta tabla
					var firstRow = tbody.find('tr').first();
					var dniInCorrectTable = dniRow.closest('table.form-table')[0] === nameTable[0];
					var dniIsFirst = firstRow.hasClass('user-dni-wrap');
					
					if (dniIsFirst && dniInCorrectTable) {
						// Ya está en la posición correcta, asegurar que esté visible
						dniRow.css('display', '');
						return true;
					}
					
					// Remover el DNI de donde esté actualmente
					dniRow.detach();
					
					// Insertar el DNI como PRIMERA fila del tbody (justo después del h2)
					if (firstRow.length && !firstRow.hasClass('user-dni-wrap')) {
						firstRow.before(dniRow);
					} else {
						tbody.prepend(dniRow);
					}
					
					// Mostrar el campo ahora que está en la posición correcta
					dniRow.css('display', '');
					
					return true;
				}
				
				// ESTRATEGIA FALLBACK 2: Buscar cualquier tabla form-table que contenga campos de nombre
				$('table.form-table').each(function() {
					var $table = $(this);
					if ($table.find('input#first_name, input[name="first_name"], tr.user-first-name-wrap, tr.user-last-name-wrap').length) {
						var firstNameInput = $table.find('input#first_name, input[name="first_name"]').first();
						if (firstNameInput.length) {
							var firstNameRow = firstNameInput.closest('tr');
							if (firstNameRow.length) {
								// Verificar si el DNI ya está antes del first_name
								var dniInSameTable = dniRow.closest('table.form-table')[0] === $table[0];
								var dniBeforeFirst = dniInSameTable && dniRow.index() < firstNameRow.index();
								
								if (!dniBeforeFirst) {
									// Mover el DNI ANTES del campo first_name
									dniRow.detach();
									firstNameRow.before(dniRow);
								}
								dniRow.css('display', '');
								return false; // break
							}
						}
						
						var tbody = $table.find('tbody');
						if (!tbody.length) {
							tbody = $table;
						}
						
						var firstRow = tbody.find('tr').first();
						if (!firstRow.hasClass('user-dni-wrap')) {
							dniRow.detach();
							firstRow.before(dniRow);
							dniRow.css('display', '');
							return false; // break
						} else {
							dniRow.css('display', '');
						}
					}
				});
				
				// Si llegamos aquí, mostrar el campo de todas formas
				dniRow.css('display', '');
				return false;
			}
			
			// Ejecutar cuando la página esté completamente cargada
			$(window).on('load', function() {
				setTimeout(function() {
					moveDniField();
				}, 200);
			});
			
			// Observar cambios en el DOM de forma más agresiva
			if (typeof MutationObserver !== 'undefined') {
				var observer = new MutationObserver(function(mutations) {
					var shouldCheck = false;
					mutations.forEach(function(mutation) {
						if (mutation.addedNodes.length > 0 || mutation.removedNodes.length > 0) {
							shouldCheck = true;
						}
					});
					
					if (shouldCheck) {
						setTimeout(function() {
							moveDniField();
						}, 50);
					}
				});
				
				observer.observe(document.body, {
					childList: true,
					subtree: true
				});
			}
			
			// También verificar periódicamente (cada 500ms) durante los primeros 5 segundos
			var periodicCheck = setInterval(function() {
				moveDniField();
			}, 500);
			
			setTimeout(function() {
				clearInterval(periodicCheck);
			}, 5000);
		});
		</script>
		<?php
	}
}

// Inicializar el plugin
Tutor_Certificate_Student_DNI::get_instance();

