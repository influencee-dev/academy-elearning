<?php
/**
 * Ordini WooCommerce: accessi, incassi annunciati e rimborsi.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ordine completato = accesso al corso a vita (concessione "purchase", riferimento
 * "wc-{ordine}") e incasso annunciato ai moduli interessati. Ordine rimborsato =
 * accesso tolto e rimborso annunciato.
 *
 * Gli ordini che contengono solo corsi si completano da soli appena il pagamento
 * arriva, così l'accesso è immediato.
 */
final class AEL_Single_Purchase_Orders {

	private const META_ANNOUNCED = '_ael_payment_announced';
	private const GATEWAY        = 'woocommerce';

	/**
	 * Istanza unica.
	 *
	 * @var AEL_Single_Purchase_Orders|null
	 */
	private static ?AEL_Single_Purchase_Orders $instance = null;

	/**
	 * Restituisce l'istanza unica.
	 */
	public static function instance(): AEL_Single_Purchase_Orders {
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
	 * Aggancia gli stati dell'ordine.
	 */
	public function boot(): void {
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'auto_complete' ), 10, 3 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'fulfil' ), 10, 2 );
		add_action( 'woocommerce_order_refunded', array( $this, 'on_refund' ), 10, 2 );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'on_full_refund_status' ), 10, 2 );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'revoke_all' ), 10, 2 );

		add_action( 'woocommerce_thankyou', array( $this, 'thank_you_box' ), 5 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_link' ), 10, 4 );
	}

	/* ------------------------------------------------------------------ */
	/* Utilità                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Importo in centesimi.
	 *
	 * @param float|string $amount Importo.
	 */
	private function cents( $amount ): int {
		return (int) round( (float) $amount * 100 );
	}

	/**
	 * Righe dell'ordine che sono corsi: elenco di [item, ID corso].
	 *
	 * @param WC_Order $order Ordine.
	 * @return array<int, array{0: WC_Order_Item_Product, 1: int}>
	 */
	private function course_items( $order ): array {
		$out = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$course_id = AEL_Single_Purchase::course_of_product( (int) $item->get_product_id() );
			if ( $course_id ) {
				$out[] = array( $item, $course_id );
			}
		}
		return $out;
	}

	/**
	 * Importo lordo (imponibile + IVA) rimborsato finora per una riga dell'ordine.
	 *
	 * @param WC_Order              $order Ordine.
	 * @param WC_Order_Item_Product $item  Riga.
	 */
	private function refunded_gross_for_item( $order, $item ): float {
		$total = 0.0;
		foreach ( $order->get_refunds() as $refund ) {
			foreach ( $refund->get_items() as $refund_item ) {
				if ( (int) $refund_item->get_meta( '_refunded_item_id' ) === (int) $item->get_id() ) {
					$total += abs( (float) $refund_item->get_total() ) + abs( (float) $refund_item->get_total_tax() );
				}
			}
		}
		return $total;
	}

	/**
	 * Riferimento della concessione per un ordine.
	 *
	 * @param WC_Order $order Ordine.
	 */
	private function grant_ref( $order ): string {
		return 'wc-' . $order->get_id();
	}

	/**
	 * L'ordine contiene solo corsi?
	 *
	 * @param WC_Order $order Ordine.
	 */
	private function only_courses( $order ): bool {
		$items = $order->get_items();
		return ! empty( $items ) && count( $items ) === count( $this->course_items( $order ) );
	}

	/* ------------------------------------------------------------------ */
	/* Pagamento e completamento                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Un ordine di soli corsi, pagato, passa direttamente a "completato".
	 *
	 * @param string        $status   Stato proposto.
	 * @param int           $order_id Ordine.
	 * @param WC_Order|null $order    Ordine.
	 * @return string
	 */
	public function auto_complete( $status, $order_id = 0, $order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( (int) $order_id );
		return ( $order && $this->only_courses( $order ) ) ? 'completed' : $status;
	}

	/**
	 * Ordine completato: concede gli accessi e annuncia gli incassi.
	 *
	 * @param int           $order_id Ordine.
	 * @param WC_Order|null $order    Ordine.
	 */
	public function fulfil( $order_id, $order = null ): void {
		$order = $order instanceof WC_Order ? $order : wc_get_order( (int) $order_id );
		if ( ! $order ) {
			return;
		}
		$items = $this->course_items( $order );
		if ( empty( $items ) ) {
			return;
		}

		$user_id = (int) $order->get_user_id();
		if ( ! $user_id ) {
			$order->add_order_note( __( 'Ordine senza account cliente: nessun accesso al corso concesso. Collega l\'ordine a un utente e rimettilo su "Completato".', 'academy-elearning' ) );
			return;
		}

		foreach ( $items as $entry ) {
			AEL_Access::grant( $user_id, (int) $entry[1], AEL_Access::SOURCE_PURCHASE, $this->grant_ref( $order ) );
		}

		// L'incasso si annuncia una sola volta, anche se l'ordine cambia stato più volte.
		if ( '' !== (string) $order->get_meta( self::META_ANNOUNCED ) ) {
			return;
		}
		$order->update_meta_data( self::META_ANNOUNCED, gmdate( 'Y-m-d H:i:s' ) );
		$order->save();

		$paid    = $order->get_date_paid() ? $order->get_date_paid() : $order->get_date_completed();
		$paid_at = $paid ? gmdate( 'Y-m-d H:i:s', $paid->getTimestamp() ) : gmdate( 'Y-m-d H:i:s' );

		/**
		 * Commissione del gateway per questo ordine, in centesimi, se la conosci.
		 * Di base null: il registro vendite la tratta come 0 e si corregge a mano.
		 *
		 * @param int|null $fee   Commissione in centesimi.
		 * @param WC_Order $order Ordine.
		 */
		$fee = apply_filters( 'ael_wc_order_fee', null, $order );

		$attribution = $order->get_meta( AEL_Single_Purchase_Checkout::META_ATTRIBUTION );
		$coupons     = implode( ', ', $order->get_coupon_codes() );
		$heard_from  = (string) $order->get_meta( '_ael_heard_from' );

		foreach ( $items as $entry ) {
			list( $item, $course_id ) = $entry;
			$line_tax                 = (float) $item->get_total_tax();
			$line_gross               = (float) $item->get_total() + $line_tax;

			AEL_Payments::record(
				array(
					'gateway'   => self::GATEWAY,
					'type'      => AEL_Payments::TYPE_PURCHASE,
					'ref'       => (string) $order->get_id(),
					'user_id'   => $user_id,
					'course_id' => (int) $course_id,
					'currency'  => $order->get_currency(),
					'gross'     => $this->cents( $line_gross ),
					'vat'       => $this->cents( $line_tax ),
					'fee'       => null === $fee ? null : (int) $fee,
					'paid_at'   => $paid_at,
					'meta'      => array(
						'attribution' => is_array( $attribution ) ? $attribution : array(),
						'coupon'      => $coupons,
						'heard_from'  => $heard_from,
					),
				)
			);
		}
	}

	/* ------------------------------------------------------------------ */
	/* Rimborsi e annullamenti                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Rimborso (anche parziale): annuncia il totale rimborsato per corso e, se la
	 * riga è rimborsata per intero, toglie l'accesso a quel corso.
	 *
	 * @param int $order_id  Ordine.
	 * @param int $refund_id Rimborso.
	 */
	public function on_refund( $order_id, $refund_id = 0 ): void {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order ) {
			return;
		}
		$user_id = (int) $order->get_user_id();

		foreach ( $this->course_items( $order ) as $entry ) {
			list( $item, $course_id ) = $entry;
			$line_gross               = (float) $item->get_total() + (float) $item->get_total_tax();
			$refunded                 = $this->refunded_gross_for_item( $order, $item );
			// Se WooCommerce non attribuisce il rimborso alle righe (rimborso "libero"), si usa il totale dell'ordine.
			if ( $refunded <= 0 && (float) $order->get_total_refunded() > 0 && 1 === count( $this->course_items( $order ) ) ) {
				$refunded = min( $line_gross, (float) $order->get_total_refunded() );
			}
			if ( $refunded <= 0 ) {
				continue;
			}

			AEL_Payments::refund(
				array(
					'gateway'   => self::GATEWAY,
					'ref'       => (string) $order->get_id(),
					'course_id' => (int) $course_id,
					'refunded'  => $this->cents( $refunded ),
				)
			);

			// Rimborsata per intero (tolleranza di un centesimo): niente più accesso.
			if ( $user_id && $this->cents( $refunded ) >= $this->cents( $line_gross ) - 1 ) {
				AEL_Access::revoke( AEL_Access::SOURCE_PURCHASE, $this->grant_ref( $order ), $user_id, (int) $course_id );
			}
		}
	}

	/**
	 * Ordine messo a mano su "Rimborsato" senza creare un rimborso: stesso effetto di un rimborso totale.
	 *
	 * @param int           $order_id Ordine.
	 * @param WC_Order|null $order    Ordine.
	 */
	public function on_full_refund_status( $order_id, $order = null ): void {
		$order = $order instanceof WC_Order ? $order : wc_get_order( (int) $order_id );
		if ( ! $order ) {
			return;
		}
		$user_id = (int) $order->get_user_id();
		foreach ( $this->course_items( $order ) as $entry ) {
			list( $item, $course_id ) = $entry;
			AEL_Payments::refund(
				array(
					'gateway'   => self::GATEWAY,
					'ref'       => (string) $order->get_id(),
					'course_id' => (int) $course_id,
					'refunded'  => $this->cents( (float) $item->get_total() + (float) $item->get_total_tax() ),
				)
			);
			if ( $user_id ) {
				AEL_Access::revoke( AEL_Access::SOURCE_PURCHASE, $this->grant_ref( $order ), $user_id, (int) $course_id );
			}
		}
	}

	/**
	 * Ordine annullato: si toglie l'accesso (se non c'è un rimborso, il registro vendite non cambia).
	 *
	 * @param int           $order_id Ordine.
	 * @param WC_Order|null $order    Ordine.
	 */
	public function revoke_all( $order_id, $order = null ): void {
		$order = $order instanceof WC_Order ? $order : wc_get_order( (int) $order_id );
		if ( $order && $order->get_user_id() ) {
			AEL_Access::revoke( AEL_Access::SOURCE_PURCHASE, $this->grant_ref( $order ), (int) $order->get_user_id() );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Cliente: pagina di conferma ed email                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Nella pagina "Grazie" porta il cliente al corso, o spiega che si sblocca col pagamento.
	 *
	 * @param int $order_id Ordine.
	 */
	public function thank_you_box( $order_id ): void {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order || empty( $this->course_items( $order ) ) ) {
			return;
		}
		echo '<div class="woocommerce-message ael-thanks">';
		if ( $order->has_status( 'completed' ) ) {
			echo '<p>' . esc_html__( 'Il tuo corso è sbloccato.', 'academy-elearning' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( AEL_Account::my_courses_url() ) . '">' . esc_html__( 'Vai ai tuoi corsi', 'academy-elearning' ) . '</a></p>';
		} else {
			echo '<p>' . esc_html__( 'Il corso si sbloccherà appena il pagamento sarà confermato. Lo troverai in "I miei corsi".', 'academy-elearning' ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Nell'email di ordine completato aggiunge il link ai corsi.
	 *
	 * @param WC_Order $order         Ordine.
	 * @param bool     $sent_to_admin Email per l'amministratore.
	 * @param bool     $plain_text    Email solo testo.
	 * @param mixed    $email         Email.
	 */
	public function email_link( $order, $sent_to_admin = false, $plain_text = false, $email = null ): void {
		if ( $sent_to_admin || ! $order instanceof WC_Order || ! $order->has_status( 'completed' ) || empty( $this->course_items( $order ) ) ) {
			return;
		}
		$url = AEL_Account::my_courses_url();
		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Accedi ai tuoi corsi:', 'academy-elearning' ) . ' ' . esc_url( $url ) . "\n";
			return;
		}
		echo '<p>' . esc_html__( 'Il tuo corso è pronto.', 'academy-elearning' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Vai ai tuoi corsi', 'academy-elearning' ) . '</a></p>';
	}
}
