dotclear.viewPostContent = (line, action) => {
	var action = action || 'toggle';
	const postId = $(line).attr('id').substr(1);
	let tr = document.getElementById(`pe${postId}`);

	if (!tr && (action == 'toggle' || action == 'open')) {
		tr = document.createElement('tr');
		tr.id = `pe${postId}`;
		const td = document.createElement('td');
		td.colSpan = 8;
		td.className = 'expand';
		tr.appendChild(td);

		// Get post content
		$.get('services.php', {
			f: 'getPostById',
			id: postId,
			post_type: ''
		}, (data) => {
			const rsp = $(data).children('rsp')[0];

			if (rsp.attributes[0].value == 'ok') {
				const post = $(rsp).find('post_display_content').text();

				let res = '';

				if (post) {

					res += post;
					$(td).append(res);
				}
			} else {
				alert($(rsp).find('message').text());
			}
		});

		$(line).addClass('expand');
		line.parentNode.insertBefore(tr, line.nextSibling);
	} else if (tr && tr.style.display == 'none' && (action == 'toggle' || action == 'open')) {
		$(tr).css('display', 'table-row');
		$(line).addClass('expand');
	} else if (tr && tr.style.display != 'none' && (action == 'toggle' || action == 'close')) {
		$(tr).css('display', 'none');
		$(line).removeClass('expand');
	}
};

$(() => {
	// Entry type switcher
	$('#type').change(function () {
		this.form.submit();
	});

	$.expandContent({
		line: $('#form-entries tr:not(.line)'),
		lines: $('#form-entries tr.line'),
		callback: dotclear.viewPostContent
	});
	$('.checkboxes-helpers').each(function () {
		dotclear.checkboxesHelpers(this);
	});
	$('#form-entries td input[type=checkbox]').enableShiftClick();
	dotclear.postsActionsHelper();
});