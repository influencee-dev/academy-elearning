<?php
/**
 * Codice del modulo Abbonamento. Viene incluso solo se il modulo è acceso.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-subscription-stripe.php';
require_once __DIR__ . '/class-subscription-webhook.php';
require_once __DIR__ . '/class-subscription.php';
require_once __DIR__ . '/class-subscription-admin.php';

AEL_Subscription::instance()->boot();
AEL_Subscription_Webhook::instance()->boot();
if ( is_admin() ) {
	AEL_Subscription_Admin::instance()->boot();
}
