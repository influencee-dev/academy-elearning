<?php
/**
 * Modulo "Creator autori": i docenti accedono con il proprio utente e creano
 * i propri corsi, senza vedere i contenuti degli altri.
 *
 * Questo file dichiara soltanto il modulo: niente hook, niente traduzioni.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

return array(
	'name'             => 'Creator autori',
	'description'      => 'I docenti entrano con il proprio utente e creano e modificano solo i propri corsi, moduli e lezioni. Non vedono i contenuti degli altri, gli studenti né le vendite. La pubblicazione resta al titolare.',
	'version'          => '0.1.0',
	'requires_modules' => array(),
	'requires_plugins' => array(),
	'boot'             => 'boot.php',
	'dev_only'         => false,
);
