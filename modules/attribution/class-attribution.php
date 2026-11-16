<?php
/**
 * Attribuzione: cookie di provenienza e registro degli acquisti.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Come funziona.
 *
 * 1) Quando un visitatore arriva sul sito, e SOLO se ha dato il consenso (filtro
 *    `ael_has_tracking_consent`, di base negato), si salva un cookie di 90 giorni con
 *    due "tocchi": il primo contatto e l'ultimo contatto. Un tocco è l'insieme di
 *    UTM (source, medium, campaign, content, term), sito di provenienza e pagina di
 *    arrivo. Le visite dirette non cancellano l'ultimo contatto.
 *
 * 2) I pagamenti arrivano dai gateway (webhook), senza cookie. Per questo i moduli di
 *    pagamento, quando creano il checkout (nel browser dell'utente), chiedono i dati con
 *    il filtro `ael_checkout_attribution`, li conservano con l'ordine e li ripassano
 *    dentro `$payment['meta']` quando annunciano l'incasso con ael_payment_recorded:
 *      meta['attribution'] => array( 'first' => tocco, 'last' => tocco )
 *      meta['coupon']      => codice promozionale usato
 *      meta['heard_from']  => risposta a "Come ci hai conosciuto?"
 *    Questo modulo ascolta l'incasso e registra una riga per ogni acquisto.
 */
final class AEL_Attribution {

	public const COOKIE = 'ael_attr';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Attribution|null
	 */
	private static ?AEL_Attribution $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Attribution {
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
	 * Aggancia cookie, tabella, filtro per i checkout e registrazione degli incassi.
	 */
	public function boot(): void {
		add_filter( 'ael_migrations', array( $this, 'add_migration' ) );
		add_action( 'init', array( $this, 'capture' ), 5 );
		add_filter( 'ael_checkout_attribution', array( $this, 'checkout_data' ) );
		add_action( 'ael_payment_recorded', array( $this, 'on_payment' ), 20, 1 );
	}

	/**
	 * Aggiunge la migrazione della tabella.
	 *
	 * @param array<string, callable> $migrations Migrazioni.
	 * @return array<string, callable>
	 */
	public function add_migration( $migrations ) {
		$migrations['attribution-001-table'] = array( __CLASS__, 'create_table' );
		return $migrations;
	}

	/**
	 * Nome completo della tabella.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ael_attribution';
	}

	/**
	 * Crea la tabella delle provenienze.
	 */
	public static function create_table(): bool {
		$sql = 'CREATE TABLE {table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  gateway varchar(20) NOT NULL,
  ref varchar(100) NOT NULL,
  course_id bigint(20) unsigned NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  paid_at datetime NOT NULL,
  first_source varchar(100) NOT NULL DEFAULT \'\',
  first_medium varchar(100) NOT NULL DEFAULT \'\',
  first_campaign varchar(100) NOT NULL DEFAULT \'\',
  last_source varchar(100) NOT NULL DEFAULT \'\',
  last_medium varchar(100) NOT NULL DEFAULT \'\',
  last_campaign varchar(100) NOT NULL DEFAULT \'\',
  coupon varchar(100) NOT NULL DEFAULT \'\',
  heard_from varchar(200) NOT NULL DEFAULT \'\',
  touches longtext,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_payment (gateway,ref,course_id),
  KEY paid_at (paid_at)
) {charset};';
		return AEL_Installer::create_table( 'ael_attribution', $sql );
	}

	/* ------------------------------------------------------------------ */
	/* Consenso                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * L'utente ha dato il consenso al tracciamento?
	 *
	 * Di base NO: il cookie non si salva finché il banner dell'academy (o un
	 * suo filtro) non conferma il consenso. Si collega così, nel tema o in un
	 * plugin del sito: add_filter( 'ael_has_tracking_consent', fn() => ... );
	 */
	public static function has_consent(): bool {
		/**
		 * Dice se l'utente ha dato il consenso al cookie di provenienza.
		 *
		 * @param bool $consent Consenso (predefinito: falso).
		 */
		return (bool) apply_filters( 'ael_has_tracking_consent', false );
	}

	/* ------------------------------------------------------------------ */
	/* Tocchi                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Ripulisce un testo per il cookie: niente HTML, massimo 100 caratteri.
	 *
	 * @param mixed $value Valore.
	 */
	public static function clean( $value ): string {
		return mb_substr( trim( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ) ), 0, 100 );
	}

	/**
	 * Costruisce un tocco dalla richiesta. Funzione pura (si prova da sola).
	 *
	 * Un tocco "vero" ha UTM oppure un sito di provenienza diverso da quello dell'academy.
	 * Altrimenti è una visita diretta (null).
	 *
	 * @param array<string, mixed> $query     Parametri dell'indirizzo.
	 * @param string               $referrer  Indirizzo di provenienza (può essere vuoto).
	 * @param string               $home_host Dominio dell'academy.
	 * @param string               $path      Pagina di arrivo (senza parametri).
	 * @param string               $date      Data e ora UTC.
	 * @return array<string, string>|null
	 */
	public static function build_touch( array $query, string $referrer, string $home_host, string $path, string $date ): ?array {
		$touch = array(
			's' => self::clean( $query['utm_source'] ?? '' ),
			'm' => self::clean( $query['utm_medium'] ?? '' ),
			'c' => self::clean( $query['utm_campaign'] ?? '' ),
			'k' => self::clean( $query['utm_content'] ?? '' ),
			't' => self::clean( $query['utm_term'] ?? '' ),
			'r' => '',
			'p' => self::clean( $path ),
			'd' => $date,
		);

		$ref_host = '';
		if ( '' !== $referrer ) {
			$host     = wp_parse_url( $referrer, PHP_URL_HOST );
			$ref_host = is_string( $host ) ? strtolower( preg_replace( '/^www\./', '', $host ) ) : '';
			if ( '' !== $ref_host && $ref_host === strtolower( preg_replace( '/^www\./', '', $home_host ) ) ) {
				$ref_host = ''; // Navigazione interna.
			}
		}
		$touch['r'] = self::clean( $ref_host );

		$has_utm = '' !== $touch['s'] || '' !== $touch['m'] || '' !== $touch['c'];
		if ( ! $has_utm && '' === $touch['r'] ) {
			return null;
		}
		if ( '' === $touch['s'] ) {
			$touch['s'] = $touch['r'];
			$touch['m'] = '' === $touch['m'] ? 'referral' : $touch['m'];
		}
		return $touch;
	}

	/**
	 * Tocco di una visita diretta (nessuna UTM, nessun sito di provenienza).
	 *
	 * @param string $path Pagina di arrivo.
	 * @param string $date Data e ora UTC.
	 * @return array<string, string>
	 */
	public static function direct_touch( string $path, string $date ): array {
		return array(
			's' => '(direct)',
			'm' => '(none)',
			'c' => '',
			'k' => '',
			't' => '',
			'r' => '',
			'p' => self::clean( $path ),
			'd' => $date,
		);
	}

	/**
	 * Aggiorna i dati del cookie con una nuova visita. Funzione pura.
	 *
	 * Il primo contatto si fissa alla prima visita e non cambia più. L'ultimo
	 * contatto cambia solo se la visita ha un tocco "vero" (UTM o sito di provenienza).
	 *
	 * @param array<string, array<string, string>>|null $existing Dati attuali.
	 * @param array<string, string>|null                $touch    Tocco della visita (null = diretta).
	 * @param array<string, string>                     $direct   Tocco da usare alla prima visita diretta.
	 * @return array<string, array<string, string>>
	 */
	public static function merge( ?array $existing, ?array $touch, array $direct ): array {
		if ( empty( $existing['f'] ) ) {
			$first = $touch ?? $direct;
			return array(
				'f' => $first,
				'l' => $first,
			);
		}
		if ( null !== $touch ) {
			$existing['l'] = $touch;
		}
		return $existing;
	}

	/**
	 * Legge i dati dal valore del cookie. Restituisce null se non sono validi.
	 *
	 * @param string $raw Valore del cookie.
	 * @return array<string, array<string, string>>|null
	 */
	public static function decode( string $raw ): ?array {
		$raw = strtr( $raw, '-_', '+/' );
		$json = base64_decode( $raw, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $json || strlen( $json ) > 2000 ) {
			return null;
		}
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || empty( $data['f'] ) || ! is_array( $data['f'] ) || empty( $data['l'] ) || ! is_array( $data['l'] ) ) {
			return null;
		}
		$out = array();
		foreach ( array( 'f', 'l' ) as $key ) {
			$touch = array();
			foreach ( array( 's', 'm', 'c', 'k', 't', 'r', 'p', 'd' ) as $field ) {
				$touch[ $field ] = self::clean( $data[ $key ][ $field ] ?? '' );
			}
			$out[ $key ] = $touch;
		}
		return $out;
	}

	/**
	 * Scrive i dati nel valore del cookie.
	 *
	 * @param array<string, array<string, string>> $data Dati.
	 */
	public static function encode( array $data ): string {
		return rtrim( strtr( base64_encode( (string) wp_json_encode( $data ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Dati attuali del visitatore (dal cookie), se validi.
	 *
	 * @return array<string, array<string, string>>|null
	 */
	public function current(): ?array {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}
		return self::decode( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Cookie                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Salva o aggiorna il cookie del visitatore, se c'è il consenso.
	 */
	public function capture(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		if ( ! self::has_consent() || headers_sent() ) {
			return;
		}
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( '' === $agent || preg_match( '/bot|crawl|spider|slurp|preview|monitor/i', $agent ) ) {
			return;
		}

		// Sono solo parametri di una visita (UTM): si leggono e si ripuliscono, non si modificano dati.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$query = array();
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$query[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$uri      = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path     = (string) strtok( $uri, '?' );
		$home     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$now      = gmdate( 'Y-m-d H:i:s' );

		$existing = $this->current();
		$touch    = self::build_touch( $query, $referrer, $home, $path, $now );
		$merged   = self::merge( $existing, $touch, self::direct_touch( $path, $now ) );

		// Nulla da scrivere: il cookie c'è già e questa visita non cambia niente.
		if ( $existing && null === $touch ) {
			return;
		}

		/**
		 * Durata del cookie di provenienza, in giorni.
		 *
		 * @param int $days Giorni (predefinito 90).
		 */
		$days = max( 1, (int) apply_filters( 'ael_attribution_days', 90 ) );
		setcookie(
			self::COOKIE,
			self::encode( $merged ),
			array(
				'expires'  => time() + $days * DAY_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = self::encode( $merged );
	}

	/**
	 * Dati da conservare con un ordine quando si crea il checkout (nel browser
	 * dell'utente, dove il cookie è disponibile). Se non c'è consenso, nulla.
	 *
	 * @param array $data Dati già presenti.
	 * @return array
	 */
	public function checkout_data( $data ) {
		$current = self::has_consent() ? $this->current() : null;
		if ( ! $current ) {
			return is_array( $data ) ? $data : array();
		}
		return array(
			'first' => $current['f'],
			'last'  => $current['l'],
		);
	}

	/* ------------------------------------------------------------------ */
	/* Registro                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Registra la provenienza di un acquisto. Lo stesso incasso ricevuto due volte non si duplica.
	 *
	 * @param array<string, mixed> $payment Pagamento (da AEL_Payments).
	 */
	public function on_payment( $payment ): void {
		global $wpdb;

		if ( ! is_array( $payment ) || empty( $payment['meta'] ) || ! is_array( $payment['meta'] ) ) {
			return;
		}
		$meta       = $payment['meta'];
		$attr       = ( isset( $meta['attribution'] ) && is_array( $meta['attribution'] ) ) ? $meta['attribution'] : array();
		$coupon     = self::clean( $meta['coupon'] ?? '' );
		$heard_from = mb_substr( trim( sanitize_text_field( (string) ( $meta['heard_from'] ?? '' ) ) ), 0, 200 );
		// Niente da registrare se mancano sia la provenienza sia il resto.
		if ( empty( $attr ) && '' === $coupon && '' === $heard_from ) {
			return;
		}

		$first = $this->touch_fields( $attr['first'] ?? array() );
		$last  = $this->touch_fields( $attr['last'] ?? array() );

		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (gateway, ref, course_id, user_id, paid_at, first_source, first_medium, first_campaign, last_source, last_medium, last_campaign, coupon, heard_from, touches, created_at) VALUES (%s, %s, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$payment['gateway'],
				$payment['ref'],
				(int) $payment['course_id'],
				(int) $payment['user_id'],
				$payment['paid_at'],
				$first['s'],
				$first['m'],
				$first['c'],
				$last['s'],
				$last['m'],
				$last['c'],
				$coupon,
				$heard_from,
				(string) wp_json_encode(
					array(
						'first' => $first,
						'last'  => $last,
					)
				),
				gmdate( 'Y-m-d H:i:s' )
			)
		);
	}

	/**
	 * Ripulisce un tocco ricevuto dal modulo di pagamento.
	 *
	 * @param mixed $touch Tocco grezzo.
	 * @return array<string, string>
	 */
	private function touch_fields( $touch ): array {
		$out = array();
		foreach ( array( 's', 'm', 'c', 'k', 't', 'r', 'p', 'd' ) as $field ) {
			$out[ $field ] = self::clean( is_array( $touch ) ? ( $touch[ $field ] ?? '' ) : '' );
		}
		return $out;
	}

	/**
	 * Righe del registro, dalla più recente.
	 *
	 * @param array<string, string> $filters from, to (Y-m-d del sito).
	 * @param int                   $limit   Quante (0 = tutte).
	 * @param int                   $offset  Da quale.
	 * @return array<int, array<string, mixed>>
	 */
	public function rows( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		list( $where, $args ) = $this->where( $filters );
		$table                = self::table();
		$sql                  = "SELECT * FROM {$table} WHERE {$where} ORDER BY paid_at DESC, id DESC";
		if ( $limit > 0 ) {
			$sql   .= ' LIMIT %d OFFSET %d';
			$args[] = $limit;
			$args[] = max( 0, $offset );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( empty( $args ) ? $sql : $wpdb->prepare( $sql, $args ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Quante righe corrispondono ai filtri.
	 *
	 * @param array<string, string> $filters Filtri.
	 */
	public function count_rows( array $filters = array() ): int {
		global $wpdb;

		list( $where, $args ) = $this->where( $filters );
		$table                = self::table();
		$sql                  = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( empty( $args ) ? $sql : $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * Conteggi per un campo (ultimo contatto: sorgente, oppure la risposta "Come ci hai conosciuto?").
	 *
	 * @param string                $column  last_source, first_source o heard_from.
	 * @param array<string, string> $filters Filtri.
	 * @return array<string, int> Valore => numero di acquisti, dal più frequente.
	 */
	public function count_by( string $column, array $filters = array() ): array {
		global $wpdb;

		if ( ! in_array( $column, array( 'last_source', 'first_source', 'heard_from' ), true ) ) {
			return array();
		}
		list( $where, $args ) = $this->where( $filters );
		$table                = self::table();
		$sql                  = "SELECT {$column} AS value, COUNT(*) AS total FROM {$table} WHERE {$where} GROUP BY {$column} ORDER BY total DESC LIMIT 20";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( empty( $args ) ? $sql : $wpdb->prepare( $sql, $args ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ '' === (string) $row['value'] ? '—' : (string) $row['value'] ] = (int) $row['total'];
		}
		return $out;
	}

	/**
	 * Condizioni SQL dei filtri.
	 *
	 * @param array<string, string> $filters Filtri.
	 * @return array{0: string, 1: array<int, string>}
	 */
	private function where( array $filters ): array {
		$where = array( '1=1' );
		$args  = array();
		if ( ! empty( $filters['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['from'] ) ) {
			$where[] = 'paid_at >= %s';
			$args[]  = get_gmt_from_date( $filters['from'] . ' 00:00:00' );
		}
		if ( ! empty( $filters['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['to'] ) ) {
			$where[] = 'paid_at <= %s';
			$args[]  = get_gmt_from_date( $filters['to'] . ' 23:59:59' );
		}
		return array( implode( ' AND ', $where ), $args );
	}
}
