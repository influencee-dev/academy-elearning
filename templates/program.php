<?php
/**
 * Programma del corso: moduli e lezioni. Le lezioni non accessibili sono
 * elencate ma senza collegamento. Per cambiarlo copia questo file in
 * `academy-elearning/program.php` nel tema.
 *
 * Variabili: $program (da AEL_Catalog::program), $current (ID lezione aperta, 0 se nessuna).
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael-program">
	<?php if ( empty( $program['modules'] ) ) : ?>
		<p class="ael-note"><?php echo esc_html__( 'Il programma è in preparazione.', 'academy-elearning' ); ?></p>
	<?php endif; ?>

	<?php foreach ( $program['modules'] as $module ) : ?>
		<section class="ael-module">
			<h3 class="ael-module-title"><?php echo esc_html( $module['title'] ); ?></h3>
			<ol class="ael-lessons">
				<?php foreach ( $module['lessons'] as $lesson ) : ?>
					<?php
					$classes = 'ael-lesson-item';
					if ( ! $lesson['can'] ) {
						$classes .= ' is-locked';
					}
					if ( (int) $lesson['id'] === (int) $current ) {
						$classes .= ' is-current';
					}
					if ( ! empty( $lesson['completed'] ) ) {
						$classes .= ' is-done';
					}
					?>
					<li class="<?php echo esc_attr( $classes ); ?>">
						<?php if ( $lesson['can'] ) : ?>
							<a class="ael-lesson-link" href="<?php echo esc_url( $lesson['url'] ); ?>"<?php echo (int) $lesson['id'] === (int) $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $lesson['title'] ); ?></a>
						<?php else : ?>
							<span class="ael-lesson-link"><?php echo esc_html( $lesson['title'] ); ?></span>
							<span class="ael-lock"><?php echo esc_html__( 'Riservata', 'academy-elearning' ); ?></span>
						<?php endif; ?>
						<span class="ael-done"><?php echo esc_html__( 'Completata', 'academy-elearning' ); ?></span>
						<?php if ( $lesson['is_preview'] ) : ?>
							<span class="ael-badge"><?php echo esc_html__( 'Anteprima gratuita', 'academy-elearning' ); ?></span>
						<?php endif; ?>
						<?php if ( $lesson['minutes'] > 0 ) : ?>
							<span class="ael-duration"><?php echo esc_html( AEL_Catalog::format_minutes( (int) $lesson['minutes'] ) ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endforeach; ?>
</div>
