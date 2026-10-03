/**
 * Atelier Irisee pattern configurator.
 *
 * Steps: pattern & size -> fabric -> haberdashery (buttons, zips, ribbons, bias tape) -> summary -> add to cart.
 * Pattern and fabric steps show a grid on the left and the details of the selection on the right.
 * All rules (allowed fabrics, quantities, stock) are enforced again on the server.
 */
(function () {
	'use strict';

	var cfg = window.aimpConfig;
	if (!cfg) {
		return;
	}

	/* ---------------------------------------------------------------
	 * Language
	 * ------------------------------------------------------------- */

	var LANG_KEY = 'aimp_lang';

	function validLang(code) {
		return !!(code && cfg.i18n[code]);
	}

	function initialLang() {
		var match = document.cookie.match(/(?:^|;\s*)aimp_lang=([a-z]+)/);
		if (match && validLang(match[1])) {
			return match[1];
		}
		try {
			var stored = window.localStorage.getItem(LANG_KEY);
			if (validLang(stored)) {
				return stored;
			}
		} catch (e) {}
		return validLang(cfg.defaultLanguage) ? cfg.defaultLanguage : Object.keys(cfg.i18n)[0];
	}

	// The cookie lets the cart and checkout show the plugin's texts in the same language.
	function persistLang(code) {
		document.cookie = LANG_KEY + '=' + code + '; path=/; max-age=31536000; SameSite=Lax';
		try {
			window.localStorage.setItem(LANG_KEY, code);
		} catch (e) {}
	}

	var lang = initialLang();
	var t = cfg.i18n[lang];
	persistLang(lang);

	function languageInfo(code) {
		return (cfg.languages || []).filter(function (l) {
			return l.code === code;
		})[0];
	}

	/* ---------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------- */

	// Decimal separator of the active language: 20.5 -> 20,5 in Dutch and French.
	function num(value) {
		var info = languageInfo(lang);
		return String(value).replace('.', info && info.decimal ? info.decimal : '.');
	}

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

	function money(amount) {
		var c = cfg.currency;
		var parts = Number(amount).toFixed(c.decimals).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousand);
		var value = parts.join(c.decimal);
		switch (c.position) {
			case 'right':
				return value + c.symbol;
			case 'left_space':
				return c.symbol + ' ' + value;
			case 'right_space':
				return value + ' ' + c.symbol;
			default:
				return c.symbol + value;
		}
	}

	// Body measurements in display order: [ data key, i18n key ].
	var MEASURES = [
		['bust', 'bust'],
		['waist', 'waist'],
		['hip', 'hip'],
		['inside_leg', 'insideLeg'],
		['height', 'height']
	];

	/**
	 * Haberdashery types of step 3, in display order.
	 * qty(size): locked cart quantity; need(size): what the size needs, as text; willAdd(size): hint above the grid.
	 */
	var NOTIONS = [
		{
			type: 'buttons', role: 'button', title: 'chooseButtons', label: 'buttons', unitLabel: 'pricePerPiece',
			qty: function (size) { return size.button_count; },
			need: function (size) { return String(size.button_count); },
			willAdd: function (size) { return fmt(t.buttonsWillAdd, size.button_count); }
		},
		{
			type: 'zips', role: 'zip', title: 'chooseZip', label: 'zips', unitLabel: 'pricePerPiece',
			qty: function (size) { return size.zip_count; },
			need: function (size) { return fmt(t.zipOf, size.zip_count, num(size.zip_length)); },
			willAdd: function (size) { return fmt(t.zipsWillAdd, size.zip_count, num(size.zip_length)); }
		},
		{
			type: 'ribbons', role: 'ribbon', title: 'chooseRibbon', label: 'ribbon', unitLabel: 'pricePerUnit',
			qty: function (size) { return size.ribbon_qty; },
			need: function (size) { return size.ribbon_text; },
			willAdd: function (size) { return fmt(t.lengthWillAdd, size.ribbon_text); }
		},
		{
			type: 'bias', role: 'bias', title: 'chooseBias', label: 'biasTape', unitLabel: 'pricePerUnit',
			qty: function (size) { return size.bias_qty; },
			need: function (size) { return size.bias_text; },
			willAdd: function (size) { return fmt(t.lengthWillAdd, size.bias_text); }
		}
	];

	function notionInfo(type) {
		return NOTIONS.filter(function (n) {
			return n.type === type;
		})[0];
	}

	function hasValue(value) {
		return value !== '' && value !== null && value !== undefined;
	}

	// Only the measurements that are filled in for at least one of the given sizes.
	function usedMeasures(sizes) {
		return MEASURES.filter(function (m) {
			return sizes.some(function (sz) {
				return hasValue(sz[m[0]]);
			});
		});
	}

	function measure(value) {
		return value === '' || value === null || value === undefined ? '–' : esc(num(value)) + ' ' + esc(t.cm);
	}

	function request(action, data) {
		var body = new URLSearchParams();
		Object.keys(data || {}).forEach(function (key) {
			body.append(key, data[key]);
		});
		body.append('aimp_lang', lang);
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
					var errors = (json && json.data && json.data.errors) || [t.error];
					var error = new Error(errors.join(' '));
					error.errors = errors;
					throw error;
				}
				return json.data;
			});
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

	// Favorite (heart) button from favorites.js; its state is filled in by aimpFavorites.refresh().
	function favButton(productId) {
		return window.aimpFavorites ? window.aimpFavorites.button(productId) : '';
	}

	// Title of a details panel with the favorite heart next to it.
	function detailsTitle(name, productId) {
		return '<div class="aimp-details-head"><h3 class="aimp-details-title">' + esc(name) + '</h3>' + favButton(productId) + '</div>';
	}

	function findById(items, id) {
		return (items || []).filter(function (item) {
			return item.id === id;
		})[0];
	}

	// Replace a selected item with its freshly loaded copy (new language, current stock).
	function rebind(selected, items) {
		return selected ? findById(items, selected.id) || selected : selected;
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

	function defaultFabricFilters() {
		return { category: 0, search: '', inStock: false, sort: 'recommended' };
	}

	/* ---------------------------------------------------------------
	 * Configurator
	 * ------------------------------------------------------------- */

	function Configurator(root) {
		this.root = root;
		this.reset();
		this.root.innerHTML =
			'<div class="aimp-topbar"><div class="aimp-languages" role="group"></div></div>' +
			'<ol class="aimp-steps"></ol>' +
			'<div class="aimp-body" aria-live="polite"></div>';
		this.languagesEl = root.querySelector('.aimp-languages');
		this.stepsEl = root.querySelector('.aimp-steps');
		this.body = root.querySelector('.aimp-body');
		this.renderLanguages();
		this.render();
		this.watchLayout();
	}

	/**
	 * After every change inside the configurator (and when pictures load or the window resizes):
	 * fit the side panels and mark the favorite hearts.
	 */
	Configurator.prototype.watchLayout = function () {
		var self = this;
		var scheduled = false;
		var schedule = function () {
			if (scheduled) {
				return;
			}
			scheduled = true;
			window.requestAnimationFrame(function () {
				scheduled = false;
				self.fitSidePanels();
				if (window.aimpFavorites) {
					window.aimpFavorites.refresh(self.root);
				}
			});
		};
		if (window.MutationObserver) {
			new MutationObserver(schedule).observe(this.root, { childList: true, subtree: true });
		}
		// "load" does not bubble, so listen in the capture phase for pictures inside the configurator.
		this.root.addEventListener('load', schedule, true);
		window.addEventListener('resize', schedule);
		schedule();
	};

	/**
	 * Side panels have no scrollbar of their own: they follow the page while scrolling only when they
	 * fit on the screen; taller panels scroll along with the page.
	 */
	Configurator.prototype.fitSidePanels = function () {
		this.root.querySelectorAll('.aimp-split-side').forEach(function (panel) {
			var top = parseFloat(window.getComputedStyle(panel).top) || 0;
			panel.classList.toggle('is-tall', panel.offsetHeight > window.innerHeight - top * 2);
		});
	};

	Configurator.prototype.reset = function () {
		this.state = {
			step: 'pattern',
			category: 0,
			patternPage: 1,
			patterns: null,
			pattern: null,
			patternImage: 0,
			sizesData: null,
			size: null,
			added: false
		};
		this.resetMaterials();
	};

	// Clears every choice that depends on the selected size.
	Configurator.prototype.resetMaterials = function () {
		var s = this.state;
		s.fabricPage = 1;
		s.fabrics = null;
		s.fabric = null;
		s.fabricImage = 0;
		s.fabricFilters = defaultFabricFilters();
		s.fabricCategories = null;
		s.notions = {};
		NOTIONS.forEach(function (n) {
			s.notions[n.type] = { page: 1, data: null, selected: null, image: 0 };
		});
	};

	// Forget loaded haberdashery lists (new language or changed stock); choices are kept.
	Configurator.prototype.clearNotionData = function () {
		var s = this.state;
		NOTIONS.forEach(function (n) {
			s.notions[n.type].data = null;
		});
	};

	/* ---------------------------------------------------------------
	 * Language switcher
	 * ------------------------------------------------------------- */

	Configurator.prototype.renderLanguages = function () {
		var self = this;
		var info = languageInfo(lang);
		this.root.lang = info ? info.locale : lang;
		this.languagesEl.setAttribute('aria-label', t.language);
		this.languagesEl.innerHTML = (cfg.languages || [])
			.map(function (l) {
				var active = l.code === lang;
				return (
					'<button type="button" class="aimp-language' + (active ? ' is-active' : '') + '" data-lang="' + esc(l.code) + '"' +
					' lang="' + esc(l.locale) + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
					'<img src="' + esc(l.flag) + '" alt="" width="24" height="16">' +
					'<span>' + esc(l.name) + '</span>' +
					'</button>'
				);
			})
			.join('');
		this.languagesEl.querySelectorAll('[data-lang]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				self.setLanguage(btn.getAttribute('data-lang'));
			});
		});
	};

	/**
	 * Switch language without losing the customer's choices. Interface texts switch instantly;
	 * texts that come from the server (stock, lengths, errors) are fetched again in the new language.
	 */
	Configurator.prototype.setLanguage = function (code) {
		var self = this;
		var s = this.state;
		if (!validLang(code) || code === lang) {
			return;
		}
		lang = code;
		t = cfg.i18n[code];
		persistLang(code);

		s.patterns = null;
		s.fabrics = null;
		self.clearNotionData();

		if (s.pattern) {
			var patternId = s.pattern.id;
			s.sizesData = null;
			request('sizes', { pattern: patternId })
				.then(function (data) {
					if (!s.pattern || s.pattern.id !== patternId || lang !== code) {
						return;
					}
					s.sizesData = data;
					s.size = rebind(s.size, data.sizes);
					if (s.step === 'pattern') {
						self.renderSizePanel();
					} else if (s.step === 'summary' && !s.added) {
						self.renderSummaryStep();
					} else {
						var recap = self.body.querySelector('.aimp-recap');
						if (recap && s.size) {
							recap.outerHTML = self.recapHtml();
						}
					}
				})
				.catch(function () {});
		}

		this.renderLanguages();
		if (s.step === 'summary' && s.added) {
			// Keep the success message visible; only the step labels change.
			this.renderSteps();
		} else {
			this.render();
		}

		// The switcher was rebuilt: keep keyboard focus on the chosen language.
		var activeButton = this.languagesEl.querySelector('.is-active');
		if (activeButton) {
			activeButton.focus();
		}
	};

	/* ---------------------------------------------------------------
	 * Steps
	 * ------------------------------------------------------------- */

	Configurator.prototype.steps = function () {
		var size = this.state.size;
		var steps = [{ key: 'pattern', label: t.stepPattern }];
		if (!size || size.fabric_units > 0) {
			steps.push({ key: 'fabric', label: t.stepFabric });
		}
		if (!size || this.notionTypes().length) {
			steps.push({ key: 'notions', label: t.stepNotions });
		}
		steps.push({ key: 'summary', label: t.stepSummary });
		return steps;
	};

	Configurator.prototype.stepIndex = function (key) {
		var steps = this.steps();
		for (var i = 0; i < steps.length; i++) {
			if (steps[i].key === key) {
				return i;
			}
		}
		return -1;
	};

	Configurator.prototype.goTo = function (key) {
		this.state.step = key;
		this.render();
		scrollIntoViewIfNeeded(this.root);
	};

	Configurator.prototype.next = function () {
		var steps = this.steps();
		var idx = this.stepIndex(this.state.step);
		if (idx < steps.length - 1) {
			this.goTo(steps[idx + 1].key);
		}
	};

	Configurator.prototype.prev = function () {
		var steps = this.steps();
		var idx = this.stepIndex(this.state.step);
		if (idx > 0) {
			this.goTo(steps[idx - 1].key);
		}
	};

	Configurator.prototype.render = function () {
		this.renderSteps();
		switch (this.state.step) {
			case 'fabric':
				this.renderFabricStep();
				break;
			case 'notions':
				this.renderNotionsStep();
				break;
			case 'summary':
				this.renderSummaryStep();
				break;
			default:
				this.renderPatternStep();
		}
	};

	Configurator.prototype.renderSteps = function () {
		var self = this;
		var current = this.stepIndex(this.state.step);
		this.stepsEl.innerHTML = this.steps()
			.map(function (step, i) {
				var cls = i === current ? 'is-current' : i < current ? 'is-done' : '';
				var inner = '<span class="aimp-step-num">' + (i + 1) + '</span> ' + esc(step.label);
				// After adding to the cart, start over via "Configure another" instead of editing the added set.
				if (i < current && !self.state.added) {
					inner = '<button type="button" data-step="' + esc(step.key) + '">' + inner + '</button>';
				}
				return '<li class="' + cls + '"' + (i === current ? ' aria-current="step"' : '') + '>' + inner + '</li>';
			})
			.join('');
		this.stepsEl.querySelectorAll('button[data-step]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				self.goTo(btn.getAttribute('data-step'));
			});
		});
	};

	/* ---------------------------------------------------------------
	 * Shared building blocks: grid, pagination, gallery, details
	 * ------------------------------------------------------------- */

	Configurator.prototype.paginationHtml = function (data) {
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
	};

	/**
	 * @param {Element} container
	 * @param {Object}  data   { items, page, pages, total, per_page }
	 * @param {Object}  opts   { selectedId, onSelect(item), onPage(page), emptyText, unavailableText, compact, priceSuffix }
	 */
	Configurator.prototype.renderGrid = function (container, data, opts) {
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
			html +=
				'<button type="button" class="aimp-card' + (selected ? ' is-selected' : '') + (unavailable ? ' is-unavailable' : '') + '"' +
				' data-id="' + esc(item.id) + '" aria-pressed="' + (selected ? 'true' : 'false') + '"' +
				(unavailable ? ' disabled' : '') + '>' +
				'<span class="aimp-card-image"><img src="' + esc(item.image) + '" alt="' + esc(item.image_alt || item.name) + '" loading="lazy"></span>' +
				'<span class="aimp-card-name">' + esc(item.name) + '</span>' +
				'<span class="aimp-card-price">' + (item.price_html || '') +
				(opts.priceSuffix ? ' <small>' + esc(opts.priceSuffix) + '</small>' : '') + '</span>' +
				(unavailable ? '<span class="aimp-badge">' + esc(opts.unavailableText || '') + '</span>' : '') +
				(selected ? '<span class="aimp-badge aimp-badge--selected">' + esc(t.selected) + '</span>' : '') +
				'</button>';
		});
		html += '</div>';
		html += this.paginationHtml(data);
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
	};

	// One big picture with the other pictures as selectable thumbnails underneath.
	Configurator.prototype.galleryHtml = function (images, index) {
		images = images && images.length ? images : [];
		if (!images.length) {
			return '';
		}
		var current = images[index] || images[0];
		var html =
			'<div class="aimp-gallery">' +
			'<div class="aimp-gallery-main"><img src="' + esc(current.large) + '" alt="' + esc(current.alt) + '"></div>';
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
	};

	Configurator.prototype.bindGallery = function (container, images, onChange) {
		var gallery = container.querySelector('.aimp-gallery');
		if (!gallery) {
			return;
		}
		var main = gallery.querySelector('.aimp-gallery-main img');
		gallery.querySelectorAll('.aimp-thumb').forEach(function (thumb) {
			thumb.addEventListener('click', function () {
				var i = parseInt(thumb.getAttribute('data-index'), 10);
				var image = images[i];
				if (!image) {
					return;
				}
				main.src = image.large;
				main.alt = image.alt;
				gallery.querySelectorAll('.aimp-thumb').forEach(function (other) {
					var active = other === thumb;
					other.classList.toggle('is-active', active);
					other.setAttribute('aria-pressed', active ? 'true' : 'false');
				});
				onChange(i);
			});
		});
	};

	// Details of a fabric, button or zip: gallery, description, attributes, prices and stock.
	Configurator.prototype.materialDetailsHtml = function (item, imageIndex, unitLabel, needText) {
		var html = '<div class="aimp-details">';
		html += this.galleryHtml(item.gallery, imageIndex);
		html += detailsTitle(item.name, item.id);
		if (item.description) {
			html += '<div class="aimp-description">' + item.description + '</div>';
		}
		html += '<dl class="aimp-info-list">';
		(item.attributes || []).forEach(function (attr) {
			html += '<div><dt>' + esc(attr.label) + '</dt><dd>' + esc(attr.value) + '</dd></div>';
		});
		html += '<div><dt>' + esc(unitLabel) + '</dt><dd>' + item.price_html + '</dd></div>';
		html += '<div><dt>' + esc(t.youNeed) + '</dt><dd>' + esc(needText) + '</dd></div>';
		html += '<div><dt>' + esc(t.totalForSize) + '</dt><dd>' + item.total_html + '</dd></div>';
		if (item.stock_text) {
			html += '<div><dt>' + esc(t.stock) + '</dt><dd>' + esc(item.stock_text) + '</dd></div>';
		}
		html += '</dl></div>';
		return html;
	};

	Configurator.prototype.showError = function (container, err) {
		var errors = (err && err.errors) || [t.error];
		container.innerHTML =
			'<div class="aimp-notice aimp-notice--error"><ul>' +
			errors.map(function (e) {
				return '<li>' + esc(e) + '</li>';
			}).join('') +
			'</ul></div>';
	};

	/* ---------------------------------------------------------------
	 * Step 1: pattern & size
	 * ------------------------------------------------------------- */

	Configurator.prototype.renderPatternStep = function () {
		var self = this;
		var s = this.state;
		var html = '<div class="aimp-step aimp-step--pattern">';

		if (cfg.categories && cfg.categories.length) {
			html += '<div class="aimp-filters" role="group">';
			[{ id: 0, name: t.all }].concat(cfg.categories).forEach(function (cat) {
				var active = s.category === cat.id;
				html +=
					'<button type="button" class="aimp-filter' + (active ? ' is-active' : '') + '" data-cat="' + esc(cat.id) + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
					esc(cat.name) +
					'</button>';
			});
			html += '</div>';
		}

		html +=
			'<div class="aimp-split">' +
			'<div class="aimp-split-main" data-role="patterns"></div>' +
			'<aside class="aimp-split-side" data-role="size-panel"></aside>' +
			'</div>' +
			'<section class="aimp-size-chart-box" data-role="size-chart" hidden></section>' +
			'</div>';
		this.body.innerHTML = html;

		this.body.querySelectorAll('[data-cat]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				s.category = parseInt(btn.getAttribute('data-cat'), 10);
				s.patternPage = 1;
				s.patterns = null;
				self.renderPatternStep();
			});
		});

		this.renderPatternGrid();
		this.renderSizePanel();

		if (!s.patterns) {
			this.loadPatterns();
		}
	};

	Configurator.prototype.loadPatterns = function () {
		var self = this;
		var s = this.state;
		var wanted = lang + ':' + s.category + ':' + s.patternPage;
		s.patterns = null;
		this.renderPatternGrid();
		request('patterns', { category: s.category, page: s.patternPage })
			.then(function (data) {
				// Ignore stale responses after a quick filter or language change.
				if (wanted !== lang + ':' + s.category + ':' + s.patternPage) {
					return;
				}
				s.patterns = data;
				if (s.step === 'pattern') {
					self.renderPatternGrid();
				}
			})
			.catch(function (err) {
				var container = self.body.querySelector('[data-role="patterns"]');
				if (container) {
					self.showError(container, err);
				}
			});
	};

	Configurator.prototype.renderPatternGrid = function () {
		var self = this;
		var s = this.state;
		var container = this.body.querySelector('[data-role="patterns"]');
		if (!container) {
			return;
		}
		this.renderGrid(container, s.patterns, {
			selectedId: s.pattern ? s.pattern.id : null,
			emptyText: t.noPatterns,
			onSelect: function (item) {
				self.selectPattern(item);
			},
			onPage: function (page) {
				s.patternPage = page;
				self.loadPatterns();
			}
		});
	};

	Configurator.prototype.selectPattern = function (item) {
		var self = this;
		var s = this.state;
		if (s.pattern && s.pattern.id === item.id) {
			return;
		}
		s.pattern = item;
		s.patternImage = 0;
		s.sizesData = null;
		s.size = null;
		this.resetMaterials();
		this.renderPatternGrid();
		this.renderSizePanel();
		this.renderSteps();
		scrollIntoViewIfNeeded(this.body.querySelector('[data-role="size-panel"]'));

		var reqLang = lang;
		request('sizes', { pattern: item.id })
			.then(function (data) {
				if (!s.pattern || s.pattern.id !== item.id || lang !== reqLang) {
					return;
				}
				s.sizesData = data;
				self.renderSizePanel();
			})
			.catch(function (err) {
				var panel = self.body.querySelector('[data-role="size-panel"]');
				if (panel) {
					self.showError(panel, err);
				}
			});
	};

	Configurator.prototype.needsHtml = function (size) {
		var rows = [];
		if (size.fabric_units > 0) {
			rows.push('<li><strong>' + esc(t.fabricNeeded) + ':</strong> ' + esc(size.fabric_text) + '</li>');
		}
		NOTIONS.forEach(function (n) {
			if (n.qty(size) > 0) {
				rows.push('<li><strong>' + esc(t[n.label]) + ':</strong> ' + esc(n.need(size)) + '</li>');
			}
		});
		if (!rows.length) {
			rows.push('<li>' + esc(t.none) + '</li>');
		}
		return '<ul class="aimp-needs">' + rows.join('') + '</ul>';
	};

	Configurator.prototype.renderSizePanel = function () {
		var self = this;
		var s = this.state;
		var panel = this.body.querySelector('[data-role="size-panel"]');
		if (!panel) {
			return;
		}
		this.renderSizeChart();
		if (!s.pattern) {
			panel.innerHTML = '<div class="aimp-side-placeholder">' + esc(t.selectPatternHint) + '</div>';
			return;
		}
		if (!s.sizesData) {
			panel.innerHTML = '<div class="aimp-details"><p class="aimp-loading">' + esc(t.loading) + '</p></div>';
			return;
		}

		var pattern = s.sizesData.pattern;
		var sizes = s.sizesData.sizes;
		var size = s.size;

		var html = '<div class="aimp-details">';
		html += this.galleryHtml(pattern.gallery, s.patternImage);
		html += detailsTitle(pattern.name, pattern.id);
		if (pattern.short_description) {
			html += '<div class="aimp-description">' + pattern.short_description + '</div>';
		}
		html += '<h4>' + esc(t.chooseSize) + '</h4>';
		html += '<p class="aimp-help">' + esc(t.sizeHelp) + '</p>';
		html += '<div class="aimp-sizes" role="group">';
		sizes.forEach(function (sz) {
			var active = size && size.id === sz.id;
			html +=
				'<button type="button" class="aimp-size' + (active ? ' is-active' : '') + '" data-size="' + esc(sz.id) + '" aria-pressed="' + (active ? 'true' : 'false') + '"' +
				(sz.available ? '' : ' disabled title="' + esc(t.unavailable) + '"') + '>' +
				esc(sz.label) +
				'</button>';
		});
		html += '</div>';

		if (size) {
			var measures = usedMeasures([size]);
			if (measures.length) {
				html += '<dl class="aimp-measurements">';
				measures.forEach(function (m) {
					html += '<div><dt>' + esc(t[m[1]]) + '</dt><dd>' + measure(size[m[0]]) + '</dd></div>';
				});
				html += '</dl>';
			}
			html += '<p class="aimp-needs-title">' + esc(t.needs) + ':</p>' + this.needsHtml(size);
			html += '<p class="aimp-size-price">' + size.price_html + '</p>';
		}

		html +=
			'<div class="aimp-actions">' +
			'<button type="button" class="aimp-button aimp-button--block" data-action="confirm"' + (size ? '' : ' disabled') + '>' + esc(t.confirmPattern) + '</button>' +
			'</div>';
		html += '</div>';
		panel.innerHTML = html;

		this.bindGallery(panel, pattern.gallery, function (i) {
			s.patternImage = i;
		});

		panel.querySelectorAll('[data-size]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var id = parseInt(btn.getAttribute('data-size'), 10);
				self.selectSize(id);
				var again = panel.querySelector('[data-size="' + id + '"]');
				if (again) {
					again.focus();
				}
			});
		});
		panel.querySelector('[data-action="confirm"]').addEventListener('click', function () {
			if (s.size) {
				self.next();
			}
		});
	};

	// Used by the size buttons in the details panel and the rows of the size chart.
	Configurator.prototype.selectSize = function (id) {
		var s = this.state;
		var chosen = s.sizesData ? findById(s.sizesData.sizes, id) : null;
		if (!chosen || !chosen.available || (s.size && s.size.id === id)) {
			return;
		}
		s.size = chosen;
		// Show the size's own picture when it has one.
		if (chosen.image_index >= 0) {
			s.patternImage = chosen.image_index;
		}
		this.resetMaterials();
		this.renderSizePanel();
		this.renderSteps();
	};

	// Size chart in its own box under the patterns and details, so it stays in place when a size is chosen.
	Configurator.prototype.renderSizeChart = function () {
		var self = this;
		var s = this.state;
		var box = this.body.querySelector('[data-role="size-chart"]');
		if (!box) {
			return;
		}
		if (!s.pattern || !s.sizesData || !s.sizesData.sizes.length) {
			box.hidden = true;
			box.innerHTML = '';
			return;
		}

		var sizes = s.sizesData.sizes;
		var measures = usedMeasures(sizes);
		var html =
			'<div class="aimp-size-chart-head">' +
			'<h4>' + esc(t.sizeChart) + '</h4>' +
			'<button type="button" class="aimp-button aimp-measure-button" data-action="measure-guide">' + esc(t.howToMeasure) + '</button>' +
			'</div>';
		html += '<div class="aimp-table-scroll"><table class="aimp-size-chart"><thead><tr><th>' + esc(t.size) + '</th>';
		measures.forEach(function (m) {
			html += '<th>' + esc(t[m[1]]) + '</th>';
		});
		html += '</tr></thead><tbody>';
		sizes.forEach(function (sz) {
			var selected = s.size && s.size.id === sz.id;
			html +=
				'<tr data-size-row="' + esc(sz.id) + '" class="' + (selected ? 'is-selected' : '') + (sz.available ? ' is-clickable' : ' is-unavailable') + '">' +
				'<th scope="row">' + esc(sz.label) + '</th>';
			measures.forEach(function (m) {
				html += '<td>' + measure(sz[m[0]]) + '</td>';
			});
			html += '</tr>';
		});
		html += '</tbody></table></div>';

		box.innerHTML = html;
		box.hidden = false;

		box.querySelector('[data-action="measure-guide"]').addEventListener('click', function () {
			self.openMeasureGuide();
		});
		box.querySelectorAll('tr[data-size-row]').forEach(function (row) {
			row.addEventListener('click', function () {
				self.selectSize(parseInt(row.getAttribute('data-size-row'), 10));
			});
		});
	};

	// "How to measure" picture in a lightbox. The image is only downloaded the first time it is opened.
	Configurator.prototype.openMeasureGuide = function () {
		var dialog = this.measureDialog;
		if (!dialog) {
			dialog = document.createElement('dialog');
			dialog.className = 'aimp-lightbox';
			dialog.innerHTML =
				'<button type="button" class="aimp-button aimp-lightbox-close" data-action="close-guide">×</button>' +
				'<img class="aimp-lightbox-image" alt="">';
			this.root.appendChild(dialog);
			this.measureDialog = dialog;

			var close = function () {
				if (typeof dialog.close === 'function') {
					dialog.close();
				} else {
					dialog.removeAttribute('open');
				}
			};
			dialog.querySelector('[data-action="close-guide"]').addEventListener('click', close);
			// A click on the dark backdrop lands on the dialog element itself.
			dialog.addEventListener('click', function (e) {
				if (e.target === dialog) {
					close();
				}
			});
		}

		// Texts follow the current language.
		var closeButton = dialog.querySelector('[data-action="close-guide"]');
		closeButton.setAttribute('aria-label', t.close);
		closeButton.title = t.close;
		dialog.setAttribute('aria-label', t.howToMeasure);
		var image = dialog.querySelector('.aimp-lightbox-image');
		image.alt = t.howToMeasure;
		if (!image.getAttribute('src')) {
			image.src = cfg.measureImage;
		}

		if (typeof dialog.showModal === 'function') {
			dialog.showModal();
		} else {
			dialog.setAttribute('open', '');
		}
	};

	/* ---------------------------------------------------------------
	 * Step 2: fabric
	 * ------------------------------------------------------------- */

	// Recap at the top of the later steps: small pictures of what has been chosen so far.
	Configurator.prototype.recapHtml = function () {
		var s = this.state;
		var items = [{ image: s.pattern.image, name: s.pattern.name, detail: t.size + ' ' + s.size.label }];
		if (s.step !== 'fabric' && s.fabric && s.size.fabric_units > 0) {
			items.push({ image: s.fabric.image, name: s.fabric.name, detail: s.size.fabric_text });
		}
		if (s.step === 'summary') {
			NOTIONS.forEach(function (n) {
				var selected = s.notions[n.type].selected;
				if (n.qty(s.size) > 0 && selected) {
					items.push({ image: selected.image, name: selected.name, detail: n.need(s.size) });
				}
			});
		}
		var html = '<div class="aimp-recap"><ul class="aimp-recap-items">';
		items.forEach(function (item) {
			html +=
				'<li class="aimp-recap-item">' +
				'<img src="' + esc(item.image) + '" alt="" width="44" height="44" loading="lazy">' +
				'<span><strong>' + esc(item.name) + '</strong><small>' + esc(item.detail) + '</small></span>' +
				'</li>';
		});
		html += '</ul>';
		if (s.step !== 'summary') {
			html += this.needsHtml(s.size);
		}
		return html + '</div>';
	};

	Configurator.prototype.renderFabricStep = function () {
		var self = this;
		var s = this.state;
		var f = s.fabricFilters;
		var sorts = [
			['recommended', t.sortRecommended],
			['name_asc', t.sortNameAsc],
			['name_desc', t.sortNameDesc],
			['price_asc', t.sortPriceAsc],
			['price_desc', t.sortPriceDesc],
			['newest', t.sortNewest]
		];

		this.body.innerHTML =
			'<div class="aimp-step aimp-step--fabric">' +
			this.recapHtml() +
			'<h3>' + esc(t.chooseFabric) + '</h3>' +
			'<div class="aimp-split">' +
			'<div class="aimp-split-main">' +
			'<div class="aimp-toolbar">' +
			'<div class="aimp-filters" data-role="fabric-cats" role="group" aria-label="' + esc(t.fabricCategory) + '"></div>' +
			'<div class="aimp-toolbar-row">' +
			'<input type="search" class="aimp-search" data-role="search" value="' + esc(f.search) + '" placeholder="' + esc(t.searchFabrics) + '" aria-label="' + esc(t.searchFabrics) + '">' +
			'<label class="aimp-check"><input type="checkbox" data-role="in-stock"' + (f.inStock ? ' checked' : '') + '> ' + esc(t.inStockOnly) + '</label>' +
			'<label class="aimp-sort"><span>' + esc(t.sortBy) + '</span> <select data-role="sort">' +
			sorts.map(function (o) {
				return '<option value="' + o[0] + '"' + (f.sort === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
			}).join('') +
			'</select></label>' +
			'</div>' +
			'</div>' +
			'<div data-role="fabrics"></div>' +
			'</div>' +
			'<aside class="aimp-split-side" data-role="fabric-panel"></aside>' +
			'</div>' +
			'<div class="aimp-actions">' +
			'<button type="button" class="aimp-button aimp-button--ghost" data-action="back">' + esc(t.back) + '</button>' +
			'</div>' +
			'</div>';

		this.body.querySelector('[data-action="back"]').addEventListener('click', function () {
			self.prev();
		});

		var reload = function () {
			s.fabricPage = 1;
			self.loadFabrics();
		};
		var searchTimer = null;
		this.body.querySelector('[data-role="search"]').addEventListener('input', function (e) {
			var value = e.target.value;
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () {
				if (value.trim() !== f.search) {
					f.search = value.trim();
					reload();
				}
			}, 350);
		});
		this.body.querySelector('[data-role="in-stock"]').addEventListener('change', function (e) {
			f.inStock = e.target.checked;
			reload();
		});
		this.body.querySelector('[data-role="sort"]').addEventListener('change', function (e) {
			f.sort = e.target.value;
			reload();
		});

		this.renderFabricCategories();
		this.renderFabricGrid();
		this.renderFabricPanel();
		if (!s.fabrics) {
			this.loadFabrics();
		}
	};

	Configurator.prototype.renderFabricCategories = function () {
		var self = this;
		var s = this.state;
		var container = this.body.querySelector('[data-role="fabric-cats"]');
		if (!container) {
			return;
		}
		var cats = s.fabricCategories || [];
		if (cats.length < 2) {
			container.innerHTML = '';
			container.hidden = true;
			return;
		}
		container.hidden = false;
		container.innerHTML = [{ id: 0, name: t.all }]
			.concat(cats)
			.map(function (cat) {
				var active = s.fabricFilters.category === cat.id;
				return (
					'<button type="button" class="aimp-filter' + (active ? ' is-active' : '') + '" data-fabric-cat="' + esc(cat.id) + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
					esc(cat.name) +
					'</button>'
				);
			})
			.join('');
		container.querySelectorAll('[data-fabric-cat]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				s.fabricFilters.category = parseInt(btn.getAttribute('data-fabric-cat'), 10);
				s.fabricPage = 1;
				self.renderFabricCategories();
				self.loadFabrics();
			});
		});
	};

	Configurator.prototype.loadFabrics = function () {
		var self = this;
		var s = this.state;
		var f = s.fabricFilters;
		var params = {
			variation: s.size.id,
			page: s.fabricPage,
			category: f.category,
			search: f.search,
			in_stock: f.inStock ? 1 : 0,
			sort: f.sort
		};
		var key = lang + JSON.stringify(params);
		this.fabricRequestKey = key;
		s.fabrics = null;
		this.renderFabricGrid();
		request('fabrics', params)
			.then(function (data) {
				if (self.fabricRequestKey !== key) {
					return;
				}
				s.fabrics = data;
				s.fabricPage = data.page;
				s.fabric = rebind(s.fabric, data.items);
				var hadCategories = s.fabricCategories !== null;
				s.fabricCategories = data.categories || [];
				if (s.step === 'fabric') {
					self.renderFabricGrid();
					self.renderFabricPanel();
					if (!hadCategories) {
						self.renderFabricCategories();
					}
				}
			})
			.catch(function (err) {
				var container = self.body.querySelector('[data-role="fabrics"]');
				if (container && self.fabricRequestKey === key) {
					self.showError(container, err);
				}
			});
	};

	Configurator.prototype.renderFabricGrid = function () {
		var self = this;
		var s = this.state;
		var container = this.body.querySelector('[data-role="fabrics"]');
		if (!container) {
			return;
		}
		var filtered = s.fabricFilters.category || s.fabricFilters.search || s.fabricFilters.inStock;
		this.renderGrid(container, s.fabrics, {
			selectedId: s.fabric ? s.fabric.id : null,
			emptyText: filtered ? t.noFabricsMatch : t.noFabrics,
			unavailableText: t.notEnoughStock,
			compact: true,
			priceSuffix: t.per10cm,
			onSelect: function (item) {
				if (!s.fabric || s.fabric.id !== item.id) {
					s.fabric = item;
					s.fabricImage = 0;
				}
				self.renderFabricGrid();
				self.renderFabricPanel();
				scrollIntoViewIfNeeded(self.body.querySelector('[data-role="fabric-panel"]'));
			},
			onPage: function (page) {
				s.fabricPage = page;
				self.loadFabrics();
			}
		});
	};

	Configurator.prototype.renderFabricPanel = function () {
		var self = this;
		var s = this.state;
		var panel = this.body.querySelector('[data-role="fabric-panel"]');
		if (!panel) {
			return;
		}
		if (!s.fabric) {
			panel.innerHTML = '<div class="aimp-side-placeholder">' + esc(t.selectFabricHint) + '</div>';
			return;
		}
		var html = this.materialDetailsHtml(s.fabric, s.fabricImage, t.pricePerUnit, s.size.fabric_text);
		html +=
			'<div class="aimp-actions">' +
			'<button type="button" class="aimp-button aimp-button--block" data-action="confirm-fabric">' + esc(t.confirmFabric) + '</button>' +
			'</div>';
		panel.innerHTML = html;

		this.bindGallery(panel, s.fabric.gallery, function (i) {
			s.fabricImage = i;
		});
		panel.querySelector('[data-action="confirm-fabric"]').addEventListener('click', function () {
			self.next();
		});
	};

	/* ---------------------------------------------------------------
	 * Step 3: haberdashery (buttons, zips, ribbons, bias tape)
	 * ------------------------------------------------------------- */

	// The haberdashery types the chosen size needs.
	Configurator.prototype.notionTypes = function () {
		var size = this.state.size;
		if (!size) {
			return [];
		}
		return NOTIONS.filter(function (n) {
			return n.qty(size) > 0;
		}).map(function (n) {
			return n.type;
		});
	};

	Configurator.prototype.renderNotionsStep = function () {
		var self = this;
		var s = this.state;
		var html = '<div class="aimp-step aimp-step--notions">' + this.recapHtml();

		this.notionTypes().forEach(function (type) {
			var info = notionInfo(type);
			html +=
				'<section class="aimp-notion-section">' +
				'<h3>' + esc(t[info.title]) + '</h3>' +
				'<p class="aimp-help">' + esc(info.willAdd(s.size)) + ' ' + esc(t.deselectHint) + '</p>' +
				'<div class="aimp-split">' +
				'<div class="aimp-split-main" data-role="notions-' + type + '"></div>' +
				'<aside class="aimp-split-side" data-role="notion-panel-' + type + '"></aside>' +
				'</div>' +
				'</section>';
		});

		html +=
			'<div class="aimp-actions">' +
			'<button type="button" class="aimp-button aimp-button--ghost" data-action="back">' + esc(t.back) + '</button>' +
			'<button type="button" class="aimp-button" data-action="continue">' + esc(t.continue) + '</button>' +
			'</div></div>';
		this.body.innerHTML = html;

		this.body.querySelector('[data-action="back"]').addEventListener('click', function () {
			self.prev();
		});
		this.body.querySelector('[data-action="continue"]').addEventListener('click', function () {
			self.next();
		});

		this.notionTypes().forEach(function (type) {
			self.renderNotionGrid(type);
			if (!s.notions[type].data) {
				self.loadNotions(type);
			}
		});
	};

	Configurator.prototype.loadNotions = function (type) {
		var self = this;
		var s = this.state;
		var sizeId = s.size.id;
		var bucket = s.notions[type];
		var page = bucket.page;
		var reqLang = lang;
		bucket.data = null;
		this.renderNotionGrid(type);
		request('notions', { variation: sizeId, type: type, page: page })
			.then(function (data) {
				if (!s.size || s.size.id !== sizeId || s.notions[type].page !== page || lang !== reqLang) {
					return;
				}
				s.notions[type].data = data;
				s.notions[type].selected = rebind(s.notions[type].selected, data.items);
				if (s.step === 'notions') {
					self.renderNotionGrid(type);
				}
			})
			.catch(function (err) {
				var container = self.body.querySelector('[data-role="notions-' + type + '"]');
				if (container) {
					self.showError(container, err);
				}
			});
	};

	Configurator.prototype.renderNotionGrid = function (type) {
		var self = this;
		var s = this.state;
		var bucket = s.notions[type];
		var container = this.body.querySelector('[data-role="notions-' + type + '"]');
		var panel = this.body.querySelector('[data-role="notion-panel-' + type + '"]');
		if (!container) {
			return;
		}
		this.renderGrid(container, bucket.data, {
			selectedId: bucket.selected ? bucket.selected.id : null,
			emptyText: t.noNotions,
			unavailableText: t.notEnoughStock,
			compact: true,
			onSelect: function (item) {
				bucket.selected = bucket.selected && bucket.selected.id === item.id ? null : item;
				bucket.image = 0;
				self.renderNotionGrid(type);
				if (bucket.selected) {
					scrollIntoViewIfNeeded(panel);
				}
			},
			onPage: function (page) {
				bucket.page = page;
				self.loadNotions(type);
			}
		});
		if (panel) {
			if (!bucket.selected) {
				panel.innerHTML = '<div class="aimp-side-placeholder">' + esc(t.selectItemHint) + '</div>';
				return;
			}
			var info = notionInfo(type);
			panel.innerHTML = this.materialDetailsHtml(bucket.selected, bucket.image, t[info.unitLabel], info.need(s.size));
			this.bindGallery(panel, bucket.selected.gallery, function (i) {
				bucket.image = i;
			});
		}
	};

	/* ---------------------------------------------------------------
	 * Step 4: summary & add to cart
	 * ------------------------------------------------------------- */

	Configurator.prototype.summaryLines = function () {
		var s = this.state;
		var lines = [{ name: s.pattern.name + ' – ' + t.size + ' ' + s.size.label, qty: 1, price: s.size.price }];
		if (s.size.fabric_units > 0 && s.fabric) {
			lines.push({ name: s.fabric.name, detail: s.size.fabric_text, qty: s.size.fabric_units, price: s.fabric.price });
		}
		NOTIONS.forEach(function (n) {
			var selected = s.notions[n.type].selected;
			if (n.qty(s.size) > 0 && selected) {
				lines.push({
					name: selected.name,
					detail: n.unitLabel === 'pricePerUnit' ? n.need(s.size) : '',
					qty: n.qty(s.size),
					price: selected.price
				});
			}
		});
		return lines;
	};

	Configurator.prototype.renderSummaryStep = function () {
		var self = this;
		var s = this.state;
		var lines = this.summaryLines();
		var total = 0;

		var html = '<div class="aimp-step aimp-step--summary">' + this.recapHtml() + '<h3>' + esc(t.summaryTitle) + '</h3>';
		html += '<div class="aimp-table-scroll"><table class="aimp-summary"><thead><tr><th>' + esc(t.product) + '</th><th>' + esc(t.quantity) + '</th><th>' + esc(t.price) + '</th></tr></thead><tbody>';
		lines.forEach(function (line) {
			var subtotal = line.price * line.qty;
			total += subtotal;
			html +=
				'<tr><td>' + esc(line.name) + (line.detail ? '<br><small>' + esc(line.detail) + '</small>' : '') + '</td>' +
				'<td>' + esc(line.qty) + '</td><td>' + esc(money(subtotal)) + '</td></tr>';
		});
		html += '</tbody><tfoot><tr><th colspan="2">' + esc(t.total) + '</th><td>' + esc(money(total)) + '</td></tr></tfoot></table></div>';
		html += '<p class="aimp-help">' + esc(t.lockedNote) + '</p>';
		html += '<div data-role="messages"></div>';
		html +=
			'<div class="aimp-actions">' +
			'<button type="button" class="aimp-button aimp-button--ghost" data-action="back"' + (s.added ? ' hidden' : '') + '>' + esc(t.back) + '</button>' +
			'<button type="button" class="aimp-button" data-action="add"' + (s.added ? ' hidden' : '') + '>' + esc(t.addToCart) + '</button>' +
			'</div></div>';
		this.body.innerHTML = html;

		this.body.querySelector('[data-action="back"]').addEventListener('click', function () {
			self.prev();
		});
		this.body.querySelector('[data-action="add"]').addEventListener('click', function () {
			self.addToCart(this);
		});
	};

	Configurator.prototype.addToCart = function (button) {
		var self = this;
		var s = this.state;
		var messages = this.body.querySelector('[data-role="messages"]');
		var back = this.body.querySelector('[data-action="back"]');

		button.disabled = true;
		back.disabled = true;
		button.textContent = t.adding;
		messages.innerHTML = '';

		var params = {
			nonce: cfg.nonce,
			variation: s.size.id,
			fabric: s.size.fabric_units > 0 && s.fabric ? s.fabric.id : 0
		};
		// One parameter per haberdashery role: button, zip, ribbon, bias.
		NOTIONS.forEach(function (n) {
			var selected = s.notions[n.type].selected;
			params[n.role] = n.qty(s.size) > 0 && selected ? selected.id : 0;
		});

		request('add_to_cart', params)
			.then(function (data) {
				s.added = true;
				button.hidden = true;
				back.hidden = true;
				self.renderSteps();
				messages.innerHTML =
					'<div class="aimp-notice aimp-notice--success"><p>' + esc(data.message) + '</p>' +
					'<p class="aimp-actions">' +
					'<a class="aimp-button" href="' + esc(data.cart_url || cfg.cartUrl) + '">' + esc(t.viewCart) + '</a> ' +
					'<button type="button" class="aimp-button aimp-button--ghost" data-action="again">' + esc(t.configureAnother) + '</button>' +
					'</p></div>';
				messages.querySelector('[data-action="again"]').addEventListener('click', function () {
					var patterns = s.patterns;
					var category = s.category;
					var page = s.patternPage;
					self.reset();
					self.state.patterns = patterns;
					self.state.category = category;
					self.state.patternPage = page;
					self.goTo('pattern');
				});
				self.refreshMiniCart();
			})
			.catch(function (err) {
				button.disabled = false;
				back.disabled = false;
				button.textContent = t.addToCart;
				self.showError(messages, err);
				// Stock may have changed: reload material lists next time they are shown.
				s.fabrics = null;
				self.clearNotionData();
			});
	};

	Configurator.prototype.refreshMiniCart = function () {
		// Classic mini cart / cart fragments.
		if (window.jQuery) {
			window.jQuery(document.body).trigger('wc_fragment_refresh');
		}
		// Mini Cart block.
		document.body.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart', { bubbles: true, detail: { preserveCartData: false } }));
	};

	/* ---------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------- */

	function boot() {
		document.querySelectorAll('[data-aimp-configurator]').forEach(function (root) {
			if (!root.aimpConfigurator) {
				root.aimpConfigurator = new Configurator(root);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
