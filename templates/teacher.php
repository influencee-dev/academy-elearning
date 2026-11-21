<?php
/**
 * Pagina pubblica del docente. Il nome lo mostra il tema. Per cambiarla copia
 * questo file in `academy-elearning/teacher.php` nel tema.
 *
 * Variabili: $teacher, $content, $courses, $cards, $profile (frase, copertina,
 * colore, link, canali social, follower totali, engagement medio e data dei
 * numeri: vuoti se le schede personalizzabili sono spente), $photo.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

$platforms = AEL_Teacher_Profile::platforms();
$has_stats = $profile['total_followers'] > 0 || $profile['engagement'] > 0;
?>
<div class="ael ael-teacher"<?php echo '' !== $profile['style'] ? ' style="' . esc_attr( $profile['style'] ) . '"' : ''; ?>>
	<?php if ( '' !== $profile['cover_url'] ) : ?>
		<div class="ael-teacher-cover"><img src="<?php echo esc_url( $profile['cover_url'] ); ?>" alt=""></div>
	<?php endif; ?>

	<div class="ael-teacher-head">
		<?php if ( '' !== $photo ) : ?>
			<span class="ael-teacher-photo"><?php echo wp_kses_post( $photo ); ?></span>
		<?php endif; ?>
		<div class="ael-teacher-info">
			<?php if ( '' !== $profile['tagline'] ) : ?>
				<p class="ael-teacher-tagline"><?php echo esc_html( $profile['tagline'] ); ?></p>
			<?php endif; ?>

			<?php if ( $has_stats ) : ?>
				<ul class="ael-stats">
					<?php if ( $profile['total_followers'] > 0 ) : ?>
						<li><span class="ael-stat-num"><?php echo esc_html( AEL_Teacher_Profile::format_count( (int) $profile['total_followers'] ) ); ?></span><span class="ael-stat-label"><?php echo esc_html__( 'Follower', 'academy-elearning' ); ?></span></li>
					<?php endif; ?>
					<?php if ( $profile['engagement'] > 0 ) : ?>
						<li><span class="ael-stat-num"><?php echo esc_html( AEL_Teacher_Profile::format_rate( (float) $profile['engagement'] ) ); ?></span><span class="ael-stat-label"><?php echo esc_html__( 'Engagement medio', 'academy-elearning' ); ?></span></li>
					<?php endif; ?>
					<?php if ( count( $profile['channels'] ) > 1 ) : ?>
						<li><span class="ael-stat-num"><?php echo esc_html( (string) count( $profile['channels'] ) ); ?></span><span class="ael-stat-label"><?php echo esc_html__( 'Canali', 'academy-elearning' ); ?></span></li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>

			<?php if ( '' !== trim( wp_strip_all_tags( $content ) ) ) : ?>
				<div class="ael-description"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già filtrato da the_content. ?></div>
			<?php endif; ?>

			<?php if ( ! empty( $profile['links'] ) ) : ?>
				<ul class="ael-teacher-box-links">
					<?php foreach ( $profile['links'] as $link ) : ?>
						<li><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( ! empty( $profile['channels'] ) ) : ?>
		<section class="ael-section ael-channels-section">
			<h2><?php echo esc_html__( 'Dove lo trovi', 'academy-elearning' ); ?></h2>
			<ul class="ael-channels">
				<?php foreach ( $profile['channels'] as $channel ) : ?>
					<li class="ael-channel">
						<span class="ael-channel-platform"><?php echo esc_html( $platforms[ $channel['platform'] ] ?? $channel['platform'] ); ?></span>
						<?php if ( '' !== $channel['handle'] ) : ?>
							<?php if ( '' !== $channel['url'] ) : ?>
								<a class="ael-channel-handle" href="<?php echo esc_url( $channel['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $channel['handle'] ); ?></a>
							<?php else : ?>
								<span class="ael-channel-handle"><?php echo esc_html( $channel['handle'] ); ?></span>
							<?php endif; ?>
						<?php elseif ( '' !== $channel['url'] ) : ?>
							<a class="ael-channel-handle" href="<?php echo esc_url( $channel['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $platforms[ $channel['platform'] ] ?? '' ); ?></a>
						<?php endif; ?>
						<span class="ael-channel-numbers">
							<?php if ( $channel['followers'] > 0 ) : ?>
								<span><strong><?php echo esc_html( AEL_Teacher_Profile::format_count( (int) $channel['followers'] ) ); ?></strong> <?php echo esc_html__( 'follower', 'academy-elearning' ); ?></span>
							<?php endif; ?>
							<?php if ( $channel['engagement'] > 0 ) : ?>
								<span><strong><?php echo esc_html( AEL_Teacher_Profile::format_rate( (float) $channel['engagement'] ) ); ?></strong> <?php echo esc_html__( 'engagement', 'academy-elearning' ); ?></span>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( '' !== $profile['stats_date'] ) : ?>
				<p class="ael-stats-date">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: data di aggiornamento dei numeri */
							__( 'Numeri aggiornati a %s', 'academy-elearning' ),
							$profile['stats_date']
						)
					);
					?>
				</p>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<section class="ael-section">
		<h2><?php echo esc_html__( 'I suoi corsi', 'academy-elearning' ); ?></h2>
		<?php if ( empty( $cards ) ) : ?>
			<p class="ael-empty"><?php echo esc_html__( 'Nessun corso pubblicato per ora.', 'academy-elearning' ); ?></p>
		<?php else : ?>
			<div class="ael-grid">
				<?php foreach ( $cards as $card ) : ?>
					<?php echo AEL_Templates::render( 'course-card', array( 'card' => $card ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape nel template. ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
</div>
