<?php
/**
 * Registro dei moduli e dei loro interruttori.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Scopre i moduli in modules/<nome>/module.php, tiene l'elenco di quelli accesi
 * e carica solo quelli. Un modulo spento non carica nulla.
 *
 * Il file module.php di ogni modulo restituisce soltanto un array con la
 * dichiarazione (nome, descrizione, versione, dipendenze). Non registra hook e
 * non usa funzioni di traduzione: serve solo a descrivere il modulo. Il codice
 * vero sta nel file indicato da "boot", che viene incluso solo a modulo acceso.
 */
final class AEL_Modules {

	/**
	 * Nome dell'option con gli slug dei moduli accesi.
	 */
	public const OPTION = 'ael_active_modules';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Modules|null
	 */
	private static ?AEL_Modules $instance = null;

	/**
	 * Moduli trovati su disco (cache per richiesta).
	 *
	 * @var array|null
	 */
	private ?array $available = null;

	/**
	 * Slug dei moduli caricati in questa richiesta.
	 *
	 * @var string[]
	 */
	private array $loaded = array();

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Modules {
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
	 * Moduli presenti su disco, indicizzati per slug.
	 *
	 * I moduli con "dev_only" compaiono solo con WP_DEBUG acceso, così quelli
	 * di prova non arrivano mai in produzione.
	 *
	 * @return array<string, array>
	 */
	public function available(): array {
		if ( null !== $this->available ) {
			return $this->available;
		}

		$defaults = array(
			'name'             => '',
			'description'      => '',
			'version'          => '0.0.0',
			'requires_modules' => array(),
			'requires_plugins' => array(),
			'boot'             => 'boot.php',
			'dev_only'         => false,
		);

		$modules = array();
		$files   = glob( AEL_PATH . 'modules/*/module.php' );
		$files   = is_array( $files ) ? $files : array();

		foreach ( $files as $file ) {
			$slug = basename( dirname( $file ) );
			if ( ! preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
				continue;
			}
			$header = include $file;
			if ( ! is_array( $header ) ) {
				continue;
			}
			$header = array_merge( $defaults, $header );
			if ( $header['dev_only'] && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
				continue;
			}
			if ( '' === $header['name'] ) {
				$header['name'] = $slug;
			}
			$header['slug'] = $slug;
			$header['path'] = dirname( $file );
			$modules[ $slug ] = $header;
		}

		ksort( $modules );
		$this->available = $modules;
		return $this->available;
	}

	/**
	 * Slug dei moduli accesi che esistono ancora su disco.
	 *
	 * @return string[]
	 */
	public function active_slugs(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array_values( array_intersect( $stored, array_keys( $this->available() ) ) );
	}

	/**
	 * Un modulo è acceso (indipendentemente dal fatto che sia già stato caricato)?
	 *
	 * @param string $slug Slug del modulo.
	 */
	public function is_active( string $slug ): bool {
		return in_array( $slug, $this->active_slugs(), true );
	}

	/**
	 * Un modulo è stato caricato in questa richiesta?
	 *
	 * @param string $slug Slug del modulo.
	 */
	public function is_loaded( string $slug ): bool {
		return in_array( $slug, $this->loaded, true );
	}

	/**
	 * Dipendenze che mancano a un modulo, dato un insieme di moduli accesi.
	 *
	 * @param string   $slug      Slug del modulo.
	 * @param string[] $selection Moduli considerati accesi.
	 * @return array{modules: string[], plugins: string[]}
	 */
	public function missing( string $slug, array $selection ): array {
		$available = $this->available();
		if ( ! isset( $available[ $slug ] ) ) {
			return array(
				'modules' => array(),
				'plugins' => array(),
			);
		}
		$module         = $available[ $slug ];
		$active_plugins = (array) get_option( 'active_plugins', array() );

		return array(
			'modules' => array_values( array_diff( (array) $module['requires_modules'], $selection ) ),
			'plugins' => array_values( array_diff( (array) $module['requires_plugins'], $active_plugins ) ),
		);
	}

	/**
	 * Toglie dall'insieme i moduli a cui manca una dipendenza, finché l'insieme
	 * non è coerente.
	 *
	 * @param string[] $selection Moduli che si vorrebbero accesi.
	 * @return array{ok: string[], dropped: array<string, array>}
	 */
	public function satisfy( array $selection ): array {
		$ok      = array_values( $selection );
		$dropped = array();

		do {
			$changed = false;
			foreach ( $ok as $index => $slug ) {
				$missing = $this->missing( $slug, $ok );
				if ( ! empty( $missing['modules'] ) || ! empty( $missing['plugins'] ) ) {
					$dropped[ $slug ] = $missing;
					unset( $ok[ $index ] );
					$changed = true;
				}
			}
		} while ( $changed );

		return array(
			'ok'      => array_values( $ok ),
			'dropped' => $dropped,
		);
	}

	/**
	 * Carica i moduli accesi e con le dipendenze a posto.
	 */
	public function load_active(): void {
		$result = $this->satisfy( $this->active_slugs() );
		$all    = $this->available();

		foreach ( $result['ok'] as $slug ) {
			$module = $all[ $slug ];
			if ( false !== strpos( $module['boot'], '..' ) ) {
				continue;
			}
			$file = $module['path'] . '/' . $module['boot'];
			if ( ! is_readable( $file ) ) {
				continue;
			}
			require_once $file;
			$this->loaded[] = $slug;
		}
	}

	/**
	 * Imposta quali moduli sono accesi.
	 *
	 * I moduli non presenti su disco (per esempio quelli di prova quando
	 * WP_DEBUG è spento) restano nell'elenco salvato: i dati non si perdono.
	 *
	 * @param string[] $requested Slug dei moduli che si vogliono accesi.
	 * @return array<string, array> Moduli non accesi per dipendenze mancanti.
	 */
	public function set_active( array $requested ): array {
		$available = $this->available();
		$old       = $this->active_slugs();

		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$kept   = array_values( array_diff( $stored, array_keys( $available ) ) );

		$selection = array_values( array_intersect( $requested, array_keys( $available ) ) );
		$result    = $this->satisfy( $selection );

		update_option( self::OPTION, array_values( array_unique( array_merge( $kept, $result['ok'] ) ) ) );

		foreach ( array_diff( $old, $result['ok'] ) as $slug ) {
			/**
			 * Scatta quando un modulo viene spento. I suoi dati restano.
			 *
			 * @param string $slug Slug del modulo.
			 */
			do_action( 'ael_module_deactivated', $slug );
		}
		foreach ( array_diff( $result['ok'], $old ) as $slug ) {
			/**
			 * Scatta quando un modulo viene acceso.
			 *
			 * @param string $slug Slug del modulo.
			 */
			do_action( 'ael_module_activated', $slug );
		}

		return $result['dropped'];
	}
}
