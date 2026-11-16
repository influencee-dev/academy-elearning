<?php
/**
 * Area "I miei corsi". Per cambiarla copia questo file in
 * `academy-elearning/my-courses.php` nel tema.
 *
 * Variabili: $logged_in; se non collegato: $login_url; se collegato: $user,
 * $cards, $is_manager, $catalog_url, $logout_url.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-my-courses">
	<?php if ( ! $logged_in ) : ?>
		<p><?php echo esc_html__( 'Accedi per vedere i tuoi corsi.', 'academy-elearning' ); ?></p>
		<p><a class="ael-button ael-button--primary" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html__( 'Accedi', 'academy-elearning' ); ?></a></p>
	<?php else : ?>
		<p class="ael-greeting">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: nome dell'utente */
					__( 'Ciao %s', 'academy-elearning' ),
					$user->display_name
				)
			);
			?>
			· <a href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html__( 'Esci', 'academy-elearning' ); ?></a>
		</p>

		<?php
		/**
		 * Sopra l'elenco dei corsi dello studente. Qui i moduli mostrano, per
		 * esempio, lo stato dell'abbonamento e il link per gestirlo.
		 *
		 * @param int $user_id Utente.
		 */
		do_action( 'ael_my_courses_before', $user->ID );
		?>

		<?php if ( $is_manager ) : ?>
			<p class="ael-note"><?php echo esc_html__( 'Come gestore dell\'academy vedi tutti i corsi.', 'academy-elearning' ); ?></p>
		<?php endif; ?>

		<?php if ( empty( $cards ) ) : ?>
			<p class="ael-empty"><?php echo esc_html__( 'Non hai ancora nessun corso.', 'academy-elearning' ); ?></p>
			<p><a class="ael-button ael-button--primary" href="<?php echo esc_url( $catalog_url ); ?>"><?php echo esc_html__( 'Sfoglia il catalogo', 'academy-elearning' ); ?></a></p>
		<?php else : ?>
			<div class="ael-grid">
				<?php foreach ( $cards as $card ) : ?>
					<article class="ael-card">
						<a class="ael-card-media" href="<?php echo esc_url( $card['url'] ); ?>" tabindex="-1" aria-hidden="true">
							<?php if ( '' !== $card['thumbnail'] ) : ?>
								<?php echo wp_kses_post( $card['thumbnail'] ); ?>
							<?php else : ?>
								<span class="ael-card-placeholder"></span>
							<?php endif; ?>
						</a>
						<div class="ael-card-body">
							<h3 class="ael-card-title"><a href="<?php echo esc_url( $card['url'] ); ?>"><?php echo esc_html( $card['title'] ); ?></a></h3>
							<?php if ( null !== $card['progress'] ) : ?>
								<div class="ael-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $card['progress'] ); ?>">
									<span class="ael-progress-bar" style="width:<?php echo esc_attr( (string) $card['progress'] ); ?>%"></span>
								</div>
								<p class="ael-card-meta">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: percentuale completata */
											__( '%d%% completato', 'academy-elearning' ),
											(int) $card['progress']
										)
									);
									?>
								</p>
							<?php endif; ?>
							<p>
								<a class="ael-button ael-button--primary" href="<?php echo esc_url( $card['resume_url'] ); ?>">
									<?php echo esc_html( $card['started'] ? __( 'Continua il corso', 'academy-elearning' ) : __( 'Inizia il corso', 'academy-elearning' ) ); ?>
								</a>
							</p>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php
		/**
		 * Sotto l'elenco dei corsi dello studente.
		 *
		 * @param int $user_id Utente.
		 */
		do_action( 'ael_my_courses_after', $user->ID );
		?>
	<?php endif; ?>
</div>
