<?php
/**
 * Codice del modulo Quiz. Viene incluso solo se il modulo è acceso.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-quiz.php';

AEL_Quiz::instance()->boot();
