<?php
/**
 * Codice del modulo di prova. Viene incluso solo se il modulo è acceso.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( AEL_Settings::CAP ) ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			esc_html__( 'Modulo di prova acceso: i suoi file sono stati caricati.', 'academy-elearning' )
		);
	}
);
