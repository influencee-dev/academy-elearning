<?php
/**
 * Modulo "Chat con i formatori": conversazioni private tra abbonati e formatori.
 *
 * Questo file dichiara soltanto il modulo: niente hook, niente traduzioni.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'             => 'Chat con i formatori',
	'description'      => 'Aggiunge una sezione "Chat": ogni abbonato scrive in privato al formatore che sceglie. I messaggi li vedono solo l\'abbonato e quel formatore, non gli altri formatori né il titolare dell\'academy. Per questo si può scegliere per quanto tempo conservarli.',
	'version'          => '1.0.0',
	'requires_modules' => array(),
	'requires_plugins' => array(),
	'boot'             => 'boot.php',
	'dev_only'         => false,
);
