/**
 * Settings → Admin menu: drag the items into order (or use ↑ / ↓), the eye hides an item for chosen
 * roles or people, "Reset to default" puts everything back.
 */
jQuery(function ($) {
	'use strict';

	var $root = $('[data-aimp-am]');
	if (!$root.length) {
		return;
	}
	var $list = $root.find('[data-aimp-am-list]');
	var t = window.aimpAdminMenu || {};

	$list.sortable({
		handle: '.aimp-am-handle',
		axis: 'y',
		placeholder: 'aimp-am-placeholder',
		forcePlaceholderSize: true,
		tolerance: 'pointer'
	});

	// ↑ / ↓ for keyboard users (and fine-tuning).
	$list.on('click', '[data-aimp-am-move]', function () {
		var $item = $(this).closest('[data-aimp-am-item]');
		if (parseInt($(this).attr('data-aimp-am-move'), 10) < 0) {
			$item.prev('[data-aimp-am-item]').before($item);
		} else {
			$item.next('[data-aimp-am-item]').after($item);
		}
		$(this).trigger('focus');
	});

	// The eye: show the roles and people the item is hidden for.
	$list.on('change', '[data-aimp-am-toggle]', function () {
		var $item = $(this).closest('[data-aimp-am-item]');
		var hide = this.checked;
		$item.toggleClass('is-hidden', hide);
		$item.find('.aimp-am-eye .dashicons').toggleClass('dashicons-hidden', hide).toggleClass('dashicons-visibility', !hide);
		$item.find('.aimp-am-rules').prop('hidden', !hide);
	});

	$root.on('click', '[data-aimp-am-reset]', function () {
		// eslint-disable-next-line no-alert
		if (!window.confirm(t.resetConfirm || '')) {
			return;
		}
		$root.find('[data-aimp-am-reset-field]').val('1');
		$(this).closest('form').trigger('submit');
	});
});
