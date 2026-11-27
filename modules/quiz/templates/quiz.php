<?php
/**
 * Pagina di un quiz. Per cambiarla copia questo file in `academy-elearning/quiz.php` nel tema.
 *
 * Variabili: $quiz_id, $intro, $can_take, $gate, $back_url, $questions (q, options, correct, explain),
 * $pass, $attempts, $result (score, total, percent, passed, answers) o null, $error.
 *
 * Le risposte giuste sono in $questions solo per chi ha l'abbonamento: nel modulo non si stampano
 * mai; compaiono solo nel risultato di un tentativo già inviato.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

$messages = array(
	'incompleto' => __( 'Rispondi a tutte le domande prima di inviare.', 'academy-elearning' ),
	'vuoto'      => __( 'Questo quiz non ha ancora domande.', 'academy-elearning' ),
);
?>
<div class="ael ael-section ael-quiz">
	<p class="ael-back"><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php echo esc_html__( 'Tutti i quiz', 'academy-elearning' ); ?></a></p>

	<?php if ( '' !== $intro ) : ?>
		<p class="ael-lead"><?php echo esc_html( $intro ); ?></p>
	<?php endif; ?>

	<?php if ( ! $can_take ) : ?>
		<?php echo AEL_Templates::render( 'section-gate', array( 'gate' => $gate ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template già con escape. ?>
	<?php else : ?>

		<?php if ( ! empty( $error ) && isset( $messages[ $error ] ) ) : ?>
			<p class="ael-error" role="alert"><?php echo esc_html( $messages[ $error ] ); ?></p>
		<?php endif; ?>

		<?php if ( $result ) : ?>
			<div id="risultato" class="ael-gate">
				<p class="ael-score <?php echo $result['passed'] ? 'is-pass' : ''; ?>">
					<?php
					/* translators: 1: risposte giuste, 2: domande, 3: percentuale. */
					echo esc_html( sprintf( __( '%1$d su %2$d (%3$d%%)', 'academy-elearning' ), $result['score'], $result['total'], $result['percent'] ) );
					?>
				</p>
				<p>
					<?php
					echo $result['passed']
						? esc_html__( 'Quiz superato. Bel lavoro!', 'academy-elearning' )
						/* translators: %d: percentuale minima. */
						: esc_html( sprintf( __( 'Non ancora: per superarlo serve almeno il %d%%. Puoi riprovare quando vuoi.', 'academy-elearning' ), $pass ) );
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $questions ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ael_quiz_submit">
				<input type="hidden" name="quiz_id" value="<?php echo esc_attr( (string) $quiz_id ); ?>">
				<?php wp_nonce_field( 'ael_quiz_submit_' . $quiz_id ); ?>

				<?php foreach ( $questions as $index => $question ) : ?>
					<?php
					$state  = '';
					$chosen = -1;
					if ( $result && isset( $result['answers'][ $index ] ) ) {
						$chosen = (int) $result['answers'][ $index ]['chosen'];
						$state  = ! empty( $result['answers'][ $index ]['right'] ) ? 'is-right' : 'is-wrong';
					}
					?>
					<fieldset class="ael-quiz-question <?php echo esc_attr( $state ); ?>">
						<legend><?php echo esc_html( (string) ( $index + 1 ) . '. ' . $question['q'] ); ?></legend>
						<?php foreach ( $question['options'] as $opt_index => $option ) : ?>
							<label>
								<input type="radio" name="ael_answer[<?php echo esc_attr( (string) $index ); ?>]" value="<?php echo esc_attr( (string) $opt_index ); ?>" <?php checked( $chosen, $opt_index ); ?> required>
								<?php echo esc_html( $option ); ?>
								<?php if ( $result && $opt_index === (int) $question['correct'] ) : ?>
									<strong>&#10003; <?php echo esc_html__( 'giusta', 'academy-elearning' ); ?></strong>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
						<?php if ( $result && '' !== $question['explain'] ) : ?>
							<p class="ael-quiz-explain"><?php echo esc_html( $question['explain'] ); ?></p>
						<?php endif; ?>
					</fieldset>
				<?php endforeach; ?>

				<p><button type="submit" class="ael-button ael-button--primary">
					<?php echo $result ? esc_html__( 'Riprova il quiz', 'academy-elearning' ) : esc_html__( 'Invia le risposte', 'academy-elearning' ); ?>
				</button></p>
			</form>
		<?php else : ?>
			<p class="ael-note"><?php echo esc_html__( 'Questo quiz non ha ancora domande.', 'academy-elearning' ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $attempts ) ) : ?>
			<h2><?php echo esc_html__( 'I tuoi tentativi', 'academy-elearning' ); ?></h2>
			<table class="ael-attempts">
				<thead><tr><th><?php echo esc_html__( 'Data', 'academy-elearning' ); ?></th><th><?php echo esc_html__( 'Punteggio', 'academy-elearning' ); ?></th><th><?php echo esc_html__( 'Esito', 'academy-elearning' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $attempts as $row ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' H:i', (int) strtotime( (string) $row['created_at'] . ' UTC' ) ) ); ?></td>
						<td><?php echo esc_html( (int) $row['score'] . '/' . (int) $row['total'] . ' (' . (int) $row['percent'] . '%)' ); ?></td>
						<td><?php echo ! empty( $row['passed'] ) ? esc_html__( 'Superato', 'academy-elearning' ) : esc_html__( 'Da ripetere', 'academy-elearning' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	<?php endif; ?>
</div>
