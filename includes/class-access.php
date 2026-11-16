<?php
/**
 * La logica di accesso: l'unico punto in cui si decide chi vede cosa.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Concessioni (tabella ael_grants) e decisione "questo utente può vedere
 * questo corso / questa lezione?". Nessun altro file prende questa decisione.
 *
 * I moduli non modificano questa classe: creano o revocano concessioni con
 * grant() e revoke(), oppure aggiungono regole con i filtri
 * ael_user_can_access_course e ael_user_can_access_lesson.
 */
final class AEL_Access {

	public const SOURCE_PURCHASE     = 'purchase';
	public const SOURCE_SUBSCRIPTION = 'subscription';
	public const SOURCE_MANUAL       = 'manual';

	/**
	 * Concessioni attive lette dal database, per utente (cache della richiesta).
	 *
	 * @var array<int, array<int, array<string, mixed>>>
	 */
	private static array $cache = array();

	/**
	 * Origini ammesse.
	 *
	 * @return string[]
	 */
	public static function sources(): array {
		return array( self::SOURCE_PURCHASE, self::SOURCE_SUBSCRIPTION, self::SOURCE_MANUAL );
	}

	/* ------------------------------------------------------------------ */
	/* Decisione                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Le concessioni coprono il corso? Funzione pura (niente database, niente
	 * WordPress): per questo si può provare da sola.
	 *
	 * Regole: una concessione sul corso lo copre sempre. Una concessione sul
	 * catalogo (course_id 0) copre ogni corso, tranne quelli esclusi
	 * dall'abbonamento quando la concessione è di tipo abbonamento.
	 * Contano solo le concessioni attive, già iniziate e non scadute.
	 *
	 * @param array<int, array<string, mixed>> $grants   Concessioni dell'utente.
	 * @param int                              $course_id Corso.
	 * @param bool                             $excluded  Il corso è escluso dall'abbonamento.
	 * @param int                              $now       Ora attuale (timestamp UTC).
	 */
	public static function grants_cover_course( array $grants, int $course_id, bool $excluded, int $now ): bool {
		foreach ( $grants as $grant ) {
			if ( 'active' !== ( $grant['status'] ?? 'active' ) ) {
				continue;
			}
			$starts = isset( $grant['starts_at'] ) && '' !== (string) $grant['starts_at'] ? strtotime( $grant['starts_at'] . ' UTC' ) : 0;
			if ( false !== $starts && $starts > $now ) {
				continue;
			}
			$ends = isset( $grant['ends_at'] ) && '' !== (string) $grant['ends_at'] ? strtotime( $grant['ends_at'] . ' UTC' ) : null;
			if ( null !== $ends && false !== $ends && $ends < $now ) {
				continue;
			}

			$granted = (int) ( $grant['course_id'] ?? 0 );
			if ( $granted === $course_id ) {
				return true;
			}
			if ( 0 === $granted ) {
				if ( $excluded && self::SOURCE_SUBSCRIPTION === ( $grant['source'] ?? '' ) ) {
					continue;
				}
				return true;
			}
		}
		return false;
	}

	/**
	 * L'utente può vedere il corso (contenuto delle lezioni incluso)?
	 *
	 * @param int $user_id   Utente (0 = visitatore).
	 * @param int $course_id Corso.
	 */
	public static function user_can_access_course( int $user_id, int $course_id ): bool {
		$allowed = false;

		if ( AEL_Content::COURSE === get_post_type( $course_id ) ) {
			if ( $user_id && user_can( $user_id, AEL_Content::CAP ) ) {
				// Chi gestisce l'academy vede tutto, anche le bozze.
				$allowed = true;
			} elseif ( 'publish' === get_post_status( $course_id ) && $user_id ) {
				$allowed = self::grants_cover_course(
					self::grants_of( $user_id ),
					$course_id,
					(bool) get_post_meta( $course_id, '_ael_excluded_from_subscription', true ),
					time()
				);
			}
		}

		/**
		 * Permette ai moduli di aggiungere o togliere accesso a un corso.
		 *
		 * @param bool $allowed   Decisione della base.
		 * @param int  $user_id   Utente (0 = visitatore).
		 * @param int  $course_id Corso.
		 */
		return (bool) apply_filters( 'ael_user_can_access_course', $allowed, $user_id, $course_id );
	}

	/**
	 * L'utente può vedere questa lezione (e quindi il suo video e i materiali)?
	 *
	 * Le anteprime gratuite sono visibili a tutti se l'opzione del core è accesa.
	 * Una lezione non pubblicata, o dentro un modulo o corso non pubblicato, è
	 * visibile solo a chi gestisce l'academy.
	 *
	 * @param int $user_id   Utente (0 = visitatore).
	 * @param int $lesson_id Lezione.
	 */
	public static function user_can_access_lesson( int $user_id, int $lesson_id ): bool {
		$allowed   = false;
		$course_id = 0;

		if ( AEL_Content::LESSON === get_post_type( $lesson_id ) ) {
			$course_id = (int) get_post_meta( $lesson_id, '_ael_course_id', true );
			$manager   = $user_id && user_can( $user_id, AEL_Content::CAP );

			if ( $manager ) {
				$allowed = true;
			} elseif ( self::is_published_chain( $lesson_id ) ) {
				if ( AEL_Settings::get( 'free_previews', 1 ) && get_post_meta( $lesson_id, '_ael_is_preview', true ) ) {
					$allowed = true;
				} elseif ( $course_id ) {
					$allowed = self::user_can_access_course( $user_id, $course_id );
				}
			}
		}

		/**
		 * Permette ai moduli di aggiungere o togliere accesso a una lezione.
		 *
		 * @param bool $allowed   Decisione della base.
		 * @param int  $user_id   Utente (0 = visitatore).
		 * @param int  $lesson_id Lezione.
		 * @param int  $course_id Corso della lezione (0 se non assegnata).
		 */
		return (bool) apply_filters( 'ael_user_can_access_lesson', $allowed, $user_id, $lesson_id, $course_id );
	}

	/**
	 * Scorciatoia per l'utente collegato.
	 *
	 * @param int $lesson_id Lezione.
	 */
	public static function current_user_can_access_lesson( int $lesson_id ): bool {
		return self::user_can_access_lesson( get_current_user_id(), $lesson_id );
	}

	/**
	 * Lezione, modulo e corso sono tutti pubblicati?
	 *
	 * @param int $lesson_id Lezione.
	 */
	private static function is_published_chain( int $lesson_id ): bool {
		if ( 'publish' !== get_post_status( $lesson_id ) ) {
			return false;
		}
		$module_id = (int) get_post_field( 'post_parent', $lesson_id );
		if ( ! $module_id || AEL_Content::MODULE !== get_post_type( $module_id ) || 'publish' !== get_post_status( $module_id ) ) {
			return false;
		}
		$course_id = (int) get_post_field( 'post_parent', $module_id );
		return $course_id && AEL_Content::COURSE === get_post_type( $course_id ) && 'publish' === get_post_status( $course_id );
	}

	/**
	 * Corsi pubblicati a cui l'utente ha accesso.
	 *
	 * @param int $user_id Utente.
	 * @return int[]
	 */
	public static function accessible_course_ids( int $user_id ): array {
		$ids = get_posts(
			array(
				'post_type'   => AEL_Content::COURSE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			if ( self::user_can_access_course( $user_id, (int) $id ) ) {
				$out[] = (int) $id;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Concessioni                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Concessioni attive di un utente (anche non ancora iniziate o scadute:
	 * le valuta grants_cover_course).
	 *
	 * @param int $user_id Utente.
	 * @return array<int, array<string, mixed>>
	 */
	public static function grants_of( int $user_id ): array {
		global $wpdb;

		if ( ! isset( self::$cache[ $user_id ] ) ) {
			$table = $wpdb->prefix . 'ael_grants';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, course_id, source, source_ref, status, starts_at, ends_at FROM {$table} WHERE user_id = %d AND status = 'active'", $user_id ), ARRAY_A );
			self::$cache[ $user_id ] = is_array( $rows ) ? $rows : array();
		}
		return self::$cache[ $user_id ];
	}

	/**
	 * Tutte le concessioni di un utente, di qualsiasi stato (per le schermate di amministrazione).
	 *
	 * @param int $user_id Utente.
	 * @return array<int, array<string, mixed>>
	 */
	public static function all_grants_of( int $user_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'ael_grants';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, course_id, source, source_ref, status, starts_at, ends_at FROM {$table} WHERE user_id = %d ORDER BY id DESC", $user_id ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Una concessione per ID, di qualsiasi stato (per le schermate di amministrazione).
	 *
	 * @param int $grant_id ID della concessione.
	 * @return array<string, mixed>|null
	 */
	public static function grant_by_id( int $grant_id ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'ael_grants';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, user_id, course_id, source, source_ref, status, starts_at, ends_at FROM {$table} WHERE id = %d", $grant_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Stato leggibile di una concessione: active, pending, expired o revoked.
	 * Funzione pura, come grants_cover_course.
	 *
	 * @param array<string, mixed> $grant Concessione.
	 * @param int                  $now   Ora attuale (timestamp UTC).
	 */
	public static function grant_state( array $grant, int $now ): string {
		if ( 'active' !== ( $grant['status'] ?? 'active' ) ) {
			return 'revoked';
		}
		$starts = isset( $grant['starts_at'] ) && '' !== (string) $grant['starts_at'] ? strtotime( $grant['starts_at'] . ' UTC' ) : 0;
		if ( false !== $starts && $starts > $now ) {
			return 'pending';
		}
		$ends = isset( $grant['ends_at'] ) && '' !== (string) $grant['ends_at'] ? strtotime( $grant['ends_at'] . ' UTC' ) : null;
		if ( null !== $ends && false !== $ends && $ends < $now ) {
			return 'expired';
		}
		return 'active';
	}

	/**
	 * Crea o riattiva una concessione. La stessa (origine, riferimento, utente,
	 * corso) non si duplica: se esiste viene riattivata e aggiornata, così un
	 * evento ricevuto due volte non produce effetti doppi.
	 *
	 * @param int         $user_id    Utente.
	 * @param int         $course_id  Corso, oppure 0 per tutto il catalogo.
	 * @param string      $source     purchase, subscription o manual.
	 * @param string      $source_ref Riferimento (per esempio l'ID ordine); vuoto per le concessioni manuali.
	 * @param string|null $ends_at    Fine (UTC, "Y-m-d H:i:s"); null = senza scadenza.
	 * @return int|false ID della concessione, false se i dati non sono validi.
	 */
	public static function grant( int $user_id, int $course_id, string $source, string $source_ref = '', ?string $ends_at = null ) {
		global $wpdb;

		if ( ! in_array( $source, self::sources(), true ) || ! get_userdata( $user_id ) ) {
			return false;
		}
		if ( $course_id && AEL_Content::COURSE !== get_post_type( $course_id ) ) {
			return false;
		}
		if ( null !== $ends_at && ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $ends_at ) ) {
			return false;
		}

		$table      = $wpdb->prefix . 'ael_grants';
		$source_ref = substr( $source_ref, 0, 100 );
		$now        = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE source = %s AND source_ref = %s AND user_id = %d AND course_id = %d", $source, $source_ref, $user_id, $course_id ) );

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'status'     => 'active',
					'ends_at'    => $ends_at,
					'updated_at' => $now,
				),
				array( 'id' => $existing ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
			$id = $existing;
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$table,
				array(
					'user_id'    => $user_id,
					'course_id'  => $course_id,
					'source'     => $source,
					'source_ref' => $source_ref,
					'status'     => 'active',
					'starts_at'  => $now,
					'ends_at'    => $ends_at,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			$id = (int) $wpdb->insert_id;
			if ( ! $id ) {
				return false;
			}
		}

		unset( self::$cache[ $user_id ] );

		/**
		 * Scatta quando una concessione viene creata o riattivata.
		 *
		 * @param int    $id         ID della concessione.
		 * @param int    $user_id    Utente.
		 * @param int    $course_id  Corso (0 = catalogo).
		 * @param string $source     Origine.
		 * @param string $source_ref Riferimento.
		 */
		do_action( 'ael_grant_saved', $id, $user_id, $course_id, $source, $source_ref );

		return $id;
	}

	/**
	 * Revoca le concessioni di una certa origine (e riferimento). Non le cancella:
	 * restano nel registro con stato "revoked".
	 *
	 * @param string   $source     Origine.
	 * @param string   $source_ref Riferimento.
	 * @param int|null $user_id    Limita a un utente (facoltativo).
	 * @param int|null $course_id  Limita a un corso (facoltativo).
	 * @return int Numero di concessioni revocate.
	 */
	public static function revoke( string $source, string $source_ref, ?int $user_id = null, ?int $course_id = null ): int {
		global $wpdb;

		if ( ! in_array( $source, self::sources(), true ) ) {
			return 0;
		}
		$table  = $wpdb->prefix . 'ael_grants';
		$where  = array( 'source = %s', 'source_ref = %s', "status = 'active'" );
		$params = array( $source, substr( $source_ref, 0, 100 ) );
		if ( null !== $user_id ) {
			$where[]  = 'user_id = %d';
			$params[] = $user_id;
		}
		if ( null !== $course_id ) {
			$where[]  = 'course_id = %d';
			$params[] = $course_id;
		}
		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$affected_users = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$table} WHERE {$where_sql}", $params ) );
		if ( empty( $affected_users ) ) {
			return 0;
		}

		array_unshift( $params, gmdate( 'Y-m-d H:i:s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'revoked', updated_at = %s WHERE {$where_sql}", $params ) );

		foreach ( $affected_users as $uid ) {
			unset( self::$cache[ (int) $uid ] );
		}

		/**
		 * Scatta dopo la revoca di concessioni.
		 *
		 * @param string   $source     Origine.
		 * @param string   $source_ref Riferimento.
		 * @param int[]    $users      Utenti interessati.
		 */
		do_action( 'ael_grant_revoked', $source, $source_ref, array_map( 'intval', $affected_users ) );

		return $count;
	}

	/**
	 * Svuota la cache della richiesta (per i test o dopo scritture dirette).
	 */
	public static function flush_cache(): void {
		self::$cache = array();
	}
}
