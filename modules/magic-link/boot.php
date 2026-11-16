<?php
/**
 * Codice del modulo Magic link. Viene incluso solo se il modulo è acceso.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-magic-link.php';

AEL_Magic_Link::instance()->boot();
