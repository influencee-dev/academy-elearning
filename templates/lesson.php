<?php
/**
 * Pagina della lezione. Il titolo lo mostra il tema. Per cambiarla copia questo
 * file in `academy-elearning/lesson.php` nel tema.
 *
 * ATTENZIONE: video e materiali arrivano già vuoti se l'utente non ha accesso.
 * Non costruire mai il video da soli in questo template: usa $video_html.
 *
 * Variabili: $lesson, $content, $can, $course, $course_url, $catalog_url,
 * $video_html, $materials, $program, $prev, $next, $is_preview, $actions,
 * $is_visitor, $login_url.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-lesson">
	<?php if ( $course ) : ?>
		<nav class="ael-breadcrumb" aria-label="<?php echo esc_attr__( 'Percorso', 'academy-elearning' ); ?>">
			<a href="<?php echo esc_url( $catalog_url ); ?>"><?php echo esc_html__( 'Catalogo', 'academy-elearning' ); ?></a>
			<span aria-hidden="true">&rsaquo;</span>
			<a href="<?php echo esc_url( $course_url ); ?>"><?php echo esc_html( get_the_title( $course ) ); ?></a>
		</nav>
	<?php endif; ?>

	<div class="ael-layout ael-layout--lesson">
		<div class="ael-main">
			<?php if ( $can ) : ?>
				<?php if ( $is_preview ) : ?>
					<p><span class="ael-badge"><?php echo esc_html__( 'Anteprima gratuita', 'academy-elearning' ); ?></span></p>
				<?php endif; ?>

				<?php if ( '' !== $video_html ) : ?>
					<?php echo $video_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costruito dopo il controllo di accesso in AEL_Video. ?>
				<?php endif; ?>

				<?php if ( '' !== trim( wp_strip_all_tags( $content ) ) ) : ?>
					<div class="ael-lesson-text"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già filtrato da the_content. ?></div>
				<?php endif; ?>

				<?php if ( ! empty( $materials ) ) : ?>
					<section class="ael-section">
						<h2><?php echo esc_html__( 'Materiali', 'academy-elearning' ); ?></h2>
						<ul class="ael-materials">
							<?php foreach ( $materials as $file ) : ?>
								<li>
									<a href="<?php echo esc_url( $file['url'] ); ?>"><?php echo esc_html( $file['title'] ); ?></a>
									<span class="ael-duration"><?php echo esc_html( trim( $file['type'] . ' ' . $file['size'] ) ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php
				/**
				 * Dopo il contenuto di una lezione (solo per chi ha accesso).
				 * Qui si agganciano i moduli futuri: quiz, attestati, community.
				 *
				 * @param int $lesson_id Lezione.
				 * @param int $course_id Corso (0 se non assegnata).
				 */
				do_action( 'ael_after_lesson_content', $lesson->ID, $course ? $course->ID : 0 );
				?>

				<nav class="ael-lesson-nav" aria-label="<?php echo esc_attr__( 'Altre lezioni', 'academy-elearning' ); ?>">
					<?php if ( $prev && $prev['can'] ) : ?>
						<a class="ael-button ael-button--secondary" href="<?php echo esc_url( $prev['url'] ); ?>">&larr; <?php echo esc_html( $prev['title'] ); ?></a>
					<?php endif; ?>
					<?php if ( $next && $next['can'] ) : ?>
						<a class="ael-button ael-button--primary" href="<?php echo esc_url( $next['url'] ); ?>"><?php echo esc_html( $next['title'] ); ?> &rarr;</a>
					<?php endif; ?>
				</nav>
			<?php else : ?>
				<div class="ael-locked">
					<h2><?php echo esc_html__( 'Questa lezione è riservata', 'academy-elearning' ); ?></h2>
					<p><?php echo esc_html__( 'Per vederla serve l\'accesso al corso.', 'academy-elearning' ); ?></p>
					<?php
					echo AEL_Templates::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape nel template.
						'actions',
						array(
							'actions'    => $actions,
							'has_access' => false,
							'is_visitor' => $is_visitor,
							'login_url'  => $login_url,
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $course ) : ?>
			<aside class="ael-side">
				<h2><?php echo esc_html__( 'Programma del corso', 'academy-elearning' ); ?></h2>
				<?php echo AEL_Templates::render( 'program', array( 'program' => $program, 'current' => $lesson->ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape nel template. ?>
			</aside>
		<?php endif; ?>
	</div>
</div>
