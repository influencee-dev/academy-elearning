<?php
/**
 * Riquadro "Il creator" nella pagina del corso. Per cambiarlo copia questo file
 * in `academy-elearning/creator-box.php` nel tema.
 *
 * Variabili: $creator (teacher, name, url, photo, profile, style), da
 * AEL_Catalog::creator_for_course().
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

$profile = $creator['profile'];
$bio     = wp_trim_words( wp_strip_all_tags( (string) $creator['teacher']->post_content ), 35 );
?>
<section class="ael-creator">
	<h2><?php echo esc_html__( 'Il creator', 'academy-elearning' ); ?></h2>
	<div class="ael-creator-head">
		<?php if ( '' !== $creator['photo'] ) : ?>
			<span class="ael-creator-photo"><?php echo wp_kses_post( $creator['photo'] ); ?></span>
		<?php endif; ?>
		<div>
			<p class="ael-creator-name">
				<?php if ( '' !== $creator['url'] ) : ?>
					<a href="<?php echo esc_url( $creator['url'] ); ?>"><?php echo esc_html( $creator['name'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $creator['name'] ); ?>
				<?php endif; ?>
			</p>
			<?php if ( '' !== $profile['tagline'] ) : ?>
				<p class="ael-creator-tagline"><?php echo esc_html( $profile['tagline'] ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( '' !== $bio ) : ?>
		<p class="ael-creator-bio"><?php echo esc_html( $bio ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $profile['links'] ) ) : ?>
		<ul class="ael-creator-links">
			<?php foreach ( $profile['links'] as $link ) : ?>
				<li><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link['label'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
