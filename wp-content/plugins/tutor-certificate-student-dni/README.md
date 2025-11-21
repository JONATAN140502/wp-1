# Tutor Certificate Student DNI

Plugin de WordPress que agrega el campo DNI del estudiante al constructor de certificados de Tutor LMS.

## Descripción

Este plugin extiende el constructor de certificados de Tutor LMS agregando un nuevo elemento "Student DNI" que permite mostrar el DNI del estudiante en los certificados. El plugin modifica automáticamente los archivos necesarios cuando se activa y los restaura cuando se desactiva.

## Características

- ✅ Agrega el elemento "Student DNI" al editor de certificados
- ✅ Modifica automáticamente los archivos JavaScript necesarios
- ✅ Crea un backup del archivo original antes de modificarlo
- ✅ Restaura el archivo original cuando se desactiva el plugin
- ✅ No requiere modificar manualmente el plugin de Tutor LMS

## Requisitos

- WordPress 5.0 o superior
- PHP 7.2 o superior
- Tutor LMS Certificate Builder instalado y activo

## Instalación

1. Sube la carpeta `tutor-certificate-student-dni` al directorio `/wp-content/plugins/`
2. Activa el plugin desde el menú de Plugins en WordPress
3. El plugin modificará automáticamente los archivos necesarios

## Uso

1. Ve al editor de certificados de Tutor LMS
2. En la pestaña de "Elements", encontrarás el nuevo elemento "Student DNI"
3. Arrástralo al certificado como cualquier otro elemento
4. El DNI se mostrará automáticamente cuando se genere el certificado

## Notas Importantes

- El plugin crea un backup del archivo `main.min.js` antes de modificarlo
- Si desactivas el plugin, el archivo original se restaurará automáticamente
- Si actualizas el plugin Tutor LMS Certificate Builder, necesitarás reactivar este plugin

## Soporte

Para soporte, por favor contacta al desarrollador.

## Licencia

GPL v2 o posterior

