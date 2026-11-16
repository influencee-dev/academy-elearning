<?php
/**
 * Pagine di sistema (Catalogo, Accedi, I miei corsi).
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le pagine che contengono gli shortcode del plugin. Le crea una migrazione e
 * il loro ID resta salvato in un'option, così il cliente può rinominarle o
 * cambiarne l'indirizzo senza rompere i collegamenti.
 */
final class AEL_Pages {

	/**
	 * Pagine gestite: chiave => option, titolo, indirizzo e contenuto.
	 * I titoli sono nella lingua di base (italiano): la migrazione può partire
	 * prima del caricamento delle traduzioni.
	 *
	 * @return array<string, array{option: string, title: string, slug: string, content: string}>
	 */
	public static function definitions(): array {
		return array(
			'catalog'    => array(
				'option'  => 'ael_page_catalog',
				'title'   => 'Catalogo',
				'slug'    => 'catalogo',
				'content' => '[ael_catalog]',
			),
			'login'      => array(
				'option'  => 'ael_page_login',
				'title'   => 'Accedi',
				'slug'    => 'accedi',
				'content' => '[ael_login]',
			),
			'my_courses' => array(
				'option'  => 'ael_page_my_courses',
				'title'   => 'I miei corsi',
				'slug'    => 'i-miei-corsi',
				'content' => '[ael_my_courses]',
			),
		);
	}

	/**
	 * ID della pagina pubblicata, o 0.
	 *
	 * @param string $key Chiave della pagina.
	 */
	public static function id( string $key ): int {
		$defs = self::definitions();
		if ( ! isset( $defs[ $key ] ) ) {
			return 0;
		}
		$page_id = (int) get_option( $defs[ $key ]['option'] );
		return ( $page_id && 'page' === get_post_type( $page_id ) && 'publish' === get_post_status( $page_id ) ) ? $page_id : 0;
	}

	/**
	 * Indirizzo della pagina, oppure il valore di riserva se non esiste.
	 *
	 * @param string $key      Chiave della pagina.
	 * @param string $fallback Indirizzo di riserva.
	 */
	public static function url( string $key, string $fallback = '' ): string {
		$page_id = self::id( $key );
		return $page_id ? (string) get_permalink( $page_id ) : $fallback;
	}

	/**
	 * Crea la pagina se manca. Non ne crea una seconda se il cliente l'ha
	 * rinominata, e non la ricrea se è nel cestino o in bozza.
	 *
	 * @param string $key Chiave della pagina.
	 */
	public static function ensure( string $key ): bool {
		$defs = self::definitions();
		if ( ! isset( $defs[ $key ] ) ) {
			return false;
		}
		$def     = $defs[ $key ];
		$page_id = (int) get_option( $def['option'] );
		if ( $page_id && 'page' === get_post_type( $page_id ) ) {
			return true;
		}
		$new_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $def['title'],
				'post_name'    => $def['slug'],
				'post_content' => $def['content'],
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			return false;
		}
		update_option( $def['option'], (int) $new_id );
		return true;
	}

	/**
	 * Crea tutte le pagine mancanti.
	 */
	public static function ensure_all(): bool {
		$ok = true;
		foreach ( array_keys( self::definitions() ) as $key ) {
			$ok = self::ensure( $key ) && $ok;
		}
		return $ok;
	}

	/**
	 * Migrazione: crea le pagine mancanti. Se WordPress non ha ancora finito di
	 * avviarsi (init non ancora passato) rimanda la creazione a init.
	 */
	public static function setup(): bool {
		if ( did_action( 'init' ) ) {
			return self::ensure_all();
		}
		add_action( 'init', array( __CLASS__, 'ensure_all' ), 98 );
		return true;
	}
}
