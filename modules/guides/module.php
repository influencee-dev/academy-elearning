<?php
/**
 * Modulo "Guide": materiali di approfondimento riservati agli abbonati.
 *
 * Questo file dichiara soltanto il modulo: niente hook, niente traduzioni.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'             => 'Guide',
	'description'      => 'Aggiunge una sezione "Guide": pagine di approfondimento con PDF scaricabili, divise per tema, visibili solo a chi ha l\'abbonamento.',
	'version'          => '1.0.0',
	'requires_modules' => array(),
	'requires_plugins' => array(),
	'boot'             => 'boot.php',
	'dev_only'         => false,
);
