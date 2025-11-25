<?php
/**
 * Plugin Name: Tutor Attendance Calendar
 * Plugin URI: https://example.com
 * Description: Sistema de gestión de asistencia por curso con calendario para Tutor LMS
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tutor-attendance-calendar
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Definir constantes del plugin
define( 'TUTOR_ATTENDANCE_VERSION', '1.0.0' );
define( 'TUTOR_ATTENDANCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TUTOR_ATTENDANCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Clase principal del plugin
 */
class Tutor_Attendance_Calendar {

	/**
	 * Instancia única del plugin
	 */
	private static $instance = null;

	/**
	 * Nombre de la tabla de asistencia
	 */
	private $table_name = '';

	/**
	 * Nombre de la tabla de horarios
	 */
	private $schedule_table_name = '';

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
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Inicializar el plugin
	 */
	public function init() {
		// Verificar que Tutor LMS esté activo
		if ( ! $this->is_tutor_lms_active() ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_missing_tutor' ) );
			return;
		}

		// Interceptar exportación ANTES de cualquier otro procesamiento (prioridad muy alta)
		add_action( 'admin_init', array( $this, 'intercept_export_request' ), 1 );
		add_action( 'init', array( $this, 'intercept_export_request_early' ), 1 );
		add_action( 'template_redirect', array( $this, 'intercept_export_request_frontend' ), 1 );

		// Inicializar componentes
		$this->init_database();
		$this->init_admin_menus();
		$this->init_frontend();
		$this->init_ajax_handlers();
		$this->init_settings();
	}

	/**
	 * Verificar si Tutor LMS está activo
	 */
	private function is_tutor_lms_active() {
		return class_exists( '\TUTOR\Tutor' ) || function_exists( 'tutor' );
	}

	/**
	 * Notificación si falta Tutor LMS
	 */
	public function admin_notice_missing_tutor() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Tutor Attendance Calendar requiere que el plugin Tutor LMS esté instalado y activo.', 'tutor-attendance-calendar' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Inicializar base de datos
	 */
	private function init_database() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'tutor_attendance';
		$this->schedule_table_name = $wpdb->prefix . 'tutor_attendance_schedules';
	}

	/**
	 * Activar el plugin - Crear tabla de asistencia
	 */
	public function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'tutor_attendance';

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			student_id bigint(20) UNSIGNED NOT NULL,
			course_id bigint(20) UNSIGNED NOT NULL,
			instructor_id bigint(20) UNSIGNED NOT NULL,
			attendance_date date NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'Asistió',
			marked_by bigint(20) UNSIGNED NOT NULL COMMENT 'ID del usuario que marcó la asistencia',
			notes text DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_attendance (student_id, course_id, attendance_date),
			KEY idx_student_id (student_id),
			KEY idx_course_id (course_id),
			KEY idx_attendance_date (attendance_date),
			KEY idx_status (status)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		// Crear tabla de horarios de cursos
		$schedule_table = $wpdb->prefix . 'tutor_attendance_schedules';
		$schedule_sql = "CREATE TABLE IF NOT EXISTS $schedule_table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id bigint(20) UNSIGNED NOT NULL,
			day_of_week tinyint(1) NOT NULL COMMENT '0=Domingo, 1=Lunes, 2=Martes, etc.',
			start_time time NOT NULL,
			end_time time NOT NULL,
			date_from date DEFAULT NULL COMMENT 'Fecha de inicio del horario (NULL = sin límite)',
			date_to date DEFAULT NULL COMMENT 'Fecha de fin del horario (NULL = sin límite)',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_course_id (course_id),
			KEY idx_day_of_week (day_of_week),
			KEY idx_date_from (date_from),
			KEY idx_date_to (date_to)
		) $charset_collate;";
		dbDelta( $schedule_sql );
		
		// Verificar si la tabla ya existe y agregar columnas si no existen
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$schedule_table'" ) === $schedule_table;
		if ( $table_exists ) {
			// Verificar si las columnas existen
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM $schedule_table" );
			if ( ! in_array( 'date_from', $columns ) ) {
				$wpdb->query( "ALTER TABLE $schedule_table ADD COLUMN date_from date DEFAULT NULL COMMENT 'Fecha de inicio del horario (NULL = sin límite)' AFTER end_time" );
			}
			if ( ! in_array( 'date_to', $columns ) ) {
				$wpdb->query( "ALTER TABLE $schedule_table ADD COLUMN date_to date DEFAULT NULL COMMENT 'Fecha de fin del horario (NULL = sin límite)' AFTER date_from" );
			}
		}

		// Crear opciones por defecto
		add_option( 'tutor_attendance_states', array( 'Asistió', 'Falta', 'Tarde', 'Justificado' ) );
		add_option( 'tutor_attendance_student_can_mark', true );
		add_option( 'tutor_attendance_student_deadline_hours', 24 );
		add_option( 'tutor_attendance_student_deadline_minutes', 0 );
		add_option( 'tutor_attendance_use_schedules', false );
	}

	/**
	 * Desactivar el plugin
	 */
	public function deactivate() {
		// No eliminar datos al desactivar, solo limpiar transients
		delete_transient( 'tutor_attendance_courses_' . get_current_user_id() );
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
		// Menú principal
		add_menu_page(
			__( 'Asistencia Tutor', 'tutor-attendance-calendar' ),
			__( 'Asistencia Tutor', 'tutor-attendance-calendar' ),
			'read',
			'tutor-attendance',
			array( $this, 'render_dashboard_page' ),
			'dashicons-calendar-alt',
			30
		);

		// Submenú: Resumen de Asistencias (solo admin)
		if ( current_user_can( 'administrator' ) ) {
			add_submenu_page(
				'tutor-attendance',
				__( 'Resumen de Asistencias', 'tutor-attendance-calendar' ),
				__( 'Resumen de Asistencias', 'tutor-attendance-calendar' ),
				'administrator',
				'tutor-attendance-summary',
				array( $this, 'render_summary_page' )
			);
			
			// Submenú: Exportar Reportes (solo admin)
			add_submenu_page(
				'tutor-attendance',
				__( 'Exportar Reportes', 'tutor-attendance-calendar' ),
				__( 'Exportar Reportes', 'tutor-attendance-calendar' ),
				'administrator',
				'tutor-attendance-export',
				array( $this, 'render_export_page' )
			);
		}

		// Submenú: Tomar Asistencia (docente y admin)
		if ( current_user_can( 'tutor_instructor' ) || current_user_can( 'administrator' ) ) {
			add_submenu_page(
				'tutor-attendance',
				__( 'Tomar Asistencia', 'tutor-attendance-calendar' ),
				__( 'Tomar Asistencia', 'tutor-attendance-calendar' ),
				'read',
				'tutor-attendance-take',
				array( $this, 'render_take_attendance_page' )
			);
		}

		// Submenú: Horarios de Cursos (docente y admin)
		if ( current_user_can( 'tutor_instructor' ) || current_user_can( 'administrator' ) ) {
			add_submenu_page(
				'tutor-attendance',
				__( 'Horarios de Cursos', 'tutor-attendance-calendar' ),
				__( 'Horarios de Cursos', 'tutor-attendance-calendar' ),
				'read',
				'tutor-attendance-schedules',
				array( $this, 'render_schedules_page' )
			);
		}

		// Submenú: Configuración (solo admin)
		if ( current_user_can( 'administrator' ) ) {
			add_submenu_page(
				'tutor-attendance',
				__( 'Configuración', 'tutor-attendance-calendar' ),
				__( 'Configuración', 'tutor-attendance-calendar' ),
				'administrator',
				'tutor-attendance-settings',
				array( $this, 'render_settings_page' )
			);
		}
	}

	/**
	 * Página principal del dashboard (redirige según rol)
	 */
	public function render_dashboard_page() {
		if ( current_user_can( 'administrator' ) ) {
			$this->render_summary_page();
		} elseif ( current_user_can( 'tutor_instructor' ) ) {
			$this->render_take_attendance_page();
		} else {
			// Para alumnos, mostrar mensaje
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Asistencia Tutor', 'tutor-attendance-calendar' ); ?></h1>
				<p><?php esc_html_e( 'Puedes marcar tu asistencia desde tu panel de estudiante en Tutor LMS.', 'tutor-attendance-calendar' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Renderizar página de resumen (Admin)
	 */
	public function render_summary_page() {
		if ( ! current_user_can( 'administrator' ) ) {
			wp_die( __( 'No tienes permisos para acceder a esta página.', 'tutor-attendance-calendar' ) );
		}

		// Obtener filtros
		$course_id = isset( $_GET['course_id'] ) ? intval( $_GET['course_id'] ) : 0;
		$instructor_id = isset( $_GET['instructor_id'] ) ? intval( $_GET['instructor_id'] ) : 0;
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );
		$date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : date( 'Y-m-d' );
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';

		// Obtener datos
		$attendance_data = $this->get_attendance_summary( $course_id, $instructor_id, $date_from, $date_to, $status_filter );
		$courses = $this->get_all_courses();
		$instructors = $this->get_all_instructors();

		include TUTOR_ATTENDANCE_PLUGIN_DIR . 'includes/admin/views/summary-page.php';
	}

	/**
	 * Renderizar página de exportación de reportes
	 * Se puede usar tanto en admin como en frontend
	 */
	public function render_export_page( $is_frontend = false ) {
		// Permitir acceso a instructores y administradores
		if ( ! current_user_can( 'tutor_instructor' ) && ! current_user_can( 'administrator' ) ) {
			if ( $is_frontend ) {
				return '<p>' . __( 'No tienes permisos para acceder a esta página.', 'tutor-attendance-calendar' ) . '</p>';
			}
			wp_die( __( 'No tienes permisos para acceder a esta página.', 'tutor-attendance-calendar' ) );
		}

		// Verificar si se solicita exportación a Excel - DEBE ser lo primero que se verifique
		if ( isset( $_POST['export_excel'] ) || ( isset( $_GET['export'] ) && $_GET['export'] === 'excel' ) ) {
			// Limpiar cualquier output previo antes de exportar
			if ( ob_get_level() ) {
				ob_end_clean();
			}
			$this->export_attendance_to_excel();
			// No debería llegar aquí, pero por si acaso
			return;
		}

		// Determinar si estamos en frontend
		if ( ! isset( $is_frontend ) ) {
			$is_frontend = ! is_admin();
		}
		
		// Variables para usar en la vista
		$is_frontend_dashboard = $is_frontend;
		$current_user_id = get_current_user_id();
		$is_admin = current_user_can( 'administrator' );

		// Obtener datos para los filtros
		if ( $is_admin ) {
			// Administradores ven todos los cursos
			$courses = $this->get_all_courses();
			$instructors = $this->get_all_instructors();
		} else {
			// Instructores solo ven sus propios cursos
			$instructor_courses_raw = $this->get_instructor_courses( $current_user_id );
			$courses = array();
			// Normalizar los cursos a objetos con ID
			foreach ( $instructor_courses_raw as $course ) {
				if ( is_object( $course ) && isset( $course->ID ) ) {
					$courses[] = $course;
				} elseif ( is_numeric( $course ) ) {
					$course_obj = get_post( $course );
					if ( $course_obj ) {
						$courses[] = $course_obj;
					}
				}
			}
			$instructors = array(); // Instructores no necesitan ver otros instructores
		}

		if ( $is_frontend ) {
			// En frontend, incluir el template directamente
			include TUTOR_ATTENDANCE_PLUGIN_DIR . 'includes/admin/views/export-page.php';
		} else {
			// En admin
			include TUTOR_ATTENDANCE_PLUGIN_DIR . 'includes/admin/views/export-page.php';
		}
	}

	/**
	 * Renderizar página para tomar asistencia (Docente)
	 * Se puede usar tanto en admin como en frontend
	 */
	public function render_take_attendance_page( $is_frontend = false ) {
		if ( ! current_user_can( 'tutor_instructor' ) && ! current_user_can( 'administrator' ) ) {
			if ( $is_frontend ) {
				return '<p>' . __( 'No tienes permisos para acceder a esta página.', 'tutor-attendance-calendar' ) . '</p>';
			}
			wp_die( __( 'No tienes permisos para acceder a esta página.', 'tutor-attendance-calendar' ) );
		}

		$current_user_id = get_current_user_id();
		
		// Obtener cursos del instructor (o todos si es admin)
		if ( current_user_can( 'administrator' ) ) {
			$instructor_courses = $this->get_all_courses();
		} else {
			$instructor_courses = $this->get_instructor_courses( $current_user_id );
		}

		// Obtener curso y fecha seleccionados
		$selected_course_id = isset( $_GET['course_id'] ) ? intval( $_GET['course_id'] ) : 0;
		$selected_date = isset( $_GET['attendance_date'] ) ? sanitize_text_field( $_GET['attendance_date'] ) : date( 'Y-m-d' );

		$students = array();
		$existing_attendance = array();

		if ( $selected_course_id > 0 ) {
			// Obtener estudiantes del curso
			$all_students = $this->get_course_students( $selected_course_id );
			
			// Paginación - leer paged de $_GET (prioridad) o de query_vars (para rewrite rules de WordPress)
			$per_page = 20; // Estudiantes por página
			global $wp_query;
			$current_page = 1;
			
			// Prioridad 1: leer de $_GET directamente (query parameter ?paged=2)
			if ( isset( $_GET['paged'] ) && $_GET['paged'] > 0 ) {
				$current_page = max( 1, intval( $_GET['paged'] ) );
			} 
			// Prioridad 2: leer de query_vars (cuando viene de rewrite rule /page/2/)
			elseif ( isset( $wp_query->query_vars['paged'] ) && $wp_query->query_vars['paged'] > 0 ) {
				$current_page = max( 1, intval( $wp_query->query_vars['paged'] ) );
			}
			// Prioridad 3: Intentar leer de la URL directamente si viene como /page/2/
			else {
				$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
				if ( preg_match( '/\/page\/(\d+)\//', $request_uri, $matches ) ) {
					$current_page = max( 1, intval( $matches[1] ) );
				}
			}
			$total_students = count( $all_students );
			$total_pages = ceil( $total_students / $per_page );
			$offset = ( $current_page - 1 ) * $per_page;
			$students = array_slice( $all_students, $offset, $per_page );
			
			// Obtener asistencias existentes para esta fecha
			$existing_attendance = $this->get_attendance_for_date( $selected_course_id, $selected_date );
		} else {
			$total_students = 0;
			$total_pages = 0;
			$current_page = 1;
		}

		// Estados de asistencia
		$attendance_states = get_option( 'tutor_attendance_states', array( 'Asistió', 'Falta', 'Tarde', 'Justificado' ) );

		// Determinar si estamos en frontend
		if ( ! isset( $is_frontend ) ) {
			$is_frontend = ! is_admin();
		}
		
		// Variable para usar en la vista
		$is_frontend_dashboard = $is_frontend;

		include TUTOR_ATTENDANCE_PLUGIN_DIR . 'includes/admin/views/take-attendance-page.php';
	}

	/**
	 * Renderizar página de configuración
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'administrator' ) ) {
			wp_die( __( 'No tienes permisos para acceder a esta página.', 'tutor-attendance-calendar' ) );
		}

		// Guardar configuración
		if ( isset( $_POST['save_settings'] ) && check_admin_referer( 'tutor_attendance_settings' ) ) {
			// Estados de asistencia
			if ( isset( $_POST['attendance_states'] ) && is_array( $_POST['attendance_states'] ) ) {
				$states = array_map( 'sanitize_text_field', $_POST['attendance_states'] );
				$states = array_filter( $states ); // Eliminar vacíos
				update_option( 'tutor_attendance_states', $states );
			}

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Configuración guardada correctamente.', 'tutor-attendance-calendar' ) . '</p></div>';
		}

		// Obtener valores actuales
		$attendance_states = get_option( 'tutor_attendance_states', array( 'Asistió', 'Falta', 'Tarde', 'Justificado' ) );

		include TUTOR_ATTENDANCE_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
	}

	/**
	 * Inicializar frontend para alumnos
	 */
	private function init_frontend() {
		// Agregar pestaña de asistencia en dashboard de Tutor LMS para estudiantes
		add_filter( 'tutor_dashboard/nav_items', array( $this, 'add_attendance_tab' ), 10, 1 );
		
		// Agregar páginas de asistencia para instructores en el dashboard
		add_filter( 'tutor_dashboard/instructor_nav_items', array( $this, 'add_instructor_attendance_menus' ), 10, 1 );
		
		// Cargar templates cuando se acceda a las páginas
		add_filter( 'load_dashboard_template_part_from_other_location', array( $this, 'load_attendance_templates' ), 10, 1 );
		
		// Agregar scripts y estilos
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		
		// Hook para agregar botones en las tarjetas de cursos en "Mis Cursos"
		add_action( 'tutor_my_courses_before_meta', array( $this, 'add_course_action_buttons' ), 10, 1 );
	}

	/**
	 * Agregar pestaña de asistencia en dashboard
	 */
	public function add_attendance_tab( $nav_items ) {
		$user_id = get_current_user_id();
		
		// Solo agregar para estudiantes
		if ( function_exists( 'tutor_utils' ) && tutor_utils()->is_student( $user_id ) ) {
			$nav_items['attendance'] = array(
				'title' => __( 'Mi Asistencia', 'tutor-attendance-calendar' ),
				'icon' => 'tutor-icon-calender',
				'type' => 'default',
			);
		}
		
		return $nav_items;
	}

	/**
	 * Agregar menús de asistencia para instructores
	 */
	public function add_instructor_attendance_menus( $nav_items ) {
		if ( ! current_user_can( 'tutor_instructor' ) && ! current_user_can( 'administrator' ) ) {
			return $nav_items;
		}

		// Agregar después de "My Courses"
		$new_items = array();
		foreach ( $nav_items as $key => $item ) {
			$new_items[ $key ] = $item;
			if ( $key === 'my-courses' ) {
				$new_items['attendance-calendar'] = array(
					'title'    => __( 'Calendario de Horarios', 'tutor-attendance-calendar' ),
					'auth_cap' => tutor()->instructor_role,
					'icon'     => 'tutor-icon-calender',
				);
				$new_items['attendance-take'] = array(
					'title'    => __( 'Tomar Asistencia', 'tutor-attendance-calendar' ),
					'auth_cap' => tutor()->instructor_role,
					'icon'     => 'tutor-icon-question',
				);
				$new_items['attendance-schedules'] = array(
					'title'    => __( 'Horarios de Cursos', 'tutor-attendance-calendar' ),
					'auth_cap' => tutor()->instructor_role,
					'icon'     => 'tutor-icon-clock-line',
				);
				// Agregar menú de Exportar Reportes para instructores y administradores
				$new_items['attendance-export'] = array(
					'title'    => __( 'Exportar Reportes', 'tutor-attendance-calendar' ),
					'auth_cap' => tutor()->instructor_role,
					'icon'     => 'tutor-icon-file-csv',
				);
			}
		}

		return $new_items;
	}

	/**
	 * Cargar templates de asistencia
	 */
	public function load_attendance_templates( $location ) {
		global $wp_query;
		
		$dashboard_page = isset( $wp_query->query_vars['tutor_dashboard_page'] ) ? $wp_query->query_vars['tutor_dashboard_page'] : '';
		
		if ( $dashboard_page === 'attendance' ) {
			// Template para estudiantes
			$template_file = TUTOR_ATTENDANCE_PLUGIN_DIR . 'templates/dashboard/attendance.php';
			if ( file_exists( $template_file ) ) {
				return $template_file;
			}
		} elseif ( $dashboard_page === 'attendance-calendar' ) {
			// Template para instructores: Calendario de Horarios
			$template_file = TUTOR_ATTENDANCE_PLUGIN_DIR . 'templates/dashboard/calendar-frontend.php';
			if ( file_exists( $template_file ) ) {
				return $template_file;
			}
		} elseif ( $dashboard_page === 'attendance-take' ) {
			// Template para instructores: Tomar Asistencia
			$template_file = TUTOR_ATTENDANCE_PLUGIN_DIR . 'templates/dashboard/take-attendance-frontend.php';
			if ( file_exists( $template_file ) ) {
				return $template_file;
			}
		} elseif ( $dashboard_page === 'attendance-schedules' ) {
			// Template para instructores: Horarios
			$template_file = TUTOR_ATTENDANCE_PLUGIN_DIR . 'templates/dashboard/schedules-frontend.php';
			if ( file_exists( $template_file ) ) {
				return $template_file;
			}
		} elseif ( $dashboard_page === 'attendance-export' ) {
			// Template para administradores: Exportar Reportes
			$template_file = TUTOR_ATTENDANCE_PLUGIN_DIR . 'templates/dashboard/export-frontend.php';
			if ( file_exists( $template_file ) ) {
				return $template_file;
			}
		}
		
		return $location;
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
		$is_my_courses = $dashboard_page === 'my-courses';
		$is_attendance = $dashboard_page === 'attendance';
		$is_attendance_take = $dashboard_page === 'attendance-take';
		$is_attendance_calendar = $dashboard_page === 'attendance-calendar';
		$is_attendance_schedules = $dashboard_page === 'attendance-schedules';
		
		if ( $is_dashboard ) {
			// Cargar estilos y scripts en todas las páginas del dashboard relacionadas con asistencia
			if ( $is_my_courses || $is_attendance || $is_attendance_take || $is_attendance_calendar || $is_attendance_schedules ) {
				wp_enqueue_style( 'tutor-attendance-frontend', TUTOR_ATTENDANCE_PLUGIN_URL . 'assets/css/frontend.css', array(), TUTOR_ATTENDANCE_VERSION );
			}
			
			// Cargar scripts para páginas que los necesitan
			if ( $is_attendance_take ) {
				// Cargar estilos del admin (incluye estilos de la tabla)
				wp_enqueue_style( 'tutor-attendance-admin', TUTOR_ATTENDANCE_PLUGIN_URL . 'assets/css/admin.css', array(), TUTOR_ATTENDANCE_VERSION );
			}
			
			if ( $is_attendance || $is_attendance_take ) {
				wp_enqueue_script( 'tutor-attendance-frontend', TUTOR_ATTENDANCE_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), TUTOR_ATTENDANCE_VERSION, true );
				wp_localize_script( 'tutor-attendance-frontend', 'tutorAttendance', array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'tutor_attendance_nonce' ),
				) );
			}
		}
	}


	/**
	 * Cargar scripts y estilos en admin
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( strpos( $hook, 'tutor-attendance' ) === false ) {
			return;
		}

		wp_enqueue_style( 'tutor-attendance-admin', TUTOR_ATTENDANCE_PLUGIN_URL . 'assets/css/admin.css', array(), TUTOR_ATTENDANCE_VERSION );
		wp_enqueue_script( 'tutor-attendance-admin', TUTOR_ATTENDANCE_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-datepicker' ), TUTOR_ATTENDANCE_VERSION, true );
		wp_localize_script( 'tutor-attendance-admin', 'tutorAttendance', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'tutor_attendance_nonce' ),
		) );
		
		// Estilos para datepicker
		wp_enqueue_style( 'jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css' );
	}

	/**
	 * Inicializar handlers AJAX
	 */
	private function init_ajax_handlers() {
		// Guardar asistencia (docente)
		add_action( 'wp_ajax_tutor_save_attendance', array( $this, 'ajax_save_attendance' ) );
		
		// Marcar asistencia (alumno)
		add_action( 'wp_ajax_tutor_mark_my_attendance', array( $this, 'ajax_mark_my_attendance' ) );
		
		// Obtener asistencias del estudiante
		add_action( 'wp_ajax_tutor_get_my_attendance', array( $this, 'ajax_get_my_attendance' ) );
	}

	/**
	 * AJAX: Guardar asistencia (docente)
	 */
	public function ajax_save_attendance() {
		// Verificar nonce (tanto en admin como en frontend)
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'tutor_attendance_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Error de seguridad. Por favor, recarga la página e intenta nuevamente.', 'tutor-attendance-calendar' ) ) );
		}

		if ( ! current_user_can( 'tutor_instructor' ) && ! current_user_can( 'administrator' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos.', 'tutor-attendance-calendar' ) ) );
		}

		$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
		$attendance_date = isset( $_POST['attendance_date'] ) ? sanitize_text_field( $_POST['attendance_date'] ) : '';
		$attendance_data = isset( $_POST['attendance'] ) ? $_POST['attendance'] : array();

		if ( empty( $course_id ) || empty( $attendance_date ) || empty( $attendance_data ) ) {
			wp_send_json_error( array( 'message' => __( 'Datos incompletos.', 'tutor-attendance-calendar' ) ) );
		}

		// Verificar que el instructor tenga acceso al curso
		if ( ! current_user_can( 'administrator' ) ) {
			$instructor_courses = $this->get_instructor_courses( get_current_user_id() );
			$course_ids = wp_list_pluck( $instructor_courses, 'ID' );
			if ( ! in_array( $course_id, $course_ids ) ) {
				wp_send_json_error( array( 'message' => __( 'No tienes acceso a este curso.', 'tutor-attendance-calendar' ) ) );
			}
		}

		// Para docentes: solo validar que la fecha esté dentro del período del curso
		// Los docentes pueden tomar asistencia en cualquier momento, no se validan horarios
		$course_date_from = get_post_meta( $course_id, '_tutor_attendance_date_from', true );
		$course_date_to = get_post_meta( $course_id, '_tutor_attendance_date_to', true );
		$attendance_timestamp = strtotime( $attendance_date );
		
		if ( ! empty( $course_date_from ) ) {
			$date_from_timestamp = strtotime( $course_date_from );
			if ( $attendance_timestamp < $date_from_timestamp ) {
				wp_send_json_error( array( 'message' => __( 'La fecha seleccionada está antes del inicio del curso.', 'tutor-attendance-calendar' ) ) );
			}
		}
		
		if ( ! empty( $course_date_to ) ) {
			$date_to_timestamp = strtotime( $course_date_to );
			if ( $attendance_timestamp > $date_to_timestamp ) {
				wp_send_json_error( array( 'message' => __( 'La fecha seleccionada está después del fin del curso.', 'tutor-attendance-calendar' ) ) );
			}
		}

		$result = $this->save_attendance_bulk( $course_id, $attendance_date, $attendance_data, get_current_user_id() );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Asistencia guardada correctamente.', 'tutor-attendance-calendar' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Error al guardar la asistencia.', 'tutor-attendance-calendar' ) ) );
		}
	}

	/**
	 * AJAX: Marcar asistencia propia (alumno)
	 */
	public function ajax_mark_my_attendance() {
		check_ajax_referer( 'tutor_attendance_nonce', 'nonce' );

		$student_id = get_current_user_id();
		$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
		
		if ( $course_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Curso no válido.', 'tutor-attendance-calendar' ) ) );
		}
		
		// Verificar que el alumno pueda marcar asistencia para este curso
		$course_student_can_mark = get_post_meta( $course_id, '_tutor_attendance_student_can_mark', true );
		// Si no está configurado, usar valor por defecto (true)
		if ( $course_student_can_mark === '' ) {
			$course_student_can_mark = true;
		} else {
			$course_student_can_mark = (bool) $course_student_can_mark;
		}
		
		if ( ! $course_student_can_mark ) {
			wp_send_json_error( array( 'message' => __( 'Los alumnos no pueden marcar su propia asistencia para este curso.', 'tutor-attendance-calendar' ) ) );
		}

		$attendance_date = isset( $_POST['attendance_date'] ) ? sanitize_text_field( $_POST['attendance_date'] ) : date( 'Y-m-d' );

		// Validar horario del curso si está habilitado
		if ( ! $this->can_mark_attendance_by_schedule( $course_id, $attendance_date ) ) {
			wp_send_json_error( array( 'message' => __( 'No puedes marcar asistencia fuera del horario de clase definido para este curso.', 'tutor-attendance-calendar' ) ) );
		}

		// Validar fecha (solo permitir hoy)
		$today = date( 'Y-m-d' );
		if ( $attendance_date !== $today ) {
			// Verificar límite de horas y minutos del curso
			$course_deadline_hours = get_post_meta( $course_id, '_tutor_attendance_deadline_hours', true );
			$course_deadline_minutes = get_post_meta( $course_id, '_tutor_attendance_deadline_minutes', true );
			
			// Si no están configurados, usar valores por defecto
			if ( $course_deadline_hours === '' ) {
				$course_deadline_hours = 24;
			} else {
				$course_deadline_hours = intval( $course_deadline_hours );
			}
			
			if ( $course_deadline_minutes === '' ) {
				$course_deadline_minutes = 0;
			} else {
				$course_deadline_minutes = intval( $course_deadline_minutes );
			}
			
			$deadline_seconds = ( $course_deadline_hours * 3600 ) + ( $course_deadline_minutes * 60 );
			$deadline_timestamp = strtotime( $attendance_date . ' 23:59:59' ) + $deadline_seconds;
			if ( time() > $deadline_timestamp ) {
				wp_send_json_error( array( 'message' => __( 'Ya pasó el tiempo límite para marcar asistencia de esta fecha.', 'tutor-attendance-calendar' ) ) );
			}
		}

		// Verificar que el estudiante esté inscrito en el curso
		if ( ! tutor_utils()->is_enrolled( $course_id, $student_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No estás inscrito en este curso.', 'tutor-attendance-calendar' ) ) );
		}

		// Obtener instructor del curso
		$course = get_post( $course_id );
		if ( ! $course ) {
			wp_send_json_error( array( 'message' => __( 'Curso no encontrado.', 'tutor-attendance-calendar' ) ) );
		}

		$instructor_id = $course->post_author;

		// Guardar asistencia
		$result = $this->save_attendance( $student_id, $course_id, $instructor_id, $attendance_date, 'Asistió', $student_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Asistencia marcada correctamente.', 'tutor-attendance-calendar' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Error al marcar la asistencia.', 'tutor-attendance-calendar' ) ) );
		}
	}

	/**
	 * AJAX: Obtener asistencias del estudiante
	 */
	public function ajax_get_my_attendance() {
		check_ajax_referer( 'tutor_attendance_nonce', 'nonce' );

		$student_id = get_current_user_id();
		$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );
		$date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : date( 'Y-m-d' );

		$attendance = $this->get_student_attendance( $student_id, $course_id, $date_from, $date_to );

		wp_send_json_success( array( 'attendance' => $attendance ) );
	}

	/**
	 * Inicializar configuración
	 */
	private function init_settings() {
		// Settings ya están en render_settings_page()
	}

	// ========== MÉTODOS DE BASE DE DATOS ==========

	/**
	 * Guardar asistencia individual
	 */
	private function save_attendance( $student_id, $course_id, $instructor_id, $attendance_date, $status, $marked_by, $notes = '' ) {
		global $wpdb;

		// Verificar que no exista ya
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$this->table_name} 
			WHERE student_id = %d AND course_id = %d AND attendance_date = %s",
			$student_id, $course_id, $attendance_date
		) );

		$data = array(
			'student_id' => $student_id,
			'course_id' => $course_id,
			'instructor_id' => $instructor_id,
			'attendance_date' => $attendance_date,
			'status' => $status,
			'marked_by' => $marked_by,
			'notes' => $notes,
		);

		if ( $existing ) {
			// Actualizar
			return $wpdb->update(
				$this->table_name,
				$data,
				array( 'id' => $existing ),
				array( '%d', '%d', '%d', '%s', '%s', '%d', '%s' ),
				array( '%d' )
			) !== false;
		} else {
			// Insertar
			return $wpdb->insert(
				$this->table_name,
				$data,
				array( '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
			) !== false;
		}
	}

	/**
	 * Guardar asistencia en bloque
	 */
	private function save_attendance_bulk( $course_id, $attendance_date, $attendance_data, $marked_by ) {
		global $wpdb;

		$course = get_post( $course_id );
		if ( ! $course ) {
			return false;
		}

		$instructor_id = $course->post_author;
		$success_count = 0;

		foreach ( $attendance_data as $student_id => $status ) {
			$student_id = intval( $student_id );
			$status = sanitize_text_field( $status );

			if ( $student_id > 0 && ! empty( $status ) ) {
				if ( $this->save_attendance( $student_id, $course_id, $instructor_id, $attendance_date, $status, $marked_by ) ) {
					$success_count++;
				}
			}
		}

		return $success_count > 0;
	}

	/**
	 * Obtener asistencias del estudiante
	 */
	public function get_student_attendance( $student_id, $course_id = 0, $date_from = '', $date_to = '' ) {
		global $wpdb;

		$where = array( 'student_id = %d' );
		$where_values = array( $student_id );

		if ( $course_id > 0 ) {
			$where[] = 'course_id = %d';
			$where_values[] = $course_id;
		}

		if ( ! empty( $date_from ) ) {
			$where[] = 'attendance_date >= %s';
			$where_values[] = $date_from;
		}

		if ( ! empty( $date_to ) ) {
			$where[] = 'attendance_date <= %s';
			$where_values[] = $date_to;
		}

		$where_clause = implode( ' AND ', $where );

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->table_name} 
			WHERE {$where_clause} 
			ORDER BY attendance_date DESC",
			$where_values
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * Obtener asistencias para una fecha y curso
	 */
	private function get_attendance_for_date( $course_id, $attendance_date ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT student_id, status FROM {$this->table_name} 
			WHERE course_id = %d AND attendance_date = %s",
			$course_id, $attendance_date
		) );

		$attendance = array();
		foreach ( $results as $row ) {
			$attendance[ $row->student_id ] = $row->status;
		}

		return $attendance;
	}

	/**
	 * Obtener resumen de asistencias (para admin)
	 */
	private function get_attendance_summary( $course_id = 0, $instructor_id = 0, $date_from = '', $date_to = '', $status = '' ) {
		global $wpdb;

		$where = array( '1=1' );
		$where_values = array();

		if ( $course_id > 0 ) {
			$where[] = 'a.course_id = %d';
			$where_values[] = $course_id;
		}

		if ( $instructor_id > 0 ) {
			$where[] = 'a.instructor_id = %d';
			$where_values[] = $instructor_id;
		}

		if ( ! empty( $date_from ) ) {
			$where[] = 'a.attendance_date >= %s';
			$where_values[] = $date_from;
		}

		if ( ! empty( $date_to ) ) {
			$where[] = 'a.attendance_date <= %s';
			$where_values[] = $date_to;
		}

		if ( ! empty( $status ) ) {
			$where[] = 'a.status = %s';
			$where_values[] = $status;
		}

		$where_clause = implode( ' AND ', $where );

		$query = "SELECT 
			a.*,
			u.display_name as student_name,
			u.user_email as student_email,
			c.post_title as course_name,
			i.display_name as instructor_name
		FROM {$this->table_name} a
		LEFT JOIN {$wpdb->users} u ON a.student_id = u.ID
		LEFT JOIN {$wpdb->posts} c ON a.course_id = c.ID
		LEFT JOIN {$wpdb->users} i ON a.instructor_id = i.ID
		WHERE {$where_clause}
		ORDER BY a.attendance_date DESC, c.post_title ASC";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		return $wpdb->get_results( $query );
	}

	/**
	 * Obtener estudiantes de un curso
	 */
	public function get_course_students( $course_id ) {
		global $wpdb;
		
		if ( ! function_exists( 'tutor_utils' ) ) {
			return array();
		}

		// Obtener TODOS los estudiantes matriculados, independientemente del estado
		// Esto es más confiable que usar get_students_data_by_course_id que solo busca 'completed'
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
		
		// Normalizar para asegurar que siempre tengamos objetos válidos
		$students = array();
		
		if ( empty( $students_raw ) || ! is_array( $students_raw ) ) {
			return $students;
		}
		
		foreach ( $students_raw as $student ) {
			if ( is_object( $student ) && isset( $student->ID ) ) {
				$student_id = intval( $student->ID );
				
				if ( $student_id > 0 ) {
					// Asegurar que tenga todas las propiedades necesarias
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
	 * Obtener cursos del instructor (incluyendo co-instructores)
	 */
	private function get_instructor_courses( $instructor_id ) {
		if ( ! function_exists( 'Tutor\Models\CourseModel' ) && ! class_exists( 'Tutor\Models\CourseModel' ) ) {
			// Fallback si Tutor LMS no está disponible
			$args = array(
				'post_type' => tutor()->course_post_type,
				'post_status' => 'publish',
				'author' => $instructor_id,
				'posts_per_page' => -1,
			);
			return get_posts( $args );
		}

		// Usar el método de Tutor LMS que incluye co-instructores
		$courses = \Tutor\Models\CourseModel::get_courses_by_instructor( 
			$instructor_id, 
			array( 'publish' ), 
			0, 
			PHP_INT_MAX, 
			false 
		);

		// Convertir a objetos WP_Post si es necesario
		if ( ! empty( $courses ) && is_array( $courses ) ) {
			$wp_posts = array();
			foreach ( $courses as $course ) {
				if ( is_object( $course ) && isset( $course->ID ) ) {
					// Ya es un objeto con ID
					$wp_posts[] = get_post( $course->ID );
				} elseif ( is_numeric( $course ) ) {
					// Es solo un ID
					$wp_posts[] = get_post( $course );
				}
			}
			$courses = array_filter( $wp_posts ); // Eliminar nulls
		}

		return $courses ? $courses : array();
	}

	/**
	 * Obtener todos los cursos
	 */
	private function get_all_courses() {
		$args = array(
			'post_type' => tutor()->course_post_type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
		);

		return get_posts( $args );
	}

	/**
	 * Obtener todos los instructores
	 */
	private function get_all_instructors() {
		$instructors = get_users( array(
			'role' => 'tutor_instructor',
			'number' => -1,
		) );

		return $instructors;
	}

	/**
	 * Obtener cursos del estudiante
	 */
	public function get_student_courses( $student_id ) {
		if ( ! function_exists( 'tutor_utils' ) ) {
			return array();
		}

		$courses = tutor_utils()->get_enrolled_courses_by_user( $student_id );
		return $courses;
	}

	/**
	 * Renderizar página de horarios de cursos
	 * Se puede usar tanto en admin como en frontend
	 */
	public function render_schedules_page( $is_frontend = false ) {
		global $wpdb;
		
		$user_id = get_current_user_id();
		$is_admin = current_user_can( 'administrator' );
		
		// Obtener cursos del instructor o todos si es admin
		if ( $is_admin ) {
			$instructor_courses = $this->get_all_courses();
		} else {
			$instructor_courses = $this->get_instructor_courses( $user_id );
		}

		// Obtener curso seleccionado
		$selected_course_id = isset( $_GET['course_id'] ) ? intval( $_GET['course_id'] ) : 0;
		
		// Guardar horarios
		if ( isset( $_POST['save_schedules'] ) ) {
			// Verificar nonce (diferente según si es frontend o admin)
			if ( $is_frontend ) {
				if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'tutor_attendance_schedules' ) ) {
					if ( $is_frontend ) {
						echo '<div class="tutor-alert tutor-alert-error"><p>' . esc_html__( 'Error de seguridad. Por favor, intenta nuevamente.', 'tutor-attendance-calendar' ) . '</p></div>';
						return;
					}
					wp_die( __( 'Error de seguridad.', 'tutor-attendance-calendar' ) );
				}
			} else {
				check_admin_referer( 'tutor_attendance_schedules' );
			}
			$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;
			
			if ( $course_id > 0 ) {
				// Verificar permisos
				$course = get_post( $course_id );
				if ( $course && ( $is_admin || $course->post_author == $user_id ) ) {
					// Guardar fechas del curso
					$course_date_from = isset( $_POST['course_date_from'] ) && ! empty( $_POST['course_date_from'] ) ? sanitize_text_field( $_POST['course_date_from'] ) : '';
					$course_date_to = isset( $_POST['course_date_to'] ) && ! empty( $_POST['course_date_to'] ) ? sanitize_text_field( $_POST['course_date_to'] ) : '';
					
					if ( ! empty( $course_date_from ) ) {
						update_post_meta( $course_id, '_tutor_attendance_date_from', $course_date_from );
					} else {
						delete_post_meta( $course_id, '_tutor_attendance_date_from' );
					}
					
					if ( ! empty( $course_date_to ) ) {
						update_post_meta( $course_id, '_tutor_attendance_date_to', $course_date_to );
					} else {
						delete_post_meta( $course_id, '_tutor_attendance_date_to' );
					}
					
					// Guardar opción de usar horarios para este curso
					$course_use_schedules = isset( $_POST['course_use_schedules'] ) ? 1 : 0;
					update_post_meta( $course_id, '_tutor_attendance_use_schedules', $course_use_schedules );
					
					// Guardar opciones de alumno para este curso
					$course_student_can_mark = isset( $_POST['course_student_can_mark'] ) ? 1 : 0;
					update_post_meta( $course_id, '_tutor_attendance_student_can_mark', $course_student_can_mark );
					
					$course_deadline_hours = isset( $_POST['course_deadline_hours'] ) ? intval( $_POST['course_deadline_hours'] ) : 24;
					$course_deadline_hours = max( 0, min( 168, $course_deadline_hours ) );
					update_post_meta( $course_id, '_tutor_attendance_deadline_hours', $course_deadline_hours );
					
					$course_deadline_minutes = isset( $_POST['course_deadline_minutes'] ) ? intval( $_POST['course_deadline_minutes'] ) : 0;
					$course_deadline_minutes = max( 0, min( 59, $course_deadline_minutes ) );
					update_post_meta( $course_id, '_tutor_attendance_deadline_minutes', $course_deadline_minutes );
					
					// Eliminar horarios existentes del curso
					$wpdb->delete(
						$this->schedule_table_name,
						array( 'course_id' => $course_id ),
						array( '%d' )
					);

					// Guardar nuevos horarios
					$saved_count = 0;
					if ( isset( $_POST['schedules'] ) && is_array( $_POST['schedules'] ) ) {
						foreach ( $_POST['schedules'] as $day_num => $day_schedules ) {
							if ( is_array( $day_schedules ) ) {
								foreach ( $day_schedules as $index => $schedule ) {
									if ( ! is_array( $schedule ) ) {
										continue;
									}
									
									$day = isset( $schedule['day'] ) ? intval( $schedule['day'] ) : intval( $day_num );
									$start_time = isset( $schedule['start_time'] ) ? sanitize_text_field( $schedule['start_time'] ) : '';
									$end_time = isset( $schedule['end_time'] ) ? sanitize_text_field( $schedule['end_time'] ) : '';

									// Normalizar formato de hora a HH:MM:SS
									if ( ! empty( $start_time ) ) {
										// Si ya tiene formato HH:MM:SS, usarlo directamente
										if ( strlen( $start_time ) == 8 && substr_count( $start_time, ':' ) == 2 ) {
											// Ya tiene formato correcto
										} elseif ( strlen( $start_time ) == 5 && substr_count( $start_time, ':' ) == 1 ) {
											// Formato HH:MM, agregar :00
											$start_time = $start_time . ':00';
										} else {
											// Intentar convertir
											$start_time = date( 'H:i:s', strtotime( $start_time ) );
										}
									}
									
									if ( ! empty( $end_time ) ) {
										if ( strlen( $end_time ) == 8 && substr_count( $end_time, ':' ) == 2 ) {
											// Ya tiene formato correcto
										} elseif ( strlen( $end_time ) == 5 && substr_count( $end_time, ':' ) == 1 ) {
											// Formato HH:MM, agregar :00
											$end_time = $end_time . ':00';
										} else {
											$end_time = date( 'H:i:s', strtotime( $end_time ) );
										}
									}

									if ( $day >= 0 && $day <= 6 && ! empty( $start_time ) && ! empty( $end_time ) ) {
										$insert_result = $wpdb->insert(
											$this->schedule_table_name,
											array(
												'course_id' => $course_id,
												'day_of_week' => $day,
												'start_time' => $start_time,
												'end_time' => $end_time,
											),
											array( '%d', '%d', '%s', '%s' )
										);
										
										if ( $insert_result !== false ) {
											$saved_count++;
										} else {
											// Error al insertar - mostrar en admin
											$error_msg = sprintf(
												__( 'Error al guardar horario para día %d: %s', 'tutor-attendance-calendar' ),
												$day,
												$wpdb->last_error
											);
											echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_msg ) . '</p></div>';
										}
									}
								}
							}
						}
						
					}
					
					// Siempre actualizar $selected_course_id después de guardar
					$selected_course_id = $course_id;
					
					// Mostrar mensaje de éxito
					if ( $saved_count > 0 ) {
						echo '<div class="notice notice-success is-dismissible"><p>' . 
							sprintf( 
								esc_html__( 'Horarios guardados correctamente. Se guardaron %d horario(s).', 'tutor-attendance-calendar' ), 
								$saved_count
							) . '</p></div>';
					} else {
						echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'No se guardaron horarios. Verifica que hayas ingresado los datos correctamente.', 'tutor-attendance-calendar' ) . '</p></div>';
					}
				}
			}
		}

		// Obtener horarios del curso seleccionado
		$schedules = array();
		$schedules_by_day = array();
		
		if ( $selected_course_id > 0 ) {
			// Verificar que la tabla existe
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$this->schedule_table_name}'" ) === $this->schedule_table_name;
			
			if ( $table_exists ) {
				$schedules = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$this->schedule_table_name} WHERE course_id = %d ORDER BY day_of_week, start_time",
					$selected_course_id
				), OBJECT );

				// Organizar horarios por día
				if ( is_array( $schedules ) ) {
					foreach ( $schedules as $schedule ) {
						if ( is_object( $schedule ) && isset( $schedule->day_of_week ) ) {
							$day_num = intval( $schedule->day_of_week );
							if ( ! isset( $schedules_by_day[ $day_num ] ) ) {
								$schedules_by_day[ $day_num ] = array();
							}
							$schedules_by_day[ $day_num ][] = $schedule;
						}
					}
				}
			} else {
				// Tabla no existe, crear mensaje de error
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error: La tabla de horarios no existe. Por favor, desactiva y reactiva el plugin.', 'tutor-attendance-calendar' ) . '</p></div>';
			}
		}

		$days_of_week = array(
			0 => __( 'Domingo', 'tutor-attendance-calendar' ),
			1 => __( 'Lunes', 'tutor-attendance-calendar' ),
			2 => __( 'Martes', 'tutor-attendance-calendar' ),
			3 => __( 'Miércoles', 'tutor-attendance-calendar' ),
			4 => __( 'Jueves', 'tutor-attendance-calendar' ),
			5 => __( 'Viernes', 'tutor-attendance-calendar' ),
			6 => __( 'Sábado', 'tutor-attendance-calendar' ),
		);

		// Variable para usar en la vista
		$is_frontend_dashboard = $is_frontend;

		include TUTOR_ATTENDANCE_PLUGIN_DIR . 'includes/admin/views/schedules-page.php';
	}

	/**
	 * Obtener horarios de un curso
	 */
	public function get_course_schedules( $course_id ) {
		global $wpdb;
		
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$this->schedule_table_name} WHERE course_id = %d ORDER BY day_of_week, start_time",
			$course_id
		) );
	}

	/**
	 * Verificar si el estudiante puede marcar asistencia según el horario del curso
	 */
	public function can_mark_attendance_by_schedule( $course_id, $attendance_date = null ) {
		// Verificar si este curso específico usa horarios
		$course_use_schedules = get_post_meta( $course_id, '_tutor_attendance_use_schedules', true );
		
		if ( ! $course_use_schedules ) {
			return true; // Si este curso no usa horarios, permitir siempre
		}

		if ( ! $attendance_date ) {
			$attendance_date = date( 'Y-m-d' );
		}

		$schedules = $this->get_course_schedules( $course_id );
		
		if ( empty( $schedules ) ) {
			return true; // Si no hay horarios definidos, permitir
		}

		// Verificar fechas del curso
		$course_date_from = get_post_meta( $course_id, '_tutor_attendance_date_from', true );
		$course_date_to = get_post_meta( $course_id, '_tutor_attendance_date_to', true );
		$attendance_timestamp = strtotime( $attendance_date );

		if ( ! empty( $course_date_from ) ) {
			$date_from_timestamp = strtotime( $course_date_from );
			if ( $attendance_timestamp < $date_from_timestamp ) {
				return false; // La fecha está antes del inicio del curso
			}
		}

		if ( ! empty( $course_date_to ) ) {
			$date_to_timestamp = strtotime( $course_date_to );
			if ( $attendance_timestamp > $date_to_timestamp ) {
				return false; // La fecha está después del fin del curso
			}
		}

		$day_of_week = date( 'w', strtotime( $attendance_date ) ); // 0=Domingo, 6=Sábado
		$current_time = date( 'H:i:s' );

		// Buscar horarios para este día
		foreach ( $schedules as $schedule ) {
			if ( $schedule->day_of_week == $day_of_week ) {
				// Verificar si la hora actual está dentro del rango
				if ( $current_time >= $schedule->start_time && $current_time <= $schedule->end_time ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Interceptar exportación muy temprano en init (para admin y frontend)
	 */
	public function intercept_export_request_early() {
		// Verificar si es una petición de exportación en admin
		$is_admin_export = isset( $_GET['page'] ) && $_GET['page'] === 'tutor-attendance-export' && ( isset( $_POST['export_excel'] ) || ( isset( $_GET['export'] ) && $_GET['export'] === 'excel' ) );
		
		// Verificar si es una petición de exportación en frontend
		if ( ! $is_admin_export && function_exists( 'tutor_utils' ) ) {
			$current_url = ( isset( $_SERVER['REQUEST_URI'] ) ) ? $_SERVER['REQUEST_URI'] : '';
			$is_frontend_export = strpos( $current_url, 'attendance-export' ) !== false && ( isset( $_POST['export_excel'] ) || ( isset( $_GET['export'] ) && $_GET['export'] === 'excel' ) );
		} else {
			$is_frontend_export = false;
		}
		
		if ( $is_admin_export || $is_frontend_export ) {
			// Limpiar todos los buffers antes de procesar
			$this->clean_all_output_buffers();
			$this->export_attendance_to_excel();
			exit;
		}
	}

	/**
	 * Interceptar exportación en admin antes de cualquier output
	 */
	public function intercept_export_request() {
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'tutor-attendance-export' ) {
			if ( isset( $_POST['export_excel'] ) || ( isset( $_GET['export'] ) && $_GET['export'] === 'excel' ) ) {
				// Limpiar todos los buffers antes de procesar
				$this->clean_all_output_buffers();
				$this->export_attendance_to_excel();
				exit;
			}
		}
	}

	/**
	 * Interceptar exportación en frontend antes de cualquier output
	 */
	public function intercept_export_request_frontend() {
		global $wp_query;
		$dashboard_page = isset( $wp_query->query_vars['tutor_dashboard_page'] ) ? $wp_query->query_vars['tutor_dashboard_page'] : '';
		
		if ( $dashboard_page === 'attendance-export' ) {
			if ( isset( $_POST['export_excel'] ) || ( isset( $_GET['export'] ) && $_GET['export'] === 'excel' ) ) {
				// Limpiar todos los buffers antes de procesar
				$this->clean_all_output_buffers();
				$this->export_attendance_to_excel();
				exit;
			}
		}
	}

	/**
	 * Limpiar todos los buffers de salida
	 */
	private function clean_all_output_buffers() {
		while ( @ob_get_level() > 0 ) {
			@ob_end_clean();
		}
		// Deshabilitar compresión
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', 1 );
		}
		@ini_set( 'zlib.output_compression', 0 );
		@ini_set( 'output_buffering', 0 );
	}

	/**
	 * Exportar asistencia a Excel
	 */
	private function export_attendance_to_excel() {
		// Limpiar buffers nuevamente por seguridad
		$this->clean_all_output_buffers();
		
		// Permitir acceso a instructores y administradores
		if ( ! current_user_can( 'tutor_instructor' ) && ! current_user_can( 'administrator' ) ) {
			wp_die( __( 'No tienes permisos para realizar esta acción.', 'tutor-attendance-calendar' ) );
		}

		// Verificar nonce si viene del formulario POST
		if ( isset( $_POST['export_excel'] ) ) {
			if ( ! isset( $_POST['export_nonce'] ) || ! wp_verify_nonce( $_POST['export_nonce'], 'tutor_export_attendance' ) ) {
				wp_die( __( 'Error de seguridad. Por favor, intenta nuevamente.', 'tutor-attendance-calendar' ) );
			}
		}

		$current_user_id = get_current_user_id();
		$is_admin = current_user_can( 'administrator' );

		// Obtener filtros de POST o GET
		$course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : ( isset( $_GET['course_id'] ) ? intval( $_GET['course_id'] ) : 0 );
		$instructor_id = isset( $_POST['instructor_id'] ) ? intval( $_POST['instructor_id'] ) : ( isset( $_GET['instructor_id'] ) ? intval( $_GET['instructor_id'] ) : 0 );
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : ( isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : date( 'Y-m-d', strtotime( '-30 days' ) ) );
		$date_to = isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : ( isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : date( 'Y-m-d' ) );
		$status_filter = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : ( isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '' );

		// Si es instructor (no admin), forzar que solo vea sus cursos
		if ( ! $is_admin ) {
			// Verificar que el curso pertenezca al instructor
			if ( $course_id > 0 ) {
				$instructor_courses = $this->get_instructor_courses( $current_user_id );
				$course_ids = array_map( function( $course ) {
					return is_object( $course ) ? $course->ID : ( is_numeric( $course ) ? $course : 0 );
				}, $instructor_courses );
				
				if ( ! in_array( $course_id, $course_ids ) ) {
					wp_die( __( 'No tienes permisos para exportar datos de este curso.', 'tutor-attendance-calendar' ) );
				}
			}
			// Forzar que solo se muestren sus cursos (usando instructor_id = current_user_id)
			$instructor_id = $current_user_id;
		}

		// Obtener datos ANTES de limpiar buffers (para evitar errores)
		$attendance_data = $this->get_attendance_summary( $course_id, $instructor_id, $date_from, $date_to, $status_filter );

		// Limpiar TODOS los buffers ANTES de enviar headers
		$this->clean_all_output_buffers();

		// Generar nombre del archivo
		$filename = 'asistencia_' . date( 'Y-m-d_His' ) . '.csv';

		// Headers para descarga - enviar ANTES de cualquier output
		// NO usar nocache_headers() porque puede generar output
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0, no-cache, no-store' );
		header( 'Content-Transfer-Encoding: binary' );

		// Abrir output stream directamente
		$output = fopen( 'php://output', 'w' );
		if ( ! $output ) {
			wp_die( __( 'Error al crear el archivo de exportación.', 'tutor-attendance-calendar' ) );
		}

		// BOM para UTF-8 (para que Excel lo lea correctamente)
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		// Encabezados
		$headers = array(
			__( 'Fecha', 'tutor-attendance-calendar' ),
			__( 'Curso', 'tutor-attendance-calendar' ),
			__( 'Alumno', 'tutor-attendance-calendar' ),
			__( 'Email', 'tutor-attendance-calendar' ),
			__( 'Docente', 'tutor-attendance-calendar' ),
			__( 'Estado', 'tutor-attendance-calendar' ),
			__( 'Marcado por', 'tutor-attendance-calendar' ),
		);
		fputcsv( $output, $headers, ';' ); // Usar punto y coma como delimitador

		// Datos
		if ( ! empty( $attendance_data ) ) {
			foreach ( $attendance_data as $record ) {
				$marked_by_user = get_userdata( $record->marked_by );
				$marked_by_name = $marked_by_user ? $marked_by_user->display_name : '-';
				
				$row = array(
					date_i18n( get_option( 'date_format' ), strtotime( $record->attendance_date ) ),
					$record->course_name,
					$record->student_name,
					$record->student_email,
					$record->instructor_name,
					$record->status,
					$marked_by_name,
				);
				fputcsv( $output, $row, ';' );
			}
		}

		fclose( $output );
		exit;
	}

	/**
	 * Renderizar página de calendario de horarios
	 * Se puede usar tanto en admin como en frontend
	 */
	public function render_calendar_page( $is_frontend = false ) {
		if ( ! current_user_can( 'tutor_instructor' ) && ! current_user_can( 'administrator' ) ) {
			if ( $is_frontend ) {
				return '<p>' . __( 'No tienes permisos para acceder a esta página.', 'tutor-attendance-calendar' ) . '</p>';
			}
			wp_die( __( 'No tienes permisos para acceder a esta página.', 'tutor-attendance-calendar' ) );
		}

		global $wpdb;
		
		$current_user_id = get_current_user_id();
		$is_admin = current_user_can( 'administrator' );
		
		// Obtener cursos del instructor
		if ( $is_admin ) {
			$instructor_courses = $this->get_all_courses();
		} else {
			$instructor_courses = $this->get_instructor_courses( $current_user_id );
		}

		// Obtener todos los horarios de los cursos del instructor
		$all_schedules = array();
		$courses_with_schedules = array();
		
		if ( ! empty( $instructor_courses ) ) {
			$course_ids = array();
			foreach ( $instructor_courses as $course ) {
				$course_id = is_object( $course ) && isset( $course->ID ) ? $course->ID : ( is_numeric( $course ) ? $course : 0 );
				if ( $course_id > 0 ) {
					$course_ids[] = $course_id;
					$courses_with_schedules[ $course_id ] = $course;
				}
			}

			if ( ! empty( $course_ids ) ) {
				$course_ids_placeholders = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );
				
				$schedules_query = $wpdb->prepare(
					"SELECT s.*, c.post_title as course_title
					FROM {$this->schedule_table_name} s
					INNER JOIN {$wpdb->posts} c ON s.course_id = c.ID
					WHERE s.course_id IN ({$course_ids_placeholders})
					ORDER BY s.day_of_week, s.start_time",
					$course_ids
				);
				
				$all_schedules = $wpdb->get_results( $schedules_query, OBJECT );
			}
		}

		// Organizar horarios por día de la semana y curso
		$schedules_by_day = array();
		$days_of_week = array(
			0 => __( 'Domingo', 'tutor-attendance-calendar' ),
			1 => __( 'Lunes', 'tutor-attendance-calendar' ),
			2 => __( 'Martes', 'tutor-attendance-calendar' ),
			3 => __( 'Miércoles', 'tutor-attendance-calendar' ),
			4 => __( 'Jueves', 'tutor-attendance-calendar' ),
			5 => __( 'Viernes', 'tutor-attendance-calendar' ),
			6 => __( 'Sábado', 'tutor-attendance-calendar' ),
		);

		foreach ( $all_schedules as $schedule ) {
			$day = intval( $schedule->day_of_week );
			if ( ! isset( $schedules_by_day[ $day ] ) ) {
				$schedules_by_day[ $day ] = array();
			}
			$schedules_by_day[ $day ][] = $schedule;
		}

		// Determinar si estamos en frontend
		if ( ! isset( $is_frontend ) ) {
			$is_frontend = ! is_admin();
		}
		$is_frontend_dashboard = $is_frontend;

		// Incluir el template
		include TUTOR_ATTENDANCE_PLUGIN_DIR . 'includes/admin/views/calendar-page.php';
	}

	/**
	 * Agregar botones de acción en las tarjetas de curso en "Mis Cursos"
	 */
	public function add_course_action_buttons( $course_id ) {
		// Solo mostrar para instructores y administradores
		if ( ! current_user_can( 'tutor_instructor' ) && ! current_user_can( 'administrator' ) ) {
			return;
		}

		// Verificar que el usuario es instructor de este curso o es admin
		$current_user_id = get_current_user_id();
		$course = get_post( $course_id );
		
		if ( ! $course ) {
			return;
		}

		if ( ! current_user_can( 'administrator' ) ) {
			// Verificar si el usuario es instructor del curso (principal o co-instructor)
			$is_instructor = false;
			
			// Verificar si es autor principal
			if ( $course->post_author == $current_user_id ) {
				$is_instructor = true;
			} else {
				// Verificar si es co-instructor usando Tutor LMS
				if ( function_exists( 'tutor_utils' ) ) {
					$course_instructors = tutor_utils()->get_course_instructors_by_course( $course_id );
					if ( ! empty( $course_instructors ) && is_array( $course_instructors ) ) {
						foreach ( $course_instructors as $instructor ) {
							$instructor_id = is_object( $instructor ) && isset( $instructor->ID ) ? $instructor->ID : ( is_numeric( $instructor ) ? $instructor : 0 );
							if ( $instructor_id == $current_user_id ) {
								$is_instructor = true;
								break;
							}
						}
					}
				}
				
				// También verificar usando CourseModel si está disponible
				if ( ! $is_instructor && class_exists( '\Tutor\Models\CourseModel' ) ) {
					$instructor_courses = \Tutor\Models\CourseModel::get_courses_by_instructor( $current_user_id );
					if ( ! empty( $instructor_courses ) ) {
						foreach ( $instructor_courses as $inst_course ) {
							$inst_course_id = is_object( $inst_course ) && isset( $inst_course->ID ) ? $inst_course->ID : ( is_numeric( $inst_course ) ? $inst_course : 0 );
							if ( $inst_course_id == $course_id ) {
								$is_instructor = true;
								break;
							}
						}
					}
				}
			}
			
			if ( ! $is_instructor ) {
				return;
			}
		}

		// URLs para los botones - usar dashboard de Tutor LMS
		$schedules_url = tutor_utils()->tutor_dashboard_url( 'attendance-schedules' ) . '?course_id=' . $course_id;
		$take_attendance_url = tutor_utils()->tutor_dashboard_url( 'attendance-take' ) . '?course_id=' . $course_id . '&attendance_date=' . date( 'Y-m-d' );

		?>
		<div class="tutor-attendance-course-actions tutor-d-flex tutor-gap-2 tutor-mb-12 tutor-mt-8">
			<a href="<?php echo esc_url( $schedules_url ); ?>" 
				class="tutor-btn tutor-btn-sm tutor-btn-outline-primary tutor-d-flex tutor-align-center tutor-gap-1" 
				title="<?php esc_attr_e( 'Configurar horarios del curso', 'tutor-attendance-calendar' ); ?>">
				<span class="tutor-icon-clock-line" area-hidden="true"></span>
				<span><?php esc_html_e( 'Horarios', 'tutor-attendance-calendar' ); ?></span>
			</a>
			<a href="<?php echo esc_url( $take_attendance_url ); ?>" 
				class="tutor-btn tutor-btn-sm tutor-btn-primary tutor-d-flex tutor-align-center tutor-gap-1" 
				title="<?php esc_attr_e( 'Tomar asistencia de los estudiantes', 'tutor-attendance-calendar' ); ?>">
				<span class="tutor-icon-check-line" area-hidden="true"></span>
				<span><?php esc_html_e( 'Tomar Asistencia', 'tutor-attendance-calendar' ); ?></span>
			</a>
		</div>
		<?php
	}
}

// Inicializar el plugin
Tutor_Attendance_Calendar::get_instance();
