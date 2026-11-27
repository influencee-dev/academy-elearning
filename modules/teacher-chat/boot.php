<?php
/**
 * Codice del modulo Chat con i formatori. Viene incluso solo se il modulo è acceso.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-teacher-chat.php';

AEL_Teacher_Chat::instance()->boot();
