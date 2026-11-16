<?php
/**
 * Pagina "Provenienza" e esportazione.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Solo per il titolare (capacità ael_manage_academy).
 */
final class AEL_Attribution_Admin {

	public const PAGE = 'ael-attribution';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Attribution_Admin|null
	 */
	private static ?AEL_Attribution_Admin $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Attribution_Admin {
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
	 * Aggancia menu ed esportazione.
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_ael_attribution_export', array( $this, 'handle_export' ) );
	}

	/**
	 * Voce di menu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			AEL_Admin::MENU,
			__( 'Provenienza degli acquisti', 'academy-elearning' ),
			__( 'Provenienza', 'academy-elearning' ),
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

	/**
	 * Periodo scelto (solo lettura).
	 *
	 * @return array<string, string>
	 */
	private function filters(): array {
		// Filtri di sola lettura.
		// phpcs:disable WordPress.Security.NonceVerification
		return array(
			'from' => isset( $_REQUEST['from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ) : '',
			'to'   => isset( $_REQUEST['to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['to'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Un tocco scritto in una riga ("instagram / social / lancio").
	 *
	 * @param string $source   Sorgente.
	 * @param string $medium   Mezzo.
	 * @param string $campaign Campagna.
	 */
	private function touch_text( string $source, string $medium, string $campaign ): string {
		$parts = array_filter( array( $source, $medium, $campaign ), 'strlen' );
		return empty( $parts ) ? '—' : implode( ' / ', $parts );
	}

	/**
	 * Disegna la pagina.
	 */
	public function render(): void {
		if ( ! current_user_can( AEL_Content::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per vedere questa pagina.', 'academy-elearning' ), 403 );
		}

		$module   = AEL_Attribution::instance();
		$filters  = $this->filters();
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page = 50;
		$rows     = $module->rows( $filters, $per_page, ( $paged - 1 ) * $per_page );
		$total    = $module->count_rows( $filters );
		$export   = wp_nonce_url(
			add_query_arg(
				array_filter(
					array(
						'action' => 'ael_attribution_export',
						'from'   => $filters['from'],
						'to'     => $filters['to'],
					)
				),
				admin_url( 'admin-post.php' )
			),
			'ael_attribution_export'
		);
		$summary  = array(
			__( 'Ultimo contatto: sorgente', 'academy-elearning' ) => $module->count_by( 'last_source', $filters ),
			__( 'Primo contatto: sorgente', 'academy-elearning' )  => $module->count_by( 'first_source', $filters ),
			__( 'Come ci hai conosciuto?', 'academy-elearning' )   => $module->count_by( 'heard_from', $filters ),
		);
		?>
		<div class="wrap ael-admin">
			<h1 class="wp-heading-inline"><?php echo esc_html__( 'Provenienza degli acquisti', 'academy-elearning' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( $export ); ?>"><?php echo esc_html__( 'Esporta CSV', 'academy-elearning' ); ?></a>
			<hr class="wp-header-end">

			<?php if ( ! AEL_Attribution::has_consent() ) : ?>
				<div class="notice notice-warning"><p>
					<?php echo esc_html__( 'Il cookie di provenienza non si sta salvando: manca il collegamento con il consenso cookie del sito. Chi configura l\'academy deve collegarlo con il filtro ael_has_tracking_consent. Finché non c\'è consenso, qui compaiono solo i dati inviati dai moduli di pagamento (codice promozionale e "Come ci hai conosciuto?").', 'academy-elearning' ); ?>
				</p></div>
			<?php endif; ?>

			<form method="get" class="ael-search">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<label><span class="screen-reader-text"><?php echo esc_html__( 'Dal', 'academy-elearning' ); ?></span>
					<input type="date" name="from" value="<?php echo esc_attr( $filters['from'] ); ?>"></label>
				<label><span class="screen-reader-text"><?php echo esc_html__( 'Al', 'academy-elearning' ); ?></span>
					<input type="date" name="to" value="<?php echo esc_attr( $filters['to'] ); ?>"></label>
				<?php submit_button( __( 'Filtra', 'academy-elearning' ), 'secondary', '', false ); ?>
			</form>

			<div class="ael-stat-grid">
				<?php foreach ( $summary as $title => $counts ) : ?>
					<div class="ael-stat">
						<strong><?php echo esc_html( $title ); ?></strong>
						<?php if ( empty( $counts ) ) : ?>
							<p class="ael-stat-label"><?php echo esc_html__( 'Nessun dato', 'academy-elearning' ); ?></p>
						<?php else : ?>
							<ul>
								<?php foreach ( $counts as $value => $number ) : ?>
									<li><?php echo esc_html( $value . ': ' . $number ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( empty( $rows ) ) : ?>
				<p><?php echo esc_html__( 'Nessun acquisto con dati di provenienza nel periodo.', 'academy-elearning' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php echo esc_html__( 'Data', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Ordine', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Corso', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Primo contatto', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Ultimo contatto', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Codice promo', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Come ci hai conosciuto?', 'academy-elearning' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'd/m/Y H:i', (int) strtotime( $row['paid_at'] . ' UTC' ) ) ); ?></td>
							<td><code><?php echo esc_html( $row['gateway'] . ' · ' . $row['ref'] ); ?></code></td>
							<td><?php echo esc_html( ( (int) $row['course_id'] && get_post( (int) $row['course_id'] ) ) ? get_the_title( (int) $row['course_id'] ) : __( 'Abbonamento', 'academy-elearning' ) ); ?></td>
							<td><?php echo esc_html( $this->touch_text( $row['first_source'], $row['first_medium'], $row['first_campaign'] ) ); ?></td>
							<td><?php echo esc_html( $this->touch_text( $row['last_source'], $row['last_medium'], $row['last_campaign'] ) ); ?></td>
							<td><?php echo esc_html( '' !== $row['coupon'] ? $row['coupon'] : '—' ); ?></td>
							<td><?php echo esc_html( '' !== $row['heard_from'] ? $row['heard_from'] : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
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

	/**
	 * Protegge una cella da formule quando il CSV si apre in Excel.
	 *
	 * @param string $value Valore.
	 */
	private function csv_cell( string $value ): string {
		return ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $value : $value;
	}

	/**
	 * Esporta il registro in CSV.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( AEL_Content::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per fare questa operazione.', 'academy-elearning' ), 403 );
		}
		check_admin_referer( 'ael_attribution_export' );

		$rows = AEL_Attribution::instance()->rows( $this->filters(), 0 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="provenienza-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv(
			$out,
			array(
				'Data (UTC)',
				'Gateway',
				'Riferimento',
				'Corso',
				'Primo: sorgente',
				'Primo: mezzo',
				'Primo: campagna',
				'Primo: contenuto',
				'Primo: termine',
				'Primo: sito di provenienza',
				'Primo: pagina di arrivo',
				'Ultimo: sorgente',
				'Ultimo: mezzo',
				'Ultimo: campagna',
				'Ultimo: contenuto',
				'Ultimo: termine',
				'Ultimo: sito di provenienza',
				'Ultimo: pagina di arrivo',
				'Codice promo',
				'Come ci hai conosciuto',
			)
		);
		foreach ( $rows as $row ) {
			$touches = json_decode( (string) $row['touches'], true );
			$first   = is_array( $touches['first'] ?? null ) ? $touches['first'] : array();
			$last    = is_array( $touches['last'] ?? null ) ? $touches['last'] : array();
			$cells   = array(
				(string) $row['paid_at'],
				(string) $row['gateway'],
				(string) $row['ref'],
				( (int) $row['course_id'] && get_post( (int) $row['course_id'] ) ) ? get_the_title( (int) $row['course_id'] ) : 'Abbonamento',
			);
			foreach ( array( $first, $last ) as $touch ) {
				foreach ( array( 's', 'm', 'c', 'k', 't', 'r', 'p' ) as $field ) {
					$cells[] = (string) ( $touch[ $field ] ?? '' );
				}
			}
			$cells[] = (string) $row['coupon'];
			$cells[] = (string) $row['heard_from'];
			fputcsv( $out, array_map( array( $this, 'csv_cell' ), $cells ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
