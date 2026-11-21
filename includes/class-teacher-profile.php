<?php
/**
 * Scheda pubblica personalizzabile del docente (docente).
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frase di presentazione, copertina, colore e link di un docente. Solo dati e
 * validazione: la grafica è nei template. Si accende dalla Configurazione
 * (solo agenzia); da spenta restituisce sempre una scheda vuota.
 *
 * Meta del docente: _ael_tagline, _ael_cover_id, _ael_accent, _ael_links.
 */
final class AEL_Teacher_Profile {

	/**
	 * Quanti link al massimo.
	 */
	public const MAX_LINKS = 6;

	/**
	 * Quanti canali social al massimo.
	 */
	public const MAX_CHANNELS = 6;

	/**
	 * Piattaforme disponibili (chiave => nome).
	 *
	 * @return array<string, string>
	 */
	public static function platforms(): array {
		/**
		 * Piattaforme social tra cui scegliere per i canali di un docente.
		 *
		 * @param array $platforms chiave => nome.
		 */
		return (array) apply_filters(
			'ael_teacher_platforms',
			array(
				'instagram' => 'Instagram',
				'tiktok'    => 'TikTok',
				'youtube'   => 'YouTube',
				'facebook'  => 'Facebook',
				'linkedin'  => 'LinkedIn',
				'twitch'    => 'Twitch',
				'x'         => 'X',
				'altro'     => __( 'Altro', 'academy-elearning' ),
			)
		);
	}

	/**
	 * Canali social ripuliti: piattaforma nota, nome del profilo, indirizzo
	 * http/https, follower (intero) ed engagement rate (da 0 a 100). Si scarta
	 * la riga senza profilo e senza indirizzo.
	 *
	 * @param mixed $raw Dati grezzi (array di array).
	 * @return array<int, array{platform: string, handle: string, url: string, followers: int, engagement: float}>
	 */
	public static function clean_channels( $raw ): array {
		$out       = array();
		$platforms = self::platforms();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$platform = sanitize_key( (string) ( $item['platform'] ?? '' ) );
			if ( ! isset( $platforms[ $platform ] ) ) {
				$platform = 'altro';
			}
			$handle = sanitize_text_field( (string) ( $item['handle'] ?? '' ) );
			$url    = esc_url_raw( trim( (string) ( $item['url'] ?? '' ) ), array( 'http', 'https' ) );
			if ( '' === $handle && '' === $url ) {
				continue;
			}
			$followers  = max( 0, (int) preg_replace( '/\D+/', '', (string) ( $item['followers'] ?? '' ) ) );
			$engagement = (float) str_replace( ',', '.', (string) preg_replace( '/[^0-9,.]/', '', (string) ( $item['engagement'] ?? '' ) ) );
			$out[]      = array(
				'platform'   => $platform,
				'handle'     => mb_substr( $handle, 0, 60 ),
				'url'        => $url,
				'followers'  => min( $followers, 2000000000 ),
				'engagement' => round( max( 0.0, min( 100.0, $engagement ) ), 2 ),
			);
			if ( count( $out ) >= self::MAX_CHANNELS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Numero in forma breve: 950, 12,5K, 1,2M.
	 *
	 * @param int $number Numero.
	 */
	public static function format_count( int $number ): string {
		if ( $number >= 1000000 ) {
			return self::trim_decimal( number_format_i18n( round( $number / 1000000, 1 ), 1 ) ) . 'M';
		}
		if ( $number >= 10000 ) {
			return self::trim_decimal( number_format_i18n( round( $number / 1000, 1 ), 1 ) ) . 'K';
		}
		return number_format_i18n( $number );
	}

	/**
	 * Toglie il decimale se è zero (183,0 diventa 183).
	 *
	 * @param string $formatted Numero già formattato con un decimale.
	 */
	private static function trim_decimal( string $formatted ): string {
		return (string) preg_replace( '/[.,]0$/', '', $formatted );
	}

	/**
	 * Percentuale in forma leggibile (es. 4,8%).
	 *
	 * @param float $rate Valore da 0 a 100.
	 */
	public static function format_rate( float $rate ): string {
		return number_format_i18n( $rate, 1 ) . '%';
	}

	/**
	 * La personalizzazione è accesa?
	 */
	public static function enabled(): bool {
		return (bool) AEL_Settings::get( 'teacher_profiles', 1 );
	}

	/**
	 * Colore esadecimale valido (#rrggbb o #rgb), oppure stringa vuota.
	 *
	 * @param string $value Valore da controllare.
	 */
	public static function clean_color( string $value ): string {
		$color = sanitize_hex_color( trim( $value ) );
		return $color ? strtolower( $color ) : '';
	}

	/**
	 * Colore del testo da usare sopra uno sfondo (bianco o quasi nero), in
	 * modo che resti leggibile.
	 *
	 * @param string $hex Colore di sfondo valido.
	 */
	public static function contrast_of( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return '#ffffff';
		}
		$parts = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$channel = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$parts[] = $channel <= 0.03928 ? $channel / 12.92 : pow( ( $channel + 0.055 ) / 1.055, 2.4 );
		}
		$luminance = 0.2126 * $parts[0] + 0.7152 * $parts[1] + 0.0722 * $parts[2];
		return $luminance > 0.4 ? '#111111' : '#ffffff';
	}

	/**
	 * Attributo style con le variabili CSS del colore, pronto da stampare con
	 * esc_attr(). Vuoto se non c'è un colore valido.
	 *
	 * @param string $accent Colore.
	 */
	public static function style_for( string $accent ): string {
		$accent = self::clean_color( $accent );
		if ( '' === $accent ) {
			return '';
		}
		return '--ael-accent:' . $accent . ';--ael-accent-contrast:' . self::contrast_of( $accent ) . ';';
	}

	/**
	 * Elenco di link ripulito: solo indirizzi http/https con un'etichetta,
	 * al massimo MAX_LINKS.
	 *
	 * @param mixed $raw Dati grezzi (array di array con label e url).
	 * @return array<int, array{label: string, url: string}>
	 */
	public static function clean_links( $raw ): array {
		$out = array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$url   = esc_url_raw( trim( (string) ( $item['url'] ?? '' ) ), array( 'http', 'https' ) );
			$label = sanitize_text_field( (string) ( $item['label'] ?? '' ) );
			if ( '' === $url ) {
				continue;
			}
			if ( '' === $label ) {
				$label = (string) preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
			}
			$out[] = array(
				'label' => $label,
				'url'   => $url,
			);
			if ( count( $out ) >= self::MAX_LINKS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Dati della scheda di un docente.
	 *
	 * @param int $teacher_id Docente.
	 * @return array{tagline: string, cover_url: string, accent: string, style: string, links: array<int, array{label: string, url: string}>}
	 */
	public static function get( int $teacher_id ): array {
		$empty = array(
			'tagline'   => '',
			'cover_url' => '',
			'accent'    => '',
			'style'     => '',
			'links'     => array(),
			'channels'        => array(),
			'total_followers' => 0,
			'engagement'      => 0.0,
			'stats_date'      => '',
		);
		if ( ! self::enabled() || AEL_Content::TEACHER !== get_post_type( $teacher_id ) ) {
			return $empty;
		}

		$cover_id  = (int) get_post_meta( $teacher_id, '_ael_cover_id', true );
		$cover_url = ( $cover_id && 'attachment' === get_post_type( $cover_id ) ) ? (string) wp_get_attachment_image_url( $cover_id, 'large' ) : '';
		$accent    = self::clean_color( (string) get_post_meta( $teacher_id, '_ael_accent', true ) );

		$channels = self::clean_channels( get_post_meta( $teacher_id, '_ael_channels', true ) );
		$total    = 0;
		$weighted = 0.0;
		$weight   = 0;
		foreach ( $channels as $channel ) {
			$total += $channel['followers'];
			if ( $channel['engagement'] > 0 && $channel['followers'] > 0 ) {
				$weighted += $channel['engagement'] * $channel['followers'];
				$weight   += $channel['followers'];
			}
		}

		return array(
			'tagline'         => (string) get_post_meta( $teacher_id, '_ael_tagline', true ),
			'cover_url'       => $cover_url,
			'accent'          => $accent,
			'style'           => self::style_for( $accent ),
			'links'           => self::clean_links( get_post_meta( $teacher_id, '_ael_links', true ) ),
			'channels'        => $channels,
			'total_followers' => $total,
			// Engagement medio dei canali, pesato sui follower.
			'engagement'      => $weight > 0 ? round( $weighted / $weight, 1 ) : 0.0,
			'stats_date'      => (string) get_post_meta( $teacher_id, '_ael_stats_date', true ),
		);
	}
}
