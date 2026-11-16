<?php
/**
 * Codice del modulo Ricavi docente. Viene incluso solo se il modulo è acceso.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-teacher-revenue.php';
require_once __DIR__ . '/class-teacher-revenue-admin.php';

AEL_Teacher_Revenue::instance()->boot();
if ( is_admin() ) {
	AEL_Teacher_Revenue_Admin::instance()->boot();
}
