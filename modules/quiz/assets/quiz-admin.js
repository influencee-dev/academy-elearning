/* Editor delle domande di un quiz: aggiungi e togli domande. */
jQuery(function ($) {
	var $wrap = $('#ael-quiz-questions');
	var tpl = $('#ael-quiz-tpl').html();
	function renumber() {
		$wrap.children('.ael-quiz-q').each(function (i) {
			$(this).find('.ael-quiz-n').text(i + 1);
			$(this).find('[data-f]').each(function () {
				$(this).attr('name', 'ael_quiz[' + i + '][' + $(this).data('f') + ']');
			});
		});
	}
	$('#ael-quiz-add').on('click', function (e) {
		e.preventDefault();
		$wrap.append(tpl);
		renumber();
	});
	$wrap.on('click', '.ael-quiz-del', function (e) {
		e.preventDefault();
		$(this).closest('.ael-quiz-q').remove();
		renumber();
	});
	renumber();
});
