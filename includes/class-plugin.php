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
		AEL_Settings::instance()->boot();
		AEL_Content::instance()->boot();
		AEL_Catalog::instance()->boot();
		AEL_Lesson_View::instance()->boot();
		AEL_Account::instance()->boot();
		AEL_Avatar::instance()->boot();
		AEL_Registration::instance()->boot();
		AEL_Progress::instance()->boot();
		AEL_Rest::instance()->boot();
		if ( is_admin() ) {
			AEL_Admin::instance()->boot();
		}
	}

	/**
	 * All'attivazione, chi attiva il plugin riceve la capacità dell'agenzia.
	 * Il cliente non la ha mai, a meno che non sia lui ad attivare il plugin.
	 */
	public static function activate(): void {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			AEL_Settings::grant_cap( $user_id );
		}
		AEL_Installer::run_pending();
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

		// Solo i moduli accesi caricano i loro file.
		AEL_Modules::instance()->load_active();

		// Migrazioni in sospeso (anche quelle dei moduli appena caricati).
		// Dopo un aggiornamento via zip l'attivazione non scatta: parte da qui.
		AEL_Installer::run_pending();

		/**
		 * Scatta quando tutti i moduli accesi sono stati caricati.
		 */
		do_action( 'ael_modules_loaded' );
	}
}
