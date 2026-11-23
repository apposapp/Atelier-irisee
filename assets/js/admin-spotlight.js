/**
 * Atelier Irisee → Kit spotlights: the fabric, lining, finish and haberdashery lists follow the chosen pattern (and
 * the fabric: its own linings and interfacing), pictures from the media library, copying the shortcode.
 */
(function () {
	'use strict';

	var cfg = window.aimpSpotlight || { i18n: {} };
	var t = cfg.i18n || {};

	function option(value, label, selected) {
		var node = document.createElement('option');
		node.value = String(value);
		node.textContent = label;
		node.selected = !!selected;
		return node;
	}

	function fillSelect(select, items, current, withNone) {
		select.innerHTML = '';
		if (withNone) {
			select.appendChild(option(0, t.none || '—', !current));
		}
		items.forEach(function (item) {
			select.appendChild(option(item.id, item.name, String(item.id) === String(current)));
		});
	}

	function setupForm(form) {
		var pattern = form.querySelector('[data-pattern]');
		var fabric = form.querySelector('[data-fabric]');
		var lining = form.querySelector('[data-lining]');
		var finish = form.querySelector('[data-finish]');
		var notions = form.querySelector('[data-notions]');
		var sizes = form.querySelector('[data-sizes]');
		var current = JSON.parse(form.getAttribute('data-current') || '{}');
		current.notions = current.notions || {};

		function row(name, show) {
			var tr = form.querySelector('[data-row="' + name + '"]');
			if (tr) {
				tr.hidden = !show;
			}
		}

		// Keep what was chosen (also after a reload of the lists).
		function remember() {
			current.fabric = fabric.value;
			current.lining = lining.value;
			current.finish = finish.value;
			notions.querySelectorAll('select').forEach(function (select) {
				current.notions[select.getAttribute('data-type')] = select.value;
			});
		}

		function fill(choices) {
			if (!choices) {
				row('fabric', false);
				row('lining', false);
				row('finish', false);
				notions.innerHTML = '';
				sizes.textContent = '';
				return;
			}
			fillSelect(fabric, choices.fabrics, current.fabric, true);
			row('fabric', choices.fabrics.length > 0);
			fillSelect(lining, choices.linings, current.lining, true);
			row('lining', choices.linings.length > 0);
			fillSelect(finish, choices.finish, current.finish, false);
			row('finish', choices.finish.length > 0);
			notions.innerHTML = '';
			choices.notions.forEach(function (group) {
				var label = document.createElement('label');
				label.className = 'aimp-sp-notion';
				var span = document.createElement('span');
				span.textContent = group.label;
				var select = document.createElement('select');
				select.name = 'spot[notions][' + group.type + ']';
				select.setAttribute('data-type', group.type);
				fillSelect(select, group.items, current.notions[group.type], true);
				label.appendChild(span);
				label.appendChild(select);
				notions.appendChild(label);
			});
			sizes.textContent = choices.sizes ? (sizes.getAttribute('data-label') || '') + choices.sizes : '';
		}

		function load() {
			remember();
			if (!parseInt(pattern.value, 10)) {
				fill(null);
				return;
			}
			form.classList.add('is-loading');
			var body = new FormData();
			body.append('action', 'aimp_spotlight_choices');
			body.append('nonce', cfg.nonce);
			body.append('size', pattern.value);
			body.append('fabric', fabric.value || current.fabric || 0);
			fetch(cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) {
					return r.json();
				})
				.then(function (json) {
					form.classList.remove('is-loading');
					fill(json && json.success ? json.data : null);
				})
				.catch(function () {
					form.classList.remove('is-loading');
				});
		}

		fill(JSON.parse(form.getAttribute('data-choices') || 'null'));
		pattern.addEventListener('change', function () {
			// Another pattern: start from scratch.
			current = { notions: {} };
			fabric.value = '0';
			load();
		});
		fabric.addEventListener('change', load);
	}

	function setupPictures() {
		document.querySelectorAll('[data-pic]').forEach(function (box) {
			var input = box.querySelector('input');
			var preview = box.querySelector('.aimp-sp-pic-preview');
			box.querySelector('[data-pic-choose]').addEventListener('click', function () {
				if (!window.wp || !window.wp.media) {
					return;
				}
				var frame = window.wp.media({ title: t.pick, multiple: false, library: { type: 'image' } });
				frame.on('select', function () {
					var picture = frame.state().get('selection').first().toJSON();
					input.value = picture.id;
					var url = picture.sizes && picture.sizes.thumbnail ? picture.sizes.thumbnail.url : picture.url;
					preview.innerHTML = '';
					var img = document.createElement('img');
					img.src = url;
					img.alt = '';
					preview.appendChild(img);
				});
				frame.open();
			});
			box.querySelector('[data-pic-clear]').addEventListener('click', function (event) {
				input.value = '0';
				preview.innerHTML = '';
				var em = document.createElement('em');
				em.textContent = event.currentTarget.getAttribute('data-empty');
				preview.appendChild(em);
			});
		});
	}

	function setupCopy() {
		document.querySelectorAll('[data-copy]').forEach(function (button) {
			button.addEventListener('click', function () {
				var text = button.getAttribute('data-copy');
				var done = function () {
					var old = button.textContent;
					button.textContent = t.copied || 'OK';
					window.setTimeout(function () {
						button.textContent = old;
					}, 1500);
				};
				if (navigator.clipboard) {
					navigator.clipboard.writeText(text).then(done, function () {});
				}
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.aimp-sp-form').forEach(setupForm);
		setupPictures();
		setupCopy();
	});
})();
