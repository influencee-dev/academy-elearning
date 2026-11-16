<?php
/**
 * Progressi delle lezioni e tempo di visione.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registra a che punto è ogni studente: lezione vista, completata, posizione
 * del video e secondi guardati per giorno. Non decide chi può vedere cosa
 * (lo fa AEL_Access): i controlli di accesso sono nell'endpoint REST.
 */
final class AEL_Progress {

	/**
	 * Righe di avanzamento già lette in questa richiesta, per utente e corso.
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private static array $cache = array();

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Progress|null
	 */
	private static ?AEL_Progress $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Progress {
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
	 * Collega i progressi a "I miei corsi" e alla ripresa dall'ultima lezione.
	 */
	public function boot(): void {
		add_filter( 'ael_course_progress', array( $this, 'filter_progress' ), 10, 3 );
		add_filter( 'ael_course_resume', array( $this, 'filter_resume' ), 10, 3 );
	}

	/**
	 * Percentuale di completamento da mostrare in "I miei corsi".
	 *
	 * @param int|null $percent   Valore precedente.
	 * @param int      $course_id Corso.
	 * @param int      $user_id   Utente.
	 */
	public function filter_progress( $percent, $course_id, $user_id ) {
		$value = self::percent( (int) $user_id, (int) $course_id );
		return null === $value ? $percent : $value;
	}

	/**
	 * Fa ripartire lo studente dall'ultima lezione guardata. Se l'aveva finita,
	 * dalla prossima non completata.
	 *
	 * @param array $resume    lesson_id e started.
	 * @param int   $course_id Corso.
	 * @param int   $user_id   Utente.
	 * @return array
	 */
	public function filter_resume( $resume, $course_id, $user_id ) {
		$user_id   = (int) $user_id;
		$course_id = (int) $course_id;
		if ( ! $user_id || ! is_array( $resume ) ) {
			return $resume;
		}
		$rows = self::rows( $user_id, $course_id );
		if ( empty( $rows ) ) {
			return $resume;
		}

		$status = array();
		foreach ( $rows as $row ) {
			$status[ (int) $row['lesson_id'] ] = $row['status'];
		}
		$flat = array_values(
			array_filter(
				AEL_Catalog::flat_lessons( AEL_Catalog::program( $course_id, $user_id ) ),
				static function ( array $lesson ): bool {
					return (bool) $lesson['can'];
				}
			)
		);
		if ( empty( $flat ) ) {
			return $resume;
		}

		$resume['started'] = true;

		// L'ultima lezione toccata (le righe sono già dalla più recente).
		$last_index = null;
		foreach ( $rows as $row ) {
			foreach ( $flat as $index => $lesson ) {
				if ( (int) $lesson['id'] === (int) $row['lesson_id'] ) {
					$last_index = $index;
					break 2;
				}
			}
		}
		if ( null === $last_index ) {
			return $resume;
		}

		$last_id = (int) $flat[ $last_index ]['id'];
		if ( 'completed' !== ( $status[ $last_id ] ?? '' ) ) {
			$resume['lesson_id'] = $last_id;
			return $resume;
		}

		// Finita: la prossima non completata dopo di lei, altrimenti la prima non completata.
		$order = array_merge( array_slice( $flat, $last_index + 1 ), array_slice( $flat, 0, $last_index ) );
		foreach ( $order as $lesson ) {
			if ( 'completed' !== ( $status[ (int) $lesson['id'] ] ?? '' ) ) {
				$resume['lesson_id'] = (int) $lesson['id'];
				return $resume;
			}
		}
		// Corso finito: si può rivedere dall'inizio.
		$resume['lesson_id'] = (int) $flat[0]['id'];
		return $resume;
	}

	/* ------------------------------------------------------------------ */
	/* Lettura                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Righe di avanzamento di un utente in un corso, dalla più recente.
	 *
	 * @param int $user_id   Utente.
	 * @param int $course_id Corso.
	 * @return array<int, array<string, mixed>>
	 */
	public static function rows( int $user_id, int $course_id ): array {
		global $wpdb;

		$key = $user_id . ':' . $course_id;
		if ( ! isset( self::$cache[ $key ] ) ) {
			$table = $wpdb->prefix . 'ael_progress';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT lesson_id, status, last_position, completed_at, last_seen_at FROM {$table} WHERE user_id = %d AND course_id = %d ORDER BY last_seen_at DESC, id DESC", $user_id, $course_id ), ARRAY_A );
			self::$cache[ $key ] = is_array( $rows ) ? $rows : array();
		}
		return self::$cache[ $key ];
	}

	/**
	 * Riga di una lezione per un utente, o null.
	 *
	 * @param int $user_id   Utente.
	 * @param int $lesson_id Lezione.
	 * @return array<string, mixed>|null
	 */
	public static function lesson_row( int $user_id, int $lesson_id ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'ael_progress';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT lesson_id, course_id, status, last_position, completed_at, last_seen_at FROM {$table} WHERE user_id = %d AND lesson_id = %d", $user_id, $lesson_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * ID delle lezioni completate di un corso.
	 *
	 * @param int $user_id   Utente.
	 * @param int $course_id Corso.
	 * @return int[]
	 */
	public static function completed_ids( int $user_id, int $course_id ): array {
		$ids = array();
		foreach ( self::rows( $user_id, $course_id ) as $row ) {
			if ( 'completed' === $row['status'] ) {
				$ids[] = (int) $row['lesson_id'];
			}
		}
		return $ids;
	}

	/**
	 * Percentuale di lezioni pubblicate completate (0-100). Null se il corso
	 * non ha lezioni o l'utente non è identificato.
	 *
	 * @param int $user_id   Utente.
	 * @param int $course_id Corso.
	 */
	public static function percent( int $user_id, int $course_id ): ?int {
		if ( ! $user_id ) {
			return null;
		}
		$ids = array_map(
			static function ( array $lesson ): int {
				return (int) $lesson['id'];
			},
			AEL_Catalog::flat_lessons( AEL_Catalog::program( $course_id, $user_id, false ) )
		);
		if ( empty( $ids ) ) {
			return null;
		}
		$done = count( array_intersect( $ids, self::completed_ids( $user_id, $course_id ) ) );
		return (int) round( $done / count( $ids ) * 100 );
	}

	/* ------------------------------------------------------------------ */
	/* Scrittura                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Corso di una lezione (copiato nella lezione quando si salva).
	 *
	 * @param int $lesson_id Lezione.
	 */
	private static function course_of( int $lesson_id ): int {
		return (int) get_post_meta( $lesson_id, '_ael_course_id', true );
	}

	/**
	 * Salva la posizione del video e i secondi guardati.
	 *
	 * I secondi accettati sono limitati al tempo realmente trascorso dall'ultimo
	 * salvataggio (più un piccolo margine), così un client malfunzionante o
	 * malevolo non può gonfiare i numeri.
	 *
	 * @param int $user_id   Utente.
	 * @param int $lesson_id Lezione.
	 * @param int $position  Posizione del video in secondi.
	 * @param int $seconds   Secondi guardati dall'ultimo invio.
	 */
	public static function record( int $user_id, int $lesson_id, int $position, int $seconds ): bool {
		global $wpdb;

		$course_id = self::course_of( $lesson_id );
		if ( ! $user_id || ! $lesson_id || ! $course_id ) {
			return false;
		}

		$previous = self::lesson_row( $user_id, $lesson_id );
		$now      = time();
		$limit    = 30;
		if ( $previous && ! empty( $previous['last_seen_at'] ) ) {
			$elapsed = $now - (int) strtotime( $previous['last_seen_at'] . ' UTC' );
			$limit   = max( 0, min( 120, $elapsed + 20 ) );
		}
		$seconds  = max( 0, min( $seconds, $limit ) );
		$position = max( 0, min( $position, 86400 ) );

		$progress = $wpdb->prefix . 'ael_progress';
		$seen     = gmdate( 'Y-m-d H:i:s', $now );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$progress} (user_id, course_id, lesson_id, status, last_position, last_seen_at) VALUES (%d, %d, %d, 'in_progress', %d, %s) ON DUPLICATE KEY UPDATE course_id = VALUES(course_id), last_position = VALUES(last_position), last_seen_at = VALUES(last_seen_at)", $user_id, $course_id, $lesson_id, $position, $seen ) );

		if ( $seconds > 0 ) {
			$watch = $wpdb->prefix . 'ael_watch_time';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$watch} (user_id, lesson_id, course_id, watched_on, seconds) VALUES (%d, %d, %d, %s, %d) ON DUPLICATE KEY UPDATE seconds = seconds + VALUES(seconds)", $user_id, $lesson_id, $course_id, gmdate( 'Y-m-d', $now ), $seconds ) );
		}

		self::flush( $user_id, $course_id );
		return true;
	}

	/**
	 * Segna una lezione come completata o da completare.
	 *
	 * @param int  $user_id   Utente.
	 * @param int  $lesson_id Lezione.
	 * @param bool $completed Completata.
	 */
	public static function set_completed( int $user_id, int $lesson_id, bool $completed ): bool {
		global $wpdb;

		$course_id = self::course_of( $lesson_id );
		if ( ! $user_id || ! $lesson_id || ! $course_id ) {
			return false;
		}

		$was_done        = in_array( $lesson_id, self::completed_ids( $user_id, $course_id ), true );
		$course_was_done = 100 === self::percent( $user_id, $course_id );
		$table           = $wpdb->prefix . 'ael_progress';
		$now             = gmdate( 'Y-m-d H:i:s' );

		if ( $completed ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (user_id, course_id, lesson_id, status, last_position, completed_at, last_seen_at) VALUES (%d, %d, %d, 'completed', 0, %s, %s) ON DUPLICATE KEY UPDATE course_id = VALUES(course_id), status = 'completed', completed_at = IF(completed_at IS NULL, VALUES(completed_at), completed_at), last_seen_at = VALUES(last_seen_at)", $user_id, $course_id, $lesson_id, $now, $now ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'status'       => 'in_progress',
					'completed_at' => null,
				),
				array(
					'user_id'   => $user_id,
					'lesson_id' => $lesson_id,
				),
				array( '%s', '%s' ),
				array( '%d', '%d' )
			);
		}

		self::flush( $user_id, $course_id );

		if ( $completed && ! $was_done ) {
			/**
			 * Scatta quando una lezione passa da non completata a completata (due
			 * volte solo se lo studente la annulla e la completa di nuovo).
			 *
			 * @param int $user_id   Utente.
			 * @param int $lesson_id Lezione.
			 * @param int $course_id Corso.
			 */
			do_action( 'ael_lesson_completed', $user_id, $lesson_id, $course_id );

			if ( ! $course_was_done && 100 === self::percent( $user_id, $course_id ) ) {
				/**
				 * Scatta quando uno studente completa tutte le lezioni di un corso.
				 *
				 * @param int $user_id   Utente.
				 * @param int $course_id Corso.
				 */
				do_action( 'ael_course_completed', $user_id, $course_id );
			}
		}
		return true;
	}

	/**
	 * Svuota le cache della richiesta dopo una scrittura.
	 *
	 * @param int $user_id   Utente.
	 * @param int $course_id Corso.
	 */
	private static function flush( int $user_id, int $course_id ): void {
		unset( self::$cache[ $user_id . ':' . $course_id ] );
		AEL_Catalog::flush_cache();
	}
}
