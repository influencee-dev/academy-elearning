<?php
/**
 * Foto profilo degli utenti (studenti e docenti).
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ogni utente può caricare, cambiare e togliere la propria foto profilo dall'area
 * "I miei corsi" e dai dettagli dell'account WooCommerce. La foto sostituisce
 * Gravatar in tutto il sito (filtro `pre_get_avatar_data`). Si accende dalla
 * Configurazione (solo agenzia).
 *
 * Meta utente: _ael_avatar_id (ID dell'allegato, già ritagliato in quadrato).
 */
final class AEL_Avatar {

	/**
	 * Meta utente con l'ID dell'immagine.
	 */
	public const META = '_ael_avatar_id';

	/**
	 * Azione del modulo (admin-post).
	 */
	public const ACTION = 'ael_avatar';

	/**
	 * Peso massimo del file, in byte (3 MB).
	 */
	public const MAX_BYTES = 3145728;

	/**
	 * Lato dell'immagine salvata, in pixel.
	 */
	public const SIZE = 400;

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Avatar|null
	 */
	private static ?AEL_Avatar $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Avatar {
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
	 * La foto profilo è accesa?
	 */
	public static function enabled(): bool {
		return (bool) AEL_Settings::get( 'user_avatars', 1 );
	}

	/**
	 * Aggancia filtro, salvataggio e modulo di caricamento.
	 */
	public function boot(): void {
		if ( ! self::enabled() ) {
			return;
		}
		add_filter( 'pre_get_avatar_data', array( $this, 'filter_avatar' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'ael_my_courses_before', array( $this, 'render_form' ) );
		add_action( 'woocommerce_before_edit_account_form', array( $this, 'render_form' ) );
	}

	/**
	 * Indirizzo della foto di un utente, o stringa vuota.
	 *
	 * @param int    $user_id Utente.
	 * @param string $size    Dimensione registrata in WordPress.
	 */
	public static function url( int $user_id, string $size = 'medium' ): string {
		if ( ! self::enabled() || $user_id <= 0 ) {
			return '';
		}
		$attachment_id = (int) get_user_meta( $user_id, self::META, true );
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return '';
		}
		return (string) wp_get_attachment_image_url( $attachment_id, $size );
	}

	/**
	 * Usa la foto caricata al posto di Gravatar.
	 *
	 * @param array $args           Dati dell'avatar.
	 * @param mixed $id_or_email    ID, email, utente, articolo o commento.
	 * @return array
	 */
	public function filter_avatar( $args, $id_or_email ) {
		$user_id = 0;
		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( $id_or_email instanceof WP_User ) {
			$user_id = (int) $id_or_email->ID;
		} elseif ( $id_or_email instanceof WP_Post ) {
			$user_id = (int) $id_or_email->post_author;
		} elseif ( $id_or_email instanceof WP_Comment ) {
			$user_id = (int) $id_or_email->user_id;
		} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
			$user_id = $user ? (int) $user->ID : 0;
		}
		if ( ! $user_id || ! is_array( $args ) ) {
			return $args;
		}
		$url = self::url( $user_id, 'medium' );
		if ( '' !== $url ) {
			$args['url']          = $url;
			$args['found_avatar'] = true;
		}
		return $args;
	}

	/**
	 * Messaggi mostrati dopo il caricamento.
	 *
	 * @return array<string, string>
	 */
	private static function messages(): array {
		return array(
			'ok'      => __( 'Foto profilo aggiornata.', 'academy-elearning' ),
			'removed' => __( 'Foto profilo rimossa.', 'academy-elearning' ),
			'type'    => __( 'Formato non valido: usa un\'immagine JPG, PNG o WebP.', 'academy-elearning' ),
			'size'    => __( 'Il file è troppo pesante: massimo 3 MB.', 'academy-elearning' ),
			'empty'   => __( 'Scegli prima un\'immagine.', 'academy-elearning' ),
			'error'   => __( 'Non sono riuscito a salvare l\'immagine. Riprova.', 'academy-elearning' ),
		);
	}

	/**
	 * Disegna il modulo "Foto profilo" per l'utente collegato.
	 */
	public function render_form(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user_id  = get_current_user_id();
		$has      = '' !== self::url( $user_id );
		$messages = self::messages();
		$code     = isset( $_GET['ael_avatar'] ) ? sanitize_key( wp_unslash( $_GET['ael_avatar'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo un messaggio di esito.
		?>
		<section class="ael ael-avatar-box">
			<h2 class="ael-avatar-title"><?php echo esc_html__( 'Foto profilo', 'academy-elearning' ); ?></h2>
			<div class="ael-avatar-row">
				<span class="ael-avatar-preview"><?php echo get_avatar( $user_id, 96 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html di WordPress. ?></span>
				<form class="ael-avatar-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION, 'ael_avatar_nonce' ); ?>
					<p class="ael-avatar-help"><?php echo esc_html__( 'Facoltativa. JPG, PNG o WebP, massimo 3 MB. Viene ritagliata in quadrato.', 'academy-elearning' ); ?></p>
					<p>
						<input type="file" name="ael_avatar_file" accept="image/jpeg,image/png,image/webp" aria-label="<?php echo esc_attr__( 'Scegli la foto', 'academy-elearning' ); ?>">
					</p>
					<p class="ael-avatar-buttons">
						<button type="submit" name="ael_avatar_do" value="upload" class="ael-button ael-button--primary"><?php echo esc_html__( 'Carica foto', 'academy-elearning' ); ?></button>
						<?php if ( $has ) : ?>
							<button type="submit" name="ael_avatar_do" value="remove" class="ael-button"><?php echo esc_html__( 'Rimuovi', 'academy-elearning' ); ?></button>
						<?php endif; ?>
					</p>
					<?php if ( isset( $messages[ $code ] ) ) : ?>
						<p class="ael-avatar-message<?php echo in_array( $code, array( 'ok', 'removed' ), true ) ? '' : ' ael-error-text'; ?>" role="status"><?php echo esc_html( $messages[ $code ] ); ?></p>
					<?php endif; ?>
				</form>
			</div>
		</section>
		<?php
		wp_enqueue_style( 'ael-front' );
	}

	/**
	 * Riceve il modulo: carica o rimuove la foto dell'utente collegato.
	 */
	public function handle(): void {
		if ( ! is_user_logged_in() || ! self::enabled() ) {
			wp_die( esc_html__( 'Non hai i permessi per farlo.', 'academy-elearning' ), 403 );
		}
		check_admin_referer( self::ACTION, 'ael_avatar_nonce' );

		$user_id = get_current_user_id();
		$do      = isset( $_POST['ael_avatar_do'] ) ? sanitize_key( wp_unslash( $_POST['ael_avatar_do'] ) ) : 'upload';
		$result  = 'remove' === $do ? $this->remove( $user_id ) : $this->upload( $user_id );

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = home_url( '/' );
		}
		$back = remove_query_arg( 'ael_avatar', $back );
		wp_safe_redirect( add_query_arg( 'ael_avatar', $result, $back ) );
		exit;
	}

	/**
	 * Toglie la foto e cancella l'immagine.
	 *
	 * @param int $user_id Utente.
	 */
	private function remove( int $user_id ): string {
		$this->delete_current( $user_id );
		return 'removed';
	}

	/**
	 * Cancella l'immagine attuale dell'utente (solo se è sua).
	 *
	 * @param int $user_id Utente.
	 */
	private function delete_current( int $user_id ): void {
		$old = (int) get_user_meta( $user_id, self::META, true );
		delete_user_meta( $user_id, self::META );
		if ( $old && 'attachment' === get_post_type( $old ) && (int) get_post_meta( $old, '_ael_avatar_owner', true ) === $user_id ) {
			wp_delete_attachment( $old, true );
		}
	}

	/**
	 * Controlla il file, lo ritaglia in quadrato e lo salva.
	 *
	 * @param int $user_id Utente.
	 * @return string Codice di esito.
	 */
	private function upload( int $user_id ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il nonce è già controllato in handle().
		$file = isset( $_FILES['ael_avatar_file'] ) && is_array( $_FILES['ael_avatar_file'] ) ? $_FILES['ael_avatar_file'] : array();
		if ( empty( $file['name'] ) || empty( $file['tmp_name'] ) ) {
			return 'empty';
		}
		if ( ! empty( $file['error'] ) ) {
			return UPLOAD_ERR_INI_SIZE === (int) $file['error'] || UPLOAD_ERR_FORM_SIZE === (int) $file['error'] ? 'size' : 'error';
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return 'size';
		}

		$mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
		);
		$check = wp_check_filetype_and_ext( (string) $file['tmp_name'], sanitize_file_name( (string) $file['name'] ), $mimes );
		if ( empty( $check['type'] ) || empty( $check['ext'] ) ) {
			return 'type';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$moved = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => $mimes,
			)
		);
		if ( ! is_array( $moved ) || ! empty( $moved['error'] ) || empty( $moved['file'] ) ) {
			return 'error';
		}

		// Ritaglio quadrato e riduzione: la foto resta leggera e sempre della stessa forma.
		$editor = wp_get_image_editor( $moved['file'] );
		if ( is_wp_error( $editor ) ) {
			wp_delete_file( $moved['file'] );
			return 'type';
		}
		$editor->resize( self::SIZE, self::SIZE, true );
		$saved = $editor->save( $moved['file'] );
		if ( is_wp_error( $saved ) ) {
			wp_delete_file( $moved['file'] );
			return 'error';
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => (string) $check['type'],
				'post_title'     => sprintf(
					/* translators: %d: ID utente */
					__( 'Foto profilo utente %d', 'academy-elearning' ),
					$user_id
				),
				'post_status'    => 'inherit',
				'post_author'    => $user_id,
			),
			$moved['file']
		);
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			wp_delete_file( $moved['file'] );
			return 'error';
		}
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $moved['file'] ) );
		update_post_meta( $attachment_id, '_ael_avatar_owner', $user_id );

		$this->delete_current( $user_id );
		update_user_meta( $user_id, self::META, (int) $attachment_id );
		return 'ok';
	}
}
