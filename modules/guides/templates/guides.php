<?php
/**
 * Elenco delle guide. Per cambiarlo copia questo file in `academy-elearning/guides.php` nel tema.
 *
 * Variabili: $items (id, title, excerpt, url, topic, files), $topics, $topic, $search,
 * $can_view, $gate, $page_url.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-section ael-guides">
	<?php if ( ! $can_view ) : ?>
		<?php echo AEL_Templates::render( 'section-gate', array( 'gate' => $gate ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template già con escape. ?>
	<?php endif; ?>

	<form class="ael-filters" method="get" action="<?php echo esc_url( $page_url ); ?>">
		<label class="screen-reader-text" for="ael-guide-search"><?php echo esc_html__( 'Cerca una guida', 'academy-elearning' ); ?></label>
		<input id="ael-guide-search" type="search" name="cerca" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Cerca una guida', 'academy-elearning' ); ?>">
		<label class="screen-reader-text" for="ael-guide-topic"><?php echo esc_html__( 'Tema', 'academy-elearning' ); ?></label>
		<select id="ael-guide-topic" name="tema">
			<option value=""><?php echo esc_html__( 'Tutti i temi', 'academy-elearning' ); ?></option>
			<?php foreach ( $topics as $term ) : ?>
				<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $topic, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="ael-button ael-button--primary"><?php echo esc_html__( 'Filtra', 'academy-elearning' ); ?></button>
	</form>

	<?php if ( empty( $items ) ) : ?>
		<p class="ael-note"><?php echo esc_html__( 'Non ci sono ancora guide.', 'academy-elearning' ); ?></p>
	<?php else : ?>
		<ul class="ael-section-grid">
			<?php foreach ( $items as $item ) : ?>
				<li class="ael-section-card">
					<?php if ( '' !== $item['topic'] ) : ?>
						<span class="ael-chip"><?php echo esc_html( $item['topic'] ); ?></span>
					<?php endif; ?>
					<h3><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>
					<?php if ( '' !== $item['excerpt'] ) : ?>
						<p><?php echo esc_html( $item['excerpt'] ); ?></p>
					<?php endif; ?>
					<p class="ael-meta">
						<?php if ( $item['files'] > 0 ) : ?>
							<?php
							/* translators: %d: numero di file allegati. */
							echo esc_html( sprintf( _n( '%d file da scaricare', '%d file da scaricare', (int) $item['files'], 'academy-elearning' ), (int) $item['files'] ) );
							?>
						<?php endif; ?>
						<?php if ( ! $can_view ) : ?>
							<span class="ael-lock" aria-hidden="true"></span><?php echo esc_html__( 'Riservata', 'academy-elearning' ); ?>
						<?php endif; ?>
					</p>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
