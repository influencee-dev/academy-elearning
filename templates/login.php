<?php
/**
 * Pagina di accesso. Per cambiarla copia questo file in
 * `academy-elearning/login.php` nel tema.
 *
 * Variabili: $logged_in, $user, $failed, $form (modulo già pronto e sicuro),
 * $lost_url, $courses_url, $logout_url.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-login-box">
	<?php if ( $logged_in ) : ?>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: nome dell'utente */
					__( 'Sei collegato come %s.', 'academy-elearning' ),
					$user->display_name
				)
			);
			?>
		</p>
		<p class="ael-actions">
			<a class="ael-button ael-button--primary" href="<?php echo esc_url( $courses_url ); ?>"><?php echo esc_html__( 'Vai ai tuoi corsi', 'academy-elearning' ); ?></a>
			<a class="ael-login" href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html__( 'Esci', 'academy-elearning' ); ?></a>
		</p>
		<?php
		/**
		 * Sotto i pulsanti, quando l'utente è già collegato. I moduli (Pannello docente, Docenti
		 * autori) ci aggiungono il collegamento alla propria area.
		 *
		 * @param WP_User $user Utente.
		 */
		do_action( 'ael_login_logged_in', $user );
		?>
	<?php else : ?>
		<?php if ( $failed ) : ?>
			<p class="ael-error" role="alert"><?php echo esc_html__( 'Email, nome utente o password non corretti. Riprova.', 'academy-elearning' ); ?></p>
		<?php endif; ?>

		<?php echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- modulo costruito e protetto da wp_login_form(). ?>

		<p class="ael-login"><a href="<?php echo esc_url( $lost_url ); ?>"><?php echo esc_html__( 'Password dimenticata?', 'academy-elearning' ); ?></a></p>

		<?php
		/**
		 * Sotto il modulo di accesso. Qui si aggancia, per esempio, il link
		 * "Accedi con un link via email" del modulo magic-link.
		 */
		do_action( 'ael_login_form_after' );
		?>
	<?php endif; ?>
</div>
