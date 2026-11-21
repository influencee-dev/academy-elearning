<?php
/**
 * Pagina "Iscriviti". Per cambiarla copia questo file in `academy-elearning/register.php` nel tema.
 *
 * Variabili: $logged_in, $errors (per campo), $old (nome, cognome, email già scritti),
 * $nonce_field, $redirect_to, $login_url, $courses_url, $privacy_url.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-login-box ael-register-box">
	<?php if ( $logged_in ) : ?>
		<p><?php echo esc_html__( 'Hai già un account e sei collegato.', 'academy-elearning' ); ?></p>
		<p class="ael-actions"><a class="ael-button ael-button--primary" href="<?php echo esc_url( $courses_url ); ?>"><?php echo esc_html__( 'Vai ai tuoi corsi', 'academy-elearning' ); ?></a></p>
	<?php else : ?>
		<?php if ( ! empty( $errors['general'] ) ) : ?>
			<p class="ael-error" role="alert"><?php echo esc_html( $errors['general'] ); ?></p>
		<?php endif; ?>

		<form class="ael-register-form" method="post" action="" novalidate>
			<?php echo $nonce_field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- campo creato da wp_nonce_field(). ?>
			<?php if ( '' !== $redirect_to ) : ?>
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>">
			<?php endif; ?>

			<p class="ael-field">
				<label for="ael-first-name"><?php echo esc_html__( 'Nome', 'academy-elearning' ); ?></label>
				<input type="text" id="ael-first-name" name="first_name" value="<?php echo esc_attr( $old['first_name'] ); ?>" autocomplete="given-name" required>
				<?php if ( ! empty( $errors['first_name'] ) ) : ?><span class="ael-error-text"><?php echo esc_html( $errors['first_name'] ); ?></span><?php endif; ?>
			</p>
			<p class="ael-field">
				<label for="ael-last-name"><?php echo esc_html__( 'Cognome', 'academy-elearning' ); ?></label>
				<input type="text" id="ael-last-name" name="last_name" value="<?php echo esc_attr( $old['last_name'] ); ?>" autocomplete="family-name">
			</p>
			<p class="ael-field">
				<label for="ael-email"><?php echo esc_html__( 'Email', 'academy-elearning' ); ?></label>
				<input type="email" id="ael-email" name="email" value="<?php echo esc_attr( $old['email'] ); ?>" autocomplete="email" required>
				<?php if ( ! empty( $errors['email'] ) ) : ?><span class="ael-error-text"><?php echo esc_html( $errors['email'] ); ?></span><?php endif; ?>
			</p>
			<p class="ael-field">
				<label for="ael-password"><?php echo esc_html__( 'Password (almeno 8 caratteri)', 'academy-elearning' ); ?></label>
				<input type="password" id="ael-password" name="password" autocomplete="new-password" minlength="8" required>
				<?php if ( ! empty( $errors['password'] ) ) : ?><span class="ael-error-text"><?php echo esc_html( $errors['password'] ); ?></span><?php endif; ?>
			</p>

			<?php // Campo trappola per i robot: le persone non lo vedono. ?>
			<p class="ael-hp" aria-hidden="true" style="position:absolute;left:-9999px;height:0;overflow:hidden">
				<label for="ael-website">Website</label>
				<input type="text" id="ael-website" name="ael_website" tabindex="-1" autocomplete="off">
			</p>

			<p class="ael-field ael-consent">
				<label>
					<input type="checkbox" name="consent" value="1" required>
					<?php
					if ( '' !== $privacy_url ) {
						printf(
							/* translators: %s: collegamento all'informativa privacy */
							esc_html__( 'Ho letto e accetto %s.', 'academy-elearning' ),
							'<a href="' . esc_url( $privacy_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'l\'informativa sulla privacy', 'academy-elearning' ) . '</a>'
						);
					} else {
						echo esc_html__( 'Ho letto e accetto l\'informativa sulla privacy.', 'academy-elearning' );
					}
					?>
				</label>
				<?php if ( ! empty( $errors['consent'] ) ) : ?><span class="ael-error-text"><?php echo esc_html( $errors['consent'] ); ?></span><?php endif; ?>
			</p>

			<p class="ael-actions"><button type="submit" class="ael-button ael-button--primary"><?php echo esc_html__( 'Crea il mio account', 'academy-elearning' ); ?></button></p>
		</form>

		<p class="ael-login"><?php echo esc_html__( 'Hai già un account?', 'academy-elearning' ); ?> <a href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html__( 'Accedi', 'academy-elearning' ); ?></a></p>
	<?php endif; ?>
</div>
