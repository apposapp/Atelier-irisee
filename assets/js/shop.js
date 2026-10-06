/**
 * Atelier Irisee shop pages ([atelier_irisee_shop]).
 *
 * Filter sidebar (can be opened and closed) | product grid | details of the selected product.
 * Cards, gallery and pagination come from ui.js, so they look exactly like the configurator.
 * The filters are kept in the address bar (aimp_* parameters), so a filtered page can be reloaded or shared.
 */
(function () {
	'use strict';

	var cfg = window.aimpShop;
	var UI = window.aimpUI;
	if (!cfg || !UI) {
		return;
	}

	var esc = UI.esc;
	var SIDEBAR_KEY = 'aimp_shop_filters';
	var DRAWER_QUERY = '(max-width: 999px)';

	var lang = UI.initialLang(cfg.i18n, cfg.defaultLanguage);
	var t = cfg.i18n[lang];
	UI.persistLang(lang);

	function defaultFilters() {
		return { category: 0, search: '', min: '', max: '', inStock: false, sort: 'recommended', attrs: {}, skills: [] };
	}

	function isDrawer() {
		return window.matchMedia && window.matchMedia(DRAWER_QUERY).matches;
	}

	function storedSidebar() {
		try {
			return window.localStorage.getItem(SIDEBAR_KEY);
		} catch (e) {
			return null;
		}
	}

	function storeSidebar(value) {
		try {
			window.localStorage.setItem(SIDEBAR_KEY, value);
		} catch (e) {}
	}

	var uid = 0;

	function Shop(root, syncUrl) {
		this.root = root;
		this.type = root.getAttribute('data-type') || 'all';
		this.syncUrl = syncUrl;
		this.id = 'aimp-shop-' + ++uid;
		this.state = {
			filters: defaultFilters(),
			page: 1,
			data: null,
			facets: null,
			selected: null,
			image: 0
		};
		if (syncUrl) {
			this.readUrl();
		}
		this.build();
		UI.watchLayout(root);
		this.load(true);
	}

	/* ---------------------------------------------------------------
	 * Address bar
	 * ------------------------------------------------------------- */

	Shop.prototype.readUrl = function () {
		var params = new URLSearchParams(window.location.search);
		var f = this.state.filters;
		f.category = parseInt(params.get('aimp_cat') || '0', 10) || 0;
		f.search = (params.get('aimp_q') || '').slice(0, 100);
		f.min = params.get('aimp_min') || '';
		f.max = params.get('aimp_max') || '';
		f.inStock = params.get('aimp_stock') === '1';
		f.sort = params.get('aimp_sort') || 'recommended';
		f.skills = (params.get('aimp_skill') || '').split(',').filter(Boolean);
		this.state.page = parseInt(params.get('aimp_page') || '1', 10) || 1;
		params.forEach(function (value, key) {
			if (key.indexOf('aimp_pa_') === 0 && value) {
				f.attrs[key.slice(5)] = value.split(',').filter(Boolean);
			}
		});
	};

	Shop.prototype.writeUrl = function () {
		if (!this.syncUrl || !window.history || !window.history.replaceState) {
			return;
		}
		var f = this.state.filters;
		var params = new URLSearchParams(window.location.search);
		Array.from(params.keys()).forEach(function (key) {
			if (key.indexOf('aimp_') === 0 && key !== 'aimp_lang') {
				params.delete(key);
			}
		});
		if (f.category) {
			params.set('aimp_cat', f.category);
		}
		if (f.search) {
			params.set('aimp_q', f.search);
		}
		if (f.min !== '') {
			params.set('aimp_min', f.min);
		}
		if (f.max !== '') {
			params.set('aimp_max', f.max);
		}
		if (f.inStock) {
			params.set('aimp_stock', '1');
		}
		if (f.skills.length) {
			params.set('aimp_skill', f.skills.join(','));
		}
		if (f.sort !== 'recommended') {
			params.set('aimp_sort', f.sort);
		}
		Object.keys(f.attrs).forEach(function (taxonomy) {
			if (f.attrs[taxonomy].length) {
				params.set('aimp_' + taxonomy, f.attrs[taxonomy].join(','));
			}
		});
		if (this.state.page > 1) {
			params.set('aimp_page', this.state.page);
		}
		var query = params.toString();
		window.history.replaceState(null, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
	};

	/* ---------------------------------------------------------------
	 * Layout
	 * ------------------------------------------------------------- */

	Shop.prototype.build = function () {
		var self = this;
		this.root.innerHTML =
			'<div class="aimp-topbar aimp-shop-topbar">' +
			'<button type="button" class="aimp-button aimp-shop-toggle" data-action="toggle-filters" aria-controls="' + this.id + '-filters"></button>' +
			'<div class="aimp-languages" role="group"></div>' +
			'</div>' +
			'<div class="aimp-shop-layout">' +
			'<aside class="aimp-shop-sidebar" id="' + this.id + '-filters">' +
			'<div class="aimp-shop-sidebar-head"><h3 data-role="filters-title"></h3>' +
			'<button type="button" class="aimp-shop-close" data-action="close-filters">×</button></div>' +
			'<div data-role="filters"></div>' +
			'<div class="aimp-shop-sidebar-foot"><button type="button" class="aimp-button aimp-button--block" data-action="close-filters" data-role="show-results"></button></div>' +
			'</aside>' +
			'<div class="aimp-shop-backdrop" data-action="close-filters" hidden></div>' +
			'<div class="aimp-split aimp-shop-content">' +
			'<div class="aimp-split-main" data-role="grid"></div>' +
			'<aside class="aimp-split-side" data-role="panel"></aside>' +
			'</div>' +
			'</div>';

		this.languagesEl = this.root.querySelector('.aimp-languages');
		this.sidebar = this.root.querySelector('.aimp-shop-sidebar');
		this.backdrop = this.root.querySelector('.aimp-shop-backdrop');
		this.toggleBtn = this.root.querySelector('[data-action="toggle-filters"]');
		this.filtersEl = this.root.querySelector('[data-role="filters"]');
		this.gridEl = this.root.querySelector('[data-role="grid"]');
		this.panelEl = this.root.querySelector('[data-role="panel"]');

		this.root.addEventListener('click', function (e) {
			var target = e.target.closest('[data-action]');
			if (!target || !self.root.contains(target)) {
				return;
			}
			var action = target.getAttribute('data-action');
			if (action === 'toggle-filters') {
				self.setSidebar(!self.sidebarOpen);
			} else if (action === 'close-filters') {
				self.setSidebar(false);
			} else if (action === 'clear-filters') {
				self.clearFilters();
			}
		});
		this.root.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && self.sidebarOpen && isDrawer()) {
				self.setSidebar(false);
				self.toggleBtn.focus();
			}
		});
		if (window.matchMedia) {
			var mq = window.matchMedia(DRAWER_QUERY);
			var onChange = function () {
				self.setSidebar(isDrawer() ? false : storedSidebar() !== 'closed', true);
			};
			if (mq.addEventListener) {
				mq.addEventListener('change', onChange);
			} else if (mq.addListener) {
				mq.addListener(onChange);
			}
		}

		this.setSidebar(isDrawer() ? false : storedSidebar() !== 'closed', true);
		this.renderTexts();
		this.renderFilters();
		this.renderGrid();
		this.renderPanel();
	};

	/**
	 * Open or close the filter sidebar. On wide screens it is a column that is remembered;
	 * on narrow screens it slides in over the page.
	 */
	Shop.prototype.setSidebar = function (open, initial) {
		this.sidebarOpen = open;
		var drawer = isDrawer();
		this.root.classList.toggle('is-filters-open', open);
		this.root.classList.toggle('is-filters-closed', !open);
		this.toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
		this.backdrop.hidden = !(open && drawer);
		if (!initial && !drawer) {
			storeSidebar(open ? 'open' : 'closed');
		}
		if (open && drawer && !initial) {
			var first = this.sidebar.querySelector('input, select, button');
			if (first) {
				first.focus();
			}
		}
	};

	Shop.prototype.activeCount = function () {
		var f = this.state.filters;
		var count = (f.category ? 1 : 0) + (f.search ? 1 : 0) + (f.min !== '' || f.max !== '' ? 1 : 0) + (f.inStock ? 1 : 0) + f.skills.length;
		Object.keys(f.attrs).forEach(function (taxonomy) {
			count += f.attrs[taxonomy].length;
		});
		return count;
	};

	// Texts outside the filter list: toggle button, sidebar title, language flags.
	Shop.prototype.renderTexts = function () {
		var self = this;
		var info = UI.languageInfo(cfg.languages, lang);
		this.root.lang = info ? info.locale : lang;

		var count = this.activeCount();
		this.toggleBtn.innerHTML =
			'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>' +
			'<span>' + esc(t.filters) + '</span>' +
			(count ? '<span class="aimp-shop-count">' + count + '</span>' : '');
		this.root.querySelector('[data-role="filters-title"]').textContent = t.filters;
		var close = this.root.querySelector('.aimp-shop-close');
		close.setAttribute('aria-label', t.closeFilters);
		close.title = t.closeFilters;
		this.root.querySelector('[data-role="show-results"]').textContent = t.showResults;
		this.sidebar.setAttribute('aria-label', t.filters);

		this.languagesEl.setAttribute('aria-label', t.language);
		this.languagesEl.innerHTML = UI.languagesHtml(cfg.languages, lang);
		this.languagesEl.querySelectorAll('[data-lang]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				self.setLanguage(btn.getAttribute('data-lang'));
			});
		});
	};

	/* ---------------------------------------------------------------
	 * Filters
	 * ------------------------------------------------------------- */

	Shop.prototype.renderFilters = function () {
		var self = this;
		var s = this.state;
		var f = s.filters;
		var facets = s.facets;
		var id = this.id;

		if (!facets) {
			this.filtersEl.innerHTML = '<p class="aimp-loading">' + esc(t.loading) + '</p>';
			return;
		}

		var html = '';

		// Search
		html +=
			'<div class="aimp-filter-group aimp-filter-group--search">' +
			'<label class="screen-reader-text" for="' + id + '-search">' + esc(t.search) + '</label>' +
			'<input type="search" id="' + id + '-search" class="aimp-search" data-role="search" value="' + esc(f.search) + '" placeholder="' + esc(t.searchProducts) + '">' +
			'</div>';

		// Sort
		var sorts = [
			['recommended', t.sortRecommended],
			['name_asc', t.sortNameAsc],
			['name_desc', t.sortNameDesc],
			['price_asc', t.sortPriceAsc],
			['price_desc', t.sortPriceDesc],
			['newest', t.sortNewest]
		];
		html +=
			'<div class="aimp-filter-group">' +
			'<label class="aimp-filter-label" for="' + id + '-sort">' + esc(t.sortBy) + '</label>' +
			'<select id="' + id + '-sort" class="aimp-shop-select" data-role="sort">' +
			sorts.map(function (o) {
				return '<option value="' + o[0] + '"' + (f.sort === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
			}).join('') +
			'</select></div>';

		// Categories
		if (facets.categories && facets.categories.length) {
			html += '<details class="aimp-filter-group" open><summary>' + esc(t.category) + '</summary><ul class="aimp-filter-options">';
			[{ id: 0, name: t.all, depth: 0 }].concat(facets.categories).forEach(function (cat) {
				html +=
					'<li style="--aimp-depth:' + parseInt(cat.depth, 10) + '"><label class="aimp-filter-option">' +
					'<input type="radio" name="' + id + '-cat" value="' + esc(cat.id) + '" data-role="category"' + (f.category === cat.id ? ' checked' : '') + '> ' +
					'<span>' + esc(cat.name) + '</span></label></li>';
			});
			html += '</ul></details>';
		}

		// Skill level (patterns)
		if (facets.skills && facets.skills.length) {
			html += '<details class="aimp-filter-group" open><summary>' + esc(t.skillLevel) + '</summary><ul class="aimp-filter-options">';
			facets.skills.forEach(function (level) {
				html +=
					'<li><label class="aimp-filter-option">' +
					'<input type="checkbox" data-role="skill" value="' + esc(level.key) + '"' + (f.skills.indexOf(level.key) !== -1 ? ' checked' : '') + '> ' +
					'<span>' + esc(level.label) + '</span></label></li>';
			});
			html += '</ul></details>';
		}

		// Price
		var range = facets.price || { min: 0, max: 0 };
		if (range.max > 0) {
			html +=
				'<details class="aimp-filter-group" open><summary>' + esc(t.price) + ' (' + esc(cfg.currencySymbol) + ')</summary>' +
				'<div class="aimp-price-inputs">' +
				'<input type="number" min="0" step="1" inputmode="decimal" data-role="min" value="' + esc(f.min) + '" placeholder="' + esc(range.min) + '" aria-label="' + esc(t.minPrice) + '">' +
				'<span aria-hidden="true">–</span>' +
				'<input type="number" min="0" step="1" inputmode="decimal" data-role="max" value="' + esc(f.max) + '" placeholder="' + esc(range.max) + '" aria-label="' + esc(t.maxPrice) + '">' +
				'</div></details>';
		}

		// Attributes (colour, material, …): open the first few and any that is in use.
		(facets.attributes || []).forEach(function (attr, index) {
			var chosen = f.attrs[attr.taxonomy] || [];
			html +=
				'<details class="aimp-filter-group"' + (index < 3 || chosen.length ? ' open' : '') + '><summary>' + esc(attr.label) +
				(chosen.length ? ' <span class="aimp-shop-count">' + chosen.length + '</span>' : '') + '</summary><ul class="aimp-filter-options">';
			attr.terms.forEach(function (term) {
				html +=
					'<li><label class="aimp-filter-option">' +
					'<input type="checkbox" data-role="attr" data-taxonomy="' + esc(attr.taxonomy) + '" value="' + esc(term.slug) + '"' +
					(chosen.indexOf(term.slug) !== -1 ? ' checked' : '') + '> <span>' + esc(term.name) + '</span></label></li>';
			});
			html += '</ul></details>';
		});

		// Availability
		html +=
			'<details class="aimp-filter-group" open><summary>' + esc(t.availability) + '</summary>' +
			'<label class="aimp-filter-option"><input type="checkbox" data-role="in-stock"' + (f.inStock ? ' checked' : '') + '> <span>' + esc(t.inStockOnly) + '</span></label>' +
			'</details>';

		html += '<button type="button" class="aimp-shop-clear" data-action="clear-filters"' + (this.activeCount() ? '' : ' hidden') + '>' + esc(t.clearFilters) + '</button>';

		this.filtersEl.innerHTML = html;
		this.bindFilters();
	};

	Shop.prototype.bindFilters = function () {
		var self = this;
		var f = this.state.filters;
		var el = this.filtersEl;
		var changed = function () {
			self.state.page = 1;
			self.renderTexts();
			var clear = el.querySelector('[data-action="clear-filters"]');
			if (clear) {
				clear.hidden = !self.activeCount();
			}
			self.load(false);
		};

		var timer = null;
		var search = el.querySelector('[data-role="search"]');
		search.addEventListener('input', function () {
			clearTimeout(timer);
			timer = setTimeout(function () {
				var value = search.value.trim();
				if (value !== f.search) {
					f.search = value;
					changed();
				}
			}, 350);
		});

		el.querySelector('[data-role="sort"]').addEventListener('change', function (e) {
			f.sort = e.target.value;
			changed();
		});

		el.querySelectorAll('[data-role="category"]').forEach(function (input) {
			input.addEventListener('change', function () {
				f.category = parseInt(input.value, 10) || 0;
				changed();
			});
		});

		['min', 'max'].forEach(function (key) {
			var input = el.querySelector('[data-role="' + key + '"]');
			if (!input) {
				return;
			}
			var priceTimer = null;
			input.addEventListener('input', function () {
				clearTimeout(priceTimer);
				priceTimer = setTimeout(function () {
					var value = input.value === '' ? '' : String(Math.max(0, parseFloat(input.value) || 0));
					if (value !== f[key]) {
						f[key] = value;
						changed();
					}
				}, 500);
			});
		});

		el.querySelectorAll('[data-role="attr"]').forEach(function (input) {
			input.addEventListener('change', function () {
				var taxonomy = input.getAttribute('data-taxonomy');
				var list = (f.attrs[taxonomy] || []).filter(function (slug) {
					return slug !== input.value;
				});
				if (input.checked) {
					list.push(input.value);
				}
				if (list.length) {
					f.attrs[taxonomy] = list;
				} else {
					delete f.attrs[taxonomy];
				}
				// Update the counter next to the attribute name.
				var summary = input.closest('details').querySelector('summary');
				var badge = summary.querySelector('.aimp-shop-count');
				if (list.length) {
					if (!badge) {
						badge = document.createElement('span');
						badge.className = 'aimp-shop-count';
						summary.appendChild(document.createTextNode(' '));
						summary.appendChild(badge);
					}
					badge.textContent = list.length;
				} else if (badge) {
					badge.remove();
				}
				changed();
			});
		});

		el.querySelectorAll('[data-role="skill"]').forEach(function (input) {
			input.addEventListener('change', function () {
				f.skills = f.skills.filter(function (key) {
					return key !== input.value;
				});
				if (input.checked) {
					f.skills.push(input.value);
				}
				changed();
			});
		});

		el.querySelector('[data-role="in-stock"]').addEventListener('change', function (e) {
			f.inStock = e.target.checked;
			changed();
		});
	};

	Shop.prototype.clearFilters = function () {
		var sort = this.state.filters.sort;
		this.state.filters = defaultFilters();
		this.state.filters.sort = sort;
		this.state.page = 1;
		this.renderTexts();
		this.renderFilters();
		this.load(false);
	};

	/* ---------------------------------------------------------------
	 * Products
	 * ------------------------------------------------------------- */

	Shop.prototype.load = function (withFacets) {
		var self = this;
		var s = this.state;
		var f = s.filters;
		var params = {
			type: this.type,
			page: s.page,
			category: f.category,
			search: f.search,
			min: f.min,
			max: f.max,
			in_stock: f.inStock ? 1 : 0,
			skill: f.skills.join(','),
			sort: f.sort
		};
		Object.keys(f.attrs).forEach(function (taxonomy) {
			params['attr_' + taxonomy] = f.attrs[taxonomy].join(',');
		});
		if (withFacets) {
			params.facets = 1;
		}
		var key = lang + JSON.stringify(params);
		this.requestKey = key;
		s.data = null;
		this.renderGrid();

		UI.request(cfg.endpoint, 'shop', params, lang, t.error)
			.then(function (data) {
				if (self.requestKey !== key) {
					return;
				}
				s.data = data;
				s.page = data.page;
				if (data.facets) {
					s.facets = data.facets;
					self.renderFilters();
				}
				if (s.selected) {
					// Fresh copy (language, stock) when the product is still on this page.
					s.selected = UI.findById(data.items, s.selected.id) || s.selected;
				}
				self.writeUrl();
				self.renderGrid();
				self.renderPanel();
			})
			.catch(function (err) {
				if (self.requestKey === key) {
					self.gridEl.innerHTML = UI.errorHtml(err, t.error);
					if (withFacets && !s.facets) {
						self.filtersEl.innerHTML = '';
					}
				}
			});
	};

	Shop.prototype.renderGrid = function () {
		var self = this;
		var s = this.state;
		UI.renderGrid(
			this.gridEl,
			s.data,
			{
				selectedId: s.selected ? s.selected.id : null,
				emptyText: this.activeCount() ? t.noMatch : t.noProducts,
				compact: false,
				onSelect: function (item) {
					if (!s.selected || s.selected.id !== item.id) {
						s.selected = item;
						s.image = 0;
					}
					self.renderGrid();
					self.renderPanel();
					UI.scrollIntoViewIfNeeded(self.panelEl);
				},
				onPage: function (page) {
					s.page = page;
					self.load(false);
				}
			},
			t
		);
	};

	Shop.prototype.renderPanel = function () {
		var self = this;
		var s = this.state;
		var item = s.selected;
		if (!item) {
			this.panelEl.innerHTML = '<div class="aimp-side-placeholder">' + esc(t.selectHint) + '</div>';
			return;
		}

		var html =
			UI.galleryHtml(item.gallery, s.image, t, UI.favButton(item.id, 'aimp-fav--overlay')) +
			'<div class="aimp-details">' +
			'<h3 class="aimp-details-title">' + esc(item.name) + '</h3>' +
			'<p class="aimp-details-price">' + (item.price_html || '') + (item.price_suffix && item.price_html ? ' <small>' + esc(item.price_suffix) + '</small>' : '') + '</p>';
		if (item.buy) {
			html += this.buyBarHtml(item);
		}
		if (item.short_description) {
			html += '<div class="aimp-description">' + item.short_description + '</div>';
		}
		if (!item.in_stock && item.badge) {
			html += '<p class="aimp-sold-out">' + esc(item.badge) + '</p>';
		}
		html +=
			'<div class="aimp-actions">' +
			'<a class="aimp-button aimp-button--block" href="' + esc(item.permalink) + '">' + esc(t.viewProduct) + '</a>' +
			'</div></div>';
		this.panelEl.innerHTML = html;
		if (item.buy) {
			this.bindBuyBar(item);
		}

		var show = UI.bindGallery(this.panelEl, item.gallery, function (i) {
			s.image = i;
		});
		var main = this.panelEl.querySelector('.aimp-gallery-main img');
		if (main && item.gallery && item.gallery.length) {
			main.classList.add('is-zoomable');
			main.addEventListener('click', function () {
				UI.openLightbox(self.root, item.gallery, s.image, t);
			});
		}
		return show;
	};

	/* ---------------------------------------------------------------
	 * Buy bar in the details panel (like the product page): ‹ amount ›, the price for that amount and
	 * Add to cart, added in the background.
	 * ------------------------------------------------------------- */

	Shop.prototype.buyBarHtml = function (item) {
		var b = item.buy;
		var value = b.min || b.step || 1;
		return (
			'<div class="aimp-buy-bar aimp-shop-buy" data-aimp-shop-buy>' +
			'<p class="aimp-shop-buy-total" data-role="total"></p>' +
			'<div class="aimp-buy-controls">' +
			'<div class="aimp-stepper">' +
			'<button type="button" class="aimp-stepper-btn" data-dir="-1" aria-label="' + esc(t.less) + '">‹</button>' +
			'<input type="number" class="aimp-stepper-input" inputmode="numeric" value="' + value + '" min="' + (b.min || 1) + '" step="' + (b.step || 1) + '"' +
			(b.max ? ' max="' + b.max + '"' : '') + ' aria-label="' + esc(b.per_10cm ? t.lengthCm : t.quantity) + '">' +
			(b.per_10cm ? '<span class="aimp-stepper-unit">' + esc(t.cm) + '</span>' : '') +
			'<button type="button" class="aimp-stepper-btn" data-dir="1" aria-label="' + esc(t.more) + '">›</button>' +
			'</div>' +
			'<button type="button" class="aimp-button aimp-add-to-cart" data-role="add">' + esc(t.addToCart) + '</button>' +
			'</div>' +
			'<div class="aimp-shop-buy-message" data-role="message" aria-live="polite"></div>' +
			'</div>'
		);
	};

	Shop.prototype.bindBuyBar = function (item) {
		var b = item.buy;
		var bar = this.panelEl.querySelector('[data-aimp-shop-buy]');
		if (!bar) {
			return;
		}
		var field = bar.querySelector('.aimp-stepper-input');
		var total = bar.querySelector('[data-role="total"]');
		var message = bar.querySelector('[data-role="message"]');
		var add = bar.querySelector('[data-role="add"]');
		var step = b.step || 1;
		var min = b.min || step;
		var clean = function (value) {
			var n = Math.max(min, Math.ceil((parseFloat(value) || 0) / step) * step);
			return b.max ? Math.min(b.max, n) : n;
		};
		var show = function () {
			var units = b.per_10cm ? parseInt(field.value, 10) / 10 : parseInt(field.value, 10);
			total.textContent = b.unit_price && cfg.currency ? UI.money(b.unit_price * units, cfg.currency) : '';
		};
		bar.querySelectorAll('.aimp-stepper-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				field.value = clean((parseInt(field.value, 10) || min) + step * parseInt(btn.getAttribute('data-dir'), 10));
				show();
			});
		});
		field.addEventListener('change', function () {
			field.value = clean(field.value);
			show();
		});
		show();

		add.addEventListener('click', function () {
			field.value = clean(field.value);
			var qty = b.per_10cm ? parseInt(field.value, 10) / 10 : parseInt(field.value, 10);
			add.disabled = true;
			add.textContent = t.adding;
			message.innerHTML = '';
			UI.request(cfg.endpoint, 'add_to_cart', { product_id: item.id, quantity: qty }, lang, t.error)
				.then(function (data) {
					message.innerHTML =
						'<p class="aimp-shop-buy-ok">' + esc(data.message) +
						' <a href="' + esc(data.cart_url) + '">' + esc(t.viewCart) + '</a></p>';
					// The cart count in the header and the mini cart.
					if (window.jQuery) {
						window.jQuery(document.body).trigger('wc_fragment_refresh');
					}
					document.body.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart', { bubbles: true, detail: { preserveCartData: false } }));
				})
				.catch(function (err) {
					var errors = (err && err.errors) || [t.error];
					message.innerHTML = '<p class="aimp-shop-buy-error">' + errors.map(esc).join('<br>') + '</p>';
				})
				.then(function () {
					add.disabled = false;
					add.textContent = t.addToCart;
				});
		});
	};

	/* ---------------------------------------------------------------
	 * Language
	 * ------------------------------------------------------------- */

	Shop.prototype.setLanguage = function (code) {
		if (!cfg.i18n[code] || code === lang) {
			return;
		}
		lang = code;
		t = cfg.i18n[code];
		UI.persistLang(code);
		// Other shop blocks on the same page follow.
		document.querySelectorAll('[data-aimp-shop]').forEach(function (root) {
			if (root.aimpShop) {
				root.aimpShop.refreshLanguage();
			}
		});
		var active = this.languagesEl.querySelector('.is-active');
		if (active) {
			active.focus();
		}
	};

	Shop.prototype.refreshLanguage = function () {
		this.renderTexts();
		this.state.facets = null;
		this.renderFilters();
		this.renderPanel();
		this.load(true);
	};

	/* ---------------------------------------------------------------
	 * Boot
	 * ------------------------------------------------------------- */

	function boot() {
		var first = true;
		document.querySelectorAll('[data-aimp-shop]').forEach(function (root) {
			if (!root.aimpShop) {
				root.aimpShop = new Shop(root, first);
				first = false;
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
