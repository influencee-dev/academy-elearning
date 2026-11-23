<?php
/**
 * Abilities dell'academy per assistenti AI (MCP).
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registra le "abilities" di WordPress (API ufficiale, dal 6.9) per leggere e modificare corsi, moduli,
 * lezioni e schede docente. Il plugin ufficiale MCP Adapter le espone a Claude.
 *
 * Regole:
 * - ogni ability controlla i permessi dell'utente che si collega (stessi dell'amministrazione): chi non può
 *   modificare un corso non lo modifica nemmeno da qui;
 * - non c'è nessuna ability su pagamenti, rimborsi, chiavi, utenti o interruttori dei moduli;
 * - eliminare vuol dire solo mandare nel cestino (si recupera dall'amministrazione);
 * - chi non può pubblicare crea e salva in bozza, come nell'amministrazione.
 */
final class AEL_Mcp_Abilities {

	public const CATEGORY = 'academy-elearning';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Mcp_Abilities|null
	 */
	private static ?AEL_Mcp_Abilities $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Mcp_Abilities {
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
	 * Aggancia la registrazione. Senza l'API delle abilities (WordPress prima del 6.9) non fa nulla.
	 */
	public function boot(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Categoria delle abilities dell'academy.
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Academy', 'academy-elearning' ),
				'description' => __( 'Corsi, moduli, lezioni e docenti dell\'academy.', 'academy-elearning' ),
			)
		);
	}

	/**
	 * Registra tutte le abilities.
	 */
	public function register_abilities(): void {
		$this->add(
			'list-courses',
			__( 'Elenca i corsi', 'academy-elearning' ),
			__( 'Elenca i corsi dell\'academy con stato, docente, livello, prezzo e numero di lezioni.', 'academy-elearning' ),
			array(
				'type'       => 'object',
				'default'    => array(),
				'properties' => array(
					'status' => array(
						'type'        => 'string',
						'enum'        => array( 'any', 'publish', 'draft', 'pending', 'private' ),
						'description' => 'Filtra per stato. Predefinito: tutti.',
					),
				),
			),
			array( $this, 'list_courses' ),
			array( $this, 'can_read_content' ),
			true
		);

		$this->add(
			'get-course',
			__( 'Legge un corso', 'academy-elearning' ),
			__( 'Restituisce tutti i campi di un corso e il suo programma (moduli e lezioni con i loro ID).', 'academy-elearning' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'id' => array(
						'type'        => 'integer',
						'description' => 'ID del corso.',
					),
				),
				'required'   => array( 'id' ),
			),
			array( $this, 'get_course' ),
			array( $this, 'can_read_content' ),
			true
		);

		$this->add(
			'save-course',
			__( 'Crea o modifica un corso', 'academy-elearning' ),
			__( 'Senza "id" crea un corso nuovo, con "id" modifica quello esistente (si cambiano solo i campi indicati). Il prezzo, il docente e l\'esclusione dall\'abbonamento li cambia solo l\'agenzia o il titolare. Chi non può pubblicare salva in bozza.', 'academy-elearning' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'id'                      => array( 'type' => 'integer' ),
					'title'                   => array( 'type' => 'string' ),
					'description'             => array(
						'type'        => 'string',
						'description' => 'Descrizione del corso (testo o HTML semplice).',
					),
					'excerpt'                 => array(
						'type'        => 'string',
						'description' => 'Riassunto breve mostrato nel catalogo.',
					),
					'subtitle'                => array( 'type' => 'string' ),
					'learn'                   => array(
						'type'        => 'string',
						'description' => 'Cosa si impara, una voce per riga.',
					),
					'audience'                => array(
						'type'        => 'string',
						'description' => 'A chi è rivolto, una voce per riga.',
					),
					'preview_video'           => array(
						'type'        => 'string',
						'description' => 'Indirizzo del video di anteprima.',
					),
					'level'                   => array(
						'type' => 'string',
						'enum' => array( '', 'principiante', 'intermedio', 'avanzato' ),
					),
					'teacher_id'              => array(
						'type'        => 'integer',
						'description' => 'ID della scheda docente (0 per nessuno). Solo agenzia o titolare.',
					),
					'excluded_from_subscription' => array(
						'type'        => 'boolean',
						'description' => 'Escludi il corso dall\'abbonamento. Solo agenzia o titolare.',
					),
					'price'                   => array(
						'type'        => 'number',
						'description' => 'Prezzo in euro, IVA inclusa (0 = non in vendita). Solo agenzia o titolare, con il modulo Acquisto singolo acceso.',
					),
					'status'                  => array(
						'type' => 'string',
						'enum' => array( 'draft', 'publish' ),
					),
				),
			),
			array( $this, 'save_course' ),
			array( $this, 'can_save_course' ),
			false
		);

		$this->add(
			'save-module',
			__( 'Crea o modifica un modulo', 'academy-elearning' ),
			__( 'Senza "id" crea un modulo in un corso ("course_id" obbligatorio), con "id" lo modifica. "position" è la posizione nel corso (1 = primo).', 'academy-elearning' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'id'        => array( 'type' => 'integer' ),
					'course_id' => array( 'type' => 'integer' ),
					'title'     => array( 'type' => 'string' ),
					'position'  => array( 'type' => 'integer' ),
				),
			),
			array( $this, 'save_module' ),
			array( $this, 'can_save_program_item' ),
			false
		);

		$this->add(
			'save-lesson',
			__( 'Crea o modifica una lezione', 'academy-elearning' ),
			__( 'Senza "id" crea una lezione in un modulo ("module_id" obbligatorio), con "id" la modifica. Il video si indica con "video_source" (youtube, vimeo) e "video_ref" (l\'indirizzo o l\'ID). Con "module_id" su una lezione esistente la si sposta.', 'academy-elearning' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'id'           => array( 'type' => 'integer' ),
					'module_id'    => array( 'type' => 'integer' ),
					'title'        => array( 'type' => 'string' ),
					'content'      => array(
						'type'        => 'string',
						'description' => 'Testo di accompagnamento della lezione.',
					),
					'video_source' => array( 'type' => 'string' ),
					'video_ref'    => array( 'type' => 'string' ),
					'duration_min' => array( 'type' => 'integer' ),
					'is_preview'   => array(
						'type'        => 'boolean',
						'description' => 'Anteprima gratuita.',
					),
					'position'     => array( 'type' => 'integer' ),
				),
			),
			array( $this, 'save_lesson' ),
			array( $this, 'can_save_program_item' ),
			false
		);

		$this->add(
			'trash-program-item',
			__( 'Manda nel cestino un modulo o una lezione', 'academy-elearning' ),
			__( 'Manda nel cestino un modulo (con le sue lezioni) o una lezione. Si recupera dal cestino dell\'amministrazione.', 'academy-elearning' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'id' => array( 'type' => 'integer' ),
				),
				'required'   => array( 'id' ),
			),
			array( $this, 'trash_item' ),
			array( $this, 'can_trash_item' ),
			false,
			true
		);

		$this->add(
			'list-teachers',
			__( 'Elenca i docenti', 'academy-elearning' ),
			__( 'Elenca le schede docente con frase di presentazione e canali social.', 'academy-elearning' ),
			array(
				'type'       => 'object',
				'properties' => new stdClass(),
				'default'    => array(),
			),
			array( $this, 'list_teachers' ),
			array( $this, 'can_read_content' ),
			true
		);

		$this->add(
			'save-teacher',
			__( 'Crea o modifica la scheda di un docente', 'academy-elearning' ),
			__( 'Senza "id" crea una scheda, con "id" la modifica. "channels" è l\'elenco dei canali social (platform, handle, url, followers, engagement in %). Il collegamento all\'utente WordPress lo cambia solo l\'agenzia o il titolare.', 'academy-elearning' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'id'          => array( 'type' => 'integer' ),
					'title'       => array(
						'type'        => 'string',
						'description' => 'Nome del docente.',
					),
					'bio'         => array( 'type' => 'string' ),
					'tagline'     => array( 'type' => 'string' ),
					'accent'      => array(
						'type'        => 'string',
						'description' => 'Colore in formato #RRGGBB.',
					),
					'stats_date'  => array(
						'type'        => 'string',
						'description' => 'Data di aggiornamento dei numeri, per esempio "novembre 2026".',
					),
					'channels'    => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'platform'   => array( 'type' => 'string' ),
								'handle'     => array( 'type' => 'string' ),
								'url'        => array( 'type' => 'string' ),
								'followers'  => array( 'type' => 'integer' ),
								'engagement' => array( 'type' => 'number' ),
							),
						),
					),
					'user_id'     => array( 'type' => 'integer' ),
					'status'      => array(
						'type' => 'string',
						'enum' => array( 'draft', 'publish' ),
					),
				),
			),
			array( $this, 'save_teacher' ),
			array( $this, 'can_save_teacher' ),
			false
		);

		$this->add(
			'academy-summary',
			__( 'Riepilogo dei numeri dell\'academy', 'academy-elearning' ),
			__( 'Numeri in sola lettura: studenti con accesso attivo, accessi per origine, lezioni completate e ore guardate negli ultimi 30 giorni, corsi più visti.', 'academy-elearning' ),
			array(
				'type'       => 'object',
				'properties' => new stdClass(),
				'default'    => array(),
			),
			array( $this, 'summary' ),
			array( $this, 'can_manage' ),
			true
		);
	}

	/* ------------------------------------------------------------------ */
	/* Registrazione di una ability                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Registra una ability con i valori comuni.
	 *
	 * @param string   $slug        Nome breve.
	 * @param string   $label       Etichetta.
	 * @param string   $description Descrizione.
	 * @param array    $input       Schema dell'input.
	 * @param callable $execute     Funzione che esegue.
	 * @param callable $permission  Funzione che controlla i permessi.
	 * @param bool     $readonly    Solo lettura.
	 * @param bool     $destructive Cancella qualcosa (anche solo nel cestino).
	 */
	private function add( string $slug, string $label, string $description, array $input, callable $execute, callable $permission, bool $readonly, bool $destructive = false ): void {
		wp_register_ability(
			'academy-elearning/' . $slug,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => self::CATEGORY,
				'input_schema'        => $input,
				'execute_callback'    => $execute,
				'permission_callback' => $permission,
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => true,
					'mcp'          => array( 'public' => true ),
					'annotations'  => array(
						'readonly'    => $readonly,
						'destructive' => $destructive,
						'idempotent'  => $readonly,
					),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Permessi                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Chi può leggere i contenuti (chi può modificare i corsi).
	 */
	public function can_read_content(): bool {
		return $this->can_post_type( AEL_Content::COURSE, 'edit_posts' );
	}

	/**
	 * Chi gestisce l'academy (titolare o agenzia).
	 */
	public function can_manage(): bool {
		return current_user_can( AEL_Content::CAP );
	}

	/**
	 * Controlla una capacità generica di un tipo di contenuto.
	 *
	 * @param string $type Tipo di contenuto.
	 * @param string $cap  Nome della capacità nel tipo ("edit_posts", "create_posts", ...).
	 */
	private function can_post_type( string $type, string $cap ): bool {
		$object = get_post_type_object( $type );
		return $object && isset( $object->cap->$cap ) && current_user_can( $object->cap->$cap );
	}

	/**
	 * Permesso per salvare un corso (nuovo o esistente).
	 *
	 * @param mixed $input Input.
	 */
	public function can_save_course( $input = null ): bool {
		$id = $this->int_of( $input, 'id' );
		return $id ? current_user_can( 'edit_post', $id ) : $this->can_post_type( AEL_Content::COURSE, 'create_posts' );
	}

	/**
	 * Permesso per salvare un modulo o una lezione.
	 *
	 * @param mixed $input Input.
	 */
	public function can_save_program_item( $input = null ): bool {
		$id = $this->int_of( $input, 'id' );
		if ( $id ) {
			return current_user_can( 'edit_post', $id );
		}
		// Nuovo: serve poter modificare il corso o il modulo in cui va.
		$course = $this->int_of( $input, 'course_id' );
		$module = $this->int_of( $input, 'module_id' );
		$parent = $module ? $module : $course;
		return $parent ? current_user_can( 'edit_post', $parent ) : false;
	}

	/**
	 * Permesso per mandare nel cestino.
	 *
	 * @param mixed $input Input.
	 */
	public function can_trash_item( $input = null ): bool {
		$id = $this->int_of( $input, 'id' );
		return $id ? current_user_can( 'delete_post', $id ) : false;
	}

	/**
	 * Permesso per salvare una scheda docente.
	 *
	 * @param mixed $input Input.
	 */
	public function can_save_teacher( $input = null ): bool {
		$id = $this->int_of( $input, 'id' );
		return $id ? current_user_can( 'edit_post', $id ) : $this->can_post_type( AEL_Content::TEACHER, 'create_posts' );
	}

	/* ------------------------------------------------------------------ */
	/* Lettura                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Elenco dei corsi.
	 *
	 * @param mixed $input Input.
	 * @return array|WP_Error
	 */
	public function list_courses( $input = null ) {
		$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'any';
		$posts  = get_posts(
			array(
				'post_type'   => AEL_Content::COURSE,
				'post_status' => ( 'any' === $status ) ? array( 'publish', 'draft', 'pending', 'private' ) : $status,
				'numberposts' => 200,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$out    = array();
		foreach ( AEL_Content::editable( $posts ) as $post ) {
			$out[] = $this->course_summary( $post );
		}
		return array( 'courses' => $out );
	}

	/**
	 * Un corso con il suo programma.
	 *
	 * @param mixed $input Input.
	 * @return array|WP_Error
	 */
	public function get_course( $input = null ) {
		$course = $this->get_typed( $this->int_of( $input, 'id' ), AEL_Content::COURSE );
		if ( is_wp_error( $course ) ) {
			return $course;
		}
		$data              = $this->course_summary( $course );
		$data['description']   = (string) $course->post_content;
		$data['excerpt']       = (string) $course->post_excerpt;
		$data['subtitle']      = (string) get_post_meta( $course->ID, '_ael_subtitle', true );
		$data['learn']         = (string) get_post_meta( $course->ID, '_ael_learn', true );
		$data['audience']      = (string) get_post_meta( $course->ID, '_ael_audience', true );
		$data['preview_video'] = (string) get_post_meta( $course->ID, '_ael_preview_video', true );
		$data['excluded_from_subscription'] = (bool) get_post_meta( $course->ID, '_ael_excluded_from_subscription', true );
		$data['modules']       = array();

		$modules = get_posts(
			array(
				'post_type'   => AEL_Content::MODULE,
				'post_parent' => $course->ID,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => -1,
				'orderby'     => 'menu_order',
				'order'       => 'ASC',
			)
		);
		foreach ( $modules as $module ) {
			$lessons = array();
			$posts   = get_posts(
				array(
					'post_type'   => AEL_Content::LESSON,
					'post_parent' => $module->ID,
					'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
					'numberposts' => -1,
					'orderby'     => 'menu_order',
					'order'       => 'ASC',
				)
			);
			foreach ( $posts as $lesson ) {
				$lessons[] = array(
					'id'           => $lesson->ID,
					'title'        => $lesson->post_title,
					'status'       => $lesson->post_status,
					'position'     => (int) $lesson->menu_order,
					'video_source' => (string) get_post_meta( $lesson->ID, '_ael_video_source', true ),
					'video_ref'    => (string) get_post_meta( $lesson->ID, '_ael_video_ref', true ),
					'duration_min' => (int) get_post_meta( $lesson->ID, '_ael_duration_min', true ),
					'is_preview'   => (bool) get_post_meta( $lesson->ID, '_ael_is_preview', true ),
				);
			}
			$data['modules'][] = array(
				'id'       => $module->ID,
				'title'    => $module->post_title,
				'status'   => $module->post_status,
				'position' => (int) $module->menu_order,
				'lessons'  => $lessons,
			);
		}
		return $data;
	}

	/**
	 * Elenco dei docenti.
	 *
	 * @return array
	 */
	public function list_teachers(): array {
		$posts = get_posts(
			array(
				'post_type'   => AEL_Content::TEACHER,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => 200,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$out   = array();
		foreach ( AEL_Content::editable( $posts ) as $post ) {
			$out[] = $this->teacher_data( $post );
		}
		return array( 'teachers' => $out );
	}

	/**
	 * Numeri dell'academy, in sola lettura.
	 *
	 * @return array
	 */
	public function summary(): array {
		return array(
			'active_students'      => AEL_Stats::active_students_count(),
			'active_grants_by_source' => AEL_Stats::active_grants_by_source(),
			'completed_lessons_30d' => AEL_Stats::completed_since( 30 ),
			'watched_hours_30d'    => round( AEL_Stats::watch_seconds_since( 30 ) / HOUR_IN_SECONDS, 1 ),
			'top_courses_30d'      => AEL_Stats::top_courses( 30, 5 ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Scrittura                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Crea o modifica un corso.
	 *
	 * @param mixed $input Input.
	 * @return array|WP_Error
	 */
	public function save_course( $input = null ) {
		$input   = is_array( $input ) ? $input : array();
		$id      = $this->int_of( $input, 'id' );
		$manager = current_user_can( AEL_Content::CAP );
		$notes   = array();

		$args = array( 'post_type' => AEL_Content::COURSE );
		if ( isset( $input['title'] ) ) {
			$args['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( isset( $input['description'] ) ) {
			$args['post_content'] = wp_kses_post( (string) $input['description'] );
		}
		if ( isset( $input['excerpt'] ) ) {
			$args['post_excerpt'] = sanitize_textarea_field( (string) $input['excerpt'] );
		}
		$status = $this->status_for( AEL_Content::COURSE, $input, $id, $notes );
		if ( null !== $status ) {
			$args['post_status'] = $status;
		}

		if ( $id ) {
			$existing = $this->get_typed( $id, AEL_Content::COURSE );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}
			$args['ID'] = $id;
			$result     = wp_update_post( wp_slash( $args ), true );
		} else {
			if ( empty( $args['post_title'] ) ) {
				return new WP_Error( 'ael_missing_title', __( 'Per creare un corso serve il titolo.', 'academy-elearning' ), array( 'status' => 400 ) );
			}
			// Un corso nuovo nasce in bozza, salvo che si chieda (e si possa) pubblicarlo.
			$args += array( 'post_status' => 'draft' );
			$result = wp_insert_post( wp_slash( $args ), true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$id = (int) $result;

		$texts = array(
			'subtitle'      => 'sanitize_text_field',
			'learn'         => 'sanitize_textarea_field',
			'audience'      => 'sanitize_textarea_field',
			'preview_video' => 'esc_url_raw',
		);
		foreach ( $texts as $key => $sanitize ) {
			if ( isset( $input[ $key ] ) ) {
				update_post_meta( $id, '_ael_' . $key, $sanitize( (string) $input[ $key ] ) );
			}
		}
		if ( isset( $input['level'] ) ) {
			$level = sanitize_key( (string) $input['level'] );
			update_post_meta( $id, '_ael_level', in_array( $level, array( 'principiante', 'intermedio', 'avanzato' ), true ) ? $level : '' );
		}

		// Docente, abbonamento e prezzo: solo agenzia o titolare, come nell'amministrazione.
		$restricted = array_intersect_key( $input, array_flip( array( 'teacher_id', 'excluded_from_subscription', 'price' ) ) );
		if ( $restricted && ! $manager ) {
			$notes[] = __( 'Docente, abbonamento e prezzo non sono stati cambiati: possono farlo solo l\'agenzia o il titolare.', 'academy-elearning' );
		} elseif ( $manager ) {
			if ( isset( $input['teacher_id'] ) ) {
				$teacher = absint( $input['teacher_id'] );
				update_post_meta( $id, '_ael_teacher_id', ( $teacher && AEL_Content::TEACHER === get_post_type( $teacher ) ) ? $teacher : 0 );
			}
			if ( isset( $input['excluded_from_subscription'] ) ) {
				update_post_meta( $id, '_ael_excluded_from_subscription', empty( $input['excluded_from_subscription'] ) ? 0 : 1 );
			}
			if ( isset( $input['price'] ) ) {
				if ( class_exists( 'AEL_Single_Purchase' ) && function_exists( 'wc_format_decimal' ) ) {
					$price = max( 0.0, (float) $input['price'] );
					if ( $price > 0 ) {
						update_post_meta( $id, AEL_Single_Purchase::META_PRICE, wc_format_decimal( $price ) );
					} else {
						delete_post_meta( $id, AEL_Single_Purchase::META_PRICE );
					}
					AEL_Single_Purchase::instance()->sync_product( $id );
				} else {
					$notes[] = __( 'Il prezzo non è stato cambiato: serve il modulo Acquisto singolo con WooCommerce.', 'academy-elearning' );
				}
			}
		}

		$data = $this->course_summary( get_post( $id ) );
		if ( $notes ) {
			$data['notes'] = $notes;
		}
		return $data;
	}

	/**
	 * Crea o modifica un modulo.
	 *
	 * @param mixed $input Input.
	 * @return array|WP_Error
	 */
	public function save_module( $input = null ) {
		$input = is_array( $input ) ? $input : array();
		$id    = $this->int_of( $input, 'id' );
		$args  = array( 'post_type' => AEL_Content::MODULE );
		if ( isset( $input['title'] ) ) {
			$args['post_title'] = sanitize_text_field( (string) $input['title'] );
		}

		if ( $id ) {
			$module = $this->get_typed( $id, AEL_Content::MODULE );
			if ( is_wp_error( $module ) ) {
				return $module;
			}
			$course_id = (int) $module->post_parent;
			if ( isset( $input['course_id'] ) && $this->int_of( $input, 'course_id' ) !== $course_id ) {
				$new = $this->get_typed( $this->int_of( $input, 'course_id' ), AEL_Content::COURSE );
				if ( is_wp_error( $new ) ) {
					return $new;
				}
				$course_id          = $new->ID;
				$args['post_parent'] = $course_id;
			}
			if ( isset( $input['position'] ) ) {
				$args['menu_order'] = max( 0, (int) $input['position'] );
			}
			$args['ID'] = $id;
			$result     = wp_update_post( wp_slash( $args ), true );
		} else {
			$course = $this->get_typed( $this->int_of( $input, 'course_id' ), AEL_Content::COURSE );
			if ( is_wp_error( $course ) ) {
				return $course;
			}
			if ( empty( $args['post_title'] ) ) {
				return new WP_Error( 'ael_missing_title', __( 'Per creare un modulo serve il titolo.', 'academy-elearning' ), array( 'status' => 400 ) );
			}
			$course_id           = $course->ID;
			$args['post_parent'] = $course_id;
			$args['menu_order']  = isset( $input['position'] ) ? max( 0, (int) $input['position'] ) : $this->next_position( AEL_Content::MODULE, $course_id );
			$args['post_status'] = $this->can_post_type( AEL_Content::MODULE, 'publish_posts' ) ? 'publish' : 'draft';
			$result              = wp_insert_post( wp_slash( $args ), true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$id = (int) $result;

		// Le lezioni del modulo portano con sé l'ID del corso (serve per le ricerche).
		foreach ( get_posts(
			array(
				'post_type'   => AEL_Content::LESSON,
				'post_parent' => $id,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		) as $lesson_id ) {
			update_post_meta( (int) $lesson_id, '_ael_course_id', $course_id );
		}

		/** Questa azione è documentata nell'amministrazione del programma. */
		do_action( 'ael_program_changed', $course_id );

		$post = get_post( $id );
		return array(
			'id'        => $id,
			'title'     => $post->post_title,
			'course_id' => $course_id,
			'position'  => (int) $post->menu_order,
			'status'    => $post->post_status,
		);
	}

	/**
	 * Crea o modifica una lezione.
	 *
	 * @param mixed $input Input.
	 * @return array|WP_Error
	 */
	public function save_lesson( $input = null ) {
		$input = is_array( $input ) ? $input : array();
		$id    = $this->int_of( $input, 'id' );
		$args  = array( 'post_type' => AEL_Content::LESSON );
		if ( isset( $input['title'] ) ) {
			$args['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$args['post_content'] = wp_kses_post( (string) $input['content'] );
		}

		$module_id = 0;
		if ( $id ) {
			$lesson = $this->get_typed( $id, AEL_Content::LESSON );
			if ( is_wp_error( $lesson ) ) {
				return $lesson;
			}
			$module_id = (int) $lesson->post_parent;
		}
		if ( isset( $input['module_id'] ) ) {
			$module = $this->get_typed( $this->int_of( $input, 'module_id' ), AEL_Content::MODULE );
			if ( is_wp_error( $module ) ) {
				return $module;
			}
			$module_id           = $module->ID;
			$args['post_parent'] = $module_id;
		}
		if ( ! $module_id ) {
			return new WP_Error( 'ael_missing_module', __( 'Serve il modulo in cui mettere la lezione.', 'academy-elearning' ), array( 'status' => 400 ) );
		}

		// Il video si controlla prima di scrivere qualsiasi cosa.
		$video = null;
		if ( isset( $input['video_source'] ) || isset( $input['video_ref'] ) ) {
			$source = isset( $input['video_source'] ) ? sanitize_key( (string) $input['video_source'] ) : (string) ( $id ? get_post_meta( $id, '_ael_video_source', true ) : '' );
			$ref_in = isset( $input['video_ref'] ) ? sanitize_text_field( (string) $input['video_ref'] ) : '';
			if ( '' === $ref_in ) {
				$video = array( '', '' );
			} elseif ( ! in_array( $source, (array) AEL_Settings::get( 'video_sources', array() ), true ) ) {
				return new WP_Error( 'ael_bad_video_source', __( 'La sorgente del video non è tra quelle attive in Academy → Configurazione.', 'academy-elearning' ), array( 'status' => 400 ) );
			} else {
				$ref = AEL_Content::normalize_video_ref( $source, $ref_in );
				if ( '' === $ref ) {
					return new WP_Error( 'ael_bad_video', __( 'Il video non è stato riconosciuto: controlla l\'indirizzo e la sorgente scelta.', 'academy-elearning' ), array( 'status' => 400 ) );
				}
				$video = array( $source, $ref );
			}
		}

		$module_post = get_post( $module_id );
		$course_id   = $module_post ? (int) $module_post->post_parent : 0;

		if ( $id ) {
			if ( isset( $input['position'] ) ) {
				$args['menu_order'] = max( 0, (int) $input['position'] );
			}
			$args['ID'] = $id;
			$result     = wp_update_post( wp_slash( $args ), true );
		} else {
			if ( empty( $args['post_title'] ) ) {
				return new WP_Error( 'ael_missing_title', __( 'Per creare una lezione serve il titolo.', 'academy-elearning' ), array( 'status' => 400 ) );
			}
			$args['menu_order']  = isset( $input['position'] ) ? max( 0, (int) $input['position'] ) : $this->next_position( AEL_Content::LESSON, $module_id );
			$args['post_status'] = $this->can_post_type( AEL_Content::LESSON, 'publish_posts' ) ? 'publish' : 'draft';
			$result              = wp_insert_post( wp_slash( $args ), true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$id = (int) $result;

		if ( null !== $video ) {
			update_post_meta( $id, '_ael_video_source', $video[0] );
			update_post_meta( $id, '_ael_video_ref', $video[1] );
		}
		if ( isset( $input['duration_min'] ) ) {
			update_post_meta( $id, '_ael_duration_min', max( 0, (int) $input['duration_min'] ) );
		}
		if ( isset( $input['is_preview'] ) ) {
			update_post_meta( $id, '_ael_is_preview', empty( $input['is_preview'] ) ? 0 : 1 );
		}
		update_post_meta( $id, '_ael_course_id', $course_id );

		/** Questa azione è documentata nell'amministrazione del programma. */
		do_action( 'ael_program_changed', $course_id );

		$post = get_post( $id );
		return array(
			'id'           => $id,
			'title'        => $post->post_title,
			'module_id'    => $module_id,
			'course_id'    => $course_id,
			'position'     => (int) $post->menu_order,
			'status'       => $post->post_status,
			'video_source' => (string) get_post_meta( $id, '_ael_video_source', true ),
			'video_ref'    => (string) get_post_meta( $id, '_ael_video_ref', true ),
		);
	}

	/**
	 * Manda nel cestino un modulo (con le sue lezioni) o una lezione.
	 *
	 * @param mixed $input Input.
	 * @return array|WP_Error
	 */
	public function trash_item( $input = null ) {
		$id   = $this->int_of( $input, 'id' );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || ! in_array( $post->post_type, array( AEL_Content::MODULE, AEL_Content::LESSON ), true ) ) {
			return new WP_Error( 'ael_not_found', __( 'Qui si possono eliminare solo moduli e lezioni.', 'academy-elearning' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'delete_post', $id ) ) {
			return new WP_Error( 'ael_forbidden', __( 'Non hai il permesso di eliminare questo contenuto.', 'academy-elearning' ), array( 'status' => 403 ) );
		}

		$course_id = AEL_Content::MODULE === $post->post_type ? (int) $post->post_parent : (int) get_post_meta( $id, '_ael_course_id', true );
		$trashed   = 0;
		if ( AEL_Content::MODULE === $post->post_type ) {
			foreach ( get_posts(
				array(
					'post_type'   => AEL_Content::LESSON,
					'post_parent' => $id,
					'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			) as $lesson_id ) {
				if ( current_user_can( 'delete_post', (int) $lesson_id ) && wp_trash_post( (int) $lesson_id ) ) {
					++$trashed;
				}
			}
		}
		if ( ! wp_trash_post( $id ) ) {
			return new WP_Error( 'ael_trash_failed', __( 'Non è stato possibile spostare nel cestino.', 'academy-elearning' ), array( 'status' => 500 ) );
		}

		/** Questa azione è documentata nell'amministrazione del programma. */
		do_action( 'ael_program_changed', $course_id );

		return array(
			'trashed'         => $id,
			'lessons_trashed' => $trashed,
		);
	}

	/**
	 * Crea o modifica la scheda di un docente.
	 *
	 * @param mixed $input Input.
	 * @return array|WP_Error
	 */
	public function save_teacher( $input = null ) {
		$input   = is_array( $input ) ? $input : array();
		$id      = $this->int_of( $input, 'id' );
		$manager = current_user_can( AEL_Content::CAP );
		$notes   = array();

		$args = array( 'post_type' => AEL_Content::TEACHER );
		if ( isset( $input['title'] ) ) {
			$args['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( isset( $input['bio'] ) ) {
			$args['post_content'] = wp_kses_post( (string) $input['bio'] );
		}
		$status = $this->status_for( AEL_Content::TEACHER, $input, $id, $notes );
		if ( null !== $status ) {
			$args['post_status'] = $status;
		}

		if ( $id ) {
			$existing = $this->get_typed( $id, AEL_Content::TEACHER );
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}
			$args['ID'] = $id;
			$result     = wp_update_post( wp_slash( $args ), true );
		} else {
			if ( empty( $args['post_title'] ) ) {
				return new WP_Error( 'ael_missing_title', __( 'Per creare un docente serve il nome.', 'academy-elearning' ), array( 'status' => 400 ) );
			}
			$args += array( 'post_status' => 'draft' );
			$result = wp_insert_post( wp_slash( $args ), true );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$id = (int) $result;

		if ( isset( $input['tagline'] ) ) {
			update_post_meta( $id, '_ael_tagline', sanitize_text_field( (string) $input['tagline'] ) );
		}
		if ( isset( $input['accent'] ) ) {
			update_post_meta( $id, '_ael_accent', AEL_Teacher_Profile::clean_color( (string) $input['accent'] ) );
		}
		if ( isset( $input['stats_date'] ) ) {
			update_post_meta( $id, '_ael_stats_date', mb_substr( sanitize_text_field( (string) $input['stats_date'] ), 0, 40 ) );
		}
		if ( isset( $input['channels'] ) ) {
			update_post_meta( $id, '_ael_channels', AEL_Teacher_Profile::clean_channels( $input['channels'] ) );
		}
		if ( isset( $input['user_id'] ) ) {
			if ( $manager ) {
				$user_id = absint( $input['user_id'] );
				update_post_meta( $id, '_ael_user_id', ( $user_id && get_userdata( $user_id ) ) ? $user_id : 0 );
			} else {
				$notes[] = __( 'Il collegamento all\'utente non è stato cambiato: può farlo solo l\'agenzia o il titolare.', 'academy-elearning' );
			}
		}

		$data = $this->teacher_data( get_post( $id ) );
		if ( $notes ) {
			$data['notes'] = $notes;
		}
		return $data;
	}

	/* ------------------------------------------------------------------ */
	/* Utilità                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Numero intero da un campo dell'input.
	 *
	 * @param mixed  $input Input.
	 * @param string $key   Campo.
	 */
	private function int_of( $input, string $key ): int {
		return ( is_array( $input ) && isset( $input[ $key ] ) ) ? absint( $input[ $key ] ) : 0;
	}

	/**
	 * Contenuto del tipo giusto che l'utente può modificare, oppure un errore.
	 *
	 * @param int    $id   ID.
	 * @param string $type Tipo atteso.
	 * @return WP_Post|WP_Error
	 */
	private function get_typed( int $id, string $type ) {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || $type !== $post->post_type ) {
			return new WP_Error( 'ael_not_found', __( 'Contenuto non trovato.', 'academy-elearning' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'ael_forbidden', __( 'Non hai il permesso di modificare questo contenuto.', 'academy-elearning' ), array( 'status' => 403 ) );
		}
		return $post;
	}

	/**
	 * Stato richiesto: chi non può pubblicare resta in bozza.
	 *
	 * @param string $type  Tipo di contenuto.
	 * @param array  $input Input.
	 * @param int    $id    ID (0 se nuovo).
	 * @param array  $notes Avvisi (per riferimento).
	 * @return string|null Stato da scrivere, null se non cambia.
	 */
	private function status_for( string $type, array $input, int $id, array &$notes ): ?string {
		if ( ! isset( $input['status'] ) ) {
			return null;
		}
		$wanted = 'publish' === $input['status'] ? 'publish' : 'draft';
		if ( 'publish' === $wanted && ! $this->can_post_type( $type, 'publish_posts' ) ) {
			$notes[] = __( 'Non puoi pubblicare: il contenuto resta in bozza e lo pubblica il titolare.', 'academy-elearning' );
			return 'draft';
		}
		return $wanted;
	}

	/**
	 * Prossima posizione libera tra i figli di un contenuto.
	 *
	 * @param string $type   Tipo dei figli.
	 * @param int    $parent Genitore.
	 */
	private function next_position( string $type, int $parent ): int {
		$next = 1;
		foreach ( get_posts(
			array(
				'post_type'   => $type,
				'post_parent' => $parent,
				'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		) as $sibling_id ) {
			$next = max( $next, (int) get_post_field( 'menu_order', (int) $sibling_id ) + 1 );
		}
		return $next;
	}

	/**
	 * Scheda breve di un corso.
	 *
	 * @param WP_Post $post Corso.
	 * @return array
	 */
	private function course_summary( WP_Post $post ): array {
		$lessons = (int) ( new WP_Query(
			array(
				'post_type'      => AEL_Content::LESSON,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'meta_key'       => '_ael_course_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $post->ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
			)
		) )->found_posts;

		$price = null;
		if ( class_exists( 'AEL_Single_Purchase' ) && function_exists( 'wc_format_decimal' ) ) {
			$price = AEL_Single_Purchase::price_of( $post->ID );
		}
		return array(
			'id'           => $post->ID,
			'title'        => $post->post_title,
			'status'       => $post->post_status,
			'teacher_id'   => (int) get_post_meta( $post->ID, '_ael_teacher_id', true ),
			'level'        => (string) get_post_meta( $post->ID, '_ael_level', true ),
			'price'        => $price,
			'lessons'      => $lessons,
			'link'         => (string) get_permalink( $post ),
		);
	}

	/**
	 * Scheda di un docente.
	 *
	 * @param WP_Post $post Docente.
	 * @return array
	 */
	private function teacher_data( WP_Post $post ): array {
		return array(
			'id'         => $post->ID,
			'title'      => $post->post_title,
			'status'     => $post->post_status,
			'bio'        => (string) $post->post_content,
			'tagline'    => (string) get_post_meta( $post->ID, '_ael_tagline', true ),
			'accent'     => (string) get_post_meta( $post->ID, '_ael_accent', true ),
			'stats_date' => (string) get_post_meta( $post->ID, '_ael_stats_date', true ),
			'channels'   => AEL_Teacher_Profile::clean_channels( get_post_meta( $post->ID, '_ael_channels', true ) ),
			'user_id'    => (int) get_post_meta( $post->ID, '_ael_user_id', true ),
		);
	}
}
