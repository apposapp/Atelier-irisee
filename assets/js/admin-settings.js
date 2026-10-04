/**
 * Settings page: choose pictures (overview cards) and font files from the media library.
 */
jQuery(function ($) {
	'use strict';

	var t = window.aimpSettings || {};

	$(document).on('click', '.aimp-media-choose', function (e) {
		e.preventDefault();
		var $field = $(this).closest('.aimp-media-field');
		var type = $field.data('type');
		var isImage = type === 'image' || type === 'images';
		var frame = wp.media({
			title: isImage ? t.chooseImage : t.chooseFont,
			button: { text: t.use },
			library: isImage ? { type: 'image' } : {},
			multiple: type === 'images' ? 'add' : false
		});
		// Several pictures (payment logos): keep the current choice selected when the window opens.
		if (type === 'images') {
			frame.on('open', function () {
				var selection = frame.state().get('selection');
				String($field.find('input[type="hidden"]').val() || '').split(',').forEach(function (id) {
					if (parseInt(id, 10)) {
						selection.add(wp.media.attachment(parseInt(id, 10)));
					}
				});
			});
		}
		frame.on('select', function () {
			if (type === 'images') {
				var files = frame.state().get('selection').toJSON();
				$field.find('input[type="hidden"]').val(files.map(function (f) {
					return f.id;
				}).join(','));
				$field.find('.aimp-media-list').html(files.map(function (f) {
					var src = f.sizes && f.sizes.thumbnail ? f.sizes.thumbnail.url : f.url;
					return $('<img alt="" style="height:36px;width:auto;margin-right:6px;vertical-align:middle;">').attr('src', src)[0].outerHTML;
				}).join(''));
				$field.find('.aimp-media-remove').toggle(files.length > 0);
				return;
			}
			var file = frame.state().get('selection').first().toJSON();
			$field.find('input[type="hidden"]').val(file.id);
			if (isImage) {
				var url = file.sizes && file.sizes.thumbnail ? file.sizes.thumbnail.url : file.url;
				$field.find('.aimp-media-preview').attr('src', url).show();
			} else {
				$field.find('.aimp-media-name').text(file.filename).show();
			}
			$field.find('.aimp-media-remove').show();
		});
		frame.open();
	});

	$(document).on('click', '.aimp-media-remove', function (e) {
		e.preventDefault();
		var $field = $(this).closest('.aimp-media-field');
		$field.find('input[type="hidden"]').val('0');
		$field.find('.aimp-media-preview').attr('src', '').hide();
		$field.find('.aimp-media-name').text('').hide();
		$field.find('.aimp-media-list').empty();
		$(this).hide();
	});
});
