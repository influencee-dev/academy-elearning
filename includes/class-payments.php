<?php
/**
 * Contratto dei pagamenti: come i moduli si scambiano gli incassi.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * I moduli di pagamento (acquisto singolo, abbonamento) NON parlano con i moduli
 * che usano i dati (quote docenti, provenienza, in futuro fatturazione). Annunciano
 * ogni incasso e ogni rimborso con due azioni, e chi è interessato le ascolta:
 *
 * - ael_payment_recorded( array $payment ): un pagamento riuscito (anche i rinnovi).
 * - ael_payment_refunded( array $refund ): un rimborso, totale o parziale.
 *
 * Gli importi sono sempre in centesimi (interi), nella valuta indicata.
 * Chi ascolta deve essere idempotente: lo stesso evento può arrivare due volte.
 */
final class AEL_Payments {

	public const TYPE_PURCHASE     = 'purchase';
	public const TYPE_SUBSCRIPTION = 'subscription';

	/**
	 * Ripulisce e completa i dati di un pagamento. Restituisce null se manca qualcosa di essenziale.
	 *
	 * Chiavi: gateway (woocommerce, stripe...), type (purchase | subscription),
	 * ref (riferimento unico dell'incasso: ordine, fattura...), user_id, course_id
	 * (0 per l'abbonamento), currency, gross, vat, fee (null = commissione non nota),
	 * paid_at (UTC), meta (dati aggiuntivi, per esempio la provenienza).
	 *
	 * @param array<string, mixed> $payment Dati grezzi.
	 * @return array<string, mixed>|null
	 */
	public static function normalize( array $payment ): ?array {
		$gateway = isset( $payment['gateway'] ) ? sanitize_key( (string) $payment['gateway'] ) : '';
		$type    = isset( $payment['type'] ) ? (string) $payment['type'] : '';
		$ref     = isset( $payment['ref'] ) ? substr( sanitize_text_field( (string) $payment['ref'] ), 0, 100 ) : '';
		if ( '' === $gateway || '' === $ref || ! in_array( $type, array( self::TYPE_PURCHASE, self::TYPE_SUBSCRIPTION ), true ) ) {
			return null;
		}

		$gross     = max( 0, (int) ( $payment['gross'] ?? 0 ) );
		$vat       = min( $gross, max( 0, (int) ( $payment['vat'] ?? 0 ) ) );
		$fee_known = isset( $payment['fee'] ) && null !== $payment['fee'];
		$fee       = $fee_known ? min( $gross - $vat, max( 0, (int) $payment['fee'] ) ) : 0;

		$paid_at = isset( $payment['paid_at'] ) ? (string) $payment['paid_at'] : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $paid_at ) ) {
			$paid_at = gmdate( 'Y-m-d H:i:s' );
		}
		$currency = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) ( $payment['currency'] ?? 'EUR' ) ), 0, 3 ) );

		return array(
			'gateway'   => $gateway,
			'type'      => $type,
			'ref'       => $ref,
			'user_id'   => max( 0, (int) ( $payment['user_id'] ?? 0 ) ),
			'course_id' => max( 0, (int) ( $payment['course_id'] ?? 0 ) ),
			'currency'  => 3 === strlen( $currency ) ? $currency : 'EUR',
			'gross'     => $gross,
			'vat'       => $vat,
			'fee'       => $fee,
			'fee_known' => $fee_known,
			'net'       => max( 0, $gross - $vat - $fee ),
			'paid_at'   => $paid_at,
			'meta'      => ( isset( $payment['meta'] ) && is_array( $payment['meta'] ) ) ? $payment['meta'] : array(),
		);
	}

	/**
	 * Annuncia un pagamento riuscito.
	 *
	 * @param array<string, mixed> $payment Dati del pagamento (vedi normalize()).
	 * @return bool Falso se i dati non sono validi (e nulla viene annunciato).
	 */
	public static function record( array $payment ): bool {
		$clean = self::normalize( $payment );
		if ( null === $clean ) {
			return false;
		}
		/**
		 * Scatta per ogni pagamento riuscito, rinnovi compresi.
		 *
		 * @param array $payment Pagamento normalizzato: gateway, type, ref, user_id,
		 *                       course_id, currency, gross, vat, fee, fee_known, net,
		 *                       paid_at (UTC), meta.
		 */
		do_action( 'ael_payment_recorded', $clean );
		return true;
	}

	/**
	 * Annuncia un rimborso.
	 *
	 * Chiavi: gateway, ref (lo stesso del pagamento), course_id, refunded (totale
	 * rimborsato finora per quel pagamento, in centesimi: non la sola ultima parte).
	 *
	 * @param array<string, mixed> $refund Dati del rimborso.
	 * @return bool Falso se i dati non sono validi.
	 */
	public static function refund( array $refund ): bool {
		$clean = array(
			'gateway'   => isset( $refund['gateway'] ) ? sanitize_key( (string) $refund['gateway'] ) : '',
			'ref'       => isset( $refund['ref'] ) ? substr( sanitize_text_field( (string) $refund['ref'] ), 0, 100 ) : '',
			'course_id' => max( 0, (int) ( $refund['course_id'] ?? 0 ) ),
			'refunded'  => max( 0, (int) ( $refund['refunded'] ?? 0 ) ),
		);
		if ( '' === $clean['gateway'] || '' === $clean['ref'] ) {
			return false;
		}
		/**
		 * Scatta quando un pagamento viene rimborsato, in tutto o in parte.
		 *
		 * @param array $refund gateway, ref, course_id e refunded (totale rimborsato in centesimi).
		 */
		do_action( 'ael_payment_refunded', $clean );
		return true;
	}

	/**
	 * Importo in centesimi scritto come testo (per esempio "12,50").
	 *
	 * @param int    $cents    Centesimi.
	 * @param string $currency Valuta.
	 */
	public static function format( int $cents, string $currency = 'EUR' ): string {
		$text = number_format_i18n( $cents / 100, 2 );
		return 'EUR' === $currency ? $text . ' €' : $text . ' ' . $currency;
	}
}
