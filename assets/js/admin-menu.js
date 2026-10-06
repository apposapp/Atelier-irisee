/**
 * Settings → Admin menu: choose a role or person at the top, then set up their menu: drag the items
 * (and submenu items within their menu) into order, the eye hides an item. All set-ups are kept in a
 * hidden field (JSON) and saved together with "Save settings".
 */
jQuery(function ($) {
	'use strict';

	var $root = $('[data-aimp-am]');
	if (!$root.length) {
		return;
	}
	var t = window.aimpAdminMenu || {};
	var data = JSON.parse($root.find('[data-aimp-am-data]').text() || '{}');
	var structure = data.structure || [];
	var profiles = data.profiles && !Array.isArray(data.profiles) ? data.profiles : {};
	var locked = data.locked || [];
	var $list = $root.find('[data-aimp-am-list]');
	var $target = $root.find('[data-aimp-am-target]');
	var $json = $root.find('[data-aimp-am-json]');
	var open = {}; // Folded-open submenus, kept while switching.
	var current = 'everyone';

	function esc(text) {
		return $('<div>').text(text == null ? '' : String(text)).html();
	}

	function fmt(text, n) {
		return String(text || '').replace('%d', n);
	}

	function emptyProfile() {
		return { order: [], sub_order: {}, hidden: [], sub_hidden: {} };
	}

	function copy(profile) {
		return JSON.parse(JSON.stringify(profile || emptyProfile()));
	}

	// The profile shown: the chosen one, or (read-only until changed) Everyone's.
	function shown() {
		return profiles[current] || profiles.everyone || emptyProfile();
	}

	// The first change to a role or person without a set-up starts from Everyone's.
	function editable() {
		if (!profiles[current]) {
			profiles[current] = copy(profiles.everyone);
		}
		var p = profiles[current];
		p.order = p.order || [];
		p.sub_order = p.sub_order && !Array.isArray(p.sub_order) ? p.sub_order : {};
		p.hidden = p.hidden || [];
		p.sub_hidden = p.sub_hidden && !Array.isArray(p.sub_hidden) ? p.sub_hidden : {};
		return p;
	}

	function store() {
		$json.val(JSON.stringify(profiles));
	}

	function sorted(items, saved) {
		var bySlug = {};
		items.forEach(function (item) {
			bySlug[item.slug] = item;
		});
		var out = [];
		(saved || []).forEach(function (slug) {
			if (bySlug[slug]) {
				out.push(bySlug[slug]);
				delete bySlug[slug];
			}
		});
		items.forEach(function (item) {
			if (bySlug[item.slug]) {
				out.push(item);
			}
		});
		return out;
	}

	function buttons(key, isLocked, hidden, canHide) {
		return (
			'<span class="aimp-am-buttons">' +
			'<button type="button" class="button-link aimp-am-move" data-move="-1" aria-label="' + esc(t.up) + '"><span class="dashicons dashicons-arrow-up-alt2"></span></button>' +
			'<button type="button" class="button-link aimp-am-move" data-move="1" aria-label="' + esc(t.down) + '"><span class="dashicons dashicons-arrow-down-alt2"></span></button>' +
			(canHide
				? '<button type="button" class="button-link aimp-am-eye" data-eye="' + esc(key) + '" aria-pressed="' + (hidden ? 'true' : 'false') + '"' +
				  (isLocked ? ' disabled title="' + esc(t.locked) + '"' : ' title="' + esc(t.toggle) + '"') + '>' +
				  '<span class="dashicons ' + (hidden ? 'dashicons-hidden' : 'dashicons-visibility') + '"></span></button>'
				: '') +
			'</span>'
		);
	}

	function render() {
		var p = shown();
		var hidden = p.hidden || [];
		var subHidden = p.sub_hidden || {};
		var subOrder = p.sub_order || {};
		var html = '';
		sorted(structure, p.order).forEach(function (item) {
			var isHidden = hidden.indexOf(item.slug) !== -1;
			var mainLocked = locked.indexOf(item.slug) !== -1;
			var subsHidden = subHidden[item.slug] || [];
			html += '<li class="aimp-am-item' + (item.separator ? ' is-separator' : '') + (isHidden ? ' is-hidden' : '') + '" data-slug="' + esc(item.slug) + '">';
			html += '<div class="aimp-am-row"><span class="aimp-am-handle dashicons dashicons-move" title="' + esc(t.drag) + '" aria-hidden="true"></span>';
			if (item.separator) {
				html += '<span class="aimp-am-title aimp-am-separator-label">' + esc(t.divider) + '</span>';
			} else {
				html += item.icon + '<span class="aimp-am-title">' + esc(item.title || item.slug) + '</span>';
			}
			if (item.subs && item.subs.length) {
				html +=
					'<button type="button" class="button-link aimp-am-fold" data-fold="' + esc(item.slug) + '" aria-expanded="' + (open[item.slug] ? 'true' : 'false') + '">' +
					esc(fmt(t.submenu, item.subs.length)) + ' <span class="dashicons dashicons-arrow-' + (open[item.slug] ? 'up' : 'down') + '"></span></button>' +
					(subsHidden.length ? '<span class="aimp-am-count">' + esc(fmt(t.hiddenCount, subsHidden.length)) + '</span>' : '');
			}
			html += buttons(item.slug, mainLocked, isHidden, !item.separator) + '</div>';
			if (item.subs && item.subs.length) {
				html += '<ol class="aimp-am-sublist" data-parent="' + esc(item.slug) + '"' + (open[item.slug] ? '' : ' hidden') + '>';
				sorted(item.subs, subOrder[item.slug]).forEach(function (sub) {
					var subIsHidden = subsHidden.indexOf(sub.slug) !== -1;
					var key = item.slug + '>' + sub.slug;
					html +=
						'<li class="aimp-am-item aimp-am-subitem' + (subIsHidden ? ' is-hidden' : '') + '" data-slug="' + esc(sub.slug) + '">' +
						'<div class="aimp-am-row"><span class="aimp-am-handle dashicons dashicons-move" title="' + esc(t.drag) + '" aria-hidden="true"></span>' +
						'<span class="aimp-am-title">' + esc(sub.title || sub.slug) + '</span>' +
						buttons(key, locked.indexOf(key) !== -1, subIsHidden, true) +
						'</div></li>';
				});
				html += '</ol>';
			}
			html += '</li>';
		});
		$list.html(html);

		if ($list.hasClass('ui-sortable')) {
			$list.sortable('refresh');
		} else {
			$list.sortable({ handle: '> .aimp-am-row .aimp-am-handle', items: '> li', axis: 'y', placeholder: 'aimp-am-placeholder', forcePlaceholderSize: true, update: readOrder });
		}
		$list.find('.aimp-am-sublist').each(function () {
			$(this).sortable({ handle: '.aimp-am-handle', items: '> li', axis: 'y', placeholder: 'aimp-am-placeholder', forcePlaceholderSize: true, update: readOrder });
		});

		status();
	}

	function status() {
		$root.find('[data-aimp-am-status]').text(current === 'everyone' ? '' : profiles[current] ? t.own : t.usesDefault);
		$root.find('[data-aimp-am-remove]').prop('hidden', current === 'everyone' || !profiles[current]);
		$root.find('[data-aimp-am-copy]').prop('hidden', current === 'everyone');
	}

	// The order on screen becomes the profile's order.
	function readOrder() {
		var p = editable();
		p.order = $list.children('li').map(function () {
			return $(this).attr('data-slug');
		}).get();
		p.sub_order = {};
		$list.find('.aimp-am-sublist').each(function () {
			p.sub_order[$(this).attr('data-parent')] = $(this).children('li').map(function () {
				return $(this).attr('data-slug');
			}).get();
		});
		store();
		status(); // The screen already shows the new order.
	}

	$list.on('click', '[data-move]', function () {
		var $item = $(this).closest('li');
		if (parseInt($(this).attr('data-move'), 10) < 0) {
			$item.prev('li').before($item);
		} else {
			$item.next('li').after($item);
		}
		readOrder();
	});

	$list.on('click', '[data-eye]', function () {
		var key = $(this).attr('data-eye');
		var p = editable();
		var parts = key.split('>');
		var listRef;
		if (parts.length > 1) {
			p.sub_hidden[parts[0]] = p.sub_hidden[parts[0]] || [];
			listRef = p.sub_hidden[parts[0]];
			key = parts.slice(1).join('>');
		} else {
			listRef = p.hidden;
		}
		var at = listRef.indexOf(key);
		if (at === -1) {
			listRef.push(key);
		} else {
			listRef.splice(at, 1);
		}
		store();
		render();
	});

	$list.on('click', '[data-fold]', function () {
		var slug = $(this).attr('data-fold');
		open[slug] = !open[slug];
		render();
	});

	$target.on('change', function () {
		current = $(this).val() || 'everyone';
		render();
	});

	// "Add a person…": adds them to the list and opens their (still empty) set-up.
	$root.on('change', '[data-aimp-am-person]', function () {
		var id = $(this).val();
		if (!id) {
			return;
		}
		var key = 'user:' + id;
		if (!$target.find('option[value="' + key + '"]').length) {
			var label = $(this).find('option:selected').text();
			$target.find('[data-aimp-am-people]').append($('<option>').val(key).text(label));
		}
		$target.val(key).trigger('change');
		$(this).val(null).trigger('change.select2');
	});

	$root.on('click', '[data-aimp-am-copy]', function () {
		profiles[current] = copy(profiles.everyone);
		store();
		render();
	});

	$root.on('click', '[data-aimp-am-remove]', function () {
		delete profiles[current];
		store();
		render();
	});

	$root.on('click', '[data-aimp-am-reset]', function () {
		// eslint-disable-next-line no-alert
		if (!window.confirm(t.resetConfirm || '')) {
			return;
		}
		$root.find('[data-aimp-am-reset-field]').val('1');
		$(this).closest('form').trigger('submit');
	});

	store();
	render();
});
