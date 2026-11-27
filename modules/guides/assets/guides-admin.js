/* Selettore dei file allegati a una guida. */
jQuery(function ($) {
	var $list = $('#ael-guide-files');
	$('#ael-guide-add').on('click', function (e) {
		e.preventDefault();
		var frame = wp.media({ title: aelGuides.title, multiple: true });
		frame.on('select', function () {
			frame.state().get('selection').each(function (a) {
				a = a.toJSON();
				if ($list.find('li[data-id="' + a.id + '"]').length) { return; }
				var $li = $('<li>').attr('data-id', a.id).text(a.title + ' ');
				$li.append($('<input type="hidden" name="ael_guide_files[]">').val(a.id));
				$li.append($('<a href="#" class="ael-guide-remove">').text(aelGuides.remove));
				$list.append($li);
			});
		});
		frame.open();
	});
	$list.on('click', '.ael-guide-remove', function (e) {
		e.preventDefault();
		$(this).closest('li').remove();
	});
});
