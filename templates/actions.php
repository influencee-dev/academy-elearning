<?php
/**
 * Pulsanti della scheda corso. Per cambiarli copia questo file in
 * `academy-elearning/actions.php` nel tema.
 *
 * Variabili: $actions, $has_access, $is_visitor, $login_url.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael-actions">
	<?php foreach ( $actions as $action ) : ?>
		<a class="ael-button ael-button--<?php echo esc_attr( $action['type'] ); ?>" href="<?php echo esc_url( $action['url'] ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
	<?php endforeach; ?>

	<?php if ( empty( $actions ) && $has_access ) : ?>
		<p class="ael-note"><?php echo esc_html__( 'Il programma del corso è in preparazione.', 'academy-elearning' ); ?></p>
	<?php elseif ( empty( $actions ) ) : ?>
		<p class="ael-note"><?php echo esc_html__( 'L\'accesso a questo corso è riservato agli iscritti.', 'academy-elearning' ); ?></p>
	<?php endif; ?>

	<?php if ( $is_visitor ) : ?>
		<p class="ael-login">
			<?php echo esc_html__( 'Hai già un accesso?', 'academy-elearning' ); ?>
			<a href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html__( 'Accedi', 'academy-elearning' ); ?></a>
		</p>
	<?php endif; ?>
</div>
