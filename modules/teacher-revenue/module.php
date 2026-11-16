<?php
/**
 * Modulo "Ricavi docente": registro vendite e quote dei docenti.
 *
 * Questo file dichiara soltanto il modulo: niente hook, niente traduzioni.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'             => 'Ricavi docente',
	'description'      => 'Registra ogni vendita di corso e calcola la quota del docente sull\'importo netto (senza IVA e commissioni), con percentuale per docente e per corso. Riepilogo per periodo, esportazione CSV, storno automatico sui rimborsi. Nessun pagamento automatico: la liquidazione avviene fuori.',
	'version'          => '0.1.0',
	'requires_modules' => array(),
	'requires_plugins' => array(),
	'boot'             => 'boot.php',
	'dev_only'         => false,
);
