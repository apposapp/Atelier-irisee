/**
 * Admin: WooCommerce > Login & registration settings (color pickers, image choosers, field builder).
 */
jQuery(function ($) {
	'use strict';

	var i18n = window.aimpAdminLogin || {};

	// Color pickers.
	$('.aimp-color').wpColorPicker();

	// Image choosers.
	$(document).on('click', '.aimp-media-choose', function (e) {
		e.preventDefault();
		var $wrap = $(this).closest('.aimp-media');
		var frame = wp.media({ title: i18n.choose, multiple: false, library: { type: 'image' } });
		frame.on('select', function () {
			var url = frame.state().get('selection').first().toJSON().url;
			$wrap.find('input[type="url"]').val(url);
			$wrap.find('.aimp-media-preview').html('<img src="' + url + '" alt="">');
		});
		frame.open();
	});

	$(document).on('click', '.aimp-media-remove', function (e) {
		e.preventDefault();
		var $wrap = $(this).closest('.aimp-media');
		$wrap.find('input[type="url"]').val('');
		$wrap.find('.aimp-media-preview').empty();
	});

	/* ---------------------------------------------------------------
	 * Field builder
	 * ------------------------------------------------------------- */

	var $builder = $('.aimp-fields-builder');
	if (!$builder.length) {
		return;
	}
	var $list = $builder.find('.aimp-fields-list');

	// Show only the options that apply to the chosen field type.
	function syncType($row) {
		var type = $row.find('.aimp-field-type').val();
		$row.find('.aimp-field-only').each(function () {
			$(this).toggle($(this).hasClass('aimp-field-only--' + type));
		});
		$row.find('.aimp-field-not').each(function () {
			$(this).toggle(!$(this).hasClass('aimp-field-not--' + type));
		});
	}

	$list.find('.aimp-field-row').each(function () {
		syncType($(this));
	});

	$list.sortable({
		handle: '.aimp-field-handle',
		axis: 'y',
		placeholder: 'aimp-field-placeholder'
	});

	$builder.on('click', '.aimp-field-add', function () {
		var html = $builder.find('.aimp-field-template').html().replace(/__i__/g, 'n' + Date.now());
		var $row = $(html);
		$list.append($row);
		syncType($row);
		$row.find('.aimp-field-label').trigger('focus');
	});

	$builder.on('click', '.aimp-field-remove', function () {
		$(this).closest('.aimp-field-row').remove();
	});

	$builder.on('click', '.aimp-field-toggle', function () {
		var $body = $(this).closest('.aimp-field-row').find('.aimp-field-row-body');
		$body.prop('hidden', !$body.prop('hidden'));
	});

	$builder.on('change', '.aimp-field-type', function () {
		syncType($(this).closest('.aimp-field-row'));
	});

	$builder.on('input', '.aimp-field-label', function () {
		$(this).closest('.aimp-field-row').find('.aimp-field-title').text($(this).val());
	});
});
