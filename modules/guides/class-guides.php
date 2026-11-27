<?php
/**
 * Guide: materiali di approfondimento per gli abbonati.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tipo di contenuto "Guida" con tema (tassonomia) e PDF allegati. Il testo e i file
 * si vedono solo dopo il controllo di AEL_Access::user_has_catalog_access(): chi non
 * è abbonato vede il titolo e un breve estratto, mai il contenuto né i file.
 */
final class AEL_Guides {

	public const TYPE       = 'ael_guide';
	public const TAX        = 'ael_guide_topic';
	public const META_FILES = '_ael_guide_files';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Guides|null
	 */
	private static ?AEL_Guides $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Guides {
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
	 * Aggancia tipo di contenuto, pagina, shortcode, contenuto e download.
	 */
	public function boot(): void {
		add_filter( 'ael_pages_definitions', array( $this, 'add_page' ) );
		add_filter( 'ael_migrations', array( $this, 'add_migration' ) );
		add_filter( 'ael_template_path', array( $this, 'module_templates' ), 10, 2 );
		add_filter( 'user_has_cap', array( $this, 'grant_caps' ), 20 );

		add_action( 'init', array( $this, 'register' ), 6 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite' ), 99 );
		add_action( 'ael_module_activated', array( $this, 'on_module_activated' ) );
		add_shortcode( 'ael_guides', array( $this, 'shortcode' ) );
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_action( 'template_redirect', array( $this, 'maybe_download' ), 1 );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
			add_action( 'save_post_' . self::TYPE, array( $this, 'save_meta' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Pagina e migrazione                                                 */
	/* ------------------------------------------------------------------ */

	/**
	 * Aggiunge la pagina "Guide" all'elenco delle pagine di sistema.
	 *
	 * @param array $defs Pagine.
	 * @return array
	 */
	public function add_page( $defs ) {
		$defs['guides'] = array(
			'option'  => 'ael_page_guides',
			'title'   => 'Guide',
			'slug'    => 'guide',
			'content' => '[ael_guides]',
		);
		return $defs;
	}

	/**
	 * Registra la migrazione che crea la pagina.
	 *
	 * @param array<string, callable> $migrations Migrazioni.
	 * @return array<string, callable>
	 */
	public function add_migration( $migrations ) {
		$migrations['guides-001-page'] = array( __CLASS__, 'setup_page' );
		return $migrations;
	}

	/**
	 * Crea la pagina Guide e chiede di rigenerare gli indirizzi.
	 */
	public static function setup_page(): bool {
		update_option( 'ael_guides_flush', 1 );
		return AEL_Pages::setup();
	}

	/**
	 * Quando il modulo viene acceso, rigenera gli indirizzi.
	 *
	 * @param string $slug Slug del modulo.
	 */
	public function on_module_activated( $slug ): void {
		if ( 'guides' === $slug ) {
			update_option( 'ael_guides_flush', 1 );
		}
	}

	/**
	 * Rigenera gli indirizzi una volta, dopo la registrazione del tipo di contenuto.
	 */
	public function maybe_flush_rewrite(): void {
		if ( get_option( 'ael_guides_flush' ) ) {
			delete_option( 'ael_guides_flush' );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * I template del modulo vivono nella sua cartella; il tema può comunque sostituirli.
	 *
	 * @param string $path Percorso scelto.
	 * @param string $name Nome del template.
	 * @return string
	 */
	public function module_templates( $path, $name ) {
		if ( in_array( $name, array( 'guides', 'guide' ), true ) && ! is_readable( (string) $path ) ) {
			return __DIR__ . '/templates/' . $name . '.php';
		}
		return $path;
	}

	/* ------------------------------------------------------------------ */
	/* Contenuto                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Registra la guida e il suo tema.
	 */
	public function register(): void {
		register_post_type(
			self::TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Guide', 'academy-elearning' ),
					'singular_name' => __( 'Guida', 'academy-elearning' ),
					'add_new_item'  => __( 'Aggiungi guida', 'academy-elearning' ),
					'edit_item'     => __( 'Modifica guida', 'academy-elearning' ),
					'all_items'     => __( 'Guide', 'academy-elearning' ),
				),
				'public'              => true,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'show_ui'             => true,
				'show_in_menu'        => 'ael-academy',
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => array( 'slug' => 'guide-approfondimento' ),
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'capability_type'     => array( 'ael_guide', 'ael_guides' ),
				'map_meta_cap'        => true,
			)
		);
		register_taxonomy(
			self::TAX,
			self::TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Temi delle guide', 'academy-elearning' ),
					'singular_name' => __( 'Tema', 'academy-elearning' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_in_rest'      => false,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => false,
				'capabilities'      => array(
					'manage_terms' => AEL_Content::CAP,
					'edit_terms'   => AEL_Content::CAP,
					'delete_terms' => AEL_Content::CAP,
					'assign_terms' => AEL_Content::CAP,
				),
			)
		);
	}

	/**
	 * Chi ha la capacità del titolare gestisce anche le guide.
	 *
	 * @param array $allcaps Permessi dell'utente.
	 * @return array
	 */
	public function grant_caps( $allcaps ) {
		if ( ! empty( $allcaps[ AEL_Content::CAP ] ) ) {
			$object = get_post_type_object( self::TYPE );
			if ( $object ) {
				foreach ( (array) $object->cap as $name ) {
					$allcaps[ (string) $name ] = true;
				}
			}
		}
		return $allcaps;
	}

	/**
	 * L'utente corrente può vedere le guide?
	 */
	public static function can_view(): bool {
		return AEL_Access::user_has_catalog_access( get_current_user_id() );
	}

	/**
	 * File allegati a una guida (ID degli allegati).
	 *
	 * @param int $guide_id Guida.
	 * @return int[]
	 */
	public static function files( int $guide_id ): array {
		$ids = get_post_meta( $guide_id, self::META_FILES, true );
		return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
	}

	/**
	 * Indirizzo di download protetto di un file.
	 *
	 * @param int $guide_id Guida.
	 * @param int $file_id  Allegato.
	 */
	public static function download_url( int $guide_id, int $file_id ): string {
		return add_query_arg(
			array(
				'ael_guide_dl' => $guide_id,
				'ael_file'     => $file_id,
			),
			home_url( '/' )
		);
	}

	/**
	 * Carica gli stili della sezione.
	 */
	private static function enqueue(): void {
		wp_enqueue_style( 'ael-front' );
		wp_enqueue_style( 'ael-sections', AEL_URL . 'assets/sections.css', array( 'ael-front' ), AEL_VERSION );
	}

	/* ------------------------------------------------------------------ */
	/* Pagina pubblica                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * Shortcode [ael_guides]: elenco delle guide con filtro per tema e ricerca.
	 */
	public function shortcode(): string {
		self::enqueue();

		// Filtri di lettura: nessun dato scritto, quindi niente nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$topic  = isset( $_GET['tema'] ) ? sanitize_title( wp_unslash( $_GET['tema'] ) ) : '';
		$search = isset( $_GET['cerca'] ) ? sanitize_text_field( wp_unslash( $_GET['cerca'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$args = array(
			'post_type'      => self::TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 60,
			'orderby'        => 'title',
			'order'          => 'ASC',
			's'              => $search,
		);
		if ( '' !== $topic ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => self::TAX,
					'field'    => 'slug',
					'terms'    => $topic,
				),
			);
		}

		$items = array();
		foreach ( get_posts( $args ) as $guide ) {
			$terms   = get_the_terms( $guide, self::TAX );
			$items[] = array(
				'id'      => (int) $guide->ID,
				'title'   => get_the_title( $guide ),
				'excerpt' => self::teaser( $guide ),
				'url'     => get_permalink( $guide ),
				'topic'   => ( is_array( $terms ) && ! empty( $terms ) ) ? $terms[0]->name : '',
				'files'   => count( self::files( $guide->ID ) ),
			);
		}

		$topics = get_terms(
			array(
				'taxonomy'   => self::TAX,
				'hide_empty' => true,
			)
		);

		return AEL_Templates::render(
			'guides',
			array(
				'items'    => $items,
				'topics'   => is_array( $topics ) ? $topics : array(),
				'topic'    => $topic,
				'search'   => $search,
				'can_view' => self::can_view(),
				'gate'     => self::gate_vars(),
				'page_url' => AEL_Pages::url( 'guides', home_url( '/' ) ),
			)
		);
	}

	/**
	 * Estratto breve visibile a tutti (mai il testo intero).
	 *
	 * @param WP_Post $guide Guida.
	 */
	private static function teaser( WP_Post $guide ): string {
		return wp_trim_words( wp_strip_all_tags( (string) $guide->post_excerpt ), 30 );
	}

	/**
	 * Dati per il riquadro "riservato agli abbonati".
	 *
	 * @return array<string, mixed>
	 */
	public static function gate_vars(): array {
		return array(
			'logged_in'     => is_user_logged_in(),
			'subscribe_url' => AEL_Pages::url( 'subscribe', home_url( '/' ) ),
			'login_url'     => AEL_Pages::url( 'login', wp_login_url() ),
			'section'       => __( 'Le guide', 'academy-elearning' ),
		);
	}

	/**
	 * Pagina della singola guida. Chi non può vederla riceve solo l'estratto,
	 * in qualunque contesto (anche elenchi e feed): il testo non esce mai.
	 *
	 * @param string $content Contenuto elaborato.
	 */
	public function filter_content( $content ) {
		$post = get_post();
		if ( ! $post instanceof WP_Post || self::TYPE !== $post->post_type ) {
			return $content;
		}
		$is_main = is_singular( self::TYPE ) && in_the_loop() && is_main_query();

		if ( ! self::can_view() ) {
			if ( ! $is_main ) {
				return esc_html( self::teaser( $post ) );
			}
			self::enqueue();
			return AEL_Templates::render(
				'guide',
				array(
					'content'  => '',
					'teaser'   => self::teaser( $post ),
					'files'    => array(),
					'can_view' => false,
					'back_url' => AEL_Pages::url( 'guides', home_url( '/' ) ),
					'gate'     => self::gate_vars(),
				)
			);
		}
		if ( ! $is_main ) {
			return $content;
		}

		self::enqueue();
		$files = array();
		foreach ( self::files( $post->ID ) as $file_id ) {
			if ( 'attachment' !== get_post_type( $file_id ) ) {
				continue;
			}
			$path    = get_attached_file( $file_id );
			$files[] = array(
				'title' => get_the_title( $file_id ),
				'url'   => self::download_url( $post->ID, $file_id ),
				'size'  => ( $path && is_file( $path ) ) ? size_format( (int) filesize( $path ) ) : '',
			);
		}
		return AEL_Templates::render(
			'guide',
			array(
				'content'  => $content,
				'teaser'   => '',
				'files'    => $files,
				'can_view' => true,
				'back_url' => AEL_Pages::url( 'guides', home_url( '/' ) ),
				'gate'     => self::gate_vars(),
			)
		);
	}

	/**
	 * Consegna un file dopo aver controllato abbonamento e appartenenza alla guida.
	 */
	public function maybe_download(): void {
		// Richiesta di lettura: il controllo di sicurezza è l'accesso, non un nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['ael_guide_dl'], $_GET['ael_file'] ) ) {
			return;
		}
		$guide_id = absint( wp_unslash( $_GET['ael_guide_dl'] ) );
		$file_id  = absint( wp_unslash( $_GET['ael_file'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $guide_id || ! $file_id || self::TYPE !== get_post_type( $guide_id ) || 'publish' !== get_post_status( $guide_id ) || ! self::can_view() ) {
			wp_die( esc_html__( 'Non hai accesso a questo file.', 'academy-elearning' ), '', array( 'response' => 403 ) );
		}
		if ( ! in_array( $file_id, self::files( $guide_id ), true ) || 'attachment' !== get_post_type( $file_id ) ) {
			wp_die( esc_html__( 'File non trovato.', 'academy-elearning' ), '', array( 'response' => 404 ) );
		}

		$path    = get_attached_file( $file_id );
		$real    = $path ? realpath( $path ) : false;
		$uploads = realpath( wp_get_upload_dir()['basedir'] );
		if ( ! $real || ! $uploads || 0 !== strpos( $real, $uploads . DIRECTORY_SEPARATOR ) || ! is_file( $real ) ) {
			wp_die( esc_html__( 'File non trovato.', 'academy-elearning' ), '', array( 'response' => 404 ) );
		}

		$type = wp_check_filetype( $real );
		nocache_headers();
		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $real ) ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $real ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );

		while ( ob_get_level() ) {
			ob_end_clean();
		}
		readfile( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Amministrazione                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Riquadro "File allegati" nella schermata della guida.
	 */
	public function add_meta_box(): void {
		add_meta_box( 'ael_guide_files', __( 'File allegati (PDF)', 'academy-elearning' ), array( $this, 'box_files' ), self::TYPE, 'normal', 'default' );
	}

	/**
	 * Disegna il riquadro dei file.
	 *
	 * @param WP_Post $post Guida.
	 */
	public function box_files( WP_Post $post ): void {
		wp_nonce_field( 'ael_guide_files_' . $post->ID, 'ael_guide_files_nonce' );
		$ids = self::files( $post->ID );
		?>
		<p class="description"><?php echo esc_html__( 'I file si scaricano solo da chi ha l\'abbonamento. Il testo della guida va nell\'editor qui sopra; l\'estratto è l\'unica parte visibile a tutti.', 'academy-elearning' ); ?></p>
		<ul id="ael-guide-files" style="margin:10px 0">
			<?php foreach ( $ids as $id ) : ?>
				<li data-id="<?php echo esc_attr( (string) $id ); ?>">
					<?php echo esc_html( get_the_title( $id ) ); ?>
					<input type="hidden" name="ael_guide_files[]" value="<?php echo esc_attr( (string) $id ); ?>">
					<a href="#" class="ael-guide-remove"><?php echo esc_html__( 'Togli', 'academy-elearning' ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
		<p><button type="button" class="button" id="ael-guide-add"><?php echo esc_html__( 'Aggiungi file', 'academy-elearning' ); ?></button></p>
		<?php
	}

	/**
	 * Salva i file allegati.
	 *
	 * @param int     $post_id Guida.
	 * @param WP_Post $post    Contenuto.
	 */
	public function save_meta( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['ael_guide_files_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ael_guide_files_nonce'] ) ), 'ael_guide_files_' . $post_id ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = isset( $_POST['ael_guide_files'] ) ? (array) wp_unslash( $_POST['ael_guide_files'] ) : array();
		$ids = array();
		foreach ( $raw as $value ) {
			$id = absint( $value );
			if ( $id && 'attachment' === get_post_type( $id ) ) {
				$ids[] = $id;
			}
		}
		update_post_meta( $post_id, self::META_FILES, array_values( array_unique( $ids ) ) );
	}

	/**
	 * Carica il selettore dei file solo nella schermata della guida.
	 *
	 * @param string $hook Pagina di amministrazione.
	 */
	public function admin_assets( $hook ): void {
		$screen = get_current_screen();
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || self::TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'ael-guides-admin', plugins_url( 'assets/guides-admin.js', __FILE__ ), array( 'jquery' ), AEL_VERSION, true );
		wp_localize_script(
			'ael-guides-admin',
			'aelGuides',
			array(
				'remove' => __( 'Togli', 'academy-elearning' ),
				'title'  => __( 'Scegli i file', 'academy-elearning' ),
			)
		);
	}
}
