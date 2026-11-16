<?php
/**
 * Caricamento dei template, sovrascrivibili dal tema.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ogni academy ha il suo tema: per cambiare la grafica basta copiare un file di
 * `templates/` in `academy-elearning/` nella cartella del tema (o del tema figlio)
 * e modificarlo. Il plugin non si tocca mai.
 */
final class AEL_Templates {

	/**
	 * Trova il file di un template: prima nel tema, poi nel plugin.
	 *
	 * @param string $name Nome senza estensione, per esempio "course".
	 * @return string Percorso del file, o stringa vuota se non esiste.
	 */
	public static function locate( string $name ): string {
		$name = preg_replace( '/[^a-z0-9_-]/i', '', $name );
		if ( '' === $name ) {
			return '';
		}
		$file  = 'academy-elearning/' . $name . '.php';
		$theme = locate_template( array( $file ), false, false );
		$path  = '' !== $theme ? $theme : AEL_PATH . 'templates/' . $name . '.php';

		/**
		 * Permette di cambiare il file usato per un template.
		 *
		 * @param string $path Percorso scelto.
		 * @param string $name Nome del template.
		 */
		$path = (string) apply_filters( 'ael_template_path', $path, $name );
		return is_readable( $path ) ? $path : '';
	}

	/**
	 * Disegna un template e restituisce l'HTML.
	 *
	 * @param string               $name Nome del template.
	 * @param array<string, mixed> $vars Variabili disponibili nel template.
	 */
	public static function render( string $name, array $vars = array() ): string {
		$path = self::locate( $name );
		if ( '' === $path ) {
			return '';
		}
		ob_start();
		( static function ( string $__path, array $__vars ): void {
			extract( $__vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include $__path;
		} )( $path, $vars );
		return (string) ob_get_clean();
	}
}
