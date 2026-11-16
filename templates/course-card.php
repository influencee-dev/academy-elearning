<?php
/**
 * Scheda di un corso nel catalogo. Per cambiarla copia questo file in
 * `academy-elearning/course-card.php` nel tema.
 *
 * Variabili: $card (url, title, subtitle, thumbnail, teacher_name, level_label,
 * lessons, duration, categories, owned, course).
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

$meta = array_filter(
	array(
		$card['teacher_name'],
		$card['level_label'],
		$card['lessons'] > 0 ? sprintf(
			/* translators: %d: numero di lezioni */
			_n( '%d lezione', '%d lezioni', (int) $card['lessons'], 'academy-elearning' ),
			(int) $card['lessons']
		) : '',
		$card['duration'],
	),
	'strlen'
);
?>
<article class="ael-card">
	<a class="ael-card-media" href="<?php echo esc_url( $card['url'] ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( '' !== $card['thumbnail'] ) : ?>
			<?php echo wp_kses_post( $card['thumbnail'] ); ?>
		<?php else : ?>
			<span class="ael-card-placeholder"></span>
		<?php endif; ?>
	</a>
	<div class="ael-card-body">
		<?php if ( ! empty( $card['categories'] ) ) : ?>
			<p class="ael-card-cats"><?php echo esc_html( implode( ' · ', $card['categories'] ) ); ?></p>
		<?php endif; ?>
		<h3 class="ael-card-title"><a href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a></h3>
		<?php if ( '' !== $card['subtitle'] ) : ?>
			<p class="ael-card-subtitle"><?php echo esc_html( $card['subtitle'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $meta ) ) : ?>
			<p class="ael-card-meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
		<?php endif; ?>
		<?php if ( $card['owned'] ) : ?>
			<p><span class="ael-badge"><?php echo esc_html__( 'Il tuo corso', 'academy-elearning' ); ?></span></p>
		<?php endif; ?>
		<?php
		/**
		 * In fondo alla scheda di un corso nel catalogo (per esempio il prezzo).
		 *
		 * @param int $course_id Corso.
		 */
		do_action( 'ael_course_card_footer', $card['course']->ID );
		?>
	</div>
</article>
