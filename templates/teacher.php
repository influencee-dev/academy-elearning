<?php
/**
 * Pagina pubblica del docente. Il nome lo mostra il tema. Per cambiarla copia
 * questo file in `academy-elearning/teacher.php` nel tema.
 *
 * Variabili: $teacher, $content, $courses, $cards.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-teacher">
	<?php if ( '' !== trim( wp_strip_all_tags( $content ) ) ) : ?>
		<div class="ael-description"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già filtrato da the_content. ?></div>
	<?php endif; ?>

	<section class="ael-section">
		<h2><?php echo esc_html__( 'I suoi corsi', 'academy-elearning' ); ?></h2>
		<?php if ( empty( $cards ) ) : ?>
			<p class="ael-empty"><?php echo esc_html__( 'Nessun corso pubblicato per ora.', 'academy-elearning' ); ?></p>
		<?php else : ?>
			<div class="ael-grid">
				<?php foreach ( $cards as $card ) : ?>
					<?php echo AEL_Templates::render( 'course-card', array( 'card' => $card ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape nel template. ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
</div>
