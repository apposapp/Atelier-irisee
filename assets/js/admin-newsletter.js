/**
 * Atelier Irisee → Newsletter: choose the attachment from the media library, and ask before sending to
 * everyone.
 */
jQuery(function ($) {
	'use strict';

	var t = window.aimpNewsletter || {};
	var $form = $('[data-aimp-nl-form]');
	if (!$form.length) {
		return;
	}
	var $field = $form.find('[data-aimp-nl-attachment]');
	var $name = $form.find('[data-aimp-nl-file]');
	var $remove = $form.find('[data-aimp-nl-remove-file]');
	var frame = null;

	$form.on('click', '[data-aimp-nl-choose]', function () {
		if (!window.wp || !window.wp.media) {
			return;
		}
		if (!frame) {
			frame = window.wp.media({ title: t.choose, button: { text: t.use }, multiple: false });
			frame.on('select', function () {
				var file = frame.state().get('selection').first().toJSON();
				$field.val(file.id);
				$name.text(file.filename || file.title);
				$remove.prop('hidden', false);
			});
		}
		frame.open();
	});

	$remove.on('click', function () {
		$field.val('0');
		$name.text('—');
		$remove.prop('hidden', true);
	});

	$form.on('click', '[data-aimp-nl-send]', function (e) {
		var count = parseInt($form.attr('data-count'), 10) || 0;
		// eslint-disable-next-line no-alert
		if (!window.confirm(String(t.sendConfirm || '').replace('%d', count))) {
			e.preventDefault();
		}
	});
});

// "Build it here", "Simple text" or "A page": show the builder or the editor.
jQuery(function ($) {
	'use strict';

	var $form = $('[data-aimp-nl-form]');
	if (!$form.length) {
		return;
	}
	function show() {
		var source = $form.find('input[name="aimp_nl_source"]:checked').val() || 'builder';
		$form.find('[data-aimp-nl-for]').each(function () {
			var on = (' ' + this.getAttribute('data-aimp-nl-for') + ' ').indexOf(' ' + source + ' ') !== -1;
			var was = this.hidden;
			this.hidden = !on;
			// The builder's text editors are built again once it is visible.
			if (on && was && source === 'builder' && typeof window.aimpBlogBuilderRender === 'function') {
				window.aimpBlogBuilderRender();
			}
		});
	}
	$form.on('change', 'input[name="aimp_nl_source"]', show);
	show();
});
