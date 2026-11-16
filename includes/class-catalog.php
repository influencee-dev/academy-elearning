<?php
/**
 * Catalogo pubblico, scheda del corso e pulsante di azione.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le pagine pubbliche dei corsi.
 *
 * La grafica vive nei template (sovrascrivibili dal tema). Qui c'è la logica:
 * il programma del corso, il pulsante che cambia in base a chi guarda, il
 * catalogo con i filtri. Le pagine del corso, del docente e della raccolta si
 * inseriscono nel contenuto dell'articolo, così funzionano con qualsiasi tema.
 */
final class AEL_Catalog {

	/**
	 * Option con l'ID della pagina che contiene il catalogo.
	 */
	public const PAGE_OPTION = 'ael_page_catalog';

	/**
	 * Programmi già calcolati in questa richiesta.
	 *
	 * @var array<string, array>
	 */
	private static array $program_cache = array();

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Catalog|null
	 */
	private static ?AEL_Catalog $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Catalog {
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
	 * Aggancia shortcode, contenuto delle pagine e stili.
	 */
	public function boot(): void {
		add_shortcode( 'ael_catalog', array( $this, 'shortcode' ) );
		// Priorità 20: dopo blocchi, paragrafi e shortcode, così $content è già pronto.
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'ael_program_changed', array( __CLASS__, 'flush_cache' ) );
		// Se le concessioni cambiano durante la richiesta, l'accesso nel programma va ricalcolato.
		add_action( 'ael_grant_saved', array( __CLASS__, 'flush_cache' ) );
		add_action( 'ael_grant_revoked', array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Registra lo stile di base e lo carica solo dove serve.
	 */
	public function register_assets(): void {
		wp_register_style( 'ael-front', AEL_URL . 'assets/front.css', array(), AEL_VERSION );
		if ( is_singular( array( AEL_Content::COURSE, AEL_Content::LESSON, AEL_Content::TEACHER, AEL_Content::COLLECTION ) ) ) {
			wp_enqueue_style( 'ael-front' );
		}
	}

	/**
	 * Svuota i programmi calcolati.
	 */
	public static function flush_cache(): void {
		self::$program_cache = array();
	}

	/* ------------------------------------------------------------------ */
	/* Indirizzi e pagine                                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * Indirizzo del catalogo.
	 */
	public static function catalog_url(): string {
		$page_id = (int) get_option( self::PAGE_OPTION );
		$url     = ( $page_id && 'publish' === get_post_status( $page_id ) ) ? (string) get_permalink( $page_id ) : home_url( '/' );

		/**
		 * Permette di indicare un altro indirizzo per il catalogo.
		 *
		 * @param string $url Indirizzo.
		 */
		return (string) apply_filters( 'ael_catalog_url', $url );
	}

	/**
	 * Migrazione: crea la pagina "Catalogo". Se il sistema non è ancora pronto
	 * (init non ancora passato) rimanda la creazione a init.
	 */
	public static function setup_pages(): bool {
		// Gli indirizzi delle lezioni sono cambiati: vanno rigenerati.
		update_option( AEL_Content::FLUSH_OPTION, 1 );
		if ( did_action( 'init' ) ) {
			return self::create_pages();
		}
		add_action( 'init', array( __CLASS__, 'create_pages' ), 98 );
		return true;
	}

	/**
	 * Crea la pagina Catalogo se manca. Non ne crea una seconda se il cliente
	 * l'ha rinominata, e non la ricrea se è nel cestino.
	 */
	public static function create_pages(): bool {
		$page_id = (int) get_option( self::PAGE_OPTION );
		if ( $page_id && 'page' === get_post_type( $page_id ) ) {
			return true;
		}
		$new_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Catalogo',
				'post_name'    => 'catalogo',
				'post_content' => '[ael_catalog]',
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			return false;
		}
		update_option( self::PAGE_OPTION, (int) $new_id );
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Dati del corso                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Livelli ammessi.
	 *
	 * @return array<string, string>
	 */
	public static function levels(): array {
		return array(
			'principiante' => __( 'Principiante', 'academy-elearning' ),
			'intermedio'   => __( 'Intermedio', 'academy-elearning' ),
			'avanzato'     => __( 'Avanzato', 'academy-elearning' ),
		);
	}

	/**
	 * Durata leggibile: "45 min", "2 h", "1 h 30 min". Vuota se zero.
	 *
	 * @param int $minutes Minuti.
	 */
	public static function format_minutes( int $minutes ): string {
		if ( $minutes <= 0 ) {
			return '';
		}
		if ( $minutes < 60 ) {
			/* translators: %d: minuti */
			return sprintf( __( '%d min', 'academy-elearning' ), $minutes );
		}
		$hours = intdiv( $minutes, 60 );
		$rest  = $minutes % 60;
		if ( 0 === $rest ) {
			/* translators: %d: ore */
			return sprintf( __( '%d h', 'academy-elearning' ), $hours );
		}
		/* translators: 1: ore, 2: minuti */
		return sprintf( __( '%1$d h %2$d min', 'academy-elearning' ), $hours, $rest );
	}

	/**
	 * Righe di un testo (una per riga), senza righe vuote.
	 *
	 * @param string $text Testo.
	 * @return string[]
	 */
	public static function lines( string $text ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		return array_values( array_filter( array_map( 'trim', is_array( $lines ) ? $lines : array() ), 'strlen' ) );
	}

	/**
	 * Docente pubblicato di un corso.
	 *
	 * @param int $course_id Corso.
	 */
	public static function teacher_of( int $course_id ): ?WP_Post {
		$teacher_id = (int) get_post_meta( $course_id, '_ael_teacher_id', true );
		if ( ! $teacher_id || AEL_Content::TEACHER !== get_post_type( $teacher_id ) || 'publish' !== get_post_status( $teacher_id ) ) {
			return null;
		}
		return get_post( $teacher_id );
	}

	/**
	 * Indirizzo della pagina del docente, se le pagine docente sono accese.
	 *
	 * @param WP_Post|null $teacher Docente.
	 */
	public static function teacher_url( ?WP_Post $teacher ): string {
		if ( ! $teacher || ! AEL_Settings::get( 'teacher_pages', 1 ) ) {
			return '';
		}
		return (string) get_permalink( $teacher );
	}

	/**
	 * Programma pubblicato del corso: moduli e lezioni in ordine.
	 *
	 * Mostra solo contenuti pubblicati, a chiunque. Per ogni lezione indica se
	 * l'utente può vederla (decide AEL_Access) e l'indirizzo.
	 *
	 * @param int  $course_id   Corso.
	 * @param int  $user_id     Utente (0 = visitatore).
	 * @param bool $with_access Calcola l'accesso lezione per lezione (più lento).
	 * @return array{modules: array<int, array<string, mixed>>, lesson_count: int, minutes: int}
	 */
	public static function program( int $course_id, int $user_id = 0, bool $with_access = true ): array {
		$key = $course_id . ':' . $user_id . ':' . ( $with_access ? 1 : 0 );
		if ( isset( self::$program_cache[ $key ] ) ) {
			return self::$program_cache[ $key ];
		}

		$order   = array(
			'menu_order' => 'ASC',
			'title'      => 'ASC',
		);
		$modules = get_posts(
			array(
				'post_type'   => AEL_Content::MODULE,
				'post_parent' => $course_id,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => $order,
			)
		);

		$result  = array(
			'modules'      => array(),
			'lesson_count' => 0,
			'minutes'      => 0,
		);
		$by_module = array();
		if ( ! empty( $modules ) ) {
			$lessons = get_posts(
				array(
					'post_type'       => AEL_Content::LESSON,
					'post_parent__in' => wp_list_pluck( $modules, 'ID' ),
					'post_status'     => 'publish',
					'numberposts'     => -1,
					'orderby'         => $order,
				)
			);
			foreach ( $lessons as $lesson ) {
				$minutes                          = (int) get_post_meta( $lesson->ID, '_ael_duration_min', true );
				$by_module[ $lesson->post_parent ][] = array(
					'id'         => (int) $lesson->ID,
					'title'      => get_the_title( $lesson ),
					'minutes'    => $minutes,
					'is_preview' => (bool) get_post_meta( $lesson->ID, '_ael_is_preview', true ) && (bool) AEL_Settings::get( 'free_previews', 1 ),
					'can'        => $with_access && AEL_Access::user_can_access_lesson( $user_id, (int) $lesson->ID ),
					'url'        => (string) get_permalink( $lesson ),
				);
				++$result['lesson_count'];
				$result['minutes'] += $minutes;
			}
		}

		foreach ( $modules as $module ) {
			$result['modules'][] = array(
				'id'      => (int) $module->ID,
				'title'   => get_the_title( $module ),
				'lessons' => $by_module[ $module->ID ] ?? array(),
			);
		}

		self::$program_cache[ $key ] = $result;
		return $result;
	}

	/**
	 * Tutte le lezioni del programma in un elenco piatto, nell'ordine di visione.
	 *
	 * @param array $program Risultato di program().
	 * @return array<int, array<string, mixed>>
	 */
	public static function flat_lessons( array $program ): array {
		$flat = array();
		foreach ( $program['modules'] as $module ) {
			foreach ( $module['lessons'] as $lesson ) {
				$flat[] = $lesson;
			}
		}
		return $flat;
	}

	/**
	 * Da quale lezione riparte l'utente. Di base la prima che può vedere; il
	 * tracciamento dei progressi (passo successivo) la sposta all'ultima vista.
	 *
	 * @param int $course_id Corso.
	 * @param int $user_id   Utente.
	 * @return array{lesson_id: int, started: bool}
	 */
	public static function resume( int $course_id, int $user_id ): array {
		$resume = array(
			'lesson_id' => 0,
			'started'   => false,
		);
		foreach ( self::flat_lessons( self::program( $course_id, $user_id ) ) as $lesson ) {
			if ( $lesson['can'] ) {
				$resume['lesson_id'] = (int) $lesson['id'];
				break;
			}
		}

		/**
		 * Permette di cambiare la lezione da cui si riparte.
		 *
		 * @param array $resume    lesson_id e started.
		 * @param int   $course_id Corso.
		 * @param int   $user_id   Utente.
		 */
		$filtered = apply_filters( 'ael_course_resume', $resume, $course_id, $user_id );
		return is_array( $filtered ) ? array_merge( $resume, $filtered ) : $resume;
	}

	/**
	 * I pulsanti della scheda corso per questo utente.
	 *
	 * La base aggiunge solo "Inizia / Continua il corso" a chi ha accesso. I
	 * moduli (acquisto singolo, abbonamento) aggiungono i loro pulsanti con il
	 * filtro ael_course_actions.
	 *
	 * @param int $course_id Corso.
	 * @param int $user_id   Utente (0 = visitatore).
	 * @return array<int, array{id: string, label: string, url: string, type: string, priority: int}>
	 */
	public static function actions( int $course_id, int $user_id ): array {
		$has     = AEL_Access::user_can_access_course( $user_id, $course_id );
		$actions = array();

		if ( $has ) {
			$resume = self::resume( $course_id, $user_id );
			if ( $resume['lesson_id'] ) {
				$actions[] = array(
					'id'       => 'start',
					'label'    => $resume['started'] ? __( 'Continua il corso', 'academy-elearning' ) : __( 'Inizia il corso', 'academy-elearning' ),
					'url'      => (string) get_permalink( $resume['lesson_id'] ),
					'type'     => 'primary',
					'priority' => 10,
				);
			}
		}

		/**
		 * Permette ai moduli di aggiungere o cambiare i pulsanti della scheda corso.
		 *
		 * @param array $actions   Pulsanti: id, label, url, type (primary|secondary), priority.
		 * @param int   $course_id Corso.
		 * @param int   $user_id   Utente (0 = visitatore).
		 * @param bool  $has       L'utente ha già accesso al corso.
		 */
		$filtered = apply_filters( 'ael_course_actions', $actions, $course_id, $user_id, $has );
		$clean    = array();
		foreach ( is_array( $filtered ) ? $filtered : array() as $action ) {
			if ( ! is_array( $action ) || empty( $action['label'] ) || empty( $action['url'] ) ) {
				continue;
			}
			$clean[] = array(
				'id'       => isset( $action['id'] ) ? sanitize_key( (string) $action['id'] ) : '',
				'label'    => (string) $action['label'],
				'url'      => (string) $action['url'],
				'type'     => ( isset( $action['type'] ) && 'secondary' === $action['type'] ) ? 'secondary' : 'primary',
				'priority' => isset( $action['priority'] ) ? (int) $action['priority'] : 50,
			);
		}
		usort(
			$clean,
			static function ( array $a, array $b ): int {
				return $a['priority'] <=> $b['priority'];
			}
		);
		return $clean;
	}

	/* ------------------------------------------------------------------ */
	/* Pagine di corso, docente, raccolta                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * Inserisce nel contenuto la scheda del corso, la pagina del docente o la
	 * raccolta. Solo nella pagina principale, mai in elenchi o feed.
	 *
	 * @param string $content Contenuto già elaborato.
	 */
	public function filter_content( $content ) {
		$content = (string) $content;
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post instanceof WP_Post ) {
			return $content;
		}

		switch ( $post->post_type ) {
			case AEL_Content::COURSE:
				return $this->render_course( $post, $content );
			case AEL_Content::TEACHER:
				return $this->render_teacher( $post, $content );
			case AEL_Content::COLLECTION:
				return $this->render_collection( $post, $content );
		}
		return $content;
	}

	/**
	 * Scheda del corso.
	 *
	 * @param WP_Post $course  Corso.
	 * @param string  $content Descrizione già elaborata.
	 */
	private function render_course( WP_Post $course, string $content ): string {
		wp_enqueue_style( 'ael-front' );

		$user_id = get_current_user_id();
		$teacher = self::teacher_of( $course->ID );
		$levels  = self::levels();
		$level   = (string) get_post_meta( $course->ID, '_ael_level', true );
		$program = self::program( $course->ID, $user_id );

		$preview_html = '';
		$preview_url  = (string) get_post_meta( $course->ID, '_ael_preview_video', true );
		if ( '' !== $preview_url ) {
			list( $source, $ref ) = AEL_Video::from_url( $preview_url );
			// Il video di anteprima del corso è pubblico per scelta dell'amministratore.
			$preview_html = AEL_Video::embed( $source, $ref, get_the_title( $course ) );
		}

		$terms = get_the_terms( $course->ID, AEL_Content::CATEGORY );
		$cats  = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$cats[] = array(
					'name' => $term->name,
					'url'  => add_query_arg( 'ael_cat', $term->slug, self::catalog_url() ),
				);
			}
		}

		return AEL_Templates::render(
			'course',
			array(
				'course'       => $course,
				'content'      => $content,
				'subtitle'     => (string) get_post_meta( $course->ID, '_ael_subtitle', true ),
				'teacher'      => $teacher,
				'teacher_url'  => self::teacher_url( $teacher ),
				'level_label'  => $levels[ $level ] ?? '',
				'learn'        => self::lines( (string) get_post_meta( $course->ID, '_ael_learn', true ) ),
				'audience'     => self::lines( (string) get_post_meta( $course->ID, '_ael_audience', true ) ),
				'preview_html' => $preview_html,
				'program'      => $program,
				'has_access'   => AEL_Access::user_can_access_course( $user_id, $course->ID ),
				'actions'      => self::actions( $course->ID, $user_id ),
				'categories'   => $cats,
				'is_visitor'   => ! $user_id,
				'login_url'    => wp_login_url( (string) get_permalink( $course ) ),
				'duration'     => self::format_minutes( $program['minutes'] ),
			)
		);
	}

	/**
	 * Pagina del docente: biografia e corsi.
	 *
	 * @param WP_Post $teacher Docente.
	 * @param string  $content Biografia già elaborata.
	 */
	private function render_teacher( WP_Post $teacher, string $content ): string {
		wp_enqueue_style( 'ael-front' );

		$query = self::query_courses(
			array(
				'posts_per_page' => -1,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_ael_teacher_id',
						'value' => $teacher->ID,
						'type'  => 'NUMERIC',
					),
				),
			)
		);
		return AEL_Templates::render(
			'teacher',
			array(
				'teacher' => $teacher,
				'content' => $content,
				'courses' => $query->posts,
				'cards'   => $this->cards( $query->posts ),
			)
		);
	}

	/**
	 * Pagina della raccolta: i suoi corsi nell'ordine scelto.
	 *
	 * @param WP_Post $collection Raccolta.
	 * @param string  $content    Descrizione già elaborata.
	 */
	private function render_collection( WP_Post $collection, string $content ): string {
		wp_enqueue_style( 'ael-front' );

		$ids   = get_post_meta( $collection->ID, '_ael_course_ids', true );
		$ids   = is_array( $ids ) ? array_values( array_filter( array_map( 'intval', $ids ) ) ) : array();
		$posts = array();
		if ( ! empty( $ids ) ) {
			$query = self::query_courses(
				array(
					'posts_per_page' => -1,
					'post__in'       => $ids,
					'orderby'        => 'post__in',
				)
			);
			$posts = $query->posts;
		}
		return AEL_Templates::render(
			'collection',
			array(
				'collection' => $collection,
				'content'    => $content,
				'courses'    => $posts,
				'cards'      => $this->cards( $posts ),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Catalogo                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Cerca i corsi pubblicati.
	 *
	 * @param array<string, mixed> $args Argomenti di WP_Query che si sommano a quelli di base.
	 */
	public static function query_courses( array $args = array() ): WP_Query {
		return new WP_Query(
			array_merge(
				array(
					'post_type'      => AEL_Content::COURSE,
					'post_status'    => 'publish',
					'posts_per_page' => 12,
					'orderby'        => 'date',
					'order'          => 'DESC',
				),
				$args
			)
		);
	}

	/**
	 * Prepara i dati di una scheda per ogni corso.
	 *
	 * @param WP_Post[] $courses Corsi.
	 * @return array<int, array<string, mixed>>
	 */
	public function cards( array $courses ): array {
		$user_id = get_current_user_id();
		$manager = $user_id && current_user_can( AEL_Content::CAP );
		$levels  = self::levels();
		$cards   = array();

		foreach ( $courses as $course ) {
			$program = self::program( $course->ID, $user_id, false );
			$teacher = self::teacher_of( $course->ID );
			$level   = (string) get_post_meta( $course->ID, '_ael_level', true );
			$terms   = get_the_terms( $course->ID, AEL_Content::CATEGORY );

			$cards[] = array(
				'course'       => $course,
				'url'          => (string) get_permalink( $course ),
				'title'        => get_the_title( $course ),
				'subtitle'     => (string) get_post_meta( $course->ID, '_ael_subtitle', true ),
				'thumbnail'    => get_the_post_thumbnail( $course, 'medium_large' ),
				'teacher_name' => $teacher ? get_the_title( $teacher ) : '',
				'level_label'  => $levels[ $level ] ?? '',
				'lessons'      => $program['lesson_count'],
				'duration'     => self::format_minutes( $program['minutes'] ),
				'categories'   => is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : array(),
				'owned'        => $user_id && ! $manager && AEL_Access::user_can_access_course( $user_id, $course->ID ),
			);
		}
		return $cards;
	}

	/**
	 * Shortcode [ael_catalog]: elenco dei corsi con filtri.
	 *
	 * Attributi: per_page (12), category (slug), teacher (ID docente),
	 * collection (ID raccolta), filters (1 o 0), orderby (date, title).
	 *
	 * @param array|string $atts Attributi.
	 */
	public function shortcode( $atts ): string {
		wp_enqueue_style( 'ael-front' );

		$atts = shortcode_atts(
			array(
				'per_page'   => 12,
				'category'   => '',
				'teacher'    => 0,
				'collection' => 0,
				'filters'    => 1,
				'orderby'    => 'date',
			),
			is_array( $atts ) ? $atts : array(),
			'ael_catalog'
		);

		$filters  = (bool) (int) $atts['filters'];
		$category = sanitize_title( (string) $atts['category'] );
		$teacher  = absint( $atts['teacher'] );
		$search   = '';
		$paged    = 1;

		if ( $filters ) {
			// Sono solo filtri di lettura nell'indirizzo: nessuna modifica di dati, niente nonce.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['ael_cat'] ) ) {
				$category = sanitize_title( wp_unslash( $_GET['ael_cat'] ) );
			}
			if ( isset( $_GET['ael_teacher'] ) ) {
				$teacher = absint( wp_unslash( $_GET['ael_teacher'] ) );
			}
			if ( isset( $_GET['ael_q'] ) ) {
				$search = sanitize_text_field( wp_unslash( $_GET['ael_q'] ) );
			}
			if ( isset( $_GET['ael_page'] ) ) {
				$paged = max( 1, absint( wp_unslash( $_GET['ael_page'] ) ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}

		$orderby = in_array( $atts['orderby'], array( 'date', 'title' ), true ) ? $atts['orderby'] : 'date';
		$args    = array(
			'posts_per_page' => max( 1, min( 48, absint( $atts['per_page'] ) ) ),
			'paged'          => $paged,
			'orderby'        => $orderby,
			'order'          => 'title' === $orderby ? 'ASC' : 'DESC',
		);
		if ( '' !== $category ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => AEL_Content::CATEGORY,
					'field'    => 'slug',
					'terms'    => $category,
				),
			);
		}
		if ( $teacher ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_ael_teacher_id',
					'value' => $teacher,
					'type'  => 'NUMERIC',
				),
			);
		}
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$collection_id = absint( $atts['collection'] );
		if ( $collection_id ) {
			$ids               = get_post_meta( $collection_id, '_ael_course_ids', true );
			$ids               = is_array( $ids ) ? array_values( array_filter( array_map( 'intval', $ids ) ) ) : array();
			$args['post__in']  = ! empty( $ids ) ? $ids : array( 0 );
			$args['orderby']   = 'post__in';
		}

		$query = self::query_courses( $args );

		$terms    = $filters ? get_terms(
			array(
				'taxonomy'   => AEL_Content::CATEGORY,
				'hide_empty' => true,
			)
		) : array();
		$teachers = $filters ? get_posts(
			array(
				'post_type'   => AEL_Content::TEACHER,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		) : array();

		$base_url   = (string) get_permalink();
		$pagination = '';
		if ( $query->max_num_pages > 1 ) {
			$add_args = array_filter(
				array(
					'ael_cat'     => $category,
					'ael_teacher' => $teacher ? $teacher : '',
					'ael_q'       => $search,
				),
				'strlen'
			);
			$pagination = (string) paginate_links(
				array(
					'base'      => add_query_arg( 'ael_page', '%#%', $base_url ),
					'format'    => '',
					'current'   => $paged,
					'total'     => (int) $query->max_num_pages,
					'add_args'  => $add_args,
					'prev_text' => __( 'Precedente', 'academy-elearning' ),
					'next_text' => __( 'Successiva', 'academy-elearning' ),
				)
			);
		}

		return AEL_Templates::render(
			'catalog',
			array(
				'cards'        => $this->cards( $query->posts ),
				'filters'      => $filters,
				'terms'        => is_array( $terms ) ? $terms : array(),
				'teachers'     => $teachers,
				'category'     => $category,
				'teacher'      => $teacher,
				'search'       => $search,
				'form_url'     => $base_url,
				'plain_page'   => ! get_option( 'permalink_structure' ) ? (int) get_queried_object_id() : 0,
				'pagination'   => $pagination,
				'has_filters'  => ( '' !== $category || $teacher || '' !== $search ),
				'catalog_url'  => $base_url,
			)
		);
	}
}
