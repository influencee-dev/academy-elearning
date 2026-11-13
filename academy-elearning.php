<?php
/**
 * Plugin Name:       Academy E-learning
 * Description:       Trasforma un sito WordPress in una piattaforma di corsi online: contenuti, catalogo, accessi e quote docenti, con funzioni attivabili a moduli.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Text Domain:       academy-elearning
 * Domain Path:       /languages
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

define( 'AEL_VERSION', '0.1.0' );
define( 'AEL_MIN_PHP', '8.1' );
define( 'AEL_MIN_WP', '6.4' );
define( 'AEL_FILE', __FILE__ );
define( 'AEL_PATH', plugin_dir_path( __FILE__ ) );
define( 'AEL_URL', plugin_dir_url( __FILE__ ) );
define( 'AEL_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Verifica i requisiti minimi. Se non sono soddisfatti il plugin non si carica
 * e l'amministratore vede un avviso chiaro.
 *
 * @return bool
 */
function ael_requirements_met() {
	global $wp_version;
	return version_compare( PHP_VERSION, AEL_MIN_PHP, '>=' )
		&& version_compare( $wp_version, AEL_MIN_WP, '>=' );
}

/**
 * Avviso mostrato quando i requisiti non sono soddisfatti.
 */
function ael_requirements_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: PHP minimo, 2: WordPress minimo */
				__( 'Academy E-learning richiede PHP %1$s o superiore e WordPress %2$s o superiore. Il plugin non è stato caricato.', 'academy-elearning' ),
				AEL_MIN_PHP,
				AEL_MIN_WP
			)
		)
	);
}

if ( ! ael_requirements_met() ) {
	add_action( 'admin_notices', 'ael_requirements_notice' );
	return;
}

/**
 * Autoloader: la classe AEL_Nome_Cosa si trova in includes/class-nome-cosa.php.
 */
spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'AEL_' ) ) {
			return;
		}
		$slug = strtolower( str_replace( '_', '-', substr( $class_name, 4 ) ) );
		$file = AEL_PATH . 'includes/class-' . $slug . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

AEL_Plugin::instance()->boot();
