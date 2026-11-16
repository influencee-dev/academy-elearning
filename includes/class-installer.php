<?php
/**
 * Tabelle e migrazioni versionate.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Crea le tabelle del core e lancia le migrazioni, anche quelle dei moduli.
 *
 * Regola dei dati: le migrazioni aggiungono, non tolgono. Una migrazione già
 * rilasciata non si modifica mai: se serve cambiare qualcosa se ne scrive una
 * nuova (per esempio con ALTER TABLE ... ADD COLUMN). Non si eliminano,
 * rinominano o cambiano di tipo tabelle e colonne esistenti.
 *
 * Il plugin si aggiorna caricando uno zip, quindi l'attivazione non scatta a
 * ogni versione: per questo le migrazioni in sospeso partono a ogni caricamento.
 */
final class AEL_Installer {

	/**
	 * Option con gli ID delle migrazioni già eseguite.
	 */
	public const OPTION_DONE = 'ael_migrations_done';

	/**
	 * Option con la versione del plugin all'ultima migrazione.
	 */
	public const OPTION_VERSION = 'ael_db_version';

	/**
	 * Option con i nomi (senza prefisso) di tutte le tabelle create.
	 */
	public const OPTION_TABLES = 'ael_tables';

	/**
	 * Option con l'ID dell'ultima migrazione fallita.
	 */
	public const OPTION_ERROR = 'ael_migration_error';

	/**
	 * Nomi delle tabelle del core, senza prefisso.
	 *
	 * @return string[]
	 */
	public static function core_tables(): array {
		return array( 'ael_grants', 'ael_progress', 'ael_watch_time' );
	}

	/**
	 * Elenco delle migrazioni: ID => callback. L'ordine è quello di esecuzione.
	 *
	 * Un modulo aggiunge le sue con il filtro ael_migrations, usando ID che
	 * iniziano con il proprio slug (per esempio teacher-revenue-001-sales).
	 *
	 * @return array<string, callable>
	 */
	public static function migrations(): array {
		$core = array(
			'core-001-grants'     => array( __CLASS__, 'create_grants' ),
			'core-002-progress'   => array( __CLASS__, 'create_progress' ),
			'core-003-watch-time' => array( __CLASS__, 'create_watch_time' ),
			'core-004-roles'      => array( 'AEL_Content', 'setup_roles' ),
			'core-005-pages'      => array( 'AEL_Catalog', 'setup_pages' ),
		);

		/**
		 * Permette ai moduli di registrare le proprie migrazioni.
		 *
		 * @param array<string, callable> $migrations ID => callback. La callback restituisce false se fallisce.
		 */
		$all = apply_filters( 'ael_migrations', $core );
		return is_array( $all ) ? $all : $core;
	}

	/**
	 * Lancia le migrazioni non ancora eseguite. Si ferma alla prima che fallisce.
	 */
	public static function run_pending(): void {
		$done = get_option( self::OPTION_DONE, array() );
		$done = is_array( $done ) ? $done : array();

		$pending = array_diff_key( self::migrations(), array_flip( $done ) );
		if ( empty( $pending ) ) {
			return;
		}

		foreach ( $pending as $id => $callback ) {
			if ( ! is_callable( $callback ) ) {
				continue;
			}
			$result = call_user_func( $callback );
			if ( false === $result ) {
				update_option( self::OPTION_ERROR, (string) $id );
				return;
			}
			$done[] = (string) $id;
			update_option( self::OPTION_DONE, $done );
		}

		delete_option( self::OPTION_ERROR );
		update_option( self::OPTION_VERSION, AEL_VERSION );
	}

	/**
	 * Registra il nome di una tabella (senza prefisso), per lo stato e per la
	 * cancellazione dei dati alla disinstallazione.
	 *
	 * @param string $name Nome senza prefisso, per esempio ael_grants.
	 */
	public static function register_table( string $name ): void {
		$tables = get_option( self::OPTION_TABLES, array() );
		$tables = is_array( $tables ) ? $tables : array();
		if ( ! in_array( $name, $tables, true ) ) {
			$tables[] = $name;
			update_option( self::OPTION_TABLES, $tables );
		}
	}

	/**
	 * Crea una tabella con dbDelta e la registra.
	 *
	 * Per i moduli: passare il nome senza prefisso e l'SQL con il segnaposto
	 * {table} al posto del nome completo e {charset} al posto del charset.
	 *
	 * @param string $name Nome senza prefisso.
	 * @param string $sql  Istruzione CREATE TABLE con {table} e {charset}.
	 * @return bool
	 */
	public static function create_table( string $name, string $sql ): bool {
		global $wpdb;

		if ( ! preg_match( '/^[a-z0-9_]+$/', $name ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = str_replace(
			array( '{table}', '{charset}' ),
			array( $wpdb->prefix . $name, $wpdb->get_charset_collate() ),
			$sql
		);
		dbDelta( $sql );

		if ( ! self::table_exists( $name ) ) {
			return false;
		}
		self::register_table( $name );
		return true;
	}

	/**
	 * La tabella esiste?
	 *
	 * @param string $name Nome senza prefisso.
	 */
	public static function table_exists( string $name ): bool {
		global $wpdb;
		$full = $wpdb->prefix . $name;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $full === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $full ) ) );
	}

	/**
	 * Stato delle tabelle note: nome => esiste.
	 *
	 * @return array<string, bool>
	 */
	public static function table_status(): array {
		$registered = get_option( self::OPTION_TABLES, array() );
		$registered = is_array( $registered ) ? $registered : array();
		$names      = array_values( array_unique( array_merge( self::core_tables(), $registered ) ) );

		$status = array();
		foreach ( $names as $name ) {
			$status[ $name ] = self::table_exists( $name );
		}
		return $status;
	}

	/**
	 * Concessioni di accesso: chi può vedere quale corso, e perché.
	 * course_id 0 significa "tutto il catalogo". ends_at vuoto = senza scadenza.
	 */
	public static function create_grants(): bool {
		$sql = 'CREATE TABLE {table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  course_id bigint(20) unsigned NOT NULL DEFAULT 0,
  source varchar(20) NOT NULL,
  source_ref varchar(100) NOT NULL DEFAULT \'\',
  status varchar(20) NOT NULL DEFAULT \'active\',
  starts_at datetime NOT NULL,
  ends_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_grant (source,source_ref,user_id,course_id),
  KEY user_course (user_id,course_id,status)
) {charset};';
		return self::create_table( 'ael_grants', $sql );
	}

	/**
	 * Progressi: lo stato di ogni lezione per ogni utente.
	 */
	public static function create_progress(): bool {
		$sql = 'CREATE TABLE {table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  course_id bigint(20) unsigned NOT NULL,
  lesson_id bigint(20) unsigned NOT NULL,
  status varchar(20) NOT NULL DEFAULT \'in_progress\',
  last_position int(10) unsigned NOT NULL DEFAULT 0,
  completed_at datetime DEFAULT NULL,
  last_seen_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_user_lesson (user_id,lesson_id),
  KEY user_course (user_id,course_id,last_seen_at)
) {charset};';
		return self::create_table( 'ael_progress', $sql );
	}

	/**
	 * Tempo di visione: secondi guardati per utente, lezione e giorno.
	 * Un record per giorno permette, in futuro, di dividere per periodo.
	 */
	public static function create_watch_time(): bool {
		$sql = 'CREATE TABLE {table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  lesson_id bigint(20) unsigned NOT NULL,
  course_id bigint(20) unsigned NOT NULL,
  watched_on date NOT NULL,
  seconds int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_user_lesson_day (user_id,lesson_id,watched_on),
  KEY course_day (course_id,watched_on)
) {charset};';
		return self::create_table( 'ael_watch_time', $sql );
	}
}
