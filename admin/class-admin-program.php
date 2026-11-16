<?php
/**
 * Editor "Programma" dentro il corso e materiali della lezione.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nel corso: elenco di moduli e lezioni con aggiunta rapida, eliminazione e
 * riordino trascinando. Nella lezione: allegati scaricabili (materiali).
 *
 * Tutte le operazioni AJAX controllano nonce e permessi, e verificano che i
 * contenuti toccati appartengano davvero al corso aperto.
 */
final class AEL_Admin_Program {

	private const NONCE_AJAX      = 'ael_program';
	private const NONCE_MATERIALS = 'ael_save_materials';
	private const NONCE_FIELD     = 'ael_materials_nonce';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Admin_Program|null
	 */
	private static ?AEL_Admin_Program $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Admin_Program {
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
	 * Aggancia riquadri, script e richieste AJAX.
	 */
	public function boot(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'save_post_' . AEL_Content::LESSON, array( $this, 'save_materials' ), 10, 2 );
		add_action( 'wp_ajax_ael_program_add', array( $this, 'ajax_add' ) );
		add_action( 'wp_ajax_ael_program_order', array( $this, 'ajax_order' ) );
		add_action( 'wp_ajax_ael_program_delete', array( $this, 'ajax_delete' ) );
	}

	/**
	 * Registra i riquadri.
	 */
	public function add_boxes(): void {
		add_meta_box( 'ael_program', __( 'Programma del corso', 'academy-elearning' ), array( $this, 'box_program' ), AEL_Content::COURSE, 'normal', 'default' );
		add_meta_box( 'ael_materials', __( 'Materiali della lezione', 'academy-elearning' ), array( $this, 'box_materials' ), AEL_Content::LESSON, 'normal', 'default' );
	}

	/**
	 * Script e stili, solo nelle schermate di corso e lezione.
	 *
	 * @param string $hook Pagina di amministrazione.
	 */
	public function enqueue( $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( AEL_Content::COURSE, AEL_Content::LESSON ), true ) ) {
			return;
		}

		wp_enqueue_style( 'ael-admin-program', AEL_URL . 'assets/admin-program.css', array(), AEL_VERSION );
		wp_enqueue_script( 'ael-admin-program', AEL_URL . 'assets/admin-program.js', array( 'jquery', 'jquery-ui-sortable' ), AEL_VERSION, true );
		if ( AEL_Content::LESSON === $screen->post_type ) {
			wp_enqueue_media();
		}
		wp_localize_script(
			'ael-admin-program',
			'aelProgram',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_AJAX ),
				'i18n'    => array(
					'saving'        => __( 'Salvataggio…', 'academy-elearning' ),
					'saved'         => __( 'Ordine salvato.', 'academy-elearning' ),
					'error'         => __( 'Qualcosa non ha funzionato. Ricarica la pagina e riprova.', 'academy-elearning' ),
					'needTitle'     => __( 'Scrivi un titolo.', 'academy-elearning' ),
					'confirmLesson' => __( 'Eliminare questa lezione? Va nel cestino.', 'academy-elearning' ),
					'confirmModule' => __( 'Eliminare questo modulo e tutte le sue lezioni? Vanno nel cestino.', 'academy-elearning' ),
					'noLessons'     => __( 'Nessuna lezione. Aggiungila qui sotto.', 'academy-elearning' ),
					'pickTitle'     => __( 'Scegli i materiali', 'academy-elearning' ),
					'pickButton'    => __( 'Usa questi file', 'academy-elearning' ),
					'remove'        => __( 'Rimuovi', 'academy-elearning' ),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Programma                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Riquadro Programma nel corso.
	 *
	 * @param WP_Post $post Corso.
	 */
	public function box_program( WP_Post $post ): void {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Dai un titolo al corso e salvalo come bozza: poi qui potrai aggiungere moduli e lezioni.', 'academy-elearning' ) . '</p>';
			return;
		}
		?>
		<div class="ael-program" data-course="<?php echo esc_attr( (string) $post->ID ); ?>">
			<p class="description"><?php echo esc_html__( 'Aggiungi moduli e lezioni scrivendo il titolo. Trascina per cambiare l\'ordine: si salva da solo. Video, durata e materiali di ogni lezione si compilano da "Modifica".', 'academy-elearning' ); ?></p>
			<ul class="ael-modules">
				<?php foreach ( $this->modules_of( $post->ID ) as $module ) : ?>
					<?php echo $this->render_module( $module ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape dentro il metodo. ?>
				<?php endforeach; ?>
			</ul>
			<div class="ael-add ael-add-module">
				<input type="text" class="regular-text" maxlength="200" placeholder="<?php echo esc_attr__( 'Titolo del nuovo modulo', 'academy-elearning' ); ?>" aria-label="<?php echo esc_attr__( 'Titolo del nuovo modulo', 'academy-elearning' ); ?>">
				<button type="button" class="button button-primary" data-kind="module"><?php echo esc_html__( 'Aggiungi modulo', 'academy-elearning' ); ?></button>
			</div>
			<p class="ael-status" role="status" aria-live="polite"></p>
		</div>
		<?php
	}

	/**
	 * Moduli di un corso, in ordine.
	 *
	 * @param int $course_id Corso.
	 * @return WP_Post[]
	 */
	private function modules_of( int $course_id ): array {
		return get_posts(
			array(
				'post_type'   => AEL_Content::MODULE,
				'post_parent' => $course_id,
				'numberposts' => -1,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'orderby'     => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);
	}

	/**
	 * Lezioni di un modulo, in ordine.
	 *
	 * @param int $module_id Modulo.
	 * @return WP_Post[]
	 */
	private function lessons_of( int $module_id ): array {
		return get_posts(
			array(
				'post_type'   => AEL_Content::LESSON,
				'post_parent' => $module_id,
				'numberposts' => -1,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'orderby'     => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);
	}

	/**
	 * HTML di un modulo con le sue lezioni.
	 *
	 * @param WP_Post $module Modulo.
	 * @return string
	 */
	private function render_module( WP_Post $module ): string {
		$edit = get_edit_post_link( $module->ID, 'raw' );
		ob_start();
		?>
		<li class="ael-module" data-id="<?php echo esc_attr( (string) $module->ID ); ?>">
			<div class="ael-row ael-module-head">
				<span class="ael-handle ael-module-handle dashicons dashicons-menu" title="<?php echo esc_attr__( 'Trascina per spostare il modulo', 'academy-elearning' ); ?>"></span>
				<strong class="ael-title"><?php echo esc_html( get_the_title( $module ) ); ?></strong>
				<?php echo wp_kses_post( $this->status_badge( $module ) ); ?>
				<span class="ael-actions">
					<?php if ( $edit ) : ?>
						<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html__( 'Modifica', 'academy-elearning' ); ?></a>
					<?php endif; ?>
					<button type="button" class="button-link ael-delete"><?php echo esc_html__( 'Elimina', 'academy-elearning' ); ?></button>
				</span>
			</div>
			<ul class="ael-lessons">
				<?php foreach ( $this->lessons_of( $module->ID ) as $lesson ) : ?>
					<?php echo $this->render_lesson( $lesson ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape dentro il metodo. ?>
				<?php endforeach; ?>
			</ul>
			<div class="ael-add ael-add-lesson">
				<input type="text" class="regular-text" maxlength="200" placeholder="<?php echo esc_attr__( 'Titolo della nuova lezione', 'academy-elearning' ); ?>" aria-label="<?php echo esc_attr__( 'Titolo della nuova lezione', 'academy-elearning' ); ?>">
				<button type="button" class="button" data-kind="lesson"><?php echo esc_html__( 'Aggiungi lezione', 'academy-elearning' ); ?></button>
			</div>
		</li>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * HTML di una lezione.
	 *
	 * @param WP_Post $lesson Lezione.
	 * @return string
	 */
	private function render_lesson( WP_Post $lesson ): string {
		$edit   = get_edit_post_link( $lesson->ID, 'raw' );
		$source = (string) get_post_meta( $lesson->ID, '_ael_video_source', true );
		$min    = (int) get_post_meta( $lesson->ID, '_ael_duration_min', true );
		$bits   = array();
		if ( '' === $source ) {
			$bits[] = __( 'senza video', 'academy-elearning' );
		} else {
			$bits[] = 'youtube' === $source ? 'YouTube' : 'Vimeo';
		}
		if ( $min > 0 ) {
			/* translators: %d: minuti */
			$bits[] = sprintf( __( '%d min', 'academy-elearning' ), $min );
		}
		if ( get_post_meta( $lesson->ID, '_ael_is_preview', true ) ) {
			$bits[] = __( 'anteprima gratuita', 'academy-elearning' );
		}
		ob_start();
		?>
		<li class="ael-lesson" data-id="<?php echo esc_attr( (string) $lesson->ID ); ?>">
			<div class="ael-row">
				<span class="ael-handle dashicons dashicons-menu" title="<?php echo esc_attr__( 'Trascina per spostare la lezione', 'academy-elearning' ); ?>"></span>
				<span class="ael-title"><?php echo esc_html( get_the_title( $lesson ) ); ?></span>
				<?php echo wp_kses_post( $this->status_badge( $lesson ) ); ?>
				<span class="ael-meta"><?php echo esc_html( implode( ' · ', $bits ) ); ?></span>
				<span class="ael-actions">
					<?php if ( $edit ) : ?>
						<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html__( 'Modifica', 'academy-elearning' ); ?></a>
					<?php endif; ?>
					<button type="button" class="button-link ael-delete"><?php echo esc_html__( 'Elimina', 'academy-elearning' ); ?></button>
				</span>
			</div>
		</li>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Etichetta "Bozza" ecc. per i contenuti non pubblicati.
	 *
	 * @param WP_Post $post Contenuto.
	 * @return string
	 */
	private function status_badge( WP_Post $post ): string {
		if ( 'publish' === $post->post_status ) {
			return '';
		}
		$obj   = get_post_status_object( $post->post_status );
		$label = $obj ? $obj->label : $post->post_status;
		return '<span class="ael-badge">' . esc_html( $label ) . '</span>';
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Controlli comuni: nonce, permessi e corso valido. Esce con errore se qualcosa non va.
	 *
	 * @return int ID del corso.
	 */
	private function authorize(): int {
		check_ajax_referer( self::NONCE_AJAX, 'nonce' );
		$course_id = isset( $_POST['course'] ) ? absint( wp_unslash( $_POST['course'] ) ) : 0;
		// Basta poter modificare QUEL corso: il titolare può tutti, un creator solo i suoi.
		if ( AEL_Content::COURSE !== get_post_type( $course_id )
			|| ! current_user_can( 'edit_post', $course_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Permesso negato.', 'academy-elearning' ) ), 403 );
		}
		return $course_id;
	}

	/**
	 * ID dei moduli del corso (qualsiasi stato tranne cestino).
	 *
	 * @param int $course_id Corso.
	 * @return int[]
	 */
	private function module_ids( int $course_id ): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'   => AEL_Content::MODULE,
					'post_parent' => $course_id,
					'numberposts' => -1,
					'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
					'fields'      => 'ids',
				)
			)
		);
	}

	/**
	 * Aggiunge un modulo o una lezione in fondo.
	 */
	public function ajax_add(): void {
		$course_id = $this->authorize();
		$kind      = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$title     = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Scrivi un titolo.', 'academy-elearning' ) ), 400 );
		}

		if ( 'module' === $kind ) {
			$parent = $course_id;
			$type   = AEL_Content::MODULE;
		} elseif ( 'lesson' === $kind ) {
			$parent = isset( $_POST['parent'] ) ? absint( wp_unslash( $_POST['parent'] ) ) : 0;
			if ( ! in_array( $parent, $this->module_ids( $course_id ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'Modulo non valido.', 'academy-elearning' ) ), 400 );
			}
			$type = AEL_Content::LESSON;
		} else {
			wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'academy-elearning' ) ), 400 );
		}

		$siblings = get_posts(
			array(
				'post_type'   => $type,
				'post_parent' => $parent,
				'numberposts' => -1,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'fields'      => 'ids',
			)
		);
		$next     = 1;
		foreach ( $siblings as $sibling_id ) {
			$next = max( $next, (int) get_post_field( 'menu_order', $sibling_id ) + 1 );
		}

		// Chi non può pubblicare (un creator) crea in bozza: la pubblicazione resta al titolare.
		$type_object = get_post_type_object( $type );
		$can_publish = $type_object && current_user_can( $type_object->cap->publish_posts );

		$new_id = wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => $can_publish ? 'publish' : 'draft',
				'post_title'  => $title,
				'post_parent' => $parent,
				'menu_order'  => $next,
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => $new_id->get_error_message() ), 500 );
		}
		if ( AEL_Content::LESSON === $type ) {
			update_post_meta( $new_id, '_ael_course_id', $course_id );
		}

		/**
		 * Scatta quando cambia la struttura (moduli e lezioni) di un corso.
		 *
		 * @param int $course_id Corso.
		 */
		do_action( 'ael_program_changed', $course_id );

		$post = get_post( $new_id );
		wp_send_json_success(
			array(
				'html' => ( $post && AEL_Content::MODULE === $type ) ? $this->render_module( $post ) : $this->render_lesson( $post ),
			)
		);
	}

	/**
	 * Salva il nuovo ordine (e gli spostamenti di lezioni tra moduli).
	 * Riceve: [ { "id": modulo, "lessons": [ id, id ] }, ... ].
	 */
	public function ajax_order(): void {
		$course_id = $this->authorize();
		$raw       = isset( $_POST['order'] ) ? json_decode( wp_unslash( $_POST['order'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ogni valore viene convertito in intero sotto.
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Richiesta non valida.', 'academy-elearning' ) ), 400 );
		}

		$modules_ok = $this->module_ids( $course_id );
		$lessons_ok = $modules_ok ? array_map(
			'intval',
			get_posts(
				array(
					'post_type'       => AEL_Content::LESSON,
					'post_parent__in' => $modules_ok,
					'numberposts'     => -1,
					'post_status'     => array( 'publish', 'draft', 'pending', 'private' ),
					'fields'          => 'ids',
				)
			)
		) : array();

		// Prima si controlla tutto, poi si scrive: o va tutto o niente.
		$plan_modules = array();
		$plan_lessons = array();
		$seen_modules = array();
		$seen_lessons = array();
		foreach ( $raw as $m_index => $entry ) {
			$module_id = isset( $entry['id'] ) ? absint( $entry['id'] ) : 0;
			if ( ! in_array( $module_id, $modules_ok, true ) || isset( $seen_modules[ $module_id ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Ordine non valido.', 'academy-elearning' ) ), 400 );
			}
			$seen_modules[ $module_id ] = true;
			$plan_modules[ $module_id ] = (int) $m_index + 1;

			$lessons = ( isset( $entry['lessons'] ) && is_array( $entry['lessons'] ) ) ? $entry['lessons'] : array();
			foreach ( $lessons as $l_index => $lesson_id ) {
				$lesson_id = absint( $lesson_id );
				if ( ! in_array( $lesson_id, $lessons_ok, true ) || isset( $seen_lessons[ $lesson_id ] ) ) {
					wp_send_json_error( array( 'message' => __( 'Ordine non valido.', 'academy-elearning' ) ), 400 );
				}
				$seen_lessons[ $lesson_id ] = true;
				$plan_lessons[ $lesson_id ] = array( $module_id, (int) $l_index + 1 );
			}
		}

		foreach ( $plan_modules as $module_id => $position ) {
			if ( (int) get_post_field( 'menu_order', $module_id ) !== $position ) {
				wp_update_post(
					array(
						'ID'         => $module_id,
						'menu_order' => $position,
					)
				);
			}
		}
		foreach ( $plan_lessons as $lesson_id => $where ) {
			list( $module_id, $position ) = $where;
			if ( (int) get_post_field( 'post_parent', $lesson_id ) !== $module_id || (int) get_post_field( 'menu_order', $lesson_id ) !== $position ) {
				wp_update_post(
					array(
						'ID'          => $lesson_id,
						'post_parent' => $module_id,
						'menu_order'  => $position,
					)
				);
			}
			update_post_meta( $lesson_id, '_ael_course_id', $course_id );
		}

		/** Questa azione è documentata in ajax_add(). */
		do_action( 'ael_program_changed', $course_id );

		wp_send_json_success();
	}

	/**
	 * Manda nel cestino un modulo (con le sue lezioni) o una lezione.
	 */
	public function ajax_delete(): void {
		$course_id = $this->authorize();
		$id        = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		$type      = get_post_type( $id );
		$modules   = $this->module_ids( $course_id );

		// Si può eliminare solo ciò per cui WordPress concede il permesso (un creator non elimina il già pubblicato).
		$denied = ! $id || ! current_user_can( 'delete_post', $id );
		if ( ! $denied && AEL_Content::MODULE === $type ) {
			foreach ( $this->lessons_of( $id ) as $lesson ) {
				if ( ! current_user_can( 'delete_post', $lesson->ID ) ) {
					$denied = true;
					break;
				}
			}
		}
		if ( $denied ) {
			wp_send_json_error( array( 'message' => __( 'Permesso negato.', 'academy-elearning' ) ), 403 );
		}

		if ( AEL_Content::MODULE === $type && in_array( $id, $modules, true ) ) {
			foreach ( $this->lessons_of( $id ) as $lesson ) {
				wp_trash_post( $lesson->ID );
			}
			wp_trash_post( $id );
		} elseif ( AEL_Content::LESSON === $type && in_array( (int) get_post_field( 'post_parent', $id ), $modules, true ) ) {
			wp_trash_post( $id );
		} else {
			wp_send_json_error( array( 'message' => __( 'Contenuto non valido.', 'academy-elearning' ) ), 400 );
		}

		/** Questa azione è documentata in ajax_add(). */
		do_action( 'ael_program_changed', $course_id );

		wp_send_json_success();
	}

	/* ------------------------------------------------------------------ */
	/* Materiali della lezione                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * ID degli allegati di una lezione.
	 *
	 * @param int $lesson_id Lezione.
	 * @return int[]
	 */
	public static function materials( int $lesson_id ): array {
		$ids = get_post_meta( $lesson_id, '_ael_materials', true );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'intval', $ids ) ) ) : array();
	}

	/**
	 * Riquadro materiali della lezione.
	 *
	 * @param WP_Post $post Lezione.
	 */
	public function box_materials( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_MATERIALS, self::NONCE_FIELD, false );
		$ids = self::materials( $post->ID );
		?>
		<div class="ael-materials">
			<p class="description"><?php echo esc_html__( 'File da scaricare insieme alla lezione (PDF, schede, esercizi). Verranno mostrati solo a chi ha accesso alla lezione.', 'academy-elearning' ); ?></p>
			<input type="hidden" name="ael_material_ids" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
			<ul class="ael-material-list">
				<?php foreach ( $ids as $id ) : ?>
					<?php if ( 'attachment' === get_post_type( $id ) ) : ?>
						<li data-id="<?php echo esc_attr( (string) $id ); ?>">
							<span class="dashicons dashicons-media-default"></span>
							<span class="ael-material-name"><?php echo esc_html( wp_basename( (string) get_attached_file( $id ) ) ); ?></span>
							<button type="button" class="button-link ael-material-remove"><?php echo esc_html__( 'Rimuovi', 'academy-elearning' ); ?></button>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<button type="button" class="button ael-material-add"><?php echo esc_html__( 'Aggiungi file', 'academy-elearning' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Salva l'elenco dei materiali.
	 *
	 * @param int     $post_id ID della lezione.
	 * @param WP_Post $post    Lezione.
	 */
	public function save_materials( int $post_id, WP_Post $post ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! AEL_Content::is_form_post( $post_id )
			|| ! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_MATERIALS )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$ids = array();
		if ( isset( $_POST['ael_material_ids'] ) ) {
			$parts = explode( ',', sanitize_text_field( wp_unslash( $_POST['ael_material_ids'] ) ) );
			foreach ( $parts as $part ) {
				$id = absint( $part );
				if ( $id && 'attachment' === get_post_type( $id ) && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}
		}
		update_post_meta( $post_id, '_ael_materials', $ids );
	}
}
