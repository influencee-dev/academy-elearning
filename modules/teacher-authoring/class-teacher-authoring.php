<?php
/**
 * Docenti autori: permessi, visibilità e proprietà dei contenuti.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Chi è un "docente": un utente con il ruolo Docente che NON ha la capacità del
 * titolare. Il modulo non salva permessi nei ruoli: li concede al volo finché è
 * acceso, quindi spegnerlo li toglie tutti e non lascia nulla da pulire.
 *
 * Un docente può:
 * - creare e modificare corsi, moduli e lezioni suoi (e quelli che il titolare
 *   ha creato a suo nome, cioè collegati alla sua scheda docente);
 * - modificare la propria scheda docente;
 * - caricare file, che vede solo lui.
 *
 * Non può: pubblicare, vedere o toccare i contenuti degli altri, vedere studenti,
 * vendite o configurazione, cambiare docente o regole di abbonamento di un corso.
 */
final class AEL_Teacher_Authoring {

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Teacher_Authoring|null
	 */
	private static ?AEL_Teacher_Authoring $instance = null;

	/**
	 * Cache della richiesta: utente => ID della scheda docente (0 = nessuna).
	 *
	 * @var array<int, int>
	 */
	private array $profile_cache = array();

	/**
	 * Cache della richiesta: "utente:tipo" => ID dei contenuti propri.
	 *
	 * @var array<string, int[]>
	 */
	private array $owned_cache = array();

	/**
	 * Corsi appena pubblicati in questa richiesta.
	 *
	 * @var array<int, bool>
	 */
	private array $just_published = array();

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Teacher_Authoring {
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
	 * Aggancia permessi, elenchi, accessi e pubblicazione.
	 */
	public function boot(): void {
		add_filter( 'user_has_cap', array( $this, 'grant_caps' ), 15, 4 );
		add_filter( 'map_meta_cap', array( $this, 'map_meta' ), 10, 4 );
		add_filter( 'ael_admin_menu_cap', array( $this, 'menu_cap' ) );

		// Ognuno vede solo i propri contenuti, anche negli elenchi e nella libreria media.
		add_action( 'pre_get_posts', array( $this, 'limit_lists' ) );
		add_filter( 'ajax_query_attachments_args', array( $this, 'limit_media_modal' ) );
		foreach ( $this->own_types() as $type ) {
			add_filter( "views_edit-{$type}", array( $this, 'hide_views' ) );
		}

		// Anteprima sul sito dei propri contenuti, anche se non pubblicati.
		add_filter( 'ael_user_can_access_course', array( $this, 'filter_course_access' ), 10, 3 );
		add_filter( 'ael_user_can_access_lesson', array( $this, 'filter_lesson_access' ), 10, 4 );

		// Proprietà e pubblicazione.
		add_action( 'save_post_' . AEL_Content::COURSE, array( $this, 'assign_teacher' ), 99, 2 );
		add_action( 'transition_post_status', array( $this, 'remember_publication' ), 10, 3 );
		add_action( 'save_post_' . AEL_Content::COURSE, array( $this, 'publish_children' ), 100, 2 );

		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 20, 3 );
		add_action( 'admin_notices', array( $this, 'profile_notice' ) );

		// Chi entra dal sito (login di WooCommerce, pagina Accedi, I miei corsi) trova la strada per i suoi corsi.
		add_action( 'ael_my_courses_before', array( $this, 'front_link' ), 4 );
		add_action( 'ael_login_logged_in', array( $this, 'front_link' ) );
		if ( function_exists( 'WC' ) ) {
			add_filter( 'woocommerce_login_redirect', array( $this, 'woo_login_redirect' ), 20, 2 );
			add_filter( 'woocommerce_account_menu_items', array( $this, 'woo_menu_item' ), 15 );
			add_filter( 'woocommerce_get_endpoint_url', array( $this, 'woo_menu_url' ), 10, 2 );
		}
	}

	/**
	 * Indirizzo dell'elenco dei corsi del docente nell'amministrazione.
	 */
	private function courses_url(): string {
		return admin_url( 'edit.php?post_type=' . AEL_Content::COURSE );
	}

	/**
	 * Pulsante "I miei corsi (area docente)" nelle pagine del sito.
	 */
	public function front_link(): void {
		if ( ! is_user_logged_in() || ! $this->is_authoring_teacher( get_current_user_id() ) ) {
			return;
		}
		echo '<p><a class="ael-button ael-button--secondary" href="' . esc_url( $this->courses_url() ) . '">' . esc_html__( 'I corsi che creo (area docente)', 'academy-elearning' ) . '</a></p>';
	}

	/**
	 * Dopo l'accesso dal login di WooCommerce, un docente autore va ai suoi corsi (se non stava andando altrove).
	 *
	 * @param string  $redirect Destinazione di WooCommerce.
	 * @param WP_User $user     Utente.
	 * @return string
	 */
	public function woo_login_redirect( $redirect, $user ) {
		if ( ! $user instanceof WP_User || ! $this->is_authoring_teacher( (int) $user->ID ) ) {
			return $redirect;
		}
		return ( function_exists( 'wc_get_page_permalink' ) && $redirect === wc_get_page_permalink( 'myaccount' ) ) ? $this->courses_url() : $redirect;
	}

	/**
	 * Voce "Area docente" nel menu di "Il mio account" di WooCommerce.
	 *
	 * @param array $items Voci del menu.
	 * @return array
	 */
	public function woo_menu_item( $items ) {
		if ( ! is_array( $items ) || ! is_user_logged_in() || ! $this->is_authoring_teacher( get_current_user_id() ) ) {
			return $items;
		}
		$new = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$new['ael-courses'] = __( 'I corsi che creo', 'academy-elearning' );
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new['ael-courses'] ) ) {
			$new['ael-courses'] = __( 'I corsi che creo', 'academy-elearning' );
		}
		return $new;
	}

	/**
	 * La voce del menu porta all'amministrazione.
	 *
	 * @param string $url      Indirizzo.
	 * @param string $endpoint Voce.
	 * @return string
	 */
	public function woo_menu_url( $url, $endpoint ) {
		return 'ael-courses' === $endpoint ? $this->courses_url() : $url;
	}

	/* ------------------------------------------------------------------ */
	/* Chi è un docente                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Tipi di contenuto che un docente può avere "propri".
	 *
	 * @return string[]
	 */
	private function own_types(): array {
		return array( AEL_Content::COURSE, AEL_Content::MODULE, AEL_Content::LESSON, AEL_Content::TEACHER );
	}

	/**
	 * L'utente è un docente? (ruolo Docente, senza la capacità del titolare).
	 *
	 * @param int $user_id Utente.
	 */
	public function is_authoring_teacher( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		$user = get_userdata( $user_id );
		return $user && in_array( AEL_Content::TEACHER, (array) $user->roles, true ) && empty( $user->allcaps[ AEL_Content::CAP ] );
	}

	/**
	 * ID della scheda docente collegata a un utente (0 se non c'è).
	 *
	 * @param int $user_id Utente.
	 */
	public function profile_id( int $user_id ): int {
		if ( ! isset( $this->profile_cache[ $user_id ] ) ) {
			$ids = get_posts(
				array(
					'post_type'        => AEL_Content::TEACHER,
					'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
					'numberposts'      => 1,
					'fields'           => 'ids',
					'suppress_filters' => true,
					'meta_key'         => '_ael_user_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'       => $user_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			$this->profile_cache[ $user_id ] = $ids ? (int) $ids[0] : 0;
		}
		return $this->profile_cache[ $user_id ];
	}

	/**
	 * Il contenuto è di questo docente?
	 *
	 * - Scheda docente: è quella collegata al suo utente.
	 * - Corso: l'ha creato lui, oppure è collegato alla sua scheda docente.
	 * - Modulo o lezione: l'ha creato lui, oppure sta in un corso suo.
	 *
	 * @param int          $user_id Utente.
	 * @param WP_Post|null $post    Contenuto.
	 */
	public function owns( int $user_id, ?WP_Post $post ): bool {
		if ( ! $post || $user_id <= 0 ) {
			return false;
		}
		if ( AEL_Content::TEACHER === $post->post_type ) {
			return (int) get_post_meta( $post->ID, '_ael_user_id', true ) === $user_id;
		}
		if ( (int) $post->post_author === $user_id ) {
			return true;
		}

		$course_id = 0;
		if ( AEL_Content::COURSE === $post->post_type ) {
			$profile = $this->profile_id( $user_id );
			return $profile && (int) get_post_meta( $post->ID, '_ael_teacher_id', true ) === $profile;
		}
		if ( AEL_Content::MODULE === $post->post_type ) {
			$course_id = (int) $post->post_parent;
		} elseif ( AEL_Content::LESSON === $post->post_type ) {
			$course_id = (int) get_post_meta( $post->ID, '_ael_course_id', true );
			if ( ! $course_id && $post->post_parent ) {
				$course_id = (int) get_post_field( 'post_parent', (int) $post->post_parent );
			}
		}
		return $course_id > 0 && $this->owns( $user_id, get_post( $course_id ) );
	}

	/* ------------------------------------------------------------------ */
	/* Permessi                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Concede ai docenti i permessi di base per scrivere i propri contenuti.
	 * Mancano di proposito: publish_*, edit_others_*, delete_others_*, read_private_*.
	 *
	 * @param array   $allcaps Permessi dell'utente.
	 * @param array   $caps    Permessi richiesti.
	 * @param array   $args    Argomenti.
	 * @param WP_User $user    Utente.
	 * @return array
	 */
	public function grant_caps( $allcaps, $caps, $args, $user ) {
		if ( ! $user instanceof WP_User || ! in_array( AEL_Content::TEACHER, (array) $user->roles, true ) || ! empty( $allcaps[ AEL_Content::CAP ] ) ) {
			return $allcaps;
		}

		foreach ( array( AEL_Content::COURSE, AEL_Content::MODULE, AEL_Content::LESSON ) as $type ) {
			$object = get_post_type_object( $type );
			if ( $object ) {
				$allcaps[ $object->cap->edit_posts ]           = true;
				$allcaps[ $object->cap->edit_published_posts ] = true;
				$allcaps[ $object->cap->delete_posts ]         = true;
			}
		}
		// La propria scheda docente: si modifica, non si crea né si elimina.
		$teacher = get_post_type_object( AEL_Content::TEACHER );
		if ( $teacher ) {
			$allcaps[ $teacher->cap->edit_posts ]           = true;
			$allcaps[ $teacher->cap->edit_published_posts ] = true;
		}
		$allcaps['upload_files'] = true;
		return $allcaps;
	}

	/**
	 * Decide, per un docente, su quali contenuti può agire: solo i propri.
	 *
	 * @param string[] $caps    Permessi richiesti.
	 * @param string   $cap     Permesso richiesto.
	 * @param int      $user_id Utente.
	 * @param array    $args    Argomenti (il primo è l'ID del contenuto).
	 * @return string[]
	 */
	public function map_meta( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( (int) $args[0] );
		if ( ! $post || ! in_array( $post->post_type, $this->own_types(), true ) || ! $this->is_authoring_teacher( (int) $user_id ) ) {
			return $caps;
		}
		$object = get_post_type_object( $post->post_type );
		if ( ! $object ) {
			return $caps;
		}

		$owned = $this->owns( (int) $user_id, $post );
		if ( 'read_post' === $cap ) {
			// Le bozze degli altri non si leggono; il pubblicato segue la regola di WordPress.
			return ( $owned || 'publish' === $post->post_status ) ? array( 'read' ) : array( 'do_not_allow' );
		}
		if ( ! $owned ) {
			return array( 'do_not_allow' );
		}

		$published = 'publish' === $post->post_status;
		if ( 'delete_post' === $cap ) {
			// Il già pubblicato lo toglie solo il titolare. La scheda docente non si elimina.
			return ( $published || AEL_Content::TEACHER === $post->post_type ) ? array( 'do_not_allow' ) : array( $object->cap->delete_posts );
		}

		/**
		 * Permette di impedire ai docenti di modificare i propri contenuti già pubblicati
		 * (di base possono: le correzioni vanno online subito).
		 *
		 * @param bool    $allowed Possono modificarli.
		 * @param WP_Post $post    Contenuto.
		 */
		if ( $published && ! (bool) apply_filters( 'ael_authoring_can_edit_published', true, $post ) ) {
			return array( 'do_not_allow' );
		}
		return array( $published ? $object->cap->edit_published_posts : $object->cap->edit_posts );
	}

	/**
	 * Il menu "Academy" è visibile anche ai docenti.
	 *
	 * @param string $cap Permesso richiesto di base.
	 */
	public function menu_cap( $cap ) {
		$object = get_post_type_object( AEL_Content::COURSE );
		return ( $object && $this->is_authoring_teacher( get_current_user_id() ) ) ? $object->cap->edit_posts : $cap;
	}

	/* ------------------------------------------------------------------ */
	/* Elenchi: ognuno vede solo il suo                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * ID dei contenuti di un tipo che appartengono al docente (in qualsiasi stato).
	 *
	 * @param int    $user_id Utente.
	 * @param string $type    Tipo di contenuto.
	 * @return int[]
	 */
	private function owned_ids( int $user_id, string $type ): array {
		$key = $user_id . ':' . $type;
		if ( isset( $this->owned_cache[ $key ] ) ) {
			return $this->owned_cache[ $key ];
		}

		$statuses = array_keys( get_post_stati() );
		$base     = array(
			'post_type'        => $type,
			'post_status'      => $statuses,
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		);

		if ( AEL_Content::TEACHER === $type ) {
			$profile = $this->profile_id( $user_id );
			$ids     = $profile ? array( $profile ) : array();
		} else {
			$own_courses = array();
			// Corsi: creati da lui o collegati alla sua scheda.
			$own_courses = get_posts( array_merge( $base, array( 'post_type' => AEL_Content::COURSE, 'author' => $user_id ) ) );
			$profile     = $this->profile_id( $user_id );
			if ( $profile ) {
				$own_courses = array_merge(
					$own_courses,
					get_posts(
						array_merge(
							$base,
							array(
								'post_type'  => AEL_Content::COURSE,
								'meta_key'   => '_ael_teacher_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
								'meta_value' => $profile, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
							)
						)
					)
				);
			}
			$own_courses = array_values( array_unique( array_map( 'intval', $own_courses ) ) );

			if ( AEL_Content::COURSE === $type ) {
				$ids = $own_courses;
			} else {
				$ids = get_posts( array_merge( $base, array( 'author' => $user_id ) ) );
				if ( ! empty( $own_courses ) ) {
					if ( AEL_Content::MODULE === $type ) {
						$more = get_posts( array_merge( $base, array( 'post_parent__in' => $own_courses ) ) );
					} else {
						$more = get_posts(
							array_merge(
								$base,
								array(
									'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
										array(
											'key'     => '_ael_course_id',
											'value'   => $own_courses,
											'compare' => 'IN',
										),
									),
								)
							)
						);
					}
					$ids = array_merge( $ids, $more );
				}
			}
		}

		$this->owned_cache[ $key ] = array_values( array_unique( array_map( 'intval', $ids ) ) );
		return $this->owned_cache[ $key ];
	}

	/**
	 * Negli elenchi dell'amministrazione un docente vede solo i propri contenuti
	 * e i propri file.
	 *
	 * @param WP_Query $query Richiesta.
	 */
	public function limit_lists( $query ): void {
		if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( ! $this->is_authoring_teacher( $user_id ) ) {
			return;
		}
		$type = $query->get( 'post_type' );
		if ( is_string( $type ) && in_array( $type, $this->own_types(), true ) ) {
			$ids = $this->owned_ids( $user_id, $type );
			$query->set( 'post__in', empty( $ids ) ? array( 0 ) : $ids );
		} elseif ( 'attachment' === $type ) {
			$query->set( 'author', $user_id );
		}
	}

	/**
	 * Nella finestra di scelta dei file un docente vede solo i propri.
	 *
	 * @param array $args Argomenti della ricerca.
	 * @return array
	 */
	public function limit_media_modal( $args ) {
		$user_id = get_current_user_id();
		if ( is_array( $args ) && $this->is_authoring_teacher( $user_id ) ) {
			$args['author'] = $user_id;
		}
		return $args;
	}

	/**
	 * Toglie i filtri "Tutti / Pubblicati / Bozze" dagli elenchi dei docenti: i
	 * numeri comprenderebbero anche i contenuti degli altri.
	 *
	 * @param array $views Filtri.
	 * @return array
	 */
	public function hide_views( $views ) {
		return $this->is_authoring_teacher( get_current_user_id() ) ? array() : $views;
	}

	/* ------------------------------------------------------------------ */
	/* Anteprima sul sito                                                  */
	/* ------------------------------------------------------------------ */

	/**
	 * Un docente vede sul sito il proprio corso anche se non è pubblicato.
	 *
	 * @param bool $allowed   Decisione della base.
	 * @param int  $user_id   Utente.
	 * @param int  $course_id Corso.
	 */
	public function filter_course_access( $allowed, $user_id, $course_id ) {
		if ( $allowed || ! $this->is_authoring_teacher( (int) $user_id ) ) {
			return $allowed;
		}
		return $this->owns( (int) $user_id, get_post( (int) $course_id ) );
	}

	/**
	 * Un docente vede sul sito la propria lezione anche se non è pubblicata.
	 *
	 * @param bool $allowed   Decisione della base.
	 * @param int  $user_id   Utente.
	 * @param int  $lesson_id Lezione.
	 */
	public function filter_lesson_access( $allowed, $user_id, $lesson_id ) {
		if ( $allowed || ! $this->is_authoring_teacher( (int) $user_id ) ) {
			return $allowed;
		}
		return $this->owns( (int) $user_id, get_post( (int) $lesson_id ) );
	}

	/* ------------------------------------------------------------------ */
	/* Proprietà e pubblicazione                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Un corso creato da un docente è sempre a nome suo.
	 *
	 * @param int     $post_id Corso.
	 * @param WP_Post $post    Contenuto.
	 */
	public function assign_teacher( int $post_id, WP_Post $post ): void {
		$user_id = get_current_user_id();
		if ( wp_is_post_revision( $post_id ) || ! $this->is_authoring_teacher( $user_id ) || ! $this->owns( $user_id, $post ) ) {
			return;
		}
		$profile = $this->profile_id( $user_id );
		if ( $profile ) {
			update_post_meta( $post_id, '_ael_teacher_id', $profile );
		}
	}

	/**
	 * Ricorda quali corsi passano a "pubblicato" in questa richiesta.
	 *
	 * @param string  $new_status Nuovo stato.
	 * @param string  $old_status Stato precedente.
	 * @param WP_Post $post       Contenuto.
	 */
	public function remember_publication( $new_status, $old_status, $post ): void {
		if ( $post instanceof WP_Post && AEL_Content::COURSE === $post->post_type && 'publish' === $new_status && 'publish' !== $old_status ) {
			$this->just_published[ (int) $post->ID ] = true;
		}
	}

	/**
	 * Quando il titolare pubblica un corso, si pubblicano anche i suoi moduli e
	 * le sue lezioni che sono ancora in bozza o in revisione: ci pensa lui una
	 * volta sola, invece di pubblicarli uno per uno.
	 *
	 * @param int     $post_id Corso.
	 * @param WP_Post $post    Contenuto.
	 */
	public function publish_children( int $post_id, WP_Post $post ): void {
		if ( empty( $this->just_published[ $post_id ] ) || 'publish' !== $post->post_status ) {
			return;
		}
		unset( $this->just_published[ $post_id ] );

		$module_type = get_post_type_object( AEL_Content::MODULE );
		if ( ! $module_type || ! current_user_can( $module_type->cap->publish_posts ) ) {
			return;
		}

		$drafts   = array( 'draft', 'pending' );
		$modules  = get_posts(
			array(
				'post_type'   => AEL_Content::MODULE,
				'post_parent' => $post_id,
				'post_status' => $drafts,
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		$all_modules = get_posts(
			array(
				'post_type'   => AEL_Content::MODULE,
				'post_parent' => $post_id,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		$lessons = empty( $all_modules ) ? array() : get_posts(
			array(
				'post_type'       => AEL_Content::LESSON,
				'post_parent__in' => $all_modules,
				'post_status'     => $drafts,
				'numberposts'     => -1,
				'fields'          => 'ids',
			)
		);

		foreach ( array_merge( $modules, $lessons ) as $child_id ) {
			wp_publish_post( (int) $child_id );
		}
		AEL_Catalog::flush_cache();
	}

	/* ------------------------------------------------------------------ */
	/* Accesso e avvisi                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Dopo l'accesso un docente va dritto ai suoi corsi.
	 *
	 * @param string           $redirect_to Destinazione calcolata.
	 * @param string           $requested   Destinazione richiesta.
	 * @param WP_User|WP_Error $user        Utente.
	 */
	public function login_redirect( $redirect_to, $requested, $user ) {
		if ( ! $user instanceof WP_User || ! $this->is_authoring_teacher( (int) $user->ID ) ) {
			return $redirect_to;
		}
		if ( '' === (string) $requested || 0 === strpos( (string) $requested, admin_url() ) ) {
			return admin_url( 'edit.php?post_type=' . AEL_Content::COURSE );
		}
		return $redirect_to;
	}

	/**
	 * Avvisa il docente se nessuna scheda docente è collegata al suo utente.
	 */
	public function profile_notice(): void {
		$user_id = get_current_user_id();
		if ( ! $this->is_authoring_teacher( $user_id ) || $this->profile_id( $user_id ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'La tua scheda docente non è ancora collegata al tuo utente: i tuoi corsi non mostreranno il tuo nome. Chiedi al titolare dell\'academy di collegarla.', 'academy-elearning' )
		);
	}
}
