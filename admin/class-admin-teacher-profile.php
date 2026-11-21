<?php
/**
 * Riquadro "Scheda pubblica" del docente.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frase di presentazione, copertina, colore e link. Compare solo se
 * l'agenzia ha acceso le schede docente personalizzabili.
 */
final class AEL_Admin_Teacher_Profile {

	private const NONCE_ACTION = 'ael_save_teacher_profile';
	private const NONCE_FIELD  = 'ael_teacher_profile_nonce';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Admin_Teacher_Profile|null
	 */
	private static ?AEL_Admin_Teacher_Profile $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Admin_Teacher_Profile {
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
	 * Aggancia riquadro, salvataggio e script.
	 */
	public function boot(): void {
		add_action( 'add_meta_boxes_' . AEL_Content::TEACHER, array( $this, 'add_box' ) );
		add_action( 'save_post_' . AEL_Content::TEACHER, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Aggiunge il riquadro, solo se la funzione è accesa.
	 */
	public function add_box(): void {
		if ( ! AEL_Teacher_Profile::enabled() ) {
			return;
		}
		add_meta_box( 'ael_teacher_profile', __( 'Scheda pubblica', 'academy-elearning' ), array( $this, 'render' ), AEL_Content::TEACHER, 'normal', 'high' );
	}

	/**
	 * Carica script e libreria media nella schermata del docente.
	 *
	 * @param string $hook Schermata.
	 */
	public function enqueue( $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! AEL_Teacher_Profile::enabled() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || AEL_Content::TEACHER !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'ael-admin-teacher', AEL_URL . 'assets/admin-teacher.js', array( 'jquery' ), AEL_VERSION, true );
		wp_localize_script(
			'ael-admin-teacher',
			'aelTeacher',
			array(
				'pickTitle'  => __( 'Scegli la copertina', 'academy-elearning' ),
				'pickButton' => __( 'Usa questa immagine', 'academy-elearning' ),
			)
		);
	}

	/**
	 * Disegna il riquadro.
	 *
	 * @param WP_Post $post Docente.
	 */
	public function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, false );

		$tagline  = (string) get_post_meta( $post->ID, '_ael_tagline', true );
		$cover_id = (int) get_post_meta( $post->ID, '_ael_cover_id', true );
		$cover    = ( $cover_id && 'attachment' === get_post_type( $cover_id ) ) ? (string) wp_get_attachment_image_url( $cover_id, 'medium' ) : '';
		$accent   = AEL_Teacher_Profile::clean_color( (string) get_post_meta( $post->ID, '_ael_accent', true ) );
		$links    = AEL_Teacher_Profile::clean_links( get_post_meta( $post->ID, '_ael_links', true ) );
		$channels = AEL_Teacher_Profile::clean_channels( get_post_meta( $post->ID, '_ael_channels', true ) );
		$stats_date = (string) get_post_meta( $post->ID, '_ael_stats_date', true );
		?>
		<p class="description"><?php echo esc_html__( 'Come appare la pagina pubblica del docente e il riquadro "Il docente" nelle pagine dei suoi corsi. Nome, foto e descrizione sono quelli del contenuto qui sopra e dell\'immagine in evidenza.', 'academy-elearning' ); ?></p>
		<table class="form-table" role="presentation"><tbody>
			<tr><th><label for="ael_tagline"><?php echo esc_html__( 'Frase di presentazione', 'academy-elearning' ); ?></label></th>
				<td><input type="text" class="large-text" id="ael_tagline" name="ael_tagline" maxlength="140" value="<?php echo esc_attr( $tagline ); ?>">
				<p class="description"><?php echo esc_html__( 'Una riga breve sotto il nome (massimo 140 caratteri).', 'academy-elearning' ); ?></p></td></tr>

			<tr><th><?php echo esc_html__( 'Copertina', 'academy-elearning' ); ?></th>
				<td>
					<input type="hidden" id="ael_cover_id" name="ael_cover_id" value="<?php echo esc_attr( (string) $cover_id ); ?>">
					<div id="ael-cover-preview" style="margin-bottom:8px"><?php if ( '' !== $cover ) : ?><img src="<?php echo esc_url( $cover ); ?>" alt="" style="max-width:320px;height:auto"><?php endif; ?></div>
					<button type="button" class="button" id="ael-cover-pick"><?php echo esc_html__( 'Scegli immagine', 'academy-elearning' ); ?></button>
					<button type="button" class="button-link" id="ael-cover-remove"><?php echo esc_html__( 'Rimuovi', 'academy-elearning' ); ?></button>
				</td></tr>

			<tr><th><label for="ael_accent"><?php echo esc_html__( 'Colore', 'academy-elearning' ); ?></label></th>
				<td>
					<input type="color" id="ael_accent_picker" value="<?php echo esc_attr( '' !== $accent ? $accent : '#1d4ed8' ); ?>">
					<input type="text" id="ael_accent" name="ael_accent" class="small-text" maxlength="7" placeholder="#RRGGBB" value="<?php echo esc_attr( $accent ); ?>">
					<p class="description"><?php echo esc_html__( 'Colore di pulsanti ed evidenze nelle pagine di questo docente e dei suoi corsi. Vuoto = il colore dell\'academy.', 'academy-elearning' ); ?></p>
				</td></tr>

			<tr><th><?php echo esc_html__( 'Canali e numeri', 'academy-elearning' ); ?></th>
				<td>
					<?php for ( $i = 0; $i < AEL_Teacher_Profile::MAX_CHANNELS; $i++ ) : ?>
						<?php $channel = $channels[ $i ] ?? array(); ?>
						<p>
							<select name="ael_channels[<?php echo esc_attr( (string) $i ); ?>][platform]">
								<?php foreach ( AEL_Teacher_Profile::platforms() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $key ); ?>" <?php selected( $channel['platform'] ?? 'instagram', $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" name="ael_channels[<?php echo esc_attr( (string) $i ); ?>][handle]" placeholder="<?php echo esc_attr__( '@profilo', 'academy-elearning' ); ?>" value="<?php echo esc_attr( $channel['handle'] ?? '' ); ?>" class="regular-text" style="width:150px">
							<input type="url" name="ael_channels[<?php echo esc_attr( (string) $i ); ?>][url]" placeholder="https://" value="<?php echo esc_attr( $channel['url'] ?? '' ); ?>" class="regular-text" style="width:220px">
							<input type="text" inputmode="numeric" name="ael_channels[<?php echo esc_attr( (string) $i ); ?>][followers]" placeholder="<?php echo esc_attr__( 'Follower', 'academy-elearning' ); ?>" value="<?php echo esc_attr( isset( $channel['followers'] ) && $channel['followers'] > 0 ? (string) $channel['followers'] : '' ); ?>" class="small-text" style="width:100px">
							<input type="text" inputmode="decimal" name="ael_channels[<?php echo esc_attr( (string) $i ); ?>][engagement]" placeholder="<?php echo esc_attr__( 'Engagement %', 'academy-elearning' ); ?>" value="<?php echo esc_attr( isset( $channel['engagement'] ) && $channel['engagement'] > 0 ? (string) $channel['engagement'] : '' ); ?>" class="small-text" style="width:110px">
						</p>
					<?php endfor; ?>
					<p><label for="ael_stats_date"><?php echo esc_html__( 'Numeri aggiornati a', 'academy-elearning' ); ?></label>
						<input type="text" id="ael_stats_date" name="ael_stats_date" maxlength="40" placeholder="<?php echo esc_attr__( 'es. novembre 2026', 'academy-elearning' ); ?>" value="<?php echo esc_attr( $stats_date ); ?>"></p>
					<p class="description"><?php echo esc_html__( 'Fino a 6 canali. I numeri si scrivono a mano: follower (es. 125000) ed engagement rate in percentuale (es. 4,8). Nella pagina del docente compaiono i follower totali e l\'engagement medio, calcolato sui follower di ogni canale.', 'academy-elearning' ); ?></p>
				</td></tr>

			<tr><th><?php echo esc_html__( 'Link', 'academy-elearning' ); ?></th>
				<td>
					<?php for ( $i = 0; $i < AEL_Teacher_Profile::MAX_LINKS; $i++ ) : ?>
						<p>
							<input type="text" name="ael_links[<?php echo esc_attr( (string) $i ); ?>][label]" placeholder="<?php echo esc_attr__( 'Nome (es. Instagram)', 'academy-elearning' ); ?>" value="<?php echo esc_attr( $links[ $i ]['label'] ?? '' ); ?>" class="regular-text">
							<input type="url" name="ael_links[<?php echo esc_attr( (string) $i ); ?>][url]" placeholder="https://" value="<?php echo esc_attr( $links[ $i ]['url'] ?? '' ); ?>" class="regular-text">
						</p>
					<?php endfor; ?>
					<p class="description"><?php echo esc_html__( 'Fino a 6 link: sito, social, canale. Si accettano solo indirizzi che iniziano con http o https.', 'academy-elearning' ); ?></p>
				</td></tr>
		</tbody></table>
		<?php
	}

	/**
	 * Salva i campi.
	 *
	 * @param int     $post_id Docente.
	 * @param WP_Post $post    Contenuto.
	 */
	public function save( int $post_id, WP_Post $post ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! AEL_Teacher_Profile::enabled()
			|| ! AEL_Content::is_form_post( $post_id )
			|| ! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$tagline = isset( $_POST['ael_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['ael_tagline'] ) ) : '';
		update_post_meta( $post_id, '_ael_tagline', mb_substr( $tagline, 0, 140 ) );

		$cover_id = isset( $_POST['ael_cover_id'] ) ? absint( wp_unslash( $_POST['ael_cover_id'] ) ) : 0;
		update_post_meta( $post_id, '_ael_cover_id', ( $cover_id && 'attachment' === get_post_type( $cover_id ) ) ? $cover_id : 0 );

		$accent = isset( $_POST['ael_accent'] ) ? AEL_Teacher_Profile::clean_color( sanitize_text_field( wp_unslash( $_POST['ael_accent'] ) ) ) : '';
		update_post_meta( $post_id, '_ael_accent', $accent );

		// Ogni elemento è ripulito da clean_links() (solo http/https, massimo 6).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_links = isset( $_POST['ael_links'] ) && is_array( $_POST['ael_links'] ) ? wp_unslash( $_POST['ael_links'] ) : array();
		update_post_meta( $post_id, '_ael_links', AEL_Teacher_Profile::clean_links( $raw_links ) );

		// Ogni canale è ripulito da clean_channels() (piattaforma nota, indirizzi http/https, numeri).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_channels = isset( $_POST['ael_channels'] ) && is_array( $_POST['ael_channels'] ) ? wp_unslash( $_POST['ael_channels'] ) : array();
		update_post_meta( $post_id, '_ael_channels', AEL_Teacher_Profile::clean_channels( $raw_channels ) );

		$stats_date = isset( $_POST['ael_stats_date'] ) ? sanitize_text_field( wp_unslash( $_POST['ael_stats_date'] ) ) : '';
		update_post_meta( $post_id, '_ael_stats_date', mb_substr( $stats_date, 0, 40 ) );
	}
}
