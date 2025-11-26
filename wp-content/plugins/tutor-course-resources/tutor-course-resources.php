<?php
/**
 * Plugin Name: Tutor Course Resources
 * Plugin URI: https://example.com/tutor-course-resources
 * Description: Gestión de recursos de curso con Google Drive y archivos físicos para Tutor LMS. Control de acceso por roles y notificaciones.
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tutor-course-resources
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TUTOR_COURSE_RESOURCES_VERSION', '1.0.0' );
define( 'TUTOR_COURSE_RESOURCES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TUTOR_COURSE_RESOURCES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

class Tutor_Course_Resources {
	
	private static $instance = null;
	public $table_name = '';
	public $folders_table_name = '';
	public $folders_lessons_table_name = '';
	public $resources_lessons_table_name = '';
	
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}
	
	public function init() {
		if ( ! $this->is_tutor_lms_active() ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_missing_tutor' ) );
			return;
		}
		
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'tutor_course_resources';
		$this->folders_table_name = $wpdb->prefix . 'tutor_course_resources_folders';
		$this->folders_lessons_table_name = $wpdb->prefix . 'tutor_course_resources_folder_lessons';
		$this->resources_lessons_table_name = $wpdb->prefix . 'tutor_course_resources_resource_lessons';
		
		$this->init_database();
		$this->init_admin_menus();
		$this->init_hooks();
		$this->init_ajax_handlers();
		$this->init_file_upload_modifications();
		$this->init_frontend();
	}
	
	/**
	 * Verificar si Tutor LMS está activo
	 */
	private function is_tutor_lms_active() {
		return class_exists( 'TUTOR\Tutor' ) || defined( 'TUTOR_VERSION' );
	}
	
	/**
	 * Aviso si Tutor LMS no está activo
	 */
	public function admin_notice_missing_tutor() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Tutor Course Resources requiere que Tutor LMS esté instalado y activado.', 'tutor-course-resources' ); ?></p>
		</div>
		<?php
	}
	
	/**
	 * Inicializar base de datos
	 */
	private function init_database() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		// Tabla de carpetas
		$folders_table = $wpdb->prefix . 'tutor_course_resources_folders';
		$folders_sql = "CREATE TABLE IF NOT EXISTS {$folders_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			parent_id bigint(20) UNSIGNED DEFAULT 0,
			course_id bigint(20) UNSIGNED DEFAULT 0,
			name varchar(255) NOT NULL,
			description text,
			is_libre tinyint(1) DEFAULT 0,
			access_students tinyint(1) DEFAULT 1,
			access_teachers tinyint(1) DEFAULT 0,
			access_teachers_list text,
			access_students_list text,
			created_by bigint(20) UNSIGNED NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY parent_id (parent_id),
			KEY course_id (course_id),
			KEY created_by (created_by)
		) $charset_collate;";
		
		// Tabla de recursos (archivos y enlaces)
		$sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			folder_id bigint(20) UNSIGNED DEFAULT 0,
			course_id bigint(20) UNSIGNED DEFAULT 0,
			resource_type varchar(20) NOT NULL DEFAULT 'file',
			resource_url text,
			file_id bigint(20) UNSIGNED DEFAULT NULL,
			title varchar(255) NOT NULL,
			description text,
			access_students tinyint(1) DEFAULT 1,
			access_teachers tinyint(1) DEFAULT 0,
			access_teachers_list text,
			access_students_list text,
			created_by bigint(20) UNSIGNED NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY folder_id (folder_id),
			KEY course_id (course_id),
			KEY created_by (created_by)
		) $charset_collate;";
		
		// Tabla de relación carpetas-lecciones (muchos a muchos)
		$folders_lessons_table = $wpdb->prefix . 'tutor_course_resources_folder_lessons';
		$folders_lessons_sql = "CREATE TABLE IF NOT EXISTS {$folders_lessons_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			folder_id bigint(20) UNSIGNED NOT NULL,
			lesson_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY folder_lesson (folder_id, lesson_id),
			KEY folder_id (folder_id),
			KEY lesson_id (lesson_id)
		) $charset_collate;";
		
		// Tabla de relación recursos-lecciones (muchos a muchos)
		$resources_lessons_table = $wpdb->prefix . 'tutor_course_resources_resource_lessons';
		$resources_lessons_sql = "CREATE TABLE IF NOT EXISTS {$resources_lessons_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			resource_id bigint(20) UNSIGNED NOT NULL,
			lesson_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY resource_lesson (resource_id, lesson_id),
			KEY resource_id (resource_id),
			KEY lesson_id (lesson_id)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $folders_sql );
		dbDelta( $sql );
		dbDelta( $folders_lessons_sql );
		dbDelta( $resources_lessons_sql );
		
		// Migrar tabla si tiene estructura antigua
		$this->migrate_table_if_needed();
	}
	
	/**
	 * Migrar tabla si tiene estructura antigua
	 */
	private function migrate_table_if_needed() {
		global $wpdb;
		
		// Verificar si la tabla existe
		$table_exists = $wpdb->get_var( $wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$this->table_name
		) ) === $this->table_name;
		
		if ( ! $table_exists ) {
			return;
		}
		
		// Verificar columnas de la tabla de recursos
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$this->table_name}" );
		$column_names = array();
		foreach ( $columns as $column ) {
			$column_names[] = $column->Field;
		}
		
		// Si tiene lesson_id pero no folder_id, migrar
		if ( in_array( 'lesson_id', $column_names ) && ! in_array( 'folder_id', $column_names ) ) {
			// Agregar columna folder_id
			$wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN folder_id bigint(20) UNSIGNED DEFAULT 0 AFTER course_id" );
			$wpdb->query( "ALTER TABLE {$this->table_name} ADD INDEX folder_id (folder_id)" );
		}
		
		// Agregar columna access_students_list si no existe
		if ( ! in_array( 'access_students_list', $column_names ) ) {
			$wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN access_students_list text AFTER access_teachers_list" );
		}
		
		// Verificar columnas de la tabla de carpetas
		$folders_columns = $wpdb->get_results( "SHOW COLUMNS FROM {$this->folders_table_name}" );
		$folders_column_names = array();
		foreach ( $folders_columns as $column ) {
			$folders_column_names[] = $column->Field;
		}
		
		// Agregar columna access_students_list si no existe
		if ( ! empty( $folders_column_names ) && ! in_array( 'access_students_list', $folders_column_names ) ) {
			$wpdb->query( "ALTER TABLE {$this->folders_table_name} ADD COLUMN access_students_list text AFTER access_teachers_list" );
		}
	}
	
	/**
	 * Activar plugin
	 */
	public function activate() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'tutor_course_resources';
		$this->folders_table_name = $wpdb->prefix . 'tutor_course_resources_folders';
		$this->folders_lessons_table_name = $wpdb->prefix . 'tutor_course_resources_folder_lessons';
		$this->resources_lessons_table_name = $wpdb->prefix . 'tutor_course_resources_resource_lessons';
		$this->init_database();
		flush_rewrite_rules();
	}
	
	/**
	 * Desactivar plugin
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}
	
	/**
	 * Inicializar menús de administración
	 */
	private function init_admin_menus() {
		add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );
	}
	
	/**
	 * Agregar menús de administración
	 */
	public function add_admin_menus() {
		add_menu_page(
			__( 'Recursos de Cursos', 'tutor-course-resources' ),
			__( 'Recursos de Cursos', 'tutor-course-resources' ),
			'manage_options',
			'tutor-course-resources',
			array( $this, 'render_resources_page' ),
			'dashicons-media-document',
			30
		);
		
		add_submenu_page(
			'tutor-course-resources',
			__( 'Todos los Recursos', 'tutor-course-resources' ),
			__( 'Todos los Recursos', 'tutor-course-resources' ),
			'manage_options',
			'tutor-course-resources',
			array( $this, 'render_resources_page' )
		);
		
		add_submenu_page(
			'tutor-course-resources',
			__( 'Añadir Recurso', 'tutor-course-resources' ),
			__( 'Añadir Recurso', 'tutor-course-resources' ),
			'manage_options',
			'tutor-course-resources-add',
			array( $this, 'render_add_resource_page' )
		);
	}
	
	/**
	 * Inicializar hooks
	 */
	private function init_hooks() {
		// Agregar meta box en edición de curso
		add_action( 'add_meta_boxes', array( $this, 'add_course_resources_meta_box' ) );
		
		// Guardar recursos del curso
		add_action( 'save_post', array( $this, 'save_course_resources' ), 10, 2 );
		
		// Agregar recursos en frontend del curso
		add_action( 'tutor_course/single/enrolled/before/inner-wrap', array( $this, 'display_course_resources_frontend' ), 10 );
		add_action( 'tutor_course/single/before/inner-wrap', array( $this, 'display_course_resources_frontend' ), 10 );
		
		// Agregar recursos en lecciones
		add_action( 'tutor_lesson/single/content/after', array( $this, 'display_lesson_resources_frontend' ), 10 );
		add_action( 'tutor_lesson/single/after/content', array( $this, 'display_lesson_resources_frontend' ), 10 );
		
		// Filtro para modificar tipos de archivo permitidos en Tutor LMS
		add_filter( 'upload_mimes', array( $this, 'allow_all_file_types' ), 999 );
		add_filter( 'tutor_allowed_file_upload_types', array( $this, 'allow_all_file_types_tutor' ), 999 );
		
		// Agregar menús al dashboard de Tutor LMS
		add_filter( 'tutor_dashboard/instructor_nav_items', array( $this, 'add_instructor_resources_menu' ) );
		add_filter( 'tutor_dashboard/nav_items', array( $this, 'add_student_resources_menu' ) );
		
		// Cargar templates del dashboard
		add_action( 'load_dashboard_template_part_from_other_location', array( $this, 'load_dashboard_templates' ) );
	}
	
	/**
	 * Inicializar AJAX handlers
	 */
	private function init_ajax_handlers() {
		add_action( 'wp_ajax_tutor_delete_resource', array( $this, 'ajax_delete_resource' ) );
		add_action( 'wp_ajax_tutor_delete_folder', array( $this, 'ajax_delete_folder' ) );
		add_action( 'wp_ajax_tutor_delete_multiple_items', array( $this, 'ajax_delete_multiple_items' ) );
		add_action( 'wp_ajax_tutor_update_resource_access', array( $this, 'ajax_update_resource_access' ) );
		add_action( 'wp_ajax_tutor_update_folder_access', array( $this, 'ajax_update_folder_access' ) );
		add_action( 'wp_ajax_tutor_upload_resource_file', array( $this, 'ajax_upload_resource_file' ) );
		add_action( 'wp_ajax_tutor_save_resource_meta', array( $this, 'ajax_save_resource_meta' ) );
		add_action( 'wp_ajax_tutor_create_folder', array( $this, 'ajax_create_folder' ) );
		add_action( 'wp_ajax_tutor_get_folder_contents', array( $this, 'ajax_get_folder_contents' ) );
		add_action( 'wp_ajax_tutor_save_file_to_folder', array( $this, 'ajax_save_file_to_folder' ) );
		add_action( 'wp_ajax_tutor_edit_resource', array( $this, 'ajax_edit_resource' ) );
		add_action( 'wp_ajax_tutor_edit_folder', array( $this, 'ajax_edit_folder' ) );
		add_action( 'wp_ajax_tutor_get_folder_data', array( $this, 'ajax_get_folder_data' ) );
		add_action( 'wp_ajax_tutor_get_resource_data', array( $this, 'ajax_get_resource_data' ) );
		add_action( 'wp_ajax_tutor_get_file_name', array( $this, 'ajax_get_file_name' ) );
		add_action( 'wp_ajax_tutor_get_course_lessons', array( $this, 'ajax_get_course_lessons' ) );
		add_action( 'wp_ajax_tutor_get_folder_lessons', array( $this, 'ajax_get_folder_lessons' ) );
		add_action( 'wp_ajax_tutor_get_resource_lessons', array( $this, 'ajax_get_resource_lessons' ) );
	}
	
	/**
	 * Modificar permisos de carga de archivos
	 */
	private function init_file_upload_modifications() {
		// Ya se hace en init_hooks con los filtros
	}
	
	/**
	 * Permitir todos los tipos de archivo
	 */
	public function allow_all_file_types( $mimes ) {
		// Permitir todos los tipos de archivo comunes
		$additional_mimes = array(
			// Archivos comprimidos
			'zip' => 'application/zip',
			'rar' => 'application/x-rar-compressed',
			'tar' => 'application/x-tar',
			'gz' => 'application/gzip',
			'7z' => 'application/x-7z-compressed',
			
			// Archivos de video
			'mp4' => 'video/mp4',
			'avi' => 'video/x-msvideo',
			'mkv' => 'video/x-matroska',
			'webm' => 'video/webm',
			'mov' => 'video/quicktime',
			'wmv' => 'video/x-ms-wmv',
			'flv' => 'video/x-flv',
			
			// Archivos de audio
			'mp3' => 'audio/mpeg',
			'wav' => 'audio/wav',
			'ogg' => 'audio/ogg',
			'm4a' => 'audio/mp4',
			'wma' => 'audio/x-ms-wma',
			
			// Archivos de texto y documentos
			'txt' => 'text/plain',
			'md' => 'text/markdown',
			'pdf' => 'application/pdf',
			'rtf' => 'application/rtf',
			
			// Archivos de código
			'js' => 'application/javascript',
			'css' => 'text/css',
			'html' => 'text/html',
			'php' => 'text/php',
			'json' => 'application/json',
			'xml' => 'application/xml',
			
			// Otros
			'svg' => 'image/svg+xml',
			'webp' => 'image/webp',
		);
		
		return array_merge( $mimes, $additional_mimes );
	}
	
	/**
	 * Permitir todos los tipos de archivo en Tutor LMS
	 */
	public function allow_all_file_types_tutor( $types ) {
		// Retornar array vacío para permitir todos los tipos
		// O retornar una lista más amplia
		return array(); // Array vacío = permitir todos
	}
	
	/**
	 * Inicializar frontend
	 */
	private function init_frontend() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}
	
	/**
	 * Cargar scripts y estilos en frontend
	 */
	public function enqueue_frontend_scripts() {
		if ( ! function_exists( 'tutor_utils' ) ) {
			return;
		}

		// Solo en páginas relevantes de Tutor LMS
		global $wp_query;
		$dashboard_page_id = tutor_utils()->get_option( 'tutor_dashboard_page_id' );
		$is_dashboard = is_page( $dashboard_page_id );
		$dashboard_page = isset( $wp_query->query_vars['tutor_dashboard_page'] ) ? $wp_query->query_vars['tutor_dashboard_page'] : '';
		$is_resources_page = $dashboard_page === 'course-resources' || $dashboard_page === 'my-resources';
		
		if ( $is_dashboard && $is_resources_page ) {
			wp_enqueue_style( 'tutor-course-resources-frontend', TUTOR_COURSE_RESOURCES_PLUGIN_URL . 'assets/css/frontend.css', array(), TUTOR_COURSE_RESOURCES_VERSION );
			wp_enqueue_script( 'tutor-course-resources-frontend', TUTOR_COURSE_RESOURCES_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), TUTOR_COURSE_RESOURCES_VERSION, true );
			
			// Localizar script para AJAX
			wp_localize_script( 'tutor-course-resources-frontend', 'tutorResources', array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'tutor_resources_nonce' ),
			) );
			
			// Cargar también el script de admin si es instructor
			if ( current_user_can( 'tutor_instructor' ) || current_user_can( 'administrator' ) ) {
				wp_enqueue_media();
				wp_enqueue_script( 'tutor-course-resources-admin', TUTOR_COURSE_RESOURCES_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), TUTOR_COURSE_RESOURCES_VERSION, true );
				wp_localize_script( 'tutor-course-resources-admin', 'tutorResources', array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'tutor_resources_nonce' ),
				) );
			}
		}
		
		// Siempre cargar estilos básicos para recursos en cursos/lecciones
		wp_enqueue_style( 'tutor-course-resources-frontend', TUTOR_COURSE_RESOURCES_PLUGIN_URL . 'assets/css/frontend.css', array(), TUTOR_COURSE_RESOURCES_VERSION );
		wp_localize_script( 'tutor-course-resources-frontend', 'tutorResources', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'tutor_resources_nonce' ),
		) );
	}
	
	/**
	 * Cargar scripts y estilos en admin
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'tutor-course-resources' ) === false && strpos( $hook, 'post.php' ) === false && strpos( $hook, 'post-new.php' ) === false ) {
			return;
		}
		
		wp_enqueue_style( 'tutor-course-resources-admin', TUTOR_COURSE_RESOURCES_PLUGIN_URL . 'assets/css/admin.css', array(), TUTOR_COURSE_RESOURCES_VERSION );
		wp_enqueue_script( 'tutor-course-resources-admin', TUTOR_COURSE_RESOURCES_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), TUTOR_COURSE_RESOURCES_VERSION, true );
		wp_localize_script( 'tutor-course-resources-admin', 'tutorResources', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'tutor_resources_nonce' ),
		) );
		
		wp_enqueue_media();
	}
	
	/**
	 * Agregar meta box en edición de curso
	 */
	public function add_course_resources_meta_box() {
		if ( ! function_exists( 'tutor' ) ) {
			return;
		}
		
		$course_post_type = tutor()->course_post_type;
		
		add_meta_box(
			'tutor-course-resources',
			__( 'Recursos del Curso', 'tutor-course-resources' ),
			array( $this, 'render_course_resources_meta_box' ),
			$course_post_type,
			'normal',
			'high'
		);
	}
	
	/**
	 * Renderizar meta box de recursos
	 */
	public function render_course_resources_meta_box( $post ) {
		$course_id = $post->ID;
		$resources = $this->get_course_resources( $course_id );
		
		include TUTOR_COURSE_RESOURCES_PLUGIN_DIR . 'includes/admin/views/course-resources-meta-box.php';
	}
	
	/**
	 * Guardar recursos del curso
	 */
	public function save_course_resources( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		if ( ! function_exists( 'tutor' ) ) {
			return;
		}
		
		if ( $post->post_type !== tutor()->course_post_type ) {
			return;
		}
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		
		// Los recursos se guardan mediante AJAX o en el formulario de administración
		// Esta función puede usarse para validaciones adicionales
	}
	
	/**
	 * Renderizar página de recursos
	 */
	public function render_resources_page() {
		include TUTOR_COURSE_RESOURCES_PLUGIN_DIR . 'includes/admin/views/resources-page.php';
	}
	
	/**
	 * Renderizar página de añadir recurso
	 */
	public function render_add_resource_page() {
		include TUTOR_COURSE_RESOURCES_PLUGIN_DIR . 'includes/admin/views/add-resource-page.php';
	}
	
	/**
	 * Obtener recursos de un curso (compatibilidad con versión anterior)
	 */
	public function get_course_resources( $course_id, $lesson_id = 0 ) {
		// Buscar en carpetas del curso o recursos directos
		global $wpdb;
		
		$folders = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$this->folders_table_name} WHERE course_id = %d",
			$course_id
		) );
		
		$where_parts = array();
		$where_parts[] = $wpdb->prepare( 'course_id = %d', $course_id );
		
		if ( ! empty( $folders ) ) {
			$folder_ids = array_map( 'intval', $folders );
			$placeholders = implode( ',', array_fill( 0, count( $folder_ids ), '%d' ) );
			$where_parts[] = $wpdb->prepare( "folder_id IN ($placeholders)", ...$folder_ids );
		}
		
		$where_clause = '(' . implode( ' OR ', $where_parts ) . ')';
		
		$query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY created_at DESC";
		
		return $wpdb->get_results( $query );
	}
	
	/**
	 * Verificar si el usuario puede acceder a un recurso
	 */
	public function can_user_access_resource( $resource, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		
		if ( ! $user_id ) {
			return false;
		}
		
		// Administradores siempre tienen acceso
		if ( current_user_can( 'administrator' ) ) {
			return true;
		}
		
		$user = get_userdata( $user_id );
		$is_instructor = current_user_can( 'tutor_instructor' );
		$is_student = in_array( 'subscriber', (array) $user->roles ) || ( function_exists( 'tutor_utils' ) && tutor_utils()->is_student( $user_id ) );
		
		// Verificar acceso individual por estudiante
		$access_students_list = ! empty( $resource->access_students_list ) ? explode( ',', $resource->access_students_list ) : array();
		if ( ! empty( $access_students_list ) ) {
			// Si hay lista específica de estudiantes, solo esos tienen acceso
			if ( in_array( $user_id, array_map( 'intval', $access_students_list ) ) ) {
				return true;
			}
			// Si hay lista específica y el usuario no está, no tiene acceso
			return false;
		}
		
		// Acceso de estudiantes
		if ( $is_student && $resource->access_students ) {
			// Verificar que el estudiante esté inscrito en el curso
			if ( $resource->course_id > 0 && function_exists( 'tutor_utils' ) ) {
				return tutor_utils()->is_enrolled( $resource->course_id, $user_id );
			}
		}
		
		// Acceso de docentes
		if ( $is_instructor && $resource->access_teachers ) {
			// Verificar si el docente tiene acceso específico
			$allowed_teachers = ! empty( $resource->access_teachers_list ) ? explode( ',', $resource->access_teachers_list ) : array();
			
			// Si la lista está vacía, todos los docentes tienen acceso
			if ( empty( $resource->access_teachers_list ) ) {
				return true;
			}
			
			// Verificar si el docente está en la lista
			if ( in_array( $user_id, $allowed_teachers ) ) {
				return true;
			}
			
			// Verificar si es el creador del recurso
			if ( $resource->created_by == $user_id ) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Mostrar recursos en frontend del curso
	 */
	public function display_course_resources_frontend() {
		global $post;
		
		if ( ! $post || ! function_exists( 'tutor' ) ) {
			return;
		}
		
		$course_id = $post->ID;
		$resources = $this->get_course_resources( $course_id, 0 );
		
		if ( empty( $resources ) ) {
			return;
		}
		
		// Filtrar recursos por acceso
		$accessible_resources = array();
		foreach ( $resources as $resource ) {
			if ( $this->can_user_access_resource( $resource ) ) {
				$accessible_resources[] = $resource;
			}
		}
		
		if ( empty( $accessible_resources ) ) {
			return;
		}
		
		include TUTOR_COURSE_RESOURCES_PLUGIN_DIR . 'templates/frontend/course-resources.php';
	}
	
	/**
	 * Mostrar recursos en lección
	 */
	public function display_lesson_resources_frontend() {
		global $post;
		
		if ( ! $post ) {
			return;
		}
		
		$lesson_id = $post->ID;
		
		// Obtener el curso padre de la lección
		$course_id = 0;
		if ( function_exists( 'tutor_utils' ) ) {
			$course_id = tutor_utils()->get_course_id_by_content( $lesson_id );
		}
		
		if ( ! $course_id ) {
			return;
		}
		
		// Obtener recursos relacionados específicamente con esta lección
		$lesson_resources = $this->get_resources_by_lesson( $lesson_id );
		
		// También obtener recursos del curso que no estén asociados a lecciones específicas
		$course_resources = $this->get_course_resources( $course_id, 0 );
		
		// Combinar y filtrar recursos duplicados
		$all_resources = array();
		$resource_ids = array();
		
		// Primero agregar recursos específicos de la lección
		foreach ( $lesson_resources as $resource ) {
			if ( ! in_array( $resource->id, $resource_ids ) ) {
				$all_resources[] = $resource;
				$resource_ids[] = $resource->id;
			}
		}
		
		// Luego agregar recursos del curso que no tengan lecciones asociadas
		foreach ( $course_resources as $resource ) {
			$resource_lessons = $this->get_resource_lessons( $resource->id );
			// Si el recurso no tiene lecciones asociadas, mostrarlo en todas las lecciones del curso
			if ( empty( $resource_lessons ) ) {
				if ( ! in_array( $resource->id, $resource_ids ) ) {
					$all_resources[] = $resource;
					$resource_ids[] = $resource->id;
				}
			}
		}
		
		if ( empty( $all_resources ) ) {
			return;
		}
		
		// Filtrar recursos por acceso
		$accessible_resources = array();
		foreach ( $all_resources as $resource ) {
			if ( $this->can_user_access_resource( $resource ) ) {
				$accessible_resources[] = $resource;
			}
		}
		
		if ( empty( $accessible_resources ) ) {
			return;
		}
		
		include TUTOR_COURSE_RESOURCES_PLUGIN_DIR . 'templates/frontend/lesson-resources.php';
	}
	
	/**
	 * AJAX: Eliminar recurso
	 */
	public function ajax_delete_resource() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		$resource_id = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;
		
		if ( ! $resource_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de recurso no válido.', 'tutor-course-resources' ) ) );
		}
		
		// Verificar permisos
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para realizar esta acción.', 'tutor-course-resources' ) ) );
		}
		
		global $wpdb;
		
		// Verificar permisos
		$resource = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$resource_id
		) );
		
		if ( ! $resource ) {
			wp_send_json_error( array( 'message' => __( 'Recurso no encontrado.', 'tutor-course-resources' ) ) );
		}
		
		if ( ! current_user_can( 'administrator' ) && $resource->created_by != get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para eliminar este recurso.', 'tutor-course-resources' ) ) );
		}
		
		// Eliminar relaciones de lecciones
		$wpdb->delete(
			$this->resources_lessons_table_name,
			array( 'resource_id' => $resource_id ),
			array( '%d' )
		);
		
		// Eliminar el recurso
		$result = $wpdb->delete( $this->table_name, array( 'id' => $resource_id ), array( '%d' ) );
		
		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Recurso eliminado correctamente.', 'tutor-course-resources' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Error al eliminar el recurso.', 'tutor-course-resources' ) ) );
		}
	}
	
	/**
	 * AJAX: Actualizar acceso a recurso
	 */
	public function ajax_update_resource_access() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		$resource_id = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;
		$access_students = isset( $_POST['access_students'] ) ? 1 : 0;
		$access_teachers = isset( $_POST['access_teachers'] ) ? 1 : 0;
		$access_teachers_list = isset( $_POST['access_teachers_list'] ) ? sanitize_text_field( $_POST['access_teachers_list'] ) : '';
		
		if ( ! $resource_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de recurso no válido.', 'tutor-course-resources' ) ) );
		}
		
		global $wpdb;
		$result = $wpdb->update(
			$this->table_name,
			array(
				'access_students' => $access_students,
				'access_teachers' => $access_teachers,
				'access_teachers_list' => $access_teachers_list,
			),
			array( 'id' => $resource_id ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);
		
		if ( $result !== false ) {
			wp_send_json_success( array( 'message' => __( 'Permisos actualizados correctamente.', 'tutor-course-resources' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Error al actualizar los permisos.', 'tutor-course-resources' ) ) );
		}
	}
	
	/**
	 * AJAX: Guardar recurso desde meta box
	 */
	public function ajax_save_resource_meta() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para realizar esta acción.', 'tutor-course-resources' ) ) );
		}
		
		$resource_data = array(
			'course_id' => isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0,
			'lesson_id' => isset( $_POST['lesson_id'] ) ? intval( $_POST['lesson_id'] ) : 0,
			'resource_type' => isset( $_POST['resource_type'] ) ? sanitize_text_field( $_POST['resource_type'] ) : 'file',
			'resource_url' => isset( $_POST['resource_url'] ) ? esc_url_raw( $_POST['resource_url'] ) : '',
			'file_id' => isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0,
			'title' => isset( $_POST['resource_title'] ) ? sanitize_text_field( $_POST['resource_title'] ) : '',
			'description' => isset( $_POST['resource_description'] ) ? sanitize_textarea_field( $_POST['resource_description'] ) : '',
			'access_students' => isset( $_POST['access_students'] ) ? 1 : 0,
			'access_teachers' => isset( $_POST['access_teachers'] ) ? 1 : 0,
			'access_teachers_list' => isset( $_POST['access_teachers_list'] ) ? sanitize_text_field( $_POST['access_teachers_list'] ) : '',
		);
		
		if ( empty( $resource_data['title'] ) || $resource_data['course_id'] <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Por favor, completa todos los campos requeridos.', 'tutor-course-resources' ) ) );
		}
		
		// Validar que haya URL o archivo según el tipo
		if ( $resource_data['resource_type'] === 'drive' && empty( $resource_data['resource_url'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Por favor, ingresa la URL de Google Drive.', 'tutor-course-resources' ) ) );
		}
		
		if ( $resource_data['resource_type'] === 'file' && $resource_data['file_id'] <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Por favor, selecciona un archivo.', 'tutor-course-resources' ) ) );
		}
		
		$resource_id = $this->save_resource( $resource_data );
		
		if ( $resource_id ) {
			wp_send_json_success( array( 
				'message' => __( 'Recurso guardado correctamente.', 'tutor-course-resources' ),
				'resource_id' => $resource_id,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Error al guardar el recurso.', 'tutor-course-resources' ) ) );
		}
	}
	
	/**
	 * AJAX: Subir archivo de recurso
	 */
	public function ajax_upload_resource_file() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para subir archivos.', 'tutor-course-resources' ) ) );
		}
		
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		
		$attachment_id = media_handle_upload( 'resource_file', 0 );
		
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}
		
		$file_url = wp_get_attachment_url( $attachment_id );
		
		wp_send_json_success( array(
			'file_id' => $attachment_id,
			'file_url' => $file_url,
			'file_name' => get_the_title( $attachment_id ),
		) );
	}
	
	/**
	 * AJAX: Crear carpeta
	 */
	public function ajax_create_folder() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para realizar esta acción.', 'tutor-course-resources' ) ) );
		}
		
		$folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
		$name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
		$parent_id = isset( $_POST['parent_id'] ) ? intval( $_POST['parent_id'] ) : 0;
		$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
		$is_libre_post = isset( $_POST['is_libre'] ) ? intval( $_POST['is_libre'] ) : 0;
		
		// Si se está creando dentro de una carpeta (parent_id > 0), heredar el curso de la carpeta padre
		if ( $parent_id > 0 && $course_id == 0 ) {
			$parent_folder = $this->get_folder( $parent_id );
			if ( $parent_folder && $parent_folder->course_id > 0 ) {
				$course_id = $parent_folder->course_id;
				// La subcarpeta hereda el curso automáticamente, no puede ser libre si el padre tiene curso
				$is_libre_post = 0;
			}
		}
		
		// Si course_id es 0, la carpeta es libre automáticamente
		$is_libre = ( $course_id == 0 ) ? 1 : $is_libre_post;
		
		$access_students = isset( $_POST['access_students'] ) ? 1 : 0;
		$access_teachers = isset( $_POST['access_teachers'] ) ? 1 : 0;
		$access_teachers_list = isset( $_POST['access_teachers_list'] ) ? sanitize_text_field( $_POST['access_teachers_list'] ) : '';
		
		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'El nombre de la carpeta es requerido.', 'tutor-course-resources' ) ) );
		}
		
		// Validar que el usuario tiene permisos para crear carpetas en este curso
		if ( $course_id > 0 ) {
			$course = get_post( $course_id );
			if ( ! $course ) {
				wp_send_json_error( array( 'message' => __( 'El curso seleccionado no existe.', 'tutor-course-resources' ) ) );
			}
			
			// Verificar permisos: admin o instructor del curso
			if ( ! current_user_can( 'administrator' ) && get_current_user_id() != $course->post_author ) {
				// Verificar si es instructor co-instructor
				if ( ! function_exists( 'tutor_utils' ) || ! tutor_utils()->is_instructor_of_this_course( get_current_user_id(), $course_id ) ) {
					wp_send_json_error( array( 'message' => __( 'No tienes permisos para crear carpetas en este curso.', 'tutor-course-resources' ) ) );
				}
			}
		}
		
		global $wpdb;
		
		if ( $folder_id > 0 ) {
			// Actualizar carpeta existente
			$result = $wpdb->update(
				$this->folders_table_name,
				array(
					'name' => $name,
					'parent_id' => $parent_id,
					'course_id' => $course_id,
					'is_libre' => $is_libre,
					'access_students' => $access_students,
					'access_teachers' => $access_teachers,
					'access_teachers_list' => $access_teachers_list,
					'access_students_list' => isset( $_POST['access_students_list'] ) ? sanitize_text_field( $_POST['access_students_list'] ) : '',
				),
				array( 'id' => $folder_id ),
				array( '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Asegurar que la tabla existe
			$this->init_database();
			
			// Verificar que la tabla existe ahora
			$table_exists = $wpdb->get_var( $wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$this->folders_table_name
			) ) === $this->folders_table_name;
			
			if ( ! $table_exists ) {
				wp_send_json_error( array( 
					'message' => __( 'Error: La tabla de carpetas no existe y no se pudo crear.', 'tutor-course-resources' ),
					'debug' => array(
						'table_name' => $this->folders_table_name,
						'last_error' => $wpdb->last_error,
					)
				) );
				return;
			}
			
			// Asegurar que access_teachers_list no sea null
			if ( empty( $access_teachers_list ) ) {
				$access_teachers_list = '';
			}
			
			// Crear nueva carpeta
			$insert_data = array(
				'name' => $name,
				'parent_id' => $parent_id,
				'course_id' => $course_id,
				'is_libre' => $is_libre,
				'access_students' => $access_students,
				'access_teachers' => $access_teachers,
				'access_teachers_list' => $access_teachers_list,
				'created_by' => get_current_user_id(),
			);
			
			$result = $wpdb->insert(
				$this->folders_table_name,
				$insert_data,
				array( '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d' )
			);
			
			if ( $result === false ) {
				// Capturar el error real
				$error_message = __( 'Error al guardar la carpeta.', 'tutor-course-resources' );
				if ( ! empty( $wpdb->last_error ) ) {
					$error_message .= ' Error SQL: ' . $wpdb->last_error;
				}
				wp_send_json_error( array( 
					'message' => $error_message,
					'debug' => array(
						'last_error' => $wpdb->last_error,
						'last_query' => $wpdb->last_query,
						'insert_data' => $insert_data,
						'table_name' => $this->folders_table_name,
					)
				) );
				return;
			}
			
			$folder_id = $wpdb->insert_id;
		}
		
		if ( $result !== false ) {
			// Guardar relaciones con lecciones si se proporcionaron
			$lesson_ids = array();
			if ( isset( $_POST['lesson_ids'] ) ) {
				if ( is_array( $_POST['lesson_ids'] ) ) {
					$lesson_ids = array_map( 'intval', $_POST['lesson_ids'] );
				} elseif ( is_string( $_POST['lesson_ids'] ) && ! empty( $_POST['lesson_ids'] ) ) {
					$lesson_ids = array_map( 'intval', explode( ',', $_POST['lesson_ids'] ) );
				}
			}
			$this->save_folder_lessons( $folder_id, $lesson_ids );
			
			wp_send_json_success( array( 
				'message' => __( 'Carpeta guardada correctamente.', 'tutor-course-resources' ),
				'folder_id' => $folder_id,
			) );
		} else {
			$error_message = __( 'Error al guardar la carpeta.', 'tutor-course-resources' );
			if ( ! empty( $wpdb->last_error ) ) {
				$error_message .= ' ' . $wpdb->last_error;
			}
			wp_send_json_error( array( 
				'message' => $error_message,
				'debug' => array(
					'last_error' => $wpdb->last_error,
					'last_query' => $wpdb->last_query,
				)
			) );
		}
	}
	
	/**
	 * Eliminar carpeta recursivamente (incluyendo contenido)
	 */
	private function delete_folder_recursive( $folder_id ) {
		global $wpdb;
		
		// Eliminar todas las subcarpetas recursivamente
		$subfolders = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$this->folders_table_name} WHERE parent_id = %d",
			$folder_id
		) );
		
		foreach ( $subfolders as $subfolder_id ) {
			$this->delete_folder_recursive( $subfolder_id );
		}
		
		// Eliminar todos los recursos de esta carpeta
		$resources = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$this->table_name} WHERE folder_id = %d",
			$folder_id
		) );
		
		foreach ( $resources as $resource_id ) {
			$this->delete_resource_internal( $resource_id );
		}
		
		// Eliminar relaciones de lecciones de la carpeta
		$wpdb->delete(
			$this->folders_lessons_table_name,
			array( 'folder_id' => $folder_id ),
			array( '%d' )
		);
		
		// Eliminar la carpeta misma
		$wpdb->delete(
			$this->folders_table_name,
			array( 'id' => $folder_id ),
			array( '%d' )
		);
		
		return true;
	}
	
	/**
	 * Eliminar recurso (método interno)
	 */
	private function delete_resource_internal( $resource_id ) {
		global $wpdb;
		
		// Eliminar relaciones de lecciones
		$wpdb->delete(
			$this->resources_lessons_table_name,
			array( 'resource_id' => $resource_id ),
			array( '%d' )
		);
		
		// Eliminar el recurso
		$wpdb->delete(
			$this->table_name,
			array( 'id' => $resource_id ),
			array( '%d' )
		);
		
		return true;
	}
	
	/**
	 * AJAX: Eliminar carpeta
	 */
	public function ajax_delete_folder() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		$folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
		
		if ( ! $folder_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de carpeta no válido.', 'tutor-course-resources' ) ) );
		}
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para realizar esta acción.', 'tutor-course-resources' ) ) );
		}
		
		global $wpdb;
		
		// Verificar permisos
		$folder = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->folders_table_name} WHERE id = %d",
			$folder_id
		) );
		
		if ( ! $folder ) {
			wp_send_json_error( array( 'message' => __( 'Carpeta no encontrada.', 'tutor-course-resources' ) ) );
		}
		
		if ( ! current_user_can( 'administrator' ) && $folder->created_by != get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para eliminar esta carpeta.', 'tutor-course-resources' ) ) );
		}
		
		// Contar elementos que se van a eliminar
		$subfolders_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->folders_table_name} WHERE parent_id = %d",
			$folder_id
		) );
		
		$files_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE folder_id = %d",
			$folder_id
		) );
		
		// Eliminar recursivamente
		$this->delete_folder_recursive( $folder_id );
		
		$message = __( 'Carpeta eliminada correctamente.', 'tutor-course-resources' );
		if ( $subfolders_count > 0 || $files_count > 0 ) {
			$message = sprintf( 
				__( 'Carpeta y su contenido eliminados correctamente (%d subcarpetas, %d archivos).', 'tutor-course-resources' ),
				$subfolders_count,
				$files_count
			);
		}
		
		wp_send_json_success( array( 'message' => $message ) );
	}
	
	/**
	 * AJAX: Guardar archivo en carpeta
	 */
	public function ajax_save_file_to_folder() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para realizar esta acción.', 'tutor-course-resources' ) ) );
		}
		
		$title = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
		$file_id = isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0;
		$resource_url = isset( $_POST['resource_url'] ) ? esc_url_raw( $_POST['resource_url'] ) : '';
		$resource_type = isset( $_POST['resource_type'] ) ? sanitize_text_field( $_POST['resource_type'] ) : 'file';
		$access_students = isset( $_POST['access_students'] ) ? 1 : 0;
		$access_teachers = isset( $_POST['access_teachers'] ) ? 1 : 0;
		
		if ( empty( $title ) ) {
			wp_send_json_error( array( 'message' => __( 'El título es requerido.', 'tutor-course-resources' ) ) );
		}
		
		if ( $resource_type === 'file' && $file_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Debes seleccionar un archivo.', 'tutor-course-resources' ) ) );
		}
		
		// Validar que el archivo existe si es tipo file
		if ( $resource_type === 'file' && $file_id > 0 ) {
			$attachment = get_post( $file_id );
			if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
				wp_send_json_error( array( 'message' => __( 'El archivo seleccionado no existe.', 'tutor-course-resources' ) ) );
			}
		}
		
		if ( $resource_type === 'drive' && empty( $resource_url ) ) {
			wp_send_json_error( array( 'message' => __( 'Debes ingresar la URL de Google Drive.', 'tutor-course-resources' ) ) );
		}
		
		// Validar carpeta si se especifica
		if ( $folder_id > 0 ) {
			$folder = $this->get_folder( $folder_id );
			if ( ! $folder ) {
				wp_send_json_error( array( 'message' => __( 'La carpeta especificada no existe.', 'tutor-course-resources' ) ) );
			}
		}
		
		// Obtener curso de la carpeta si existe, o usar el curso especificado
		$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
		if ( $folder_id > 0 && $course_id == 0 ) {
			$folder = $this->get_folder( $folder_id );
			if ( $folder && $folder->course_id > 0 ) {
				$course_id = $folder->course_id;
			}
		}
		
		$access_students_list = '';
		if ( isset( $_POST['access_students_list'] ) ) {
			if ( is_array( $_POST['access_students_list'] ) ) {
				$access_students_list = implode( ',', array_map( 'intval', $_POST['access_students_list'] ) );
			} else {
				$access_students_list = sanitize_text_field( $_POST['access_students_list'] );
			}
		}
		
		$resource_data = array(
			'folder_id' => $folder_id,
			'course_id' => $course_id,
			'resource_type' => $resource_type,
			'resource_url' => $resource_url,
			'file_id' => $file_id,
			'title' => $title,
			'access_students' => $access_students,
			'access_teachers' => $access_teachers,
			'access_students_list' => $access_students_list,
		);
		
		$resource_id = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;
		
		if ( $resource_id > 0 ) {
			// Actualizar recurso existente
			global $wpdb;
			$update_data = array(
				'title' => $title,
				'access_students' => $access_students,
				'access_teachers' => $access_teachers,
				'access_students_list' => $access_students_list,
			);
			$update_format = array( '%s', '%d', '%d', '%s' );
			
			if ( ! empty( $resource_url ) ) {
				$update_data['resource_url'] = $resource_url;
				$update_format[] = '%s';
			}
			if ( $file_id > 0 ) {
				$update_data['file_id'] = $file_id;
				$update_format[] = '%d';
			}
			if ( $course_id > 0 ) {
				$update_data['course_id'] = $course_id;
				$update_format[] = '%d';
			}
			
			$result = $wpdb->update(
				$this->table_name,
				$update_data,
				array( 'id' => $resource_id ),
				$update_format,
				array( '%d' )
			);
			
			if ( $result === false ) {
				wp_send_json_error( array( 'message' => __( 'Error al actualizar el recurso.', 'tutor-course-resources' ) ) );
				return;
			}
		} else {
			// Crear nuevo recurso
			$resource_id = $this->save_resource( $resource_data );
		}
		
		if ( $resource_id ) {
			// Guardar relaciones con lecciones
			$lesson_ids = array();
			if ( isset( $_POST['lesson_ids'] ) ) {
				if ( is_array( $_POST['lesson_ids'] ) ) {
					$lesson_ids = array_map( 'intval', $_POST['lesson_ids'] );
				} elseif ( is_string( $_POST['lesson_ids'] ) && ! empty( $_POST['lesson_ids'] ) ) {
					$lesson_ids = array_map( 'intval', explode( ',', $_POST['lesson_ids'] ) );
				}
			}
			
			// Si no se especificaron lecciones y el recurso está en una carpeta, heredar las lecciones de la carpeta
			if ( empty( $lesson_ids ) && $folder_id > 0 ) {
				$folder_lessons = $this->get_folder_lessons( $folder_id );
				if ( ! empty( $folder_lessons ) ) {
					$lesson_ids = $folder_lessons;
				}
			}
			
			$this->save_resource_lessons( $resource_id, $lesson_ids );
			
			wp_send_json_success( array( 
				'message' => __( 'Archivo guardado correctamente.', 'tutor-course-resources' ),
				'resource_id' => $resource_id,
			) );
		} else {
			global $wpdb;
			$error_message = __( 'Error al guardar el archivo.', 'tutor-course-resources' );
			if ( ! empty( $wpdb->last_error ) ) {
				$error_message .= ' Error SQL: ' . $wpdb->last_error;
			}
			wp_send_json_error( array( 
				'message' => $error_message,
				'debug' => array(
					'last_error' => $wpdb->last_error,
					'last_query' => $wpdb->last_query,
					'resource_data' => $resource_data,
				)
			) );
		}
	}
	
	/**
	 * Guardar recurso
	 */
	public function save_resource( $data ) {
		global $wpdb;
		
		// Asegurar que la tabla existe
		$this->init_database();
		
		// Verificar que la tabla existe
		$table_exists = $wpdb->get_var( $wpdb->prepare(
			"SHOW TABLES LIKE %s",
			$this->table_name
		) ) === $this->table_name;
		
		if ( ! $table_exists ) {
			error_log( 'Tutor Course Resources: La tabla ' . $this->table_name . ' no existe.' );
			return false;
		}
		
		$defaults = array(
			'folder_id' => 0,
			'course_id' => 0,
			'resource_type' => 'file',
			'resource_url' => '',
			'file_id' => 0,
			'title' => '',
			'description' => '',
			'access_students' => 1,
			'access_teachers' => 0,
			'access_teachers_list' => '',
			'created_by' => get_current_user_id(),
		);
		
		$data = wp_parse_args( $data, $defaults );
		
		// Asegurar que los valores vacíos no sean null
		if ( empty( $data['resource_url'] ) ) {
			$data['resource_url'] = '';
		}
		if ( empty( $data['description'] ) ) {
			$data['description'] = '';
		}
		if ( empty( $data['access_teachers_list'] ) ) {
			$data['access_teachers_list'] = '';
		}
		if ( empty( $data['access_students_list'] ) ) {
			$data['access_students_list'] = '';
		}
		
		$insert_data = array(
			'folder_id' => intval( $data['folder_id'] ),
			'course_id' => intval( $data['course_id'] ),
			'resource_type' => sanitize_text_field( $data['resource_type'] ),
			'resource_url' => esc_url_raw( $data['resource_url'] ),
			'file_id' => intval( $data['file_id'] ),
			'title' => sanitize_text_field( $data['title'] ),
			'description' => sanitize_textarea_field( $data['description'] ),
			'access_students' => intval( $data['access_students'] ),
			'access_teachers' => intval( $data['access_teachers'] ),
			'access_teachers_list' => sanitize_text_field( $data['access_teachers_list'] ),
			'access_students_list' => sanitize_text_field( $data['access_students_list'] ),
			'created_by' => intval( $data['created_by'] ),
		);
		
		$result = $wpdb->insert(
			$this->table_name,
			$insert_data,
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d' )
		);
		
		if ( $result === false ) {
			error_log( 'Tutor Course Resources: Error al insertar recurso. Error: ' . $wpdb->last_error );
			error_log( 'Tutor Course Resources: Query: ' . $wpdb->last_query );
			error_log( 'Tutor Course Resources: Datos: ' . print_r( $insert_data, true ) );
			return false;
		}
		
		if ( $result ) {
			$resource_id = $wpdb->insert_id;
			
			// Enviar notificación si es necesario
			$this->send_resource_notification( $resource_id, $data );
			
			return $resource_id;
		}
		
		return false;
	}
	
	/**
	 * Enviar notificación de nuevo recurso
	 */
	private function send_resource_notification( $resource_id, $resource_data ) {
		if ( ! function_exists( 'tutor_utils' ) ) {
			return;
		}
		
		$course_id = $resource_data['course_id'];
		
		// Disparar acción para notificaciones
		do_action( 'tutor_course_resource_added', $resource_id, $course_id, $resource_data );
		
		// Obtener estudiantes inscritos en el curso
		if ( $resource_data['access_students'] ) {
			global $wpdb;
			
			// Obtener estudiantes matriculados
			$enrolled_students = $wpdb->get_results( $wpdb->prepare(
				"SELECT DISTINCT student.ID, student.user_email, student.display_name
				FROM {$wpdb->posts} enrol
				INNER JOIN {$wpdb->users} student ON enrol.post_author = student.ID
				WHERE enrol.post_type = %s AND enrol.post_parent = %d",
				'tutor_enrolled',
				$course_id
			) );
			
			if ( ! empty( $enrolled_students ) ) {
				$course = get_post( $course_id );
				$course_title = $course ? $course->post_title : '';
				
				// Enviar email a cada estudiante
				foreach ( $enrolled_students as $student ) {
					$subject = sprintf( __( 'Nuevo recurso disponible en el curso: %s', 'tutor-course-resources' ), $course_title );
					$message = sprintf(
						__( 'Hola %s,\n\nSe ha agregado un nuevo recurso al curso "%s":\n\nTítulo: %s\n\nPuedes acceder al recurso desde tu panel de estudiante.\n\nSaludos.', 'tutor-course-resources' ),
						$student->display_name,
						$course_title,
						$resource_data['title']
					);
					
					wp_mail( $student->user_email, $subject, $message );
				}
			}
		}
	}
	
	/**
	 * Agregar menú de recursos al dashboard de instructor
	 */
	public function add_instructor_resources_menu( $nav_items ) {
		if ( ! current_user_can( 'tutor_instructor' ) && ! current_user_can( 'administrator' ) ) {
			return $nav_items;
		}
		
		$nav_items['course-resources'] = array(
			'title'    => __( 'Recursos', 'tutor-course-resources' ),
			'auth_cap' => tutor()->instructor_role,
			'icon'     => 'tutor-icon-media',
		);
		
		return $nav_items;
	}
	
	/**
	 * Agregar menú de recursos al dashboard de estudiante
	 */
	public function add_student_resources_menu( $nav_items ) {
		// Solo para estudiantes
		if ( ! is_user_logged_in() ) {
			return $nav_items;
		}
		
		$user = wp_get_current_user();
		$is_student = in_array( 'subscriber', (array) $user->roles );
		$is_instructor = current_user_can( 'tutor_instructor' );
		
		// Solo mostrar a estudiantes, no a instructores
		if ( $is_student && ! $is_instructor ) {
			$new_items = array();
			foreach ( $nav_items as $key => $item ) {
				$new_items[ $key ] = $item;
				if ( $key === 'my-profile' || $key === 'enrolled-courses' ) {
					$new_items['my-resources'] = array(
						'title'    => __( 'Mis Recursos', 'tutor-course-resources' ),
						'auth_cap' => 'subscriber',
						'icon'     => 'tutor-icon-media',
					);
				}
			}
			return $new_items;
		}
		
		return $nav_items;
	}
	
	/**
	 * Cargar templates del dashboard
	 */
	public function load_dashboard_templates( $location ) {
		global $wp_query;
		
		$dashboard_page = isset( $wp_query->query_vars['tutor_dashboard_page'] ) ? $wp_query->query_vars['tutor_dashboard_page'] : '';
		
		if ( $dashboard_page === 'course-resources' ) {
			// Template para instructores
			$template_file = TUTOR_COURSE_RESOURCES_PLUGIN_DIR . 'templates/dashboard/resources-instructor.php';
			if ( file_exists( $template_file ) ) {
				return $template_file;
			}
		} elseif ( $dashboard_page === 'my-resources' ) {
			// Template para estudiantes
			$template_file = TUTOR_COURSE_RESOURCES_PLUGIN_DIR . 'templates/dashboard/resources-student.php';
			if ( file_exists( $template_file ) ) {
				return $template_file;
			}
		}
		
		return $location;
	}
	
	/**
	 * Obtener estudiantes de un curso
	 */
	public function get_course_students( $course_id ) {
		global $wpdb;
		
		if ( ! function_exists( 'tutor_utils' ) ) {
			return array();
		}
		
		// Obtener todos los estudiantes matriculados
		$students_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					student.ID,
					student.display_name,
					student.user_email,
					student.user_login,
					enrol.post_status as enrollment_status,
					enrol.post_date as enrollment_date
				FROM {$wpdb->posts} enrol
				INNER JOIN {$wpdb->users} student
					ON enrol.post_author = student.ID
				WHERE enrol.post_type = %s
					AND enrol.post_parent = %d
				GROUP BY student.ID
				ORDER BY student.display_name ASC",
				'tutor_enrolled',
				$course_id
			)
		);
		
		$students = array();
		
		if ( empty( $students_raw ) || ! is_array( $students_raw ) ) {
			return $students;
		}
		
		foreach ( $students_raw as $student ) {
			if ( is_object( $student ) && isset( $student->ID ) ) {
				$student_id = intval( $student->ID );
				
				if ( $student_id > 0 ) {
					$user = get_userdata( $student_id );
					if ( $user ) {
						$students[] = (object) array(
							'ID' => $user->ID,
							'display_name' => isset( $student->display_name ) && ! empty( $student->display_name ) 
								? $student->display_name 
								: $user->display_name,
							'user_email' => isset( $student->user_email ) && ! empty( $student->user_email )
								? $student->user_email
								: $user->user_email,
						);
					}
				}
			}
		}
		
		return $students;
	}
	
	/**
	 * Obtener todos los cursos
	 */
	public function get_all_courses() {
		if ( ! function_exists( 'tutor' ) ) {
			return array();
		}
		
		$args = array(
			'post_type' => tutor()->course_post_type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'title',
			'order' => 'ASC',
		);
		
		$courses = get_posts( $args );
		return $courses;
	}
	
	/**
	 * Obtener cursos del instructor
	 */
	public function get_instructor_courses( $instructor_id ) {
		if ( ! function_exists( 'Tutor\Models\CourseModel' ) ) {
			return array();
		}
		
		return \Tutor\Models\CourseModel::get_courses_by_instructor( $instructor_id, 'publish', 0, 0, false, array( tutor()->course_post_type ) );
	}
	
	/**
	 * Obtener carpetas
	 */
	public function get_folders( $parent_id = 0, $course_id = 0 ) {
		global $wpdb;
		
		$where = array( '1=1' );
		$where_values = array();
		
		if ( $parent_id >= 0 ) {
			$where[] = 'parent_id = %d';
			$where_values[] = $parent_id;
		}
		
		if ( $course_id > 0 ) {
			$where[] = '(course_id = %d OR is_libre = 1)';
			$where_values[] = $course_id;
		}
		
		$where_clause = implode( ' AND ', $where );
		
		$query = "SELECT * FROM {$this->folders_table_name} WHERE {$where_clause} ORDER BY name ASC";
		
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}
		
		return $wpdb->get_results( $query );
	}
	
	/**
	 * Obtener una carpeta por ID
	 */
	public function get_folder( $folder_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->folders_table_name} WHERE id = %d",
			$folder_id
		) );
	}
	
	/**
	 * Obtener recursos por carpeta
	 */
	public function get_resources_by_folder( $folder_id = 0 ) {
		global $wpdb;
		
		$query = "SELECT * FROM {$this->table_name} WHERE folder_id = %d ORDER BY title ASC";
		return $wpdb->get_results( $wpdb->prepare( $query, $folder_id ) );
	}
	
	/**
	 * Verificar si el usuario puede acceder a una carpeta
	 */
	public function can_user_access_folder( $folder, $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		
		if ( ! $user_id ) {
			return false;
		}
		
		// Administradores siempre tienen acceso
		if ( current_user_can( 'administrator' ) ) {
			return true;
		}
		
		$user = get_userdata( $user_id );
		$is_instructor = current_user_can( 'tutor_instructor' );
		$is_student = in_array( 'subscriber', (array) $user->roles ) || ( function_exists( 'tutor_utils' ) && tutor_utils()->is_student( $user_id ) );
		
		// Verificar acceso individual por estudiante
		$access_students_list = ! empty( $folder->access_students_list ) ? explode( ',', $folder->access_students_list ) : array();
		if ( ! empty( $access_students_list ) ) {
			// Si hay lista específica de estudiantes, solo esos tienen acceso
			if ( in_array( $user_id, array_map( 'intval', $access_students_list ) ) ) {
				return true;
			}
			// Si hay lista específica y el usuario no está, no tiene acceso
			return false;
		}
		
		// Acceso de estudiantes
		if ( $is_student && $folder->access_students ) {
			if ( $folder->course_id > 0 && function_exists( 'tutor_utils' ) ) {
				return tutor_utils()->is_enrolled( $folder->course_id, $user_id );
			} elseif ( $folder->is_libre ) {
				return true;
			}
		}
		
		// Acceso de docentes
		if ( $is_instructor && $folder->access_teachers ) {
			$allowed_teachers = ! empty( $folder->access_teachers_list ) ? explode( ',', $folder->access_teachers_list ) : array();
			
			if ( empty( $folder->access_teachers_list ) ) {
				return true;
			}
			
			if ( in_array( $user_id, $allowed_teachers ) || $folder->created_by == $user_id ) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Obtener todos los recursos con filtros
	 */
	public function get_all_resources( $filters = array() ) {
		global $wpdb;
		
		$where = array( '1=1' );
		$where_values = array();
		
		if ( ! empty( $filters['course_id'] ) ) {
			$where[] = 'course_id = %d';
			$where_values[] = intval( $filters['course_id'] );
		}
		
		if ( ! empty( $filters['resource_type'] ) ) {
			$where[] = 'resource_type = %s';
			$where_values[] = sanitize_text_field( $filters['resource_type'] );
		}
		
		if ( ! empty( $filters['created_by'] ) ) {
			$where[] = 'created_by = %d';
			$where_values[] = intval( $filters['created_by'] );
		}
		
		$where_clause = implode( ' AND ', $where );
		
		$query = "SELECT r.*, c.post_title as course_title 
			FROM {$this->table_name} r
			LEFT JOIN {$wpdb->posts} c ON r.course_id = c.ID
			WHERE {$where_clause}
			ORDER BY r.created_at DESC";
		
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}
		
		return $wpdb->get_results( $query );
	}
	
	/**
	 * AJAX: Obtener datos de carpeta para edición
	 */
	public function ajax_get_folder_data() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		$folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
		
		if ( ! $folder_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de carpeta no válido.', 'tutor-course-resources' ) ) );
		}
		
		$folder = $this->get_folder( $folder_id );
		
		if ( ! $folder ) {
			wp_send_json_error( array( 'message' => __( 'Carpeta no encontrada.', 'tutor-course-resources' ) ) );
		}
		
		// Verificar permisos
		if ( ! current_user_can( 'administrator' ) && $folder->created_by != get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para editar esta carpeta.', 'tutor-course-resources' ) ) );
		}
		
		wp_send_json_success( array( 'folder' => $folder ) );
	}
	
	/**
	 * AJAX: Obtener datos de recurso para edición
	 */
	public function ajax_get_resource_data() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		$resource_id = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;
		
		if ( ! $resource_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de recurso no válido.', 'tutor-course-resources' ) ) );
		}
		
		global $wpdb;
		$resource = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$resource_id
		) );
		
		if ( ! $resource ) {
			wp_send_json_error( array( 'message' => __( 'Recurso no encontrado.', 'tutor-course-resources' ) ) );
		}
		
		// Verificar permisos
		if ( ! current_user_can( 'administrator' ) && $resource->created_by != get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para editar este recurso.', 'tutor-course-resources' ) ) );
		}
		
		wp_send_json_success( array( 'resource' => $resource ) );
	}
	
	/**
	 * AJAX: Editar carpeta
	 */
	public function ajax_edit_folder() {
		// Reutilizar el método de crear carpeta
		$this->ajax_create_folder();
	}
	
	/**
	 * AJAX: Editar recurso
	 */
	public function ajax_edit_resource() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para realizar esta acción.', 'tutor-course-resources' ) ) );
		}
		
		$resource_id = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;
		
		if ( ! $resource_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de recurso no válido.', 'tutor-course-resources' ) ) );
		}
		
		// Verificar que el recurso existe y pertenece al usuario
		global $wpdb;
		$resource = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$this->table_name} WHERE id = %d",
			$resource_id
		) );
		
		if ( ! $resource ) {
			wp_send_json_error( array( 'message' => __( 'Recurso no encontrado.', 'tutor-course-resources' ) ) );
		}
		
		if ( ! current_user_can( 'administrator' ) && $resource->created_by != get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para editar este recurso.', 'tutor-course-resources' ) ) );
		}
		
		$title = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$resource_url = isset( $_POST['resource_url'] ) ? esc_url_raw( $_POST['resource_url'] ) : '';
		$file_id = isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0;
		$access_students = isset( $_POST['access_students'] ) ? 1 : 0;
		$access_teachers = isset( $_POST['access_teachers'] ) ? 1 : 0;
		$access_students_list = '';
		
		if ( isset( $_POST['access_students_list'] ) ) {
			if ( is_array( $_POST['access_students_list'] ) ) {
				$access_students_list = implode( ',', array_map( 'intval', $_POST['access_students_list'] ) );
			} else {
				$access_students_list = sanitize_text_field( $_POST['access_students_list'] );
			}
		}
		
		$update_data = array(
			'title' => $title,
			'access_students' => $access_students,
			'access_teachers' => $access_teachers,
			'access_students_list' => $access_students_list,
		);
		
		$update_format = array( '%s', '%d', '%d', '%s' );
		
		// Solo actualizar URL o file_id si se proporcionan
		if ( ! empty( $resource_url ) ) {
			$update_data['resource_url'] = $resource_url;
			$update_format[] = '%s';
		}
		
		if ( $file_id > 0 ) {
			$update_data['file_id'] = $file_id;
			$update_format[] = '%d';
		}
		
		$result = $wpdb->update(
			$this->table_name,
			$update_data,
			array( 'id' => $resource_id ),
			$update_format,
			array( '%d' )
		);
		
		if ( $result !== false ) {
			wp_send_json_success( array( 
				'message' => __( 'Recurso actualizado correctamente.', 'tutor-course-resources' ),
			) );
		} else {
			$error_message = __( 'Error al actualizar el recurso.', 'tutor-course-resources' );
			if ( ! empty( $wpdb->last_error ) ) {
				$error_message .= ' Error SQL: ' . $wpdb->last_error;
			}
			wp_send_json_error( array( 
				'message' => $error_message,
				'debug' => array(
					'last_error' => $wpdb->last_error,
					'last_query' => $wpdb->last_query,
				)
			) );
		}
	}
	
	/**
	 * AJAX: Obtener nombre de archivo
	 */
	public function ajax_get_file_name() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		$file_id = isset( $_POST['file_id'] ) ? intval( $_POST['file_id'] ) : 0;
		
		if ( ! $file_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de archivo no válido.', 'tutor-course-resources' ) ) );
		}
		
		$file_path = get_attached_file( $file_id );
		$file_name = $file_path ? basename( $file_path ) : get_the_title( $file_id );
		
		wp_send_json_success( array( 'file_name' => $file_name ) );
	}
	
	/**
	 * Obtener recursos accesibles por estudiante
	 */
	public function get_student_accessible_resources( $student_id = 0 ) {
		if ( ! $student_id ) {
			$student_id = get_current_user_id();
		}
		
		if ( ! $student_id ) {
			return array();
		}
		
		// Obtener todos los recursos
		global $wpdb;
		$all_resources = $wpdb->get_results( "SELECT * FROM {$this->table_name} ORDER BY created_at DESC" );
		
		$accessible_resources = array();
		foreach ( $all_resources as $resource ) {
			if ( $this->can_user_access_resource( $resource, $student_id ) ) {
				$accessible_resources[] = $resource;
			}
		}
		
		return $accessible_resources;
	}
	
	/**
	 * Obtener carpetas accesibles por estudiante
	 */
	public function get_student_accessible_folders( $student_id = 0, $parent_id = 0 ) {
		if ( ! $student_id ) {
			$student_id = get_current_user_id();
		}
		
		if ( ! $student_id ) {
			return array();
		}
		
		// Obtener todas las carpetas
		$all_folders = $this->get_folders( $parent_id );
		
		$accessible_folders = array();
		foreach ( $all_folders as $folder ) {
			if ( $this->can_user_access_folder( $folder, $student_id ) ) {
				$accessible_folders[] = $folder;
			}
		}
		
		return $accessible_folders;
	}
	
	/**
	 * Obtener lecciones de un curso
	 */
	public function get_course_lessons( $course_id ) {
		if ( ! function_exists( 'tutor_utils' ) || ! function_exists( 'tutor' ) ) {
			return array();
		}
		
		global $wpdb;
		
		$lessons = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT items.ID, items.post_title
				FROM {$wpdb->posts} topic
				INNER JOIN {$wpdb->posts} items ON topic.ID = items.post_parent
				WHERE topic.post_parent = %d
					AND items.post_type = %s
					AND items.post_status = 'publish'
					AND topic.post_status = 'publish'
				ORDER BY topic.menu_order ASC, items.menu_order ASC",
				$course_id,
				tutor()->lesson_post_type
			)
		);
		
		return $lessons;
	}
	
	/**
	 * Guardar relaciones carpeta-lecciones
	 */
	public function save_folder_lessons( $folder_id, $lesson_ids ) {
		global $wpdb;
		
		// Eliminar relaciones existentes
		$wpdb->delete(
			$this->folders_lessons_table_name,
			array( 'folder_id' => $folder_id ),
			array( '%d' )
		);
		
		// Guardar nuevas relaciones
		if ( ! empty( $lesson_ids ) && is_array( $lesson_ids ) ) {
			foreach ( $lesson_ids as $lesson_id ) {
				$lesson_id = intval( $lesson_id );
				if ( $lesson_id > 0 ) {
					$wpdb->insert(
						$this->folders_lessons_table_name,
						array(
							'folder_id' => $folder_id,
							'lesson_id' => $lesson_id,
						),
						array( '%d', '%d' )
					);
				}
			}
		}
	}
	
	/**
	 * Guardar relaciones recurso-lecciones
	 */
	public function save_resource_lessons( $resource_id, $lesson_ids ) {
		global $wpdb;
		
		// Eliminar relaciones existentes
		$wpdb->delete(
			$this->resources_lessons_table_name,
			array( 'resource_id' => $resource_id ),
			array( '%d' )
		);
		
		// Guardar nuevas relaciones
		if ( ! empty( $lesson_ids ) && is_array( $lesson_ids ) ) {
			foreach ( $lesson_ids as $lesson_id ) {
				$lesson_id = intval( $lesson_id );
				if ( $lesson_id > 0 ) {
					$wpdb->insert(
						$this->resources_lessons_table_name,
						array(
							'resource_id' => $resource_id,
							'lesson_id' => $lesson_id,
						),
						array( '%d', '%d' )
					);
				}
			}
		}
	}
	
	/**
	 * Obtener lecciones relacionadas con una carpeta
	 */
	public function get_folder_lessons( $folder_id ) {
		global $wpdb;
		
		$lesson_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT lesson_id FROM {$this->folders_lessons_table_name} WHERE folder_id = %d",
				$folder_id
			)
		);
		
		return array_map( 'intval', $lesson_ids );
	}
	
	/**
	 * Obtener lecciones relacionadas con un recurso
	 */
	public function get_resource_lessons( $resource_id ) {
		global $wpdb;
		
		$lesson_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT lesson_id FROM {$this->resources_lessons_table_name} WHERE resource_id = %d",
				$resource_id
			)
		);
		
		return array_map( 'intval', $lesson_ids );
	}
	
	/**
	 * Obtener recursos por lección
	 */
	public function get_resources_by_lesson( $lesson_id ) {
		global $wpdb;
		
		$resource_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT resource_id FROM {$this->resources_lessons_table_name} WHERE lesson_id = %d",
				$lesson_id
			)
		);
		
		if ( empty( $resource_ids ) ) {
			return array();
		}
		
		$placeholders = implode( ',', array_fill( 0, count( $resource_ids ), '%d' ) );
		$resources = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id IN ($placeholders) ORDER BY title ASC",
				$resource_ids
			)
		);
		
		return $resources;
	}
	
	/**
	 * Obtener carpetas por lección
	 */
	public function get_folders_by_lesson( $lesson_id ) {
		global $wpdb;
		
		$folder_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT folder_id FROM {$this->folders_lessons_table_name} WHERE lesson_id = %d",
				$lesson_id
			)
		);
		
		if ( empty( $folder_ids ) ) {
			return array();
		}
		
		$placeholders = implode( ',', array_fill( 0, count( $folder_ids ), '%d' ) );
		$folders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->folders_table_name} WHERE id IN ($placeholders) ORDER BY name ASC",
				$folder_ids
			)
		);
		
		return $folders;
	}
	
	/**
	 * AJAX: Obtener lecciones de un curso
	 */
	public function ajax_get_course_lessons() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
		
		if ( ! $course_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de curso no válido.', 'tutor-course-resources' ) ) );
		}
		
		$lessons = $this->get_course_lessons( $course_id );
		
		wp_send_json_success( array( 'lessons' => $lessons ) );
	}
	
	/**
	 * AJAX: Obtener lecciones de una carpeta
	 */
	public function ajax_get_folder_lessons() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		$folder_id = isset( $_POST['folder_id'] ) ? intval( $_POST['folder_id'] ) : 0;
		
		if ( ! $folder_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de carpeta no válido.', 'tutor-course-resources' ) ) );
		}
		
		$lesson_ids = $this->get_folder_lessons( $folder_id );
		
		wp_send_json_success( array( 'lesson_ids' => $lesson_ids ) );
	}
	
	/**
	 * AJAX: Obtener lecciones de un recurso
	 */
	public function ajax_get_resource_lessons() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		$resource_id = isset( $_POST['resource_id'] ) ? intval( $_POST['resource_id'] ) : 0;
		
		if ( ! $resource_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de recurso no válido.', 'tutor-course-resources' ) ) );
		}
		
		$lesson_ids = $this->get_resource_lessons( $resource_id );
		
		wp_send_json_success( array( 'lesson_ids' => $lesson_ids ) );
	}
	
	/**
	 * AJAX: Eliminar múltiples elementos (carpetas y recursos)
	 */
	public function ajax_delete_multiple_items() {
		check_ajax_referer( 'tutor_resources_nonce', 'nonce' );
		
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para realizar esta acción.', 'tutor-course-resources' ) ) );
		}
		
		$items = isset( $_POST['items'] ) ? $_POST['items'] : array();
		
		if ( empty( $items ) || ! is_array( $items ) ) {
			wp_send_json_error( array( 'message' => __( 'No se seleccionaron elementos para eliminar.', 'tutor-course-resources' ) ) );
		}
		
		$deleted_folders = 0;
		$deleted_resources = 0;
		$errors = array();
		
		foreach ( $items as $item ) {
			$type = isset( $item['type'] ) ? sanitize_text_field( $item['type'] ) : '';
			$id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
			
			if ( ! $id ) {
				continue;
			}
			
			try {
				if ( $type === 'folder' ) {
					// Verificar permisos
					global $wpdb;
					$folder = $wpdb->get_row( $wpdb->prepare(
						"SELECT * FROM {$this->folders_table_name} WHERE id = %d",
						$id
					) );
					
					if ( $folder ) {
						if ( ! current_user_can( 'administrator' ) && $folder->created_by != get_current_user_id() ) {
							$errors[] = sprintf( __( 'No tienes permisos para eliminar la carpeta "%s".', 'tutor-course-resources' ), $folder->name );
							continue;
						}
						
						$this->delete_folder_recursive( $id );
						$deleted_folders++;
					}
				} elseif ( $type === 'resource' ) {
					// Verificar permisos
					global $wpdb;
					$resource = $wpdb->get_row( $wpdb->prepare(
						"SELECT * FROM {$this->table_name} WHERE id = %d",
						$id
					) );
					
					if ( $resource ) {
						if ( ! current_user_can( 'administrator' ) && $resource->created_by != get_current_user_id() ) {
							$errors[] = sprintf( __( 'No tienes permisos para eliminar el recurso "%s".', 'tutor-course-resources' ), $resource->title );
							continue;
						}
						
						$this->delete_resource_internal( $id );
						$deleted_resources++;
					}
				}
			} catch ( Exception $e ) {
				$errors[] = sprintf( __( 'Error al eliminar elemento ID %d: %s', 'tutor-course-resources' ), $id, $e->getMessage() );
			}
		}
		
		$message = '';
		if ( $deleted_folders > 0 && $deleted_resources > 0 ) {
			$message = sprintf( 
				__( 'Se eliminaron %d carpeta(s) y %d recurso(s).', 'tutor-course-resources' ),
				$deleted_folders,
				$deleted_resources
			);
		} elseif ( $deleted_folders > 0 ) {
			$message = sprintf( __( 'Se eliminaron %d carpeta(s).', 'tutor-course-resources' ), $deleted_folders );
		} elseif ( $deleted_resources > 0 ) {
			$message = sprintf( __( 'Se eliminaron %d recurso(s).', 'tutor-course-resources' ), $deleted_resources );
		}
		
		if ( ! empty( $errors ) ) {
			$message .= ' ' . implode( ' ', $errors );
		}
		
		if ( $deleted_folders > 0 || $deleted_resources > 0 ) {
			wp_send_json_success( array( 'message' => $message ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'No se pudo eliminar ningún elemento.', 'tutor-course-resources' ) ) );
		}
	}
}

// Inicializar plugin
Tutor_Course_Resources::get_instance();

