<?php
/**
 * Checkout dei corsi: account obbligatorio e consenso all'accesso immediato.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Valgono solo quando il carrello contiene un corso: il resto del negozio, se c'è,
 * non cambia.
 *
 * - Account obbligatorio: niente acquisto da ospite, email e password scelte dal cliente.
 *   Serve perché l'accesso al corso è legato a un utente.
 * - Consenso: casella obbligatoria con cui il cliente chiede la fornitura immediata del
 *   contenuto digitale e riconosce di perdere il diritto di recesso. Si salva con l'ordine
 *   (data e testo), come prova.
 */
final class AEL_Single_Purchase_Checkout {

	public const META_CONSENT      = '_ael_withdrawal_consent_at';
	public const META_CONSENT_TEXT = '_ael_withdrawal_consent_text';
	public const META_ATTRIBUTION  = '_ael_attribution';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Single_Purchase_Checkout|null
	 */
	private static ?AEL_Single_Purchase_Checkout $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Single_Purchase_Checkout {
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
	 * Aggancia le regole del checkout.
	 */
	public function boot(): void {
		// Account obbligatorio con scelta della password, solo se nel carrello c'è un corso.
		add_filter( 'pre_option_woocommerce_enable_guest_checkout', array( $this, 'no_guest' ) );
		add_filter( 'pre_option_woocommerce_enable_signup_and_login_from_checkout', array( $this, 'allow_signup' ) );
		add_filter( 'pre_option_woocommerce_registration_generate_password', array( $this, 'customer_picks_password' ) );

		// Consenso al recesso.
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_consent' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_consent' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_consent_and_attribution' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_consent_in_admin' ) );
	}

	/**
	 * Nel carrello c'è almeno un corso?
	 */
	public static function cart_has_courses(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['product_id'] ) && AEL_Single_Purchase::course_of_product( (int) $item['product_id'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Niente acquisto da ospite quando c'è un corso.
	 *
	 * @param mixed $value Valore (false = si usa quello salvato).
	 * @return mixed
	 */
	public function no_guest( $value ) {
		return self::cart_has_courses() ? 'no' : $value;
	}

	/**
	 * La creazione dell'account è possibile al checkout quando c'è un corso.
	 *
	 * @param mixed $value Valore.
	 * @return mixed
	 */
	public function allow_signup( $value ) {
		return self::cart_has_courses() ? 'yes' : $value;
	}

	/**
	 * Il cliente sceglie la propria password (non gliene viene mandata una).
	 *
	 * @param mixed $value Valore.
	 * @return mixed
	 */
	public function customer_picks_password( $value ) {
		return self::cart_has_courses() ? 'no' : $value;
	}

	/**
	 * Testo del consenso.
	 */
	public static function consent_text(): string {
		/**
		 * Testo della casella di consenso all'accesso immediato ai contenuti digitali.
		 *
		 * @param string $text Testo.
		 */
		return (string) apply_filters(
			'ael_withdrawal_consent_text',
			__( 'Chiedo l\'accesso immediato ai contenuti digitali e prendo atto che, una volta iniziata la fornitura, perdo il diritto di recesso.', 'academy-elearning' )
		);
	}

	/**
	 * Casella di consenso sopra il pulsante "Effettua ordine".
	 */
	public function render_consent(): void {
		if ( ! self::cart_has_courses() ) {
			return;
		}
		// Il checkout classico invia di nuovo i dati se c'è un errore: si mantiene la casella.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$checked = ! empty( $_POST['ael_withdrawal_consent'] );
		?>
		<p class="form-row validate-required ael-consent">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="ael_withdrawal_consent" id="ael_withdrawal_consent" value="1" <?php checked( $checked ); ?> required>
				<span><?php echo esc_html( self::consent_text() ); ?>&nbsp;<abbr class="required" title="<?php echo esc_attr__( 'obbligatorio', 'academy-elearning' ); ?>">*</abbr></span>
			</label>
		</p>
		<?php
	}

	/**
	 * Senza consenso l'ordine non parte. (Il nonce lo ha già controllato WooCommerce.)
	 */
	public function validate_consent(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( self::cart_has_courses() && empty( $_POST['ael_withdrawal_consent'] ) ) {
			wc_add_notice( __( 'Per completare l\'acquisto devi spuntare la casella sull\'accesso immediato ai contenuti digitali.', 'academy-elearning' ), 'error' );
		}
	}

	/**
	 * Salva con l'ordine il consenso (data e testo) e i dati di provenienza.
	 *
	 * @param WC_Order $order Ordine.
	 * @param array    $data  Dati del checkout.
	 */
	public function save_consent_and_attribution( $order, $data ): void {
		if ( ! self::cart_has_courses() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['ael_withdrawal_consent'] ) ) {
			$order->update_meta_data( self::META_CONSENT, gmdate( 'Y-m-d H:i:s' ) );
			$order->update_meta_data( self::META_CONSENT_TEXT, self::consent_text() );
		}

		/**
		 * Dati di provenienza da conservare con l'ordine. Il modulo Attribuzione li
		 * fornisce (primo e ultimo contatto); senza di lui l'elenco resta vuoto.
		 *
		 * @param array  $data    Dati (di base vuoti).
		 * @param string $gateway Origine dell'ordine.
		 */
		$attribution = apply_filters( 'ael_checkout_attribution', array(), 'woocommerce' );
		if ( is_array( $attribution ) && ! empty( $attribution ) ) {
			$order->update_meta_data( self::META_ATTRIBUTION, $attribution );
		}
	}

	/**
	 * Mostra nell'ordine, per il titolare, il consenso dato dal cliente.
	 *
	 * @param WC_Order $order Ordine.
	 */
	public function show_consent_in_admin( $order ): void {
		$at = (string) $order->get_meta( self::META_CONSENT );
		if ( '' === $at ) {
			return;
		}
		echo '<p><strong>' . esc_html__( 'Consenso all\'accesso immediato ai contenuti digitali', 'academy-elearning' ) . ':</strong> ' . esc_html( sprintf( /* translators: %s: data */ __( 'dato il %s (UTC)', 'academy-elearning' ), $at ) ) . '</p>';
	}
}
