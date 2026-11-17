<?php
/**
 * Riquadro dell'abbonamento sopra "I miei corsi". Per cambiarlo copia questo file in
 * `academy-elearning/subscription-box.php` nel tema.
 *
 * Variabili: $state (id, status, period_end, cancel_at_end, customer), $active, $ready,
 * $just_paid, $error, $messages, $price_text, $page_url.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

$end = '' !== $state['period_end'] ? wp_date( 'd/m/Y', (int) strtotime( $state['period_end'] . ' UTC' ) ) : '';
?>
<div class="ael-sub-box">
	<?php if ( $just_paid ) : ?>
		<p class="ael-note" role="status"><?php echo esc_html__( 'Grazie! Il tuo abbonamento è attivo.', 'academy-elearning' ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $error && isset( $messages[ $error ] ) ) : ?>
		<p class="ael-error" role="alert"><?php echo esc_html( $messages[ $error ] ); ?></p>
	<?php endif; ?>

	<?php if ( $active ) : ?>
		<p>
			<strong><?php echo esc_html__( 'Abbonamento', 'academy-elearning' ); ?>:</strong>
			<?php
			if ( 'past_due' === $state['status'] ) {
				echo esc_html(
					sprintf(
						/* translators: %s: data */
						__( 'pagamento non riuscito. Hai accesso fino al %s: aggiorna la carta per continuare.', 'academy-elearning' ),
						$end
					)
				);
			} elseif ( $state['cancel_at_end'] || 'canceled' === $state['status'] ) {
				echo esc_html(
					sprintf(
						/* translators: %s: data */
						__( 'disdetto. Hai accesso a tutto il catalogo fino al %s.', 'academy-elearning' ),
						$end
					)
				);
			} else {
				echo esc_html(
					sprintf(
						/* translators: %s: data */
						__( 'attivo. Si rinnova il %s.', 'academy-elearning' ),
						$end
					)
				);
			}
			?>
		</p>
	<?php elseif ( $ready ) : ?>
		<p>
			<?php echo esc_html__( 'Con l\'abbonamento accedi a tutto il catalogo.', 'academy-elearning' ); ?>
			<a href="<?php echo esc_url( $page_url ); ?>"><?php echo esc_html( '' !== $price_text ? sprintf( /* translators: %s: prezzo */ __( 'Abbonati a %s', 'academy-elearning' ), $price_text ) : __( 'Abbonati', 'academy-elearning' ) ); ?></a>
		</p>
	<?php endif; ?>

	<?php if ( $ready && '' !== $state['customer'] ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ael_sub_portal">
			<?php wp_nonce_field( 'ael_sub_portal' ); ?>
			<button type="submit" class="ael-button ael-button--secondary"><?php echo esc_html__( 'Gestisci abbonamento', 'academy-elearning' ); ?></button>
		</form>
	<?php endif; ?>
</div>
