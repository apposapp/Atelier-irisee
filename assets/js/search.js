/**
 * Search in the header: the magnifier opens a field under the header; results appear while typing.
 * ↑ / ↓ choose a result, Enter opens it (or all results), Esc closes.
 */
(function () {
	'use strict';

	var cfg = window.aimpSearch;
	if (!cfg) {
		return;
	}

	function esc(text) {
		var div = document.createElement('div');
		div.textContent = text == null ? '' : String(text);
		return div.innerHTML;
	}

	function setup(panel) {
		var toggle = document.querySelector('[data-aimp-search-toggle]');
		var form = panel.querySelector('form');
		var input = panel.querySelector('.aimp-search-input');
		var list = panel.querySelector('.aimp-search-results');
		var status = panel.querySelector('.aimp-search-status');
		var timer = null;
		var last = '';
		var active = -1;
		var cache = {};

		function open() {
			panel.hidden = false;
			if (toggle) {
				toggle.setAttribute('aria-expanded', 'true');
			}
			input.focus();
		}

		function close() {
			panel.hidden = true;
			if (toggle) {
				toggle.setAttribute('aria-expanded', 'false');
				toggle.focus();
			}
		}

		function options() {
			return list.querySelectorAll('[role="option"]');
		}

		function mark(index) {
			var items = options();
			active = items.length ? (index + items.length) % items.length : -1;
			items.forEach(function (item, i) {
				item.setAttribute('aria-selected', i === active ? 'true' : 'false');
				item.classList.toggle('is-active', i === active);
			});
			if (active >= 0) {
				input.setAttribute('aria-activedescendant', items[active].id);
				items[active].scrollIntoView({ block: 'nearest' });
			} else {
				input.removeAttribute('aria-activedescendant');
			}
		}

		function show(q, data) {
			active = -1;
			input.removeAttribute('aria-activedescendant');
			if (!data.items.length) {
				list.hidden = true;
				list.innerHTML = '';
				input.setAttribute('aria-expanded', 'false');
				status.textContent = cfg.none.replace('%s', q);
				return;
			}
			status.textContent = '';
			list.innerHTML =
				data.items
					.map(function (item, i) {
						return (
							'<li role="option" id="aimp-search-option-' + i + '" aria-selected="false">' +
							'<a class="aimp-search-result" href="' + esc(item.url) + '" tabindex="-1">' +
							'<img src="' + esc(item.image) + '" alt="" loading="lazy">' +
							'<span class="aimp-search-name">' + esc(item.name) + '</span>' +
							'<span class="aimp-search-price">' + (item.price_html || '') + '</span>' +
							'</a></li>'
						);
					})
					.join('') +
				'<li role="option" id="aimp-search-option-all" aria-selected="false" class="aimp-search-all-item">' +
				'<a class="aimp-search-all" href="' + esc(data.all) + '" tabindex="-1">' + esc(cfg.all) + ' →</a></li>';
			list.hidden = false;
			input.setAttribute('aria-expanded', 'true');
		}

		function run() {
			var q = input.value.trim();
			if (q === last) {
				return;
			}
			last = q;
			if (q.length < 2) {
				list.hidden = true;
				list.innerHTML = '';
				status.textContent = '';
				input.setAttribute('aria-expanded', 'false');
				return;
			}
			if (cache[q]) {
				show(q, cache[q]);
				return;
			}
			status.textContent = cfg.loading;
			var url = cfg.endpoint + (cfg.endpoint.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q);
			fetch(url, { credentials: 'same-origin' })
				.then(function (response) {
					return response.json();
				})
				.then(function (json) {
					if (input.value.trim() !== q || !json || !json.success) {
						return;
					}
					cache[q] = json.data;
					show(q, json.data);
				})
				.catch(function () {
					status.textContent = '';
				});
		}

		if (toggle) {
			toggle.addEventListener('click', function () {
				if (panel.hidden) {
					open();
				} else {
					close();
				}
			});
		}
		panel.querySelector('[data-aimp-search-close]').addEventListener('click', close);

		input.addEventListener('input', function () {
			clearTimeout(timer);
			timer = setTimeout(run, 250);
		});

		input.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				if (!list.hidden) {
					e.preventDefault();
					mark(active + (e.key === 'ArrowDown' ? 1 : -1));
				}
			} else if (e.key === 'Enter' && active >= 0) {
				e.preventDefault();
				window.location.href = options()[active].querySelector('a').href;
			} else if (e.key === 'Escape') {
				e.preventDefault();
				close();
			}
		});

		// Enter without a chosen result: all results (the form's own address, ?aimp_q=…).
		form.addEventListener('submit', function (e) {
			if (input.value.trim().length < 2) {
				e.preventDefault();
			}
		});

		document.addEventListener('click', function (e) {
			if (!panel.hidden && !panel.contains(e.target) && !(toggle && toggle.contains(e.target))) {
				close();
			}
		});
	}

	function boot() {
		document.querySelectorAll('[data-aimp-search]').forEach(function (panel) {
			if (!panel.aimpReady) {
				panel.aimpReady = true;
				setup(panel);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
