<?php
/**
 * Codice del modulo Attribuzione. Viene incluso solo se il modulo è acceso.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-attribution.php';
require_once __DIR__ . '/class-attribution-admin.php';

AEL_Attribution::instance()->boot();
if ( is_admin() ) {
	AEL_Attribution_Admin::instance()->boot();
}
