/* Scheda pubblica del docente: scelta della copertina e colore. Solo amministrazione. */
jQuery(function ($) {
	'use strict';

	var frame = null;
	var idField = $('#ael_cover_id');
	var preview = $('#ael-cover-preview');

	$('#ael-cover-pick').on('click', function (event) {
		event.preventDefault();
		if (!frame) {
			frame = wp.media({
				title: window.aelTeacher ? window.aelTeacher.pickTitle : '',
				button: { text: window.aelTeacher ? window.aelTeacher.pickButton : '' },
				library: { type: 'image' },
				multiple: false
			});
			frame.on('select', function () {
				var item = frame.state().get('selection').first().toJSON();
				var url = (item.sizes && item.sizes.medium) ? item.sizes.medium.url : item.url;
				idField.val(item.id);
				preview.empty().append($('<img>', { src: url, alt: '' }).css({ maxWidth: '320px', height: 'auto' }));
			});
		}
		frame.open();
	});

	$('#ael-cover-remove').on('click', function (event) {
		event.preventDefault();
		idField.val('0');
		preview.empty();
	});

	// Selettore colore e campo di testo sempre allineati.
	var picker = $('#ael_accent_picker');
	var text = $('#ael_accent');
	picker.on('input change', function () {
		text.val(picker.val());
	});
	text.on('input', function () {
		if (/^#[0-9a-fA-F]{6}$/.test(text.val())) {
			picker.val(text.val());
		}
	});
});
