<?php
/**
 * Incorporamento dei video (YouTube e Vimeo).
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Costruisce il codice di incorporamento di un video.
 *
 * Regola di sicurezza del progetto: questa classe produce il codice del video,
 * ma chi la chiama deve averlo chiesto SOLO dopo che AEL_Access ha confermato
 * l'accesso. Il video di anteprima di un corso, pubblico per natura, è l'unica eccezione.
 */
final class AEL_Video {

	/**
	 * Sorgenti disponibili: slug => etichetta e funzione che costruisce l'iframe.
	 * Un modulo futuro può aggiungerne con il filtro ael_video_providers.
	 *
	 * @return array<string, array{label: string, embed: callable}>
	 */
	public static function providers(): array {
		$providers = array(
			'youtube' => array(
				'label' => 'YouTube',
				'embed' => array( __CLASS__, 'embed_youtube' ),
			),
			'vimeo'   => array(
				'label' => 'Vimeo',
				'embed' => array( __CLASS__, 'embed_vimeo' ),
			),
		);

		/**
		 * Permette di aggiungere sorgenti video.
		 *
		 * @param array $providers slug => array( 'label' => string, 'embed' => callable( string $ref, string $title ): string ).
		 */
		$filtered = apply_filters( 'ael_video_providers', $providers );
		return is_array( $filtered ) ? $filtered : $providers;
	}

	/**
	 * Codice di incorporamento per una sorgente e un riferimento.
	 * Vuoto se la sorgente non è tra quelle attive o il riferimento è vuoto.
	 *
	 * @param string $source Sorgente (youtube, vimeo...).
	 * @param string $ref    ID del video (Vimeo: "ID" o "ID/codice").
	 * @param string $title  Titolo accessibile dell'iframe.
	 */
	public static function embed( string $source, string $ref, string $title = '' ): string {
		$providers = self::providers();
		$enabled   = (array) AEL_Settings::get( 'video_sources', array() );

		if ( '' === $ref || ! isset( $providers[ $source ] ) || ! in_array( $source, $enabled, true ) ) {
			return '';
		}
		$callback = $providers[ $source ]['embed'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return '';
		}
		$html = (string) call_user_func( $callback, $ref, $title );
		return '' === $html ? '' : '<div class="ael-video">' . $html . '</div>';
	}

	/**
	 * Riconosce sorgente e ID da un indirizzo (per il video di anteprima del corso).
	 *
	 * @param string $url Indirizzo incollato.
	 * @return array{0: string, 1: string} Sorgente e ID, o due stringhe vuote.
	 */
	public static function from_url( string $url ): array {
		foreach ( array( 'youtube', 'vimeo' ) as $source ) {
			$ref = AEL_Content::normalize_video_ref( $source, $url );
			if ( '' !== $ref ) {
				return array( $source, $ref );
			}
		}
		return array( '', '' );
	}

	/**
	 * Iframe YouTube (dominio senza cookie di tracciamento).
	 *
	 * @param string $ref   ID di 11 caratteri.
	 * @param string $title Titolo accessibile.
	 */
	public static function embed_youtube( string $ref, string $title ): string {
		if ( ! preg_match( '/^[A-Za-z0-9_-]{11}$/', $ref ) ) {
			return '';
		}
		$src = add_query_arg(
			array(
				'rel'            => 0,
				'modestbranding' => 1,
				'enablejsapi'    => 1,
				'origin'         => rawurlencode( home_url() ),
			),
			'https://www.youtube-nocookie.com/embed/' . $ref
		);
		return self::iframe( $src, $title );
	}

	/**
	 * Iframe Vimeo (con "do not track"). I video non in elenco hanno un codice
	 * dopo l'ID, salvato come "ID/codice".
	 *
	 * @param string $ref   "ID" oppure "ID/codice".
	 * @param string $title Titolo accessibile.
	 */
	public static function embed_vimeo( string $ref, string $title ): string {
		if ( ! preg_match( '~^(\d{6,12})(?:/([a-z0-9]+))?$~i', $ref, $m ) ) {
			return '';
		}
		$args = array( 'dnt' => 1 );
		if ( ! empty( $m[2] ) ) {
			$args['h'] = $m[2];
		}
		return self::iframe( add_query_arg( $args, 'https://player.vimeo.com/video/' . $m[1] ), $title );
	}

	/**
	 * Costruisce l'iframe con attributi sicuri.
	 *
	 * @param string $src   Indirizzo del lettore.
	 * @param string $title Titolo accessibile.
	 */
	private static function iframe( string $src, string $title ): string {
		return sprintf(
			'<iframe src="%1$s" title="%2$s" loading="lazy" allow="accelerometer; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>',
			esc_url( $src ),
			esc_attr( '' !== $title ? $title : __( 'Video', 'academy-elearning' ) )
		);
	}
}
