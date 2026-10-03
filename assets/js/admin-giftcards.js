/**
 * Admin: gift card designs (add, remove, reorder, choose image).
 */
jQuery(function ($) {
	'use strict';

	var i18n = window.aimpAdminGc || {};
	var $wrap = $('.aimp-gc-designs-admin');
	if (!$wrap.length) {
		return;
	}
	var $list = $wrap.find('.aimp-gc-design-list');

	$list.sortable({ handle: '.aimp-field-handle', axis: 'y' });

	$wrap.on('click', '.aimp-gc-design-add', function () {
		var html = $wrap.find('.aimp-gc-design-template').html().replace(/__i__/g, 'n' + Date.now());
		$list.append(html);
	});

	$wrap.on('click', '.aimp-gc-design-remove', function () {
		$(this).closest('.aimp-gc-design-row').remove();
	});

	$wrap.on('click', '.aimp-gc-design-choose', function (e) {
		e.preventDefault();
		var $row = $(this).closest('.aimp-gc-design-row');
		var frame = wp.media({ title: i18n.choose, multiple: false, library: { type: 'image' } });
		frame.on('select', function () {
			var image = frame.state().get('selection').first().toJSON();
			var url = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;
			$row.find('.aimp-gc-design-image').val(image.id);
			$row.find('.aimp-gc-design-preview').html('<img src="' + url + '" alt="">');
		});
		frame.open();
	});
});
