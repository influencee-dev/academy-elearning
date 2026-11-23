<?php
/**
 * Modulo "Attribuzione": da dove arrivano gli acquisti.
 *
 * Questo file dichiara soltanto il modulo: niente hook, niente traduzioni.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'             => 'Attribuzione',
	'description'      => 'Al primo arrivo salva UTM e provenienza in un cookie di 90 giorni (primo e ultimo contatto) e li registra con ogni acquisto, insieme al codice promozionale e alla risposta a "Come ci hai conosciuto?". Esportazione CSV. Il cookie si salva solo con il consenso dell\'utente.',
	'version'          => '1.0.0',
	'requires_modules' => array(),
	'requires_plugins' => array(),
	'boot'             => 'boot.php',
	'dev_only'         => false,
);
