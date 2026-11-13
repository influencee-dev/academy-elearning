<?php
/**
 * Avvio del plugin.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Punto di ingresso della base. In questo passo carica solo la lingua e
 * annuncia con gli hook che la base è pronta; registro moduli, installer,
 * contenuti e il resto arrivano nei passi successivi.
 */
final class AEL_Plugin {

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Plugin|null
	 */
	private static ?AEL_Plugin $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Costruttore privato: si usa instance().
	 */
	private function __construct() {}

	/**
	 * Aggancia l'avvio ai punti giusti del ciclo di WordPress.
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'plugins_loaded', array( $this, 'loaded' ), 20 );
	}

	/**
	 * Carica le traduzioni (lingua di base: italiano).
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'academy-elearning', false, dirname( AEL_BASENAME ) . '/languages' );
	}

	/**
	 * La base è pronta. I moduli si agganciano da qui in poi.
	 */
	public function loaded(): void {
		/**
		 * Scatta quando la base del plugin è caricata.
		 *
		 * @param AEL_Plugin $plugin Istanza della base.
		 */
		do_action( 'ael_loaded', $this );
	}
}
