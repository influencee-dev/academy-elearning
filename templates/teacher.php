<?php
/**
 * Pagina pubblica del docente. Il nome lo mostra il tema. Per cambiarla copia
 * questo file in `academy-elearning/teacher.php` nel tema.
 *
 * Variabili: $teacher, $content, $courses, $cards, $profile (frase, copertina,
 * colore e link: vuoti se le schede personalizzabili sono spente), $photo.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-teacher"<?php echo '' !== $profile['style'] ? ' style="' . esc_attr( $profile['style'] ) . '"' : ''; ?>>
	<?php if ( '' !== $profile['cover_url'] ) : ?>
		<div class="ael-teacher-cover"><img src="<?php echo esc_url( $profile['cover_url'] ); ?>" alt=""></div>
	<?php endif; ?>

	<div class="ael-teacher-head">
		<?php if ( '' !== $photo ) : ?>
			<span class="ael-teacher-photo"><?php echo wp_kses_post( $photo ); ?></span>
		<?php endif; ?>
		<div>
			<?php if ( '' !== $profile['tagline'] ) : ?>
				<p class="ael-teacher-tagline"><?php echo esc_html( $profile['tagline'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $profile['links'] ) ) : ?>
				<ul class="ael-teacher-box-links">
					<?php foreach ( $profile['links'] as $link ) : ?>
						<li><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

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
