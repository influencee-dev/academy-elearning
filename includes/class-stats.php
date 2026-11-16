<?php
/**
 * Numeri per le schermate di amministrazione (studenti e panoramica).
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Solo letture dal database: concessioni, progressi e tempo di visione.
 * Non prende decisioni di accesso (quelle sono di AEL_Access).
 */
final class AEL_Stats {

	/**
	 * Condizione SQL delle concessioni valide adesso.
	 * Si usa con due segnaposto %s (ora attuale UTC, due volte).
	 */
	private const ACTIVE_SQL = "status = 'active' AND starts_at <= %s AND (ends_at IS NULL OR ends_at >= %s)";

	/**
	 * Ora attuale in UTC, nel formato del database.
	 */
	private static function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Durata leggibile di un numero di secondi ("1 h 05 min", "12 min", "—").
	 *
	 * @param int $seconds Secondi.
	 */
	public static function format_seconds( int $seconds ): string {
		$minutes = intdiv( max( 0, $seconds ), 60 );
		if ( $minutes <= 0 ) {
			return $seconds > 0 ? __( 'meno di 1 min', 'academy-elearning' ) : '—';
		}
		return AEL_Catalog::format_minutes( $minutes );
	}

	/**
	 * ID degli utenti che hanno almeno una concessione o un avanzamento.
	 *
	 * @return int[]
	 */
	public static function candidate_student_ids(): array {
		global $wpdb;

		$grants   = $wpdb->prefix . 'ael_grants';
		$progress = $wpdb->prefix . 'ael_progress';
		// Nessun dato esterno nella query: niente da preparare.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( "SELECT user_id FROM {$grants} UNION SELECT user_id FROM {$progress}" );
		return array_values( array_filter( array_map( 'intval', is_array( $ids ) ? $ids : array() ) ) );
	}

	/**
	 * Riepilogo per gli utenti indicati: concessioni valide, lezioni
	 * completate, secondi guardati e ultima attività.
	 *
	 * @param int[] $user_ids Utenti.
	 * @return array<int, array{courses: int, catalog: bool, completed: int, seconds: int, last_seen: string}>
	 */
	public static function summaries( array $user_ids ): array {
		global $wpdb;

		$user_ids = array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) );
		$out      = array();
		foreach ( $user_ids as $id ) {
			$out[ $id ] = array(
				'courses'   => 0,
				'catalog'   => false,
				'completed' => 0,
				'seconds'   => 0,
				'last_seen' => '',
			);
		}
		if ( empty( $user_ids ) ) {
			return $out;
		}

		$in       = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		$now      = self::now();
		$grants   = $wpdb->prefix . 'ael_grants';
		$progress = $wpdb->prefix . 'ael_progress';
		$watch    = $wpdb->prefix . 'ael_watch_time';
		$active   = self::ACTIVE_SQL;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, SUM(course_id > 0) AS courses, MAX(course_id = 0) AS catalog FROM {$grants} WHERE user_id IN ({$in}) AND {$active} GROUP BY user_id", array_merge( $user_ids, array( $now, $now ) ) ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['user_id'] ]['courses'] = (int) $row['courses'];
			$out[ (int) $row['user_id'] ]['catalog'] = (bool) (int) $row['catalog'];
		}

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, SUM(status = 'completed') AS completed, MAX(last_seen_at) AS last_seen FROM {$progress} WHERE user_id IN ({$in}) GROUP BY user_id", $user_ids ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['user_id'] ]['completed'] = (int) $row['completed'];
			$out[ (int) $row['user_id'] ]['last_seen'] = (string) $row['last_seen'];
		}

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, SUM(seconds) AS seconds FROM {$watch} WHERE user_id IN ({$in}) GROUP BY user_id", $user_ids ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['user_id'] ]['seconds'] = (int) $row['seconds'];
		}
		// phpcs:enable

		return $out;
	}

	/**
	 * Secondi guardati da un utente, per corso.
	 *
	 * @param int $user_id Utente.
	 * @return array<int, int> ID corso => secondi.
	 */
	public static function watch_by_course( int $user_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'ael_watch_time';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT course_id, SUM(seconds) AS seconds FROM {$table} WHERE user_id = %d GROUP BY course_id", $user_id ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['course_id'] ] = (int) $row['seconds'];
		}
		return $out;
	}

	/**
	 * Secondi guardati da un utente negli ultimi giorni, dal più recente.
	 *
	 * @param int $user_id Utente.
	 * @param int $days    Quanti giorni mostrare.
	 * @return array<string, int> Data (Y-m-d) => secondi.
	 */
	public static function watch_by_day( int $user_id, int $days = 14 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'ael_watch_time';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT watched_on, SUM(seconds) AS seconds FROM {$table} WHERE user_id = %d GROUP BY watched_on ORDER BY watched_on DESC LIMIT %d", $user_id, max( 1, $days ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['watched_on'] ] = (int) $row['seconds'];
		}
		return $out;
	}

	/**
	 * Quanti utenti hanno un accesso valido adesso.
	 */
	public static function active_students_count(): int {
		global $wpdb;

		$now    = self::now();
		$table  = $wpdb->prefix . 'ael_grants';
		$active = self::ACTIVE_SQL;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE {$active}", $now, $now ) );
	}

	/**
	 * Concessioni valide adesso, per origine.
	 *
	 * @return array<string, int> Origine => numero.
	 */
	public static function active_grants_by_source(): array {
		global $wpdb;

		$now    = self::now();
		$table  = $wpdb->prefix . 'ael_grants';
		$active = self::ACTIVE_SQL;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT source, COUNT(*) AS total FROM {$table} WHERE {$active} GROUP BY source", $now, $now ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['source'] ] = (int) $row['total'];
		}
		return $out;
	}

	/**
	 * Lezioni completate negli ultimi giorni.
	 *
	 * @param int $days Giorni.
	 */
	public static function completed_since( int $days ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'ael_progress';
		$since = gmdate( 'Y-m-d H:i:s', time() - max( 1, $days ) * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'completed' AND completed_at >= %s", $since ) );
	}

	/**
	 * Secondi guardati da tutti, negli ultimi giorni.
	 *
	 * @param int $days Giorni.
	 */
	public static function watch_seconds_since( int $days ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'ael_watch_time';
		$since = gmdate( 'Y-m-d', time() - max( 1, $days ) * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(seconds), 0) FROM {$table} WHERE watched_on >= %s", $since ) );
	}

	/**
	 * Corsi più guardati negli ultimi giorni.
	 *
	 * @param int $days  Giorni.
	 * @param int $limit Quanti corsi.
	 * @return array<int, array{course_id: int, seconds: int, students: int}>
	 */
	public static function top_courses( int $days, int $limit = 5 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'ael_watch_time';
		$since = gmdate( 'Y-m-d', time() - max( 1, $days ) * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT course_id, SUM(seconds) AS seconds, COUNT(DISTINCT user_id) AS students FROM {$table} WHERE watched_on >= %s GROUP BY course_id ORDER BY seconds DESC LIMIT %d", $since, max( 1, $limit ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'course_id' => (int) $row['course_id'],
				'seconds'   => (int) $row['seconds'],
				'students'  => (int) $row['students'],
			);
		}
		return $out;
	}
}
