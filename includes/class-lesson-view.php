<?php
/**
 * Pagina pubblica della lezione, video protetto e download dei materiali.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Mostra la lezione solo a chi AEL_Access dice che può vederla.
 *
 * Regola del progetto: l'ID o il link del video non devono comparire, nemmeno
 * nel codice sorgente, per chi non ha accesso. Per questo il video viene
 * costruito solo dopo il controllo e il testo della lezione viene tolto anche
 * dagli altri contesti (elenchi, feed, estratti) quando non c'è accesso.
 */
final class AEL_Lesson_View {

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Lesson_View|null
	 */
	private static ?AEL_Lesson_View $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Lesson_View {
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
	 * Aggancia contenuto, estratti, motori di ricerca e download.
	 */
	public function boot(): void {
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_filter( 'get_the_excerpt', array( $this, 'filter_excerpt' ), 10, 2 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_action( 'template_redirect', array( $this, 'maybe_download' ), 1 );
	}

	/**
	 * Indirizzo di download protetto di un materiale.
	 *
	 * @param int $lesson_id Lezione.
	 * @param int $file_id   Allegato.
	 */
	public static function download_url( int $lesson_id, int $file_id ): string {
		return add_query_arg(
			array(
				'ael_download' => $lesson_id,
				'ael_file'     => $file_id,
			),
			home_url( '/' )
		);
	}

	/**
	 * Le lezioni non vanno indicizzate dai motori di ricerca.
	 *
	 * @param array $robots Direttive attuali.
	 * @return array
	 */
	public function robots( $robots ) {
		if ( is_singular( AEL_Content::LESSON ) && is_array( $robots ) ) {
			$robots['noindex']   = true;
			$robots['noarchive'] = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/**
	 * Estratto: vuoto per le lezioni che l'utente non può vedere.
	 *
	 * @param string       $excerpt Estratto.
	 * @param WP_Post|null $post    Articolo.
	 */
	public function filter_excerpt( $excerpt, $post = null ) {
		if ( $post instanceof WP_Post && AEL_Content::LESSON === $post->post_type
			&& ! AEL_Access::current_user_can_access_lesson( (int) $post->ID ) ) {
			return '';
		}
		return $excerpt;
	}

	/**
	 * Contenuto della lezione. Nella pagina principale disegna il lettore; in
	 * ogni altro contesto restituisce il testo solo a chi ha accesso.
	 *
	 * @param string $content Contenuto già elaborato.
	 */
	public function filter_content( $content ) {
		$content = (string) $content;
		$post    = get_post();
		if ( ! $post instanceof WP_Post || AEL_Content::LESSON !== $post->post_type ) {
			return $content;
		}

		$user_id = get_current_user_id();
		$can     = AEL_Access::user_can_access_lesson( $user_id, (int) $post->ID );

		if ( ! is_singular( AEL_Content::LESSON ) || ! in_the_loop() || ! is_main_query() ) {
			return $can ? $content : '';
		}

		wp_enqueue_style( 'ael-front' );

		$course_id = (int) get_post_meta( $post->ID, '_ael_course_id', true );
		$course    = $course_id ? get_post( $course_id ) : null;
		$course    = ( $course instanceof WP_Post && AEL_Content::COURSE === $course->post_type ) ? $course : null;

		$program = $course ? AEL_Catalog::program( $course->ID, $user_id ) : array(
			'modules'      => array(),
			'lesson_count' => 0,
			'minutes'      => 0,
		);
		$flat    = AEL_Catalog::flat_lessons( $program );
		$prev    = null;
		$next    = null;
		foreach ( $flat as $index => $item ) {
			if ( (int) $item['id'] === (int) $post->ID ) {
				$prev = $flat[ $index - 1 ] ?? null;
				$next = $flat[ $index + 1 ] ?? null;
				break;
			}
		}

		// Il video e i materiali si costruiscono SOLO se l'accesso è confermato.
		$video_html = '';
		$materials  = array();
		if ( $can ) {
			$source     = (string) get_post_meta( $post->ID, '_ael_video_source', true );
			$ref        = (string) get_post_meta( $post->ID, '_ael_video_ref', true );
			$video_html = AEL_Video::embed( $source, $ref, get_the_title( $post ) );
			$materials  = $this->materials( (int) $post->ID );
		}

		// Solo chi è collegato e ha accesso salva i progressi (i visitatori delle anteprime no).
		$tracking  = $can && $user_id > 0 && null !== $course;
		$completed = false;
		if ( $tracking ) {
			$row       = AEL_Progress::lesson_row( $user_id, (int) $post->ID );
			$completed = $row && 'completed' === $row['status'];
			$this->enqueue_player( (int) $post->ID, $completed, $row ? (int) $row['last_position'] : 0 );
		}

		return AEL_Templates::render(
			'lesson',
			array(
				'lesson'      => $post,
				'tracking'    => $tracking,
				'completed'   => $completed,
				'content'     => $can ? $content : '',
				'can'         => $can,
				'course'      => $course,
				'course_url'  => $course ? (string) get_permalink( $course ) : '',
				'catalog_url' => AEL_Catalog::catalog_url(),
				'video_html'  => $video_html,
				'materials'   => $materials,
				'program'     => $program,
				'prev'        => $prev,
				'next'        => $next,
				'is_preview'  => (bool) get_post_meta( $post->ID, '_ael_is_preview', true ),
				'accent_style' => $this->accent_style( $course ),
				'actions'     => ( ! $can && $course ) ? AEL_Catalog::actions( $course->ID, $user_id ) : array(),
				'is_visitor'  => ! $user_id,
				'login_url'   => wp_login_url( (string) get_permalink( $post ) ),
			)
		);
	}

	/**
	 * Variabili CSS con il colore del docente del corso (vuote se non c'è).
	 *
	 * @param WP_Post|null $course Corso.
	 */
	private function accent_style( ?WP_Post $course ): string {
		$teacher_box = $course ? AEL_Catalog::teacher_box_for_course( $course->ID ) : null;
		return $teacher_box ? $teacher_box['style'] : '';
	}

	/**
	 * Carica lo script che registra posizione, tempo guardato e completamento.
	 *
	 * @param int  $lesson_id Lezione.
	 * @param bool $completed La lezione è già completata.
	 * @param int  $position  Ultima posizione del video, in secondi.
	 */
	private function enqueue_player( int $lesson_id, bool $completed, int $position ): void {
		wp_enqueue_script(
			'ael-lesson',
			AEL_URL . 'assets/lesson.js',
			array(),
			AEL_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'ael-lesson',
			'aelLesson',
			array(
				'restUrl'   => esc_url_raw( rest_url( AEL_Rest::NAMESPACE . '/lessons/' . $lesson_id . '/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'completed' => $completed,
				'position'  => $position,
				'threshold' => (int) apply_filters( 'ael_completion_threshold', 90 ),
				'i18n'      => array(
					'complete'   => __( 'Segna come completata', 'academy-elearning' ),
					'uncomplete' => __( 'Completata. Annulla', 'academy-elearning' ),
					'saved'      => __( 'Salvato', 'academy-elearning' ),
					'error'      => __( 'Non sono riuscito a salvare. Riprova.', 'academy-elearning' ),
				),
			)
		);
	}

	/**
	 * Materiali scaricabili di una lezione, con indirizzo protetto.
	 *
	 * @param int $lesson_id Lezione.
	 * @return array<int, array{title: string, url: string, size: string, type: string}>
	 */
	private function materials( int $lesson_id ): array {
		$out = array();
		foreach ( AEL_Content::lesson_materials( $lesson_id ) as $file_id ) {
			$path = get_attached_file( $file_id );
			if ( 'attachment' !== get_post_type( $file_id ) || ! $path || ! is_readable( $path ) ) {
				continue;
			}
			$type  = wp_check_filetype( $path );
			$out[] = array(
				'title' => get_the_title( $file_id ),
				'url'   => self::download_url( $lesson_id, $file_id ),
				'size'  => size_format( (int) filesize( $path ) ),
				'type'  => strtoupper( (string) $type['ext'] ),
			);
		}
		return $out;
	}

	/**
	 * Consegna un materiale dopo aver controllato accesso e appartenenza alla lezione.
	 */
	public function maybe_download(): void {
		// Richiesta di lettura: il controllo di sicurezza è l'accesso alla lezione, non un nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['ael_download'], $_GET['ael_file'] ) ) {
			return;
		}
		$lesson_id = absint( wp_unslash( $_GET['ael_download'] ) );
		$file_id   = absint( wp_unslash( $_GET['ael_file'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $lesson_id || ! $file_id || ! AEL_Access::current_user_can_access_lesson( $lesson_id ) ) {
			wp_die( esc_html__( 'Non hai accesso a questo file.', 'academy-elearning' ), '', array( 'response' => 403 ) );
		}
		if ( ! in_array( $file_id, AEL_Content::lesson_materials( $lesson_id ), true ) || 'attachment' !== get_post_type( $file_id ) ) {
			wp_die( esc_html__( 'File non trovato.', 'academy-elearning' ), '', array( 'response' => 404 ) );
		}

		$path    = get_attached_file( $file_id );
		$real    = $path ? realpath( $path ) : false;
		$uploads = realpath( wp_get_upload_dir()['basedir'] );
		if ( ! $real || ! $uploads || 0 !== strpos( $real, $uploads . DIRECTORY_SEPARATOR ) || ! is_file( $real ) ) {
			wp_die( esc_html__( 'File non trovato.', 'academy-elearning' ), '', array( 'response' => 404 ) );
		}

		$type = wp_check_filetype( $real );
		nocache_headers();
		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $real ) ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $real ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );

		while ( ob_get_level() ) {
			ob_end_clean();
		}
		readfile( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
