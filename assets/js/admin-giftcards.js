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

	// A picture from the media library into a hidden field, with a small preview.
	function choose($field, $preview) {
		var frame = wp.media({ title: i18n.choose, multiple: false, library: { type: 'image' } });
		frame.on('select', function () {
			var image = frame.state().get('selection').first().toJSON();
			var url = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;
			$field.val(image.id);
			$preview.empty().append($('<img alt="">').attr('src', url));
		});
		frame.open();
	}

	$wrap.on('click', '.aimp-gc-design-choose', function (e) {
		e.preventDefault();
		var $row = $(this).closest('.aimp-gc-design-row');
		choose($row.find('.aimp-gc-design-image'), $row.find('.aimp-gc-design-preview').first());
	});

	// The back of a theme: a background picture, or none.
	$wrap.on('click', '.aimp-gc-back-choose', function (e) {
		e.preventDefault();
		var $row = $(this).closest('.aimp-gc-design-row');
		choose($row.find('.aimp-gc-back-image'), $row.find('.aimp-gc-back-preview'));
	});
	$wrap.on('click', '.aimp-gc-back-remove', function () {
		var $row = $(this).closest('.aimp-gc-design-row');
		$row.find('.aimp-gc-back-image').val('0');
		$row.find('.aimp-gc-back-preview').empty();
	});

	// Divi's global colours as one-click choices.
	$wrap.on('click', '.aimp-gc-swatch', function () {
		$(this).closest('.aimp-gc-color').find('input[type="color"]').val($(this).attr('data-color')).trigger('change');
	});

	// Both sides of the theme as set now (also before saving), with example details.
	$wrap.on('click', '.aimp-gc-theme-preview', function () {
		var $row = $(this).closest('.aimp-gc-design-row');
		var $msg = $row.find('.aimp-gc-theme-msg');
		var data = { action: 'aimp_gc_admin_preview', nonce: i18n.nonce };
		$row.find('[name^="designs["]').each(function () {
			var match = /\[([a-z_]+)\]$/.exec(this.name);
			if (!match || (this.type === 'checkbox' && !this.checked)) {
				return;
			}
			data['design[' + match[1] + ']'] = this.value;
		});
		$msg.text(i18n.working);
		$.post(i18n.ajaxUrl, data)
			.done(function (res) {
				if (!res || !res.success) {
					$msg.text((res && res.data && res.data.message) || i18n.error);
					return;
				}
				$row.find('.aimp-gc-theme-front').attr('src', res.data.front);
				$row.find('.aimp-gc-theme-back').attr('src', res.data.back);
				$row.find('.aimp-gc-theme-sides').prop('hidden', false);
				$msg.text('');
			})
			.fail(function () {
				$msg.text(i18n.error);
			});
	});

	// The logo on the cards (empty = the Divi logo).
	$('.aimp-gc-logo-choose').on('click', function (e) {
		e.preventDefault();
		choose($('.aimp-gc-logo-id'), $('.aimp-gc-logo-preview'));
	});
	$('.aimp-gc-logo-remove').on('click', function () {
		$('.aimp-gc-logo-id').val('0');
		$('.aimp-gc-logo-preview').empty();
	});
});
