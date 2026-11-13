<?php
/**
 * Modulo di prova: serve solo a verificare registro e interruttori.
 * Compare solo con WP_DEBUG acceso e va escluso dallo zip di rilascio.
 *
 * Questo file dichiara soltanto il modulo: niente hook, niente traduzioni.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'             => 'Modulo di prova',
	'description'      => 'Mostra un avviso nell\'amministrazione quando è acceso. Se lo spegni, l\'avviso sparisce.',
	'version'          => '0.1.0',
	'requires_modules' => array(),
	'requires_plugins' => array(),
	'boot'             => 'boot.php',
	'dev_only'         => true,
);
