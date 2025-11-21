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

		// Aplicar modificaciones al archivo JavaScript
		$this->apply_js_modifications();

		// Agregar campo DNI en el perfil de usuario
		$this->init_user_profile_fields();
	}

	/**
	 * Activar el plugin
	 */
	public function activate() {
		// Crear backup del archivo original
		if ( file_exists( $this->main_js_file ) && ! file_exists( $this->backup_file ) ) {
			copy( $this->main_js_file, $this->backup_file );
		}

		// Aplicar modificaciones
		$this->apply_js_modifications();
		
		// Limpiar variables dni duplicadas y migrar a _tutor_user_dni
		$this->cleanup_duplicate_dni_meta();
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
		// Restaurar el archivo original desde el backup
		if ( file_exists( $this->backup_file ) && file_exists( $this->main_js_file ) ) {
			copy( $this->backup_file, $this->main_js_file );
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
		// Buscar: "Component: Pg,}; const Tg =" con diferentes variaciones de espacios y saltos de línea
		// El archivo tiene: Component: Pg,\n        };\n      const Tg = function (t) {
		// Usar el modificador 's' para que . también coincida con saltos de línea
		$patterns = array(
			'/Component:\s*Pg\s*,\s*\n\s*\};\s*\n\s*const\s+Tg\s*=\s*function/s',  // Con saltos de línea y function
			'/Component:\s*Pg\s*,\s*\n\s*\};\s*\n\s*const\s+Tg\s*=/s',             // Con saltos de línea sin function
			'/Component:\s*Pg\s*,\s*\};\s*const\s+Tg\s*=\s*function/',             // Sin saltos de línea con function
			'/Component:\s*Pg\s*,\s*\};\s*const\s+Tg\s*=/',                       // Sin saltos de línea sin function
			'/Component:\s*Pg\s*,\s*\r?\n\s*\};\s*\r?\n\s*const\s+Tg\s*=\s*function/s', // Con \r\n y function
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
			$insert_code = 'Component: Pg,
        };
      const PgDni = function (t) {
          var e,
            n = t.id,
            i = t.name,
            r = t.componentRef,
            a = t.attributes,
            s = a.content,
            o = (a.position, a.textAlignment),
            A = a.isEditable,
            l = a.style,
            c = a.transparency,
            f = void 0 === c ? 1 : c,
            u = a.flippedType,
            d = (0, E.wA)(),
            p = (0, E.d4)(function (t) {
              return t.certificateData;
            }),
            v = (0, g.useState)((null == p ? void 0 : p.STUDENT_DNI) || s),
            b = (0, h.A)(v, 2),
            y = b[0],
            B = b[1];
          (0, g.useEffect)(
            function () {
              B((null == p ? void 0 : p.STUDENT_DNI) || s);
            },
            [p]
          );
          var k = m()(
              "".concat(tcb_prefix, "-element"),
              "".concat(tcb_prefix, "-element-").concat(i),
              "tcb-element-".concat(n),
              (0, w.A)({}, "has-text-".concat(o), o),
              (0, w.A)({}, "tcb-flip-".concat(u), void 0 !== u)
            ),
            F =
              null == l || null === (e = l.typography) || void 0 === e
                ? void 0
                : e.spacing;
          return (0, C.jsx)("div", {
            className: k,
            ref: r,
            style: { letterSpacing: F, opacity: f },
            children: (0, C.jsx)(vg, {
              textContent: y,
              isEditable: A,
              attributes: t.attributes,
              onChange: function (t) {
                d({ type: x.Nm, payload: { id: n, attributes: t } });
              },
              name: i,
            }),
          });
        },
        LgDni = {
          name: "student_dni",
          title: window.wp.i18n.__(
            "Student DNI",
            "tutor-lms-certificate-builder"
          ),
          icon: "student",
          category: "element",
          attributes: {
            content: "[ student_dni ]",
            formats: [],
            position: { top: 20, left: 80 },
            size: { width: 200, height: 50 },
            rotate: { value: 0, unit: "deg" },
            style: {
              typography: {
                family: "Lexend",
                type: "sans-serif",
                height: 1.4,
                weight: 400,
                spacing: 0,
                size: 20,
                fontFamily: "Lexend",
              },
              color: { textColor: "#000" },
            },
            textAlignment: "align_left",
            flippedType: null,
            align: "center",
            transparency: 100,
            isEditable: !1,
          },
          Component: PgDni,
        };
      const Tg = function (t) {';

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
		// Agregar campo DNI justo después de la sección "Name" - wp-admin
		// Usar prioridad baja para que se ejecute temprano, luego JavaScript lo moverá
		add_action( 'show_user_profile', array( $this, 'add_dni_field_after_name' ), 5 );
		add_action( 'edit_user_profile', array( $this, 'add_dni_field_after_name' ), 5 );

		// Agregar campo DNI en el formulario de nuevo usuario - wp-admin
		add_action( 'user_new_form', array( $this, 'add_dni_field_new_user' ), 20 );

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
	 * Agregar campo DNI justo después de la sección "Name" (wp-admin)
	 *
	 * @param WP_User $user Objeto de usuario
	 */
	public function add_dni_field_after_name( $user ) {
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

		// Agregar el campo dentro de la tabla "Name" usando JavaScript
		// Primero lo agregamos oculto y luego JavaScript lo moverá a la posición correcta
		?>
		<tr class="user-dni-wrap" style="display:none;">
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
		<tr class="form-field user-dni-wrap" style="display:none;">
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
				
				// Ocultar el campo mientras lo movemos
				dniRow.css('display', 'none');
				
				// Buscar h2 "Name" o "Nombre"
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
						dniRow.css('display', '');
					} else {
						dniRow.css('display', '');
					}
				} else {
					dniRow.css('display', '');
				}
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
				
				// ESTRATEGIA PRINCIPAL: Buscar h2 "Name" o "Nombre" y la tabla que le sigue inmediatamente
				var nameTable = null;
				var nameHeading = null;
				
				// Buscar todos los h2 y encontrar el que dice "Name" o "Nombre"
				$('h2').each(function() {
					var $h2 = $(this);
					var text = $h2.text().trim();
					
					// Verificar si es el h2 "Name" o "Nombre"
					if (text === 'Name' || text === 'Nombre') {
						nameHeading = $h2;
						
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
				
				// ESTRATEGIA FALLBACK 1: Buscar directamente la tabla con first_name (funciona en profile.php y user-new.php)
				var firstNameInput = $('#first_name');
				if (!firstNameInput.length) {
					firstNameInput = $('input[name="first_name"]');
				}
				
				if (firstNameInput.length) {
					var firstNameRow = firstNameInput.closest('tr');
					if (firstNameRow.length) {
						var nameTable = firstNameRow.closest('table.form-table');
						if (nameTable.length) {
							var tbody = nameTable.find('tbody');
							if (!tbody.length) {
								tbody = nameTable;
							}
							
							// Verificar si el DNI ya está en la primera posición
							var firstRow = tbody.find('tr').first();
							if (firstRow.hasClass('user-dni-wrap') && dniRow.closest('table.form-table')[0] === nameTable[0]) {
								dniRow.css('display', '');
								return true;
							}
							
							// Remover y mover
							dniRow.detach();
							if (firstRow.length && !firstRow.hasClass('user-dni-wrap')) {
								firstRow.before(dniRow);
							} else {
								tbody.prepend(dniRow);
							}
							
							// Mostrar el campo
							dniRow.css('display', '');
							
							return true;
						}
					}
				}
				
				// ESTRATEGIA FALLBACK 1.5: Para user-new.php, buscar también por form-field
				if (!nameTable || !nameTable.length) {
					var firstNameRowByClass = $('tr.form-field').has('input#first_name, input[name="first_name"]').first();
					if (firstNameRowByClass.length) {
						var nameTable = firstNameRowByClass.closest('table.form-table');
						if (nameTable.length) {
							var tbody = nameTable.find('tbody');
							if (!tbody.length) {
								tbody = nameTable;
							}
							var firstRow = tbody.find('tr').first();
							if (!firstRow.hasClass('user-dni-wrap')) {
								dniRow.detach();
								firstRow.before(dniRow);
								dniRow.css('display', '');
								return true;
							}
						}
					}
				}
				
				// ESTRATEGIA FALLBACK 2: Buscar cualquier tabla form-table que contenga campos de nombre
				$('table.form-table').each(function() {
					var $table = $(this);
					if ($table.find('input#first_name, input[name="first_name"], tr.user-first-name-wrap, tr.user-last-name-wrap').length) {
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
				
				return false;
			}
			
			// Función para verificar y corregir la posición del DNI
			function checkAndMoveDniField() {
				var dniRow = $('.user-dni-wrap').first();
				if (!dniRow.length) {
					return;
				}
				
				// Buscar h2 "Name" o "Nombre" y su tabla
				var nameTable = null;
				
				$('h2').each(function() {
					var $h2 = $(this);
					var text = $h2.text().trim();
					if (text === 'Name' || text === 'Nombre') {
						// Buscar la tabla que está justo después del h2
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
				
				// Si no se encontró, buscar directamente por first_name
				if (!nameTable || !nameTable.length) {
					var firstNameInput = $('#first_name, input[name="first_name"]').first();
					if (firstNameInput.length) {
						nameTable = firstNameInput.closest('table.form-table');
					}
				}
				
				if (nameTable && nameTable.length) {
					var tbody = nameTable.find('tbody');
					if (!tbody.length) {
						tbody = nameTable;
					}
					
					var firstRow = tbody.find('tr').first();
					
					// Verificar si el DNI está en la posición correcta
					var isInCorrectTable = dniRow.closest('table.form-table')[0] === nameTable[0];
					var isFirstRow = firstRow.hasClass('user-dni-wrap');
					
					// Si no está en la posición correcta, moverlo
					if (!isFirstRow || !isInCorrectTable) {
						moveDniField();
					}
				} else {
					// Si no se encontró la tabla, intentar mover de todas formas
					moveDniField();
				}
			}
			
			// Ejecutar inmediatamente
			moveDniField();
			
			// Ejecutar cuando el DOM esté listo
			$(document).ready(function() {
				setTimeout(function() {
					moveDniField();
					// Verificar cada 200ms durante los primeros 3 segundos
					var checkInterval = setInterval(function() {
						checkAndMoveDniField();
					}, 200);
					
					setTimeout(function() {
						clearInterval(checkInterval);
					}, 3000);
				}, 100);
			});
			
			// Ejecutar cuando la página esté completamente cargada
			$(window).on('load', function() {
				setTimeout(function() {
					moveDniField();
					checkAndMoveDniField();
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
							checkAndMoveDniField();
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
				checkAndMoveDniField();
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

