<?php
/**
 * Accesso con link monouso via email.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Come funziona, e perché è sicuro:
 *
 * - Il link contiene un codice casuale di 64 caratteri; sul sito si conserva solo
 *   la sua impronta (hash), mai il codice.
 * - Vale 15 minuti ed è monouso. Ogni nuova richiesta annulla quello precedente.
 * - Aprire il link NON fa entrare: mostra una pagina con un pulsante. Così i
 *   programmi che "controllano" i link nelle email non lo consumano al posto
 *   dell'utente.
 * - La risposta alla richiesta è sempre la stessa, che l'email esista o no.
 * - Le richieste sono limitate per email e per indirizzo di rete.
 * - Non vale per chi gestisce l'academy o il sito (titolare, agenzia, amministratori):
 *   per loro resta solo la password.
 */
final class AEL_Magic_Link {

	/**
	 * Prefisso delle chiavi temporanee.
	 */
	private const PREFIX = 'ael_ml_';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Magic_Link|null
	 */
	private static ?AEL_Magic_Link $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Magic_Link {
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
	 * Aggancia modulo di richiesta, pagina di conferma e accesso.
	 */
	public function boot(): void {
		add_action( 'ael_login_form_after', array( $this, 'render_form' ) );
		add_action( 'admin_post_nopriv_ael_magic_request', array( $this, 'handle_request' ) );
		add_action( 'admin_post_ael_magic_request', array( $this, 'handle_request' ) );
		add_action( 'admin_post_nopriv_ael_magic_login', array( $this, 'handle_login' ) );
		add_action( 'admin_post_ael_magic_login', array( $this, 'handle_login' ) );
		add_action( 'template_redirect', array( $this, 'maybe_show_confirm_page' ), 1 );
	}

	/* ------------------------------------------------------------------ */
	/* Codici                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Durata del link in secondi (di base 15 minuti).
	 */
	public function ttl(): int {
		/**
		 * Durata del link di accesso, in secondi.
		 *
		 * @param int $seconds Secondi (predefinito 900).
		 */
		return max( 60, (int) apply_filters( 'ael_magic_link_ttl', 15 * MINUTE_IN_SECONDS ) );
	}

	/**
	 * Impronta di un codice.
	 *
	 * @param string $token Codice.
	 */
	private function hash( string $token ): string {
		return hash( 'sha256', $token );
	}

	/**
	 * L'utente può entrare con un link? Non chi gestisce l'academy o il sito.
	 *
	 * @param int $user_id Utente.
	 */
	public function is_allowed_user( int $user_id ): bool {
		$allowed = $user_id > 0
			&& get_userdata( $user_id )
			&& ! user_can( $user_id, 'manage_options' )
			&& ! user_can( $user_id, AEL_Content::CAP )
			&& ! user_can( $user_id, AEL_Settings::CAP );

		/**
		 * Permette di escludere altri utenti dall'accesso con link.
		 *
		 * @param bool $allowed Consentito.
		 * @param int  $user_id Utente.
		 */
		return (bool) apply_filters( 'ael_magic_link_allowed_user', (bool) $allowed, $user_id );
	}

	/**
	 * Crea un codice per un utente. Annulla quello precedente.
	 *
	 * @param int $user_id Utente.
	 * @return string Codice di 64 caratteri esadecimali.
	 */
	public function issue_token( int $user_id ): string {
		$previous = (string) get_user_meta( $user_id, '_ael_magic_hash', true );
		if ( '' !== $previous ) {
			delete_transient( self::PREFIX . $previous );
		}
		$token = bin2hex( random_bytes( 32 ) );
		$hash  = $this->hash( $token );
		set_transient( self::PREFIX . $hash, array( 'user' => $user_id ), $this->ttl() );
		update_user_meta( $user_id, '_ael_magic_hash', $hash );
		return $token;
	}

	/**
	 * Utente a cui appartiene un codice valido, senza consumarlo. 0 se non valido o scaduto.
	 *
	 * @param string $token Codice.
	 */
	public function peek_token( string $token ): int {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return 0;
		}
		$data = get_transient( self::PREFIX . $this->hash( $token ) );
		return ( is_array( $data ) && ! empty( $data['user'] ) ) ? (int) $data['user'] : 0;
	}

	/**
	 * Consuma un codice (monouso). Restituisce l'utente, oppure 0 se non valido.
	 *
	 * @param string $token Codice.
	 */
	public function consume_token( string $token ): int {
		$user_id = $this->peek_token( $token );
		if ( ! $user_id ) {
			return 0;
		}
		$hash = $this->hash( $token );
		delete_transient( self::PREFIX . $hash );
		if ( (string) get_user_meta( $user_id, '_ael_magic_hash', true ) === $hash ) {
			delete_user_meta( $user_id, '_ael_magic_hash' );
		}
		return $user_id;
	}

	/**
	 * Email con la parte iniziale nascosta (m***@esempio.it).
	 *
	 * @param string $email Email.
	 */
	public function mask_email( string $email ): string {
		$parts = explode( '@', $email, 2 );
		if ( 2 !== count( $parts ) || '' === $parts[0] ) {
			return '';
		}
		return mb_substr( $parts[0], 0, 1 ) . '***@' . $parts[1];
	}

	/* ------------------------------------------------------------------ */
	/* Limiti                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Conta una richiesta e dice se è ancora ammessa: al massimo 3 per email e
	 * 10 per indirizzo di rete ogni 15 minuti.
	 *
	 * @param string $email Email richiesta.
	 * @param string $ip    Indirizzo di rete.
	 */
	public function allow_request( string $email, string $ip ): bool {
		$limits = array(
			self::PREFIX . 'e_' . md5( strtolower( $email ) ) => 3,
			self::PREFIX . 'i_' . md5( $ip )                  => 10,
		);
		$ok = true;
		foreach ( $limits as $key => $max ) {
			$count = (int) get_transient( $key );
			if ( $count >= $max ) {
				$ok = false;
			}
			set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
		}
		return $ok;
	}

	/**
	 * Indirizzo di rete del visitatore.
	 */
	private function ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/* ------------------------------------------------------------------ */
	/* Pagina di accesso: richiesta del link                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Modulo "Accedi con un link via email", sotto il modulo di accesso.
	 */
	public function render_form(): void {
		// Solo il messaggio di conferma dopo la richiesta: nessun dato letto o salvato.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sent = isset( $_GET['ael_magic_sent'] );
		?>
		<div class="ael-magic">
			<hr>
			<h3><?php echo esc_html__( 'Oppure accedi con un link via email', 'academy-elearning' ); ?></h3>
			<?php if ( $sent ) : ?>
				<p class="ael-note" role="status">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: minuti di validità del link */
							__( 'Se l\'indirizzo è registrato, ti abbiamo inviato un link. Vale %d minuti e si usa una volta sola.', 'academy-elearning' ),
							(int) round( $this->ttl() / MINUTE_IN_SECONDS )
						)
					);
					?>
				</p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ael_magic_request">
				<?php wp_nonce_field( 'ael_magic_request' ); ?>
				<p>
					<label for="ael-magic-email"><?php echo esc_html__( 'La tua email', 'academy-elearning' ); ?></label>
					<input type="email" id="ael-magic-email" name="email" required autocomplete="email">
				</p>
				<p><button type="submit" class="ael-button ael-button--secondary"><?php echo esc_html__( 'Inviami il link', 'academy-elearning' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Dove tornare dopo la richiesta: la pagina di accesso del sito.
	 *
	 * @param array<string, mixed> $args Parametri da aggiungere.
	 */
	private function login_page( array $args = array() ): string {
		$url = AEL_Account::login_page_url();
		if ( '' === $url ) {
			$url = wp_login_url();
		}
		return add_query_arg( $args, $url );
	}

	/**
	 * Riceve la richiesta e, se l'indirizzo è di uno studente o docente, spedisce il link.
	 * La risposta è sempre la stessa.
	 */
	public function handle_request(): void {
		check_admin_referer( 'ael_magic_request' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( '' !== $email && is_email( $email ) && $this->allow_request( $email, $this->ip() ) ) {
			$user = get_user_by( 'email', $email );
			if ( $user && $this->is_allowed_user( (int) $user->ID ) ) {
				$this->send_link( $user );
			}
		}

		wp_safe_redirect( $this->login_page( array( 'ael_magic_sent' => 1 ) ) );
		exit;
	}

	/**
	 * Spedisce l'email con il link.
	 *
	 * @param WP_User $user Utente.
	 */
	private function send_link( WP_User $user ): void {
		$token   = $this->issue_token( (int) $user->ID );
		$url     = add_query_arg( 'ael_magic', $token, home_url( '/' ) );
		$minutes = (int) round( $this->ttl() / MINUTE_IN_SECONDS );

		$subject = sprintf(
			/* translators: %s: nome del sito */
			__( 'Il tuo link di accesso a %s', 'academy-elearning' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$body = sprintf(
			/* translators: 1: nome dell'utente, 2: indirizzo del link, 3: minuti di validità */
			__( "Ciao %1\$s,\n\nper accedere apri questo indirizzo:\n%2\$s\n\nIl link vale %3\$d minuti e si può usare una volta sola. Se non l'hai chiesto tu, ignora questa email: nessuno può entrare senza aprirlo.\n", 'academy-elearning' ),
			$user->display_name,
			$url,
			$minutes
		);
		wp_mail( $user->user_email, $subject, $body );
	}

	/* ------------------------------------------------------------------ */
	/* Apertura del link e accesso                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Aprendo il link si vede una pagina con un pulsante: l'accesso avviene solo
	 * premendolo, così i controlli automatici delle email non consumano il codice.
	 */
	public function maybe_show_confirm_page(): void {
		// Il codice del link è il segreto: non si usa un nonce per aprire la pagina di conferma.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['ael_magic'] ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = sanitize_text_field( wp_unslash( $_GET['ael_magic'] ) );

		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex' );

		$user_id = $this->peek_token( $token );
		$user    = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user || ! $this->is_allowed_user( $user_id ) ) {
			wp_die(
				'<p>' . esc_html__( 'Questo link non è valido o è scaduto. Richiedine uno nuovo dalla pagina di accesso.', 'academy-elearning' ) . '</p><p><a href="' . esc_url( $this->login_page() ) . '">' . esc_html__( 'Vai alla pagina di accesso', 'academy-elearning' ) . '</a></p>',
				esc_html__( 'Link non valido', 'academy-elearning' ),
				array( 'response' => 200 )
			);
		}

		ob_start();
		?>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: email con la parte iniziale nascosta */
					__( 'Stai per accedere come %s.', 'academy-elearning' ),
					$this->mask_email( $user->user_email )
				)
			);
			?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ael_magic_login">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<?php wp_nonce_field( 'ael_magic_login' ); ?>
			<button type="submit" class="button button-primary"><?php echo esc_html__( 'Accedi', 'academy-elearning' ); ?></button>
		</form>
		<?php
		wp_die( (string) ob_get_clean(), esc_html__( 'Accedi', 'academy-elearning' ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costruito sopra con funzioni di escape.
	}

	/**
	 * Premuto il pulsante: si consuma il codice e si fa entrare l'utente.
	 */
	public function handle_login(): void {
		check_admin_referer( 'ael_magic_login' );

		$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$user_id = $this->consume_token( $token );
		$user    = $user_id ? get_userdata( $user_id ) : false;

		if ( ! $user || ! $this->is_allowed_user( $user_id ) ) {
			wp_safe_redirect( $this->login_page( array( 'ael_login' => 'failed' ) ) );
			exit;
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		/** Come avviene per ogni accesso di WordPress. */
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$destination = apply_filters( 'login_redirect', AEL_Account::my_courses_url(), '', $user );
		wp_safe_redirect( (string) $destination );
		exit;
	}
}
