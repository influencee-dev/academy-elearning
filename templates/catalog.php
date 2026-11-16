<?php
/**
 * Catalogo dei corsi con filtri. Per cambiarlo copia questo file in
 * `academy-elearning/catalog.php` nel tema.
 *
 * Variabili: $cards, $filters, $terms, $teachers, $category, $teacher, $search,
 * $form_url, $plain_page, $pagination, $has_filters, $catalog_url.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-catalog">
	<?php if ( $filters ) : ?>
		<form class="ael-filters" method="get" action="<?php echo esc_url( $form_url ); ?>">
			<?php if ( $plain_page ) : ?>
				<input type="hidden" name="page_id" value="<?php echo esc_attr( (string) $plain_page ); ?>">
			<?php endif; ?>

			<label class="screen-reader-text" for="ael-q"><?php echo esc_html__( 'Cerca un corso', 'academy-elearning' ); ?></label>
			<input type="search" id="ael-q" name="ael_q" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Cerca un corso', 'academy-elearning' ); ?>">

			<?php if ( ! empty( $terms ) ) : ?>
				<label class="screen-reader-text" for="ael-cat"><?php echo esc_html__( 'Categoria', 'academy-elearning' ); ?></label>
				<select id="ael-cat" name="ael_cat">
					<option value=""><?php echo esc_html__( 'Tutte le categorie', 'academy-elearning' ); ?></option>
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $category, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<?php if ( ! empty( $teachers ) ) : ?>
				<label class="screen-reader-text" for="ael-teacher"><?php echo esc_html__( 'Docente', 'academy-elearning' ); ?></label>
				<select id="ael-teacher" name="ael_teacher">
					<option value=""><?php echo esc_html__( 'Tutti i docenti', 'academy-elearning' ); ?></option>
					<?php foreach ( $teachers as $item ) : ?>
						<option value="<?php echo esc_attr( (string) $item->ID ); ?>" <?php selected( $teacher, (int) $item->ID ); ?>><?php echo esc_html( get_the_title( $item ) ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<button type="submit" class="ael-button ael-button--primary"><?php echo esc_html__( 'Filtra', 'academy-elearning' ); ?></button>
			<?php if ( $has_filters ) : ?>
				<a class="ael-reset" href="<?php echo esc_url( $catalog_url ); ?>"><?php echo esc_html__( 'Azzera filtri', 'academy-elearning' ); ?></a>
			<?php endif; ?>
		</form>
	<?php endif; ?>

	<?php if ( empty( $cards ) ) : ?>
		<p class="ael-empty"><?php echo esc_html__( 'Nessun corso trovato.', 'academy-elearning' ); ?></p>
	<?php else : ?>
		<div class="ael-grid">
			<?php foreach ( $cards as $card ) : ?>
				<?php echo AEL_Templates::render( 'course-card', array( 'card' => $card ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape nel template. ?>
			<?php endforeach; ?>
		</div>
		<?php if ( '' !== $pagination ) : ?>
			<nav class="ael-pagination" aria-label="<?php echo esc_attr__( 'Pagine del catalogo', 'academy-elearning' ); ?>"><?php echo wp_kses_post( $pagination ); ?></nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
