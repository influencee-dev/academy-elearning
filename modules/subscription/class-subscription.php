<?php
/**
 * Abbonamento: pagina "Abbonati", Checkout, portale clienti e riquadro dell'utente.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cosa vede e fa lo studente. L'accesso lo decide il webhook (AEL_Subscription_Webhook):
 * qui si porta l'utente a Stripe e lo si riporta indietro.
 */
final class AEL_Subscription {

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Subscription|null
	 */
	private static ?AEL_Subscription $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Subscription {
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
	 * Aggancia pagina, pulsanti, azioni e ritorno da Stripe.
	 */
	public function boot(): void {
		add_filter( 'ael_pages_definitions', array( $this, 'add_page' ) );
		add_filter( 'ael_template_path', array( $this, 'module_templates' ), 10, 2 );
		add_shortcode( 'ael_subscribe', array( $this, 'shortcode' ) );

		add_filter( 'ael_course_actions', array( $this, 'add_subscribe_action' ), 30, 4 );
		add_action( 'ael_my_courses_before', array( $this, 'my_courses_box' ) );

		add_action( 'admin_post_nopriv_ael_sub_register', array( $this, 'handle_register' ) );
		add_action( 'admin_post_ael_sub_register', array( $this, 'handle_register' ) );
		add_action( 'admin_post_ael_sub_start', array( $this, 'handle_start' ) );
		add_action( 'admin_post_ael_sub_portal', array( $this, 'handle_portal' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_return' ), 1 );
	}

	/* ------------------------------------------------------------------ */
	/* Pagina e template                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Aggiunge la pagina "Abbonati" all'elenco delle pagine del plugin.
	 *
	 * @param array $defs Pagine.
	 * @return array
	 */
	public function add_page( $defs ) {
		$defs['subscribe'] = array(
			'option'  => 'ael_page_subscribe',
			'title'   => 'Abbonati',
			'slug'    => 'abbonati',
			'content' => '[ael_subscribe]',
		);
		return $defs;
	}

	/**
	 * I template del modulo vivono nella sua cartella; il tema può comunque sostituirli.
	 *
	 * @param string $path Percorso scelto.
	 * @param string $name Nome del template.
	 * @return string
	 */
	public function module_templates( $path, $name ) {
		if ( in_array( $name, array( 'subscribe', 'subscription-box' ), true ) && ! is_readable( (string) $path ) ) {
			return __DIR__ . '/templates/' . $name . '.php';
		}
		return $path;
	}

	/**
	 * Indirizzo della pagina "Abbonati".
	 *
	 * @param array<string, string> $args Parametri.
	 */
	public static function page_url( array $args = array() ): string {
		$url = AEL_Pages::url( 'subscribe', home_url( '/' ) );
		return empty( $args ) ? $url : add_query_arg( $args, $url );
	}

	/**
	 * Prezzo scritto per esteso ("19,00 € al mese"), vuoto se non disponibile.
	 */
	public static function price_text(): string {
		$price = AEL_Subscription_Stripe::price();
		if ( ! $price ) {
			return '';
		}
		$every = array(
			'month' => __( 'al mese', 'academy-elearning' ),
			'year'  => __( 'all\'anno', 'academy-elearning' ),
			'week'  => __( 'alla settimana', 'academy-elearning' ),
			'day'   => __( 'al giorno', 'academy-elearning' ),
		);
		return AEL_Payments::format( $price['amount'], $price['currency'] ) . ' ' . ( $every[ $price['interval'] ] ?? '' );
	}

	/**
	 * Stato dell'abbonamento di un utente, dai dati salvati.
	 *
	 * @param int $user_id Utente.
	 * @return array{id: string, status: string, period_end: string, cancel_at_end: bool, customer: string}
	 */
	public static function state_of( int $user_id ): array {
		return array(
			'id'            => (string) get_user_meta( $user_id, AEL_Subscription_Webhook::META_SUB_ID, true ),
			'status'        => (string) get_user_meta( $user_id, AEL_Subscription_Webhook::META_SUB_STATUS, true ),
			'period_end'    => (string) get_user_meta( $user_id, AEL_Subscription_Webhook::META_PERIOD_END, true ),
			'cancel_at_end' => '1' === (string) get_user_meta( $user_id, AEL_Subscription_Webhook::META_CANCEL_END, true ),
			'customer'      => (string) get_user_meta( $user_id, AEL_Subscription_Webhook::META_CUSTOMER, true ),
		);
	}

	/**
	 * L'utente ha un abbonamento attivo adesso?
	 *
	 * @param int $user_id Utente.
	 */
	public static function is_subscribed( int $user_id ): bool {
		$state = self::state_of( $user_id );
		if ( '' === $state['id'] || ! in_array( $state['status'], array( 'active', 'trialing', 'past_due', 'canceled' ), true ) ) {
			return false;
		}
		$end = '' !== $state['period_end'] ? strtotime( $state['period_end'] . ' UTC' ) : 0;
		return $end > time();
	}

	/**
	 * Shortcode [ael_subscribe].
	 */
	public function shortcode(): string {
		wp_enqueue_style( 'ael-front' );

		// Solo codici di messaggio da una lista fissa e flag: nessun dato letto o salvato.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$error    = isset( $_GET['ael_sub_err'] ) ? sanitize_key( wp_unslash( $_GET['ael_sub_err'] ) ) : '';
		$canceled = isset( $_GET['ael_sub'] ) && 'cancel' === sanitize_key( wp_unslash( $_GET['ael_sub'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$user_id = get_current_user_id();
		return AEL_Templates::render(
			'subscribe',
			array(
				'ready'        => AEL_Subscription_Stripe::is_ready(),
				'price_text'   => self::price_text(),
				'logged_in'    => $user_id > 0,
				'user'         => wp_get_current_user(),
				'subscribed'   => $user_id > 0 && self::is_subscribed( $user_id ),
				'consent_text' => AEL_Subscription_Consent::get(),
				'error'        => $error,
				'canceled'     => $canceled,
				'login_url'    => wp_login_url( self::page_url() ),
				'courses_url'  => AEL_Account::my_courses_url(),
				'catalog_url'  => AEL_Catalog::catalog_url(),
				'messages'     => self::messages(),
			)
		);
	}

	/**
	 * Messaggi per i codici di errore.
	 *
	 * @return array<string, string>
	 */
	public static function messages(): array {
		return array(
			'consent' => __( 'Per continuare devi spuntare la casella sull\'accesso immediato ai contenuti digitali.', 'academy-elearning' ),
			'exists'  => __( 'Esiste già un account con questa email: accedi.', 'academy-elearning' ),
			'email'   => __( 'Scrivi un indirizzo email valido.', 'academy-elearning' ),
			'weak'    => __( 'La password deve avere almeno 8 caratteri.', 'academy-elearning' ),
			'rate'    => __( 'Troppi tentativi: riprova tra un po\'.', 'academy-elearning' ),
			'already' => __( 'Hai già un abbonamento attivo.', 'academy-elearning' ),
			'api'     => __( 'Non è stato possibile avviare il pagamento. Riprova tra poco.', 'academy-elearning' ),
			'nobill'  => __( 'Non hai ancora un abbonamento da gestire.', 'academy-elearning' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Pulsante nel corso                                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * "Abbonati e accedi a tutto" per chi non ha accesso, se il corso rientra nell'abbonamento.
	 *
	 * @param array $actions   Pulsanti.
	 * @param int   $course_id Corso.
	 * @param int   $user_id   Utente.
	 * @param bool  $has       Ha già accesso.
	 * @return array
	 */
	public function add_subscribe_action( $actions, $course_id, $user_id, $has ) {
		if ( $has || ! AEL_Subscription_Stripe::is_ready() || get_post_meta( (int) $course_id, '_ael_excluded_from_subscription', true ) ) {
			return $actions;
		}
		$has_buy = false;
		foreach ( $actions as $action ) {
			if ( isset( $action['id'] ) && 'buy' === $action['id'] ) {
				$has_buy = true;
			}
		}
		$price = self::price_text();
		$actions[] = array(
			'id'       => 'subscribe',
			'label'    => '' !== $price
				/* translators: %s: prezzo dell'abbonamento, per esempio "19,00 € al mese" */
				? sprintf( __( 'Abbonati a %s e accedi a tutto', 'academy-elearning' ), $price )
				: __( 'Abbonati e accedi a tutto', 'academy-elearning' ),
			'url'      => self::page_url(),
			'type'     => $has_buy ? 'secondary' : 'primary',
			'priority' => 30,
		);
		return $actions;
	}

	/* ------------------------------------------------------------------ */
	/* Registrazione                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Torna alla pagina "Abbonati" con un messaggio.
	 *
	 * @param string $code Codice del messaggio.
	 */
	private function back( string $code ): void {
		wp_safe_redirect( self::page_url( array( 'ael_sub_err' => $code ) ) );
		exit;
	}

	/**
	 * Crea l'account (email e password) e porta l'utente al pagamento.
	 */
	public function handle_register(): void {
		check_admin_referer( 'ael_sub_register' );

		// Campo esca: una persona non lo compila.
		if ( ! empty( $_POST['ael_website'] ) ) {
			$this->back( 'api' );
		}
		if ( is_user_logged_in() ) {
			wp_safe_redirect( self::page_url() );
			exit;
		}

		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'ael_sub_reg_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 5 ) {
			$this->back( 'rate' );
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );

		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- le password non si modificano.
		if ( ! is_email( $email ) ) {
			$this->back( 'email' );
		}
		if ( strlen( $password ) < 8 ) {
			$this->back( 'weak' );
		}
		if ( email_exists( $email ) || username_exists( $email ) ) {
			$this->back( 'exists' );
		}

		$user_id = wp_create_user( $email, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			$this->back( 'api' );
		}
		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => (string) strtok( $email, '@' ),
			)
		);
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		do_action( 'wp_login', $email, get_userdata( $user_id ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		wp_safe_redirect( self::page_url() );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Checkout                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Dati di provenienza in forma compatta (Stripe accetta al massimo 500 caratteri per valore).
	 */
	private function compact_attribution(): string {
		$data = apply_filters( 'ael_checkout_attribution', array(), 'stripe' );
		if ( ! is_array( $data ) || empty( $data ) ) {
			return '';
		}
		$out = array();
		foreach ( array( 'first', 'last' ) as $which ) {
			if ( empty( $data[ $which ] ) || ! is_array( $data[ $which ] ) ) {
				continue;
			}
			foreach ( array( 's', 'm', 'c', 'k', 't', 'r' ) as $field ) {
				$value = isset( $data[ $which ][ $field ] ) ? mb_substr( (string) $data[ $which ][ $field ], 0, 30 ) : '';
				if ( '' !== $value ) {
					$out[ $which ][ $field ] = $value;
				}
			}
		}
		$json = (string) wp_json_encode( $out );
		return strlen( $json ) <= 480 ? $json : '';
	}

	/**
	 * Crea il cliente (se serve) e la sessione di Checkout, poi porta l'utente a Stripe.
	 */
	public function handle_start(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::page_url() ) );
			exit;
		}
		check_admin_referer( 'ael_sub_start' );

		if ( ! AEL_Subscription_Stripe::is_ready() ) {
			$this->back( 'api' );
		}
		if ( empty( $_POST['ael_withdrawal_consent'] ) ) {
			$this->back( 'consent' );
		}
		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		if ( self::is_subscribed( $user_id ) ) {
			$this->back( 'already' );
		}

		$consent_at = gmdate( 'Y-m-d H:i:s' );
		update_user_meta( $user_id, AEL_Subscription_Webhook::META_CONSENT, $consent_at );

		// Cliente Stripe: se c'è già lo si riusa.
		$customer = (string) get_user_meta( $user_id, AEL_Subscription_Webhook::META_CUSTOMER, true );
		if ( '' === $customer ) {
			$created = AEL_Subscription_Stripe::request(
				'POST',
				'customers',
				array(
					'email'    => $user->user_email,
					'name'     => $user->display_name,
					'metadata' => array( 'ael_user_id' => $user_id ),
				)
			);
			if ( is_wp_error( $created ) || empty( $created['id'] ) ) {
				$this->back( 'api' );
			}
			$customer = (string) $created['id'];
			update_user_meta( $user_id, AEL_Subscription_Webhook::META_CUSTOMER, $customer );
		}

		$return = self::page_url();
		$glue   = false === strpos( $return, '?' ) ? '?' : '&';
		$params = array(
			'mode'                => 'subscription',
			'customer'            => $customer,
			'client_reference_id' => (string) $user_id,
			'line_items'          => array(
				array(
					'price'    => AEL_Subscription_Stripe::settings()['price_id'],
					'quantity' => 1,
				),
			),
			// Le parentesi graffe devono restare tali: le sostituisce Stripe con l'ID della sessione.
			'success_url'         => $return . $glue . 'ael_sub=success&session_id={CHECKOUT_SESSION_ID}',
			'cancel_url'          => $return . $glue . 'ael_sub=cancel',
			'allow_promotion_codes' => 'true',
			'locale'              => 'it',
			'metadata'            => array(
				'ael_user_id'    => (string) $user_id,
				'ael_consent_at' => $consent_at,
			),
			'subscription_data'   => array(
				'metadata' => array( 'ael_user_id' => (string) $user_id ),
			),
		);
		$attr = $this->compact_attribution();
		if ( '' !== $attr ) {
			$params['subscription_data']['metadata']['ael_attr'] = $attr;
		}

		/**
		 * Permette ai moduli di cambiare i parametri della sessione di Checkout
		 * (per esempio per aggiungere la domanda "Come ci hai conosciuto?").
		 *
		 * @param array   $params Parametri per Stripe.
		 * @param WP_User $user   Utente.
		 */
		$params = apply_filters( 'ael_stripe_checkout_params', $params, $user );

		$session = AEL_Subscription_Stripe::request( 'POST', 'checkout/sessions', $params );
		$url     = ( ! is_wp_error( $session ) && isset( $session['url'] ) ) ? (string) $session['url'] : '';
		if ( 0 !== strpos( $url, 'https://checkout.stripe.com/' ) ) {
			$this->back( 'api' );
		}
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- indirizzo di Stripe, verificato sopra.
		exit;
	}

	/**
	 * Al ritorno da Stripe si conferma subito il pagamento (senza aspettare il webhook).
	 */
	public function maybe_handle_return(): void {
		// Parametri del ritorno da Stripe: l'autenticità si verifica leggendo la sessione dall'API e controllando l'utente.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['ael_sub'], $_GET['session_id'] ) || 'success' !== sanitize_key( wp_unslash( $_GET['ael_sub'] ) ) ) {
			return;
		}
		$session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! is_user_logged_in() || ! preg_match( '/^cs_[A-Za-z0-9_]+$/', $session_id ) || ! AEL_Subscription_Stripe::is_ready() ) {
			return;
		}

		$session = AEL_Subscription_Stripe::request( 'GET', 'checkout/sessions/' . rawurlencode( $session_id ) );
		if ( ! is_wp_error( $session )
			&& 'subscription' === ( $session['mode'] ?? '' )
			&& 'complete' === ( $session['status'] ?? '' )
			&& (int) ( $session['client_reference_id'] ?? 0 ) === get_current_user_id() ) {
			AEL_Subscription_Webhook::instance()->finish_checkout( $session );
			AEL_Access::flush_cache();
		}
		wp_safe_redirect( add_query_arg( 'ael_sub_ok', '1', AEL_Account::my_courses_url() ) );
		exit;
	}

	/**
	 * Porta l'utente al portale clienti di Stripe (cambio carta, disdetta, fatture).
	 */
	public function handle_portal(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( AEL_Account::my_courses_url() ) );
			exit;
		}
		check_admin_referer( 'ael_sub_portal' );

		$customer = (string) get_user_meta( get_current_user_id(), AEL_Subscription_Webhook::META_CUSTOMER, true );
		if ( '' === $customer || ! AEL_Subscription_Stripe::is_ready() ) {
			wp_safe_redirect( add_query_arg( 'ael_sub_err', 'nobill', AEL_Account::my_courses_url() ) );
			exit;
		}
		$session = AEL_Subscription_Stripe::request(
			'POST',
			'billing_portal/sessions',
			array(
				'customer'   => $customer,
				'return_url' => AEL_Account::my_courses_url(),
			)
		);
		$url = ( ! is_wp_error( $session ) && isset( $session['url'] ) ) ? (string) $session['url'] : '';
		if ( 0 !== strpos( $url, 'https://billing.stripe.com/' ) ) {
			wp_safe_redirect( add_query_arg( 'ael_sub_err', 'api', AEL_Account::my_courses_url() ) );
			exit;
		}
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- indirizzo di Stripe, verificato sopra.
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Riquadro in "I miei corsi"                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Stato dell'abbonamento sopra l'elenco dei corsi.
	 *
	 * @param int $user_id Utente.
	 */
	public function my_courses_box( $user_id ): void {
		$user_id = (int) $user_id;
		if ( ! AEL_Subscription_Stripe::is_ready() && '' === self::state_of( $user_id )['id'] ) {
			return;
		}
		// Solo codici da una lista fissa.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$just_paid = isset( $_GET['ael_sub_ok'] );
		$error     = isset( $_GET['ael_sub_err'] ) ? sanitize_key( wp_unslash( $_GET['ael_sub_err'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo AEL_Templates::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape nel template.
			'subscription-box',
			array(
				'state'      => self::state_of( $user_id ),
				'active'     => self::is_subscribed( $user_id ),
				'ready'      => AEL_Subscription_Stripe::is_ready(),
				'just_paid'  => $just_paid,
				'error'      => $error,
				'messages'   => self::messages(),
				'price_text' => self::price_text(),
				'page_url'   => self::page_url(),
			)
		);
	}
}

/**
 * Testo del consenso: lo stesso dell'acquisto singolo, anche se quel modulo è spento.
 */
final class AEL_Subscription_Consent {

	/**
	 * Testo della casella di consenso all'accesso immediato ai contenuti digitali.
	 */
	public static function get(): string {
		/** Documentato in AEL_Single_Purchase_Checkout::consent_text(). */
		return (string) apply_filters(
			'ael_withdrawal_consent_text',
			__( 'Chiedo l\'accesso immediato ai contenuti digitali e prendo atto che, una volta iniziata la fornitura, perdo il diritto di recesso.', 'academy-elearning' )
		);
	}
}
