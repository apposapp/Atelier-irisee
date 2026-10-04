/**
 * Settings page: choose pictures (overview cards) and font files from the media library.
 */
jQuery(function ($) {
	'use strict';

	var t = window.aimpSettings || {};

	$(document).on('click', '.aimp-media-choose', function (e) {
		e.preventDefault();
		var $field = $(this).closest('.aimp-media-field');
		var isImage = $field.data('type') === 'image';
		var frame = wp.media({
			title: isImage ? t.chooseImage : t.chooseFont,
			button: { text: t.use },
			library: isImage ? { type: 'image' } : {},
			multiple: false
		});
		frame.on('select', function () {
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
		$(this).hide();
	});
});
