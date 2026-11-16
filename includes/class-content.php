<?php
/**
 * Tipi di contenuto: corsi, moduli, lezioni, docenti, raccolte.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registra i tipi di contenuto e la tassonomia, e prepara ruoli e permessi.
 *
 * Struttura: docente > corso > modulo > lezione. Il modulo è figlio del corso
 * e la lezione è figlia del modulo (post_parent). L'ordine è menu_order.
 * Nessun contenuto è esposto nell'API REST di WordPress.
 */
final class AEL_Content {

	public const COURSE     = 'ael_course';
	public const MODULE     = 'ael_module';
	public const LESSON     = 'ael_lesson';
	public const TEACHER    = 'ael_teacher';
	public const COLLECTION = 'ael_collection';
	public const CATEGORY   = 'ael_course_category';

	/**
	 * Capacità che apre l'area di gestione dell'academy al titolare.
	 */
	public const CAP = 'ael_manage_academy';

	/**
	 * Option che chiede di rigenerare gli indirizzi (rewrite) al prossimo giro.
	 */
	public const FLUSH_OPTION = 'ael_flush_rewrite';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Content|null
	 */
	private static ?AEL_Content $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Content {
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
	 * Aggancia la registrazione dei contenuti.
	 */
	public function boot(): void {
		add_action( 'init', array( $this, 'register' ), 5 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite' ), 99 );
		add_action( 'ael_settings_saved', array( $this, 'request_flush' ) );
	}

	/**
	 * Tutti i permessi dei contenuti puntano a un'unica capacità, così basta
	 * dare quella al titolare. Solo la lettura resta aperta.
	 *
	 * @return array<string, string>
	 */
	private function caps(): array {
		$cap = self::CAP;
		return array(
			'edit_posts'           => $cap,
			'edit_others_posts'      => $cap,
			'delete_posts'           => $cap,
			'publish_posts'          => $cap,
			'read_private_posts'     => $cap,
			'delete_private_posts'   => $cap,
			'delete_published_posts' => $cap,
			'delete_others_posts'    => $cap,
			'edit_private_posts'     => $cap,
			'edit_published_posts'   => $cap,
			'create_posts'           => $cap,
		);
	}

	/**
	 * Etichette in italiano di un tipo di contenuto.
	 *
	 * @param string $singular Nome al singolare.
	 * @param string $plural   Nome al plurale.
	 * @param bool   $female   Genere grammaticale femminile.
	 * @return array<string, string>
	 */
	private function labels( string $singular, string $plural, bool $female ): array {
		$new = $female ? __( 'Nuova', 'academy-elearning' ) : __( 'Nuovo', 'academy-elearning' );
		return array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $plural,
			'all_items'          => $plural,
			'add_new'            => $female ? __( 'Aggiungi nuova', 'academy-elearning' ) : __( 'Aggiungi nuovo', 'academy-elearning' ),
			/* translators: %s: nome del contenuto */
			'add_new_item'       => sprintf( __( 'Aggiungi: %s', 'academy-elearning' ), $singular ),
			/* translators: %s: nome del contenuto */
			'edit_item'          => sprintf( __( 'Modifica: %s', 'academy-elearning' ), $singular ),
			'new_item'           => $new . ' ' . strtolower( $singular ),
			'view_item'          => $singular,
			/* translators: %s: nome del contenuto al plurale */
			'search_items'       => sprintf( __( 'Cerca: %s', 'academy-elearning' ), strtolower( $plural ) ),
			'not_found'          => __( 'Nessun elemento trovato.', 'academy-elearning' ),
			'not_found_in_trash' => __( 'Nessun elemento nel cestino.', 'academy-elearning' ),
		);
	}

	/**
	 * Registra tipi di contenuto e tassonomia.
	 */
	public function register(): void {
		$common = array(
			'show_ui'         => true,
			'show_in_rest'    => false,
			'show_in_menu'    => 'ael-academy',
			'map_meta_cap'    => true,
			'capabilities'    => $this->caps(),
			'delete_with_user' => false,
		);

		register_post_type(
			self::COURSE,
			array_merge(
				$common,
				array(
					'labels'      => $this->labels( __( 'Corso', 'academy-elearning' ), __( 'Corsi', 'academy-elearning' ), false ),
					'public'      => true,
					'has_archive' => false,
					'rewrite'     => array( 'slug' => 'corsi' ),
					'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				)
			)
		);

		// Moduli: si gestiscono dall'amministrazione, non hanno pagina pubblica.
		register_post_type(
			self::MODULE,
			array_merge(
				$common,
				array(
					'labels'   => $this->labels( __( 'Modulo', 'academy-elearning' ), __( 'Moduli', 'academy-elearning' ), false ),
					'public'   => false,
					'rewrite'  => false,
					'supports' => array( 'title' ),
				)
			)
		);

		// Lezioni: nessuna pagina pubblica finché non c'è il controllo degli accessi
		// (passi 4 e 6). Così testo e video non possono finire in vista a chi non paga.
		register_post_type(
			self::LESSON,
			array_merge(
				$common,
				array(
					'labels'   => $this->labels( __( 'Lezione', 'academy-elearning' ), __( 'Lezioni', 'academy-elearning' ), true ),
					'public'   => false,
					'rewrite'  => false,
					'supports' => array( 'title', 'editor' ),
				)
			)
		);

		$teacher_pages = (bool) AEL_Settings::get( 'teacher_pages', 1 );
		register_post_type(
			self::TEACHER,
			array_merge(
				$common,
				array(
					'labels'             => $this->labels( __( 'Docente', 'academy-elearning' ), __( 'Docenti', 'academy-elearning' ), false ),
					'public'             => $teacher_pages,
					'publicly_queryable' => $teacher_pages,
					'rewrite'            => $teacher_pages ? array( 'slug' => 'docenti' ) : false,
					'supports'           => array( 'title', 'editor', 'thumbnail' ),
				)
			)
		);

		register_post_type(
			self::COLLECTION,
			array_merge(
				$common,
				array(
					'labels'   => $this->labels( __( 'Raccolta', 'academy-elearning' ), __( 'Raccolte', 'academy-elearning' ), true ),
					'public'   => true,
					'rewrite'  => array( 'slug' => 'raccolte' ),
					'supports' => array( 'title', 'editor', 'thumbnail' ),
				)
			)
		);

		register_taxonomy(
			self::CATEGORY,
			array( self::COURSE ),
			array(
				'labels'       => array(
					'name'          => __( 'Categorie dei corsi', 'academy-elearning' ),
					'singular_name' => __( 'Categoria', 'academy-elearning' ),
					'menu_name'     => __( 'Categorie', 'academy-elearning' ),
				),
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => false,
				'show_ui'      => true,
				'rewrite'      => array( 'slug' => 'categoria-corso' ),
				'capabilities' => array(
					'manage_terms' => self::CAP,
					'edit_terms'   => self::CAP,
					'delete_terms' => self::CAP,
					'assign_terms' => self::CAP,
				),
			)
		);
	}

	/**
	 * Chiede di rigenerare gli indirizzi al prossimo caricamento.
	 */
	public function request_flush(): void {
		update_option( self::FLUSH_OPTION, 1 );
	}

	/**
	 * Rigenera gli indirizzi una volta sola, dopo che i tipi sono registrati.
	 */
	public function maybe_flush_rewrite(): void {
		if ( get_option( self::FLUSH_OPTION ) ) {
			flush_rewrite_rules( false );
			delete_option( self::FLUSH_OPTION );
		}
	}

	/**
	 * Migrazione: ruolo Docente, permesso del titolare, rigenerazione indirizzi.
	 * Aggiunge dati, non ne toglie. Il nome del ruolo è nella lingua di base
	 * (italiano) perché la migrazione può girare prima del caricamento delle traduzioni.
	 */
	public static function setup_roles(): bool {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP );
		}
		if ( ! get_role( self::TEACHER ) ) {
			add_role( self::TEACHER, 'Docente', array( 'read' => true ) );
		}
		update_option( self::FLUSH_OPTION, 1 );
		return true;
	}

	/**
	 * Estrae l'ID del video da un indirizzo o da un ID, per la sorgente indicata.
	 * Restituisce una stringa vuota se non lo riconosce.
	 *
	 * Per Vimeo i video non in elenco hanno anche un codice: si salva come "ID/codice".
	 *
	 * @param string $source Sorgente: youtube o vimeo.
	 * @param string $input  Indirizzo o ID incollato dall'amministratore.
	 */
	public static function normalize_video_ref( string $source, string $input ): string {
		$input = trim( $input );
		if ( '' === $input ) {
			return '';
		}

		if ( 'youtube' === $source ) {
			if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $input ) ) {
				return $input;
			}
			if ( preg_match( '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})~', $input, $m ) ) {
				return $m[1];
			}
			return '';
		}

		if ( 'vimeo' === $source ) {
			if ( preg_match( '~^\d{6,12}(?:/[a-z0-9]+)?$~i', $input ) ) {
				return $input;
			}
			if ( preg_match( '~vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d{6,12})(?:/([a-z0-9]+))?~i', $input, $m ) ) {
				$id = $m[1];
				if ( ! empty( $m[2] ) ) {
					return $id . '/' . $m[2];
				}
				if ( preg_match( '/[?&]h=([a-z0-9]+)/i', $input, $h ) ) {
					return $id . '/' . $h[1];
				}
				return $id;
			}
			return '';
		}

		return '';
	}

	/**
	 * Ordina una raccolta: riceve ID corso => posizione e restituisce gli ID
	 * dei corsi con posizione maggiore di zero, in ordine.
	 *
	 * @param array<int|string, int|string> $positions ID corso => posizione.
	 * @return int[]
	 */
	public static function order_collection( array $positions ): array {
		$clean = array();
		foreach ( $positions as $course_id => $position ) {
			$course_id = (int) $course_id;
			$position  = (int) $position;
			if ( $course_id > 0 && $position > 0 ) {
				$clean[ $course_id ] = $position;
			}
		}
		asort( $clean, SORT_NUMERIC );
		return array_map( 'intval', array_keys( $clean ) );
	}
}
