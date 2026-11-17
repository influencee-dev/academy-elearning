<?php
/**
 * Pagina Abbonamento: chiavi di Stripe, prezzo e controllo del collegamento.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Riservata all'agenzia (capacità ael_manage_modules): il titolare non vede né le chiavi
 * né questa pagina. Le chiavi non si stampano mai per intero: si mostra solo la fine.
 */
final class AEL_Subscription_Admin {

	public const PAGE = 'ael-subscription';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Subscription_Admin|null
	 */
	private static ?AEL_Subscription_Admin $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Subscription_Admin {
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
	 * Aggancia menu e azioni.
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 12 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_ael_sub_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_ael_sub_check', array( $this, 'handle_check' ) );
	}

	/**
	 * Voce di menu (solo per l'agenzia).
	 */
	public function register_menu(): void {
		add_submenu_page(
			AEL_Admin::MENU,
			__( 'Abbonamento (Stripe)', 'academy-elearning' ),
			__( 'Abbonamento', 'academy-elearning' ),
			AEL_Settings::CAP,
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
	 * Una chiave mostrata senza esporla: solo i primi e gli ultimi caratteri.
	 *
	 * @param string $key Chiave.
	 */
	private function mask( string $key ): string {
		if ( '' === $key ) {
			return '';
		}
		return substr( $key, 0, 8 ) . '••••••••' . substr( $key, -4 );
	}

	/**
	 * Messaggio salvato per l'utente corrente (esito di un'azione).
	 */
	private function take_flash(): string {
		$key  = 'ael_sub_flash_' . get_current_user_id();
		$text = get_transient( $key );
		if ( false !== $text ) {
			delete_transient( $key );
		}
		return is_string( $text ) ? $text : '';
	}

	/**
	 * Salva un messaggio e torna alla pagina.
	 *
	 * @param string $text Testo.
	 */
	private function back( string $text ): void {
		set_transient( 'ael_sub_flash_' . get_current_user_id(), $text, MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'page', self::PAGE, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Disegna la pagina.
	 */
	public function render(): void {
		if ( ! current_user_can( AEL_Settings::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per vedere questa pagina.', 'academy-elearning' ), 403 );
		}
		global $wpdb;

		$settings = AEL_Subscription_Stripe::settings();
		$mode     = AEL_Subscription_Stripe::mode();
		$flash    = $this->take_flash();
		$table    = AEL_Subscription_Webhook::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$events   = $wpdb->get_results( "SELECT event_type, created_at FROM {$table} ORDER BY created_at DESC LIMIT 10", ARRAY_A );
		$webhook  = AEL_Subscription_Webhook::url();
		?>
		<div class="wrap ael-admin">
			<h1><?php echo esc_html__( 'Abbonamento (Stripe)', 'academy-elearning' ); ?></h1>
			<p><?php echo esc_html__( 'Pagina riservata all\'agenzia. Le chiavi si salvano nelle impostazioni del sito e non si mostrano mai per intero.', 'academy-elearning' ); ?></p>

			<?php if ( '' !== $flash ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $flash ); ?></p></div>
			<?php endif; ?>

			<?php if ( 'live' === $mode && AEL_Subscription_Stripe::is_dev_site() ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html__( 'Hai inserito una chiave LIVE su un sito di sviluppo: il plugin non la usa. In sviluppo servono solo chiavi di test (sk_test_...).', 'academy-elearning' ); ?></p></div>
			<?php endif; ?>

			<div class="ael-box">
				<h2><?php echo esc_html__( 'Collegamento a Stripe', 'academy-elearning' ); ?></h2>
				<p>
					<?php
					$label = array(
						'test' => __( 'Modalità: TEST (nessun pagamento reale)', 'academy-elearning' ),
						'live' => __( 'Modalità: LIVE (pagamenti reali)', 'academy-elearning' ),
					);
					echo esc_html( $label[ $mode ] ?? __( 'Chiave non impostata', 'academy-elearning' ) );
					?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ael_sub_save">
					<?php wp_nonce_field( 'ael_sub_save' ); ?>
					<table class="form-table" role="presentation"><tbody>
						<tr><th><label for="ael_sk"><?php echo esc_html__( 'Chiave segreta', 'academy-elearning' ); ?></label></th>
							<td><input type="password" id="ael_sk" name="secret_key" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $settings['secret_key'] ? $this->mask( $settings['secret_key'] ) : 'sk_test_...' ); ?>">
							<p class="description"><?php echo esc_html__( 'Inizia con sk_test_ (prova) o sk_live_ (reale). Lascia vuoto per non cambiarla.', 'academy-elearning' ); ?></p></td></tr>
						<tr><th><label for="ael_wh"><?php echo esc_html__( 'Segreto del webhook', 'academy-elearning' ); ?></label></th>
							<td><input type="password" id="ael_wh" name="webhook_secret" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $settings['webhook_secret'] ? $this->mask( $settings['webhook_secret'] ) : 'whsec_...' ); ?>">
							<p class="description"><?php echo esc_html__( 'Inizia con whsec_. Lo dà Stripe quando crei il webhook (o il comando "stripe listen" in prova). Lascia vuoto per non cambiarlo.', 'academy-elearning' ); ?></p></td></tr>
						<tr><th><label for="ael_price"><?php echo esc_html__( 'ID del prezzo', 'academy-elearning' ); ?></label></th>
							<td><input type="text" id="ael_price" name="price_id" class="regular-text" value="<?php echo esc_attr( $settings['price_id'] ); ?>" placeholder="price_...">
							<p class="description"><?php echo esc_html__( 'Il prezzo mensile creato nel Catalogo prodotti di Stripe.', 'academy-elearning' ); ?></p></td></tr>
						<tr><th><label for="ael_grace"><?php echo esc_html__( 'Giorni di tolleranza', 'academy-elearning' ); ?></label></th>
							<td><input type="number" id="ael_grace" name="grace_days" min="0" max="14" class="small-text" value="<?php echo esc_attr( (string) $settings['grace_days'] ); ?>">
							<p class="description"><?php echo esc_html__( 'Giorni in più di accesso a un abbonamento attivo se il rinnovo tarda ad arrivare (0-14).', 'academy-elearning' ); ?></p></td></tr>
						<tr><th><?php echo esc_html__( 'Rimozione', 'academy-elearning' ); ?></th>
							<td><label><input type="checkbox" name="clear_keys" value="1"> <?php echo esc_html__( 'Cancella le chiavi salvate', 'academy-elearning' ); ?></label></td></tr>
					</tbody></table>
					<?php submit_button( __( 'Salva', 'academy-elearning' ), 'primary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px">
					<input type="hidden" name="action" value="ael_sub_check">
					<?php wp_nonce_field( 'ael_sub_check' ); ?>
					<?php submit_button( __( 'Verifica il collegamento', 'academy-elearning' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>

			<div class="ael-box">
				<h2><?php echo esc_html__( 'Webhook', 'academy-elearning' ); ?></h2>
				<p><?php echo esc_html__( 'Stripe comunica al sito pagamenti, rinnovi e disdette a questo indirizzo:', 'academy-elearning' ); ?></p>
				<p><code><?php echo esc_html( $webhook ); ?></code></p>
				<p><?php echo esc_html__( 'Eventi da attivare su Stripe:', 'academy-elearning' ); ?></p>
				<p><code>checkout.session.completed, invoice.paid, invoice.payment_failed, customer.subscription.created, customer.subscription.updated, customer.subscription.deleted, charge.refunded</code></p>
				<p class="description"><?php echo esc_html__( 'In prova, su un sito locale, Stripe non può raggiungere l\'indirizzo: si usa la Stripe CLI con "stripe listen --forward-to" seguito da questo indirizzo.', 'academy-elearning' ); ?></p>
				<p><?php echo '' !== $settings['webhook_secret'] ? esc_html__( 'Segreto del webhook: impostato.', 'academy-elearning' ) : '<strong>' . esc_html__( 'Segreto del webhook: mancante (il webhook rifiuta ogni richiesta).', 'academy-elearning' ) . '</strong>'; ?></p>
			</div>

			<div class="ael-box">
				<h2><?php echo esc_html__( 'Ultimi eventi ricevuti', 'academy-elearning' ); ?></h2>
				<?php if ( empty( $events ) ) : ?>
					<p><?php echo esc_html__( 'Nessun evento ricevuto finora.', 'academy-elearning' ); ?></p>
				<?php else : ?>
					<table class="widefat striped ael-narrow" style="max-width:520px"><tbody>
						<?php foreach ( $events as $event ) : ?>
							<tr>
								<td><code><?php echo esc_html( $event['event_type'] ); ?></code></td>
								<td><?php echo esc_html( wp_date( 'd/m/Y H:i:s', (int) strtotime( $event['created_at'] . ' UTC' ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody></table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Salva chiavi, prezzo e tolleranza.
	 */
	public function handle_save(): void {
		if ( ! current_user_can( AEL_Settings::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per fare questa operazione.', 'academy-elearning' ), 403 );
		}
		check_admin_referer( 'ael_sub_save' );

		if ( ! empty( $_POST['clear_keys'] ) ) {
			delete_option( AEL_Subscription_Stripe::OPTION );
			$this->back( __( 'Chiavi cancellate.', 'academy-elearning' ) );
		}

		$current = AEL_Subscription_Stripe::settings();
		$problems = array();

		$secret = isset( $_POST['secret_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['secret_key'] ) ) ) : '';
		if ( '' === $secret ) {
			$secret = $current['secret_key'];
		} elseif ( ! preg_match( '/^(sk|rk)_(test|live)_[A-Za-z0-9]{10,}$/', $secret ) ) {
			$problems[] = __( 'La chiave segreta non ha il formato giusto (sk_test_... o sk_live_...): non è stata cambiata.', 'academy-elearning' );
			$secret     = $current['secret_key'];
		}

		$webhook = isset( $_POST['webhook_secret'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ) ) : '';
		if ( '' === $webhook ) {
			$webhook = $current['webhook_secret'];
		} elseif ( ! preg_match( '/^whsec_[A-Za-z0-9]{10,}$/', $webhook ) ) {
			$problems[] = __( 'Il segreto del webhook non ha il formato giusto (whsec_...): non è stato cambiato.', 'academy-elearning' );
			$webhook    = $current['webhook_secret'];
		}

		$price = isset( $_POST['price_id'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['price_id'] ) ) ) : '';
		if ( '' !== $price && ! preg_match( '/^price_[A-Za-z0-9]{6,}$/', $price ) ) {
			$problems[] = __( 'L\'ID del prezzo non ha il formato giusto (price_...): non è stato cambiato.', 'academy-elearning' );
			$price      = $current['price_id'];
		}

		$grace = isset( $_POST['grace_days'] ) ? max( 0, min( 14, absint( wp_unslash( $_POST['grace_days'] ) ) ) ) : 2;

		update_option(
			AEL_Subscription_Stripe::OPTION,
			array(
				'secret_key'     => $secret,
				'webhook_secret' => $webhook,
				'price_id'       => $price,
				'grace_days'     => $grace,
			),
			false
		);
		$this->back( empty( $problems ) ? __( 'Impostazioni salvate.', 'academy-elearning' ) : implode( ' ', $problems ) );
	}

	/**
	 * Controlla che chiave e prezzo funzionino, leggendo il prezzo da Stripe.
	 */
	public function handle_check(): void {
		if ( ! current_user_can( AEL_Settings::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per fare questa operazione.', 'academy-elearning' ), 403 );
		}
		check_admin_referer( 'ael_sub_check' );

		$settings = AEL_Subscription_Stripe::settings();
		if ( '' === $settings['price_id'] ) {
			$this->back( __( 'Manca l\'ID del prezzo.', 'academy-elearning' ) );
		}
		$price = AEL_Subscription_Stripe::request( 'GET', 'prices/' . rawurlencode( $settings['price_id'] ) );
		if ( is_wp_error( $price ) ) {
			$this->back( sprintf( /* translators: %s: errore di Stripe */ __( 'Collegamento non riuscito: %s', 'academy-elearning' ), $price->get_error_message() ) );
		}
		if ( empty( $price['recurring'] ) ) {
			$this->back( __( 'Il collegamento funziona, ma questo prezzo non è ricorrente: serve un prezzo mensile.', 'academy-elearning' ) );
		}
		delete_transient( 'ael_sub_price_' . md5( $settings['price_id'] . AEL_Subscription_Stripe::mode() ) );
		$this->back(
			sprintf(
				/* translators: 1: importo, 2: frequenza */
				__( 'Collegamento riuscito. Prezzo: %1$s (ogni %2$s).', 'academy-elearning' ),
				AEL_Payments::format( (int) ( $price['unit_amount'] ?? 0 ), strtoupper( (string) ( $price['currency'] ?? 'eur' ) ) ),
				(string) ( $price['recurring']['interval'] ?? '' )
			)
		);
	}
}
