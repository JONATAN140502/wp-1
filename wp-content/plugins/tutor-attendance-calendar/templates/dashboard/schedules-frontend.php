<?php
/**
 * Template: Horarios de Cursos (Frontend Dashboard)
 * 
 * @package TutorAttendanceCalendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin = Tutor_Attendance_Calendar::get_instance();
$plugin->render_schedules_page( true );

