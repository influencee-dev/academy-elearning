<?php
/**
 * Riquadro "Il docente" nella pagina del corso. Per cambiarlo copia questo file
 * in `academy-elearning/teacher-box.php` nel tema.
 *
 * Variabili: $teacher_box (teacher, name, url, photo, profile, style), da
 * AEL_Catalog::teacher_box_for_course().
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

$profile = $teacher_box['profile'];
$bio     = wp_trim_words( wp_strip_all_tags( (string) $teacher_box['teacher']->post_content ), 35 );
?>
<section class="ael-teacher-box">
	<h2><?php echo esc_html__( 'Il docente', 'academy-elearning' ); ?></h2>
	<div class="ael-teacher-box-head">
		<?php if ( '' !== $teacher_box['photo'] ) : ?>
			<span class="ael-teacher-box-photo"><?php echo wp_kses_post( $teacher_box['photo'] ); ?></span>
		<?php endif; ?>
		<div>
			<p class="ael-teacher-box-name">
				<?php if ( '' !== $teacher_box['url'] ) : ?>
					<a href="<?php echo esc_url( $teacher_box['url'] ); ?>"><?php echo esc_html( $teacher_box['name'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $teacher_box['name'] ); ?>
				<?php endif; ?>
			</p>
			<?php if ( '' !== $profile['tagline'] ) : ?>
				<p class="ael-teacher-box-tagline"><?php echo esc_html( $profile['tagline'] ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( $profile['total_followers'] > 0 || $profile['engagement'] > 0 ) : ?>
		<ul class="ael-stats ael-stats--compact">
			<?php if ( $profile['total_followers'] > 0 ) : ?>
				<li><span class="ael-stat-num"><?php echo esc_html( AEL_Teacher_Profile::format_count( (int) $profile['total_followers'] ) ); ?></span><span class="ael-stat-label"><?php echo esc_html__( 'Follower', 'academy-elearning' ); ?></span></li>
			<?php endif; ?>
			<?php if ( $profile['engagement'] > 0 ) : ?>
				<li><span class="ael-stat-num"><?php echo esc_html( AEL_Teacher_Profile::format_rate( (float) $profile['engagement'] ) ); ?></span><span class="ael-stat-label"><?php echo esc_html__( 'Engagement', 'academy-elearning' ); ?></span></li>
			<?php endif; ?>
		</ul>
	<?php endif; ?>
	<?php if ( '' !== $bio ) : ?>
		<p class="ael-teacher-box-bio"><?php echo esc_html( $bio ); ?></p>
	<?php endif; ?>
	<?php if ( ! empty( $profile['links'] ) ) : ?>
		<ul class="ael-teacher-box-links">
			<?php foreach ( $profile['links'] as $link ) : ?>
				<li><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link['label'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
