<?php
/**
 * Corsi in vendita: prezzo, prodotto WooCommerce collegato, pulsante Acquista.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Il prezzo si scrive nella scheda del corso (riquadro "Vendita del corso"). Il
 * modulo crea e tiene aggiornato un prodotto WooCommerce virtuale collegato al corso:
 * nascosto dal negozio, venduto una sola volta per cliente, senza spedizione. La fonte
 * di verità del prezzo è il corso, non il prodotto.
 *
 * Meta del corso: _ael_price (prezzo nella valuta del negozio), _ael_product_id.
 * Meta del prodotto: _ael_course_id.
 */
final class AEL_Single_Purchase {

	public const META_PRICE   = '_ael_price';
	public const META_PRODUCT = '_ael_product_id';
	public const META_COURSE  = '_ael_course_id';

	private const NONCE_FIELD  = 'ael_sale_nonce';
	private const NONCE_ACTION = 'ael_save_sale';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Single_Purchase|null
	 */
	private static ?AEL_Single_Purchase $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Single_Purchase {
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
	 * Aggancia migrazione, riquadro, sincronizzazione e pulsanti.
	 */
	public function boot(): void {
		add_filter( 'ael_migrations', array( $this, 'add_migration' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
		add_action( 'save_post_' . AEL_Content::COURSE, array( $this, 'save_price' ), 30, 2 );
		add_action( 'save_post_' . AEL_Content::COURSE, array( $this, 'sync_on_save' ), 40, 1 );
		add_action( 'wp_trash_post', array( $this, 'on_trash' ) );

		// La pagina del prodotto non serve: si va alla pagina del corso.
		add_action( 'template_redirect', array( $this, 'redirect_product_page' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'block_if_owned' ), 10, 2 );

		// Qui si vendono corsi, non c'è un negozio: "Torna al negozio" e la pagina negozio portano al catalogo.
		add_filter( 'woocommerce_return_to_shop_redirect', array( $this, 'catalog_redirect' ) );
		add_filter( 'woocommerce_continue_shopping_redirect', array( $this, 'catalog_redirect' ) );
		add_filter( 'woocommerce_return_to_shop_text', array( $this, 'return_text' ) );
		add_filter( 'woocommerce_get_shop_page_permalink', array( $this, 'catalog_redirect' ) );
		add_action( 'template_redirect', array( $this, 'redirect_shop_page' ), 9 );
		// I corsi non sono file da scaricare: la voce "Download" dell'account non serve.
		add_filter(
			'woocommerce_account_menu_items',
			static function ( $items ) {
				if ( is_array( $items ) ) {
					unset( $items['downloads'] );
				}
				return $items;
			},
			30
		);

		// Prezzo e pulsante nelle pagine del corso.
		add_filter( 'ael_course_actions', array( $this, 'add_buy_action' ), 20, 4 );
		add_action( 'ael_course_side_top', array( $this, 'print_price' ) );
		add_action( 'ael_course_card_footer', array( $this, 'print_price_small' ) );
	}

	/**
	 * Indirizzo del catalogo corsi, al posto della pagina negozio di WooCommerce.
	 */
	public function catalog_redirect(): string {
		return AEL_Catalog::catalog_url();
	}

	/**
	 * Testo del pulsante "Torna al negozio" nel carrello vuoto.
	 */
	public function return_text(): string {
		return __( 'Torna al catalogo', 'academy-elearning' );
	}

	/**
	 * Chi apre la pagina negozio (o l'archivio prodotti) va al catalogo corsi.
	 */
	public function redirect_shop_page(): void {
		if ( function_exists( 'is_shop' ) && ( is_shop() || is_post_type_archive( 'product' ) ) ) {
			wp_safe_redirect( AEL_Catalog::catalog_url(), 302 );
			exit;
		}
	}

	/* ------------------------------------------------------------------ */
	/* Migrazione: pagine carrello e checkout classici                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Aggiunge la migrazione.
	 *
	 * @param array<string, callable> $migrations Migrazioni.
	 * @return array<string, callable>
	 */
	public function add_migration( $migrations ) {
		$migrations['single-purchase-001-classic-checkout'] = array( __CLASS__, 'use_classic_checkout' );
		return $migrations;
	}

	/**
	 * Le pagine Carrello e Pagamento di WooCommerce passano al checkout classico
	 * (shortcode): è quello che permette di richiedere password e consenso al recesso
	 * in modo affidabile. Il contenuto precedente si conserva in un'option.
	 */
	public static function use_classic_checkout(): bool {
		// Modificare delle pagine richiede che WordPress sia pronto: se non lo è ancora, si rimanda a init.
		if ( ! did_action( 'init' ) ) {
			add_action( 'init', array( __CLASS__, 'use_classic_checkout' ), 98 );
			return true;
		}
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return true; // WooCommerce non c'è ora: la migrazione si rifà quando il modulo sarà acceso con WooCommerce.
		}
		$pages = array(
			'cart'     => '[woocommerce_cart]',
			'checkout' => '[woocommerce_checkout]',
		);
		foreach ( $pages as $key => $shortcode ) {
			$page_id = (int) wc_get_page_id( $key );
			$page    = $page_id > 0 ? get_post( $page_id ) : null;
			if ( ! $page || false !== strpos( $page->post_content, $shortcode ) ) {
				continue;
			}
			// Prima si conserva il contenuto precedente, poi si cambia.
			$backup         = get_option( 'ael_sp_pages_backup', array() );
			$backup         = is_array( $backup ) ? $backup : array();
			$backup[ $key ] = array(
				'id'      => $page_id,
				'content' => $page->post_content,
			);
			update_option( 'ael_sp_pages_backup', $backup, false );
			wp_update_post(
				array(
					'ID'           => $page_id,
					'post_content' => $shortcode,
				)
			);
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Prezzo e prodotto                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Prezzo del corso, come numero (0 = non in vendita).
	 *
	 * @param int $course_id Corso.
	 */
	public static function price_of( int $course_id ): float {
		$raw = get_post_meta( $course_id, self::META_PRICE, true );
		return '' === (string) $raw ? 0.0 : max( 0.0, (float) wc_format_decimal( $raw ) );
	}

	/**
	 * Prodotto collegato a un corso, se esiste ed è in vendita.
	 *
	 * @param int $course_id Corso.
	 */
	public static function product_for( int $course_id ): ?WC_Product {
		$product_id = (int) get_post_meta( $course_id, self::META_PRODUCT, true );
		if ( ! $product_id ) {
			return null;
		}
		$product = wc_get_product( $product_id );
		return ( $product instanceof WC_Product && 'publish' === $product->get_status() ) ? $product : null;
	}

	/**
	 * Corso collegato a un prodotto (0 se il prodotto non è di un corso).
	 *
	 * @param int $product_id Prodotto.
	 */
	public static function course_of_product( int $product_id ): int {
		return (int) get_post_meta( $product_id, self::META_COURSE, true );
	}

	/**
	 * Crea o aggiorna il prodotto collegato, oppure lo toglie dalla vendita.
	 *
	 * @param int $course_id Corso.
	 */
	public function sync_product( int $course_id ): void {
		$course = get_post( $course_id );
		if ( ! $course || AEL_Content::COURSE !== $course->post_type ) {
			return;
		}
		$price      = self::price_of( $course_id );
		$on_sale    = $price > 0 && 'publish' === $course->post_status;
		$product_id = (int) get_post_meta( $course_id, self::META_PRODUCT, true );
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		if ( $product && ! ( $product instanceof WC_Product_Simple ) ) {
			$product = null;
		}

		if ( ! $on_sale ) {
			if ( $product && 'draft' !== $product->get_status() ) {
				$product->set_status( 'draft' );
				$product->save();
			}
			return;
		}

		if ( ! $product ) {
			$product = new WC_Product_Simple();
		}
		$product->set_name( get_the_title( $course ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_downloadable( false );
		$product->set_sold_individually( true );
		$product->set_manage_stock( false );
		$product->set_tax_status( 'taxable' );
		$product->set_regular_price( wc_format_decimal( $price ) );
		$product->update_meta_data( self::META_COURSE, $course_id );
		$product->save();

		update_post_meta( $course_id, self::META_PRODUCT, $product->get_id() );
	}

	/**
	 * Tiene allineato il prodotto quando il corso cambia (titolo, stato).
	 *
	 * @param int $course_id Corso.
	 */
	public function sync_on_save( int $course_id ): void {
		if ( wp_is_post_revision( $course_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}
		if ( self::price_of( $course_id ) > 0 || get_post_meta( $course_id, self::META_PRODUCT, true ) ) {
			$this->sync_product( $course_id );
		}
	}

	/**
	 * Un corso nel cestino non si può più comprare.
	 *
	 * @param int $post_id Contenuto.
	 */
	public function on_trash( $post_id ): void {
		if ( AEL_Content::COURSE === get_post_type( (int) $post_id ) ) {
			$product_id = (int) get_post_meta( (int) $post_id, self::META_PRODUCT, true );
			$product    = $product_id ? wc_get_product( $product_id ) : null;
			if ( $product ) {
				$product->set_status( 'draft' );
				$product->save();
			}
		}
	}

	/**
	 * La pagina WooCommerce di un prodotto-corso porta alla pagina del corso.
	 */
	public function redirect_product_page(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$course_id = self::course_of_product( (int) get_queried_object_id() );
		if ( $course_id && 'publish' === get_post_status( $course_id ) ) {
			wp_safe_redirect( (string) get_permalink( $course_id ), 302 );
			exit;
		}
	}

	/**
	 * Chi ha già accesso al corso non lo ricompra.
	 *
	 * @param bool $passed     Controllo superato.
	 * @param int  $product_id Prodotto.
	 * @return bool
	 */
	public function block_if_owned( $passed, $product_id ) {
		$course_id = self::course_of_product( (int) $product_id );
		if ( $passed && $course_id && is_user_logged_in() && AEL_Access::user_can_access_course( get_current_user_id(), $course_id ) ) {
			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Hai già accesso a questo corso.', 'academy-elearning' ), 'error' );
			}
			return false;
		}
		return $passed;
	}

	/* ------------------------------------------------------------------ */
	/* Riquadro nella scheda del corso                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Riquadro "Vendita del corso" (solo titolare).
	 */
	public function add_box(): void {
		if ( ! current_user_can( AEL_Content::CAP ) ) {
			return;
		}
		add_meta_box( 'ael_sale', __( 'Vendita del corso', 'academy-elearning' ), array( $this, 'render_box' ), AEL_Content::COURSE, 'side', 'default' );
	}

	/**
	 * Disegna il riquadro.
	 *
	 * @param WP_Post $post Corso.
	 */
	public function render_box( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, false );
		$raw        = get_post_meta( $post->ID, self::META_PRICE, true );
		$product_id = (int) get_post_meta( $post->ID, self::META_PRODUCT, true );
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		?>
		<p><label for="ael_price"><strong><?php echo esc_html( sprintf( /* translators: %s: simbolo della valuta */ __( 'Prezzo (%s)', 'academy-elearning' ), get_woocommerce_currency_symbol() ) ); ?></strong></label></p>
		<input type="text" inputmode="decimal" id="ael_price" name="ael_price" style="width:100%" value="<?php echo esc_attr( (string) $raw ); ?>" placeholder="0,00">
		<p class="description"><?php echo esc_html__( 'Vuoto o 0 = non in vendita. Il corso si compra solo quando è pubblicato.', 'academy-elearning' ); ?></p>
		<?php if ( $product ) : ?>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: stato del prodotto, 2: ID del prodotto */
						__( 'Prodotto WooCommerce #%2$d (%1$s).', 'academy-elearning' ),
						'publish' === $product->get_status() ? __( 'in vendita', 'academy-elearning' ) : __( 'non in vendita', 'academy-elearning' ),
						$product_id
					)
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Salva il prezzo e sincronizza il prodotto.
	 *
	 * @param int     $post_id Corso.
	 * @param WP_Post $post    Contenuto.
	 */
	public function save_price( int $post_id, WP_Post $post ): void {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id )
			|| ! AEL_Content::is_form_post( $post_id )
			|| ! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION )
			|| ! current_user_can( AEL_Content::CAP )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = isset( $_POST['ael_price'] ) ? sanitize_text_field( wp_unslash( $_POST['ael_price'] ) ) : '';
		if ( '' === trim( $raw ) ) {
			delete_post_meta( $post_id, self::META_PRICE );
		} else {
			update_post_meta( $post_id, self::META_PRICE, wc_format_decimal( $raw ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Pulsante e prezzo                                                   */
	/* ------------------------------------------------------------------ */

	/**
	 * Prezzo da mostrare (con IVA come impostato in WooCommerce), come HTML di WooCommerce.
	 *
	 * @param WC_Product $product Prodotto.
	 */
	private function price_html( WC_Product $product ): string {
		return wc_price( (float) wc_get_price_to_display( $product ) );
	}

	/**
	 * Aggiunge "Acquista a [prezzo]" a chi non ha ancora il corso.
	 *
	 * @param array $actions   Pulsanti.
	 * @param int   $course_id Corso.
	 * @param int   $user_id   Utente.
	 * @param bool  $has       Ha già accesso.
	 * @return array
	 */
	public function add_buy_action( $actions, $course_id, $user_id, $has ) {
		if ( $has ) {
			return $actions;
		}
		$product = self::product_for( (int) $course_id );
		if ( ! $product ) {
			return $actions;
		}
		$price = html_entity_decode( wp_strip_all_tags( $this->price_html( $product ) ), ENT_QUOTES, 'UTF-8' );

		$actions[] = array(
			'id'       => 'buy',
			/* translators: %s: prezzo */
			'label'    => sprintf( __( 'Acquista a %s', 'academy-elearning' ), $price ),
			'url'      => add_query_arg( 'add-to-cart', $product->get_id(), wc_get_checkout_url() ),
			'type'     => 'primary',
			'priority' => 20,
		);
		return $actions;
	}

	/**
	 * Prezzo grande sopra i pulsanti della scheda corso.
	 *
	 * @param int $course_id Corso.
	 */
	public function print_price( $course_id ): void {
		$product = self::product_for( (int) $course_id );
		if ( ! $product || ( is_user_logged_in() && AEL_Access::user_can_access_course( get_current_user_id(), (int) $course_id ) ) ) {
			return;
		}
		echo '<p class="ael-price">' . wp_kses_post( $this->price_html( $product ) ) . '</p>';
	}

	/**
	 * Prezzo nella scheda del catalogo.
	 *
	 * @param int $course_id Corso.
	 */
	public function print_price_small( $course_id ): void {
		$product = self::product_for( (int) $course_id );
		if ( ! $product || ( is_user_logged_in() && AEL_Access::user_can_access_course( get_current_user_id(), (int) $course_id ) ) ) {
			return;
		}
		echo '<p class="ael-card-price">' . wp_kses_post( $this->price_html( $product ) ) . '</p>';
	}
}
