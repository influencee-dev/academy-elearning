<?php
/**
 * Quiz: verifiche a risposta multipla per gli abbonati.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tipo di contenuto "Quiz" con le domande salvate nel contenuto stesso (meta) e una tabella
 * dei tentativi. La correzione avviene sempre sul server: le risposte giuste non arrivano al
 * browser prima dell'invio e chi non è abbonato non vede nemmeno le domande.
 *
 * Meta del quiz: _ael_quiz_questions (elenco di domande), _ael_quiz_course_id, _ael_quiz_pass.
 */
final class AEL_Quiz {

	public const TYPE           = 'ael_quiz';
	public const META_QUESTIONS = '_ael_quiz_questions';
	public const META_COURSE    = '_ael_quiz_course_id';
	public const META_PASS      = '_ael_quiz_pass';
	public const TABLE          = 'ael_quiz_attempts';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Quiz|null
	 */
	private static ?AEL_Quiz $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Quiz {
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
	 * Aggancia tipo di contenuto, tabella, pagina, invio delle risposte.
	 */
	public function boot(): void {
		add_filter( 'ael_pages_definitions', array( $this, 'add_page' ) );
		add_filter( 'ael_migrations', array( $this, 'add_migrations' ) );
		add_filter( 'ael_template_path', array( $this, 'module_templates' ), 10, 2 );
		add_filter( 'user_has_cap', array( $this, 'grant_caps' ), 20 );

		add_action( 'init', array( $this, 'register' ), 6 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite' ), 99 );
		add_action( 'ael_module_activated', array( $this, 'on_module_activated' ) );
		add_shortcode( 'ael_quizzes', array( $this, 'shortcode' ) );
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_action( 'admin_post_ael_quiz_submit', array( $this, 'handle_submit' ) );
		add_action( 'ael_after_lesson_content', array( $this, 'lesson_quiz_links' ), 20, 2 );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
			add_action( 'save_post_' . self::TYPE, array( $this, 'save_meta' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Pagina, tabella, template                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Aggiunge la pagina "Quiz" all'elenco delle pagine di sistema.
	 *
	 * @param array $defs Pagine.
	 * @return array
	 */
	public function add_page( $defs ) {
		$defs['quizzes'] = array(
			'option'  => 'ael_page_quizzes',
			'title'   => 'Quiz',
			'slug'    => 'quiz',
			'content' => '[ael_quizzes]',
		);
		return $defs;
	}

	/**
	 * Migrazioni del modulo: tabella dei tentativi e pagina.
	 *
	 * @param array<string, callable> $migrations Migrazioni.
	 * @return array<string, callable>
	 */
	public function add_migrations( $migrations ) {
		$migrations['quiz-001-attempts'] = array( __CLASS__, 'create_table' );
		$migrations['quiz-002-page']     = array( __CLASS__, 'setup_page' );
		return $migrations;
	}

	/**
	 * Nome completo della tabella.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Crea la tabella dei tentativi (le tabelle esistenti non si toccano).
	 */
	public static function create_table(): bool {
		$sql = 'CREATE TABLE {table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  quiz_id bigint(20) unsigned NOT NULL,
  score int(11) NOT NULL DEFAULT 0,
  total int(11) NOT NULL DEFAULT 0,
  percent int(11) NOT NULL DEFAULT 0,
  passed tinyint(1) NOT NULL DEFAULT 0,
  answers longtext NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_quiz (user_id,quiz_id)
) {charset};';
		return AEL_Installer::create_table( self::TABLE, $sql );
	}

	/**
	 * Crea la pagina Quiz e chiede di rigenerare gli indirizzi.
	 */
	public static function setup_page(): bool {
		update_option( 'ael_quiz_flush', 1 );
		return AEL_Pages::setup();
	}

	/**
	 * Quando il modulo viene acceso, rigenera gli indirizzi.
	 *
	 * @param string $slug Slug del modulo.
	 */
	public function on_module_activated( $slug ): void {
		if ( 'quiz' === $slug ) {
			update_option( 'ael_quiz_flush', 1 );
		}
	}

	/**
	 * Rigenera gli indirizzi una volta, dopo la registrazione del tipo di contenuto.
	 */
	public function maybe_flush_rewrite(): void {
		if ( get_option( 'ael_quiz_flush' ) ) {
			delete_option( 'ael_quiz_flush' );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * I template del modulo vivono nella sua cartella; il tema può comunque sostituirli.
	 *
	 * @param string $path Percorso scelto.
	 * @param string $name Nome del template.
	 * @return string
	 */
	public function module_templates( $path, $name ) {
		if ( in_array( $name, array( 'quizzes', 'quiz' ), true ) && ! is_readable( (string) $path ) ) {
			return __DIR__ . '/templates/' . $name . '.php';
		}
		return $path;
	}

	/* ------------------------------------------------------------------ */
	/* Contenuto                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Registra il tipo di contenuto "Quiz".
	 */
	public function register(): void {
		register_post_type(
			self::TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Quiz', 'academy-elearning' ),
					'singular_name' => __( 'Quiz', 'academy-elearning' ),
					'add_new_item'  => __( 'Aggiungi quiz', 'academy-elearning' ),
					'edit_item'     => __( 'Modifica quiz', 'academy-elearning' ),
					'all_items'     => __( 'Quiz', 'academy-elearning' ),
				),
				'public'              => true,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'show_ui'             => true,
				'show_in_menu'        => 'ael-academy',
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => array( 'slug' => 'verifiche' ),
				'supports'            => array( 'title', 'excerpt' ),
				'capability_type'     => array( 'ael_quiz', 'ael_quizzes' ),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Chi ha la capacità del titolare gestisce anche i quiz.
	 *
	 * @param array $allcaps Permessi dell'utente.
	 * @return array
	 */
	public function grant_caps( $allcaps ) {
		if ( ! empty( $allcaps[ AEL_Content::CAP ] ) ) {
			$object = get_post_type_object( self::TYPE );
			if ( $object ) {
				foreach ( (array) $object->cap as $name ) {
					$allcaps[ (string) $name ] = true;
				}
			}
		}
		return $allcaps;
	}

	/**
	 * L'utente corrente può fare i quiz?
	 */
	public static function can_take(): bool {
		return AEL_Access::user_has_catalog_access( get_current_user_id() );
	}

	/**
	 * Domande di un quiz, normalizzate: q (testo), options (elenco), correct (indice da 0), explain.
	 *
	 * @param int $quiz_id Quiz.
	 * @return array<int, array{q: string, options: string[], correct: int, explain: string}>
	 */
	public static function questions( int $quiz_id ): array {
		$raw = get_post_meta( $quiz_id, self::META_QUESTIONS, true );
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || empty( $row['q'] ) || empty( $row['options'] ) || ! is_array( $row['options'] ) ) {
				continue;
			}
			$options = array_values( array_filter( array_map( 'strval', $row['options'] ), 'strlen' ) );
			if ( count( $options ) < 2 ) {
				continue;
			}
			$correct = max( 0, min( count( $options ) - 1, (int) ( $row['correct'] ?? 0 ) ) );
			$out[]   = array(
				'q'       => (string) $row['q'],
				'options' => $options,
				'correct' => $correct,
				'explain' => (string) ( $row['explain'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Percentuale per superare il quiz (0-100).
	 *
	 * @param int $quiz_id Quiz.
	 */
	public static function pass_percent( int $quiz_id ): int {
		$value = get_post_meta( $quiz_id, self::META_PASS, true );
		return '' === $value ? 60 : max( 0, min( 100, (int) $value ) );
	}

	/**
	 * Tentativi di un utente su un quiz, dal più recente.
	 *
	 * @param int $user_id Utente.
	 * @param int $quiz_id Quiz.
	 * @return array<int, array<string, mixed>>
	 */
	public static function attempts( int $user_id, int $quiz_id ): array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, score, total, percent, passed, created_at FROM {$table} WHERE user_id = %d AND quiz_id = %d ORDER BY id DESC LIMIT 20", $user_id, $quiz_id ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Un tentativo, solo se è dell'utente indicato.
	 *
	 * @param int $attempt_id Tentativo.
	 * @param int $user_id    Utente.
	 * @return array<string, mixed>|null
	 */
	public static function attempt( int $attempt_id, int $user_id ): ?array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND user_id = %d", $attempt_id, $user_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Carica gli stili della sezione.
	 */
	private static function enqueue(): void {
		wp_enqueue_style( 'ael-front' );
		wp_enqueue_style( 'ael-sections', AEL_URL . 'assets/sections.css', array( 'ael-front' ), AEL_VERSION );
	}

	/* ------------------------------------------------------------------ */
	/* Pagine pubbliche                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Shortcode [ael_quizzes]: elenco dei quiz.
	 */
	public function shortcode(): string {
		self::enqueue();
		$user_id = get_current_user_id();
		$can     = self::can_take();

		$items = array();
		$posts = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 60,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		foreach ( $posts as $quiz ) {
			$course_id = (int) get_post_meta( $quiz->ID, self::META_COURSE, true );
			$best      = null;
			if ( $can && $user_id ) {
				foreach ( self::attempts( $user_id, $quiz->ID ) as $row ) {
					$best = null === $best ? (int) $row['percent'] : max( $best, (int) $row['percent'] );
				}
			}
			$items[] = array(
				'title'     => get_the_title( $quiz ),
				'excerpt'   => wp_trim_words( wp_strip_all_tags( (string) $quiz->post_excerpt ), 28 ),
				'url'       => get_permalink( $quiz ),
				'questions' => count( self::questions( $quiz->ID ) ),
				'pass'      => self::pass_percent( $quiz->ID ),
				'course'    => $course_id ? get_the_title( $course_id ) : '',
				'best'      => $best,
			);
		}

		return AEL_Templates::render(
			'quizzes',
			array(
				'items'    => $items,
				'can_take' => $can,
				'gate'     => $this->gate_vars(),
			)
		);
	}

	/**
	 * Dati per il riquadro "riservato agli abbonati".
	 *
	 * @return array<string, mixed>
	 */
	private function gate_vars(): array {
		return array(
			'logged_in'     => is_user_logged_in(),
			'subscribe_url' => AEL_Pages::url( 'subscribe', home_url( '/' ) ),
			'login_url'     => AEL_Pages::url( 'login', wp_login_url() ),
			'section'       => __( 'I quiz', 'academy-elearning' ),
		);
	}

	/**
	 * Pagina del singolo quiz: modulo con le domande, oppure il risultato.
	 * Chi non è abbonato non riceve mai le domande, in nessun contesto.
	 *
	 * @param string $content Contenuto elaborato.
	 */
	public function filter_content( $content ) {
		$post = get_post();
		if ( ! $post instanceof WP_Post || self::TYPE !== $post->post_type ) {
			return $content;
		}
		if ( ! ( is_singular( self::TYPE ) && in_the_loop() && is_main_query() ) ) {
			return esc_html( wp_trim_words( wp_strip_all_tags( (string) $post->post_excerpt ), 30 ) );
		}

		self::enqueue();
		$user_id = get_current_user_id();
		$vars    = array(
			'quiz_id'   => (int) $post->ID,
			'intro'     => wp_strip_all_tags( (string) $post->post_excerpt ),
			'can_take'  => self::can_take(),
			'gate'      => $this->gate_vars(),
			'back_url'  => AEL_Pages::url( 'quizzes', home_url( '/' ) ),
			'questions' => array(),
			'pass'      => self::pass_percent( $post->ID ),
			'attempts'  => array(),
			'result'    => null,
		);

		if ( $vars['can_take'] ) {
			$vars['questions'] = self::questions( $post->ID );
			$vars['attempts']  = self::attempts( $user_id, $post->ID );

			// Lettura del risultato di un proprio tentativo: nessun dato scritto, il controllo è la proprietà.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$attempt_id = isset( $_GET['risultato'] ) ? absint( wp_unslash( $_GET['risultato'] ) ) : 0;
			$attempt    = $attempt_id ? self::attempt( $attempt_id, $user_id ) : null;
			if ( $attempt && (int) $attempt['quiz_id'] === (int) $post->ID ) {
				$answers = json_decode( (string) $attempt['answers'], true );
				$vars['result'] = array(
					'score'   => (int) $attempt['score'],
					'total'   => (int) $attempt['total'],
					'percent' => (int) $attempt['percent'],
					'passed'  => (bool) $attempt['passed'],
					'answers' => is_array( $answers ) ? $answers : array(),
				);
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$vars['error'] = isset( $_GET['errore'] ) ? sanitize_key( wp_unslash( $_GET['errore'] ) ) : '';
		}
		return AEL_Templates::render( 'quiz', $vars );
	}

	/* ------------------------------------------------------------------ */
	/* Invio delle risposte                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Corregge un quiz, salva il tentativo e riporta al risultato.
	 */
	public function handle_submit(): void {
		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
		check_admin_referer( 'ael_quiz_submit_' . $quiz_id );

		if ( ! $quiz_id || self::TYPE !== get_post_type( $quiz_id ) || 'publish' !== get_post_status( $quiz_id ) ) {
			wp_die( esc_html__( 'Quiz non trovato.', 'academy-elearning' ), '', array( 'response' => 404 ) );
		}
		$user_id = get_current_user_id();
		if ( ! $user_id || ! self::can_take() ) {
			wp_die( esc_html__( 'Non hai accesso a questo quiz.', 'academy-elearning' ), '', array( 'response' => 403 ) );
		}

		$questions = self::questions( $quiz_id );
		$posted    = isset( $_POST['ael_answer'] ) && is_array( $_POST['ael_answer'] ) ? array_map( 'intval', wp_unslash( $_POST['ael_answer'] ) ) : array();
		if ( empty( $questions ) ) {
			wp_safe_redirect( add_query_arg( 'errore', 'vuoto', get_permalink( $quiz_id ) ) );
			exit;
		}
		if ( count( $posted ) < count( $questions ) ) {
			wp_safe_redirect( add_query_arg( 'errore', 'incompleto', get_permalink( $quiz_id ) ) );
			exit;
		}

		$score   = 0;
		$answers = array();
		foreach ( $questions as $index => $question ) {
			$chosen    = isset( $posted[ $index ] ) ? (int) $posted[ $index ] : -1;
			$is_right  = $chosen === $question['correct'];
			$score    += $is_right ? 1 : 0;
			$answers[] = array(
				'chosen'  => $chosen,
				'correct' => $question['correct'],
				'right'   => $is_right,
			);
		}
		$total   = count( $questions );
		$percent = (int) round( 100 * $score / max( 1, $total ) );
		$passed  = $percent >= self::pass_percent( $quiz_id );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			self::table(),
			array(
				'user_id'    => $user_id,
				'quiz_id'    => $quiz_id,
				'score'      => $score,
				'total'      => $total,
				'percent'    => $percent,
				'passed'     => $passed ? 1 : 0,
				'answers'    => wp_json_encode( $answers ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
		$attempt_id = (int) $wpdb->insert_id;

		/**
		 * Scatta quando un utente completa un quiz.
		 *
		 * @param int  $user_id    Utente.
		 * @param int  $quiz_id    Quiz.
		 * @param int  $percent    Percentuale di risposte giuste.
		 * @param bool $passed     Soglia superata.
		 * @param int  $attempt_id Tentativo.
		 */
		do_action( 'ael_quiz_completed', $user_id, $quiz_id, $percent, $passed, $attempt_id );

		wp_safe_redirect( add_query_arg( 'risultato', $attempt_id, get_permalink( $quiz_id ) ) . '#risultato' );
		exit;
	}

	/**
	 * In fondo alla lezione: i quiz collegati al corso, per chi può farli.
	 *
	 * @param int $lesson_id Lezione.
	 * @param int $course_id Corso.
	 */
	public function lesson_quiz_links( $lesson_id, $course_id ): void {
		$course_id = (int) $course_id;
		if ( ! $course_id || ! self::can_take() ) {
			return;
		}
		$quizzes = get_posts(
			array(
				'post_type'      => self::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'meta_key'       => self::META_COURSE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $course_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( empty( $quizzes ) ) {
			return;
		}
		self::enqueue();
		echo '<div class="ael-lesson-quiz"><h3>' . esc_html__( 'Metti alla prova quello che hai imparato', 'academy-elearning' ) . '</h3><p>';
		foreach ( $quizzes as $quiz ) {
			echo '<a class="ael-button ael-button--secondary" href="' . esc_url( (string) get_permalink( $quiz ) ) . '">' . esc_html( get_the_title( $quiz ) ) . '</a> ';
		}
		echo '</p></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Amministrazione                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Riquadro delle domande nella schermata del quiz.
	 */
	public function add_meta_box(): void {
		add_meta_box( 'ael_quiz_questions', __( 'Domande e impostazioni', 'academy-elearning' ), array( $this, 'box_questions' ), self::TYPE, 'normal', 'high' );
	}

	/**
	 * Disegna una domanda dell'editor.
	 *
	 * @param array $row Domanda (vuota per il modello).
	 */
	private function question_row( array $row = array() ): void {
		$options = isset( $row['options'] ) && is_array( $row['options'] ) ? implode( "\n", $row['options'] ) : '';
		?>
		<div class="ael-quiz-q" style="border:1px solid #ccd0d4;padding:12px;margin:0 0 12px;background:#fff">
			<p><strong><?php echo esc_html__( 'Domanda', 'academy-elearning' ); ?> <span class="ael-quiz-n"></span></strong>
				<a href="#" class="ael-quiz-del" style="float:right"><?php echo esc_html__( 'Elimina', 'academy-elearning' ); ?></a></p>
			<p><input type="text" class="widefat" data-f="q" value="<?php echo esc_attr( (string) ( $row['q'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr__( 'Testo della domanda', 'academy-elearning' ); ?>"></p>
			<p><textarea class="widefat" rows="4" data-f="options" placeholder="<?php echo esc_attr__( 'Le risposte, una per riga (almeno due)', 'academy-elearning' ); ?>"><?php echo esc_textarea( $options ); ?></textarea></p>
			<p><label><?php echo esc_html__( 'Numero della risposta giusta (1 = la prima riga)', 'academy-elearning' ); ?>
				<input type="number" min="1" max="10" data-f="correct" value="<?php echo esc_attr( (string) ( (int) ( $row['correct'] ?? 0 ) + 1 ) ); ?>" style="width:70px"></label></p>
			<p><input type="text" class="widefat" data-f="explain" value="<?php echo esc_attr( (string) ( $row['explain'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr__( 'Spiegazione mostrata dopo l\'invio (facoltativa)', 'academy-elearning' ); ?>"></p>
		</div>
		<?php
	}

	/**
	 * Disegna il riquadro delle domande.
	 *
	 * @param WP_Post $post Quiz.
	 */
	public function box_questions( WP_Post $post ): void {
		wp_nonce_field( 'ael_quiz_save_' . $post->ID, 'ael_quiz_nonce' );
		$course  = (int) get_post_meta( $post->ID, self::META_COURSE, true );
		$courses = get_posts(
			array(
				'post_type'      => AEL_Content::COURSE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<p class="description"><?php echo esc_html__( 'Il quiz è visibile solo a chi ha l\'abbonamento. Il testo qui sopra, nell\'estratto, è la presentazione. Le risposte giuste le vede chi ha già inviato il quiz.', 'academy-elearning' ); ?></p>
		<p>
			<label><?php echo esc_html__( 'Corso collegato (facoltativo)', 'academy-elearning' ); ?>
				<select name="ael_quiz_course">
					<option value="0"><?php echo esc_html__( '— Nessuno —', 'academy-elearning' ); ?></option>
					<?php foreach ( $courses as $c ) : ?>
						<option value="<?php echo esc_attr( (string) $c->ID ); ?>" <?php selected( $course, (int) $c->ID ); ?>><?php echo esc_html( get_the_title( $c ) ); ?></option>
					<?php endforeach; ?>
				</select></label>
			&nbsp;
			<label><?php echo esc_html__( 'Per superarlo serve almeno (%)', 'academy-elearning' ); ?>
				<input type="number" name="ael_quiz_pass" min="0" max="100" value="<?php echo esc_attr( (string) self::pass_percent( $post->ID ) ); ?>" style="width:70px"></label>
		</p>
		<div id="ael-quiz-questions">
			<?php
			foreach ( self::questions( $post->ID ) as $row ) {
				$this->question_row( $row );
			}
			?>
		</div>
		<script type="text/template" id="ael-quiz-tpl"><?php $this->question_row(); ?></script>
		<p><button type="button" class="button button-primary" id="ael-quiz-add"><?php echo esc_html__( 'Aggiungi domanda', 'academy-elearning' ); ?></button></p>
		<?php
	}

	/**
	 * Salva domande e impostazioni.
	 *
	 * @param int     $post_id Quiz.
	 * @param WP_Post $post    Contenuto.
	 */
	public function save_meta( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['ael_quiz_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ael_quiz_nonce'] ) ), 'ael_quiz_save_' . $post_id ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$rows = array();
		$raw  = isset( $_POST['ael_quiz'] ) && is_array( $_POST['ael_quiz'] ) ? wp_unslash( $_POST['ael_quiz'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ogni campo è pulito qui sotto.
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$q = sanitize_text_field( (string) ( $row['q'] ?? '' ) );
			$options = array();
			foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $row['options'] ?? '' ) ) as $line ) {
				$line = sanitize_text_field( $line );
				if ( '' !== $line ) {
					$options[] = $line;
				}
			}
			if ( '' === $q || count( $options ) < 2 ) {
				continue;
			}
			$rows[] = array(
				'q'       => $q,
				'options' => $options,
				'correct' => max( 0, min( count( $options ) - 1, absint( $row['correct'] ?? 1 ) - 1 ) ),
				'explain' => sanitize_text_field( (string) ( $row['explain'] ?? '' ) ),
			);
		}
		update_post_meta( $post_id, self::META_QUESTIONS, $rows );

		$course_id = isset( $_POST['ael_quiz_course'] ) ? absint( wp_unslash( $_POST['ael_quiz_course'] ) ) : 0;
		if ( $course_id && AEL_Content::COURSE !== get_post_type( $course_id ) ) {
			$course_id = 0;
		}
		update_post_meta( $post_id, self::META_COURSE, $course_id );
		update_post_meta( $post_id, self::META_PASS, isset( $_POST['ael_quiz_pass'] ) ? max( 0, min( 100, absint( wp_unslash( $_POST['ael_quiz_pass'] ) ) ) ) : 60 );
	}

	/**
	 * Carica l'editor delle domande solo nella schermata del quiz.
	 *
	 * @param string $hook Pagina di amministrazione.
	 */
	public function admin_assets( $hook ): void {
		$screen = get_current_screen();
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || self::TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'ael-quiz-admin', plugins_url( 'assets/quiz-admin.js', __FILE__ ), array( 'jquery' ), AEL_VERSION, true );
	}
}
