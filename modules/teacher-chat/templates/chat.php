<?php
/**
 * Chat con i formatori. Per cambiarla copia questo file in `academy-elearning/chat.php` nel tema.
 *
 * Variabili: $mode ('gate' o 'chat'), $gate, $threads (id, label, role, unread), $current
 * (id, label, messages: mine, body, time) o null, $teachers (ID scheda => nome), $subscriber,
 * $page_url, $privacy, $error, $messages.
 *
 * @package AcademyElearning
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ael ael-section ael-chat-wrap">
	<?php if ( 'gate' === $mode ) : ?>
		<?php echo AEL_Templates::render( 'section-gate', array( 'gate' => $gate ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template già con escape. ?>
	<?php else : ?>

		<?php if ( '' !== $error && isset( $messages[ $error ] ) ) : ?>
			<p class="ael-error" role="alert"><?php echo esc_html( $messages[ $error ] ); ?></p>
		<?php endif; ?>

		<div class="ael-chat">
			<aside>
				<?php if ( $subscriber ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ael-chat-new">
						<input type="hidden" name="action" value="ael_chat_start">
						<?php wp_nonce_field( 'ael_chat_start' ); ?>
						<?php if ( empty( $teachers ) ) : ?>
							<p class="ael-note"><?php echo esc_html__( 'Nessun formatore è ancora disponibile in chat.', 'academy-elearning' ); ?></p>
						<?php else : ?>
							<label for="ael-chat-teacher"><strong><?php echo esc_html__( 'Scrivi a un formatore', 'academy-elearning' ); ?></strong></label>
							<p>
								<select id="ael-chat-teacher" name="teacher_id" required>
									<option value=""><?php echo esc_html__( 'Scegli…', 'academy-elearning' ); ?></option>
									<?php foreach ( $teachers as $teacher_id => $name ) : ?>
										<option value="<?php echo esc_attr( (string) $teacher_id ); ?>"><?php echo esc_html( $name ); ?></option>
									<?php endforeach; ?>
								</select>
							</p>
							<p><button type="submit" class="ael-button ael-button--primary"><?php echo esc_html__( 'Apri la conversazione', 'academy-elearning' ); ?></button></p>
						<?php endif; ?>
					</form>
				<?php endif; ?>

				<h3><?php echo esc_html__( 'Le tue conversazioni', 'academy-elearning' ); ?></h3>
				<?php if ( empty( $threads ) ) : ?>
					<p class="ael-note"><?php echo esc_html__( 'Non hai ancora conversazioni.', 'academy-elearning' ); ?></p>
				<?php else : ?>
					<ul class="ael-chat-list">
						<?php foreach ( $threads as $thread ) : ?>
							<li class="<?php echo ( $current && (int) $current['id'] === (int) $thread['id'] ) ? 'is-current' : ''; ?>">
								<a href="<?php echo esc_url( add_query_arg( 'c', (int) $thread['id'], $page_url ) ); ?>">
									<?php echo esc_html( $thread['label'] ); ?>
									<?php if ( $thread['unread'] > 0 ) : ?>
										<span class="ael-unread"><?php echo esc_html( (string) $thread['unread'] ); ?></span>
									<?php endif; ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</aside>

			<section>
				<?php if ( $current ) : ?>
					<div class="ael-chat-thread">
						<div class="ael-chat-thread__head"><?php echo esc_html( $current['label'] ); ?></div>
						<div class="ael-chat-messages">
							<?php if ( empty( $current['messages'] ) ) : ?>
								<p class="ael-note"><?php echo esc_html__( 'Scrivi il primo messaggio.', 'academy-elearning' ); ?></p>
							<?php endif; ?>
							<?php foreach ( $current['messages'] as $message ) : ?>
								<div class="ael-msg <?php echo $message['mine'] ? 'is-mine' : ''; ?>"><span class="ael-msg__text"><?php echo esc_html( $message['body'] ); ?></span><time><?php echo esc_html( $message['time'] ); ?></time></div>
							<?php endforeach; ?>
							<span id="fine"></span>
						</div>
						<form class="ael-chat-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="ael_chat_send">
							<input type="hidden" name="thread_id" value="<?php echo esc_attr( (string) $current['id'] ); ?>">
							<?php wp_nonce_field( 'ael_chat_send_' . $current['id'] ); ?>
							<label class="screen-reader-text" for="ael-chat-body"><?php echo esc_html__( 'Il tuo messaggio', 'academy-elearning' ); ?></label>
							<textarea id="ael-chat-body" name="body" maxlength="4000" required placeholder="<?php echo esc_attr__( 'Scrivi un messaggio…', 'academy-elearning' ); ?>"></textarea>
							<button type="submit" class="ael-button ael-button--primary"><?php echo esc_html__( 'Invia', 'academy-elearning' ); ?></button>
						</form>
					</div>
				<?php else : ?>
					<div class="ael-gate">
						<p class="ael-gate__title"><?php echo esc_html__( 'Parla in privato con un formatore', 'academy-elearning' ); ?></p>
						<p><?php echo esc_html__( 'Scegli un formatore a sinistra e scrivi il tuo messaggio: ti risponderà qui.', 'academy-elearning' ); ?></p>
					</div>
				<?php endif; ?>
				<p class="ael-privacy"><?php echo esc_html( $privacy ); ?></p>
			</section>
		</div>
	<?php endif; ?>
</div>
