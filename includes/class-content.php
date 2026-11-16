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
		// Priorità 20: dopo che la capacità del titolare è stata calcolata (anche da wp-config).
		add_filter( 'user_has_cap', array( $this, 'grant_content_caps' ), 20 );
	}

	/**
	 * Ogni tipo di contenuto ha i suoi permessi (edit_ael_courses, publish_ael_courses...).
	 * Così WordPress sa distinguere "i miei" da "quelli degli altri" e un modulo
	 * (creator autori) può dare ad alcuni utenti solo una parte dei permessi.
	 * Chi ha la capacità del titolare (ael_manage_academy) li ha tutti: vedi grant_content_caps().
	 *
	 * @param string $singular Nome al singolare, per esempio "ael_course".
	 * @param string $plural   Nome al plurale, per esempio "ael_courses".
	 * @return array{capability_type: array<int, string>, map_meta_cap: bool}
	 */
	private function cap_type( string $singular, string $plural ): array {
		return array(
			'capability_type' => array( $singular, $plural ),
			'map_meta_cap'    => true,
		);
	}

	/**
	 * Tutti i permessi dei tipi di contenuto, per nome.
	 *
	 * @return string[]
	 */
	public static function content_caps(): array {
		static $caps = null;
		if ( null === $caps ) {
			$names = array();
			foreach ( array( self::COURSE, self::MODULE, self::LESSON, self::TEACHER, self::COLLECTION ) as $type ) {
				$object = get_post_type_object( $type );
				if ( ! $object ) {
					return array();
				}
				foreach ( (array) $object->cap as $name ) {
					$names[ (string) $name ] = true;
				}
			}
			$caps = array_keys( $names );
		}
		return $caps;
	}

	/**
	 * Chi ha la capacità del titolare ha anche tutti i permessi sui contenuti.
	 *
	 * @param array $allcaps Permessi dell'utente.
	 * @return array
	 */
	public function grant_content_caps( $allcaps ) {
		if ( ! empty( $allcaps[ self::CAP ] ) ) {
			foreach ( self::content_caps() as $name ) {
				$allcaps[ $name ] = true;
			}
		}
		return $allcaps;
	}

	/**
	 * Il contenuto è quello aperto nel modulo di modifica di questa richiesta?
	 * Serve a non salvare i campi su altri contenuti toccati nella stessa richiesta
	 * (per esempio quando pubblicando un corso si pubblicano anche le sue lezioni).
	 *
	 * @param int $post_id Contenuto.
	 */
	public static function is_form_post( int $post_id ): bool {
		// Solo il confronto dell'ID: la verifica del nonce la fa chi salva.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST['post_ID'] ) && absint( wp_unslash( $_POST['post_ID'] ) ) === $post_id;
	}

	/**
	 * Filtra una lista di contenuti lasciando solo quelli che l'utente può modificare.
	 *
	 * @param WP_Post[] $posts Contenuti.
	 * @return WP_Post[]
	 */
	public static function editable( array $posts ): array {
		return array_values(
			array_filter(
				$posts,
				static function ( $post ): bool {
					return $post instanceof WP_Post && current_user_can( 'edit_post', $post->ID );
				}
			)
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
			'delete_with_user' => false,
		);

		register_post_type(
			self::COURSE,
			array_merge(
				$common,
					$this->cap_type( 'ael_course', 'ael_courses' ),
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
					$this->cap_type( 'ael_module', 'ael_modules' ),
				array(
					'labels'   => $this->labels( __( 'Modulo', 'academy-elearning' ), __( 'Moduli', 'academy-elearning' ), false ),
					'public'   => false,
					'rewrite'  => false,
					'supports' => array( 'title' ),
				)
			)
		);

		// Lezioni: hanno un indirizzo, ma non compaiono in ricerche, menu o mappe del sito.
		// Testo e video li mostra solo AEL_Lesson_View, dopo il controllo di AEL_Access.
		register_post_type(
			self::LESSON,
			array_merge(
				$common,
					$this->cap_type( 'ael_lesson', 'ael_lessons' ),
				array(
					'labels'              => $this->labels( __( 'Lezione', 'academy-elearning' ), __( 'Lezioni', 'academy-elearning' ), true ),
					'public'              => false,
					'publicly_queryable'  => true,
					'exclude_from_search' => true,
					'show_in_nav_menus'   => false,
					'query_var'           => true,
					'has_archive'         => false,
					'rewrite'             => array( 'slug' => 'lezioni' ),
					'supports'            => array( 'title', 'editor' ),
				)
			)
		);

		$teacher_pages = (bool) AEL_Settings::get( 'teacher_pages', 1 );
		register_post_type(
			self::TEACHER,
			array_merge(
				$common,
					$this->cap_type( 'ael_teacher', 'ael_teachers' ),
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
					$this->cap_type( 'ael_collection', 'ael_collections' ),
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
					'assign_terms' => 'edit_ael_courses',
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
	 * ID degli allegati (materiali) di una lezione, nell'ordine scelto.
	 *
	 * @param int $lesson_id Lezione.
	 * @return int[]
	 */
	public static function lesson_materials( int $lesson_id ): array {
		$ids = get_post_meta( $lesson_id, '_ael_materials', true );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'intval', $ids ) ) ) : array();
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
