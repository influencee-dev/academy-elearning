<?php
/**
 * Chiamate dirette all'API di Stripe e verifica della firma dei webhook.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Niente SDK: solo wp_remote_request verso api.stripe.com.
 *
 * Regole di sicurezza:
 * - le chiavi stanno solo nelle impostazioni del modulo (option) e non si stampano né si registrano mai;
 * - una chiave "live" non si usa su un sito di sviluppo (localhost, .local, .test...);
 * - la versione dell'API è fissata, così i campi che leggiamo restano quelli attesi anche se
 *   l'account Stripe passa a una versione più recente;
 * - dagli eventi dei webhook si leggono solo gli ID: l'oggetto vero si rilegge dall'API.
 */
final class AEL_Subscription_Stripe {

	public const API     = 'https://api.stripe.com/v1/';
	public const VERSION = '2024-06-20';
	public const OPTION  = 'ael_sub_settings';

	/**
	 * Impostazioni del modulo.
	 *
	 * @return array{secret_key: string, webhook_secret: string, price_id: string, grace_days: int}
	 */
	public static function settings(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array(
			'secret_key'     => isset( $stored['secret_key'] ) ? (string) $stored['secret_key'] : '',
			'webhook_secret' => isset( $stored['webhook_secret'] ) ? (string) $stored['webhook_secret'] : '',
			'price_id'       => isset( $stored['price_id'] ) ? (string) $stored['price_id'] : '',
			'grace_days'     => isset( $stored['grace_days'] ) ? max( 0, min( 14, (int) $stored['grace_days'] ) ) : 2,
		);
	}

	/**
	 * Modalità della chiave: test, live o stringa vuota.
	 */
	public static function mode(): string {
		$key = self::settings()['secret_key'];
		if ( preg_match( '/^(sk|rk)_test_/', $key ) ) {
			return 'test';
		}
		if ( preg_match( '/^(sk|rk)_live_/', $key ) ) {
			return 'live';
		}
		return '';
	}

	/**
	 * Il sito è un ambiente di sviluppo (indirizzo locale)?
	 */
	public static function is_dev_site(): bool {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		return 'localhost' === $host || '127.0.0.1' === $host || (bool) preg_match( '/\.(local|test|localhost|invalid|example)$/', $host );
	}

	/**
	 * Chiave e prezzo sono impostati e l'uso è consentito?
	 * Una chiave live su un sito di sviluppo non è consentita.
	 */
	public static function is_ready(): bool {
		$settings = self::settings();
		if ( '' === $settings['secret_key'] || '' === $settings['price_id'] || '' === self::mode() ) {
			return false;
		}
		return ! ( 'live' === self::mode() && self::is_dev_site() );
	}

	/**
	 * Codifica i parametri nel formato di Stripe (a[b][0][c]=valore).
	 *
	 * @param array<string, mixed> $params Parametri.
	 */
	public static function encode( array $params ): string {
		return http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Chiama l'API di Stripe.
	 *
	 * @param string               $method  GET, POST o DELETE.
	 * @param string               $path    Percorso dopo /v1/ (per esempio "subscriptions/sub_123").
	 * @param array<string, mixed> $params  Parametri.
	 * @param string|null          $idem    Chiave di idempotenza per le scritture.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function request( string $method, string $path, array $params = array(), ?string $idem = null ) {
		$settings = self::settings();
		if ( '' === $settings['secret_key'] ) {
			return new WP_Error( 'ael_stripe_no_key', __( 'La chiave segreta di Stripe non è impostata.', 'academy-elearning' ) );
		}
		if ( 'live' === self::mode() && self::is_dev_site() ) {
			return new WP_Error( 'ael_stripe_live_on_dev', __( 'Una chiave live non si usa su un sito di sviluppo.', 'academy-elearning' ) );
		}

		$url     = self::API . ltrim( $path, '/' );
		$headers = array(
			'Authorization'  => 'Bearer ' . $settings['secret_key'],
			'Stripe-Version' => self::VERSION,
			'User-Agent'     => 'academy-elearning',
		);
		$args    = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => $headers,
		);
		if ( 'GET' === $method ) {
			if ( ! empty( $params ) ) {
				$url .= '?' . self::encode( $params );
			}
		} else {
			$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
			$args['body']                    = self::encode( $params );
			if ( null !== $idem ) {
				$args['headers']['Idempotency-Key'] = $idem;
			}
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ael_stripe_http', $response->get_error_message() );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status >= 400 || ! is_array( $data ) ) {
			$message = ( is_array( $data ) && isset( $data['error']['message'] ) ) ? (string) $data['error']['message'] : __( 'Risposta non valida da Stripe.', 'academy-elearning' );
			return new WP_Error( 'ael_stripe_api', $message, array( 'status' => $status ) );
		}
		return $data;
	}

	/**
	 * Verifica la firma di un webhook (intestazione Stripe-Signature).
	 *
	 * La firma è HMAC-SHA256 di "{tempo}.{corpo}" con il segreto del webhook. Si rifiuta
	 * se manca, se non coincide o se il tempo è troppo lontano (protezione dai replay).
	 *
	 * @param string   $payload   Corpo grezzo della richiesta.
	 * @param string   $header    Intestazione Stripe-Signature.
	 * @param string   $secret    Segreto del webhook (whsec_...).
	 * @param int      $tolerance Secondi di tolleranza.
	 * @param int|null $now       Ora attuale (per le prove).
	 */
	public static function verify_signature( string $payload, string $header, string $secret, int $tolerance = 300, ?int $now = null ): bool {
		if ( '' === $secret || '' === $header ) {
			return false;
		}
		$timestamp  = 0;
		$signatures = array();
		foreach ( explode( ',', $header ) as $part ) {
			$pair = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			if ( 't' === $pair[0] ) {
				$timestamp = (int) $pair[1];
			} elseif ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}
		if ( $timestamp <= 0 || empty( $signatures ) ) {
			return false;
		}
		$now = $now ?? time();
		if ( abs( $now - $timestamp ) > $tolerance ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Prezzo dell'abbonamento (importo e frequenza), con cache di un'ora.
	 *
	 * @return array{amount: int, currency: string, interval: string}|null
	 */
	public static function price(): ?array {
		if ( ! self::is_ready() ) {
			return null;
		}
		$key    = 'ael_sub_price_' . md5( self::settings()['price_id'] . self::mode() );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$price = self::request( 'GET', 'prices/' . rawurlencode( self::settings()['price_id'] ) );
		if ( is_wp_error( $price ) || empty( $price['unit_amount'] ) ) {
			return null;
		}
		$out = array(
			'amount'   => (int) $price['unit_amount'],
			'currency' => strtoupper( (string) ( $price['currency'] ?? 'eur' ) ),
			'interval' => (string) ( $price['recurring']['interval'] ?? 'month' ),
		);
		set_transient( $key, $out, HOUR_IN_SECONDS );
		return $out;
	}
}
