<?php
/**
 * Modulo "Accesso per assistenti AI (MCP)": rende l'academy gestibile da Claude tramite le Abilities di WordPress.
 *
 * Questo file dichiara soltanto il modulo: niente hook, niente traduzioni.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'             => 'Accesso per assistenti AI (MCP)',
	'description'      => 'Permette a Claude, collegato con il plugin MCP Adapter e una password applicazione, di leggere e modificare corsi, moduli, lezioni e schede docente. Non tocca pagamenti, rimborsi, chiavi o utenti. Richiede WordPress con le Abilities (6.9 o successivo).',
	'version'          => '1.0.0',
	'requires_modules' => array(),
	'requires_plugins' => array(),
	'boot'             => 'boot.php',
	'dev_only'         => false,
);
