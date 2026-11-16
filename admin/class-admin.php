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
	}

	/**
	 * Registra il menu principale e la pagina Panoramica.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Academy', 'academy-elearning' ),
			__( 'Academy', 'academy-elearning' ),
			AEL_Content::CAP,
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
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Academy', 'academy-elearning' ); ?></h1>
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
			<p class="description"><?php echo esc_html__( 'Il cruscotto con vendite e abbonamenti arriverà più avanti.', 'academy-elearning' ); ?></p>
		</div>
		<?php
	}
}
