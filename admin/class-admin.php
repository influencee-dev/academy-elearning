<?php
/**
 * Menu principale "Academy" nell'amministrazione.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Crea la voce di menu "Academy", visibile al titolare, che raccoglie corsi,
 * docenti, raccolte e (solo per l'agenzia) la Configurazione.
 */
final class AEL_Admin {

	/**
	 * Slug del menu principale.
	 */
	public const MENU = 'ael-academy';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Admin|null
	 */
	private static ?AEL_Admin $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Admin {
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
	 * Aggancia il menu e le schermate dei contenuti.
	 */
	public function boot(): void {
		// Priorità 9: il menu principale deve esistere prima delle voci dei contenuti.
		add_action( 'admin_menu', array( $this, 'register_menu' ), 9 );
		AEL_Admin_Content::instance()->boot();
		AEL_Admin_Program::instance()->boot();
		AEL_Admin_Students::instance()->boot();
		AEL_Admin_Teacher_Profile::instance()->boot();
		// La pagina "Prova accessi" è solo per lo sviluppo e non c'è nello zip di rilascio.
		if ( class_exists( 'AEL_Admin_Access_Debug' ) ) {
			AEL_Admin_Access_Debug::instance()->boot();
		}
	}

	/**
	 * Registra il menu principale e la pagina Panoramica.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Academy', 'academy-elearning' ),
			__( 'Academy', 'academy-elearning' ),
			/**
			 * Permesso richiesto per vedere il menu "Academy". Di base quello del
			 * titolare; un modulo (docenti autori) può abbassarlo per i suoi utenti.
			 *
			 * @param string $cap Permesso.
			 */
			(string) apply_filters( 'ael_admin_menu_cap', AEL_Content::CAP ),
			self::MENU,
			array( $this, 'render_overview' ),
			'dashicons-welcome-learn-more',
			58
		);
		// Rinomina la prima voce del sottomenu (altrimenti ripeterebbe "Academy").
		add_submenu_page(
			self::MENU,
			__( 'Panoramica', 'academy-elearning' ),
			__( 'Panoramica', 'academy-elearning' ),
			AEL_Content::CAP,
			self::MENU,
			array( $this, 'render_overview' )
		);
	}

	/**
	 * Pagina Panoramica. Il cruscotto con vendite e abbonamenti arriva più avanti.
	 */
	public function render_overview(): void {
		if ( ! current_user_can( AEL_Content::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per vedere questa pagina.', 'academy-elearning' ), 403 );
		}

		$rows = array(
			AEL_Content::COURSE     => __( 'Corsi', 'academy-elearning' ),
			AEL_Content::MODULE     => __( 'Moduli', 'academy-elearning' ),
			AEL_Content::LESSON     => __( 'Lezioni', 'academy-elearning' ),
			AEL_Content::TEACHER    => __( 'Docenti', 'academy-elearning' ),
			AEL_Content::COLLECTION => __( 'Raccolte', 'academy-elearning' ),
		);
		$published  = (int) wp_count_posts( AEL_Content::COURSE )->publish;
		$by_source  = AEL_Stats::active_grants_by_source();
		$sources    = array(
			AEL_Access::SOURCE_PURCHASE     => __( 'Acquisti', 'academy-elearning' ),
			AEL_Access::SOURCE_SUBSCRIPTION => __( 'Abbonamenti', 'academy-elearning' ),
			AEL_Access::SOURCE_MANUAL       => __( 'Accessi manuali', 'academy-elearning' ),
		);
		$top        = AEL_Stats::top_courses( 30, 5 );
		$cards      = array(
			array( (string) $published, __( 'Corsi pubblicati', 'academy-elearning' ) ),
			array( (string) AEL_Stats::active_students_count(), __( 'Studenti con accesso', 'academy-elearning' ) ),
			array( (string) AEL_Stats::completed_since( 30 ), __( 'Lezioni completate (30 giorni)', 'academy-elearning' ) ),
			array( AEL_Stats::format_seconds( AEL_Stats::watch_seconds_since( 30 ) ), __( 'Tempo guardato (30 giorni)', 'academy-elearning' ) ),
		);
		?>
		<div class="wrap ael-admin">
			<h1><?php echo esc_html__( 'Academy', 'academy-elearning' ); ?></h1>

			<div class="ael-stat-grid">
				<?php foreach ( $cards as $card ) : ?>
					<div class="ael-stat">
						<span class="ael-stat-value"><?php echo esc_html( $card[0] ); ?></span>
						<span class="ael-stat-label"><?php echo esc_html( $card[1] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="ael-box">
				<h2><?php echo esc_html__( 'Accessi attivi per origine', 'academy-elearning' ); ?></h2>
				<?php if ( empty( $by_source ) ) : ?>
					<p><?php echo esc_html__( 'Nessun accesso attivo.', 'academy-elearning' ); ?></p>
				<?php else : ?>
					<ul>
						<?php foreach ( $by_source as $source => $total ) : ?>
							<li><?php echo esc_html( ( $sources[ $source ] ?? $source ) . ': ' . $total ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AEL_Admin_Students::PAGE ) ); ?>"><?php echo esc_html__( 'Vai agli studenti', 'academy-elearning' ); ?></a></p>
			</div>

			<div class="ael-box">
				<h2><?php echo esc_html__( 'Corsi più guardati (30 giorni)', 'academy-elearning' ); ?></h2>
				<?php if ( empty( $top ) ) : ?>
					<p><?php echo esc_html__( 'Ancora nessuna visione registrata.', 'academy-elearning' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead><tr>
							<th><?php echo esc_html__( 'Corso', 'academy-elearning' ); ?></th>
							<th><?php echo esc_html__( 'Studenti', 'academy-elearning' ); ?></th>
							<th><?php echo esc_html__( 'Tempo guardato', 'academy-elearning' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $top as $row ) : ?>
							<tr>
								<td><?php echo esc_html( get_post( $row['course_id'] ) ? get_the_title( $row['course_id'] ) : '#' . $row['course_id'] ); ?></td>
								<td><?php echo esc_html( (string) $row['students'] ); ?></td>
								<td><?php echo esc_html( AEL_Stats::format_seconds( $row['seconds'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<?php
			/**
			 * Spazio nella Panoramica per i moduli (vendite, abbonamenti, quote docenti).
			 * I moduli stampano qui i propri riquadri, per esempio dentro un <div class="ael-box">.
			 */
			do_action( 'ael_overview_sections' );
			?>

			<h2><?php echo esc_html__( 'Contenuti', 'academy-elearning' ); ?></h2>
			<table class="widefat striped" style="max-width:420px">
				<tbody>
				<?php foreach ( $rows as $type => $label ) : ?>
					<?php
					$counts = wp_count_posts( $type );
					$total  = 0;
					foreach ( array( 'publish', 'draft', 'pending', 'private' ) as $status ) {
						$total += isset( $counts->$status ) ? (int) $counts->$status : 0;
					}
					?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $type ) ); ?>"><?php echo esc_html( $label ); ?></a></td>
						<td><?php echo esc_html( (string) $total ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
