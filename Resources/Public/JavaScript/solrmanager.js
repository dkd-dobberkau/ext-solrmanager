function firstAndLast(container) {
	if (!container) {
		return false;
	}
	else {
		container.find('a.disabled').removeClass('disabled')
		container.find('a.btn-move-up:first').addClass('disabled');
		container.find('a.btn-move-down:last').addClass('disabled');
	}
}

$(document).ready(function() {
	firstAndLast($('#result-container'));

	$('.btn-move-up, .btn-move-down').click(function(e) {
		e.preventDefault();
		var parent = $(this).closest('.result');
		var grandparent = $(this).closest('#result-container');
		if ($(this).hasClass('btn-move-up')) {
			parent.insertBefore(parent.prev('div'));
			firstAndLast(grandparent);
		} else if ($(this).hasClass('btn-move-down')) {
			parent.insertAfter(parent.next('div'));
			firstAndLast(grandparent);
		}
		$('#tx_solrmanager_resultdocument_chbox').prependTo($('.result').first().find('.result-chbox'));
	});
});