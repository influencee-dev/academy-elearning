<?php
/**
 * Pagina Studenti: elenco, dettaglio, concessioni manuali, esportazione.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Per il titolare dell'academy (capacità ael_manage_academy). Le concessioni
 * create qui hanno origine "manuale". Quelle da acquisto o abbonamento sono in
 * sola lettura: le gestiscono i pagamenti.
 */
final class AEL_Admin_Students {

	public const PAGE = 'ael-students';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Admin_Students|null
	 */
	private static ?AEL_Admin_Students $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Admin_Students {
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
		add_action( 'admin_menu', array( $this, 'register_menu' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_ael_student_grant', array( $this, 'handle_grant' ) );
		add_action( 'admin_post_ael_student_revoke', array( $this, 'handle_revoke' ) );
		add_action( 'admin_post_ael_students_export', array( $this, 'handle_export' ) );
	}

	/**
	 * Voce di menu.
	 */
	public function register_menu(): void {
		add_submenu_page(
			AEL_Admin::MENU,
			__( 'Studenti', 'academy-elearning' ),
			__( 'Studenti', 'academy-elearning' ),
			AEL_Content::CAP,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Stile delle pagine dell'academy.
	 */
	public function enqueue(): void {
		// Solo per scegliere dove caricare lo stile: nessun dato viene letto o salvato.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( in_array( $page, array( AEL_Admin::MENU, self::PAGE ), true ) ) {
			wp_enqueue_style( 'ael-admin-dashboard', AEL_URL . 'assets/admin-dashboard.css', array(), AEL_VERSION );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Utilità                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Messaggi mostrati dopo un'azione.
	 *
	 * @return array<string, array{0: string, 1: string}> Chiave => tipo e testo.
	 */
	private function messages(): array {
		return array(
			'granted'   => array( 'success', __( 'Accesso concesso.', 'academy-elearning' ) ),
			'revoked'   => array( 'success', __( 'Accesso revocato.', 'academy-elearning' ) ),
			'nouser'    => array( 'error', __( 'Non ho trovato nessun utente con quell\'email. Crea prima l\'utente da Utenti → Aggiungi.', 'academy-elearning' ) ),
			'invalid'   => array( 'error', __( 'Dati non validi: controlla il corso scelto.', 'academy-elearning' ) ),
			'notmanual' => array( 'error', __( 'Questo accesso arriva da un pagamento e non si revoca da qui.', 'academy-elearning' ) ),
		);
	}

	/**
	 * Indirizzo della pagina (elenco o dettaglio di un utente).
	 *
	 * @param int                  $user_id Utente (0 = elenco).
	 * @param array<string, mixed> $args    Altri parametri.
	 */
	private function url( int $user_id = 0, array $args = array() ): string {
		$args['page'] = self::PAGE;
		if ( $user_id ) {
			$args['user'] = $user_id;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Data e ora locali da un valore UTC del database.
	 *
	 * @param string $utc Data UTC "Y-m-d H:i:s".
	 */
	private function local_date( string $utc ): string {
		if ( '' === $utc ) {
			return '—';
		}
		$ts = strtotime( $utc . ' UTC' );
		return $ts ? wp_date( 'd/m/Y H:i', $ts ) : '—';
	}

	/**
	 * Corsi pubblicati per i menu a tendina.
	 *
	 * @return WP_Post[]
	 */
	private function courses(): array {
		return get_posts(
			array(
				'post_type'   => AEL_Content::COURSE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
	}

	/**
	 * Modulo per concedere un accesso.
	 *
	 * @param int $user_id Utente già scelto (0 = si indica l'email).
	 */
	private function grant_form( int $user_id ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ael-grant-form">
			<input type="hidden" name="action" value="ael_student_grant">
			<?php wp_nonce_field( 'ael_student_grant' ); ?>
			<?php if ( $user_id ) : ?>
				<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user_id ); ?>">
			<?php else : ?>
				<label>
					<span><?php echo esc_html__( 'Email dello studente', 'academy-elearning' ); ?></span>
					<input type="email" name="email" required class="regular-text">
				</label>
			<?php endif; ?>
			<label>
				<span><?php echo esc_html__( 'Corso', 'academy-elearning' ); ?></span>
				<select name="course_id">
					<option value="0"><?php echo esc_html__( 'Tutto il catalogo', 'academy-elearning' ); ?></option>
					<?php foreach ( $this->courses() as $course ) : ?>
						<option value="<?php echo esc_attr( (string) $course->ID ); ?>"><?php echo esc_html( get_the_title( $course ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php echo esc_html__( 'Durata in giorni (vuoto = senza scadenza)', 'academy-elearning' ); ?></span>
				<input type="number" name="days" min="1" max="3650" class="small-text">
			</label>
			<?php submit_button( __( 'Concedi accesso', 'academy-elearning' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Mostra l'avviso dopo un'azione.
	 */
	private function notice(): void {
		// Solo il codice di un messaggio da una lista fissa: nessun dato viene scritto.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key      = isset( $_GET['ael_msg'] ) ? sanitize_key( wp_unslash( $_GET['ael_msg'] ) ) : '';
		$messages = $this->messages();
		if ( isset( $messages[ $key ] ) ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $key ][0] ),
				esc_html( $messages[ $key ][1] )
			);
		}
	}

	/* ------------------------------------------------------------------ */
	/* Pagina                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Elenco o dettaglio, a seconda dell'indirizzo.
	 */
	public function render(): void {
		if ( ! current_user_can( AEL_Content::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per vedere questa pagina.', 'academy-elearning' ), 403 );
		}
		// Parametro di sola lettura per scegliere cosa mostrare.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$user_id = isset( $_GET['user'] ) ? absint( wp_unslash( $_GET['user'] ) ) : 0;
		echo '<div class="wrap ael-admin">';
		$this->notice();
		if ( $user_id ) {
			$this->render_detail( $user_id );
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	/**
	 * Elenco degli studenti.
	 */
	private function render_list(): void {
		// Ricerca e pagina: solo lettura.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$per_page = 20;
		$ids      = AEL_Stats::candidate_student_ids();
		$users    = array();
		$total    = 0;
		if ( ! empty( $ids ) ) {
			$query = new WP_User_Query(
				array(
					'include'        => $ids,
					'number'         => $per_page,
					'paged'          => $paged,
					'orderby'        => 'display_name',
					'order'          => 'ASC',
					'search'         => '' !== $search ? '*' . $search . '*' : '',
					'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				)
			);
			$users = $query->get_results();
			$total = (int) $query->get_total();
		}
		$summaries = AEL_Stats::summaries( wp_list_pluck( $users, 'ID' ) );
		$export    = wp_nonce_url( admin_url( 'admin-post.php?action=ael_students_export' ), 'ael_students_export' );
		?>
		<h1 class="wp-heading-inline"><?php echo esc_html__( 'Studenti', 'academy-elearning' ); ?></h1>
		<a class="page-title-action" href="<?php echo esc_url( $export ); ?>"><?php echo esc_html__( 'Esporta CSV', 'academy-elearning' ); ?></a>
		<hr class="wp-header-end">

		<div class="ael-box">
			<h2><?php echo esc_html__( 'Concedi un accesso', 'academy-elearning' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Per dare un corso in omaggio o con una scadenza. Lo studente deve già avere un utente: si crea da Utenti → Aggiungi.', 'academy-elearning' ); ?></p>
			<?php $this->grant_form( 0 ); ?>
		</div>

		<form method="get" class="ael-search">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
			<label class="screen-reader-text" for="ael-student-search"><?php echo esc_html__( 'Cerca uno studente', 'academy-elearning' ); ?></label>
			<input type="search" id="ael-student-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Nome o email', 'academy-elearning' ); ?>">
			<?php submit_button( __( 'Cerca', 'academy-elearning' ), 'secondary', '', false ); ?>
		</form>

		<?php if ( empty( $users ) ) : ?>
			<p><?php echo esc_html( '' !== $search ? __( 'Nessuno studente trovato.', 'academy-elearning' ) : __( 'Non ci sono ancora studenti con un accesso o dei progressi.', 'academy-elearning' ) ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php echo esc_html__( 'Studente', 'academy-elearning' ); ?></th>
					<th><?php echo esc_html__( 'Accessi attivi', 'academy-elearning' ); ?></th>
					<th><?php echo esc_html__( 'Lezioni completate', 'academy-elearning' ); ?></th>
					<th><?php echo esc_html__( 'Tempo guardato', 'academy-elearning' ); ?></th>
					<th><?php echo esc_html__( 'Ultima attività', 'academy-elearning' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $users as $user ) : ?>
					<?php $s = $summaries[ (int) $user->ID ]; ?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $this->url( (int) $user->ID ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a></strong><br>
							<span class="description"><?php echo esc_html( $user->user_email ); ?></span>
						</td>
						<td>
							<?php
							$parts = array();
							if ( $s['catalog'] ) {
								$parts[] = __( 'Tutto il catalogo', 'academy-elearning' );
							}
							if ( $s['courses'] > 0 ) {
								$parts[] = sprintf(
									/* translators: %d: numero di corsi */
									_n( '%d corso', '%d corsi', $s['courses'], 'academy-elearning' ),
									$s['courses']
								);
							}
							echo esc_html( empty( $parts ) ? '—' : implode( ' + ', $parts ) );
							?>
						</td>
						<td><?php echo esc_html( (string) $s['completed'] ); ?></td>
						<td><?php echo esc_html( AEL_Stats::format_seconds( $s['seconds'] ) ); ?></td>
						<td><?php echo esc_html( $this->local_date( $s['last_seen'] ) ); ?></td>
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
							'base'    => add_query_arg( 'paged', '%#%', $this->url( 0, '' !== $search ? array( 's' => $search ) : array() ) ),
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
		<?php
	}

	/**
	 * Dettaglio di uno studente.
	 *
	 * @param int $user_id Utente.
	 */
	private function render_detail( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			echo '<h1>' . esc_html__( 'Studente', 'academy-elearning' ) . '</h1>';
			echo '<p>' . esc_html__( 'Utente non trovato.', 'academy-elearning' ) . '</p>';
			echo '<p><a href="' . esc_url( $this->url() ) . '">&larr; ' . esc_html__( 'Torna all\'elenco', 'academy-elearning' ) . '</a></p>';
			return;
		}

		$now     = time();
		$grants  = AEL_Access::all_grants_of( $user_id );
		$sources = array(
			AEL_Access::SOURCE_PURCHASE     => __( 'Acquisto', 'academy-elearning' ),
			AEL_Access::SOURCE_SUBSCRIPTION => __( 'Abbonamento', 'academy-elearning' ),
			AEL_Access::SOURCE_MANUAL       => __( 'Manuale', 'academy-elearning' ),
		);
		$states  = array(
			'active'  => __( 'Attivo', 'academy-elearning' ),
			'pending' => __( 'Non ancora iniziato', 'academy-elearning' ),
			'expired' => __( 'Scaduto', 'academy-elearning' ),
			'revoked' => __( 'Revocato', 'academy-elearning' ),
		);
		$watch   = AEL_Stats::watch_by_course( $user_id );
		?>
		<h1>
			<?php echo esc_html( $user->display_name ); ?>
		</h1>
		<p>
			<a href="<?php echo esc_url( $this->url() ); ?>">&larr; <?php echo esc_html__( 'Tutti gli studenti', 'academy-elearning' ); ?></a>
			<?php if ( current_user_can( 'edit_user', $user_id ) ) : ?>
				· <a href="<?php echo esc_url( get_edit_user_link( $user_id ) ); ?>"><?php echo esc_html__( 'Modifica profilo', 'academy-elearning' ); ?></a>
			<?php endif; ?>
		</p>
		<p>
			<?php echo esc_html( $user->user_email ); ?>
			· <?php echo esc_html( sprintf( /* translators: %s: data di registrazione */ __( 'registrato il %s', 'academy-elearning' ), $this->local_date( get_gmt_from_date( $user->user_registered ) ) ) ); ?>
		</p>

		<div class="ael-box">
			<h2><?php echo esc_html__( 'Accessi', 'academy-elearning' ); ?></h2>
			<?php if ( empty( $grants ) ) : ?>
				<p><?php echo esc_html__( 'Nessun accesso concesso.', 'academy-elearning' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php echo esc_html__( 'Corso', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Origine', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Stato', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Inizio', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Fine', 'academy-elearning' ); ?></th>
						<th></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $grants as $grant ) : ?>
						<?php
						$state     = AEL_Access::grant_state( $grant, $now );
						$course_id = (int) $grant['course_id'];
						if ( 0 === $course_id ) {
							$course_name = __( 'Tutto il catalogo', 'academy-elearning' );
						} elseif ( get_post( $course_id ) ) {
							$course_name = get_the_title( $course_id );
						} else {
							/* translators: %d: ID del corso */
							$course_name = sprintf( __( 'Corso eliminato (#%d)', 'academy-elearning' ), $course_id );
						}
						?>
						<tr>
							<td><?php echo esc_html( $course_name ); ?></td>
							<td><?php echo esc_html( $sources[ $grant['source'] ] ?? (string) $grant['source'] ); ?></td>
							<td><?php echo esc_html( $states[ $state ] ); ?></td>
							<td><?php echo esc_html( $this->local_date( (string) $grant['starts_at'] ) ); ?></td>
							<td><?php echo esc_html( empty( $grant['ends_at'] ) ? __( 'Senza scadenza', 'academy-elearning' ) : $this->local_date( (string) $grant['ends_at'] ) ); ?></td>
							<td>
								<?php if ( in_array( $state, array( 'active', 'pending' ), true ) ) : ?>
									<?php if ( AEL_Access::SOURCE_MANUAL === $grant['source'] ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Revocare questo accesso?', 'academy-elearning' ) ); ?>');">
											<input type="hidden" name="action" value="ael_student_revoke">
											<input type="hidden" name="grant_id" value="<?php echo esc_attr( (string) $grant['id'] ); ?>">
											<?php wp_nonce_field( 'ael_student_revoke_' . (int) $grant['id'] ); ?>
											<button type="submit" class="button-link button-link-delete"><?php echo esc_html__( 'Revoca', 'academy-elearning' ); ?></button>
										</form>
									<?php else : ?>
										<span class="description"><?php echo esc_html__( 'Gestito dai pagamenti', 'academy-elearning' ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h3><?php echo esc_html__( 'Concedi un accesso', 'academy-elearning' ); ?></h3>
			<?php $this->grant_form( $user_id ); ?>
		</div>

		<div class="ael-box">
			<h2><?php echo esc_html__( 'Progressi', 'academy-elearning' ); ?></h2>
			<?php
			$rows = array();
			foreach ( $this->courses() as $course ) {
				$progress_rows = AEL_Progress::rows( $user_id, (int) $course->ID );
				if ( empty( $progress_rows ) && empty( $watch[ (int) $course->ID ] ) ) {
					continue;
				}
				$program   = AEL_Catalog::program( (int) $course->ID, $user_id, false );
				$completed = count( array_intersect( wp_list_pluck( AEL_Catalog::flat_lessons( $program ), 'id' ), AEL_Progress::completed_ids( $user_id, (int) $course->ID ) ) );
				$rows[]    = array(
					'title'     => get_the_title( $course ),
					'completed' => $completed,
					'total'     => (int) $program['lesson_count'],
					'percent'   => AEL_Progress::percent( $user_id, (int) $course->ID ),
					'seconds'   => (int) ( $watch[ (int) $course->ID ] ?? 0 ),
					'last_seen' => $progress_rows ? (string) $progress_rows[0]['last_seen_at'] : '',
				);
			}
			?>
			<?php if ( empty( $rows ) ) : ?>
				<p><?php echo esc_html__( 'Nessun progresso registrato.', 'academy-elearning' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php echo esc_html__( 'Corso', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Lezioni completate', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Avanzamento', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Tempo guardato', 'academy-elearning' ); ?></th>
						<th><?php echo esc_html__( 'Ultima attività', 'academy-elearning' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['title'] ); ?></td>
							<td><?php echo esc_html( $row['completed'] . ' / ' . $row['total'] ); ?></td>
							<td><?php echo esc_html( null === $row['percent'] ? '—' : $row['percent'] . '%' ); ?></td>
							<td><?php echo esc_html( AEL_Stats::format_seconds( $row['seconds'] ) ); ?></td>
							<td><?php echo esc_html( $this->local_date( $row['last_seen'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php $days = AEL_Stats::watch_by_day( $user_id, 14 ); ?>
			<?php if ( ! empty( $days ) ) : ?>
				<h3><?php echo esc_html__( 'Tempo guardato per giorno', 'academy-elearning' ); ?></h3>
				<table class="widefat striped ael-narrow">
					<tbody>
					<?php foreach ( $days as $day => $seconds ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'd/m/Y', (int) strtotime( $day . ' 12:00:00 UTC' ) ) ); ?></td>
							<td><?php echo esc_html( AEL_Stats::format_seconds( $seconds ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Azioni                                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * Torna alla pagina con un messaggio.
	 *
	 * @param int    $user_id Utente (0 = elenco).
	 * @param string $message Codice del messaggio.
	 */
	private function back( int $user_id, string $message ): void {
		wp_safe_redirect( $this->url( $user_id, array( 'ael_msg' => $message ) ) );
		exit;
	}

	/**
	 * Concede un accesso manuale.
	 */
	public function handle_grant(): void {
		if ( ! current_user_can( AEL_Content::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per fare questa operazione.', 'academy-elearning' ), 403 );
		}
		check_admin_referer( 'ael_student_grant' );

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		if ( ! $user_id && ! empty( $_POST['email'] ) ) {
			$found   = get_user_by( 'email', sanitize_email( wp_unslash( $_POST['email'] ) ) );
			$user_id = $found ? (int) $found->ID : 0;
		}
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			$this->back( 0, 'nouser' );
		}

		$course_id = isset( $_POST['course_id'] ) ? absint( wp_unslash( $_POST['course_id'] ) ) : 0;
		if ( $course_id && AEL_Content::COURSE !== get_post_type( $course_id ) ) {
			$this->back( $user_id, 'invalid' );
		}
		$days = isset( $_POST['days'] ) ? absint( wp_unslash( $_POST['days'] ) ) : 0;
		$ends = ( $days >= 1 && $days <= 3650 ) ? gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ) : null;

		// Ogni concessione manuale ha un riferimento proprio, così si possono avere più accessi sullo stesso corso.
		$ref = 'admin-' . get_current_user_id() . '-' . time();
		AEL_Access::grant( $user_id, $course_id, AEL_Access::SOURCE_MANUAL, $ref, $ends );

		$this->back( $user_id, 'granted' );
	}

	/**
	 * Revoca un accesso manuale.
	 */
	public function handle_revoke(): void {
		if ( ! current_user_can( AEL_Content::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per fare questa operazione.', 'academy-elearning' ), 403 );
		}
		$grant_id = isset( $_POST['grant_id'] ) ? absint( wp_unslash( $_POST['grant_id'] ) ) : 0;
		check_admin_referer( 'ael_student_revoke_' . $grant_id );

		$grant = $grant_id ? AEL_Access::grant_by_id( $grant_id ) : null;
		if ( ! $grant ) {
			$this->back( 0, 'invalid' );
		}
		if ( AEL_Access::SOURCE_MANUAL !== $grant['source'] ) {
			$this->back( (int) $grant['user_id'], 'notmanual' );
		}
		AEL_Access::revoke( AEL_Access::SOURCE_MANUAL, (string) $grant['source_ref'], (int) $grant['user_id'], (int) $grant['course_id'] );
		$this->back( (int) $grant['user_id'], 'revoked' );
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
	 * Esporta gli studenti in CSV.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( AEL_Content::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per fare questa operazione.', 'academy-elearning' ), 403 );
		}
		check_admin_referer( 'ael_students_export' );

		$ids       = AEL_Stats::candidate_student_ids();
		$summaries = AEL_Stats::summaries( $ids );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="studenti-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $out, array( 'ID', 'Nome', 'Email', 'Corsi con accesso', 'Tutto il catalogo', 'Lezioni completate', 'Secondi guardati', 'Ultima attivita (UTC)' ) );
		foreach ( $ids as $id ) {
			$user = get_userdata( $id );
			if ( ! $user ) {
				continue;
			}
			$s = $summaries[ $id ];
			fputcsv(
				$out,
				array(
					$id,
					$this->csv_cell( $user->display_name ),
					$this->csv_cell( $user->user_email ),
					$s['courses'],
					$s['catalog'] ? 'si' : 'no',
					$s['completed'],
					$s['seconds'],
					$s['last_seen'],
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}
