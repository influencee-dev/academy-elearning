<?php
/**
 * Pagina "Abbonati". Per cambiarla copia questo file in `academy-elearning/subscribe.php` nel tema.
 *
 * Variabili: $ready, $price_text, $logged_in, $user, $subscribed, $consent_text, $error,
 * $canceled, $login_url, $courses_url, $catalog_url, $messages.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-subscribe ael-login-box">
	<?php if ( '' !== $price_text ) : ?>
		<p class="ael-price"><?php echo esc_html( $price_text ); ?></p>
	<?php endif; ?>
	<p><?php echo esc_html__( 'Con l\'abbonamento accedi a tutto il catalogo. Puoi disdire quando vuoi: l\'accesso resta fino alla fine del periodo già pagato.', 'academy-elearning' ); ?></p>

	<?php if ( $canceled ) : ?>
		<p class="ael-note"><?php echo esc_html__( 'Pagamento annullato: non ti è stato addebitato nulla.', 'academy-elearning' ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $error && isset( $messages[ $error ] ) ) : ?>
		<p class="ael-error" role="alert"><?php echo esc_html( $messages[ $error ] ); ?></p>
	<?php endif; ?>

	<?php if ( ! $ready ) : ?>
		<p class="ael-note"><?php echo esc_html__( 'L\'abbonamento non è ancora disponibile.', 'academy-elearning' ); ?></p>

	<?php elseif ( $subscribed ) : ?>
		<p><?php echo esc_html__( 'Hai già un abbonamento attivo.', 'academy-elearning' ); ?></p>
		<p><a class="ael-button ael-button--primary" href="<?php echo esc_url( $courses_url ); ?>"><?php echo esc_html__( 'Vai ai tuoi corsi', 'academy-elearning' ); ?></a></p>

	<?php elseif ( ! $logged_in ) : ?>
		<h3><?php echo esc_html__( 'Crea il tuo account', 'academy-elearning' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ael_sub_register">
			<?php wp_nonce_field( 'ael_sub_register' ); ?>
			<p style="position:absolute;left:-9999px" aria-hidden="true"><label>Sito web <input type="text" name="ael_website" tabindex="-1" autocomplete="off"></label></p>
			<p>
				<label for="ael-sub-email"><?php echo esc_html__( 'Email', 'academy-elearning' ); ?></label>
				<input type="email" id="ael-sub-email" name="email" required autocomplete="email">
			</p>
			<p>
				<label for="ael-sub-password"><?php echo esc_html__( 'Password (almeno 8 caratteri)', 'academy-elearning' ); ?></label>
				<input type="password" id="ael-sub-password" name="password" required minlength="8" autocomplete="new-password">
			</p>
			<p><button type="submit" class="ael-button ael-button--primary"><?php echo esc_html__( 'Crea account e continua', 'academy-elearning' ); ?></button></p>
		</form>
		<p class="ael-login"><?php echo esc_html__( 'Hai già un account?', 'academy-elearning' ); ?> <a href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html__( 'Accedi', 'academy-elearning' ); ?></a></p>

	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ael_sub_start">
			<?php wp_nonce_field( 'ael_sub_start' ); ?>
			<p>
				<label>
					<input type="checkbox" name="ael_withdrawal_consent" value="1" required>
					<?php echo esc_html( $consent_text ); ?>
				</label>
			</p>
			<p><button type="submit" class="ael-button ael-button--primary"><?php echo esc_html__( 'Vai al pagamento', 'academy-elearning' ); ?></button></p>
		</form>
		<p class="ael-note"><?php echo esc_html__( 'Il pagamento si completa su Stripe, in modo sicuro.', 'academy-elearning' ); ?></p>
	<?php endif; ?>
</div>
