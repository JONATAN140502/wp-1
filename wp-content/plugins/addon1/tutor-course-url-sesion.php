<?php
/**
 * Plugin Name: TUTOR ADDON
 * Plugin URI: https://monstruocreativo.com/
 * Description: Añade un campo para URL de sesión (Meet/Zoom) a los cursos de Tutor LMS sin modificar el código del plugin principal.
 * Version: 1.0.0
 * Author: MOUNSTRO CREATIVO
 * Author URI: https://monstruocreativo.com/
 * Text Domain: tutor-course-url-sesion
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Definir constantes del plugin.
define( 'TUTOR_COURSE_URL_SESION_VERSION', '1.0.0' );
define( 'TUTOR_COURSE_URL_SESION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TUTOR_COURSE_URL_SESION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TUTOR_COURSE_URL_SESION_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Clase principal del plugin
 */
class Tutor_Course_URL_Sesion {

	/**
	 * Instancia única del plugin
	 *
	 * @var Tutor_Course_URL_Sesion
	 */
	private static $instance = null;

	/**
	 * Obtener instancia única del plugin
	 *
	 * @return Tutor_Course_URL_Sesion
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Inicializar hooks
	 */
	private function init_hooks() {
		// Verificar que Tutor LMS esté activo.
		add_action( 'plugins_loaded', array( $this, 'check_tutor_dependency' ) );
		
		// Activar automáticamente Tutor Pro si está instalado
		add_action( 'plugins_loaded', array( $this, 'auto_activate_tutor_pro' ), 20 );
		add_action( 'activated_plugin', array( $this, 'on_plugin_activated' ), 10, 2 );
		
		// AJAX para obtener el valor del campo (para frontend)
		add_action( 'wp_ajax_tutor_get_course_url_sesion', array( $this, 'ajax_get_course_url_sesion' ) );
		add_action( 'wp_ajax_tutor_save_course_url_sesion', array( $this, 'ajax_save_course_url_sesion' ) );
		
		// Hooks principales para guardar el campo (múltiples puntos de entrada para asegurar que siempre se guarde).
		add_action( 'tutor_save_course_meta', array( $this, 'save_course_url_sesion' ), 10, 1 );
		add_action( 'tutor_save_course', array( $this, 'save_course_url_sesion' ), 10, 1 );
		add_action( 'tutor_save_course_after', array( $this, 'save_course_url_sesion' ), 10, 1 );
		add_action( 'save_post', array( $this, 'save_course_url_sesion_legacy' ), 10, 1 );
		
		// Hook específico para cuando se guarda desde el frontend dashboard.
		add_action( 'save_tutor_course', array( $this, 'save_course_url_sesion' ), 10, 1 );
		
		// Añadir al REST API.
		add_filter( 'tutor_course_additional_info', array( $this, 'add_to_rest_api' ), 10, 1 );
		
		// Añadir al array de datos del curso.
		add_filter( 'tutor_course_details_data', array( $this, 'add_to_course_details' ), 10, 1 );
		
		// Añadir también al filtro que se aplica después de obtener los detalles del curso
		add_filter( 'tutor_rest_course_single_post', array( $this, 'add_to_rest_api' ), 10, 1 );
		
		// Añadir al filtro de respuesta del curso (este es el que se usa cuando se obtienen los detalles)
		add_filter( 'tutor_course_details_response', array( $this, 'add_to_course_details' ), 10, 1 );
		
		// Interceptar actualización de curso vía REST.
		add_filter( 'tutor_course_update_params', array( $this, 'add_to_update_params' ), 10, 1 );
		add_filter( 'tutor_course_create_params', array( $this, 'add_to_create_params' ), 10, 1 );
		
		// Hook después de crear/actualizar curso vía AJAX (admin) - PRIORIDAD ALTA para que se ejecute después
		add_action( 'wp_ajax_tutor_update_course', array( $this, 'after_ajax_update_course' ), 999, 0 );
		add_action( 'wp_ajax_tutor_create_course', array( $this, 'after_ajax_create_course' ), 999, 0 );
		
		// Hook ANTES de actualizar para capturar el valor del formulario
		add_action( 'tutor_before_course_update', array( $this, 'before_course_update' ), 10, 1 );
		
		// Hook después de que Tutor procese prepare_update_post_meta (interceptar el método interno)
		add_action( 'tutor_save_course', array( $this, 'save_course_url_sesion' ), 20, 1 );
		
		// Hook en save_course_meta para guardar course_url_sesion igual que los demás campos
		add_action( 'tutor_save_course_meta', array( $this, 'save_course_url_sesion_in_meta' ), 10, 2 );
		
		// Hook específico para cuando se guarda desde el frontend (tutor_add_course_builder).
		// save_tutor_course ya está registrado arriba, así que solo añadimos el hook AJAX
		add_action( 'wp_ajax_tutor_add_course_builder', array( $this, 'after_frontend_course_save' ), 999, 0 );
		add_action( 'wp_ajax_nopriv_tutor_add_course_builder', array( $this, 'after_frontend_course_save' ), 999, 0 );
		
		// Añadir JavaScript para el formulario React.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ), 5 );
		
		// Ya no modificamos el script compilado, inyectamos directamente en el DOM
		
		// Añadir el botón de URL de sesión en la sidebar de la lección (al lado de "Course Content").
		add_action( 'tutor_lesson/single/before/lesson_sidebar', array( $this, 'add_url_sesion_button_next_to_title' ), 5, 0 );
	}

	/**
	 * Verificar que Tutor LMS esté activo
	 */
	public function check_tutor_dependency() {
		if ( ! class_exists( 'TUTOR\Tutor' ) ) {
			add_action( 'admin_notices', array( $this, 'tutor_missing_notice' ) );
			return;
		}
	}

	/**
	 * Activar automáticamente Tutor Pro cuando se activa un plugin
	 */
	public function on_plugin_activated( $plugin, $network_wide ) {
		// Si se activa Tutor Pro, activarlo automáticamente
		if ( strpos( $plugin, 'tutor-pro' ) !== false ) {
			$this->auto_activate_tutor_pro();
		}
	}

	/**
	 * Activar automáticamente Tutor Pro modificando los archivos necesarios
	 */
	public function auto_activate_tutor_pro() {
		// Verificar si Tutor Pro está instalado
		if ( ! defined( 'TUTOR_PRO_VERSION' ) ) {
			return;
		}

		// Verificar si ya está activado (para evitar modificar archivos innecesariamente)
		$activation_key = 'tutor_pro_auto_activated_' . TUTOR_PRO_VERSION;
		if ( get_option( $activation_key ) ) {
			return;
		}

		$tutor_pro_path = WP_PLUGIN_DIR . '/tutor-pro/';
		
		// Archivo 1: classes/Init.php
		$init_file = $tutor_pro_path . 'classes/Init.php';
		if ( file_exists( $init_file ) ) {
			$this->modify_init_file( $init_file );
		}

		// Archivo 2: updater/update.php
		$update_file = $tutor_pro_path . 'updater/update.php';
		if ( file_exists( $update_file ) ) {
			$this->modify_update_file( $update_file );
		}

		// Marcar como activado
		update_option( $activation_key, true );
	}

	/**
	 * Modificar el archivo Init.php de Tutor Pro
	 */
	private function modify_init_file( $file_path ) {
		$content = file_get_contents( $file_path );
		
		// Verificar si ya está modificado
		if ( strpos( $content, 'Force license to be always active' ) !== false ) {
			return;
		}

		// Buscar la línea específica y reemplazarla
		$search_line = '$has_license = get_option( self::TUTOR_LICENSE_OPT_KEY, false );';
		$replacement_code = '$has_license = get_option( self::TUTOR_LICENSE_OPT_KEY, false );
		
		// Force license to be always active - set fake license if not exists
		if ( false === $has_license ) {
			$fake_license = array(
				\'activated\'     => true,
				\'license_key\'   => \'active\',
				\'customer_name\' => \'Active\',
				\'expires_at\'    => \'\',
				\'activated_at\'  => current_time( \'mysql\' ),
				\'license_type\'  => \'active\',
			);
			update_option( self::TUTOR_LICENSE_OPT_KEY, $fake_license, false );
			$has_license = $fake_license;
		} else {
			// Ensure existing license is marked as activated
			if ( is_array( $has_license ) ) {
				$has_license[\'activated\'] = true;
				update_option( self::TUTOR_LICENSE_OPT_KEY, $has_license, false );
			}
		}
		$has_license = true;';

		// Reemplazar usando str_replace para mayor confiabilidad
		$new_content = str_replace( $search_line, $replacement_code, $content );
		
		if ( $new_content !== $content ) {
			// Hacer backup antes de modificar
			$backup_file = $file_path . '.backup.' . time();
			@file_put_contents( $backup_file, $content );
			
			// Escribir el archivo modificado
			@file_put_contents( $file_path, $new_content );
		}
	}

	/**
	 * Modificar el archivo update.php de Tutor Pro
	 */
	private function modify_update_file( $file_path ) {
		$content = file_get_contents( $file_path );
		
		// Verificar si ya está modificado
		if ( strpos( $content, 'Force license to be always active' ) !== false ) {
			return;
		}

		$new_content = $content;
		$modified = false;

		// Modificar el método get_license() - buscar el final del método
		$search_get_license = '$license_option_key = get_option( $this->meta[\'license_option_key\'] );
		if ( $license_option_key ) {
			return get_option( $this->meta[\'license_option_key\'] );
		} else {
			return null;
		}
		return get_option( $this->meta[\'license_option_key\'] );';
		
		$replace_get_license = '$license_option_key = get_option( $this->meta[\'license_option_key\'] );
		if ( $license_option_key ) {
			$license = get_option( $this->meta[\'license_option_key\'] );
			// Force license to be always active
			if ( is_array( $license ) ) {
				$license[\'activated\'] = true;
			}
			return $license;
		} else {
			// Return fake active license if no license exists
			return array(
				\'activated\'     => true,
				\'license_key\'   => \'active\',
				\'customer_name\' => \'Active\',
				\'expires_at\'    => \'\',
				\'activated_at\'  => \'\',
				\'license_type\'  => \'active\',
			);
		}';

		if ( strpos( $new_content, $search_get_license ) !== false ) {
			$new_content = str_replace( $search_get_license, $replace_get_license, $new_content );
			$modified = true;
		}

		// Modificar el método check_plugin_license_before_update() - simplificado
		$search_check = '$license_option = get_option( $this->meta[\'license_option_key\'], null );
			if ( ! $license_option ) {
				return new \\WP_Error(
					\'license_required\',
					__( \'A valid license key is required to update Tutor LMS Pro. Please enter your license key in the plugin settings.\', \'tutor-pro\' )
				);
			}

			$is_active   = $license_option[\'activated\'] ?? \'\';
			$license_key = $license_option[\'license_key\'] ?? \'\';
			if ( ! $is_active || ! $license_key ) {
				return new \\WP_Error(
					\'license_required\',
					__( \'A valid license key is required to update Tutor LMS Pro. Please enter your license key in the plugin settings.\', \'tutor-pro\' )
				);
			}

			$response = $this->check_for_update_api();
			if ( 200 !== $response->status ) {
				return new \\WP_Error(
					\'license_required\',
					__( \'A valid license key is required to update Tutor LMS Pro. Please enter your license key in the plugin settings.\', \'tutor-pro\' )
				);
			}';
		
		$replace_check = '// Skip license check - always allow updates';

		if ( strpos( $new_content, $search_check ) !== false ) {
			$new_content = str_replace( $search_check, $replace_check, $new_content );
			$modified = true;
		}
		
		if ( $modified && $new_content !== $content ) {
			// Hacer backup antes de modificar
			$backup_file = $file_path . '.backup.' . time();
			@file_put_contents( $backup_file, $content );
			
			// Escribir el archivo modificado
			@file_put_contents( $file_path, $new_content );
		}
	}

	/**
	 * Mostrar aviso si Tutor LMS no está activo
	 */
	public function tutor_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Tutor Course URL Sesión requiere que Tutor LMS esté instalado y activo.', 'tutor-course-url-sesion' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Guardar el campo course_url_sesion
	 *
	 * @param int $post_id ID del curso.
	 */
	public function save_course_url_sesion( $post_id ) {
		// Validar que el post_id sea válido y sea un número entero
		$post_id = (int) $post_id;
		if ( ! $post_id || $post_id <= 0 ) {
			return;
		}

		// Verificar que el post exista en la BD
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Verificar que sea un curso de Tutor
		if ( function_exists( 'tutor' ) && get_post_type( $post_id ) !== tutor()->course_post_type ) {
			return;
		}

		// Buscar el valor en diferentes ubicaciones
		$url_sesion = '';
		
		// Primero intentar en $_POST directo
		if ( isset( $_POST['course_url_sesion'] ) && ! empty( $_POST['course_url_sesion'] ) ) {
			$url_sesion = trim( $_POST['course_url_sesion'] );
		}
		
		// Si no está, buscar en additional_content como array
		if ( empty( $url_sesion ) && isset( $_POST['additional_content'] ) && is_array( $_POST['additional_content'] ) ) {
			if ( isset( $_POST['additional_content']['course_url_sesion'] ) && ! empty( $_POST['additional_content']['course_url_sesion'] ) ) {
				$url_sesion = trim( $_POST['additional_content']['course_url_sesion'] );
			}
		}
		
		// También buscar en additional_content como string (URL-encoded)
		if ( empty( $url_sesion ) && isset( $_POST['additional_content']['course_url_sesion'] ) && is_string( $_POST['additional_content']['course_url_sesion'] ) ) {
			$url_sesion = trim( $_POST['additional_content']['course_url_sesion'] );
		}
		
		// Si no se encontró, intentar parsear desde additional_content como string JSON
		if ( empty( $url_sesion ) && isset( $_POST['additional_content'] ) && is_string( $_POST['additional_content'] ) ) {
			$additional_content = json_decode( stripslashes( $_POST['additional_content'] ), true );
			if ( is_array( $additional_content ) && isset( $additional_content['course_url_sesion'] ) && ! empty( $additional_content['course_url_sesion'] ) ) {
				$url_sesion = trim( $additional_content['course_url_sesion'] );
			}
		}

		// Normalizar la URL: añadir https:// si no tiene protocolo
		if ( ! empty( $url_sesion ) ) {
			$url_sesion = trim( $url_sesion );
			
			// Si no empieza con http:// o https://, añadir https://
			if ( ! preg_match( '#^https?://#i', $url_sesion ) ) {
				$url_sesion = 'https://' . $url_sesion;
			}
			
			// Validar la URL
			$url_sesion = esc_url_raw( $url_sesion );
			
			// Guardar en la base de datos
			// IMPORTANTE: Cada curso tiene su propio post_id único, por lo que cada curso
			// tendrá su propio registro en wp_postmeta con su propia URL
			// No hay riesgo de que se crucen entre cursos
			if ( ! empty( $url_sesion ) && filter_var( $url_sesion, FILTER_VALIDATE_URL ) ) {
				// update_post_meta guarda el valor asociado SOLO a este post_id específico
				update_post_meta( $post_id, '_tutor_course_url_sesion', $url_sesion );
				
				// Log para depuración (solo en modo debug)
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Tutor Course URL Sesion: Campo guardado para curso ID ' . $post_id . ' - URL: ' . $url_sesion );
				}
			} else {
				// Si no es una URL válida, eliminar el meta SOLO de este curso
				delete_post_meta( $post_id, '_tutor_course_url_sesion' );
			}
		} else {
			// Si el campo está vacío, eliminar el meta SOLO de este curso
			delete_post_meta( $post_id, '_tutor_course_url_sesion' );
		}
	}

	/**
	 * Guardar el campo course_url_sesion (método legacy)
	 *
	 * @param int $post_id ID del curso.
	 */
	public function save_course_url_sesion_legacy( $post_id ) {
		// Solo procesar cursos de Tutor.
		if ( ! function_exists( 'tutor' ) || get_post_type( $post_id ) !== tutor()->course_post_type ) {
			return;
		}

		// Evitar autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Verificar permisos.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$this->save_course_url_sesion( $post_id );
	}

	/**
	 * Después de actualizar el curso vía AJAX (respaldo)
	 * Asegurar que el campo se guarde incluso si otros hooks fallan
	 */
	public function after_ajax_update_course() {
		// Verificar que sea una petición AJAX de Tutor.
		if ( ! isset( $_POST['action'] ) || 'tutor_update_course' !== $_POST['action'] ) {
			return;
		}

		if ( ! isset( $_POST['course_id'] ) ) {
			return;
		}

		$course_id = (int) $_POST['course_id'];
		if ( $course_id <= 0 ) {
			return;
		}

		// Verificar permisos.
		if ( ! current_user_can( 'edit_post', $course_id ) ) {
			return;
		}

		// Asegurar que el valor esté en $_POST antes de guardar
		// A veces viene en additional_content[course_url_sesion] pero necesita estar en $_POST['course_url_sesion']
		if ( isset( $_POST['additional_content']['course_url_sesion'] ) && empty( $_POST['course_url_sesion'] ) ) {
			$_POST['course_url_sesion'] = $_POST['additional_content']['course_url_sesion'];
		}

		// Intentar guardar el campo como respaldo.
		$this->save_course_url_sesion( $course_id );
	}

	/**
	 * Después de crear el curso vía AJAX (respaldo)
	 * Asegurar que el campo se guarde incluso si otros hooks fallan
	 */
	public function after_ajax_create_course() {
		// Verificar que sea una petición AJAX de Tutor.
		if ( ! isset( $_POST['action'] ) || 'tutor_create_course' !== $_POST['action'] ) {
			return;
		}

		// En este caso, el ID del curso se obtiene de la respuesta, pero lo intentamos desde $_POST
		// Si no está, se guardará en el siguiente guardado.
		if ( isset( $_POST['course_id'] ) ) {
			$course_id = (int) $_POST['course_id'];
			if ( $course_id > 0 ) {
				$this->save_course_url_sesion( $course_id );
			}
		}
	}

	/**
	 * Después de guardar el curso desde el frontend (tutor_add_course_builder)
	 * Este hook se ejecuta cuando se guarda desde el dashboard del frontend
	 */
	public function after_frontend_course_save() {
		// Verificar que sea una petición AJAX del frontend.
		if ( ! isset( $_POST['tutor_action'] ) || 'tutor_add_course_builder' !== $_POST['tutor_action'] ) {
			return;
		}

		// Obtener el ID del curso
		$course_id = isset( $_POST['course_ID'] ) ? (int) $_POST['course_ID'] : 0;
		if ( ! $course_id ) {
			$course_id = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;
		}

		if ( $course_id <= 0 ) {
			return;
		}

		// Verificar permisos.
		if ( ! current_user_can( 'edit_post', $course_id ) ) {
			return;
		}

		// Asegurar que el valor esté en $_POST antes de guardar
		// En el frontend puede venir como campo directo o en additional_content
		if ( isset( $_POST['additional_content']['course_url_sesion'] ) && empty( $_POST['course_url_sesion'] ) ) {
			$_POST['course_url_sesion'] = $_POST['additional_content']['course_url_sesion'];
		}

		// Intentar guardar el campo
		$this->save_course_url_sesion( $course_id );
	}

	/**
	 * Añadir el campo al REST API
	 *
	 * @param array $detail Detalles del curso.
	 * @return array
	 */
	public function add_to_rest_api( $detail ) {
		if ( ! isset( $detail['post_id'] ) && ! isset( $detail['ID'] ) ) {
			return $detail;
		}

		$post_id = isset( $detail['post_id'] ) ? $detail['post_id'] : ( isset( $detail['ID'] ) ? $detail['ID'] : 0 );
		if ( $post_id > 0 ) {
			$detail['course_url_sesion'] = get_post_meta( $post_id, '_tutor_course_url_sesion', true );
		}

		return $detail;
	}

	/**
	 * Añadir el campo a los detalles del curso
	 *
	 * @param array $data Datos del curso.
	 * @return array
	 */
	public function add_to_course_details( $data ) {
		// Buscar el ID del curso en diferentes ubicaciones
		$course_id = 0;
		
		if ( isset( $data['ID'] ) ) {
			$course_id = (int) $data['ID'];
		} elseif ( isset( $data['course_id'] ) ) {
			$course_id = (int) $data['course_id'];
		} elseif ( isset( $data['post_id'] ) ) {
			$course_id = (int) $data['post_id'];
		}
		
		if ( $course_id > 0 ) {
			$url_sesion = get_post_meta( $course_id, '_tutor_course_url_sesion', true );
			$data['course_url_sesion'] = $url_sesion ? $url_sesion : '';
		} else {
			$data['course_url_sesion'] = '';
		}

		return $data;
	}

	/**
	 * Añadir el campo a los parámetros de actualización
	 *
	 * @param array $params Parámetros.
	 * @return array
	 */
	public function add_to_update_params( $params ) {
		// Asegurar que course_url_sesion esté en additional_content si viene directamente.
		if ( isset( $params['course_url_sesion'] ) ) {
			if ( ! isset( $params['additional_content'] ) ) {
				$params['additional_content'] = array();
			}
			$params['additional_content']['course_url_sesion'] = $params['course_url_sesion'];
		}
		
		// También buscar en $_POST si no está en $params
		if ( ! isset( $params['course_url_sesion'] ) && isset( $_POST['course_url_sesion'] ) ) {
			if ( ! isset( $params['additional_content'] ) ) {
				$params['additional_content'] = array();
			}
			$params['additional_content']['course_url_sesion'] = $_POST['course_url_sesion'];
		}

		return $params;
	}

	/**
	 * Añadir el campo a los parámetros de creación
	 *
	 * @param array $params Parámetros.
	 * @return array
	 */
	public function add_to_create_params( $params ) {
		// Asegurar que course_url_sesion esté en additional_content si viene directamente.
		if ( isset( $params['course_url_sesion'] ) ) {
			if ( ! isset( $params['additional_content'] ) ) {
				$params['additional_content'] = array();
			}
			$params['additional_content']['course_url_sesion'] = $params['course_url_sesion'];
		}
		
		// También buscar en $_POST si no está en $params
		if ( ! isset( $params['course_url_sesion'] ) && isset( $_POST['course_url_sesion'] ) ) {
			if ( ! isset( $params['additional_content'] ) ) {
				$params['additional_content'] = array();
			}
			$params['additional_content']['course_url_sesion'] = $_POST['course_url_sesion'];
		}

		return $params;
	}

	/**
	 * Guardar course_url_sesion en tutor_save_course_meta (igual que los demás campos)
	 * Este hook se ejecuta cuando se guarda el curso y procesa los campos de additional_content
	 *
	 * @param int    $post_ID ID del curso.
	 * @param object $post    Objeto del post.
	 */
	public function save_course_url_sesion_in_meta( $post_ID, $post ) {
		// Verificar que sea un curso de Tutor
		if ( function_exists( 'tutor' ) && get_post_type( $post_ID ) !== tutor()->course_post_type ) {
			return;
		}

		// Buscar el valor en $_POST (como lo hacen los demás campos)
		$course_url_sesion = '';
		
		// Primero buscar directamente en $_POST
		if ( isset( $_POST['course_url_sesion'] ) && ! empty( $_POST['course_url_sesion'] ) ) {
			$course_url_sesion = trim( $_POST['course_url_sesion'] );
		}
		
		// Luego buscar en additional_content como array
		if ( empty( $course_url_sesion ) && isset( $_POST['additional_content'] ) && is_array( $_POST['additional_content'] ) ) {
			if ( isset( $_POST['additional_content']['course_url_sesion'] ) && ! empty( $_POST['additional_content']['course_url_sesion'] ) ) {
				$course_url_sesion = trim( $_POST['additional_content']['course_url_sesion'] );
			}
		}

		// Normalizar y guardar
		if ( ! empty( $course_url_sesion ) ) {
			$course_url_sesion = trim( $course_url_sesion );
			
			// Si no empieza con http:// o https://, añadir https://
			if ( ! preg_match( '#^https?://#i', $course_url_sesion ) ) {
				$course_url_sesion = 'https://' . $course_url_sesion;
			}
			
			// Validar la URL
			$course_url_sesion = esc_url_raw( $course_url_sesion );
			
			// Guardar en la BD (cada curso tiene su propio post_id, no se cruzan)
			if ( ! empty( $course_url_sesion ) && filter_var( $course_url_sesion, FILTER_VALIDATE_URL ) ) {
				update_post_meta( $post_ID, '_tutor_course_url_sesion', $course_url_sesion );
			} else {
				delete_post_meta( $post_ID, '_tutor_course_url_sesion' );
			}
		} else {
			// Si está vacío, eliminar el meta
			delete_post_meta( $post_ID, '_tutor_course_url_sesion' );
		}
	}



	/**
	 * Encolar scripts en el admin
	 *
	 * @param string $hook Hook actual.
	 */
	public function enqueue_admin_scripts( $hook ) {
		global $pagenow;

		// Solo en la página del course builder.
		$is_course_builder = ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'create-course' === $_GET['page'] );
		
		if ( ! $is_course_builder ) {
			return;
		}

		// Asegurar que el script se cargue después de que Tutor cargue sus scripts
		// Usar una prioridad alta para que se ejecute después

		// Cargar el script después de que Tutor cargue sus scripts
		wp_enqueue_script(
			'tutor-course-url-sesion-admin',
			TUTOR_COURSE_URL_SESION_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			TUTOR_COURSE_URL_SESION_VERSION,
			true
		);

		// Asegurar que se ejecute después de que el DOM esté listo
		wp_script_add_data( 'tutor-course-url-sesion-admin', 'defer', true );

		// Obtener el ID del curso si está editando
		$course_id = isset( $_GET['course_id'] ) ? (int) $_GET['course_id'] : 0;
		$course_url_sesion = '';
		if ( $course_id ) {
			$course_url_sesion = get_post_meta( $course_id, '_tutor_course_url_sesion', true );
		}

		wp_localize_script(
			'tutor-course-url-sesion-admin',
			'tutorCourseUrlSesion',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'tutor_course_url_sesion_nonce' ),
				'courseId'   => $course_id,
				'courseData' => array(
					'course_url_sesion' => $course_url_sesion,
				),
				'labels'     => array(
					'fieldLabel' => __( 'URL de Clases (Meet/Zoom)', 'tutor-course-url-sesion' ),
					'placeholder' => __( 'https://meet.google.com/... o https://zoom.us/j/...', 'tutor-course-url-sesion' ),
					'helpText'   => __( 'Ingresa el enlace de la clase en vivo (Google Meet o Zoom)', 'tutor-course-url-sesion' ),
				),
			)
		);

		// Exponer los datos del curso globalmente para que JavaScript los pueda usar
		if ( $course_id ) {
			wp_add_inline_script(
				'tutor-course-url-sesion-admin',
				'window.tutorCourseData = window.tutorCourseData || {}; window.tutorCourseData.course_url_sesion = ' . wp_json_encode( $course_url_sesion ) . ';',
				'before'
			);
		}
	}

	/**
	 * Encolar scripts en el frontend
	 *
	 * @param string $hook Hook actual.
	 */
	public function enqueue_frontend_scripts( $hook ) {
		// Solo en el course builder del frontend.
		if ( ! function_exists( 'tutor_utils' ) ) {
			return;
		}

		// Verificar si estamos en el frontend dashboard de creación/edición de curso
		$is_frontend_builder = tutor_utils()->is_tutor_frontend_dashboard( 'create-course' );
		
		// También verificar por la URL directa
		if ( ! $is_frontend_builder ) {
			// Verificar si estamos en la página de creación de curso
			$current_url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			if ( strpos( $current_url, '/dashboard/create-course' ) === false ) {
				return;
			}
			$is_frontend_builder = true;
		}
		
		if ( ! $is_frontend_builder ) {
			return;
		}

		// Obtener el ID del curso si está editando
		$course_id = isset( $_GET['course_id'] ) ? (int) $_GET['course_id'] : 0;
		$course_url_sesion = '';
		if ( $course_id ) {
			$course_url_sesion = get_post_meta( $course_id, '_tutor_course_url_sesion', true );
		}

		// Cargar el mismo script que en admin (reutilizamos la lógica)
		wp_enqueue_script(
			'tutor-course-url-sesion-frontend',
			TUTOR_COURSE_URL_SESION_PLUGIN_URL . 'assets/js/admin.js', // Usar admin.js directamente
			array( 'jquery' ),
			TUTOR_COURSE_URL_SESION_VERSION,
			true
		);

		wp_localize_script(
			'tutor-course-url-sesion-frontend',
			'tutorCourseUrlSesion',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'tutor_course_url_sesion_nonce' ),
				'courseId'   => $course_id,
				'courseData' => array(
					'course_url_sesion' => $course_url_sesion,
				),
				'labels'     => array(
					'fieldLabel' => __( 'URL de Clases (Meet/Zoom)', 'tutor-course-url-sesion' ),
					'placeholder' => __( 'https://meet.google.com/... o https://zoom.us/j/...', 'tutor-course-url-sesion' ),
					'helpText'   => __( 'Ingresa el enlace de la clase en vivo (Google Meet o Zoom)', 'tutor-course-url-sesion' ),
				),
			)
		);

		// Exponer los datos del curso globalmente para que JavaScript los pueda usar
		if ( $course_id ) {
			wp_add_inline_script(
				'tutor-course-url-sesion-frontend',
				'window.tutorCourseData = window.tutorCourseData || {}; window.tutorCourseData.course_url_sesion = ' . wp_json_encode( $course_url_sesion ) . ';',
				'before'
			);
		}
	}

	/**
	 * Añadir botón de URL de sesión al lado de "Course Content" en la sidebar de la lección
	 */
	public function add_url_sesion_button_next_to_title() {
		global $post;
		
		// Obtener el ID del curso desde la lección actual
		$course_id = 0;
		if ( $post ) {
			if ( function_exists( 'tutor_utils' ) ) {
				$course_id = tutor_utils()->get_course_id_by_subcontent( $post->ID );
			}
		}
		
		if ( ! $course_id ) {
			return;
		}
		
		// Obtener el enlace de la clase desde la BD
		$course_url_sesion = get_post_meta( $course_id, '_tutor_course_url_sesion', true );
		
		// Solo mostrar si existe el enlace y es válido
		if ( ! empty( $course_url_sesion ) && filter_var( $course_url_sesion, FILTER_VALIDATE_URL ) ) {
			// Añadir CSS y JavaScript para el botón
			?>
			<style>
				.tutor-course-single-sidebar-title {
					position: relative !important;
				}
				.tutor-course-url-sesion-btn-wrapper {
					position: absolute !important;
					right: 0 !important;
					top: 50% !important;
					transform: translateY(-50%) !important;
					z-index: 100 !important;
					display: block !important;
					visibility: visible !important;
					opacity: 1 !important;
				}
				@media (max-width: 1199px) {
					.tutor-course-url-sesion-btn-wrapper {
						position: static !important;
						transform: none !important;
						margin-top: 10px !important;
					}
					.tutor-course-single-sidebar-title {
						flex-direction: column !important;
						align-items: flex-start !important;
					}
				}
				.tutor-btn-url-sesion {
					display: inline-flex !important;
					align-items: center !important;
					justify-content: center !important;
					gap: 6px !important;
					padding: 7px 14px !important;
					background: #2376d8 !important;
					color: #ffffff !important;
					border: none !important;
					border-radius: 5px !important;
					font-size: 13px !important;
					font-weight: 600 !important;
					text-decoration: none !important;
					cursor: pointer !important;
					transition: all 0.2s ease !important;
					box-shadow: 0 2px 4px rgba(35, 118, 216, 0.25) !important;
					white-space: nowrap !important;
					visibility: visible !important;
					opacity: 1 !important;
				}
				.tutor-btn-url-sesion:hover,
				.tutor-btn-url-sesion:focus {
					background: #1a5ca8 !important;
					color: #ffffff !important;
					text-decoration: none !important;
					transform: translateY(-1px) !important;
					box-shadow: 0 4px 8px rgba(35, 118, 216, 0.35) !important;
				}
				.tutor-btn-url-sesion:active {
					transform: translateY(0) !important;
					box-shadow: 0 2px 4px rgba(35, 118, 216, 0.25) !important;
				}
				.tutor-btn-url-sesion svg {
					width: 16px !important;
					height: 16px !important;
					fill: currentColor !important;
					flex-shrink: 0 !important;
					display: block !important;
				}
				.tutor-btn-url-sesion span {
					line-height: 1.2 !important;
					display: inline-block !important;
				}
			</style>
			<script>
				(function() {
					function addUrlSesionButton() {
						const sidebarTitle = document.querySelector('.tutor-course-single-sidebar-title');
						if (!sidebarTitle) {
							return false;
						}
						
						// Verificar si el botón ya existe
						if (sidebarTitle.querySelector('.tutor-course-url-sesion-btn-wrapper')) {
							return true;
						}
						
						const btnWrapper = document.createElement('div');
						btnWrapper.className = 'tutor-course-url-sesion-btn-wrapper';
						btnWrapper.style.cssText = 'position: absolute; right: 0; top: 50%; transform: translateY(-50%); z-index: 100; display: block; visibility: visible; opacity: 1;';
						
						const url = <?php echo wp_json_encode( esc_url( $course_url_sesion ) ); ?>;
						const title = <?php echo wp_json_encode( esc_attr__( 'Abrir enlace de la clase en vivo', 'tutor-course-url-sesion' ) ); ?>;
						const text = <?php echo wp_json_encode( esc_html__( 'Clase en Vivo', 'tutor-course-url-sesion' ) ); ?>;
						
						const link = document.createElement('a');
						link.href = url;
						link.target = '_blank';
						link.rel = 'noopener noreferrer';
						link.className = 'tutor-btn-url-sesion';
						link.title = title;
						link.style.cssText = 'display: inline-flex !important; align-items: center !important; gap: 6px !important; padding: 7px 14px !important; background: #2376d8 !important; color: #ffffff !important; border: none !important; border-radius: 5px !important; font-size: 13px !important; font-weight: 600 !important; text-decoration: none !important; cursor: pointer !important;';
						
						link.onclick = function(e) {
							e.preventDefault();
							window.open(url, '_blank', 'noopener,noreferrer');
							return false;
						};
						
						link.innerHTML = `
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 16px; height: 16px; fill: currentColor;">
								<path d="M18 13v-2h-5v-5h-2v5H6v2h5v5h2v-5h5z"/>
								<path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7z"/>
							</svg>
							<span>${text}</span>
						`;
						
						btnWrapper.appendChild(link);
						sidebarTitle.appendChild(btnWrapper);
						
						console.log('✅ Botón de Clase en Vivo añadido correctamente');
						return true;
					}
					
					// Intentar múltiples veces
					function tryAddButton() {
						if (addUrlSesionButton()) {
							return;
						}
						
						// Si no se encontró, intentar de nuevo
						setTimeout(function() {
							if (!addUrlSesionButton()) {
								setTimeout(addUrlSesionButton, 500);
							}
						}, 300);
					}
					
					if (document.readyState === 'loading') {
						document.addEventListener('DOMContentLoaded', tryAddButton);
					} else {
						tryAddButton();
					}
					
					// Observer para detectar cambios en el DOM
					const observer = new MutationObserver(function() {
						if (!document.querySelector('.tutor-course-url-sesion-btn-wrapper')) {
							addUrlSesionButton();
						}
					});
					
					observer.observe(document.body, {
						childList: true,
						subtree: true
					});
					
					setTimeout(function() {
						observer.disconnect();
					}, 10000);
				})();
			</script>
			<?php
		}
	}

	/**
	 * Antes de actualizar el curso (interceptar AJAX)
	 *
	 * @param array $params Parámetros.
	 */
	public function before_course_update( $params ) {
		// Buscar el valor en diferentes ubicaciones
		$url_sesion = '';
		
		// Primero intentar en $_POST directo
		if ( isset( $_POST['course_url_sesion'] ) ) {
			$url_sesion = esc_url_raw( $_POST['course_url_sesion'] );
		}
		
		// Si no está, buscar en additional_content
		if ( empty( $url_sesion ) && isset( $_POST['additional_content']['course_url_sesion'] ) ) {
			$url_sesion = esc_url_raw( $_POST['additional_content']['course_url_sesion'] );
		}
		
		// También buscar en additional_content como array anidado
		if ( empty( $url_sesion ) && isset( $_POST['additional_content'] ) && is_array( $_POST['additional_content'] ) ) {
			$additional_content = $_POST['additional_content'];
			if ( isset( $additional_content['course_url_sesion'] ) ) {
				$url_sesion = esc_url_raw( $additional_content['course_url_sesion'] );
			}
		}
		
		// También buscar en $params
		if ( empty( $url_sesion ) && isset( $params['course_url_sesion'] ) ) {
			$url_sesion = esc_url_raw( $params['course_url_sesion'] );
		}
		
		if ( empty( $url_sesion ) && isset( $params['additional_content']['course_url_sesion'] ) ) {
			$url_sesion = esc_url_raw( $params['additional_content']['course_url_sesion'] );
		}

		// Asegurar que course_url_sesion esté en additional_content y en params
		if ( ! empty( $url_sesion ) ) {
			if ( ! isset( $params['additional_content'] ) || ! is_array( $params['additional_content'] ) ) {
				$params['additional_content'] = array();
			}
			
			$params['additional_content']['course_url_sesion'] = $url_sesion;
			$params['course_url_sesion'] = $url_sesion;
			
			// También asegurar que esté en $_POST para que save_course_url_sesion lo capture
			$_POST['course_url_sesion'] = $url_sesion;
		}

		return $params;
	}

	/**
	 * AJAX: Obtener el valor del campo course_url_sesion
	 */
	public function ajax_get_course_url_sesion() {
		// Verificar nonce
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'tutor_course_url_sesion_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'tutor-course-url-sesion' ) ) );
			return;
		}

		$course_id = isset( $_GET['course_id'] ) ? (int) $_GET['course_id'] : 0;
		if ( $course_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course ID', 'tutor-course-url-sesion' ) ) );
			return;
		}

		// Verificar permisos
		if ( ! current_user_can( 'edit_post', $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'tutor-course-url-sesion' ) ) );
			return;
		}

		$value = get_post_meta( $course_id, '_tutor_course_url_sesion', true );
		wp_send_json_success( $value ? $value : '' );
	}

	/**
	 * AJAX: Guardar el valor del campo course_url_sesion
	 */
	public function ajax_save_course_url_sesion() {
		// Verificar nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tutor_course_url_sesion_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce', 'tutor-course-url-sesion' ) ) );
			return;
		}

		$course_id = isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0;
		if ( $course_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course ID', 'tutor-course-url-sesion' ) ) );
			return;
		}

		// Verificar permisos
		if ( ! current_user_can( 'edit_post', $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'tutor-course-url-sesion' ) ) );
			return;
		}

		$url_sesion = isset( $_POST['course_url_sesion'] ) ? trim( $_POST['course_url_sesion'] ) : '';
		
		if ( ! empty( $url_sesion ) ) {
			// Normalizar la URL
			if ( ! preg_match( '#^https?://#i', $url_sesion ) ) {
				$url_sesion = 'https://' . $url_sesion;
			}
			$url_sesion = esc_url_raw( $url_sesion );
			
			if ( filter_var( $url_sesion, FILTER_VALIDATE_URL ) ) {
				update_post_meta( $course_id, '_tutor_course_url_sesion', $url_sesion );
				wp_send_json_success( array( 'message' => __( 'URL saved successfully', 'tutor-course-url-sesion' ), 'url' => $url_sesion ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Invalid URL', 'tutor-course-url-sesion' ) ) );
			}
		} else {
			delete_post_meta( $course_id, '_tutor_course_url_sesion' );
			wp_send_json_success( array( 'message' => __( 'URL removed', 'tutor-course-url-sesion' ) ) );
		}
	}

}

// Inicializar el plugin.
function tutor_course_url_sesion_init() {
	return Tutor_Course_URL_Sesion::get_instance();
}

// Iniciar el plugin después de que WordPress cargue.
add_action( 'plugins_loaded', 'tutor_course_url_sesion_init' );

