<?php
/**
 * Codice del modulo Accesso per assistenti AI (MCP). Viene incluso solo se il modulo è acceso.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-mcp-abilities.php';

AEL_Mcp_Abilities::instance()->boot();
