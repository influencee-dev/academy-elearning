<?php
/**
 * Pagina di una guida. Per cambiarla copia questo file in `academy-elearning/guide.php` nel tema.
 *
 * Variabili: $content (vuoto se non abbonato), $teaser, $files (title, url, size), $can_view,
 * $back_url, $gate.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-section ael-guide">
	<p class="ael-back"><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php echo esc_html__( 'Tutte le guide', 'academy-elearning' ); ?></a></p>

	<?php if ( ! $can_view ) : ?>
		<?php if ( '' !== $teaser ) : ?>
			<p class="ael-lead"><?php echo esc_html( $teaser ); ?></p>
		<?php endif; ?>
		<?php echo AEL_Templates::render( 'section-gate', array( 'gate' => $gate ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template già con escape. ?>
	<?php else : ?>
		<div class="ael-guide__text"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già filtrato da the_content. ?></div>

		<?php if ( ! empty( $files ) ) : ?>
			<div class="ael-files">
				<h2><?php echo esc_html__( 'Da scaricare', 'academy-elearning' ); ?></h2>
				<ul>
					<?php foreach ( $files as $file ) : ?>
						<li>
							<a class="ael-button ael-button--secondary" href="<?php echo esc_url( $file['url'] ); ?>"><?php echo esc_html( $file['title'] ); ?></a>
							<?php if ( '' !== $file['size'] ) : ?>
								<span class="ael-meta"><?php echo esc_html( $file['size'] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
