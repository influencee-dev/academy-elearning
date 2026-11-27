/* Chat: porta la conversazione in fondo, all'ultimo messaggio. */
document.addEventListener('DOMContentLoaded', function () {
	var box = document.querySelector('.ael-chat-messages');
	if (box) { box.scrollTop = box.scrollHeight; }
});
