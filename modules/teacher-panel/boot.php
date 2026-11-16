<?php
/**
 * Codice del modulo Pannello docente. Viene incluso solo se il modulo è acceso.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-teacher-panel.php';

// Il registro vendite lo carica il modulo Ricavi docente: ci si aggancia a caricamento finito.
add_action(
	'ael_modules_loaded',
	static function () {
		if ( class_exists( 'AEL_Teacher_Revenue' ) ) {
			AEL_Teacher_Panel::instance()->boot();
		}
	}
);
