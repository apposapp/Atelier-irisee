/**
 * Atelier Irisee shared front-end building blocks (window.aimpUI).
 *
 * Used by the configurator, the shop pages and the product pages, so product cards, galleries,
 * pagination and the language switcher look and behave the same everywhere.
 */
(function () {
	'use strict';

	var LANG_KEY = 'aimp_lang';

	function esc(value) {
		return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	// Minimal sprintf for "%s", "%d", "%1$s", "%2$d".
	function fmt(str) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return String(str).replace(/%(\d+\$)?[ds]/g, function (m, pos) {
			var idx = pos ? parseInt(pos, 10) - 1 : i++;
			return args[idx];
		});
	}

	/**
	 * @param {number} amount
	 * @param {Object} c { symbol, position, decimals, decimal, thousand }
	 */
	function money(amount, c) {
		var parts = Number(amount).toFixed(c.decimals).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousand);
		var value = parts.join(c.decimal);
		switch (c.position) {
			case 'right':
				return value + c.symbol;
			case 'left_space':
				return c.symbol + ' ' + value;
			case 'right_space':
				return value + ' ' + c.symbol;
			default:
				return c.symbol + value;
		}
	}

	// Page numbers with gaps: 1 … 4 5 6 … 12.
	function pageList(page, pages) {
		var list = [];
		for (var p = 1; p <= pages; p++) {
			if (p === 1 || p === pages || Math.abs(p - page) <= 1) {
				list.push(p);
			} else if (list[list.length - 1] !== '…') {
				list.push('…');
			}
		}
		return list;
	}

	function findById(items, id) {
		return (items || []).filter(function (item) {
			return item.id === id;
		})[0];
	}

	function scrollIntoViewIfNeeded(el) {
		if (!el) {
			return;
		}
		var rect = el.getBoundingClientRect();
		if (rect.top < 0 || rect.top > window.innerHeight * 0.6) {
			el.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}

	/* ---------------------------------------------------------------
	 * Language
	 * ------------------------------------------------------------- */

	/**
	 * The language to start with: cookie, then this browser's last choice, then the shop default.
	 *
	 * @param {Object} i18n            language code => strings
	 * @param {string} defaultLanguage
	 */
	function initialLang(i18n, defaultLanguage) {
		var match = document.cookie.match(/(?:^|;\s*)aimp_lang=([a-z]+)/);
		if (match && i18n[match[1]]) {
			return match[1];
		}
		try {
			var stored = window.localStorage.getItem(LANG_KEY);
			if (stored && i18n[stored]) {
				return stored;
			}
		} catch (e) {}
		return i18n[defaultLanguage] ? defaultLanguage : Object.keys(i18n)[0];
	}

	// The cookie lets the cart, checkout and product pages show the plugin's texts in the same language.
	function persistLang(code) {
		document.cookie = LANG_KEY + '=' + code + '; path=/; max-age=31536000; SameSite=Lax';
		try {
			window.localStorage.setItem(LANG_KEY, code);
		} catch (e) {}
	}

	function languageInfo(languages, code) {
		return (languages || []).filter(function (l) {
			return l.code === code;
		})[0];
	}

	// Flag buttons; each has data-lang with the language code.
	function languagesHtml(languages, current) {
		return (languages || [])
			.map(function (l) {
				var active = l.code === current;
				return (
					'<button type="button" class="aimp-language' + (active ? ' is-active' : '') + '" data-lang="' + esc(l.code) + '"' +
					' lang="' + esc(l.locale) + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
					'<img src="' + esc(l.flag) + '" alt="" width="24" height="16">' +
					'<span>' + esc(l.name) + '</span>' +
					'</button>'
				);
			})
			.join('');
	}

	/* ---------------------------------------------------------------
	 * Requests to ?wc-ajax=aimp_*
	 * ------------------------------------------------------------- */

	/**
	 * @param {string} endpoint  WC_AJAX endpoint with %%endpoint%% placeholder
	 * @param {string} action    Without the aimp_ prefix
	 * @param {Object} data
	 * @param {string} lang
	 * @param {string} errorText Shown when the server gives no message
	 */
	function request(endpoint, action, data, lang, errorText) {
		var body = new URLSearchParams();
		Object.keys(data || {}).forEach(function (key) {
			body.append(key, data[key]);
		});
		body.append('aimp_lang', lang);
		return fetch(endpoint.replace('%%endpoint%%', 'aimp_' + action), {
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
					var errors = (json && json.data && json.data.errors) || [errorText];
					var error = new Error(errors.join(' '));
					error.errors = errors;
					throw error;
				}
				return json.data;
			});
	}

	function errorHtml(err, fallback) {
		var errors = (err && err.errors) || [fallback];
		return (
			'<div class="aimp-notice aimp-notice--error"><ul>' +
			errors
				.map(function (e) {
					return '<li>' + esc(e) + '</li>';
				})
				.join('') +
			'</ul></div>'
		);
	}

	/* ---------------------------------------------------------------
	 * Product grid with pagination
	 * ------------------------------------------------------------- */

	/**
	 * @param {Object} data { items, page, pages, total, per_page }
	 * @param {Object} t    strings: pagination, previous, next, showing
	 */
	function paginationHtml(data, t) {
		var html = '';
		if (data.pages > 1) {
			html += '<nav class="aimp-pagination" aria-label="' + esc(t.pagination) + '">';
			html +=
				'<button type="button" class="aimp-page aimp-page--nav" data-page="' + (data.page - 1) + '"' +
				(data.page <= 1 ? ' disabled' : '') + ' aria-label="' + esc(t.previous) + '">‹</button>';
			pageList(data.page, data.pages).forEach(function (p) {
				if (p === '…') {
					html += '<span class="aimp-page-gap" aria-hidden="true">…</span>';
				} else if (p === data.page) {
					html += '<button type="button" class="aimp-page is-current" aria-current="page" disabled>' + p + '</button>';
				} else {
					html += '<button type="button" class="aimp-page" data-page="' + p + '">' + p + '</button>';
				}
			});
			html +=
				'<button type="button" class="aimp-page aimp-page--nav" data-page="' + (data.page + 1) + '"' +
				(data.page >= data.pages ? ' disabled' : '') + ' aria-label="' + esc(t.next) + '">›</button>';
			html += '</nav>';
		}
		if (data.total > 0 && data.per_page > 0) {
			var from = (data.page - 1) * data.per_page + 1;
			var to = Math.min(data.total, from + data.items.length - 1);
			html += '<p class="aimp-results-count">' + esc(fmt(t.showing, from, to, data.total)) + '</p>';
		}
		return html;
	}

	/**
	 * Fill a container with product cards.
	 *
	 * @param {Element} container
	 * @param {Object}  data   { items, page, pages, total, per_page } or null while loading
	 * @param {Object}  opts   { selectedId, onSelect(item), onPage(page), emptyText, unavailableText, compact, priceSuffix }
	 *                         An item may carry its own price_suffix and badge.
	 * @param {Object}  t      strings: loading, selected + the pagination strings
	 */
	function renderGrid(container, data, opts, t) {
		if (!data) {
			container.innerHTML = '<p class="aimp-loading">' + esc(t.loading) + '</p>';
			return;
		}
		if (!data.items.length) {
			container.innerHTML = '<p class="aimp-empty">' + esc(opts.emptyText) + '</p>';
			return;
		}

		var html = '<div class="aimp-grid' + (opts.compact ? ' aimp-grid--compact' : '') + '">';
		data.items.forEach(function (item) {
			var selected = opts.selectedId === item.id;
			var unavailable = item.available === false;
			var suffix = item.price_suffix || opts.priceSuffix;
			html +=
				'<button type="button" class="aimp-card' + (selected ? ' is-selected' : '') + (unavailable ? ' is-unavailable' : '') + '"' +
				' data-id="' + esc(item.id) + '" aria-pressed="' + (selected ? 'true' : 'false') + '"' +
				(unavailable ? ' disabled' : '') + '>' +
				'<span class="aimp-card-image"><img src="' + esc(item.image) + '" alt="' + esc(item.image_alt || item.name) + '" loading="lazy"></span>' +
				'<span class="aimp-card-name">' + esc(item.name) + '</span>' +
				'<span class="aimp-card-price">' + (item.price_html || '') +
				(suffix && item.price_html ? ' <small>' + esc(suffix) + '</small>' : '') + '</span>' +
				(unavailable ? '<span class="aimp-badge">' + esc(opts.unavailableText || '') + '</span>' : '') +
				(!unavailable && item.badge ? '<span class="aimp-badge">' + esc(item.badge) + '</span>' : '') +
				(selected ? '<span class="aimp-badge aimp-badge--selected">' + esc(t.selected) + '</span>' : '') +
				'</button>';
		});
		html += '</div>';
		html += paginationHtml(data, t);
		container.innerHTML = html;

		container.querySelectorAll('.aimp-card').forEach(function (card) {
			card.addEventListener('click', function () {
				var item = findById(data.items, parseInt(card.getAttribute('data-id'), 10));
				if (item) {
					opts.onSelect(item);
				}
			});
		});
		container.querySelectorAll('[data-page]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				opts.onPage(parseInt(btn.getAttribute('data-page'), 10));
				scrollIntoViewIfNeeded(container);
			});
		});
	}

	/* ---------------------------------------------------------------
	 * Gallery: one big picture with selectable thumbnails
	 * ------------------------------------------------------------- */

	/**
	 * @param {Array}  images  [{ thumb, large, alt }]
	 * @param {number} index
	 * @param {Object} t       strings: showPicture
	 * @param {string} overlay Optional HTML placed on the big picture (e.g. the favorite heart)
	 */
	function galleryHtml(images, index, t, overlay) {
		images = images && images.length ? images : [];
		if (!images.length) {
			return '';
		}
		var current = images[index] || images[0];
		var html =
			'<div class="aimp-gallery">' +
			'<div class="aimp-gallery-main' + (overlay ? ' aimp-fav-wrap' : '') + '"><img src="' + esc(current.large) + '" alt="' + esc(current.alt) + '">' +
			(overlay || '') + '</div>';
		if (images.length > 1) {
			html += '<div class="aimp-gallery-thumbs">';
			images.forEach(function (image, i) {
				var active = image === current;
				html +=
					'<button type="button" class="aimp-thumb' + (active ? ' is-active' : '') + '" data-index="' + i + '"' +
					' aria-pressed="' + (active ? 'true' : 'false') + '" aria-label="' + esc(fmt(t.showPicture, i + 1)) + '">' +
					'<img src="' + esc(image.thumb) + '" alt="" loading="lazy">' +
					'</button>';
			});
			html += '</div>';
		}
		return html + '</div>';
	}

	// Switch the big picture when a thumbnail is clicked. Returns a function to show picture i.
	function bindGallery(container, images, onChange) {
		var gallery = container.querySelector('.aimp-gallery');
		if (!gallery) {
			return function () {};
		}
		var main = gallery.querySelector('.aimp-gallery-main img');
		var show = function (i) {
			var image = images[i];
			if (!image) {
				return;
			}
			main.src = image.large;
			main.alt = image.alt;
			gallery.querySelectorAll('.aimp-thumb').forEach(function (other) {
				var active = parseInt(other.getAttribute('data-index'), 10) === i;
				other.classList.toggle('is-active', active);
				other.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
			if (onChange) {
				onChange(i);
			}
		};
		gallery.querySelectorAll('.aimp-thumb').forEach(function (thumb) {
			thumb.addEventListener('click', function () {
				show(parseInt(thumb.getAttribute('data-index'), 10));
			});
		});
		return show;
	}

	/* ---------------------------------------------------------------
	 * Lightbox (<dialog>)
	 * ------------------------------------------------------------- */

	/**
	 * Open a full-screen picture viewer.
	 *
	 * @param {Element} host   Element the dialog is added to (keeps the plugin's styles)
	 * @param {Array}   images [{ large, alt }] or [{ src, alt }]
	 * @param {number}  index
	 * @param {Object}  t      strings: close, previous, next
	 */
	function openLightbox(host, images, index, t) {
		var dialog = host.querySelector(':scope > .aimp-lightbox');
		if (!dialog) {
			dialog = document.createElement('dialog');
			dialog.className = 'aimp-lightbox';
			dialog.innerHTML =
				'<button type="button" class="aimp-button aimp-lightbox-close" data-action="close">×</button>' +
				'<button type="button" class="aimp-lightbox-nav aimp-lightbox-nav--prev" data-action="prev">‹</button>' +
				'<img class="aimp-lightbox-image" alt="">' +
				'<button type="button" class="aimp-lightbox-nav aimp-lightbox-nav--next" data-action="next">›</button>';
			host.appendChild(dialog);
			dialog.addEventListener('click', function (e) {
				// A click on the dark backdrop lands on the dialog element itself.
				var action = e.target === dialog ? 'close' : e.target.getAttribute('data-action');
				if (action === 'close') {
					if (typeof dialog.close === 'function') {
						dialog.close();
					} else {
						dialog.removeAttribute('open');
					}
				} else if (action === 'prev' || action === 'next') {
					dialog.aimpShow(dialog.aimpIndex + (action === 'next' ? 1 : -1));
				}
			});
			dialog.addEventListener('keydown', function (e) {
				if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
					dialog.aimpShow(dialog.aimpIndex + (e.key === 'ArrowRight' ? 1 : -1));
				}
			});
		}

		var img = dialog.querySelector('.aimp-lightbox-image');
		var several = images.length > 1;
		dialog.aimpShow = function (i) {
			var count = images.length;
			dialog.aimpIndex = ((i % count) + count) % count;
			var image = images[dialog.aimpIndex];
			img.src = image.large || image.src;
			img.alt = image.alt || '';
		};
		dialog.querySelectorAll('.aimp-lightbox-nav').forEach(function (btn) {
			btn.hidden = !several;
		});
		[['close', t.close], ['prev', t.previous], ['next', t.next]].forEach(function (pair) {
			var btn = dialog.querySelector('[data-action="' + pair[0] + '"]');
			btn.setAttribute('aria-label', pair[1]);
			btn.title = pair[1];
		});
		dialog.aimpShow(index || 0);

		if (typeof dialog.showModal === 'function') {
			dialog.showModal();
		} else {
			dialog.setAttribute('open', '');
		}
		return dialog;
	}

	/* ---------------------------------------------------------------
	 * Layout upkeep
	 * ------------------------------------------------------------- */

	/**
	 * Side panels have no scrollbar of their own: they follow the page while scrolling only when they
	 * fit on the screen; taller panels scroll along with the page.
	 */
	function fitSidePanels(root) {
		root.querySelectorAll('.aimp-split-side').forEach(function (panel) {
			var top = parseFloat(window.getComputedStyle(panel).top) || 0;
			panel.classList.toggle('is-tall', panel.offsetHeight > window.innerHeight - top * 2);
		});
	}

	/**
	 * After every change inside root (and when pictures load or the window resizes):
	 * fit the side panels and mark the favorite hearts.
	 */
	function watchLayout(root) {
		var scheduled = false;
		var schedule = function () {
			if (scheduled) {
				return;
			}
			scheduled = true;
			window.requestAnimationFrame(function () {
				scheduled = false;
				fitSidePanels(root);
				if (window.aimpFavorites) {
					window.aimpFavorites.refresh(root);
				}
			});
		};
		if (window.MutationObserver) {
			new MutationObserver(schedule).observe(root, { childList: true, subtree: true });
		}
		// "load" does not bubble, so listen in the capture phase for pictures inside root.
		root.addEventListener('load', schedule, true);
		window.addEventListener('resize', schedule);
		schedule();
	}

	// Favorite (heart) button from favorites.js; its state is filled in by aimpFavorites.refresh().
	function favButton(productId, modifier) {
		if (!window.aimpFavorites) {
			return '';
		}
		var html = window.aimpFavorites.button(productId);
		return modifier ? html.replace('class="aimp-fav', 'class="aimp-fav ' + modifier) : html;
	}

	window.aimpUI = {
		esc: esc,
		fmt: fmt,
		money: money,
		pageList: pageList,
		findById: findById,
		scrollIntoViewIfNeeded: scrollIntoViewIfNeeded,
		initialLang: initialLang,
		persistLang: persistLang,
		languageInfo: languageInfo,
		languagesHtml: languagesHtml,
		request: request,
		errorHtml: errorHtml,
		paginationHtml: paginationHtml,
		renderGrid: renderGrid,
		galleryHtml: galleryHtml,
		bindGallery: bindGallery,
		openLightbox: openLightbox,
		fitSidePanels: fitSidePanels,
		watchLayout: watchLayout,
		favButton: favButton
	};
})();
