<?php
/**
 * Scheda del corso. Il titolo lo mostra il tema. Per cambiarla copia questo file
 * in `academy-elearning/course.php` nel tema.
 *
 * Variabili: $course, $content, $subtitle, $teacher, $teacher_url, $level_label,
 * $learn, $audience, $preview_html, $program, $has_access, $actions, $categories,
 * $is_visitor, $login_url, $duration, $teacher_box (null se le schede docente sono spente).
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;

$lesson_count = (int) $program['lesson_count'];
?>
<div class="ael ael-course"<?php echo ( $teacher_box && '' !== $teacher_box['style'] ) ? ' style="' . esc_attr( $teacher_box['style'] ) . '"' : ''; ?>>
	<?php if ( '' !== $subtitle ) : ?>
		<p class="ael-subtitle"><?php echo esc_html( $subtitle ); ?></p>
	<?php endif; ?>

	<ul class="ael-meta">
		<?php if ( $teacher ) : ?>
			<li>
				<span class="ael-meta-label"><?php echo esc_html__( 'Docente', 'academy-elearning' ); ?></span>
				<?php if ( '' !== $teacher_url ) : ?>
					<a href="<?php echo esc_url( $teacher_url ); ?>"><?php echo esc_html( get_the_title( $teacher ) ); ?></a>
				<?php else : ?>
					<?php echo esc_html( get_the_title( $teacher ) ); ?>
				<?php endif; ?>
			</li>
		<?php endif; ?>
		<?php if ( '' !== $level_label ) : ?>
			<li><span class="ael-meta-label"><?php echo esc_html__( 'Livello', 'academy-elearning' ); ?></span> <?php echo esc_html( $level_label ); ?></li>
		<?php endif; ?>
		<?php if ( $lesson_count > 0 ) : ?>
			<li>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: numero di lezioni */
						_n( '%d lezione', '%d lezioni', $lesson_count, 'academy-elearning' ),
						$lesson_count
					)
				);
				?>
			</li>
		<?php endif; ?>
		<?php if ( '' !== $duration ) : ?>
			<li><span class="ael-meta-label"><?php echo esc_html__( 'Durata', 'academy-elearning' ); ?></span> <?php echo esc_html( $duration ); ?></li>
		<?php endif; ?>
		<?php foreach ( $categories as $category ) : ?>
			<li><a href="<?php echo esc_url( $category['url'] ); ?>"><?php echo esc_html( $category['name'] ); ?></a></li>
		<?php endforeach; ?>
	</ul>

	<div class="ael-layout">
		<div class="ael-main">
			<?php if ( '' !== $preview_html ) : ?>
				<?php echo $preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costruito e protetto in AEL_Video. ?>
			<?php endif; ?>

			<?php if ( '' !== trim( wp_strip_all_tags( $content ) ) ) : ?>
				<div class="ael-description"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- già filtrato da the_content. ?></div>
			<?php endif; ?>

			<?php if ( ! empty( $learn ) ) : ?>
				<section class="ael-section">
					<h2><?php echo esc_html__( 'Cosa imparerai', 'academy-elearning' ); ?></h2>
					<ul class="ael-checklist">
						<?php foreach ( $learn as $line ) : ?>
							<li><?php echo esc_html( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $audience ) ) : ?>
				<section class="ael-section">
					<h2><?php echo esc_html__( 'A chi è rivolto', 'academy-elearning' ); ?></h2>
					<ul>
						<?php foreach ( $audience as $line ) : ?>
							<li><?php echo esc_html( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<section class="ael-section">
				<h2><?php echo esc_html__( 'Programma', 'academy-elearning' ); ?></h2>
				<?php echo AEL_Templates::render( 'program', array( 'program' => $program, 'current' => 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape nel template. ?>
			</section>
		</div>

		<aside class="ael-side">
			<?php
			/**
			 * Spazio sopra i pulsanti della scheda corso (per esempio il prezzo).
			 *
			 * @param int $course_id Corso.
			 */
			do_action( 'ael_course_side_top', $course->ID );
			?>
			<?php
			echo AEL_Templates::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape nel template.
				'actions',
				array(
					'actions'    => $actions,
					'has_access' => $has_access,
					'is_visitor' => $is_visitor,
					'login_url'  => $login_url,
				)
			);
			?>
			<?php if ( $teacher_box ) : ?>
				<?php echo AEL_Templates::render( 'teacher-box', array( 'teacher_box' => $teacher_box ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape nel template. ?>
			<?php endif; ?>
		</aside>
	</div>
</div>
