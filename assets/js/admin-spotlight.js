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
		var shown = form.querySelector('[data-shown]');
		var listed = form.querySelector('[data-listed]');
		var current = JSON.parse(form.getAttribute('data-current') || '{}');
		current.notions = current.notions || {};
		current.hide = current.hide || [];

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

		function chosenText(select) {
			var option = select.options[select.selectedIndex];
			return option ? option.textContent : '';
		}

		// One tick box per item of the kit (as AIMP_Spotlight::elements()): ticked = shown around the picture.
		function renderShown() {
			var items = [];
			if (parseInt(pattern.value, 10)) {
				items.push(['pattern', t.pattern || 'Pattern', chosenText(pattern)]);
			}
			var fabricRow = form.querySelector('[data-row="fabric"]');
			if (parseInt(fabric.value, 10) && fabricRow && !fabricRow.hidden) {
				items.push(['fabric', t.fabric || 'Fabric', chosenText(fabric)]);
			}
			var liningRow = form.querySelector('[data-row="lining"]');
			if (parseInt(lining.value, 10) && liningRow && !liningRow.hidden) {
				items.push(['lining', t.lining || 'Lining', chosenText(lining)]);
			}
			notions.querySelectorAll('select').forEach(function (select) {
				if (parseInt(select.value, 10)) {
					var label = select.parentNode.querySelector('span');
					items.push(['n_' + select.getAttribute('data-type'), label ? label.textContent : '', chosenText(select)]);
				}
			});
			shown.innerHTML = '';
			items.forEach(function (item) {
				var box = document.createElement('label');
				box.className = 'aimp-sp-shown-item';
				var input = document.createElement('input');
				input.type = 'checkbox';
				input.name = 'spot[show][]';
				input.value = item[0];
				input.checked = current.hide.indexOf(item[0]) === -1;
				input.addEventListener('change', function () {
					current.hide = current.hide.filter(function (key) {
						return key !== item[0];
					});
					if (!input.checked) {
						current.hide.push(item[0]);
					}
				});
				var strong = document.createElement('strong');
				strong.textContent = item[1];
				box.appendChild(input);
				box.appendChild(strong);
				box.appendChild(document.createTextNode(' ' + item[2]));
				shown.appendChild(box);
			});
			listed.value = items.map(function (item) {
				return item[0];
			}).join(',');
		}
		function fill(choices) {
			if (!choices) {
				row('fabric', false);
				row('lining', false);
				row('finish', false);
				notions.innerHTML = '';
				sizes.textContent = '';
				renderShown();
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
			renderShown();
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
			current = { notions: {}, hide: [] };
			fabric.value = '0';
			load();
		});
		fabric.addEventListener('change', load);
		// Another lining or haberdashery item: its element in the list.
		form.addEventListener('change', function (event) {
			if (event.target === lining || event.target.closest('[data-notions]')) {
				renderShown();
			}
		});
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
		// The sliders show their value.
		document.querySelectorAll('.aimp-sp-form [data-range]').forEach(function (range) {
			range.addEventListener('input', function () {
				var out = range.parentNode.querySelector('output');
				if (out) {
					out.textContent = range.value;
				}
			});
		});
	});
})();
