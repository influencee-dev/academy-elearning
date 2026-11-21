<?php
/**
 * Iscrizione libera: chiunque può creare il proprio account studente.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pagina "Iscriviti" (shortcode `[ael_register]`): nome, email, password e consenso.
 * Crea un utente studente, lo collega e lo porta a "I miei corsi" (o dove stava
 * andando). Si accende dalla Configurazione (solo agenzia). Difese: nonce, campo
 * trappola per i robot e limite di tentativi per indirizzo IP.
 */
final class AEL_Registration {

	private const NONCE_ACTION = 'ael_register';
	private const NONCE_FIELD  = 'ael_register_nonce';

	/**
	 * Quante iscrizioni al massimo per indirizzo IP ogni ora.
	 */
	private const MAX_PER_HOUR = 5;

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Registration|null
	 */
	private static ?AEL_Registration $instance = null;

	/**
	 * Errori dell'ultimo invio, per codice.
	 *
	 * @var array<string, string>
	 */
	private array $errors = array();

	/**
	 * Valori già scritti, da rimettere nel modulo (mai la password).
	 *
	 * @var array<string, string>
	 */
	private array $old = array(
		'first_name' => '',
		'last_name'  => '',
		'email'      => '',
	);

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Registration {
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
	 * L'iscrizione libera è accesa?
	 */
	public static function enabled(): bool {
		return (bool) AEL_Settings::get( 'open_registration', 1 );
	}

	/**
	 * Indirizzo della pagina Iscriviti (vuoto se spenta o non creata).
	 */
	public static function url(): string {
		return self::enabled() ? AEL_Pages::url( 'register' ) : '';
	}

	/**
	 * Aggancia shortcode, invio del modulo e creazione della pagina.
	 */
	public function boot(): void {
		if ( ! self::enabled() ) {
			return;
		}
		add_filter( 'ael_pages_definitions', array( $this, 'add_page' ) );
		add_shortcode( 'ael_register', array( $this, 'shortcode' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle' ), 5 );
		add_action( 'ael_login_form_after', array( $this, 'print_login_link' ), 20 );
		add_action( 'init', array( $this, 'ensure_page' ), 99 );
	}

	/**
	 * Registra la pagina fra quelle di sistema.
	 *
	 * @param array $defs Pagine.
	 * @return array
	 */
	public function add_page( $defs ) {
		$defs['register'] = array(
			'option'  => 'ael_page_register',
			'title'   => 'Iscriviti',
			'slug'    => 'iscriviti',
			'content' => '[ael_register]',
		);
		return $defs;
	}

	/**
	 * Crea la pagina una sola volta, la prima volta che l'iscrizione è accesa.
	 */
	public function ensure_page(): void {
		if ( ! (int) get_option( 'ael_page_register' ) ) {
			AEL_Pages::ensure( 'register' );
		}
	}

	/**
	 * Sotto il modulo di accesso: collegamento a "Iscriviti".
	 */
	public function print_login_link(): void {
		$url = self::url();
		if ( '' === $url ) {
			return;
		}
		$redirect = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), '' ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo un indirizzo di ritorno controllato.
		if ( '' !== $redirect ) {
			$url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
		}
		echo '<p class="ael-login ael-login-register">' . esc_html__( 'Non hai un account?', 'academy-elearning' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Iscriviti', 'academy-elearning' ) . '</a></p>';
	}

	/**
	 * Indirizzo dove andare dopo l'iscrizione: quello richiesto (se è del sito) o "I miei corsi".
	 */
	private function destination(): string {
		$requested = '';
		if ( isset( $_REQUEST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- controllato con wp_validate_redirect.
			$requested = wp_validate_redirect( esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		return '' !== $requested ? $requested : AEL_Account::my_courses_url();
	}

	/**
	 * Se la pagina Iscriviti riceve il modulo, lo elabora prima di stampare la pagina.
	 */
	public function maybe_handle(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}
		$page_id = AEL_Pages::id( 'register' );
		if ( ! $page_id || ! is_page( $page_id ) ) {
			return;
		}
		if ( is_user_logged_in() ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			$this->errors['general'] = __( 'La pagina è scaduta. Riprova.', 'academy-elearning' );
			return;
		}

		// Campo trappola: i robot lo riempiono, le persone non lo vedono.
		if ( ! empty( $_POST['ael_website'] ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		// La password non si modifica: va salvata com'è (solo cancellata se è un file).
		$pass = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$ok   = ! empty( $_POST['consent'] );

		$this->old = array(
			'first_name' => $first,
			'last_name'  => $last,
			'email'      => $email,
		);

		if ( '' === $first ) {
			$this->errors['first_name'] = __( 'Scrivi il tuo nome.', 'academy-elearning' );
		}
		if ( ! is_email( $email ) ) {
			$this->errors['email'] = __( 'Scrivi un indirizzo email valido.', 'academy-elearning' );
		} elseif ( email_exists( $email ) ) {
			$this->errors['email'] = __( 'Esiste già un account con questa email. Accedi oppure recupera la password.', 'academy-elearning' );
		}
		if ( strlen( $pass ) < 8 ) {
			$this->errors['password'] = __( 'La password deve avere almeno 8 caratteri.', 'academy-elearning' );
		}
		if ( ! $ok ) {
			$this->errors['consent'] = __( 'Per iscriverti devi accettare l\'informativa sulla privacy.', 'academy-elearning' );
		}

		// Limite di tentativi per IP.
		$key   = 'ael_reg_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$count = (int) get_transient( $key );
		if ( $count >= self::MAX_PER_HOUR ) {
			$this->errors['general'] = __( 'Troppi tentativi. Riprova fra un po\'.', 'academy-elearning' );
		}
		if ( ! empty( $this->errors ) ) {
			return;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		$login = $this->unique_login( $email );
		$role  = get_role( 'customer' ) ? 'customer' : (string) get_option( 'default_role', 'subscriber' );
		$id    = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => $pass,
				'user_email'   => $email,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim( $first . ' ' . $last ),
				'nickname'     => $first,
				'role'         => $role,
			)
		);
		if ( is_wp_error( $id ) ) {
			$this->errors['general'] = __( 'Non sono riuscito a creare l\'account. Riprova.', 'academy-elearning' );
			return;
		}

		/**
		 * Un nuovo studente si è iscritto dalla pagina Iscriviti.
		 *
		 * @param int $user_id Utente creato.
		 */
		do_action( 'ael_user_registered', (int) $id );

		wp_set_current_user( (int) $id );
		wp_set_auth_cookie( (int) $id, true );
		wp_safe_redirect( $this->destination() );
		exit;
	}

	/**
	 * Nome utente libero ricavato dall'email.
	 *
	 * @param string $email Email.
	 */
	private function unique_login( string $email ): string {
		$base = sanitize_user( (string) strstr( $email, '@', true ), true );
		if ( '' === $base ) {
			$base = 'studente';
		}
		$login = $base;
		$n     = 1;
		while ( username_exists( $login ) ) {
			++$n;
			$login = $base . $n;
		}
		return $login;
	}

	/**
	 * Shortcode: la pagina Iscriviti.
	 */
	public function shortcode(): string {
		wp_enqueue_style( 'ael-front' );
		return AEL_Templates::render(
			'register',
			array(
				'logged_in'   => is_user_logged_in(),
				'errors'      => $this->errors,
				'old'         => $this->old,
				'nonce_field' => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'redirect_to' => isset( $_REQUEST['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), '' ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo un indirizzo di ritorno controllato.
				'login_url'   => AEL_Account::login_page_url() ?: wp_login_url(),
				'courses_url' => AEL_Account::my_courses_url(),
				'privacy_url' => function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '',
			)
		);
	}
}
