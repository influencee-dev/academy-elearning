<?php
/**
 * Modulo "Acquisto singolo": vendita dei corsi con WooCommerce.
 *
 * Questo file dichiara soltanto il modulo: niente hook, niente traduzioni.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'             => 'Acquisto singolo',
	'description'      => 'Ogni corso in vendita è collegato a un prodotto WooCommerce virtuale. Checkout con account obbligatorio e consenso all\'accesso immediato ai contenuti digitali (rinuncia al recesso), salvato con l\'ordine. Concede l\'accesso a ordine completato e lo toglie in caso di rimborso. Richiede WooCommerce.',
	'version'          => '1.0.0',
	'requires_modules' => array(),
	'requires_plugins' => array( 'woocommerce/woocommerce.php' ),
	'boot'             => 'boot.php',
	'dev_only'         => false,
);
