<?php
/**
 * Elenco dei quiz. Per cambiarlo copia questo file in `academy-elearning/quizzes.php` nel tema.
 *
 * Variabili: $items (title, excerpt, url, questions, pass, course, best), $can_take, $gate.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-section ael-quizzes">
	<?php if ( ! $can_take ) : ?>
		<?php echo AEL_Templates::render( 'section-gate', array( 'gate' => $gate ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template già con escape. ?>
	<?php endif; ?>

	<?php if ( empty( $items ) ) : ?>
		<p class="ael-note"><?php echo esc_html__( 'Non ci sono ancora quiz.', 'academy-elearning' ); ?></p>
	<?php else : ?>
		<ul class="ael-section-grid">
			<?php foreach ( $items as $item ) : ?>
				<li class="ael-section-card">
					<?php if ( '' !== $item['course'] ) : ?>
						<span class="ael-chip"><?php echo esc_html( $item['course'] ); ?></span>
					<?php endif; ?>
					<h3><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>
					<?php if ( '' !== $item['excerpt'] ) : ?>
						<p><?php echo esc_html( $item['excerpt'] ); ?></p>
					<?php endif; ?>
					<p class="ael-meta">
						<?php
						/* translators: %d: numero di domande. */
						echo esc_html( sprintf( _n( '%d domanda', '%d domande', (int) $item['questions'], 'academy-elearning' ), (int) $item['questions'] ) );
						?>
						·
						<?php
						/* translators: %d: percentuale minima per superare il quiz. */
						echo esc_html( sprintf( __( 'si supera con il %d%%', 'academy-elearning' ), (int) $item['pass'] ) );
						?>
						<?php if ( ! $can_take ) : ?>
							· <span class="ael-lock" aria-hidden="true"></span><?php echo esc_html__( 'Riservato', 'academy-elearning' ); ?>
						<?php elseif ( null !== $item['best'] ) : ?>
							·
							<?php
							/* translators: %d: miglior punteggio in percentuale. */
							echo esc_html( sprintf( __( 'il tuo migliore: %d%%', 'academy-elearning' ), (int) $item['best'] ) );
							?>
						<?php endif; ?>
					</p>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
