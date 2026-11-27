<?php
/**
 * Modulo "Quiz": verifiche a risposta multipla riservate agli abbonati.
 *
 * Questo file dichiara soltanto il modulo: niente hook, niente traduzioni.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'             => 'Quiz',
	'description'      => 'Aggiunge una sezione "Quiz": verifiche a risposta multipla, con punteggio e storico dei tentativi, visibili solo a chi ha l\'abbonamento. Un quiz si può collegare a un corso.',
	'version'          => '1.0.0',
	'requires_modules' => array(),
	'requires_plugins' => array(),
	'boot'             => 'boot.php',
	'dev_only'         => false,
);
