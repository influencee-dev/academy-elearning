<?php
/**
 * Pagina "Prova accessi": serve all'agenzia per verificare la logica di accesso.
 * Compare solo con WP_DEBUG acceso e solo a chi ha la capacità dell'agenzia.
 * Prima della consegna al cliente WP_DEBUG è spento, quindi non si vede.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Permette di controllare "questo utente vede questa lezione?" e di creare o
 * revocare concessioni di prova (riferimento "prova").
 */
final class AEL_Admin_Access_Debug {

	private const PAGE   = 'ael-access-debug';
	private const ACTION = 'ael_access_debug';
	private const REF    = 'prova';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Admin_Access_Debug|null
	 */
	private static ?AEL_Admin_Access_Debug $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Admin_Access_Debug {
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
	 * Aggancia menu e azione, solo con WP_DEBUG.
	 */
	public function boot(): void {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}
		add_action( 'admin_menu', array( $this, 'register_menu' ), 12 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Voce di menu sotto Academy.
	 */
	public function register_menu(): void {
		add_submenu_page(
			AEL_Admin::MENU,
			__( 'Prova accessi', 'academy-elearning' ),
			__( 'Prova accessi', 'academy-elearning' ),
			AEL_Settings::CAP,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Crea o revoca una concessione di prova.
	 */
	public function handle(): void {
		if ( ! current_user_can( AEL_Settings::CAP ) ) {
			wp_die( esc_html__( 'Non hai il permesso di farlo.', 'academy-elearning' ), 403 );
		}
		check_admin_referer( self::ACTION );

		$user_id   = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$course_id = isset( $_POST['course_id'] ) ? absint( wp_unslash( $_POST['course_id'] ) ) : 0;
		$source    = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$op        = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$days      = isset( $_POST['days'] ) ? absint( wp_unslash( $_POST['days'] ) ) : 0;

		$message = 'err';
		if ( 'grant' === $op ) {
			$ends    = $days > 0 ? gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ) : null;
			$message = AEL_Access::grant( $user_id, $course_id, $source, self::REF, $ends ) ? 'granted' : 'err';
		} elseif ( 'revoke' === $op ) {
			$message = AEL_Access::revoke( $source, self::REF, $user_id, $course_id ) > 0 ? 'revoked' : 'none';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE,
					'user_id' => $user_id,
					'done'    => $message,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Mostra la pagina.
	 */
	public function render(): void {
		if ( ! current_user_can( AEL_Settings::CAP ) ) {
			wp_die( esc_html__( 'Non hai il permesso di vedere questa pagina.', 'academy-elearning' ), 403 );
		}

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- sola lettura, protetta dalla capacità.
		$user_id   = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : get_current_user_id();
		$lesson_id = isset( $_GET['lesson_id'] ) ? absint( wp_unslash( $_GET['lesson_id'] ) ) : 0;
		$done      = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
        // phpcs:enable

		$users   = get_users(
			array(
				'orderby' => 'display_name',
				'fields'  => array( 'ID', 'display_name', 'user_login' ),
			)
		);
		$courses = get_posts(
			array(
				'post_type'   => AEL_Content::COURSE,
				'numberposts' => -1,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$lessons = get_posts(
			array(
				'post_type'   => AEL_Content::LESSON,
				'numberposts' => -1,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'orderby'     => array(
					'post_parent' => 'ASC',
					'menu_order'  => 'ASC',
				),
			)
		);
		$notes   = array(
			'granted' => __( 'Concessione di prova salvata.', 'academy-elearning' ),
			'revoked' => __( 'Concessione di prova revocata.', 'academy-elearning' ),
			'none'    => __( 'Nessuna concessione di prova da revocare per questa combinazione.', 'academy-elearning' ),
			'err'     => __( 'Non è stato possibile completare l\'operazione.', 'academy-elearning' ),
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Prova accessi', 'academy-elearning' ); ?></h1>
			<p><?php echo esc_html__( 'Strumento per l\'agenzia, visibile solo con WP_DEBUG acceso. Non è parte di ciò che vede il cliente.', 'academy-elearning' ); ?></p>
			<?php if ( isset( $notes[ $done ] ) ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $notes[ $done ] ); ?></p></div>
			<?php endif; ?>

			<h2><?php echo esc_html__( '1. Chi può vedere cosa?', 'academy-elearning' ); ?></h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<p>
					<label><?php echo esc_html__( 'Utente', 'academy-elearning' ); ?>
						<select name="user_id"><?php $this->user_options( $users, $user_id ); ?></select></label>
					<label><?php echo esc_html__( 'Lezione', 'academy-elearning' ); ?>
						<select name="lesson_id">
							<option value="0"><?php echo esc_html__( '— Nessuna —', 'academy-elearning' ); ?></option>
							<?php foreach ( $lessons as $lesson ) : ?>
								<option value="<?php echo esc_attr( (string) $lesson->ID ); ?>" <?php selected( $lesson_id, $lesson->ID ); ?>><?php echo esc_html( $this->lesson_label( $lesson ) ); ?></option>
							<?php endforeach; ?>
						</select></label>
					<button class="button"><?php echo esc_html__( 'Controlla', 'academy-elearning' ); ?></button>
				</p>
			</form>

			<?php if ( $user_id && get_userdata( $user_id ) ) : ?>
				<h3><?php echo esc_html__( 'Corsi', 'academy-elearning' ); ?></h3>
				<table class="widefat striped" style="max-width:700px"><tbody>
					<?php foreach ( $courses as $course ) : ?>
						<tr>
							<td><?php echo esc_html( get_the_title( $course ) ); ?><?php echo 'publish' === $course->post_status ? '' : ' (' . esc_html( $course->post_status ) . ')'; ?></td>
							<td><?php echo AEL_Access::user_can_access_course( $user_id, $course->ID ) ? '<strong>' . esc_html__( 'Accesso sì', 'academy-elearning' ) . '</strong>' : esc_html__( 'Accesso no', 'academy-elearning' ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $courses ) ) : ?>
						<tr><td><?php echo esc_html__( 'Nessun corso.', 'academy-elearning' ); ?></td></tr>
					<?php endif; ?>
				</tbody></table>

				<?php if ( $lesson_id ) : ?>
					<h3><?php echo esc_html__( 'Lezione scelta', 'academy-elearning' ); ?></h3>
					<p>
						<?php echo esc_html( get_the_title( $lesson_id ) ); ?> →
						<strong><?php echo AEL_Access::user_can_access_lesson( $user_id, $lesson_id ) ? esc_html__( 'Accesso sì', 'academy-elearning' ) : esc_html__( 'Accesso no', 'academy-elearning' ); ?></strong>
						<br>
						<span class="description"><?php echo esc_html__( 'Visitatore non collegato:', 'academy-elearning' ); ?>
							<?php echo AEL_Access::user_can_access_lesson( 0, $lesson_id ) ? esc_html__( 'sì (anteprima gratuita)', 'academy-elearning' ) : esc_html__( 'no', 'academy-elearning' ); ?></span>
					</p>
				<?php endif; ?>

				<h3><?php echo esc_html__( 'Concessioni dell\'utente', 'academy-elearning' ); ?></h3>
				<?php $grants = AEL_Access::all_grants_of( $user_id ); ?>
				<table class="widefat striped" style="max-width:900px">
					<thead><tr>
						<th><?php echo esc_html__( 'Corso', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Origine', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Riferimento', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Stato', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Fine (UTC)', 'academy-elearning' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $grants as $grant ) : ?>
						<tr>
							<td><?php echo (int) $grant['course_id'] ? esc_html( get_the_title( (int) $grant['course_id'] ) ) : esc_html__( 'Tutto il catalogo', 'academy-elearning' ); ?></td>
							<td><?php echo esc_html( (string) $grant['source'] ); ?></td>
							<td><?php echo esc_html( (string) $grant['source_ref'] ); ?></td>
							<td><?php echo esc_html( (string) $grant['status'] ); ?></td>
							<td><?php echo esc_html( $grant['ends_at'] ? (string) $grant['ends_at'] : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $grants ) ) : ?>
						<tr><td colspan="5"><?php echo esc_html__( 'Nessuna concessione.', 'academy-elearning' ); ?></td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php echo esc_html__( '2. Concessione di prova', 'academy-elearning' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
				<p>
					<label><?php echo esc_html__( 'Utente', 'academy-elearning' ); ?>
						<select name="user_id"><?php $this->user_options( $users, $user_id ); ?></select></label>
					<label><?php echo esc_html__( 'Corso', 'academy-elearning' ); ?>
						<select name="course_id">
							<option value="0"><?php echo esc_html__( 'Tutto il catalogo', 'academy-elearning' ); ?></option>
							<?php foreach ( $courses as $course ) : ?>
								<option value="<?php echo esc_attr( (string) $course->ID ); ?>"><?php echo esc_html( get_the_title( $course ) ); ?></option>
							<?php endforeach; ?>
						</select></label>
					<label><?php echo esc_html__( 'Origine', 'academy-elearning' ); ?>
						<select name="source">
							<option value="manual"><?php echo esc_html__( 'manual (manuale)', 'academy-elearning' ); ?></option>
							<option value="purchase"><?php echo esc_html__( 'purchase (acquisto)', 'academy-elearning' ); ?></option>
							<option value="subscription"><?php echo esc_html__( 'subscription (abbonamento)', 'academy-elearning' ); ?></option>
						</select></label>
					<label><?php echo esc_html__( 'Durata in giorni (vuoto = senza scadenza)', 'academy-elearning' ); ?>
						<input type="number" name="days" min="0" step="1" style="width:80px"></label>
				</p>
				<p>
					<button class="button button-primary" name="op" value="grant"><?php echo esc_html__( 'Concedi', 'academy-elearning' ); ?></button>
					<button class="button" name="op" value="revoke"><?php echo esc_html__( 'Revoca (stessa combinazione)', 'academy-elearning' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Opzioni del menu a tendina degli utenti.
	 *
	 * @param array<int, object> $users    Utenti.
	 * @param int                $selected Utente scelto.
	 */
	private function user_options( array $users, int $selected ): void {
		foreach ( $users as $user ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $user->ID,
				selected( $selected, (int) $user->ID, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output di selected().
				esc_html( $user->display_name . ' (' . $user->user_login . ')' )
			);
		}
	}

	/**
	 * Etichetta "Corso › Modulo › Lezione".
	 *
	 * @param WP_Post $lesson Lezione.
	 */
	private function lesson_label( WP_Post $lesson ): string {
		$module = $lesson->post_parent ? get_post( (int) $lesson->post_parent ) : null;
		$course = $module && $module->post_parent ? get_the_title( (int) $module->post_parent ) : '—';
		return $course . ' › ' . ( $module ? get_the_title( $module ) : '—' ) . ' › ' . get_the_title( $lesson );
	}
}
