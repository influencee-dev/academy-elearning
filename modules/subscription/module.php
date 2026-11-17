<?php
/**
 * Modulo "Abbonamento": accesso a tutto il catalogo con un abbonamento mensile su Stripe.
 *
 * Questo file dichiara soltanto il modulo: niente hook, niente traduzioni.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'             => 'Abbonamento',
	'description'      => 'Abbonamento mensile su Stripe (Checkout, portale clienti e webhook, senza estensioni WooCommerce). Dà accesso a tutto il catalogo, esclusi i corsi segnati come "fuori abbonamento". Alla disdetta l\'accesso resta fino alla fine del periodo pagato. Le chiavi si inseriscono nella pagina Abbonamento, riservata all\'agenzia.',
	'version'          => '0.1.0',
	'requires_modules' => array(),
	'requires_plugins' => array(),
	'boot'             => 'boot.php',
	'dev_only'         => false,
);
