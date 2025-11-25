<?php
/**
 * Template: Tomar Asistencia (Frontend Dashboard)
 * 
 * @package TutorAttendanceCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin = Tutor_Attendance_Calendar::get_instance();
$plugin->render_take_attendance_page( true );

