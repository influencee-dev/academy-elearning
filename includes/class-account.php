<?php
/**
 * Accesso dello studente e area "I miei corsi".
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * La pagina di accesso con lo shortcode [ael_login] e l'area personale con
 * [ael_my_courses]. Gli utenti sono utenti WordPress normali (email e password):
 * qui si cambia solo dove atterrano e dove vedono gli errori.
 */
final class AEL_Account {

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Account|null
	 */
	private static ?AEL_Account $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Account {
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
	 * Aggancia shortcode e comportamento dell'accesso.
	 */
	public function boot(): void {
		add_shortcode( 'ael_login', array( $this, 'shortcode_login' ) );
		add_shortcode( 'ael_my_courses', array( $this, 'shortcode_my_courses' ) );
		add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 3 );
		add_filter( 'login_redirect', array( $this, 'filter_login_redirect' ), 10, 3 );
		add_action( 'wp_login_failed', array( $this, 'on_login_failed' ), 10, 1 );
		add_filter( 'show_admin_bar', array( $this, 'filter_admin_bar' ) );
	}

	/**
	 * Indirizzo dell'area "I miei corsi".
	 */
	public static function my_courses_url(): string {
		return AEL_Pages::url( 'my_courses', home_url( '/' ) );
	}

	/**
	 * Indirizzo della pagina di accesso del sito (vuoto se non esiste).
	 */
	public static function login_page_url(): string {
		return AEL_Pages::url( 'login' );
	}

	/**
	 * L'utente è uno studente (non gestisce l'academy e non scrive contenuti)?
	 *
	 * @param WP_User|int $user Utente.
	 */
	private static function is_student( $user ): bool {
		$user_id = $user instanceof WP_User ? (int) $user->ID : (int) $user;
		return $user_id > 0
			&& ! user_can( $user_id, 'edit_posts' )
			&& ! user_can( $user_id, AEL_Content::CAP )
			&& ! user_can( $user_id, AEL_Settings::CAP );
	}

	/**
	 * Porta chi deve accedere alla pagina di accesso del sito. Gli indirizzi
	 * dell'amministrazione restano su wp-login.php, così l'agenzia entra sempre.
	 *
	 * @param string $login_url    Indirizzo di accesso di WordPress.
	 * @param string $redirect     Dove tornare dopo l'accesso.
	 * @param bool   $force_reauth Richiede un nuovo accesso.
	 */
	public function filter_login_url( $login_url, $redirect = '', $force_reauth = false ) {
		$page = self::login_page_url();
		if ( '' === $page || $force_reauth || false !== strpos( (string) $redirect, 'wp-admin' ) ) {
			return $login_url;
		}
		return '' !== (string) $redirect ? add_query_arg( 'redirect_to', rawurlencode( (string) $redirect ), $page ) : $page;
	}

	/**
	 * Dopo l'accesso uno studente atterra su "I miei corsi", a meno che stesse
	 * andando in una pagina precisa del sito (per esempio un corso).
	 *
	 * @param string           $redirect_to Destinazione calcolata.
	 * @param string           $requested   Destinazione richiesta.
	 * @param WP_User|WP_Error $user        Utente.
	 */
	public function filter_login_redirect( $redirect_to, $requested, $user ) {
		if ( ! $user instanceof WP_User || ! self::is_student( $user ) ) {
			return $redirect_to;
		}
		$admin = admin_url();
		if ( '' === (string) $requested || 0 === strpos( (string) $requested, $admin ) ) {
			return self::my_courses_url();
		}
		return $redirect_to;
	}

	/**
	 * Se l'accesso è partito dalla pagina Accedi e fallisce, si torna lì con un
	 * messaggio chiaro invece di finire su wp-login.php.
	 *
	 * @param string $username Nome utente digitato.
	 */
	public function on_login_failed( $username ): void {
		$page    = self::login_page_url();
		$referer = wp_get_referer();
		if ( '' === $page || ! $referer ) {
			return;
		}
		$clean = untrailingslashit( strtok( $referer, '?' ) );
		if ( untrailingslashit( $page ) !== $clean ) {
			return;
		}
		$args = array( 'ael_login' => 'failed' );
		// Si mantiene la destinazione, se c'era.
		$query = (string) wp_parse_url( $referer, PHP_URL_QUERY );
		parse_str( $query, $params );
		if ( ! empty( $params['redirect_to'] ) ) {
			$args['redirect_to'] = rawurlencode( (string) $params['redirect_to'] );
		}
		wp_safe_redirect( add_query_arg( $args, $page ) );
		exit;
	}

	/**
	 * Gli studenti non vedono la barra di amministrazione di WordPress.
	 *
	 * @param bool $show Mostrare la barra.
	 */
	public function filter_admin_bar( $show ) {
		if ( is_user_logged_in() && self::is_student( get_current_user_id() ) ) {
			/**
			 * Permette di lasciare la barra di amministrazione agli studenti.
			 *
			 * @param bool $hide Nasconderla (predefinito: sì).
			 */
			return ! apply_filters( 'ael_hide_admin_bar_for_students', true );
		}
		return $show;
	}

	/* ------------------------------------------------------------------ */
	/* Accesso                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Shortcode [ael_login].
	 */
	public function shortcode_login(): string {
		wp_enqueue_style( 'ael-front' );

		$default = self::my_courses_url();
		$target  = $default;
		// Solo lettura dell'indirizzo di destinazione, validato: niente nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['redirect_to'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$target = wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), $default );
		}

		$form = '';
		if ( ! is_user_logged_in() ) {
			$form = wp_login_form(
				array(
					'echo'           => false,
					'redirect'       => $target,
					'label_username' => __( 'Email o nome utente', 'academy-elearning' ),
					'label_password' => __( 'Password', 'academy-elearning' ),
					'label_remember' => __( 'Ricordami', 'academy-elearning' ),
					'label_log_in'   => __( 'Accedi', 'academy-elearning' ),
					'remember'       => true,
				)
			);
		}

		return AEL_Templates::render(
			'login',
			array(
				'logged_in'   => is_user_logged_in(),
				'user'        => wp_get_current_user(),
				'failed'      => isset( $_GET['ael_login'] ) && 'failed' === $_GET['ael_login'], // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'form'        => $form,
				'lost_url'    => wp_lostpassword_url( $target ),
				'courses_url' => self::my_courses_url(),
				'logout_url'  => wp_logout_url( home_url( '/' ) ),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* I miei corsi                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Percentuale completata di un corso (0-100), o null se non si conosce.
	 * Il tracciamento dei progressi si aggancia con il filtro ael_course_progress.
	 *
	 * @param int $course_id Corso.
	 * @param int $user_id   Utente.
	 */
	public static function progress( int $course_id, int $user_id ): ?int {
		/**
		 * Percentuale di completamento di un corso per un utente.
		 *
		 * @param int|null $percent   Percentuale 0-100, o null se non disponibile.
		 * @param int      $course_id Corso.
		 * @param int      $user_id   Utente.
		 */
		$percent = apply_filters( 'ael_course_progress', null, $course_id, $user_id );
		return is_numeric( $percent ) ? max( 0, min( 100, (int) $percent ) ) : null;
	}

	/**
	 * Shortcode [ael_my_courses].
	 */
	public function shortcode_my_courses(): string {
		wp_enqueue_style( 'ael-front' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			$login = self::login_page_url();
			$here  = (string) get_permalink();
			return AEL_Templates::render(
				'my-courses',
				array(
					'logged_in' => false,
					'login_url' => '' !== $login ? add_query_arg( 'redirect_to', rawurlencode( $here ), $login ) : wp_login_url( $here ),
				)
			);
		}

		$ids   = AEL_Access::accessible_course_ids( $user_id );
		$cards = array();
		if ( ! empty( $ids ) ) {
			$query = AEL_Catalog::query_courses(
				array(
					'posts_per_page' => -1,
					'post__in'       => $ids,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			foreach ( AEL_Catalog::instance()->cards( $query->posts ) as $card ) {
				$course_id = (int) $card['course']->ID;
				$resume    = AEL_Catalog::resume( $course_id, $user_id );
				$card['resume_url'] = $resume['lesson_id'] ? (string) get_permalink( $resume['lesson_id'] ) : $card['url'];
				$card['started']    = (bool) $resume['started'];
				$card['progress']   = self::progress( $course_id, $user_id );
				$cards[]            = $card;
			}
		}

		$user = wp_get_current_user();
		return AEL_Templates::render(
			'my-courses',
			array(
				'logged_in'   => true,
				'user'        => $user,
				'cards'       => $cards,
				'is_manager'  => current_user_can( AEL_Content::CAP ),
				'catalog_url' => AEL_Catalog::catalog_url(),
				'logout_url'  => wp_logout_url( home_url( '/' ) ),
			)
		);
	}
}
