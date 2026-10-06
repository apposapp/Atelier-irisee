/**
 * Atelier Irisee favorites: heart buttons everywhere in the shop and the favorites page.
 *
 * The heart states are loaded with one request per page, so they are correct on cached pages too.
 * Exposes window.aimpFavorites.button(id) and .refresh(root) for the configurator.
 */
(function () {
	'use strict';

	var cfg = window.aimpFavoritesConfig;
	if (!cfg) {
		return;
	}

	var ids = null;
	var loading = null;
	var toastTimer = null;

	/* ---------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------- */

	// Same language as the configurator: the customer's choice (cookie), otherwise the plugin setting.
	function lang() {
		var match = document.cookie.match(/(?:^|;\s*)aimp_lang=([a-z]+)/);
		if (match && cfg.i18n[match[1]]) {
			return match[1];
		}
		return cfg.i18n[cfg.defaultLanguage] ? cfg.defaultLanguage : Object.keys(cfg.i18n)[0];
	}

	function t() {
		return cfg.i18n[lang()];
	}

	function esc(value) {
		return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function request(action, data) {
		var body = new URLSearchParams();
		Object.keys(data || {}).forEach(function (key) {
			body.append(key, data[key]);
		});
		body.append('aimp_lang', lang());
		return fetch(cfg.endpoint.replace('%%endpoint%%', 'aimp_' + action), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (response) {
				return response.json().catch(function () {
					return null;
				});
			})
			.then(function (json) {
				if (!json || !json.success) {
					var errors = (json && json.data && json.data.errors) || [t().error];
					throw new Error(errors.join(' '));
				}
				return json.data;
			});
	}

	function load() {
		if (!loading) {
			loading = request('favorites', {})
				.then(function (data) {
					ids = data.ids || [];
					return ids;
				})
				.catch(function () {
					ids = [];
					return ids;
				});
		}
		return loading;
	}

	function isFavorite(id) {
		return !!ids && ids.indexOf(id) !== -1;
	}

	/* ---------------------------------------------------------------
	 * Heart buttons
	 * ------------------------------------------------------------- */

	function paint(btn) {
		var id = parseInt(btn.getAttribute('data-aimp-fav'), 10);
		var on = isFavorite(id);
		var label = on ? t().remove : t().add;
		btn.classList.toggle('is-favorite', on);
		btn.setAttribute('aria-pressed', on ? 'true' : 'false');
		btn.setAttribute('aria-label', label);
		btn.title = label;
		var text = btn.querySelector('.aimp-fav-label');
		var wanted = on ? t().inFavorites : t().add;
		// Only touch the text when it changes: replacing it would wake the MutationObserver again.
		if (text && text.textContent !== wanted) {
			text.textContent = wanted;
		}
	}

	function refresh(root) {
		if (ids === null) {
			load().then(function () {
				refresh(root);
			});
			return;
		}
		(root || document).querySelectorAll('[data-aimp-fav]').forEach(paint);
		// Number of favorites in the header.
		document.querySelectorAll('[data-aimp-fav-count]').forEach(function (badge) {
			badge.textContent = ids.length;
			badge.hidden = !ids.length;
		});
	}

	function button(id) {
		return (
			'<button type="button" class="aimp-fav" data-aimp-fav="' + parseInt(id, 10) + '" aria-pressed="false" aria-label="' + esc(t().add) + '">' +
			cfg.icon +
			'</button>'
		);
	}

	function toast(message, withLink) {
		var el = document.querySelector('.aimp-fav-toast');
		if (!el) {
			el = document.createElement('div');
			el.className = 'aimp-fav-toast';
			el.setAttribute('role', 'status');
			el.setAttribute('aria-live', 'polite');
			document.body.appendChild(el);
		}
		el.innerHTML =
			'<span>' + esc(message) + '</span>' +
			(withLink && cfg.favoritesUrl ? ' <a href="' + esc(cfg.favoritesUrl) + '">' + esc(t().view) + '</a>' : '');
		el.classList.add('is-visible');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () {
			el.classList.remove('is-visible');
		}, 3500);
	}

	function toggle(btn) {
		var id = parseInt(btn.getAttribute('data-aimp-fav'), 10);
		if (!id || btn.disabled) {
			return;
		}
		btn.disabled = true;
		request('favorite_toggle', { product_id: id, nonce: cfg.nonce })
			.then(function (data) {
				ids = data.ids || [];
				refresh(document);
				toast(data.favorite ? t().added : t().removed, data.favorite);
				if (!data.favorite) {
					removeFromPage(id);
				}
			})
			.catch(function (err) {
				toast(err.message || t().error, false);
			})
			.then(function () {
				btn.disabled = false;
			});
	}

	/* ---------------------------------------------------------------
	 * Favorites page ([atelier_irisee_favorites])
	 * ------------------------------------------------------------- */

	function emptyHtml() {
		return (
			'<p class="aimp-fav-empty">' + esc(t().empty) + '</p>' +
			(cfg.shopUrl ? '<p><a class="aimp-fav-card-action button" href="' + esc(cfg.shopUrl) + '">' + esc(t().browse) + '</a></p>' : '')
		);
	}

	function loadPage(container) {
		container.innerHTML = '<p class="aimp-fav-loading">' + esc(t().loading) + '</p>';
		request('favorites_list', {})
			.then(function (data) {
				container.innerHTML = data.html || emptyHtml();
				refresh(container);
			})
			.catch(function () {
				container.innerHTML = '<p class="aimp-fav-empty">' + esc(t().error) + '</p>';
			});
	}

	function removeFromPage(id) {
		document.querySelectorAll('[data-aimp-favorites-page]').forEach(function (container) {
			var card = container.querySelector('[data-aimp-fav-card="' + id + '"]');
			if (card) {
				card.parentNode.removeChild(card);
			}
			if (!container.querySelector('[data-aimp-fav-card]')) {
				container.innerHTML = emptyHtml();
			}
		});
	}

	/* ---------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------- */

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-aimp-fav]') : null;
		if (!btn) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		toggle(btn);
	});

	function boot() {
		refresh(document);
		document.querySelectorAll('[data-aimp-favorites-page]').forEach(loadPage);

		// Product grids loaded later (filters, infinite scroll) get their hearts marked too.
		if (window.MutationObserver) {
			var scheduled = false;
			new MutationObserver(function () {
				if (scheduled) {
					return;
				}
				scheduled = true;
				window.requestAnimationFrame(function () {
					scheduled = false;
					refresh(document);
				});
			}).observe(document.body, { childList: true, subtree: true });
		}
	}

	window.aimpFavorites = {
		button: button,
		refresh: refresh
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();

/**
 * Favorites page: remove a saved sewing project kit (×).
 */
(function () {
	'use strict';

	document.addEventListener('click', function (e) {
		var button = e.target.closest ? e.target.closest('[data-aimp-kit-remove]') : null;
		if (!button) {
			return;
		}
		var section = button.closest('[data-aimp-saved-kits]');
		var cfg = window.aimpFavoritesConfig || {};
		var body = new URLSearchParams();
		body.append('kit', button.getAttribute('data-aimp-kit-remove'));
		body.append('nonce', cfg.nonce || '');
		button.disabled = true;
		fetch(section.getAttribute('data-endpoint'), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				if (!json || !json.success) {
					button.disabled = false;
					return;
				}
				button.closest('[data-aimp-saved-kit]').remove();
				if (!section.querySelector('[data-aimp-saved-kit]')) {
					var next = section.nextElementSibling;
					if (next && next.classList.contains('aimp-saved-kits-title')) {
						next.remove();
					}
					section.remove();
				}
			})
			.catch(function () {
				button.disabled = false;
			});
	});
})();
