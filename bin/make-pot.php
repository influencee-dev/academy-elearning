<?php
/**
 * Genera languages/academy-elearning.pot leggendo tutti i testi traducibili del plugin.
 *
 * Uso (dalla cartella del plugin):
 *   php bin/make-pot.php
 *
 * La lingua di base del plugin è l'italiano: il file .pot serve a chi vuole tradurre
 * il plugin in un'altra lingua (con Poedit o Loco Translate).
 *
 * @package AcademyElearning
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 'Solo da riga di comando.' );
}

$root    = dirname( __DIR__ );
$domain  = 'academy-elearning';
$skip    = array( '.git', 'docs', 'bin', 'languages', 'node_modules', 'vendor' );
// Funzione => posizione degli argomenti: s = testo singolare, p = plurale, c = contesto.
$funcs   = array(
	'__'           => array( 's' ),
	'_e'           => array( 's' ),
	'esc_html__'   => array( 's' ),
	'esc_html_e'   => array( 's' ),
	'esc_attr__'   => array( 's' ),
	'esc_attr_e'   => array( 's' ),
	'_n'           => array( 's', 'p' ),
	'_x'           => array( 's', 'c' ),
	'esc_html_x'   => array( 's', 'c' ),
	'esc_attr_x'   => array( 's', 'c' ),
	'_nx'          => array( 's', 'p', 'c' ),
);
$entries = array();

$iterator = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		static function ( $current ) use ( $skip ) {
			return ! in_array( $current->getFilename(), $skip, true );
		}
	)
);

foreach ( $iterator as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}
	$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
	$tokens   = token_get_all( (string) file_get_contents( $file->getPathname() ) );
	$count    = count( $tokens );
	$comment  = '';

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];
		if ( is_array( $token ) && T_COMMENT === $token[0] && false !== stripos( $token[1], 'translators:' ) ) {
			$comment = trim( preg_replace( '#^/\*+|\*+/$|^//\s*#', '', $token[1] ) );
			continue;
		}
		if ( ! is_array( $token ) || T_STRING !== $token[0] || ! isset( $funcs[ $token[1] ] ) ) {
			continue;
		}
		// Deve essere una chiamata: nome seguito da "(".
		$j = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			++$j;
		}
		if ( ! isset( $tokens[ $j ] ) || '(' !== $tokens[ $j ] ) {
			continue;
		}
		// Si leggono gli argomenti di primo livello fino alla parentesi di chiusura.
		$args  = array();
		$depth = 1;
		$arg   = array();
		for ( $k = $j + 1; $k < $count && $depth > 0; $k++ ) {
			$t = $tokens[ $k ];
			if ( '(' === $t || '[' === $t ) {
				++$depth;
			} elseif ( ')' === $t || ']' === $t ) {
				--$depth;
				if ( 0 === $depth ) {
					break;
				}
			} elseif ( ',' === $t && 1 === $depth ) {
				$args[] = $arg;
				$arg    = array();
				continue;
			}
			$arg[] = $t;
		}
		$args[] = $arg;

		$values = array();
		foreach ( $args as $parts ) {
			$text = '';
			$ok   = true;
			foreach ( $parts as $p ) {
				if ( is_array( $p ) && T_WHITESPACE === $p[0] ) {
					continue;
				}
				if ( is_array( $p ) && T_CONSTANT_ENCAPSED_STRING === $p[0] ) {
					$raw   = $p[1];
					$quote = $raw[0];
					$body  = substr( $raw, 1, -1 );
					$text .= "'" === $quote ? str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body ) : stripcslashes( $body );
				} elseif ( '.' === $p ) {
					continue;
				} else {
					$ok = false;
				}
			}
			$values[] = $ok ? $text : null;
		}

		$positions = $funcs[ $tokens[ $i ][1] ];
		$msgid     = null;
		$plural    = null;
		$context   = null;
		foreach ( $positions as $index => $role ) {
			$value = $values[ $index ] ?? null;
			if ( 's' === $role ) {
				$msgid = $value;
			} elseif ( 'p' === $role ) {
				$plural = $value;
			} elseif ( 'c' === $role ) {
				$context = $value;
			}
		}
		// Il dominio è l'ultimo argomento: se non è il nostro, si salta.
		$last = $values[ count( $values ) - 1 ] ?? null;
		if ( null === $msgid || '' === $msgid || $domain !== $last ) {
			continue;
		}

		$key = ( $context ?? '' ) . "\x04" . $msgid . "\x00" . ( $plural ?? '' );
		if ( ! isset( $entries[ $key ] ) ) {
			$entries[ $key ] = array(
				'msgid'    => $msgid,
				'plural'   => $plural,
				'context'  => $context,
				'comments' => array(),
				'refs'     => array(),
			);
		}
		if ( '' !== $comment ) {
			$entries[ $key ]['comments'][ $comment ] = true;
			$comment                                 = '';
		}
		$entries[ $key ]['refs'][ $relative . ':' . $tokens[ $i ][2] ] = true;
	}
}

/**
 * Testo in formato .po.
 *
 * @param string $text Testo.
 */
function ael_po_quote( string $text ): string {
	$text = str_replace( array( '\\', '"', "\n", "\t" ), array( '\\\\', '\\"', '\\n', '\\t' ), $text );
	return '"' . $text . '"';
}

ksort( $entries );
$version = '1.1.0';
if ( preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', (string) file_get_contents( $root . '/academy-elearning.php' ), $m ) ) {
	$version = $m[1];
}

$out  = "# Testi traducibili di Academy E-learning.\n";
$out .= "# Lingua di base: italiano. Generato da bin/make-pot.php.\n";
$out .= "msgid \"\"\nmsgstr \"\"\n";
$out .= '"Project-Id-Version: Academy E-learning ' . $version . "\\n\"\n";
$out .= "\"Report-Msgid-Bugs-To: \\n\"\n";
$out .= "\"MIME-Version: 1.0\\n\"\n";
$out .= "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
$out .= "\"Content-Transfer-Encoding: 8bit\\n\"\n";
$out .= "\"Language: it_IT\\n\"\n";
$out .= "\"Plural-Forms: nplurals=2; plural=(n != 1);\\n\"\n";
$out .= "\"X-Domain: academy-elearning\\n\"\n\n";

foreach ( $entries as $entry ) {
	foreach ( array_keys( $entry['comments'] ) as $c ) {
		$out .= '#. ' . str_replace( "\n", ' ', $c ) . "\n";
	}
	foreach ( array_keys( $entry['refs'] ) as $ref ) {
		$out .= '#: ' . $ref . "\n";
	}
	if ( null !== $entry['context'] ) {
		$out .= 'msgctxt ' . ael_po_quote( $entry['context'] ) . "\n";
	}
	$out .= 'msgid ' . ael_po_quote( $entry['msgid'] ) . "\n";
	if ( null !== $entry['plural'] ) {
		$out .= 'msgid_plural ' . ael_po_quote( $entry['plural'] ) . "\n";
		$out .= "msgstr[0] \"\"\nmsgstr[1] \"\"\n\n";
	} else {
		$out .= "msgstr \"\"\n\n";
	}
}

if ( ! is_dir( $root . '/languages' ) ) {
	mkdir( $root . '/languages', 0755, true );
}
file_put_contents( $root . '/languages/academy-elearning.pot', $out );
echo count( $entries ) . " testi traducibili scritti in languages/academy-elearning.pot\n";
