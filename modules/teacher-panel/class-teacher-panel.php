<?php
/**
 * Pannello del docente: le sue vendite e quote, in sola lettura.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Il docente (ruolo Docente, senza la capacità del titolare) vede una sola voce di
 * menu, "Le mie vendite". Il permesso `ael_view_own_sales` si concede al volo finché
 * il modulo è acceso: spegnerlo lo toglie, senza toccare i dati.
 *
 * Il docente vede solo le vendite dei corsi collegati alla propria scheda docente, e
 * di ogni vendita solo data, corso, importo netto, percentuale, quota e stato: mai
 * i dati dell'acquirente. Il filtro per docente non si legge dall'indirizzo: è sempre
 * il suo.
 */
final class AEL_Teacher_Panel {

	public const CAP  = 'ael_view_own_sales';
	public const PAGE = 'ael-my-sales';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Teacher_Panel|null
	 */
	private static ?AEL_Teacher_Panel $instance = null;

	/**
	 * Cache: utente => scheda docente.
	 *
	 * @var array<int, int>
	 */
	private array $profile_cache = array();

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Teacher_Panel {
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
	 * Aggancia permesso, menu, esportazione e accesso.
	 */
	public function boot(): void {
		add_filter( 'user_has_cap', array( $this, 'grant_cap' ), 15, 4 );
		add_action( 'admin_menu', array( $this, 'register_menu' ), 12 );
		add_action( 'admin_post_ael_my_sales_export', array( $this, 'handle_export' ) );
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 30, 3 );

		// Chi entra dal sito (login di WooCommerce, pagina Accedi, I miei corsi) trova la strada per il pannello.
		add_action( 'ael_my_courses_before', array( $this, 'front_link' ), 5 );
		add_action( 'ael_login_logged_in', array( $this, 'front_link' ) );
		if ( function_exists( 'WC' ) ) {
			add_filter( 'woocommerce_prevent_admin_access', array( $this, 'woo_allow_admin' ) );
			add_filter( 'woocommerce_login_redirect', array( $this, 'woo_login_redirect' ), 30, 2 );
			add_filter( 'woocommerce_account_menu_items', array( $this, 'woo_menu_item' ), 20 );
			add_filter( 'woocommerce_get_endpoint_url', array( $this, 'woo_menu_url' ), 10, 2 );
		}
	}

	/**
	 * Indirizzo del pannello nell'amministrazione.
	 */
	private function panel_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * Il docente corrente deve vedere il collegamento al pannello (solo se non scrive corsi: in quel caso
	 * i collegamenti li mostra il modulo Docenti autori)?
	 */
	private function show_for_current_user(): bool {
		return is_user_logged_in() && current_user_can( self::CAP );
	}

	/**
	 * WooCommerce rimanda a "Il mio account" chi entra nell'amministrazione senza poter modificare
	 * articoli: un docente col pannello deve poterci entrare.
	 *
	 * @param bool $prevent Blocco deciso da WooCommerce.
	 * @return bool
	 */
	public function woo_allow_admin( $prevent ) {
		return $this->show_for_current_user() ? false : $prevent;
	}

	/**
	 * Pulsante "Vai al pannello docente" nelle pagine del sito.
	 */
	public function front_link(): void {
		if ( ! $this->show_for_current_user() ) {
			return;
		}
		echo '<p><a class="ael-button ael-button--secondary" href="' . esc_url( $this->panel_url() ) . '">' . esc_html__( 'Le mie vendite (pannello docente)', 'academy-elearning' ) . '</a></p>';
	}

	/**
	 * Dopo l'accesso dal login di WooCommerce, un docente va al suo pannello (se non stava andando altrove).
	 *
	 * @param string  $redirect Destinazione di WooCommerce.
	 * @param WP_User $user     Utente.
	 * @return string
	 */
	public function woo_login_redirect( $redirect, $user ) {
		if ( ! $user instanceof WP_User || ! in_array( AEL_Content::TEACHER, (array) $user->roles, true ) || user_can( $user, AEL_Content::CAP ) ) {
			return $redirect;
		}
		$course = get_post_type_object( AEL_Content::COURSE );
		if ( $course && user_can( $user, $course->cap->edit_posts ) ) {
			return $redirect; // La destinazione la sceglie il modulo Docenti autori.
		}
		return ( function_exists( 'wc_get_page_permalink' ) && $redirect === wc_get_page_permalink( 'myaccount' ) ) ? $this->panel_url() : $redirect;
	}

	/**
	 * Voce "Pannello docente" nel menu di "Il mio account" di WooCommerce.
	 *
	 * @param array $items Voci del menu.
	 * @return array
	 */
	public function woo_menu_item( $items ) {
		if ( ! is_array( $items ) || ! $this->show_for_current_user() ) {
			return $items;
		}
		$new = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$new['ael-panel'] = __( 'Le mie vendite', 'academy-elearning' );
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new['ael-panel'] ) ) {
			$new['ael-panel'] = __( 'Le mie vendite', 'academy-elearning' );
		}
		return $new;
	}

	/**
	 * La voce del menu porta all'amministrazione, non a una pagina di WooCommerce.
	 *
	 * @param string $url      Indirizzo.
	 * @param string $endpoint Voce.
	 * @return string
	 */
	public function woo_menu_url( $url, $endpoint ) {
		return 'ael-panel' === $endpoint ? $this->panel_url() : $url;
	}

	/**
	 * Concede il permesso ai docenti (non al titolare, che ha la pagina completa).
	 *
	 * @param array   $allcaps Permessi.
	 * @param array   $caps    Richiesti.
	 * @param array   $args    Argomenti.
	 * @param WP_User $user    Utente.
	 * @return array
	 */
	public function grant_cap( $allcaps, $caps, $args, $user ) {
		if ( $user instanceof WP_User && in_array( AEL_Content::TEACHER, (array) $user->roles, true ) && empty( $allcaps[ AEL_Content::CAP ] ) ) {
			$allcaps[ self::CAP ] = true;
		}
		return $allcaps;
	}

	/**
	 * Scheda docente collegata all'utente (0 se non c'è).
	 *
	 * @param int $user_id Utente.
	 */
	public function profile_id( int $user_id ): int {
		if ( ! isset( $this->profile_cache[ $user_id ] ) ) {
			$ids = get_posts(
				array(
					'post_type'        => AEL_Content::TEACHER,
					'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
					'numberposts'      => 1,
					'fields'           => 'ids',
					'suppress_filters' => true,
					'meta_key'         => '_ael_user_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'       => $user_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			$this->profile_cache[ $user_id ] = $ids ? (int) $ids[0] : 0;
		}
		return $this->profile_cache[ $user_id ];
	}

	/**
	 * Voce di menu "Le mie vendite".
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Le mie vendite', 'academy-elearning' ),
			__( 'Le mie vendite', 'academy-elearning' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render' ),
			'dashicons-chart-bar',
			59
		);
	}

	/**
	 * Dopo l'accesso un docente senza altre funzioni va alle sue vendite.
	 *
	 * @param string           $redirect_to Destinazione.
	 * @param string           $requested   Richiesta.
	 * @param WP_User|WP_Error $user        Utente.
	 */
	public function login_redirect( $redirect_to, $requested, $user ) {
		if ( ! $user instanceof WP_User || ! in_array( AEL_Content::TEACHER, (array) $user->roles, true ) || user_can( $user, AEL_Content::CAP ) ) {
			return $redirect_to;
		}
		$course = get_post_type_object( AEL_Content::COURSE );
		if ( $course && user_can( $user, $course->cap->edit_posts ) ) {
			// Se scrive anche i corsi (modulo Docenti autori), la destinazione la sceglie quel modulo.
			return $redirect_to;
		}
		if ( '' === (string) $requested || 0 === strpos( (string) $requested, admin_url() ) ) {
			return admin_url( 'admin.php?page=' . self::PAGE );
		}
		return $redirect_to;
	}

	/**
	 * Filtri validi: solo il periodo. Il docente è sempre quello collegato.
	 *
	 * @param int $profile Scheda docente.
	 * @return array<string, mixed>
	 */
	private function filters( int $profile ): array {
		// Filtri di sola lettura.
		// phpcs:disable WordPress.Security.NonceVerification
		$from = isset( $_REQUEST['from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['from'] ) ) : '';
		$to   = isset( $_REQUEST['to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification
		return array(
			'from'       => $from,
			'to'         => $to,
			'teacher_id' => $profile,
		);
	}

	/**
	 * Disegna la pagina.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per vedere questa pagina.', 'academy-elearning' ), 403 );
		}
		$user_id = get_current_user_id();
		$profile = $this->profile_id( $user_id );
		?>
		<div class="wrap ael-admin">
			<h1><?php echo esc_html__( 'Le mie vendite', 'academy-elearning' ); ?></h1>
		<?php
		if ( ! $profile ) {
			echo '<p>' . esc_html__( 'La tua scheda docente non è ancora collegata al tuo utente. Chiedi al titolare dell\'academy di collegarla.', 'academy-elearning' ) . '</p></div>';
			return;
		}

		$revenue  = AEL_Teacher_Revenue::instance();
		$filters  = $this->filters( $profile );
		$sales    = $revenue->sales( $filters, 200 );
		$totals   = $revenue->totals_by_teacher( $filters );
		$sum      = $totals[ $profile ] ?? array(
			'sales'         => 0,
			'net'           => 0,
			'quota_open'    => 0,
			'quota_settled' => 0,
		);
		$statuses = array(
			AEL_Teacher_Revenue::STATUS_OPEN     => __( 'Da liquidare', 'academy-elearning' ),
			AEL_Teacher_Revenue::STATUS_SETTLED  => __( 'Liquidata', 'academy-elearning' ),
			AEL_Teacher_Revenue::STATUS_REVERSED => __( 'Stornata', 'academy-elearning' ),
		);
		$export   = wp_nonce_url(
			add_query_arg(
				array_filter(
					array(
						'action' => 'ael_my_sales_export',
						'from'   => $filters['from'],
						'to'     => $filters['to'],
					)
				),
				admin_url( 'admin-post.php' )
			),
			'ael_my_sales_export'
		);
		$cards    = array(
			array( (string) $sum['sales'], __( 'Vendite', 'academy-elearning' ) ),
			array( AEL_Payments::format( $sum['net'] ), __( 'Netto', 'academy-elearning' ) ),
			array( AEL_Payments::format( $sum['quota_open'] ), __( 'Quota da liquidare', 'academy-elearning' ) ),
			array( AEL_Payments::format( $sum['quota_settled'] ), __( 'Quota già liquidata', 'academy-elearning' ) ),
		);
		?>
			<p class="description"><?php echo esc_html__( 'Sola lettura. Le quote sono calcolate sull\'importo netto (senza IVA e commissioni). Il pagamento delle quote avviene fuori dalla piattaforma.', 'academy-elearning' ); ?></p>

			<form method="get" class="ael-search">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<label><span class="screen-reader-text"><?php echo esc_html__( 'Dal', 'academy-elearning' ); ?></span>
					<input type="date" name="from" value="<?php echo esc_attr( (string) $filters['from'] ); ?>"></label>
				<label><span class="screen-reader-text"><?php echo esc_html__( 'Al', 'academy-elearning' ); ?></span>
					<input type="date" name="to" value="<?php echo esc_attr( (string) $filters['to'] ); ?>"></label>
				<?php submit_button( __( 'Filtra', 'academy-elearning' ), 'secondary', '', false ); ?>
				<a class="button" href="<?php echo esc_url( $export ); ?>"><?php echo esc_html__( 'Esporta CSV', 'academy-elearning' ); ?></a>
			</form>

			<div class="ael-stat-grid">
				<?php foreach ( $cards as $card ) : ?>
					<div class="ael-stat">
						<span class="ael-stat-value"><?php echo esc_html( $card[0] ); ?></span>
						<span class="ael-stat-label"><?php echo esc_html( $card[1] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( empty( $sales ) ) : ?>
				<p><?php echo esc_html__( 'Nessuna vendita nel periodo.', 'academy-elearning' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php echo esc_html__( 'Data', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Corso', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Netto', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( '%', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Quota', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Stato', 'academy-elearning' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $sales as $sale ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'd/m/Y', (int) strtotime( $sale['paid_at'] . ' UTC' ) ) ); ?></td>
							<td><?php echo esc_html( get_post( (int) $sale['course_id'] ) ? get_the_title( (int) $sale['course_id'] ) : '—' ); ?></td>
							<td><?php echo esc_html( AEL_Payments::format( (int) $sale['net'], $sale['currency'] ) ); ?></td>
							<td><?php echo esc_html( rtrim( rtrim( number_format( (float) $sale['percent'], 2, ',', '' ), '0' ), ',' ) ); ?></td>
							<td><strong><?php echo esc_html( AEL_Payments::format( (int) $sale['quota'], $sale['currency'] ) ); ?></strong></td>
							<td><?php echo esc_html( $statuses[ $sale['status'] ] ?? $sale['status'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Esporta in CSV le sole vendite del docente collegato.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per fare questa operazione.', 'academy-elearning' ), 403 );
		}
		check_admin_referer( 'ael_my_sales_export' );

		$profile = $this->profile_id( get_current_user_id() );
		if ( ! $profile ) {
			wp_die( esc_html__( 'La tua scheda docente non è collegata.', 'academy-elearning' ), 403 );
		}
		$sales = AEL_Teacher_Revenue::instance()->sales( $this->filters( $profile ), 0 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mie-vendite-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, array( 'Data (UTC)', 'Corso', 'Netto', 'Percentuale', 'Quota', 'Stato' ) );
		foreach ( $sales as $sale ) {
			$title = get_post( (int) $sale['course_id'] ) ? get_the_title( (int) $sale['course_id'] ) : '';
			if ( '' !== $title && in_array( $title[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
				$title = "'" . $title;
			}
			fputcsv(
				$out,
				array(
					$sale['paid_at'],
					$title,
					number_format( (int) $sale['net'] / 100, 2, '.', '' ),
					number_format( (float) $sale['percent'], 2, '.', '' ),
					number_format( (int) $sale['quota'] / 100, 2, '.', '' ),
					$sale['status'],
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
