<?php
/**
 * Codice del modulo Guide. Viene incluso solo se il modulo è acceso.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-guides.php';

AEL_Guides::instance()->boot();
