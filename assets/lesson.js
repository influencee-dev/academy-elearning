/*
 * Lettore della lezione: salva la posizione del video, i secondi guardati e il
 * completamento (oltre la soglia, 90% di default, oppure con il pulsante).
 * Funziona con YouTube e Vimeo. Si carica solo per studenti collegati con accesso.
 */
(function () {
	'use strict';

	var cfg = window.aelLesson;
	if (!cfg || !window.fetch) {
		return;
	}

	var SEND_EVERY = 15; // secondi tra un salvataggio e l'altro mentre il video va.
	var state = {
		completed: !!cfg.completed,
		pending: 0, // secondi guardati non ancora inviati.
		position: 0,
		percent: 0,
		lastTick: 0,
		lastSend: Date.now(),
		sending: false
	};

	var button = document.querySelector('[data-ael-complete]');
	var statusEl = document.querySelector('[data-ael-status]');

	function say(text, isError) {
		if (!statusEl) {
			return;
		}
		statusEl.textContent = text || '';
		statusEl.classList.toggle('ael-error-text', !!isError);
	}

	function api(path, body, keepalive) {
		return fetch(cfg.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			keepalive: !!keepalive,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce
			},
			body: JSON.stringify(body)
		}).then(function (response) {
			if (!response.ok) {
				throw new Error('http ' + response.status);
			}
			return response.json();
		});
	}

	/* Aggiorna pulsante e programma in base allo stato ricevuto dal server. */
	function apply(data) {
		if (!data) {
			return;
		}
		state.completed = !!data.completed;
		if (button) {
			button.setAttribute('data-completed', state.completed ? '1' : '0');
			button.textContent = state.completed ? cfg.i18n.uncomplete : cfg.i18n.complete;
		}
		var current = document.querySelector('.ael-lesson-item.is-current');
		if (current) {
			current.classList.toggle('is-done', state.completed);
		}
	}

	/* Invia posizione, secondi e percentuale. */
	function flush() {
		if (state.sending) {
			return;
		}
		var seconds = Math.min(Math.round(state.pending), 120);
		state.pending -= seconds;
		state.lastSend = Date.now();
		state.sending = true;
		api('progress', {
			position: Math.floor(state.position),
			seconds: seconds,
			percent: Math.round(state.percent * 10) / 10
		}, true).then(function (data) {
			state.sending = false;
			apply(data);
		}).catch(function () {
			state.sending = false;
			state.pending += seconds; // riprova al prossimo invio.
		});
	}

	/* Chiamata dal lettore: posizione attuale, durata totale, il video sta andando. */
	function tick(position, duration, playing) {
		var now = Date.now();
		if (playing && state.lastTick) {
			var delta = (now - state.lastTick) / 1000;
			if (delta > 0 && delta < 5) {
				state.pending += delta;
			}
		}
		state.lastTick = playing ? now : 0;
		state.position = position || 0;
		if (duration > 0) {
			state.percent = Math.max(state.percent, (position / duration) * 100);
		}

		var due = (now - state.lastSend) / 1000 >= SEND_EVERY;
		var reached = !state.completed && state.percent >= cfg.threshold;
		if (due || reached) {
			flush();
		}
	}

	function stop(position, duration) {
		tick(position, duration, false);
		flush();
	}

	function loadScript(src, done) {
		var tag = document.createElement('script');
		tag.src = src;
		tag.async = true;
		tag.onload = done;
		document.head.appendChild(tag);
	}

	/* YouTube */
	function initYouTube(iframe) {
		var timer = null;
		var player = null;

		function read() {
			return [player.getCurrentTime(), player.getDuration()];
		}

		function create() {
			player = new window.YT.Player(iframe, {
				events: {
					onReady: function () {
						if (!state.completed && cfg.position > 5) {
							player.seekTo(cfg.position, true);
						}
					},
					onStateChange: function (event) {
						var s = window.YT.PlayerState;
						if (event.data === s.PLAYING) {
							clearInterval(timer);
							timer = setInterval(function () {
								var r = read();
								tick(r[0], r[1], true);
							}, 1000);
						} else if (event.data === s.PAUSED || event.data === s.ENDED) {
							clearInterval(timer);
							var r2 = read();
							stop(event.data === s.ENDED ? r2[1] : r2[0], r2[1]);
						}
					}
				}
			});
		}

		if (window.YT && window.YT.Player) {
			create();
			return;
		}
		var previous = window.onYouTubeIframeAPIReady;
		window.onYouTubeIframeAPIReady = function () {
			if (typeof previous === 'function') {
				previous();
			}
			create();
		};
		loadScript('https://www.youtube.com/iframe_api', function () {});
	}

	/* Vimeo */
	function initVimeo(iframe) {
		loadScript('https://player.vimeo.com/api/player.js', function () {
			var player = new window.Vimeo.Player(iframe);
			var duration = 0;
			player.getDuration().then(function (d) {
				duration = d;
			});
			player.ready().then(function () {
				if (!state.completed && cfg.position > 5) {
					player.setCurrentTime(cfg.position).catch(function () {});
				}
			});
			player.on('timeupdate', function (data) {
				duration = data.duration || duration;
				tick(data.seconds, duration, true);
			});
			player.on('pause', function (data) {
				stop(data.seconds, data.duration || duration);
			});
			player.on('ended', function (data) {
				stop(data.duration || duration, data.duration || duration);
			});
		});
	}

	var iframe = document.querySelector('.ael-video iframe');
	if (iframe) {
		var src = iframe.getAttribute('src') || '';
		if (src.indexOf('youtube-nocookie.com') !== -1 || src.indexOf('youtube.com') !== -1) {
			initYouTube(iframe);
		} else if (src.indexOf('player.vimeo.com') !== -1) {
			initVimeo(iframe);
		}
	}

	/* Pulsante manuale. */
	if (button) {
		button.addEventListener('click', function () {
			var next = button.getAttribute('data-completed') !== '1';
			button.disabled = true;
			say('', false);
			api('complete', { completed: next }, false).then(function (data) {
				apply(data);
				say(cfg.i18n.saved, false);
			}).catch(function () {
				say(cfg.i18n.error, true);
			}).then(function () {
				button.disabled = false;
			});
		});
	}

	/* Prima di lasciare la pagina si salva quello che resta. */
	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'hidden' && state.pending > 0.5) {
			flush();
		}
	});
	window.addEventListener('pagehide', function () {
		if (state.pending > 0.5) {
			flush();
		}
	});
})();
