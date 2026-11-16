<?php
/**
 * Modulo "Pannello docente": il docente vede le proprie vendite e quote, in sola lettura.
 *
 * Questo file dichiara soltanto il modulo: niente hook, niente traduzioni.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'             => 'Pannello docente',
	'description'      => 'Il docente entra con il proprio utente e vede solo le proprie vendite e quote, in sola lettura. Richiede il modulo Ricavi docente.',
	'version'          => '0.1.0',
	'requires_modules' => array( 'teacher-revenue' ),
	'requires_plugins' => array(),
	'boot'             => 'boot.php',
	'dev_only'         => false,
);
