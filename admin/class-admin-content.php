<?php
/**
 * Schermate di modifica di corsi, moduli, lezioni, docenti e raccolte.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Riquadri con i campi, salvataggio e colonne degli elenchi.
 */
final class AEL_Admin_Content {

	private const NONCE_ACTION = 'ael_save_content';
	private const NONCE_FIELD  = 'ael_content_nonce';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Admin_Content|null
	 */
	private static ?AEL_Admin_Content $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Admin_Content {
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
	 * Aggancia riquadri, salvataggio, colonne e ordinamento.
	 */
	public function boot(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'show_notice' ) );
		add_action( 'pre_get_posts', array( $this, 'order_lists' ) );

		foreach ( array( AEL_Content::COURSE, AEL_Content::MODULE, AEL_Content::LESSON ) as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( $this, 'columns' ) );
			add_action( "manage_{$type}_posts_custom_column", array( $this, 'column_value' ), 10, 2 );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Riquadri                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Registra i riquadri.
	 */
	public function add_boxes(): void {
		add_meta_box( 'ael_course_details', __( 'Dettagli del corso', 'academy-elearning' ), array( $this, 'box_course' ), AEL_Content::COURSE, 'normal', 'high' );
		add_meta_box( 'ael_place', __( 'Collocazione', 'academy-elearning' ), array( $this, 'box_place' ), array( AEL_Content::MODULE, AEL_Content::LESSON ), 'side', 'high' );
		add_meta_box( 'ael_lesson_details', __( 'Video e opzioni', 'academy-elearning' ), array( $this, 'box_lesson' ), AEL_Content::LESSON, 'normal', 'high' );
		add_meta_box( 'ael_teacher_details', __( 'Account del docente', 'academy-elearning' ), array( $this, 'box_teacher' ), AEL_Content::TEACHER, 'side', 'default' );
		add_meta_box( 'ael_collection_details', __( 'Corsi della raccolta', 'academy-elearning' ), array( $this, 'box_collection' ), AEL_Content::COLLECTION, 'normal', 'high' );
	}

	/**
	 * Legge un meta del contenuto.
	 *
	 * @param int    $post_id ID.
	 * @param string $key     Chiave senza prefisso.
	 * @return mixed
	 */
	private function meta( int $post_id, string $key ) {
		return get_post_meta( $post_id, '_ael_' . $key, true );
	}

	/**
	 * Riquadro del corso.
	 *
	 * @param WP_Post $post Corso.
	 */
	public function box_course( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, false );

		$teachers = get_posts(
			array(
				'post_type'   => AEL_Content::TEACHER,
				'numberposts' => -1,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$levels   = array(
			''             => __( '— Non indicato —', 'academy-elearning' ),
			'principiante' => __( 'Principiante', 'academy-elearning' ),
			'intermedio'   => __( 'Intermedio', 'academy-elearning' ),
			'avanzato'     => __( 'Avanzato', 'academy-elearning' ),
		);
		$teacher  = (int) $this->meta( $post->ID, 'teacher_id' );
		$level    = (string) $this->meta( $post->ID, 'level' );
		// Docente e abbonamento li decide il titolare: un docente non li vede né li cambia.
		$manager  = current_user_can( AEL_Content::CAP );
		?>
		<table class="form-table" role="presentation"><tbody>
			<tr><th><label for="ael_subtitle"><?php echo esc_html__( 'Sottotitolo', 'academy-elearning' ); ?></label></th>
				<td><input type="text" class="large-text" id="ael_subtitle" name="ael_subtitle" value="<?php echo esc_attr( (string) $this->meta( $post->ID, 'subtitle' ) ); ?>"></td></tr>
			<?php if ( $manager ) : ?>
			<tr><th><label for="ael_teacher_id"><?php echo esc_html__( 'Docente', 'academy-elearning' ); ?></label></th>
				<td>
					<select id="ael_teacher_id" name="ael_teacher_id">
						<option value="0"><?php echo esc_html__( '— Scegli —', 'academy-elearning' ); ?></option>
						<?php foreach ( $teachers as $t ) : ?>
							<option value="<?php echo esc_attr( (string) $t->ID ); ?>" <?php selected( $teacher, $t->ID ); ?>><?php echo esc_html( get_the_title( $t ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( empty( $teachers ) ) : ?>
						<p class="description"><?php echo esc_html__( 'Non ci sono ancora docenti: creane uno da Academy → Docenti.', 'academy-elearning' ); ?></p>
					<?php endif; ?>
				</td></tr>
			<?php endif; ?>
			<tr><th><label for="ael_level"><?php echo esc_html__( 'Livello', 'academy-elearning' ); ?></label></th>
				<td><select id="ael_level" name="ael_level">
					<?php foreach ( $levels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $level, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
			<tr><th><label for="ael_learn"><?php echo esc_html__( 'Cosa si impara', 'academy-elearning' ); ?></label></th>
				<td><textarea id="ael_learn" name="ael_learn" rows="5" class="large-text"><?php echo esc_textarea( (string) $this->meta( $post->ID, 'learn' ) ); ?></textarea>
					<p class="description"><?php echo esc_html__( 'Un punto per riga.', 'academy-elearning' ); ?></p></td></tr>
			<tr><th><label for="ael_audience"><?php echo esc_html__( 'A chi è rivolto', 'academy-elearning' ); ?></label></th>
				<td><textarea id="ael_audience" name="ael_audience" rows="3" class="large-text"><?php echo esc_textarea( (string) $this->meta( $post->ID, 'audience' ) ); ?></textarea></td></tr>
			<tr><th><label for="ael_preview_video"><?php echo esc_html__( 'Video di anteprima', 'academy-elearning' ); ?></label></th>
				<td><input type="url" class="large-text" id="ael_preview_video" name="ael_preview_video" value="<?php echo esc_attr( (string) $this->meta( $post->ID, 'preview_video' ) ); ?>">
					<p class="description"><?php echo esc_html__( 'Indirizzo del video mostrato a tutti nella scheda del corso.', 'academy-elearning' ); ?></p></td></tr>
			<?php if ( $manager ) : ?>
		<tr><th><?php echo esc_html__( 'Abbonamento', 'academy-elearning' ); ?></th>
				<td><label><input type="checkbox" name="ael_excluded_from_subscription" value="1" <?php checked( (int) $this->meta( $post->ID, 'excluded_from_subscription' ), 1 ); ?>>
					<?php echo esc_html__( 'Escludi questo corso dall\'abbonamento (si compra solo a parte)', 'academy-elearning' ); ?></label></td></tr>
		<?php endif; ?>
		</tbody></table>
		<?php
	}

	/**
	 * Riquadro "Collocazione" di moduli e lezioni: a cosa appartengono e in che posizione.
	 *
	 * @param WP_Post $post Modulo o lezione.
	 */
	public function box_place( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, false );

		$is_module = AEL_Content::MODULE === $post->post_type;
		$parents   = $is_module ? $this->course_options() : $this->module_options();
		?>
		<p><label for="ael_parent_id"><strong><?php echo esc_html( $is_module ? __( 'Corso', 'academy-elearning' ) : __( 'Modulo', 'academy-elearning' ) ); ?></strong></label></p>
		<select id="ael_parent_id" name="ael_parent_id" style="width:100%">
			<option value="0"><?php echo esc_html__( '— Scegli —', 'academy-elearning' ); ?></option>
			<?php foreach ( $parents as $id => $label ) : ?>
				<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( (int) $post->post_parent, $id ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p><label for="ael_menu_order"><strong><?php echo esc_html__( 'Posizione', 'academy-elearning' ); ?></strong></label></p>
		<input type="number" id="ael_menu_order" name="ael_menu_order" value="<?php echo esc_attr( (string) (int) $post->menu_order ); ?>" min="0" step="1" style="width:100%">
		<p class="description"><?php echo esc_html__( 'Numero più basso = prima. Più comodo: riordina trascinando dal riquadro "Programma" del corso.', 'academy-elearning' ); ?></p>
		<?php
	}

	/**
	 * Riquadro video e opzioni della lezione.
	 *
	 * @param WP_Post $post Lezione.
	 */
	public function box_lesson( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, false );

		$allowed = (array) AEL_Settings::get( 'video_sources', array() );
		$labels  = array(
			'youtube' => 'YouTube',
			'vimeo'   => 'Vimeo',
		);
		$source  = (string) $this->meta( $post->ID, 'video_source' );
		?>
		<table class="form-table" role="presentation"><tbody>
			<tr><th><label for="ael_video_source"><?php echo esc_html__( 'Sorgente', 'academy-elearning' ); ?></label></th>
				<td><select id="ael_video_source" name="ael_video_source">
					<option value=""><?php echo esc_html__( '— Nessun video —', 'academy-elearning' ); ?></option>
					<?php foreach ( $allowed as $key ) : ?>
						<?php if ( isset( $labels[ $key ] ) ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $source, $key ); ?>><?php echo esc_html( $labels[ $key ] ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select></td></tr>
			<tr><th><label for="ael_video_ref"><?php echo esc_html__( 'Video', 'academy-elearning' ); ?></label></th>
				<td><input type="text" class="large-text" id="ael_video_ref" name="ael_video_ref" value="<?php echo esc_attr( (string) $this->meta( $post->ID, 'video_ref' ) ); ?>">
					<p class="description"><?php echo esc_html__( 'Incolla l\'indirizzo del video (o il suo ID). Per YouTube usa i video "non in elenco".', 'academy-elearning' ); ?></p></td></tr>
			<tr><th><label for="ael_duration_min"><?php echo esc_html__( 'Durata (minuti)', 'academy-elearning' ); ?></label></th>
				<td><input type="number" id="ael_duration_min" name="ael_duration_min" min="0" step="1" value="<?php echo esc_attr( (string) (int) $this->meta( $post->ID, 'duration_min' ) ); ?>"></td></tr>
			<tr><th><?php echo esc_html__( 'Anteprima gratuita', 'academy-elearning' ); ?></th>
				<td><label><input type="checkbox" name="ael_is_preview" value="1" <?php checked( (int) $this->meta( $post->ID, 'is_preview' ), 1 ); ?>>
					<?php echo esc_html__( 'Visibile a tutti, anche senza acquisto (se le anteprime sono attive)', 'academy-elearning' ); ?></label></td></tr>
		</tbody></table>
		<?php
	}

	/**
	 * Riquadro del docente: l'utente WordPress collegato.
	 *
	 * @param WP_Post $post Docente.
	 */
	public function box_teacher( WP_Post $post ): void {
		if ( ! current_user_can( AEL_Content::CAP ) ) {
			echo '<p class="description">' . esc_html__( 'Il collegamento all\'account lo gestisce il titolare dell\'academy.', 'academy-elearning' ) . '</p>';
			return;
		}
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, false );

		$users   = get_users(
			array(
				'orderby' => 'display_name',
				'fields'  => array( 'ID', 'display_name', 'user_login' ),
			)
		);
		$current = (int) $this->meta( $post->ID, 'user_id' );
		?>
		<p><label for="ael_user_id"><?php echo esc_html__( 'Utente collegato', 'academy-elearning' ); ?></label></p>
		<select id="ael_user_id" name="ael_user_id" style="width:100%">
			<option value="0"><?php echo esc_html__( '— Nessuno —', 'academy-elearning' ); ?></option>
			<?php foreach ( $users as $user ) : ?>
				<option value="<?php echo esc_attr( (string) $user->ID ); ?>" <?php selected( $current, (int) $user->ID ); ?>>
					<?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php echo esc_html__( 'Serve per il pannello del docente, più avanti. Facoltativo.', 'academy-elearning' ); ?></p>
		<?php
	}

	/**
	 * Riquadro della raccolta: una posizione per ogni corso. Vuoto = non incluso.
	 *
	 * @param WP_Post $post Raccolta.
	 */
	public function box_collection( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, false );

		$courses  = $this->course_options();
		$selected = array_values( array_map( 'intval', (array) $this->meta( $post->ID, 'course_ids' ) ) );
		?>
		<p class="description"><?php echo esc_html__( 'Scrivi una posizione (1, 2, 3...) accanto ai corsi da includere. Lascia vuoto per escluderli.', 'academy-elearning' ); ?></p>
		<?php if ( empty( $courses ) ) : ?>
			<p><?php echo esc_html__( 'Non ci sono ancora corsi.', 'academy-elearning' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:600px"><tbody>
			<?php foreach ( $courses as $id => $label ) : ?>
				<?php $index = array_search( (int) $id, $selected, true ); ?>
				<tr>
					<td style="width:90px"><input type="number" min="0" step="1" style="width:70px" name="ael_coll_pos[<?php echo esc_attr( (string) $id ); ?>]"
						value="<?php echo esc_attr( false === $index ? '' : (string) ( $index + 1 ) ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: titolo del corso */ __( 'Posizione di %s', 'academy-elearning' ), $label ) ); ?>"></td>
					<td><?php echo esc_html( $label ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Elenco dei corsi: ID => titolo.
	 *
	 * @return array<int, string>
	 */
	private function course_options(): array {
		$posts = get_posts(
			array(
				'post_type'   => AEL_Content::COURSE,
				'numberposts' => -1,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$out   = array();
		foreach ( AEL_Content::editable( $posts ) as $p ) {
			$out[ (int) $p->ID ] = get_the_title( $p );
		}
		return $out;
	}

	/**
	 * Elenco dei moduli: ID => "Corso › Modulo".
	 *
	 * @return array<int, string>
	 */
	private function module_options(): array {
		$posts = get_posts(
			array(
				'post_type'   => AEL_Content::MODULE,
				'numberposts' => -1,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'orderby'     => array(
					'post_parent' => 'ASC',
					'menu_order'  => 'ASC',
					'title'       => 'ASC',
				),
			)
		);
		$out   = array();
		foreach ( AEL_Content::editable( $posts ) as $p ) {
			$course              = $p->post_parent ? get_the_title( (int) $p->post_parent ) : __( '(senza corso)', 'academy-elearning' );
			$out[ (int) $p->ID ] = $course . ' › ' . get_the_title( $p );
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Salvataggio                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * La richiesta è autorizzata a salvare i campi del plugin?
	 */
	private function request_is_valid(): bool {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return false;
		}
		return (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION );
	}

	/**
	 * Imposta genitore e posizione di moduli e lezioni prima del salvataggio,
	 * così non servono salvataggi ulteriori.
	 *
	 * @param array $data    Dati del contenuto.
	 * @param array $postarr Dati inviati.
	 * @return array
	 */
	public function filter_post_data( $data, $postarr ) {
		$type = $data['post_type'] ?? '';
		if ( ! in_array( $type, array( AEL_Content::MODULE, AEL_Content::LESSON ), true ) ) {
			return $data;
		}
		$type_object = get_post_type_object( $type );
		if ( ! $this->request_is_valid() || ! $type_object || ! current_user_can( $type_object->cap->edit_posts ) ) {
			return $data;
		}

		if ( isset( $_POST['ael_parent_id'] ) ) {
			$parent   = absint( wp_unslash( $_POST['ael_parent_id'] ) );
			$expected = AEL_Content::MODULE === $type ? AEL_Content::COURSE : AEL_Content::MODULE;
			// Si può collocare solo dentro a un corso o modulo che si può modificare.
			if ( 0 === $parent || ( get_post_type( $parent ) === $expected && current_user_can( 'edit_post', $parent ) ) ) {
				$data['post_parent'] = $parent;
			}
		}
		if ( isset( $_POST['ael_menu_order'] ) ) {
			$data['menu_order'] = absint( wp_unslash( $_POST['ael_menu_order'] ) );
		}
		return $data;
	}

	/**
	 * Salva i campi.
	 *
	 * @param int     $post_id ID.
	 * @param WP_Post $post    Contenuto.
	 */
	public function save_post( int $post_id, WP_Post $post ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		// Si salva solo il contenuto aperto nel modulo (non quelli toccati da altri salvataggi nella stessa richiesta).
		if ( ! AEL_Content::is_form_post( $post_id ) || ! $this->request_is_valid() || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		switch ( $post->post_type ) {
			case AEL_Content::COURSE:
				$this->save_course( $post_id );
				break;
			case AEL_Content::MODULE:
				$this->sync_lessons_course( $post );
				break;
			case AEL_Content::LESSON:
				$this->save_lesson( $post_id, $post );
				break;
			case AEL_Content::TEACHER:
				// Il collegamento all'utente lo cambia solo il titolare.
				if ( current_user_can( AEL_Content::CAP ) ) {
					$user_id = isset( $_POST['ael_user_id'] ) ? absint( wp_unslash( $_POST['ael_user_id'] ) ) : 0;
					update_post_meta( $post_id, '_ael_user_id', ( $user_id && get_userdata( $user_id ) ) ? $user_id : 0 );
				}
				break;
			case AEL_Content::COLLECTION:
				$this->save_collection( $post_id );
				break;
		}
	}

	/**
	 * Salva i campi del corso.
	 *
	 * @param int $post_id ID del corso.
	 */
	private function save_course( int $post_id ): void {
		update_post_meta( $post_id, '_ael_subtitle', isset( $_POST['ael_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['ael_subtitle'] ) ) : '' );
		update_post_meta( $post_id, '_ael_learn', isset( $_POST['ael_learn'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ael_learn'] ) ) : '' );
		update_post_meta( $post_id, '_ael_audience', isset( $_POST['ael_audience'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ael_audience'] ) ) : '' );
		update_post_meta( $post_id, '_ael_preview_video', isset( $_POST['ael_preview_video'] ) ? esc_url_raw( wp_unslash( $_POST['ael_preview_video'] ) ) : '' );
		// Abbonamento e docente li decide il titolare.
		$manager = current_user_can( AEL_Content::CAP );
		if ( $manager ) {
			update_post_meta( $post_id, '_ael_excluded_from_subscription', empty( $_POST['ael_excluded_from_subscription'] ) ? 0 : 1 );
		}

		$level = isset( $_POST['ael_level'] ) ? sanitize_key( wp_unslash( $_POST['ael_level'] ) ) : '';
		update_post_meta( $post_id, '_ael_level', in_array( $level, array( 'principiante', 'intermedio', 'avanzato' ), true ) ? $level : '' );

		if ( $manager ) {
			$teacher = isset( $_POST['ael_teacher_id'] ) ? absint( wp_unslash( $_POST['ael_teacher_id'] ) ) : 0;
			update_post_meta( $post_id, '_ael_teacher_id', ( $teacher && AEL_Content::TEACHER === get_post_type( $teacher ) ) ? $teacher : 0 );
		}
	}

	/**
	 * Salva i campi della lezione e copia l'ID del corso (serve per le ricerche).
	 *
	 * @param int     $post_id ID della lezione.
	 * @param WP_Post $post    Lezione.
	 */
	private function save_lesson( int $post_id, WP_Post $post ): void {
		$allowed = (array) AEL_Settings::get( 'video_sources', array() );
		$source  = isset( $_POST['ael_video_source'] ) ? sanitize_key( wp_unslash( $_POST['ael_video_source'] ) ) : '';
		$input   = isset( $_POST['ael_video_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['ael_video_ref'] ) ) : '';
		$ref     = '';

		if ( '' !== $source && in_array( $source, $allowed, true ) ) {
			$ref = AEL_Content::normalize_video_ref( $source, $input );
			if ( '' === $ref && '' !== trim( $input ) ) {
				set_transient( 'ael_notice_' . get_current_user_id(), __( 'Il video non è stato riconosciuto: controlla l\'indirizzo e la sorgente scelta.', 'academy-elearning' ), MINUTE_IN_SECONDS );
			}
		} else {
			$source = '';
		}
		update_post_meta( $post_id, '_ael_video_source', ( '' !== $ref ) ? $source : '' );
		update_post_meta( $post_id, '_ael_video_ref', $ref );
		update_post_meta( $post_id, '_ael_duration_min', isset( $_POST['ael_duration_min'] ) ? absint( wp_unslash( $_POST['ael_duration_min'] ) ) : 0 );
		update_post_meta( $post_id, '_ael_is_preview', empty( $_POST['ael_is_preview'] ) ? 0 : 1 );

		$module    = $post->post_parent ? get_post( (int) $post->post_parent ) : null;
		$course_id = ( $module && AEL_Content::MODULE === $module->post_type ) ? (int) $module->post_parent : 0;
		update_post_meta( $post_id, '_ael_course_id', $course_id );
	}

	/**
	 * Quando un modulo cambia corso, aggiorna il corso copiato nelle sue lezioni.
	 *
	 * @param WP_Post $module Modulo.
	 */
	private function sync_lessons_course( WP_Post $module ): void {
		$lessons = get_posts(
			array(
				'post_type'   => AEL_Content::LESSON,
				'post_parent' => $module->ID,
				'numberposts' => -1,
				'post_status' => 'any',
				'fields'      => 'ids',
			)
		);
		foreach ( $lessons as $lesson_id ) {
			update_post_meta( (int) $lesson_id, '_ael_course_id', (int) $module->post_parent );
		}
	}

	/**
	 * Salva l'elenco ordinato dei corsi di una raccolta.
	 *
	 * @param int $post_id ID della raccolta.
	 */
	private function save_collection( int $post_id ): void {
		$positions = array();
		if ( isset( $_POST['ael_coll_pos'] ) && is_array( $_POST['ael_coll_pos'] ) ) {
			foreach ( wp_unslash( $_POST['ael_coll_pos'] ) as $course_id => $position ) {
				$course_id = absint( $course_id );
				if ( AEL_Content::COURSE === get_post_type( $course_id ) ) {
					$positions[ $course_id ] = absint( $position );
				}
			}
		}
		update_post_meta( $post_id, '_ael_course_ids', AEL_Content::order_collection( $positions ) );
	}

	/**
	 * Mostra un avviso salvato in precedenza.
	 */
	public function show_notice(): void {
		$key     = 'ael_notice_' . get_current_user_id();
		$message = get_transient( $key );
		if ( $message ) {
			delete_transient( $key );
			printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( (string) $message ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Elenchi                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Negli elenchi di moduli e lezioni: ordina per corso/modulo e posizione.
	 *
	 * @param WP_Query $query Richiesta.
	 */
	public function order_lists( $query ): void {
		if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}
		$type = $query->get( 'post_type' );
		if ( ( AEL_Content::MODULE === $type || AEL_Content::LESSON === $type ) && ! $query->get( 'orderby' ) ) {
			$query->set(
				'orderby',
				array(
					'post_parent' => 'ASC',
					'menu_order'  => 'ASC',
					'title'       => 'ASC',
				)
			);
		}
	}

	/**
	 * Colonne degli elenchi.
	 *
	 * @param array $columns Colonne.
	 * @return array
	 */
	public function columns( $columns ) {
		// Il nome del filtro contiene il tipo: manage_ael_course_posts_columns.
		$type = str_replace( array( 'manage_', '_posts_columns' ), '', current_filter() );
		$new  = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				if ( AEL_Content::COURSE === $type ) {
					$new['ael_teacher'] = __( 'Docente', 'academy-elearning' );
					$new['ael_level']   = __( 'Livello', 'academy-elearning' );
				} elseif ( AEL_Content::MODULE === $type ) {
					$new['ael_parent'] = __( 'Corso', 'academy-elearning' );
					$new['ael_order']  = __( 'Posizione', 'academy-elearning' );
				} elseif ( AEL_Content::LESSON === $type ) {
					$new['ael_parent']  = __( 'Corso › Modulo', 'academy-elearning' );
					$new['ael_order']   = __( 'Posizione', 'academy-elearning' );
					$new['ael_video']   = __( 'Video', 'academy-elearning' );
					$new['ael_preview'] = __( 'Anteprima', 'academy-elearning' );
				}
			}
		}
		return $new;
	}

	/**
	 * Valore delle colonne.
	 *
	 * @param string $column  Colonna.
	 * @param int    $post_id ID.
	 */
	public function column_value( $column, $post_id ): void {
		$post_id = (int) $post_id;
		switch ( $column ) {
			case 'ael_teacher':
				$teacher = (int) $this->meta( $post_id, 'teacher_id' );
				echo $teacher ? esc_html( get_the_title( $teacher ) ) : '—';
				break;
			case 'ael_level':
				$level = (string) $this->meta( $post_id, 'level' );
				echo '' !== $level ? esc_html( ucfirst( $level ) ) : '—';
				break;
			case 'ael_parent':
				$post = get_post( $post_id );
				if ( ! $post || ! $post->post_parent ) {
					echo '—';
					break;
				}
				if ( AEL_Content::MODULE === $post->post_type ) {
					echo esc_html( get_the_title( (int) $post->post_parent ) );
				} else {
					$module = get_post( (int) $post->post_parent );
					echo $module ? esc_html( ( $module->post_parent ? get_the_title( (int) $module->post_parent ) : '—' ) . ' › ' . get_the_title( $module ) ) : '—';
				}
				break;
			case 'ael_order':
				$post = get_post( $post_id );
				echo $post ? esc_html( (string) (int) $post->menu_order ) : '';
				break;
			case 'ael_video':
				$source = (string) $this->meta( $post_id, 'video_source' );
				echo '' !== $source ? esc_html( 'youtube' === $source ? 'YouTube' : 'Vimeo' ) : '—';
				break;
			case 'ael_preview':
				echo $this->meta( $post_id, 'is_preview' ) ? esc_html__( 'Sì', 'academy-elearning' ) : '—';
				break;
		}
	}
}
