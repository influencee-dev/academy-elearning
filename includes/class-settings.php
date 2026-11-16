<?php
/**
 * Impostazioni e pagina Configurazione (riservata all'agenzia).
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Gestisce le opzioni del core e la pagina in cui l'agenzia accende i moduli.
 *
 * La pagina richiede la capacità ael_manage_modules. Il ruolo amministratore
 * del cliente non la ha: senza, né la pagina né la voce di menu esistono.
 */
final class AEL_Settings {

	/**
	 * Capacità riservata all'agenzia.
	 */
	public const CAP = 'ael_manage_modules';

	/**
	 * Nome dell'option con le impostazioni del core.
	 */
	public const OPTION = 'ael_settings';

	/**
	 * Slug della pagina di configurazione.
	 */
	public const PAGE = 'ael-config';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Settings|null
	 */
	private static ?AEL_Settings $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Settings {
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
	 * Aggancia menu, salvataggio e controllo dei permessi.
	 */
	public function boot(): void {
		add_filter( 'user_has_cap', array( $this, 'filter_caps' ), 10, 4 );
		// Priorità 11: dopo il menu principale (9) e le voci dei contenuti (10).
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_post_ael_save_config', array( $this, 'handle_save' ) );
	}

	/**
	 * Valori predefiniti delle opzioni del core.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		/**
		 * Filtra i valori predefiniti delle impostazioni.
		 *
		 * @param array $defaults Valori predefiniti.
		 */
		return apply_filters(
			'ael_default_settings',
			array(
				'teacher_pages'            => 1,
				'free_previews'            => 1,
				'video_sources'            => array( 'youtube', 'vimeo' ),
				'delete_data_on_uninstall' => 0,
			)
		);
	}

	/**
	 * Legge un'impostazione.
	 *
	 * @param string $key     Chiave.
	 * @param mixed  $default Valore se la chiave non esiste.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$stored   = get_option( self::OPTION, array() );
		$settings = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Dà la capacità dell'agenzia a un utente.
	 *
	 * @param int $user_id ID utente.
	 */
	public static function grant_cap( int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( $user ) {
			$user->add_cap( self::CAP );
			// Per entrare nel menu Academy serve anche il permesso base del titolare.
			$user->add_cap( AEL_Content::CAP );
		}
	}

	/**
	 * Permette di dare la capacità da wp-config.php, per esempio:
	 * define( 'AEL_AGENCY_USER_IDS', array( 1 ) );
	 * Serve a recuperare l'accesso se nessun utente la ha più.
	 *
	 * @param array   $allcaps Capacità dell'utente.
	 * @param array   $caps    Capacità richieste.
	 * @param array   $args    Argomenti.
	 * @param WP_User $user    Utente.
	 * @return array
	 */
	public function filter_caps( $allcaps, $caps, $args, $user ) {
		if ( defined( 'AEL_AGENCY_USER_IDS' ) && is_array( AEL_AGENCY_USER_IDS ) && $user instanceof WP_User ) {
			if ( in_array( (int) $user->ID, array_map( 'intval', AEL_AGENCY_USER_IDS ), true ) ) {
				$allcaps[ self::CAP ]             = true;
				$allcaps[ AEL_Content::CAP ] = true;
			}
		}
		return $allcaps;
	}

	/**
	 * Registra la voce di menu. Compare solo a chi ha la capacità.
	 */
	public function register_menu(): void {
		add_submenu_page(
			'ael-academy',
			__( 'Configurazione Academy', 'academy-elearning' ),
			__( 'Configurazione', 'academy-elearning' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Testo che spiega cosa manca a un modulo.
	 *
	 * @param array $missing Risultato di AEL_Modules::missing().
	 */
	private function missing_text( array $missing ): string {
		$parts = array();
		if ( ! empty( $missing['modules'] ) ) {
			/* translators: %s: elenco di moduli */
			$parts[] = sprintf( __( 'richiede i moduli: %s', 'academy-elearning' ), implode( ', ', $missing['modules'] ) );
		}
		if ( ! empty( $missing['plugins'] ) ) {
			/* translators: %s: elenco di plugin */
			$parts[] = sprintf( __( 'richiede il plugin attivo: %s', 'academy-elearning' ), implode( ', ', $missing['plugins'] ) );
		}
		return implode( '; ', $parts );
	}

	/**
	 * Disegna la pagina Configurazione.
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per vedere questa pagina.', 'academy-elearning' ), 403 );
		}

		$modules  = AEL_Modules::instance();
		$all      = $modules->available();
		$active   = $modules->active_slugs();
		$sources  = (array) self::get( 'video_sources', array() );
		$dropped  = get_transient( 'ael_dropped_' . get_current_user_id() );
		$saved    = isset( $_GET['ael_saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( false !== $dropped ) {
			delete_transient( 'ael_dropped_' . get_current_user_id() );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Configurazione Academy', 'academy-elearning' ); ?></h1>
			<p><?php echo esc_html__( 'Questa pagina è solo per l\'agenzia. Il cliente non la vede: riceve l\'academy già configurata.', 'academy-elearning' ); ?></p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Configurazione salvata.', 'academy-elearning' ); ?></p></div>
			<?php endif; ?>

			<?php if ( is_array( $dropped ) ) : ?>
				<?php foreach ( $dropped as $slug => $missing ) : ?>
					<div class="notice notice-warning"><p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: modulo, 2: motivo */
								__( 'Il modulo "%1$s" non è stato acceso: %2$s.', 'academy-elearning' ),
								$slug,
								$this->missing_text( $missing )
							)
						);
						?>
					</p></div>
				<?php endforeach; ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ael_save_config">
				<?php wp_nonce_field( 'ael_save_config' ); ?>

				<h2><?php echo esc_html__( 'Moduli', 'academy-elearning' ); ?></h2>
				<?php if ( empty( $all ) ) : ?>
					<p><?php echo esc_html__( 'Nessun modulo disponibile.', 'academy-elearning' ); ?></p>
				<?php else : ?>
					<table class="widefat striped" style="max-width:900px">
						<thead><tr>
							<th style="width:70px"><?php echo esc_html__( 'Acceso', 'academy-elearning' ); ?></th>
							<th><?php echo esc_html__( 'Modulo', 'academy-elearning' ); ?></th>
							<th style="width:90px"><?php echo esc_html__( 'Versione', 'academy-elearning' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $all as $slug => $module ) : ?>
							<?php
							$missing   = $modules->missing( $slug, $active );
							$no_plugin = ! empty( $missing['plugins'] );
							$note      = $this->missing_text( $missing );
							?>
							<tr>
								<td>
									<input type="checkbox" name="modules[]" id="ael-mod-<?php echo esc_attr( $slug ); ?>" value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, $active, true ) ); ?>
										<?php disabled( $no_plugin ); ?>>
								</td>
								<td>
									<label for="ael-mod-<?php echo esc_attr( $slug ); ?>"><strong><?php echo esc_html( $module['name'] ); ?></strong></label>
									<?php if ( $module['dev_only'] ) : ?>
										<span class="description">(<?php echo esc_html__( 'solo prova', 'academy-elearning' ); ?>)</span>
									<?php endif; ?>
									<br><span class="description"><?php echo esc_html( $module['description'] ); ?></span>
									<?php if ( '' !== $note ) : ?>
										<br><span style="color:#b32d2e"><?php echo esc_html( ucfirst( $note ) ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $module['version'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<h2><?php echo esc_html__( 'Opzioni del core', 'academy-elearning' ); ?></h2>
				<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row"><?php echo esc_html__( 'Pagine docente', 'academy-elearning' ); ?></th><td>
						<label><input type="checkbox" name="teacher_pages" value="1" <?php checked( (int) self::get( 'teacher_pages', 1 ), 1 ); ?>>
						<?php echo esc_html__( 'Ogni docente ha una pagina pubblica con i suoi corsi', 'academy-elearning' ); ?></label>
					</td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Anteprime gratuite', 'academy-elearning' ); ?></th><td>
						<label><input type="checkbox" name="free_previews" value="1" <?php checked( (int) self::get( 'free_previews', 1 ), 1 ); ?>>
						<?php echo esc_html__( 'Le lezioni segnate come anteprima si vedono senza acquisto', 'academy-elearning' ); ?></label>
					</td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Sorgenti video', 'academy-elearning' ); ?></th><td>
						<label><input type="checkbox" name="video_sources[]" value="youtube" <?php checked( in_array( 'youtube', $sources, true ) ); ?>> YouTube</label><br>
						<label><input type="checkbox" name="video_sources[]" value="vimeo" <?php checked( in_array( 'vimeo', $sources, true ) ); ?>> Vimeo</label>
					</td></tr>
					<tr><th scope="row"><?php echo esc_html__( 'Disinstallazione', 'academy-elearning' ); ?></th><td>
						<label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( (int) self::get( 'delete_data_on_uninstall', 0 ), 1 ); ?>>
						<?php echo esc_html__( 'Cancella tutti i dati del plugin quando viene eliminato', 'academy-elearning' ); ?></label>
						<p class="description"><?php echo esc_html__( 'Lascia spento se non sei sicuro: spegnere un modulo non cancella mai i suoi dati.', 'academy-elearning' ); ?></p>
					</td></tr>
				</tbody></table>

				<?php submit_button( __( 'Salva configurazione', 'academy-elearning' ) ); ?>
			</form>

			<h2><?php echo esc_html__( 'Stato del database', 'academy-elearning' ); ?></h2>
			<?php
			$migration_error = get_option( AEL_Installer::OPTION_ERROR, '' );
			$done_migrations = get_option( AEL_Installer::OPTION_DONE, array() );
			?>
			<?php if ( '' !== $migration_error ) : ?>
				<div class="notice notice-error inline"><p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: ID della migrazione */
							__( 'La migrazione "%s" non è riuscita. Le successive non sono state eseguite.', 'academy-elearning' ),
							(string) $migration_error
						)
					);
					?>
				</p></div>
			<?php endif; ?>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: versione del plugin all'ultima migrazione, 2: numero di migrazioni eseguite */
						__( 'Versione dati: %1$s. Migrazioni eseguite: %2$d.', 'academy-elearning' ),
						(string) get_option( AEL_Installer::OPTION_VERSION, '-' ),
						is_array( $done_migrations ) ? count( $done_migrations ) : 0
					)
				);
				?>
			</p>
			<table class="widefat striped" style="max-width:500px">
				<thead><tr>
					<th><?php echo esc_html__( 'Tabella', 'academy-elearning' ); ?></th>
					<th style="width:90px"><?php echo esc_html__( 'Stato', 'academy-elearning' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( AEL_Installer::table_status() as $table_name => $exists ) : ?>
					<tr>
						<td><code><?php echo esc_html( $table_name ); ?></code></td>
						<td><?php echo $exists ? esc_html__( 'presente', 'academy-elearning' ) : '<strong style="color:#b32d2e">' . esc_html__( 'manca', 'academy-elearning' ) . '</strong>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Salva la configurazione.
	 */
	public function handle_save(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per fare questa operazione.', 'academy-elearning' ), 403 );
		}
		check_admin_referer( 'ael_save_config' );

		$requested = array();
		if ( isset( $_POST['modules'] ) && is_array( $_POST['modules'] ) ) {
			$requested = array_map( 'sanitize_key', wp_unslash( $_POST['modules'] ) );
		}
		$dropped = AEL_Modules::instance()->set_active( $requested );
		set_transient( 'ael_dropped_' . get_current_user_id(), $dropped, MINUTE_IN_SECONDS );

		$video = array();
		if ( isset( $_POST['video_sources'] ) && is_array( $_POST['video_sources'] ) ) {
			$video = array_map( 'sanitize_key', wp_unslash( $_POST['video_sources'] ) );
		}

		$settings = array(
			'teacher_pages'            => empty( $_POST['teacher_pages'] ) ? 0 : 1,
			'free_previews'            => empty( $_POST['free_previews'] ) ? 0 : 1,
			'video_sources'            => array_values( array_intersect( array( 'youtube', 'vimeo' ), $video ) ),
			'delete_data_on_uninstall' => empty( $_POST['delete_data_on_uninstall'] ) ? 0 : 1,
		);
		update_option( self::OPTION, $settings );

		/**
		 * Scatta dopo il salvataggio della configurazione dell'agenzia.
		 *
		 * @param array $settings Impostazioni del core salvate.
		 */
		do_action( 'ael_settings_saved', $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => self::PAGE,
					'ael_saved' => 1,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
