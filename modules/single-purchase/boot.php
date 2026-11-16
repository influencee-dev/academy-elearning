<?php
/**
 * Codice del modulo Acquisto singolo. Viene incluso solo se il modulo è acceso.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-single-purchase.php';
require_once __DIR__ . '/class-single-purchase-checkout.php';
require_once __DIR__ . '/class-single-purchase-orders.php';

// WooCommerce si carica prima o insieme ai plugin: ci si aggancia solo se c'è.
if ( class_exists( 'WooCommerce' ) || function_exists( 'WC' ) ) {
	AEL_Single_Purchase::instance()->boot();
	AEL_Single_Purchase_Checkout::instance()->boot();
	AEL_Single_Purchase_Orders::instance()->boot();
}
