<?php
/**
 * Modulo "Magic link": accesso con un link monouso via email.
 *
 * Questo file dichiara soltanto il modulo: niente hook, niente traduzioni.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'             => 'Magic link',
	'description'      => 'Aggiunge alla pagina di accesso la possibilità di entrare con un link monouso ricevuto via email. Non sostituisce mai la password e non vale per amministratori e titolare.',
	'version'          => '1.0.0',
	'requires_modules' => array(),
	'requires_plugins' => array(),
	'boot'             => 'boot.php',
	'dev_only'         => false,
);
