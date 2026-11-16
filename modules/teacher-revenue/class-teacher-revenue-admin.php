<?php
/**
 * Pagina "Vendite e quote" e campi della percentuale.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Solo per il titolare (capacità ael_manage_academy).
 */
final class AEL_Teacher_Revenue_Admin {

	public const PAGE = 'ael-sales';

	private const NONCE_FIELD  = 'ael_revenue_nonce';
	private const NONCE_ACTION = 'ael_revenue_save_percent';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Teacher_Revenue_Admin|null
	 */
	private static ?AEL_Teacher_Revenue_Admin $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Teacher_Revenue_Admin {
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
	 * Aggancia menu, azioni, campi e riquadro della Panoramica.
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_ael_sales_settle', array( $this, 'handle_settle' ) );
		add_action( 'admin_post_ael_sales_fee', array( $this, 'handle_fee' ) );
		add_action( 'admin_post_ael_sales_percent', array( $this, 'handle_default_percent' ) );
		add_action( 'admin_post_ael_sales_export', array( $this, 'handle_export' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post_' . AEL_Content::TEACHER, array( $this, 'save_percent' ), 10, 2 );
		add_action( 'save_post_' . AEL_Content::COURSE, array( $this, 'save_percent' ), 10, 2 );
		add_action( 'ael_overview_sections', array( $this, 'overview_box' ) );
	}

	/**
	 * Voce di menu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			AEL_Admin::MENU,
			__( 'Vendite e quote', 'academy-elearning' ),
			__( 'Vendite e quote', 'academy-elearning' ),
			AEL_Content::CAP,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Stile delle pagine dell'academy.
	 */
	public function enqueue(): void {
		// Solo per scegliere dove caricare lo stile.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && self::PAGE === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			wp_enqueue_style( 'ael-admin-dashboard', AEL_URL . 'assets/admin-dashboard.css', array(), AEL_VERSION );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Utilità                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Controllo di permesso comune alle azioni.
	 */
	private function require_manager(): void {
		if ( ! current_user_can( AEL_Content::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per fare questa operazione.', 'academy-elearning' ), 403 );
		}
	}

	/**
	 * Euro scritti dall'utente ("12,50") in centesimi.
	 *
	 * @param string $text Testo.
	 */
	private function to_cents( string $text ): int {
		return (int) round( (float) str_replace( ',', '.', trim( $text ) ) * 100 );
	}

	/**
	 * Filtri dall'indirizzo (solo lettura).
	 *
	 * @return array<string, mixed>
	 */
	private function filters_from_request(): array {
		// Filtri di sola lettura: non modificano dati.
		// phpcs:disable WordPress.Security.NonceVerification
		$source  = $_REQUEST;
		$filters = array(
			'from'       => isset( $source['from'] ) ? sanitize_text_field( wp_unslash( $source['from'] ) ) : '',
			'to'         => isset( $source['to'] ) ? sanitize_text_field( wp_unslash( $source['to'] ) ) : '',
			'teacher_id' => ( isset( $source['teacher'] ) && '' !== $source['teacher'] ) ? absint( wp_unslash( $source['teacher'] ) ) : '',
			'status'     => isset( $source['status'] ) ? sanitize_key( wp_unslash( $source['status'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification
		return $filters;
	}

	/**
	 * Stato scritto per esteso.
	 *
	 * @return array<string, string>
	 */
	private function status_labels(): array {
		return array(
			AEL_Teacher_Revenue::STATUS_OPEN     => __( 'Da liquidare', 'academy-elearning' ),
			AEL_Teacher_Revenue::STATUS_SETTLED  => __( 'Liquidata', 'academy-elearning' ),
			AEL_Teacher_Revenue::STATUS_REVERSED => __( 'Stornata', 'academy-elearning' ),
		);
	}

	/**
	 * Nome del docente di una vendita.
	 *
	 * @param int $teacher_id Docente.
	 */
	private function teacher_name( int $teacher_id ): string {
		return ( $teacher_id && get_post( $teacher_id ) ) ? get_the_title( $teacher_id ) : __( '(senza docente)', 'academy-elearning' );
	}

	/**
	 * Torna alla pagina con un messaggio.
	 *
	 * @param string               $message Codice del messaggio.
	 * @param array<string, mixed> $filters Filtri da mantenere.
	 */
	private function back( string $message, array $filters = array() ): void {
		$args = array_filter(
			array(
				'page'    => self::PAGE,
				'from'    => $filters['from'] ?? '',
				'to'      => $filters['to'] ?? '',
				'teacher' => $filters['teacher_id'] ?? '',
				'status'  => $filters['status'] ?? '',
				'ael_msg' => $message,
			),
			static function ( $value ): bool {
				return '' !== $value && null !== $value;
			}
		);
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Pagina                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Disegna la pagina.
	 */
	public function render(): void {
		if ( ! current_user_can( AEL_Content::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per vedere questa pagina.', 'academy-elearning' ), 403 );
		}

		$revenue  = AEL_Teacher_Revenue::instance();
		$filters  = $this->filters_from_request();
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = 50;
		$sales    = $revenue->sales( $filters, $per_page, ( $paged - 1 ) * $per_page );
		$total    = $revenue->count_sales( $filters );
		$totals   = $revenue->totals_by_teacher( $filters );
		$statuses = $this->status_labels();
		$teachers = get_posts(
			array(
				'post_type'   => AEL_Content::TEACHER,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$messages = array(
			'percent' => __( 'Percentuale predefinita salvata.', 'academy-elearning' ),
			'fee'     => __( 'Commissione aggiornata: netto e quota ricalcolati.', 'academy-elearning' ),
			'settled' => __( 'Vendite segnate come liquidate.', 'academy-elearning' ),
			'none'    => __( 'Nessuna vendita da liquidare con questi filtri.', 'academy-elearning' ),
		);
		// Solo il codice di un messaggio da una lista fissa.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = isset( $_GET['ael_msg'] ) ? sanitize_key( wp_unslash( $_GET['ael_msg'] ) ) : '';

		$export_url = wp_nonce_url(
			add_query_arg(
				array_filter(
					array(
						'action'  => 'ael_sales_export',
						'from'    => $filters['from'],
						'to'      => $filters['to'],
						'teacher' => $filters['teacher_id'],
						'status'  => $filters['status'],
					),
					static function ( $value ): bool {
						return '' !== $value;
					}
				),
				admin_url( 'admin-post.php' )
			),
			'ael_sales_export'
		);
		?>
		<div class="wrap ael-admin">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Vendite e quote', 'academy-elearning' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( $export_url ); ?>"><?php echo esc_html__( 'Esporta CSV', 'academy-elearning' ); ?></a>
			<hr class="wp-header-end">

			<?php if ( isset( $messages[ $msg ] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $messages[ $msg ] ); ?></p></div>
			<?php endif; ?>

			<div class="ael-box">
				<h2><?php echo esc_html__( 'Percentuale predefinita', 'academy-elearning' ); ?></h2>
				<p class="description"><?php echo esc_html__( 'Quota del docente sull\'importo netto (senza IVA e commissioni). Si può cambiare per ogni docente e, come eccezione, per ogni corso, dalle loro schede. Chi non ha una percentuale propria usa questa.', 'academy-elearning' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ael-grant-form">
					<input type="hidden" name="action" value="ael_sales_percent">
					<?php wp_nonce_field( 'ael_sales_percent' ); ?>
					<label>
						<span><?php echo esc_html__( 'Percentuale (%)', 'academy-elearning' ); ?></span>
						<input type="number" name="percent" min="0" max="100" step="0.01" class="small-text" value="<?php echo esc_attr( (string) AEL_Teacher_Revenue::default_percent() ); ?>">
					</label>
					<?php submit_button( __( 'Salva', 'academy-elearning' ), 'secondary', 'submit', false ); ?>
				</form>
				<p class="description"><?php echo esc_html__( 'La percentuale si salva con ogni vendita: cambiarla qui non modifica le vendite già registrate.', 'academy-elearning' ); ?></p>
			</div>

			<form method="get" class="ael-search">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<label><span class="screen-reader-text"><?php echo esc_html__( 'Dal', 'academy-elearning' ); ?></span>
					<input type="date" name="from" value="<?php echo esc_attr( (string) $filters['from'] ); ?>"></label>
				<label><span class="screen-reader-text"><?php echo esc_html__( 'Al', 'academy-elearning' ); ?></span>
					<input type="date" name="to" value="<?php echo esc_attr( (string) $filters['to'] ); ?>"></label>
				<select name="teacher">
					<option value=""><?php echo esc_html__( 'Tutti i docenti', 'academy-elearning' ); ?></option>
					<option value="0" <?php selected( $filters['teacher_id'], 0 ); ?>><?php echo esc_html__( '(senza docente)', 'academy-elearning' ); ?></option>
					<?php foreach ( $teachers as $teacher ) : ?>
						<option value="<?php echo esc_attr( (string) $teacher->ID ); ?>" <?php selected( (string) $filters['teacher_id'], (string) $teacher->ID ); ?>><?php echo esc_html( get_the_title( $teacher ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="status">
					<option value=""><?php echo esc_html__( 'Tutti gli stati', 'academy-elearning' ); ?></option>
					<?php foreach ( $statuses as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Filtra', 'academy-elearning' ), 'secondary', '', false ); ?>
			</form>

			<h2><?php echo esc_html__( 'Riepilogo per docente', 'academy-elearning' ); ?></h2>
			<?php if ( empty( $totals ) ) : ?>
				<p><?php echo esc_html__( 'Nessuna vendita registrata con questi filtri.', 'academy-elearning' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php echo esc_html__( 'Docente', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Vendite', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Lordo', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Netto', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Quota da liquidare', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Quota liquidata', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Stornate', 'academy-elearning' ); ?></th>
						<th></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $totals as $teacher_id => $row ) : ?>
						<tr>
							<td><?php echo esc_html( $this->teacher_name( (int) $teacher_id ) ); ?></td>
							<td><?php echo esc_html( (string) $row['sales'] ); ?></td>
							<td><?php echo esc_html( AEL_Payments::format( $row['gross'] ) ); ?></td>
							<td><?php echo esc_html( AEL_Payments::format( $row['net'] ) ); ?></td>
							<td><strong><?php echo esc_html( AEL_Payments::format( $row['quota_open'] ) ); ?></strong></td>
							<td><?php echo esc_html( AEL_Payments::format( $row['quota_settled'] ) ); ?></td>
							<td><?php echo esc_html( (string) $row['reversed'] ); ?></td>
							<td>
								<?php if ( $row['quota_open'] > 0 && (int) $teacher_id > 0 ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Segnare come liquidate tutte le vendite da liquidare di questo docente con i filtri attuali? Il pagamento al docente resta da fare fuori dal plugin.', 'academy-elearning' ) ); ?>');">
										<input type="hidden" name="action" value="ael_sales_settle">
										<?php wp_nonce_field( 'ael_sales_settle' ); ?>
										<input type="hidden" name="from" value="<?php echo esc_attr( (string) $filters['from'] ); ?>">
										<input type="hidden" name="to" value="<?php echo esc_attr( (string) $filters['to'] ); ?>">
										<input type="hidden" name="teacher" value="<?php echo esc_attr( (string) $teacher_id ); ?>">
										<button type="submit" class="button button-small"><?php echo esc_html__( 'Segna come liquidate', 'academy-elearning' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Registro vendite', 'academy-elearning' ); ?></h2>
			<?php if ( empty( $sales ) ) : ?>
				<p><?php echo esc_html__( 'Nessuna vendita.', 'academy-elearning' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php echo esc_html__( 'Data', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Ordine', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Corso', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Docente', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Lordo', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'IVA', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Commissione', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Netto', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( '%', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Quota', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Stato', 'academy-elearning' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $sales as $sale ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'd/m/Y H:i', (int) strtotime( $sale['paid_at'] . ' UTC' ) ) ); ?></td>
							<td><code><?php echo esc_html( $sale['gateway'] . ' · ' . $sale['ref'] ); ?></code></td>
							<td><?php echo esc_html( get_post( (int) $sale['course_id'] ) ? get_the_title( (int) $sale['course_id'] ) : '#' . $sale['course_id'] ); ?></td>
							<td><?php echo esc_html( $this->teacher_name( (int) $sale['teacher_id'] ) ); ?></td>
							<td><?php echo esc_html( AEL_Payments::format( (int) $sale['gross'], $sale['currency'] ) ); ?>
								<?php if ( (int) $sale['refunded'] > 0 ) : ?>
									<br><span class="description"><?php echo esc_html( sprintf( /* translators: %s: importo rimborsato */ __( 'rimborsati %s', 'academy-elearning' ), AEL_Payments::format( (int) $sale['refunded'], $sale['currency'] ) ) ); ?></span>
								<?php endif; ?></td>
							<td><?php echo esc_html( AEL_Payments::format( (int) $sale['vat'], $sale['currency'] ) ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:4px">
									<input type="hidden" name="action" value="ael_sales_fee">
									<input type="hidden" name="sale_id" value="<?php echo esc_attr( (string) $sale['id'] ); ?>">
									<?php wp_nonce_field( 'ael_sales_fee_' . (int) $sale['id'] ); ?>
									<input type="text" name="fee" inputmode="decimal" style="width:70px" value="<?php echo esc_attr( number_format( (int) $sale['fee'] / 100, 2, ',', '' ) ); ?>" aria-label="<?php echo esc_attr__( 'Commissione in euro', 'academy-elearning' ); ?>">
									<button type="submit" class="button button-small"><?php echo esc_html__( 'Salva', 'academy-elearning' ); ?></button>
								</form>
							</td>
							<td><?php echo esc_html( AEL_Payments::format( (int) $sale['net'], $sale['currency'] ) ); ?></td>
							<td><?php echo esc_html( rtrim( rtrim( number_format( (float) $sale['percent'], 2, ',', '' ), '0' ), ',' ) ); ?></td>
							<td><strong><?php echo esc_html( AEL_Payments::format( (int) $sale['quota'], $sale['currency'] ) ); ?></strong></td>
							<td>
								<?php echo esc_html( $statuses[ $sale['status'] ] ?? $sale['status'] ); ?>
								<?php if ( AEL_Teacher_Revenue::STATUS_REVERSED === $sale['status'] && ! empty( $sale['settled_at'] ) ) : ?>
									<br><span class="description"><?php echo esc_html__( 'già liquidata: da recuperare', 'academy-elearning' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description"><?php echo esc_html__( 'La commissione è quella comunicata dal gateway; se non è nota vale 0 e si corregge qui.', 'academy-elearning' ); ?></p>
				<?php
				$pages = (int) ceil( $total / $per_page );
				if ( $pages > 1 ) {
					echo '<div class="tablenav"><div class="tablenav-pages">';
					echo wp_kses_post(
						(string) paginate_links(
							array(
								'base'    => add_query_arg( 'paged', '%#%' ),
								'format'  => '',
								'current' => $paged,
								'total'   => $pages,
							)
						)
					);
					echo '</div></div>';
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Azioni                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Salva la percentuale predefinita.
	 */
	public function handle_default_percent(): void {
		$this->require_manager();
		check_admin_referer( 'ael_sales_percent' );
		$percent = isset( $_POST['percent'] ) ? AEL_Teacher_Revenue::clamp_percent( sanitize_text_field( wp_unslash( $_POST['percent'] ) ) ) : 0.0;
		update_option( AEL_Teacher_Revenue::OPTION_DEFAULT_PERCENT, $percent );
		$this->back( 'percent' );
	}

	/**
	 * Corregge la commissione di una vendita.
	 */
	public function handle_fee(): void {
		$this->require_manager();
		$sale_id = isset( $_POST['sale_id'] ) ? absint( wp_unslash( $_POST['sale_id'] ) ) : 0;
		check_admin_referer( 'ael_sales_fee_' . $sale_id );
		$fee = isset( $_POST['fee'] ) ? $this->to_cents( sanitize_text_field( wp_unslash( $_POST['fee'] ) ) ) : 0;
		AEL_Teacher_Revenue::instance()->set_fee( $sale_id, $fee );
		$this->back( 'fee' );
	}

	/**
	 * Segna come liquidate le vendite da liquidare di un docente, secondo i filtri.
	 */
	public function handle_settle(): void {
		$this->require_manager();
		check_admin_referer( 'ael_sales_settle' );

		$filters           = $this->filters_from_request();
		$filters['status'] = AEL_Teacher_Revenue::STATUS_OPEN;
		if ( '' === $filters['teacher_id'] || 0 === (int) $filters['teacher_id'] ) {
			$this->back( 'none' );
		}
		$revenue = AEL_Teacher_Revenue::instance();
		$ids     = array_map( 'intval', wp_list_pluck( $revenue->sales( $filters, 0 ), 'id' ) );
		$changed = $revenue->mark_settled( $ids );

		$filters['status'] = '';
		$this->back( $changed > 0 ? 'settled' : 'none', $filters );
	}

	/**
	 * Esporta il registro in CSV, con gli stessi filtri della pagina.
	 */
	public function handle_export(): void {
		$this->require_manager();
		check_admin_referer( 'ael_sales_export' );

		$statuses = $this->status_labels();
		$sales    = AEL_Teacher_Revenue::instance()->sales( $this->filters_from_request(), 0 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="vendite-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, array( 'Data (UTC)', 'Gateway', 'Riferimento', 'Corso', 'Docente', 'Valuta', 'Lordo', 'IVA', 'Commissione', 'Netto', 'Rimborsato', 'Percentuale', 'Quota', 'Stato', 'Liquidata il (UTC)' ) );
		foreach ( $sales as $sale ) {
			$cells = array(
				$sale['paid_at'],
				$sale['gateway'],
				$sale['ref'],
				get_post( (int) $sale['course_id'] ) ? get_the_title( (int) $sale['course_id'] ) : '#' . $sale['course_id'],
				$this->teacher_name( (int) $sale['teacher_id'] ),
			);
			// Le celle di testo non devono poter diventare formule in Excel.
			foreach ( $cells as $index => $cell ) {
				$cell = (string) $cell;
				if ( '' !== $cell && in_array( $cell[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
					$cells[ $index ] = "'" . $cell;
				}
			}
			fputcsv(
				$out,
				array_merge(
					$cells,
					array(
						$sale['currency'],
						number_format( (int) $sale['gross'] / 100, 2, '.', '' ),
						number_format( (int) $sale['vat'] / 100, 2, '.', '' ),
						number_format( (int) $sale['fee'] / 100, 2, '.', '' ),
						number_format( (int) $sale['net'] / 100, 2, '.', '' ),
						number_format( (int) $sale['refunded'] / 100, 2, '.', '' ),
						number_format( (float) $sale['percent'], 2, '.', '' ),
						number_format( (int) $sale['quota'] / 100, 2, '.', '' ),
						$statuses[ $sale['status'] ] ?? $sale['status'],
						(string) $sale['settled_at'],
					)
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Campo "percentuale" su docente e corso                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Riquadri della percentuale (solo per il titolare).
	 */
	public function add_boxes(): void {
		if ( ! current_user_can( AEL_Content::CAP ) ) {
			return;
		}
		add_meta_box( 'ael_revenue_teacher', __( 'Quota del docente', 'academy-elearning' ), array( $this, 'box_teacher' ), AEL_Content::TEACHER, 'side', 'default' );
		add_meta_box( 'ael_revenue_course', __( 'Quota del docente su questo corso', 'academy-elearning' ), array( $this, 'box_course' ), AEL_Content::COURSE, 'side', 'default' );
	}

	/**
	 * Riquadro del docente.
	 *
	 * @param WP_Post $post Docente.
	 */
	public function box_teacher( WP_Post $post ): void {
		$this->percent_field( $post, __( 'Percentuale del docente (%)', 'academy-elearning' ), __( 'Vuoto = percentuale predefinita.', 'academy-elearning' ) );
	}

	/**
	 * Riquadro del corso.
	 *
	 * @param WP_Post $post Corso.
	 */
	public function box_course( WP_Post $post ): void {
		$this->percent_field( $post, __( 'Percentuale per questo corso (%)', 'academy-elearning' ), __( 'Vuoto = quella del docente (o la predefinita). Serve solo per le eccezioni.', 'academy-elearning' ) );
	}

	/**
	 * Campo percentuale.
	 *
	 * @param WP_Post $post  Contenuto.
	 * @param string  $label Etichetta.
	 * @param string  $help  Aiuto.
	 */
	private function percent_field( WP_Post $post, string $label, string $help ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, false );
		$value = get_post_meta( $post->ID, AEL_Teacher_Revenue::META_PERCENT, true );
		?>
		<p><label for="ael_revenue_percent"><strong><?php echo esc_html( $label ); ?></strong></label></p>
		<input type="number" id="ael_revenue_percent" name="ael_revenue_percent" min="0" max="100" step="0.01" style="width:100%" value="<?php echo esc_attr( '' === (string) $value ? '' : (string) AEL_Teacher_Revenue::clamp_percent( $value ) ); ?>" placeholder="<?php echo esc_attr( (string) AEL_Teacher_Revenue::default_percent() ); ?>">
		<p class="description"><?php echo esc_html( $help ); ?></p>
		<?php
	}

	/**
	 * Salva la percentuale di un docente o di un corso.
	 *
	 * @param int     $post_id Contenuto.
	 * @param WP_Post $post    Contenuto.
	 */
	public function save_percent( int $post_id, WP_Post $post ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id )
			|| ! AEL_Content::is_form_post( $post_id )
			|| ! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION )
			|| ! current_user_can( AEL_Content::CAP )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = isset( $_POST['ael_revenue_percent'] ) ? sanitize_text_field( wp_unslash( $_POST['ael_revenue_percent'] ) ) : '';
		if ( '' === trim( $raw ) ) {
			delete_post_meta( $post_id, AEL_Teacher_Revenue::META_PERCENT );
		} else {
			update_post_meta( $post_id, AEL_Teacher_Revenue::META_PERCENT, AEL_Teacher_Revenue::clamp_percent( $raw ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Panoramica                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Riquadro "Vendite e quote" nella Panoramica.
	 */
	public function overview_box(): void {
		$filters = array( 'from' => gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ) );
		$totals  = AEL_Teacher_Revenue::instance()->totals_by_teacher( $filters );
		$sum     = array(
			'sales' => 0,
			'gross' => 0,
			'net'   => 0,
			'open'  => 0,
		);
		foreach ( $totals as $row ) {
			$sum['sales'] += $row['sales'];
			$sum['gross'] += $row['gross'];
			$sum['net']   += $row['net'];
			$sum['open']  += $row['quota_open'];
		}
		?>
		<div class="ael-box">
			<h2><?php echo esc_html__( 'Vendite e quote (30 giorni)', 'academy-elearning' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: numero vendite, 2: lordo, 3: netto, 4: quote da liquidare */
						__( '%1$d vendite · lordo %2$s · netto %3$s · quote da liquidare %4$s', 'academy-elearning' ),
						$sum['sales'],
						AEL_Payments::format( $sum['gross'] ),
						AEL_Payments::format( $sum['net'] ),
						AEL_Payments::format( $sum['open'] )
					)
				);
				?>
			</p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>"><?php echo esc_html__( 'Apri il registro', 'academy-elearning' ); ?></a></p>
		</div>
		<?php
	}
}
