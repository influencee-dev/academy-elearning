<?php
/**
 * Pagina di una raccolta di corsi. Il titolo lo mostra il tema. Per cambiarla
 * copia questo file in `academy-elearning/collection.php` nel tema.
 *
 * Variabili: $collection, $content, $courses, $cards.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-collection">
	<?php if ( '' !== trim( wp_strip_all_tags( $content ) ) ) : ?>
		<div class="ael-description"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già filtrato da the_content. ?></div>
	<?php endif; ?>

	<?php if ( empty( $cards ) ) : ?>
		<p class="ael-empty"><?php echo esc_html__( 'Nessun corso in questa raccolta per ora.', 'academy-elearning' ); ?></p>
	<?php else : ?>
		<div class="ael-grid">
			<?php foreach ( $cards as $card ) : ?>
				<?php echo AEL_Templates::render( 'course-card', array( 'card' => $card ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape nel template. ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
