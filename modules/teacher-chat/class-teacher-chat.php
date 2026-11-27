<?php
/**
 * Chat con i formatori: conversazioni private tra abbonati e formatori.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Una conversazione ("thread") lega un abbonato a un formatore (la scheda docente collegata a un
 * utente WordPress con il campo _ael_user_id). Possono leggerla e scriverci SOLO quei due utenti:
 * non gli altri formatori e nemmeno il titolare. Il modulo non ha nessuna schermata di
 * amministrazione che mostri i messaggi. Le email di avviso non contengono il testo.
 */
final class AEL_Teacher_Chat {

	public const TABLE_THREADS  = 'ael_chat_threads';
	public const TABLE_MESSAGES = 'ael_chat_messages';
	public const OPT_RETENTION  = 'ael_chat_retention_days';
	public const OPT_PRIVACY    = 'ael_chat_privacy_text';
	public const CRON_HOOK      = 'ael_chat_cleanup';
	public const MAX_LENGTH     = 4000;

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Teacher_Chat|null
	 */
	private static ?AEL_Teacher_Chat $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Teacher_Chat {
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
	 * Aggancia pagina, tabelle, invio dei messaggi, pulizia e privacy.
	 */
	public function boot(): void {
		add_filter( 'ael_pages_definitions', array( $this, 'add_page' ) );
		add_filter( 'ael_migrations', array( $this, 'add_migrations' ) );
		add_filter( 'ael_template_path', array( $this, 'module_templates' ), 10, 2 );

		add_shortcode( 'ael_chat', array( $this, 'shortcode' ) );
		add_action( 'admin_post_ael_chat_start', array( $this, 'handle_start' ) );
		add_action( 'admin_post_ael_chat_send', array( $this, 'handle_send' ) );

		add_action( 'ael_module_activated', array( $this, 'on_activated' ) );
		add_action( 'ael_module_deactivated', array( $this, 'on_deactivated' ) );
		add_action( self::CRON_HOOK, array( $this, 'cleanup' ) );
		add_action( 'init', array( $this, 'ensure_cron' ), 20 );

		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_filter( 'option_page_capability_ael_chat', array( $this, 'settings_cap' ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Pagina, tabelle, template, pulizia                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * Aggiunge la pagina "Chat" all'elenco delle pagine di sistema.
	 *
	 * @param array $defs Pagine.
	 * @return array
	 */
	public function add_page( $defs ) {
		$defs['chat'] = array(
			'option'  => 'ael_page_chat',
			'title'   => 'Chat',
			'slug'    => 'chat',
			'content' => '[ael_chat]',
		);
		return $defs;
	}

	/**
	 * Migrazioni del modulo: due tabelle nuove e la pagina.
	 *
	 * @param array<string, callable> $migrations Migrazioni.
	 * @return array<string, callable>
	 */
	public function add_migrations( $migrations ) {
		$migrations['teacher-chat-001-threads']  = array( __CLASS__, 'create_threads' );
		$migrations['teacher-chat-002-messages'] = array( __CLASS__, 'create_messages' );
		$migrations['teacher-chat-003-page']     = array( 'AEL_Pages', 'setup' );
		return $migrations;
	}

	/**
	 * Nome completo della tabella delle conversazioni.
	 */
	public static function threads_table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_THREADS;
	}

	/**
	 * Nome completo della tabella dei messaggi.
	 */
	public static function messages_table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_MESSAGES;
	}

	/**
	 * Crea la tabella delle conversazioni.
	 */
	public static function create_threads(): bool {
		$sql = 'CREATE TABLE {table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  teacher_id bigint(20) unsigned NOT NULL,
  created_at datetime NOT NULL,
  last_message_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_teacher (user_id,teacher_id),
  KEY teacher (teacher_id)
) {charset};';
		return AEL_Installer::create_table( self::TABLE_THREADS, $sql );
	}

	/**
	 * Crea la tabella dei messaggi.
	 */
	public static function create_messages(): bool {
		$sql = 'CREATE TABLE {table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  thread_id bigint(20) unsigned NOT NULL,
  sender_id bigint(20) unsigned NOT NULL,
  body text NOT NULL,
  created_at datetime NOT NULL,
  read_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY thread (thread_id,id),
  KEY created (created_at)
) {charset};';
		return AEL_Installer::create_table( self::TABLE_MESSAGES, $sql );
	}

	/**
	 * I template del modulo vivono nella sua cartella; il tema può comunque sostituirli.
	 *
	 * @param string $path Percorso scelto.
	 * @param string $name Nome del template.
	 * @return string
	 */
	public function module_templates( $path, $name ) {
		if ( 'chat' === $name && ! is_readable( (string) $path ) ) {
			return __DIR__ . '/templates/chat.php';
		}
		return $path;
	}

	/**
	 * Quando il modulo viene acceso parte la pulizia giornaliera.
	 *
	 * @param string $slug Slug del modulo.
	 */
	public function on_activated( $slug ): void {
		if ( 'teacher-chat' === $slug ) {
			$this->ensure_cron();
		}
	}

	/**
	 * Quando il modulo viene spento la pulizia si ferma. I messaggi restano dove sono.
	 *
	 * @param string $slug Slug del modulo.
	 */
	public function on_deactivated( $slug ): void {
		if ( 'teacher-chat' === $slug ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Programma la pulizia giornaliera se manca.
	 */
	public function ensure_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Giorni di conservazione dei messaggi (0 = per sempre).
	 */
	public static function retention_days(): int {
		return max( 0, (int) get_option( self::OPT_RETENTION, 0 ) );
	}

	/**
	 * Cancella i messaggi più vecchi del periodo scelto e le conversazioni rimaste vuote.
	 */
	public function cleanup(): void {
		$days = self::retention_days();
		if ( $days <= 0 ) {
			return;
		}
		global $wpdb;
		$messages = self::messages_table();
		$threads  = self::threads_table();
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$messages} WHERE created_at < %s", $cutoff ) );
		$wpdb->query( "DELETE t FROM {$threads} t LEFT JOIN {$messages} m ON m.thread_id = t.id WHERE m.id IS NULL AND t.created_at < '" . esc_sql( $cutoff ) . "'" );
		// phpcs:enable
	}

	/* ------------------------------------------------------------------ */
	/* Chi è chi                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * L'utente è un abbonato (può avviare conversazioni)?
	 *
	 * @param int $user_id Utente.
	 */
	public static function is_subscriber( int $user_id ): bool {
		return AEL_Access::user_has_catalog_access( $user_id );
	}

	/**
	 * Utente WordPress collegato a una scheda formatore (0 se non collegato).
	 *
	 * @param int $teacher_id Scheda formatore.
	 */
	public static function teacher_user( int $teacher_id ): int {
		if ( AEL_Content::TEACHER !== get_post_type( $teacher_id ) ) {
			return 0;
		}
		$user_id = (int) get_post_meta( $teacher_id, '_ael_user_id', true );
		return ( $user_id && get_userdata( $user_id ) ) ? $user_id : 0;
	}

	/**
	 * Schede formatore collegate a un utente.
	 *
	 * @param int $user_id Utente.
	 * @return int[]
	 */
	public static function teacher_posts_of( int $user_id ): array {
		if ( ! $user_id ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'      => AEL_Content::TEACHER,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'meta_key'       => '_ael_user_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $user_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * Formatori con cui si può chattare: pubblicati e con un utente collegato.
	 *
	 * @return array<int, string> ID scheda => nome.
	 */
	public static function available_teachers(): array {
		$out = array();
		$all = get_posts(
			array(
				'post_type'      => AEL_Content::TEACHER,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		foreach ( $all as $teacher ) {
			if ( self::teacher_user( (int) $teacher->ID ) ) {
				$out[ (int) $teacher->ID ] = get_the_title( $teacher );
			}
		}
		return $out;
	}

	/**
	 * Una conversazione.
	 *
	 * @param int $thread_id Conversazione.
	 * @return array<string, mixed>|null
	 */
	public static function thread( int $thread_id ): ?array {
		global $wpdb;
		$table = self::threads_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $thread_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * L'utente può leggere e scrivere in questa conversazione?
	 * Solo i due interessati: l'abbonato (finché ha l'abbonamento) e il formatore collegato.
	 * Il titolare dell'academy non ha nessuna eccezione.
	 *
	 * @param array<string, mixed>|null $thread  Conversazione.
	 * @param int                       $user_id Utente.
	 */
	public static function can_access( ?array $thread, int $user_id ): bool {
		if ( ! $thread || ! $user_id ) {
			return false;
		}
		if ( (int) $thread['user_id'] === $user_id && self::is_subscriber( $user_id ) ) {
			return true;
		}
		return self::teacher_user( (int) $thread['teacher_id'] ) === $user_id;
	}

	/* ------------------------------------------------------------------ */
	/* Pagina pubblica                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Testo informativo sulla privacy, mostrato sotto la chat.
	 */
	public static function privacy_text(): string {
		$custom = trim( (string) get_option( self::OPT_PRIVACY, '' ) );
		if ( '' !== $custom ) {
			return $custom;
		}
		$text = __( 'I messaggi sono privati: li vedono solo tu e il formatore scelto, non gli altri formatori. Non scrivere dati che non vuoi condividere, come password o numeri di carte.', 'academy-elearning' );
		$days = self::retention_days();
		if ( $days > 0 ) {
			/* translators: %d: giorni di conservazione dei messaggi. */
			$text .= ' ' . sprintf( __( 'I messaggi vengono cancellati dopo %d giorni.', 'academy-elearning' ), $days );
		}
		return $text;
	}

	/**
	 * Shortcode [ael_chat]: elenco delle conversazioni e conversazione aperta.
	 */
	public function shortcode(): string {
		wp_enqueue_style( 'ael-front' );
		wp_enqueue_style( 'ael-sections', AEL_URL . 'assets/sections.css', array( 'ael-front' ), AEL_VERSION );
		wp_enqueue_script( 'ael-chat', plugins_url( 'assets/chat.js', __FILE__ ), array(), AEL_VERSION, true );

		$user_id    = get_current_user_id();
		$subscriber = $user_id && self::is_subscriber( $user_id );
		$my_posts   = self::teacher_posts_of( $user_id );
		$gate       = array(
			'logged_in'     => (bool) $user_id,
			'subscribe_url' => AEL_Pages::url( 'subscribe', home_url( '/' ) ),
			'login_url'     => AEL_Pages::url( 'login', wp_login_url() ),
			'section'       => __( 'La chat con i formatori', 'academy-elearning' ),
		);

		if ( ! $subscriber && empty( $my_posts ) ) {
			return AEL_Templates::render( 'chat', array( 'mode' => 'gate', 'gate' => $gate ) );
		}

		global $wpdb;
		$threads_t  = self::threads_table();
		$messages_t = self::messages_table();
		$where      = array();
		$params     = array();
		if ( $subscriber ) {
			$where[]  = 'user_id = %d';
			$params[] = $user_id;
		}
		if ( ! empty( $my_posts ) ) {
			$where[] = 'teacher_id IN (' . implode( ',', array_map( 'intval', $my_posts ) ) . ')';
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$sql  = "SELECT * FROM {$threads_t} WHERE " . implode( ' OR ', $where ) . ' ORDER BY last_message_at DESC LIMIT 100';
		$rows = $wpdb->get_results( empty( $params ) ? $sql : $wpdb->prepare( $sql, $params ), ARRAY_A );
		// phpcs:enable
		$rows = is_array( $rows ) ? $rows : array();

		$threads = array();
		foreach ( $rows as $row ) {
			$as_teacher = in_array( (int) $row['teacher_id'], $my_posts, true ) && (int) $row['user_id'] !== $user_id;
			$other      = $as_teacher ? get_userdata( (int) $row['user_id'] ) : null;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$unread = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$messages_t} WHERE thread_id = %d AND sender_id <> %d AND read_at IS NULL", (int) $row['id'], $user_id ) );
			$threads[] = array(
				'id'     => (int) $row['id'],
				'label'  => $as_teacher ? ( $other ? $other->display_name : __( 'Utente', 'academy-elearning' ) ) : get_the_title( (int) $row['teacher_id'] ),
				'role'   => $as_teacher ? 'teacher' : 'student',
				'unread' => $unread,
			);
		}

		// Conversazione aperta: lettura, il controllo è l'appartenenza.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_id = isset( $_GET['c'] ) ? absint( wp_unslash( $_GET['c'] ) ) : 0;
		$current    = null;
		if ( $current_id ) {
			$row = self::thread( $current_id );
			if ( self::can_access( $row, $user_id ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "UPDATE {$messages_t} SET read_at = %s WHERE thread_id = %d AND sender_id <> %d AND read_at IS NULL", current_time( 'mysql', true ), $current_id, $user_id ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$msgs = $wpdb->get_results( $wpdb->prepare( "SELECT id, sender_id, body, created_at FROM {$messages_t} WHERE thread_id = %d ORDER BY id ASC LIMIT 300", $current_id ), ARRAY_A );
				$as_teacher = (int) $row['user_id'] !== $user_id;
				$other      = $as_teacher ? get_userdata( (int) $row['user_id'] ) : null;
				$current    = array(
					'id'       => $current_id,
					'label'    => $as_teacher ? ( $other ? $other->display_name : __( 'Utente', 'academy-elearning' ) ) : get_the_title( (int) $row['teacher_id'] ),
					'messages' => array_map(
						static function ( $m ) use ( $user_id ) {
							return array(
								'mine' => (int) $m['sender_id'] === $user_id,
								'body' => (string) $m['body'],
								'time' => wp_date( get_option( 'date_format' ) . ' H:i', (int) strtotime( (string) $m['created_at'] . ' UTC' ) ),
							);
						},
						is_array( $msgs ) ? $msgs : array()
					),
				);
				foreach ( $threads as $i => $t ) {
					if ( $t['id'] === $current_id ) {
						$threads[ $i ]['unread'] = 0;
					}
				}
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = isset( $_GET['errore'] ) ? sanitize_key( wp_unslash( $_GET['errore'] ) ) : '';

		return AEL_Templates::render(
			'chat',
			array(
				'mode'       => 'chat',
				'gate'       => $gate,
				'threads'    => $threads,
				'current'    => $current,
				'teachers'   => $subscriber ? self::available_teachers() : array(),
				'subscriber' => $subscriber,
				'page_url'   => AEL_Pages::url( 'chat', home_url( '/' ) ),
				'privacy'    => self::privacy_text(),
				'error'      => $error,
				'messages'   => array(
					'vuoto'     => __( 'Scrivi un messaggio prima di inviare.', 'academy-elearning' ),
					'troppi'    => __( 'Stai scrivendo troppo in fretta: riprova tra qualche minuto.', 'academy-elearning' ),
					'formatore' => __( 'Questo formatore non è ancora disponibile in chat.', 'academy-elearning' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Azioni                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Indirizzo della chat con un parametro, dopo un'azione.
	 *
	 * @param array<string, mixed> $args Parametri.
	 */
	private static function back( array $args = array() ): string {
		return add_query_arg( $args, AEL_Pages::url( 'chat', home_url( '/' ) ) );
	}

	/**
	 * Avvia (o riapre) la conversazione con un formatore.
	 */
	public function handle_start(): void {
		check_admin_referer( 'ael_chat_start' );
		$user_id    = get_current_user_id();
		$teacher_id = isset( $_POST['teacher_id'] ) ? absint( wp_unslash( $_POST['teacher_id'] ) ) : 0;

		if ( ! $user_id || ! self::is_subscriber( $user_id ) ) {
			wp_die( esc_html__( 'La chat è riservata a chi ha l\'abbonamento.', 'academy-elearning' ), '', array( 'response' => 403 ) );
		}
		$teacher_user = self::teacher_user( $teacher_id );
		if ( ! $teacher_user || 'publish' !== get_post_status( $teacher_id ) || $teacher_user === $user_id ) {
			wp_safe_redirect( self::back( array( 'errore' => 'formatore' ) ) );
			exit;
		}

		global $wpdb;
		$table = self::threads_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id = %d AND teacher_id = %d", $user_id, $teacher_id ) );
		if ( ! $id ) {
			$now = current_time( 'mysql', true );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$table,
				array(
					'user_id'         => $user_id,
					'teacher_id'      => $teacher_id,
					'created_at'      => $now,
					'last_message_at' => $now,
				),
				array( '%d', '%d', '%s', '%s' )
			);
			$id = (int) $wpdb->insert_id;
		}
		wp_safe_redirect( self::back( array( 'c' => $id ) ) );
		exit;
	}

	/**
	 * Invia un messaggio.
	 */
	public function handle_send(): void {
		$thread_id = isset( $_POST['thread_id'] ) ? absint( wp_unslash( $_POST['thread_id'] ) ) : 0;
		check_admin_referer( 'ael_chat_send_' . $thread_id );

		$user_id = get_current_user_id();
		$thread  = self::thread( $thread_id );
		if ( ! self::can_access( $thread, $user_id ) ) {
			wp_die( esc_html__( 'Non hai accesso a questa conversazione.', 'academy-elearning' ), '', array( 'response' => 403 ) );
		}

		$body = isset( $_POST['body'] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) ) : '';
		$body = function_exists( 'mb_substr' ) ? mb_substr( $body, 0, self::MAX_LENGTH ) : substr( $body, 0, self::MAX_LENGTH );
		if ( '' === $body ) {
			wp_safe_redirect( self::back( array( 'c' => $thread_id, 'errore' => 'vuoto' ) ) );
			exit;
		}

		// Freno semplice: al massimo 15 messaggi ogni 10 minuti per utente.
		$key   = 'ael_chat_rl_' . $user_id;
		$count = (int) get_transient( $key );
		if ( $count >= 15 ) {
			wp_safe_redirect( self::back( array( 'c' => $thread_id, 'errore' => 'troppi' ) ) );
			exit;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );

		global $wpdb;
		$now = current_time( 'mysql', true );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			self::messages_table(),
			array(
				'thread_id'  => $thread_id,
				'sender_id'  => $user_id,
				'body'       => $body,
				'created_at' => $now,
			),
			array( '%d', '%d', '%s', '%s' )
		);
		$wpdb->update( self::threads_table(), array( 'last_message_at' => $now ), array( 'id' => $thread_id ), array( '%s' ), array( '%d' ) );
		// phpcs:enable

		$this->notify( $thread, $user_id );

		/**
		 * Scatta quando viene inviato un messaggio nella chat. Non riceve il testo.
		 *
		 * @param int $thread_id Conversazione.
		 * @param int $user_id   Chi ha scritto.
		 */
		do_action( 'ael_chat_message_sent', $thread_id, $user_id );

		wp_safe_redirect( self::back( array( 'c' => $thread_id ) ) . '#fine' );
		exit;
	}

	/**
	 * Avvisa l'altra persona con una email senza il testo del messaggio (al massimo una ogni 30 minuti).
	 *
	 * @param array<string, mixed> $thread    Conversazione.
	 * @param int                  $sender_id Chi ha scritto.
	 */
	private function notify( array $thread, int $sender_id ): void {
		$recipient_id = (int) $thread['user_id'] === $sender_id ? self::teacher_user( (int) $thread['teacher_id'] ) : (int) $thread['user_id'];
		$recipient    = $recipient_id ? get_userdata( $recipient_id ) : false;
		if ( ! $recipient ) {
			return;
		}
		$key = 'ael_chat_n_' . (int) $thread['id'] . '_' . $recipient_id;
		if ( get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, 30 * MINUTE_IN_SECONDS );

		$subject = sprintf(
			/* translators: %s: nome del sito. */
			__( '[%s] Hai un nuovo messaggio nella chat', 'academy-elearning' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$message = __( 'Hai ricevuto un nuovo messaggio. Per leggerlo e rispondere accedi alla chat:', 'academy-elearning' ) . "\n\n" . self::back( array( 'c' => (int) $thread['id'] ) ) . "\n\n" . __( 'Per la tua privacy il testo del messaggio non viene mai inviato per email.', 'academy-elearning' );
		wp_mail( $recipient->user_email, $subject, $message );
	}

	/* ------------------------------------------------------------------ */
	/* Privacy (esportazione e cancellazione dei dati personali)           */
	/* ------------------------------------------------------------------ */

	/**
	 * Registra l'esportazione dei messaggi di una persona.
	 *
	 * @param array $exporters Esportatori.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['ael-chat'] = array(
			'exporter_friendly_name' => __( 'Chat con i formatori', 'academy-elearning' ),
			'callback'               => array( $this, 'export_data' ),
		);
		return $exporters;
	}

	/**
	 * Registra la cancellazione dei messaggi di una persona.
	 *
	 * @param array $erasers Cancellatori.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['ael-chat'] = array(
			'eraser_friendly_name' => __( 'Chat con i formatori', 'academy-elearning' ),
			'callback'             => array( $this, 'erase_data' ),
		);
		return $erasers;
	}

	/**
	 * Esporta i messaggi inviati da una persona.
	 *
	 * @param string $email Email.
	 * @return array{data: array, done: bool}
	 */
	public function export_data( $email ): array {
		$user = get_user_by( 'email', (string) $email );
		$data = array();
		if ( $user ) {
			global $wpdb;
			$table = self::messages_table();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, thread_id, body, created_at FROM {$table} WHERE sender_id = %d ORDER BY id ASC", $user->ID ), ARRAY_A );
			foreach ( (array) $rows as $row ) {
				$data[] = array(
					'group_id'    => 'ael-chat',
					'group_label' => __( 'Chat con i formatori', 'academy-elearning' ),
					'item_id'     => 'ael-chat-' . $row['id'],
					'data'        => array(
						array( 'name' => __( 'Data', 'academy-elearning' ), 'value' => $row['created_at'] ),
						array( 'name' => __( 'Messaggio', 'academy-elearning' ), 'value' => $row['body'] ),
					),
				);
			}
		}
		return array( 'data' => $data, 'done' => true );
	}

	/**
	 * Cancella le conversazioni di una persona (come abbonato) e i suoi messaggi.
	 *
	 * @param string $email Email.
	 * @return array<string, mixed>
	 */
	public function erase_data( $email ): array {
		$user    = get_user_by( 'email', (string) $email );
		$removed = false;
		if ( $user ) {
			global $wpdb;
			$threads  = self::threads_table();
			$messages = self::messages_table();
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "DELETE m FROM {$messages} m INNER JOIN {$threads} t ON t.id = m.thread_id WHERE t.user_id = %d", $user->ID ) );
			$wpdb->query( $wpdb->prepare( "DELETE m FROM {$messages} m WHERE m.sender_id = %d", $user->ID ) );
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$threads} WHERE user_id = %d", $user->ID ) );
			// phpcs:enable
			$removed = (bool) $deleted;
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Impostazioni di privacy (solo agenzia)                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Voce di menu: la vede solo chi ha la capacità dell'agenzia.
	 */
	public function admin_menu(): void {
		add_submenu_page(
			'ael-academy',
			__( 'Chat: privacy', 'academy-elearning' ),
			__( 'Chat: privacy', 'academy-elearning' ),
			AEL_Settings::CAP,
			'ael-chat-privacy',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Permesso richiesto per salvare le impostazioni della chat.
	 */
	public function settings_cap(): string {
		return AEL_Settings::CAP;
	}

	/**
	 * Registra le due impostazioni.
	 */
	public function register_settings(): void {
		register_setting(
			'ael_chat',
			self::OPT_RETENTION,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);
		register_setting(
			'ael_chat',
			self::OPT_PRIVACY,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			)
		);
	}

	/**
	 * Disegna la pagina delle impostazioni.
	 */
	public function render_settings(): void {
		if ( ! current_user_can( AEL_Settings::CAP ) ) {
			wp_die( esc_html__( 'Non hai il permesso di vedere questa pagina.', 'academy-elearning' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Chat con i formatori: privacy', 'academy-elearning' ); ?></h1>
			<p><?php echo esc_html__( 'I messaggi li vedono solo l\'abbonato e il formatore scelto. In questa pagina non compare nessun messaggio.', 'academy-elearning' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'ael_chat' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ael-chat-days"><?php echo esc_html__( 'Conserva i messaggi per (giorni)', 'academy-elearning' ); ?></label></th>
						<td>
							<input id="ael-chat-days" type="number" min="0" name="<?php echo esc_attr( self::OPT_RETENTION ); ?>" value="<?php echo esc_attr( (string) self::retention_days() ); ?>" class="small-text">
							<p class="description"><?php echo esc_html__( '0 = per sempre. Oltre questo periodo i messaggi vengono cancellati ogni notte.', 'academy-elearning' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ael-chat-privacy"><?php echo esc_html__( 'Informativa sotto la chat', 'academy-elearning' ); ?></label></th>
						<td>
							<textarea id="ael-chat-privacy" name="<?php echo esc_attr( self::OPT_PRIVACY ); ?>" rows="4" class="large-text"><?php echo esc_textarea( (string) get_option( self::OPT_PRIVACY, '' ) ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'Vuoto = testo predefinito. Il testo legale definitivo lo decide il titolare con il suo consulente.', 'academy-elearning' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
