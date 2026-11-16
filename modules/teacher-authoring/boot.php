<?php
/**
 * Codice del modulo Docenti autori. Viene incluso solo se il modulo è acceso.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-teacher-authoring.php';

AEL_Teacher_Authoring::instance()->boot();
