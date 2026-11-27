<?php
/**
 * Riquadro "sezione riservata agli abbonati" (guide, quiz, chat). Per cambiarlo copia questo
 * file in `academy-elearning/section-gate.php` nel tema.
 *
 * Variabili: $gate (logged_in, subscribe_url, login_url, section).
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael-gate" role="note">
	<p class="ael-gate__title"><?php echo esc_html__( 'Riservato a chi ha l\'abbonamento', 'academy-elearning' ); ?></p>
	<p>
		<?php
		/* translators: %s: nome della sezione, per esempio "Le guide". */
		echo esc_html( sprintf( __( '%s sono incluse nell\'abbonamento: con un solo abbonamento hai corsi, guide, quiz e chat con i formatori.', 'academy-elearning' ), (string) $gate['section'] ) );
		?>
	</p>
	<p class="ael-gate__actions">
		<a class="ael-button ael-button--primary" href="<?php echo esc_url( (string) $gate['subscribe_url'] ); ?>"><?php echo esc_html__( 'Abbonati', 'academy-elearning' ); ?></a>
		<?php if ( empty( $gate['logged_in'] ) ) : ?>
			<a class="ael-button ael-button--secondary" href="<?php echo esc_url( (string) $gate['login_url'] ); ?>"><?php echo esc_html__( 'Accedi', 'academy-elearning' ); ?></a>
		<?php endif; ?>
	</p>
</div>
