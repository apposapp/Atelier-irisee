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

		// The panel fades in and out through .is-open; "hidden" only keeps it away until this script runs.
		panel.hidden = false;

		function isOpen() {
			return panel.classList.contains('is-open');
		}

		function open() {
			panel.classList.add('is-open');
			if (toggle) {
				toggle.setAttribute('aria-expanded', 'true');
			}
			input.focus();
			if (input.value.trim().length < 2) {
				showRecent();
			}
		}

		// keepFocus: closed by clicking elsewhere, so the focus stays where the visitor clicked.
		function close(keepFocus) {
			panel.classList.remove('is-open');
			if (toggle) {
				toggle.setAttribute('aria-expanded', 'false');
				if (keepFocus !== true) {
					toggle.focus();
				}
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

		/* Recent searches: the last five, kept in this browser only. */
		var RECENT_KEY = 'aimp_recent_searches';

		function recentList() {
			try {
				var stored = JSON.parse(window.localStorage.getItem(RECENT_KEY) || '[]');
				return Array.isArray(stored) ? stored.slice(0, 5) : [];
			} catch (e) {
				return [];
			}
		}

		function remember(q) {
			q = (q || '').trim();
			if (q.length < 2) {
				return;
			}
			var recent = recentList().filter(function (other) {
				return other.toLowerCase() !== q.toLowerCase();
			});
			recent.unshift(q);
			try {
				window.localStorage.setItem(RECENT_KEY, JSON.stringify(recent.slice(0, 5)));
			} catch (e) {}
		}

		function allUrl(q) {
			return cfg.allUrl + (cfg.allUrl.indexOf('?') === -1 ? '?' : '&') + 'aimp_q=' + encodeURIComponent(q);
		}

		function heading(text) {
			return '<li class="aimp-search-heading" role="presentation">' + esc(text) + '</li>';
		}

		function showRecent() {
			var recent = recentList();
			active = -1;
			input.removeAttribute('aria-activedescendant');
			status.textContent = '';
			if (!recent.length) {
				list.hidden = true;
				list.innerHTML = '';
				input.setAttribute('aria-expanded', 'false');
				return;
			}
			list.innerHTML =
				heading(cfg.recent) +
				recent
					.map(function (q, i) {
						return (
							'<li role="option" id="aimp-search-recent-' + i + '" aria-selected="false">' +
							'<a class="aimp-search-recent" href="' + esc(allUrl(q)) + '" data-q="' + esc(q) + '" tabindex="-1">' + esc(q) + '</a></li>'
						);
					})
					.join('');
			list.hidden = false;
			input.setAttribute('aria-expanded', 'true');
		}

		function show(q, data) {
			active = -1;
			input.removeAttribute('aria-activedescendant');
			var categories = data.categories || [];
			if (!data.items.length && !categories.length) {
				list.hidden = true;
				list.innerHTML = '';
				input.setAttribute('aria-expanded', 'false');
				status.innerHTML = esc(cfg.none.replace('%s', q)) +
					(data.suggest ? ' ' + esc(cfg.didYouMean).replace('%s', '<button type="button" class="aimp-search-suggest" data-q="' + esc(data.suggest) + '">' + esc(data.suggest) + '</button>') : '');
				return;
			}
			status.textContent = '';
			var html = '';
			if (categories.length) {
				html += heading(cfg.categories) + categories
					.map(function (cat, i) {
						return (
							'<li role="option" id="aimp-search-category-' + i + '" aria-selected="false">' +
							'<a class="aimp-search-category" href="' + esc(cat.url) + '" tabindex="-1">' +
							'<span class="aimp-search-name">' + esc(cat.name) + '</span> <span class="aimp-search-count">(' + parseInt(cat.count, 10) + ')</span>' +
							'</a></li>'
						);
					})
					.join('');
			}
			if (data.items.length) {
				html += (categories.length ? heading(cfg.products) : '') + data.items
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
					.join('');
			}
			list.innerHTML = html +
				'<li role="option" id="aimp-search-option-all" aria-selected="false" class="aimp-search-all-item">' +
				'<a class="aimp-search-all" href="' + esc(data.all) + '" tabindex="-1">' + esc(cfg.all) + ' →</a></li>';
			list.hidden = false;
			input.setAttribute('aria-expanded', 'true');
		}

		// A recent search or "Did you mean …": search for it here.
		function searchFor(q) {
			input.value = q;
			input.focus();
			run();
		}

		panel.addEventListener('click', function (e) {
			var target = e.target.closest ? e.target.closest('[data-q]') : null;
			if (target && panel.contains(target)) {
				e.preventDefault();
				searchFor(target.getAttribute('data-q'));
				return;
			}
			// Going to a result: remember what was searched for.
			if (e.target.closest && e.target.closest('.aimp-search-results a')) {
				remember(input.value);
			}
		});

		function run() {
			var q = input.value.trim();
			if (q === last) {
				return;
			}
			last = q;
			if (q.length < 2) {
				showRecent();
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
				if (!isOpen()) {
					open();
				} else {
					close();
				}
			});
		}
		panel.querySelector('[data-aimp-search-close]').addEventListener('click', function () {
			close();
		});

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
				var chosen = options()[active].querySelector('a');
				if (chosen.hasAttribute('data-q')) {
					searchFor(chosen.getAttribute('data-q'));
					return;
				}
				remember(input.value);
				window.location.href = chosen.href;
			} else if (e.key === 'Escape') {
				e.preventDefault();
				close();
			}
		});

		// Enter without a chosen result: all results (the form's own address, ?aimp_q=…).
		form.addEventListener('submit', function (e) {
			if (input.value.trim().length < 2) {
				e.preventDefault();
				return;
			}
			remember(input.value);
		});

		document.addEventListener('click', function (e) {
			if (isOpen() && !panel.contains(e.target) && !(toggle && toggle.contains(e.target))) {
				close(true);
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
