<?php
/**
 * Template: Exportar Reportes (Frontend Dashboard)
 * 
 * @package TutorAttendanceCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin = Tutor_Attendance_Calendar::get_instance();
$plugin->render_export_page( true );

