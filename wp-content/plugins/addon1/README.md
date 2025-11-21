# Tutor Course URL Sesión

Plugin para añadir un campo de URL de sesión (Meet/Zoom) a los cursos de Tutor LMS sin modificar el código del plugin principal.

## Descripción

Este plugin añade un campo personalizado `course_url_sesion` a los cursos de Tutor LMS, permitiendo a los instructores añadir enlaces de reuniones en vivo (Google Meet, Zoom, etc.) a sus cursos. El plugin funciona completamente independiente del código de Tutor, por lo que las actualizaciones de Tutor no afectarán esta funcionalidad.

## Características

- ✅ Añade el campo "URL de Clases (Meet/Zoom)" al formulario de creación/edición de cursos
- ✅ Guarda el campo en la base de datos como meta del curso
- ✅ Muestra el campo en el REST API de Tutor
- ✅ Incluye un botón de acceso rápido en la sidebar de las lecciones
- ✅ Compatible con el Course Builder de Tutor (React)
- ✅ Funciona tanto en el admin como en el frontend

## Instalación

1. Sube la carpeta `tutor-course-url-sesion` a `/wp-content/plugins/`
2. Activa el plugin desde el panel de administración de WordPress
3. Asegúrate de que Tutor LMS esté instalado y activo

## Uso

### Para Instructores

1. Ve al Course Builder de Tutor (crear o editar un curso)
2. En la sección "Additional" (Adicional), encontrarás el campo "URL de Clases (Meet/Zoom)"
3. Ingresa el enlace de tu reunión (ej: `https://meet.google.com/xxx-xxxx-xxx` o `https://zoom.us/j/xxxxxxx`)
4. Guarda el curso

### Para Estudiantes

Los estudiantes verán un botón "Reunión" en la sidebar de las lecciones cuando el curso tenga una URL de sesión configurada. Al hacer clic, se abrirá la reunión en una nueva pestaña.

## Estructura del Plugin

```
tutor-course-url-sesion/
├── tutor-course-url-sesion.php  # Archivo principal del plugin
├── assets/
│   └── js/
│       ├── admin.js             # Script para el admin
│       └── frontend.js          # Script para el frontend
└── README.md                    # Este archivo
```

## Hooks y Filtros

El plugin utiliza los siguientes hooks de WordPress/Tutor:

### Filtros
- `tutor_course_additional_info` - Añade el campo al REST API
- `tutor_course_details_data` - Añade el campo a los detalles del curso
- `tutor_course_update_params` - Intercepta parámetros de actualización
- `tutor_course_create_params` - Intercepta parámetros de creación

### Acciones
- `tutor_save_course_meta` - Guarda el campo cuando se guarda el curso
- `save_post` - Guarda el campo en el método legacy
- `tutor_lesson_sidebar_after` - Añade el botón en la sidebar de la lección

## Desarrollo

### Requisitos

- WordPress 5.0+
- PHP 7.4+
- Tutor LMS (cualquier versión reciente)

### Personalización

Puedes personalizar las etiquetas del campo editando el archivo `tutor-course-url-sesion.php` en la función `enqueue_admin_scripts()` y `enqueue_frontend_scripts()`.

## Notas Importantes

- Este plugin NO modifica el código de Tutor LMS
- Las actualizaciones de Tutor LMS no afectarán este plugin
- El campo se guarda como `_tutor_course_url_sesion` en la tabla `wp_postmeta`
- El plugin intercepta las llamadas AJAX y REST API para asegurar que el campo se guarde correctamente

## Soporte

Si encuentras algún problema o tienes sugerencias, por favor crea un issue en el repositorio del plugin.

## Licencia

GPL v2 or later

## Changelog

### 1.0.0
- Versión inicial
- Añade campo de URL de sesión al course builder
- Integración con REST API
- Botón de acceso rápido en lecciones

