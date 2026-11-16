<?php
/**
 * Registro vendite e quote docenti: dati e calcoli.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ascolta gli incassi annunciati dai moduli di pagamento (ael_payment_recorded e
 * ael_payment_refunded) e tiene il registro. Non incassa e non paga nessuno: conta.
 *
 * Importi in centesimi. La quota è calcolata sul netto, cioè lordo meno IVA e
 * commissioni. La commissione è quella indicata dal gateway; se non è nota vale 0
 * e si può correggere a mano dal registro. La percentuale applicata si salva con la
 * vendita: cambiarla dopo non modifica le vendite già registrate.
 *
 * Gli abbonamenti non generano quote docenti (decisione del progetto): qui si
 * registrano solo gli acquisti di un corso.
 */
final class AEL_Teacher_Revenue {

	public const STATUS_OPEN     = 'da_liquidare';
	public const STATUS_SETTLED  = 'liquidata';
	public const STATUS_REVERSED = 'stornata';

	public const OPTION_DEFAULT_PERCENT = 'ael_revenue_default_percent';
	public const META_PERCENT           = '_ael_revenue_percent';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Teacher_Revenue|null
	 */
	private static ?AEL_Teacher_Revenue $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Teacher_Revenue {
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
	 * Registra la tabella e ascolta pagamenti e rimborsi.
	 */
	public function boot(): void {
		add_filter( 'ael_migrations', array( $this, 'add_migration' ) );
		add_action( 'ael_payment_recorded', array( $this, 'on_payment' ), 10, 1 );
		add_action( 'ael_payment_refunded', array( $this, 'on_refund' ), 10, 1 );
	}

	/**
	 * Aggiunge la migrazione della tabella.
	 *
	 * @param array<string, callable> $migrations Migrazioni.
	 * @return array<string, callable>
	 */
	public function add_migration( $migrations ) {
		$migrations['teacher-revenue-001-sales'] = array( __CLASS__, 'create_table' );
		return $migrations;
	}

	/**
	 * Nome completo della tabella.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ael_sales';
	}

	/**
	 * Crea la tabella del registro vendite.
	 */
	public static function create_table(): bool {
		$sql = 'CREATE TABLE {table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  paid_at datetime NOT NULL,
  gateway varchar(20) NOT NULL,
  ref varchar(100) NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  course_id bigint(20) unsigned NOT NULL DEFAULT 0,
  teacher_id bigint(20) unsigned NOT NULL DEFAULT 0,
  currency char(3) NOT NULL DEFAULT \'EUR\',
  gross int(11) NOT NULL DEFAULT 0,
  vat int(11) NOT NULL DEFAULT 0,
  fee int(11) NOT NULL DEFAULT 0,
  net int(11) NOT NULL DEFAULT 0,
  refunded int(11) NOT NULL DEFAULT 0,
  percent decimal(5,2) NOT NULL DEFAULT 0,
  quota int(11) NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT \'da_liquidare\',
  settled_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_sale (gateway,ref,course_id),
  KEY teacher_date (teacher_id,paid_at),
  KEY status (status)
) {charset};';
		return AEL_Installer::create_table( 'ael_sales', $sql );
	}

	/* ------------------------------------------------------------------ */
	/* Calcoli                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Percentuale predefinita (0-100).
	 */
	public static function default_percent(): float {
		return self::clamp_percent( get_option( self::OPTION_DEFAULT_PERCENT, 0 ) );
	}

	/**
	 * Porta un valore a una percentuale valida (0-100, due decimali).
	 *
	 * @param mixed $value Valore.
	 */
	public static function clamp_percent( $value ): float {
		if ( is_string( $value ) ) {
			$value = str_replace( ',', '.', trim( $value ) );
		}
		return round( max( 0.0, min( 100.0, (float) $value ) ), 2 );
	}

	/**
	 * Percentuale da applicare: eccezione del corso, poi quella del docente, poi la predefinita.
	 *
	 * @param int $course_id  Corso.
	 * @param int $teacher_id Docente (0 = nessuno).
	 */
	public function percent_for( int $course_id, int $teacher_id ): float {
		if ( ! $teacher_id ) {
			return 0.0;
		}
		$course = get_post_meta( $course_id, self::META_PERCENT, true );
		if ( '' !== (string) $course ) {
			return self::clamp_percent( $course );
		}
		$teacher = get_post_meta( $teacher_id, self::META_PERCENT, true );
		if ( '' !== (string) $teacher ) {
			return self::clamp_percent( $teacher );
		}
		return self::default_percent();
	}

	/**
	 * Quota in centesimi. Funzione pura.
	 *
	 * Con un rimborso parziale il netto si riduce nella stessa proporzione del lordo
	 * rimborsato; con un rimborso totale la quota è zero.
	 *
	 * @param int   $gross    Lordo.
	 * @param int   $net      Netto (lordo - IVA - commissione).
	 * @param int   $refunded Lordo rimborsato.
	 * @param float $percent  Percentuale del docente (0-100).
	 */
	public static function compute_quota( int $gross, int $net, int $refunded, float $percent ): int {
		if ( $gross > 0 && $refunded >= $gross ) {
			return 0;
		}
		$effective = ( $gross > 0 && $refunded > 0 ) ? $net * ( 1 - $refunded / $gross ) : $net;
		return (int) round( max( 0, $effective ) * $percent / 100 );
	}

	/* ------------------------------------------------------------------ */
	/* Ascolto di pagamenti e rimborsi                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Registra la vendita di un corso. Lo stesso incasso ricevuto due volte non si duplica.
	 *
	 * @param array<string, mixed> $payment Pagamento (da AEL_Payments).
	 */
	public function on_payment( $payment ): void {
		global $wpdb;

		if ( ! is_array( $payment ) || AEL_Payments::TYPE_PURCHASE !== ( $payment['type'] ?? '' ) || empty( $payment['course_id'] ) ) {
			return;
		}
		$course_id  = (int) $payment['course_id'];
		$teacher_id = (int) get_post_meta( $course_id, '_ael_teacher_id', true );
		$percent    = $this->percent_for( $course_id, $teacher_id );
		$quota      = self::compute_quota( (int) $payment['gross'], (int) $payment['net'], 0, $percent );

		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (paid_at, gateway, ref, user_id, course_id, teacher_id, currency, gross, vat, fee, net, refunded, percent, quota, status, created_at) VALUES (%s, %s, %s, %d, %d, %d, %s, %d, %d, %d, %d, 0, %f, %d, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$payment['paid_at'],
				$payment['gateway'],
				$payment['ref'],
				(int) $payment['user_id'],
				$course_id,
				$teacher_id,
				$payment['currency'],
				(int) $payment['gross'],
				(int) $payment['vat'],
				(int) $payment['fee'],
				(int) $payment['net'],
				$percent,
				$quota,
				self::STATUS_OPEN,
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		if ( $wpdb->insert_id ) {
			/**
			 * Scatta quando una vendita entra nel registro (non per i duplicati).
			 *
			 * @param int $sale_id ID della vendita nel registro.
			 */
			do_action( 'ael_sale_logged', (int) $wpdb->insert_id );
		}
	}

	/**
	 * Applica un rimborso: ricalcola la quota e, se è totale, storna la vendita.
	 *
	 * @param array<string, mixed> $refund Rimborso (da AEL_Payments).
	 */
	public function on_refund( $refund ): void {
		global $wpdb;

		if ( ! is_array( $refund ) || empty( $refund['gateway'] ) || empty( $refund['ref'] ) ) {
			return;
		}
		$table = self::table();
		$where = 'gateway = %s AND ref = %s';
		$args  = array( $refund['gateway'], $refund['ref'] );
		if ( ! empty( $refund['course_id'] ) ) {
			$where .= ' AND course_id = %d';
			$args[] = (int) $refund['course_id'];
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where}", $args ), ARRAY_A );
		if ( empty( $rows ) ) {
			return;
		}

		// Più corsi nello stesso incasso senza indicazione del corso: il rimborso si divide in proporzione al lordo.
		$total_gross = array_sum( array_map( 'intval', wp_list_pluck( $rows, 'gross' ) ) );
		$refunded    = max( 0, (int) ( $refund['refunded'] ?? 0 ) );

		foreach ( $rows as $row ) {
			$share = ( 1 === count( $rows ) || $total_gross <= 0 ) ? $refunded : (int) round( $refunded * (int) $row['gross'] / $total_gross );
			$this->apply_refund_to_row( $row, min( $share, (int) $row['gross'] ) );
		}
	}

	/**
	 * Aggiorna una vendita con l'importo totale rimborsato.
	 *
	 * @param array<string, mixed> $row      Riga del registro.
	 * @param int                  $refunded Totale rimborsato (lordo).
	 */
	private function apply_refund_to_row( array $row, int $refunded ): void {
		global $wpdb;

		$gross  = (int) $row['gross'];
		$quota  = self::compute_quota( $gross, (int) $row['net'], $refunded, (float) $row['percent'] );
		$status = ( $gross > 0 && $refunded >= $gross ) ? self::STATUS_REVERSED : (string) $row['status'];
		if ( self::STATUS_REVERSED === $row['status'] && $refunded < $gross ) {
			// Un rimborso che si riduce (raro) riporta la vendita nello stato di prima.
			$status = ! empty( $row['settled_at'] ) ? self::STATUS_SETTLED : self::STATUS_OPEN;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			self::table(),
			array(
				'refunded' => $refunded,
				'quota'    => $quota,
				'status'   => $status,
			),
			array( 'id' => (int) $row['id'] ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Correzioni e liquidazione                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Corregge la commissione di una vendita e ricalcola netto e quota.
	 *
	 * @param int $sale_id Vendita.
	 * @param int $fee     Commissione in centesimi.
	 */
	public function set_fee( int $sale_id, int $fee ): bool {
		global $wpdb;

		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $sale_id ), ARRAY_A );
		if ( ! $row ) {
			return false;
		}
		$fee   = min( max( 0, $fee ), max( 0, (int) $row['gross'] - (int) $row['vat'] ) );
		$net   = max( 0, (int) $row['gross'] - (int) $row['vat'] - $fee );
		$quota = self::compute_quota( (int) $row['gross'], $net, (int) $row['refunded'], (float) $row['percent'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'fee'   => $fee,
				'net'   => $net,
				'quota' => $quota,
			),
			array( 'id' => $sale_id ),
			array( '%d', '%d', '%d' ),
			array( '%d' )
		);
		return true;
	}

	/**
	 * Segna come liquidate le vendite "da liquidare" indicate.
	 *
	 * @param int[] $sale_ids ID delle vendite.
	 * @return int Quante ne ha cambiate.
	 */
	public function mark_settled( array $sale_ids ): int {
		global $wpdb;

		$sale_ids = array_values( array_filter( array_map( 'intval', $sale_ids ) ) );
		if ( empty( $sale_ids ) ) {
			return 0;
		}
		$table = self::table();
		$in    = implode( ',', array_fill( 0, count( $sale_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s, settled_at = %s WHERE status = %s AND id IN ({$in})", array_merge( array( self::STATUS_SETTLED, gmdate( 'Y-m-d H:i:s' ), self::STATUS_OPEN ), $sale_ids ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Letture                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Condizioni e parametri SQL per i filtri.
	 *
	 * @param array<string, mixed> $filters from/to (Y-m-d, data del sito), teacher_id, status.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function where( array $filters ): array {
		$where = array( '1=1' );
		$args  = array();
		if ( ! empty( $filters['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $filters['from'] ) ) {
			$where[] = 'paid_at >= %s';
			$args[]  = get_gmt_from_date( $filters['from'] . ' 00:00:00' );
		}
		if ( ! empty( $filters['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $filters['to'] ) ) {
			$where[] = 'paid_at <= %s';
			$args[]  = get_gmt_from_date( $filters['to'] . ' 23:59:59' );
		}
		if ( isset( $filters['teacher_id'] ) && '' !== $filters['teacher_id'] && null !== $filters['teacher_id'] ) {
			$where[] = 'teacher_id = %d';
			$args[]  = (int) $filters['teacher_id'];
		}
		if ( ! empty( $filters['status'] ) && in_array( $filters['status'], array( self::STATUS_OPEN, self::STATUS_SETTLED, self::STATUS_REVERSED ), true ) ) {
			$where[] = 'status = %s';
			$args[]  = (string) $filters['status'];
		}
		return array( implode( ' AND ', $where ), $args );
	}

	/**
	 * Vendite del registro, dalla più recente.
	 *
	 * @param array<string, mixed> $filters Filtri (vedi where()).
	 * @param int                  $limit   Quante righe (0 = tutte).
	 * @param int                  $offset  Da quale riga.
	 * @return array<int, array<string, mixed>>
	 */
	public function sales( array $filters = array(), int $limit = 50, int $offset = 0 ): array {
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
	 * Quante vendite corrispondono ai filtri.
	 *
	 * @param array<string, mixed> $filters Filtri.
	 */
	public function count_sales( array $filters = array() ): int {
		global $wpdb;

		list( $where, $args ) = $this->where( $filters );
		$table                = self::table();
		$sql                  = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( empty( $args ) ? $sql : $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * Riepilogo per docente: numero vendite, lordo, netto e quote per stato.
	 *
	 * @param array<string, mixed> $filters Filtri.
	 * @return array<int, array<string, int>> Docente (0 = senza docente) => totali.
	 */
	public function totals_by_teacher( array $filters = array() ): array {
		global $wpdb;

		list( $where, $args ) = $this->where( $filters );
		$table                = self::table();
		$open                 = self::STATUS_OPEN;
		$settled              = self::STATUS_SETTLED;
		$reversed             = self::STATUS_REVERSED;
		$sql                  = "SELECT teacher_id, COUNT(*) AS sales, SUM(gross) AS gross, SUM(net) AS net, SUM(IF(status = '{$open}', quota, 0)) AS quota_open, SUM(IF(status = '{$settled}', quota, 0)) AS quota_settled, SUM(status = '{$reversed}') AS reversed FROM {$table} WHERE {$where} GROUP BY teacher_id ORDER BY quota_open DESC";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( empty( $args ) ? $sql : $wpdb->prepare( $sql, $args ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['teacher_id'] ] = array(
				'sales'         => (int) $row['sales'],
				'gross'         => (int) $row['gross'],
				'net'           => (int) $row['net'],
				'quota_open'    => (int) $row['quota_open'],
				'quota_settled' => (int) $row['quota_settled'],
				'reversed'      => (int) $row['reversed'],
			);
		}
		return $out;
	}
}
