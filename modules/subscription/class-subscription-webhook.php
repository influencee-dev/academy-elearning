<?php
/**
 * Webhook di Stripe: eventi, accessi e incassi.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Indirizzo: /wp-json/ael/v1/stripe-webhook
 *
 * - Ogni richiesta si accetta SOLO se la firma (Stripe-Signature) è valida.
 * - Lo stesso evento ricevuto due volte non produce effetti doppi: si registra l'ID di
 *   ogni evento elaborato, e comunque concessioni e incassi sono idempotenti di per sé.
 * - Se l'elaborazione fallisce si risponde con un errore, così Stripe riprova.
 * - Dagli eventi si usano solo gli ID: l'abbonamento, la fattura e la sessione si rileggono
 *   dall'API con la versione fissata.
 *
 * Eventi gestiti: checkout.session.completed, invoice.paid, invoice.payment_failed,
 * customer.subscription.created/updated/deleted, charge.refunded.
 */
final class AEL_Subscription_Webhook {

	public const META_CUSTOMER     = '_ael_stripe_customer';
	public const META_SUB_ID       = '_ael_sub_id';
	public const META_SUB_STATUS   = '_ael_sub_status';
	public const META_PERIOD_END   = '_ael_sub_period_end';
	public const META_CANCEL_END   = '_ael_sub_cancel_at_end';
	public const META_CONSENT      = '_ael_sub_consent_at';
	public const META_FAILED_INV   = '_ael_sub_failed_invoice';

	private const GATEWAY = 'stripe';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Subscription_Webhook|null
	 */
	private static ?AEL_Subscription_Webhook $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Subscription_Webhook {
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
	 * Aggancia la tabella degli eventi e la route del webhook.
	 */
	public function boot(): void {
		add_filter( 'ael_migrations', array( $this, 'add_migration' ) );
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Aggiunge le migrazioni del modulo (eventi elaborati e pagina "Abbonati").
	 *
	 * @param array<string, callable> $migrations Migrazioni.
	 * @return array<string, callable>
	 */
	public function add_migration( $migrations ) {
		$migrations['subscription-001-events'] = array( __CLASS__, 'create_table' );
		$migrations['subscription-002-page']   = array( 'AEL_Pages', 'setup' );
		return $migrations;
	}

	/**
	 * Tabella degli eventi già elaborati.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ael_stripe_events';
	}

	/**
	 * Crea la tabella degli eventi.
	 */
	public static function create_table(): bool {
		$sql = 'CREATE TABLE {table} (
  event_id varchar(100) NOT NULL,
  event_type varchar(80) NOT NULL DEFAULT \'\',
  created_at datetime NOT NULL,
  PRIMARY KEY  (event_id),
  KEY created_at (created_at)
) {charset};';
		return AEL_Installer::create_table( 'ael_stripe_events', $sql );
	}

	/**
	 * Registra la route pubblica del webhook.
	 */
	public function register_route(): void {
		register_rest_route(
			AEL_Rest::NAMESPACE,
			'/stripe-webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				// Pubblica per natura (la chiama Stripe): l'autenticità si verifica nella funzione con la firma.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Indirizzo del webhook, da incollare nella pagina di Stripe.
	 */
	public static function url(): string {
		return rest_url( AEL_Rest::NAMESPACE . '/stripe-webhook' );
	}

	/* ------------------------------------------------------------------ */
	/* Ricezione                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Riceve un evento di Stripe.
	 *
	 * @param WP_REST_Request $request Richiesta.
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$secret = AEL_Subscription_Stripe::settings()['webhook_secret'];
		if ( '' === $secret ) {
			return new WP_REST_Response( array( 'error' => 'webhook not configured' ), 503 );
		}
		$payload = (string) $request->get_body();
		$header  = (string) $request->get_header( 'stripe-signature' );
		if ( strlen( $payload ) > 1048576 || ! AEL_Subscription_Stripe::verify_signature( $payload, $header, $secret ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid signature' ), 400 );
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid payload' ), 400 );
		}

		$result = $this->process( $event );
		if ( is_wp_error( $result ) ) {
			// Stripe riproverà. Nel registro non finiscono mai chiavi né dati dell'utente.
			error_log( '[academy-elearning] webhook ' . sanitize_text_field( (string) $event['type'] ) . ' non elaborato: ' . $result->get_error_code() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_REST_Response( array( 'error' => 'processing failed' ), 500 );
		}
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Elabora un evento già autenticato, una sola volta.
	 *
	 * @param array<string, mixed> $event Evento.
	 * @return true|WP_Error
	 */
	public function process( array $event ) {
		global $wpdb;

		$event_id = substr( sanitize_text_field( (string) $event['id'] ), 0, 100 );
		$type     = substr( sanitize_text_field( (string) $event['type'] ), 0, 80 );
		$table    = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT event_id FROM {$table} WHERE event_id = %s", $event_id ) ) ) {
			return true; // Già elaborato.
		}

		$object_id = isset( $event['data']['object']['id'] ) ? (string) $event['data']['object']['id'] : '';
		$result    = $this->dispatch( $type, $object_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (event_id, event_type, created_at) VALUES (%s, %s, %s)", $event_id, $type, gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// Pulizia degli eventi vecchi (Stripe non li ripresenta dopo qualche giorno).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 60 * DAY_IN_SECONDS ) ) );
		return true;
	}

	/**
	 * Sceglie cosa fare in base al tipo di evento.
	 *
	 * @param string $type      Tipo di evento.
	 * @param string $object_id ID dell'oggetto dell'evento.
	 * @return true|WP_Error
	 */
	private function dispatch( string $type, string $object_id ) {
		if ( '' === $object_id ) {
			return true;
		}
		switch ( $type ) {
			case 'checkout.session.completed':
				return $this->on_checkout_completed( $object_id );
			case 'invoice.paid':
				return $this->on_invoice_paid( $object_id );
			case 'invoice.payment_failed':
				return $this->on_invoice_failed( $object_id );
			case 'customer.subscription.created':
			case 'customer.subscription.updated':
			case 'customer.subscription.deleted':
				return $this->on_subscription_event( $object_id );
			case 'charge.refunded':
				return $this->on_charge_refunded( $object_id );
		}
		return true; // Evento che non ci riguarda.
	}

	/* ------------------------------------------------------------------ */
	/* Utenti                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Utente collegato a un oggetto di Stripe: dai metadati, altrimenti dal cliente.
	 *
	 * @param array<string, mixed> $object Abbonamento, sessione o fattura.
	 */
	public function find_user( array $object ): int {
		$meta_id = isset( $object['metadata']['ael_user_id'] ) ? absint( $object['metadata']['ael_user_id'] ) : 0;
		if ( ! $meta_id && isset( $object['client_reference_id'] ) ) {
			$meta_id = absint( $object['client_reference_id'] );
		}
		if ( $meta_id && get_userdata( $meta_id ) ) {
			return $meta_id;
		}
		$customer = isset( $object['customer'] ) ? ( is_array( $object['customer'] ) ? (string) ( $object['customer']['id'] ?? '' ) : (string) $object['customer'] ) : '';
		if ( '' !== $customer ) {
			$users = get_users(
				array(
					'meta_key'   => self::META_CUSTOMER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $customer, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'number'     => 1,
					'fields'     => 'ID',
				)
			);
			if ( ! empty( $users ) ) {
				return (int) $users[0];
			}
		}
		return 0;
	}

	/* ------------------------------------------------------------------ */
	/* Eventi                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Pagamento in Checkout completato: collega cliente e utente e sincronizza l'abbonamento.
	 *
	 * @param string $session_id Sessione.
	 * @return true|WP_Error
	 */
	private function on_checkout_completed( string $session_id ) {
		$session = AEL_Subscription_Stripe::request( 'GET', 'checkout/sessions/' . rawurlencode( $session_id ) );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		if ( 'subscription' !== ( $session['mode'] ?? '' ) ) {
			return true; // Non è un abbonamento nostro.
		}
		return $this->finish_checkout( $session );
	}

	/**
	 * Collega cliente e abbonamento a un utente dopo un Checkout riuscito.
	 * Usata dal webhook e dal ritorno dell'utente sul sito (stessi effetti, idempotenti).
	 *
	 * @param array<string, mixed> $session Sessione di Checkout (letta dall'API).
	 * @return true|WP_Error
	 */
	public function finish_checkout( array $session ) {
		$user_id = $this->find_user( $session );
		if ( ! $user_id ) {
			return true; // Sessione di un altro uso dello stesso account Stripe.
		}
		$customer = isset( $session['customer'] ) ? ( is_array( $session['customer'] ) ? (string) ( $session['customer']['id'] ?? '' ) : (string) $session['customer'] ) : '';
		if ( '' !== $customer ) {
			update_user_meta( $user_id, self::META_CUSTOMER, $customer );
		}
		if ( ! empty( $session['metadata']['ael_consent_at'] ) ) {
			update_user_meta( $user_id, self::META_CONSENT, sanitize_text_field( (string) $session['metadata']['ael_consent_at'] ) );
		}

		$sub_id = isset( $session['subscription'] ) ? ( is_array( $session['subscription'] ) ? (string) ( $session['subscription']['id'] ?? '' ) : (string) $session['subscription'] ) : '';
		if ( '' === $sub_id ) {
			return true;
		}
		$sub = AEL_Subscription_Stripe::request( 'GET', 'subscriptions/' . rawurlencode( $sub_id ) );
		if ( is_wp_error( $sub ) ) {
			return $sub;
		}
		return $this->sync_subscription( $sub, $user_id );
	}

	/**
	 * Evento su un abbonamento: si rilegge e si sincronizza.
	 *
	 * @param string $sub_id Abbonamento.
	 * @return true|WP_Error
	 */
	private function on_subscription_event( string $sub_id ) {
		$sub = AEL_Subscription_Stripe::request( 'GET', 'subscriptions/' . rawurlencode( $sub_id ) );
		return is_wp_error( $sub ) ? $sub : $this->sync_subscription( $sub );
	}

	/**
	 * Fattura pagata (prima iscrizione o rinnovo): estende l'accesso e annuncia l'incasso.
	 *
	 * @param string $invoice_id Fattura.
	 * @return true|WP_Error
	 */
	private function on_invoice_paid( string $invoice_id ) {
		$invoice = AEL_Subscription_Stripe::request( 'GET', 'invoices/' . rawurlencode( $invoice_id ) );
		if ( is_wp_error( $invoice ) ) {
			return $invoice;
		}
		$sub_id = isset( $invoice['subscription'] ) ? ( is_array( $invoice['subscription'] ) ? (string) ( $invoice['subscription']['id'] ?? '' ) : (string) $invoice['subscription'] ) : '';
		if ( '' === $sub_id ) {
			return true; // Fattura che non riguarda un abbonamento.
		}
		$sub = AEL_Subscription_Stripe::request( 'GET', 'subscriptions/' . rawurlencode( $sub_id ) );
		if ( is_wp_error( $sub ) ) {
			return $sub;
		}
		$user_id = $this->find_user( $sub );
		if ( ! $user_id ) {
			$user_id = $this->find_user( $invoice );
		}
		if ( ! $user_id ) {
			return true;
		}

		$synced = $this->sync_subscription( $sub, $user_id );
		if ( is_wp_error( $synced ) ) {
			return $synced;
		}
		$this->announce_payment( $invoice, $sub, $user_id );
		return true;
	}

	/**
	 * Annuncia l'incasso di una fattura ai moduli interessati (ael_payment_recorded).
	 *
	 * @param array<string, mixed> $invoice Fattura.
	 * @param array<string, mixed> $sub     Abbonamento.
	 * @param int                  $user_id Utente.
	 */
	private function announce_payment( array $invoice, array $sub, int $user_id ): void {
		$amount = (int) ( $invoice['amount_paid'] ?? 0 );
		if ( $amount <= 0 ) {
			return;
		}
		$paid  = (int) ( $invoice['status_transitions']['paid_at'] ?? 0 );
		$meta  = array();
		$first = 'subscription_create' === ( $invoice['billing_reason'] ?? '' );

		// Provenienza e risposta a "Come ci hai conosciuto?" valgono solo per il primo pagamento.
		if ( $first ) {
			$attr = isset( $sub['metadata']['ael_attr'] ) ? json_decode( (string) $sub['metadata']['ael_attr'], true ) : array();
			$meta['attribution'] = is_array( $attr ) ? $attr : array();
			$meta['coupon']      = (string) ( $invoice['discount']['coupon']['name'] ?? ( $invoice['discount']['coupon']['id'] ?? '' ) );
			$meta['heard_from']  = $this->heard_from( (string) ( $sub['id'] ?? '' ) );
		}

		AEL_Payments::record(
			array(
				'gateway'   => self::GATEWAY,
				'type'      => AEL_Payments::TYPE_SUBSCRIPTION,
				'ref'       => (string) $invoice['id'],
				'user_id'   => $user_id,
				'course_id' => 0,
				'currency'  => (string) ( $invoice['currency'] ?? 'eur' ),
				'gross'     => $amount,
				'vat'       => (int) ( $invoice['tax'] ?? 0 ),
				'fee'       => null,
				'paid_at'   => gmdate( 'Y-m-d H:i:s', $paid > 0 ? $paid : time() ),
				'meta'      => $meta,
			)
		);
	}

	/**
	 * Risposta a "Come ci hai conosciuto?" data nel Checkout di un abbonamento.
	 *
	 * @param string $sub_id Abbonamento.
	 */
	private function heard_from( string $sub_id ): string {
		if ( '' === $sub_id ) {
			return '';
		}
		$list = AEL_Subscription_Stripe::request(
			'GET',
			'checkout/sessions',
			array(
				'subscription' => $sub_id,
				'limit'        => 1,
			)
		);
		if ( is_wp_error( $list ) || empty( $list['data'][0]['custom_fields'] ) ) {
			return '';
		}
		foreach ( (array) $list['data'][0]['custom_fields'] as $field ) {
			if ( 'heard_from' === ( $field['key'] ?? '' ) ) {
				return (string) ( $field['dropdown']['value'] ?? ( $field['text']['value'] ?? '' ) );
			}
		}
		return '';
	}

	/**
	 * Pagamento non riuscito: si segnala all'utente (una volta per fattura).
	 *
	 * @param string $invoice_id Fattura.
	 * @return true|WP_Error
	 */
	private function on_invoice_failed( string $invoice_id ) {
		$invoice = AEL_Subscription_Stripe::request( 'GET', 'invoices/' . rawurlencode( $invoice_id ) );
		if ( is_wp_error( $invoice ) ) {
			return $invoice;
		}
		$user_id = $this->find_user( $invoice );
		if ( ! $user_id || (string) get_user_meta( $user_id, self::META_FAILED_INV, true ) === $invoice_id ) {
			return true;
		}
		update_user_meta( $user_id, self::META_FAILED_INV, $invoice_id );

		$user = get_userdata( $user_id );
		if ( $user ) {
			wp_mail(
				$user->user_email,
				sprintf(
					/* translators: %s: nome del sito */
					__( 'Il pagamento del tuo abbonamento a %s non è andato a buon fine', 'academy-elearning' ),
					wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
				),
				sprintf(
					/* translators: 1: nome dell'utente, 2: indirizzo della pagina dei corsi */
					__( "Ciao %1\$s,\n\nnon siamo riusciti a incassare il rinnovo del tuo abbonamento. Puoi aggiornare la carta dalla tua area personale, con il pulsante \"Gestisci abbonamento\":\n%2\$s\n\nStripe riproverà automaticamente nei prossimi giorni.\n", 'academy-elearning' ),
					$user->display_name,
					AEL_Account::my_courses_url()
				)
			);
		}
		return true;
	}

	/**
	 * Rimborso di un addebito dell'abbonamento: si annuncia e, se è totale, si toglie l'accesso.
	 *
	 * @param string $charge_id Addebito.
	 * @return true|WP_Error
	 */
	private function on_charge_refunded( string $charge_id ) {
		$charge = AEL_Subscription_Stripe::request( 'GET', 'charges/' . rawurlencode( $charge_id ) );
		if ( is_wp_error( $charge ) ) {
			return $charge;
		}
		$invoice_id = isset( $charge['invoice'] ) ? ( is_array( $charge['invoice'] ) ? (string) ( $charge['invoice']['id'] ?? '' ) : (string) $charge['invoice'] ) : '';
		if ( '' === $invoice_id ) {
			return true; // Non è l'addebito di una fattura (non è nostro).
		}
		$invoice = AEL_Subscription_Stripe::request( 'GET', 'invoices/' . rawurlencode( $invoice_id ) );
		if ( is_wp_error( $invoice ) ) {
			return $invoice;
		}
		$sub_id = isset( $invoice['subscription'] ) ? ( is_array( $invoice['subscription'] ) ? (string) ( $invoice['subscription']['id'] ?? '' ) : (string) $invoice['subscription'] ) : '';
		if ( '' === $sub_id ) {
			return true;
		}

		AEL_Payments::refund(
			array(
				'gateway'   => self::GATEWAY,
				'ref'       => $invoice_id,
				'course_id' => 0,
				'refunded'  => (int) ( $charge['amount_refunded'] ?? 0 ),
			)
		);

		if ( ! empty( $charge['refunded'] ) ) {
			$user_id = $this->find_user( $invoice );
			if ( $user_id ) {
				AEL_Access::revoke( AEL_Access::SOURCE_SUBSCRIPTION, $sub_id, $user_id, 0 );
				update_user_meta( $user_id, self::META_SUB_STATUS, 'refunded' );
			}
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Accesso                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Allinea stato e accesso dell'utente a quello dell'abbonamento su Stripe.
	 *
	 * Regole:
	 * - attivo o in prova: accesso fino alla fine del periodo pagato (più i giorni di tolleranza,
	 *   che coprono il ritardo tra la scadenza e l'arrivo del rinnovo; nessuna tolleranza se è stato disdetto);
	 * - pagamento in ritardo: accesso solo fino alla fine del periodo già pagato;
	 * - disdetto: accesso fino alla fine del periodo pagato (o subito, se annullato subito).
	 *
	 * @param array<string, mixed> $sub     Abbonamento (letto dall'API).
	 * @param int                  $user_id Utente (se già noto).
	 * @return true|WP_Error
	 */
	public function sync_subscription( array $sub, int $user_id = 0 ) {
		if ( empty( $sub['id'] ) ) {
			return new WP_Error( 'ael_sub_invalid', 'subscription without id' );
		}
		$user_id = $user_id ? $user_id : $this->find_user( $sub );
		if ( ! $user_id ) {
			return true; // Abbonamento di un altro uso dello stesso account Stripe.
		}

		$sub_id     = (string) $sub['id'];
		$status     = (string) ( $sub['status'] ?? '' );
		$period_end = (int) ( $sub['current_period_end'] ?? 0 );
		$cancel_end = ! empty( $sub['cancel_at_period_end'] );
		$grace      = AEL_Subscription_Stripe::settings()['grace_days'] * DAY_IN_SECONDS;
		$now        = time();

		update_user_meta( $user_id, self::META_SUB_ID, $sub_id );
		update_user_meta( $user_id, self::META_SUB_STATUS, $status );
		update_user_meta( $user_id, self::META_PERIOD_END, $period_end > 0 ? gmdate( 'Y-m-d H:i:s', $period_end ) : '' );
		update_user_meta( $user_id, self::META_CANCEL_END, $cancel_end ? '1' : '0' );
		if ( ! empty( $sub['customer'] ) ) {
			update_user_meta( $user_id, self::META_CUSTOMER, is_array( $sub['customer'] ) ? (string) ( $sub['customer']['id'] ?? '' ) : (string) $sub['customer'] );
		}

		$ends = 0;
		switch ( $status ) {
			case 'active':
			case 'trialing':
				$ends = $period_end + ( $cancel_end ? 0 : $grace );
				break;
			case 'past_due':
			case 'unpaid':
				$ends = $period_end;
				break;
			case 'canceled':
				$ended = (int) ( $sub['ended_at'] ?? 0 );
				$ends  = $ended > 0 ? min( $ended, $period_end > 0 ? $period_end : $ended ) : $period_end;
				break;
			default:
				$ends = 0; // incomplete, incomplete_expired, paused: nessun accesso.
		}

		if ( $ends > $now ) {
			AEL_Access::grant( $user_id, 0, AEL_Access::SOURCE_SUBSCRIPTION, $sub_id, gmdate( 'Y-m-d H:i:s', $ends ) );
		} else {
			AEL_Access::revoke( AEL_Access::SOURCE_SUBSCRIPTION, $sub_id, $user_id, 0 );
		}
		return true;
	}
}
