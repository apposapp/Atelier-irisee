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
	var UI = window.aimpUI;
	if (!cfg || !UI) {
		return;
	}

	/* ---------------------------------------------------------------
	 * Language
	 * ------------------------------------------------------------- */

	function validLang(code) {
		return !!(code && cfg.i18n[code]);
	}

	function initialLang() {
		return UI.initialLang(cfg.i18n, cfg.defaultLanguage);
	}

	// The cookie lets the cart and checkout show the plugin's texts in the same language.
	function persistLang(code) {
		UI.persistLang(code);
	}

	var lang = initialLang();
	var t = cfg.i18n[lang];
	persistLang(lang);

	function languageInfo(code) {
		return UI.languageInfo(cfg.languages, code);
	}

	/* ---------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------- */

	// Decimal separator of the active language: 20.5 -> 20,5 in Dutch and French.
	function num(value) {
		var info = languageInfo(lang);
		return String(value).replace('.', info && info.decimal ? info.decimal : '.');
	}

	var esc = UI.esc;

	var fmt = UI.fmt;

	function money(amount) {
		return UI.money(amount, cfg.currency);
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
		return UI.request(cfg.endpoint, action, data, lang, t.error);
	}

	var scrollIntoViewIfNeeded = UI.scrollIntoViewIfNeeded;

	// Title of a details panel (the favorite heart sits on the picture, see galleryHtml).
	function detailsTitle(name) {
		return '<div class="aimp-details-head"><h3 class="aimp-details-title">' + esc(name) + '</h3></div>';
	}

	var findById = UI.findById;

	// Replace a selected item with its freshly loaded copy (new language, current stock).
	function rebind(selected, items) {
		return selected ? findById(items, selected.id) || selected : selected;
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
		this.openFromLink();
	}

	// "Complete it in the configurator" on a product page links here with ?aimp_pattern=ID: start with that pattern selected.
	Configurator.prototype.openFromLink = function () {
		var kit = window.location.search.match(/[?&]aimp_kit=([a-z0-9]+)/);
		if (kit) {
			this.openSavedKit(kit[1]);
			return;
		}
		var match = window.location.search.match(/[?&]aimp_pattern=(\d+)/);
		if (!match) {
			return;
		}
		var self = this;
		var s = this.state;
		request('sizes', { pattern: parseInt(match[1], 10) })
			.then(function (data) {
				if (s.pattern || s.step !== 'pattern') {
					return;
				}
				s.pattern = data.pattern;
				s.patternImage = 0;
				s.sizesData = data;
				s.size = null;
				self.resetMaterials();
				self.renderPatternGrid();
				self.renderSizePanel();
				self.renderSteps();
				scrollIntoViewIfNeeded(self.body.querySelector('[data-role="size-panel"]'));
			})
			.catch(function () {});
	};

	// Fit the side panels and mark the favorite hearts after every change (see ui.js).
	Configurator.prototype.watchLayout = function () {
		UI.watchLayout(this.root);
		this.setupMobileBar();
	};

	/*
	 * Phones: a fixed bar at the bottom with the current choice and the step's main button ("Confirm",
	 * "Continue", "Add to cart"); it presses the real button. It lives in <body>, because the
	 * configurator is a CSS container and would trap a fixed bar.
	 */
	Configurator.prototype.setupMobileBar = function () {
		var self = this;
		if (!window.matchMedia || this.mobileBar) {
			return;
		}
		var bar = document.createElement('div');
		bar.className = 'aimp-config-bar';
		bar.hidden = true;
		bar.innerHTML = '<span class="aimp-config-bar-label"></span><button type="button" class="aimp-config-bar-button"></button>';
		document.body.appendChild(bar);
		this.mobileBar = bar;
		var phone = window.matchMedia('(max-width: 700px)');
		var primary = function () {
			var buttons = self.root.querySelectorAll('[data-action="confirm"], [data-action="confirm-fabric"], [data-action="continue"], [data-action="add"]');
			for (var i = 0; i < buttons.length; i++) {
				if (!buttons[i].hidden && !buttons[i].disabled && buttons[i].offsetParent !== null) {
					return buttons[i];
				}
			}
			return null;
		};
		var label = function () {
			var s = self.state;
			if (s.step === 'pattern') {
				return s.pattern ? s.pattern.name + (s.size ? ' – ' + s.size.label : '') : '';
			}
			if (s.step === 'fabric') {
				return s.fabric ? s.fabric.name : '';
			}
			return '';
		};
		var timer = null;
		// Phones show the tables as cards: every cell gets its column name (configurator.css).
		var labelTables = function () {
			self.root.querySelectorAll('table.aimp-size-chart, table.aimp-summary').forEach(function (table) {
				var heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
					return th.textContent.trim();
				});
				table.querySelectorAll('tbody tr').forEach(function (tr) {
					Array.prototype.forEach.call(tr.children, function (cell, i) {
						if (heads[i] && !cell.hasAttribute('data-label')) {
							cell.setAttribute('data-label', heads[i]);
						}
					});
				});
			});
		};
		var update = function () {
			var button = phone.matches ? primary() : null;
			bar.hidden = !button;
			document.body.classList.toggle('aimp-has-config-bar', !!button);
			labelTables();
			if (button) {
				bar.querySelector('.aimp-config-bar-label').textContent = label();
				bar.querySelector('.aimp-config-bar-button').textContent = button.textContent.trim();
			}
		};
		bar.querySelector('button').addEventListener('click', function () {
			var button = primary();
			if (button) {
				button.click();
			}
		});
		new MutationObserver(function () {
			clearTimeout(timer);
			timer = setTimeout(update, 50);
		}).observe(this.root, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'hidden'] });
		if (phone.addEventListener) {
			phone.addEventListener('change', update);
		}
		update();
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
		this.languagesEl.innerHTML = UI.languagesHtml(cfg.languages, lang);
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

	// Every new step starts at the very top of the page.
	Configurator.prototype.goTo = function (key) {
		this.state.step = key;
		this.render();
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
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
		return UI.paginationHtml(data, t);
	};

	// Product cards with pagination (see ui.js for the options).
	Configurator.prototype.renderGrid = function (container, data, opts) {
		UI.renderGrid(container, data, opts, t);
	};

	// One big picture with the other pictures as selectable thumbnails underneath, and the favorite heart in its corner.
	Configurator.prototype.galleryHtml = function (images, index, productId) {
		return UI.galleryHtml(images, index, t, productId ? UI.favButton(productId, 'aimp-fav--overlay') : '');
	};

	Configurator.prototype.bindGallery = function (container, images, onChange) {
		UI.bindGallery(container, images, onChange);
	};

	// Details of a fabric, button or zip: gallery, description, attributes and prices.
	Configurator.prototype.materialDetailsHtml = function (item, imageIndex, unitLabel, needText) {
		var html = '<div class="aimp-details">';
		html += this.galleryHtml(item.gallery, imageIndex, item.id);
		html += detailsTitle(item.name);
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
		// Cards with a gold icon, like the fabric specifications.
		var icons = cfg.icons || {};
		var card = function (icon, label, value) {
			return (
				'<li class="aimp-need">' + (icons[icon] || '') +
				'<span class="aimp-need-text"><span class="aimp-need-label">' + esc(label) + '</span>' +
				(value !== '' ? '<strong class="aimp-need-value">' + esc(value) + '</strong>' : '') + '</span></li>'
			);
		};
		var rows = [];
		if (size.fabric_units > 0) {
			rows.push(card('fabric', t.fabricNeeded, size.fabric_text));
		}
		NOTIONS.forEach(function (n) {
			if (n.qty(size) > 0) {
				rows.push(card(n.type, t[n.label], n.need(size)));
			}
		});
		if (!rows.length) {
			rows.push(card('', t.none, ''));
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
		html += this.galleryHtml(pattern.gallery, s.patternImage, pattern.id);
		html += detailsTitle(pattern.name);
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
		var dialog = UI.openLightbox(this.root, [{ src: cfg.measureImage, alt: t.howToMeasure }], 0, t);
		dialog.setAttribute('aria-label', t.howToMeasure);
	};

	/* ---------------------------------------------------------------
	 * Step 2: fabric
	 * ------------------------------------------------------------- */

	// Recap at the top of the later steps: small pictures and names of what has been chosen so far (and the size).
	Configurator.prototype.recapHtml = function () {
		var s = this.state;
		var items = [{ image: s.pattern.image, name: s.pattern.name, detail: t.size + ' ' + s.size.label }];
		if (s.step !== 'fabric' && s.fabric && s.size.fabric_units > 0) {
			items.push({ image: s.fabric.image, name: s.fabric.name });
		}
		if (s.step === 'summary') {
			NOTIONS.forEach(function (n) {
				var selected = s.notions[n.type].selected;
				if (n.qty(s.size) > 0 && selected) {
					items.push({ image: selected.image, name: selected.name });
				}
			});
		}
		var html = '<div class="aimp-recap"><ul class="aimp-recap-items">';
		items.forEach(function (item) {
			html +=
				'<li class="aimp-recap-item">' +
				'<img src="' + esc(item.image) + '" alt="" width="44" height="44" loading="lazy">' +
				'<span><strong>' + esc(item.name) + '</strong>' + (item.detail ? '<small>' + esc(item.detail) + '</small>' : '') + '</span>' +
				'</li>';
		});
		return html + '</ul></div>';
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

		// The same filter panel as the shop pages: a gold column on the left that opens and closes.
		this.body.innerHTML =
			'<div class="aimp-step aimp-step--fabric aimp-fabric-filters">' +
			this.recapHtml() +
			'<h3>' + esc(t.chooseFabric) + '</h3>' +
			'<div class="aimp-fabric-filterbar">' +
			'<button type="button" class="aimp-button aimp-shop-toggle" data-action="toggle-filters" aria-controls="aimp-fabric-filters"></button>' +
			'</div>' +
			'<div class="aimp-shop-layout">' +
			'<aside class="aimp-shop-sidebar" id="aimp-fabric-filters" aria-label="' + esc(t.filters) + '">' +
			'<div class="aimp-filter-group aimp-filter-group--search">' +
			'<input type="search" class="aimp-search" data-role="search" value="' + esc(f.search) + '" placeholder="' + esc(t.searchFabrics) + '" aria-label="' + esc(t.searchFabrics) + '">' +
			'</div>' +
			'<div class="aimp-filter-group">' +
			'<label class="aimp-filter-label" for="aimp-fabric-sort">' + esc(t.sortBy) + '</label>' +
			'<select id="aimp-fabric-sort" class="aimp-shop-select" data-role="sort">' +
			sorts.map(function (o) {
				return '<option value="' + o[0] + '"' + (f.sort === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
			}).join('') +
			'</select></div>' +
			'<details class="aimp-filter-group" open data-role="fabric-cats-group"><summary>' + esc(t.fabricCategory) + '</summary>' +
			'<ul class="aimp-filter-options" data-role="fabric-cats"></ul></details>' +
			'<details class="aimp-filter-group" open><summary>' + esc(t.availability) + '</summary>' +
			'<label class="aimp-filter-option"><input type="checkbox" data-role="in-stock"' + (f.inStock ? ' checked' : '') + '> <span>' + esc(t.inStockOnly) + '</span></label>' +
			'</details>' +
			'</aside>' +
			'<div class="aimp-split aimp-shop-content">' +
			'<div class="aimp-split-main" data-role="fabrics"></div>' +
			'<aside class="aimp-split-side" data-role="fabric-panel"></aside>' +
			'</div>' +
			'</div>' +
			'<div class="aimp-actions">' +
			'<button type="button" class="aimp-button aimp-button--ghost" data-action="back">' + esc(t.back) + '</button>' +
			'</div>' +
			'</div>';

		this.body.querySelector('[data-action="back"]').addEventListener('click', function () {
			self.prev();
		});

		var step = this.body.querySelector('.aimp-fabric-filters');
		var toggle = this.body.querySelector('[data-action="toggle-filters"]');
		var setOpen = function (open, remember) {
			step.classList.toggle('is-filters-open', open);
			step.classList.toggle('is-filters-closed', !open);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (remember) {
				try {
					window.localStorage.setItem('aimp_shop_filters', open ? 'open' : 'closed');
				} catch (e) {}
			}
		};
		var stored = null;
		try {
			stored = window.localStorage.getItem('aimp_shop_filters');
		} catch (e) {}
		var narrow = window.matchMedia && window.matchMedia('(max-width: 999px)').matches;
		setOpen(narrow ? false : stored !== 'closed', false);
		toggle.addEventListener('click', function () {
			setOpen(!step.classList.contains('is-filters-open'), true);
		});
		this.renderFilterToggle();

		var reload = function () {
			s.fabricPage = 1;
			self.renderFilterToggle();
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

	// "Filters" with the number of active filters, like on the shop pages.
	Configurator.prototype.renderFilterToggle = function () {
		var toggle = this.body.querySelector('[data-action="toggle-filters"]');
		if (!toggle) {
			return;
		}
		var f = this.state.fabricFilters;
		var count = (f.category ? 1 : 0) + (f.search ? 1 : 0) + (f.inStock ? 1 : 0);
		toggle.innerHTML =
			'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>' +
			'<span>' + esc(t.filters) + '</span>' +
			(count ? '<span class="aimp-shop-count">' + count + '</span>' : '');
	};

	// Fabric categories in the filter panel: one choice (radio buttons), "All" first.
	Configurator.prototype.renderFabricCategories = function () {
		var self = this;
		var s = this.state;
		var container = this.body.querySelector('[data-role="fabric-cats"]');
		var group = this.body.querySelector('[data-role="fabric-cats-group"]');
		if (!container) {
			return;
		}
		var cats = s.fabricCategories || [];
		if (cats.length < 2) {
			container.innerHTML = '';
			group.hidden = true;
			return;
		}
		group.hidden = false;
		container.innerHTML = [{ id: 0, name: t.all }]
			.concat(cats)
			.map(function (cat) {
				var active = s.fabricFilters.category === cat.id;
				return (
					'<li><label class="aimp-filter-option">' +
					'<input type="radio" name="aimp-fabric-cat" value="' + esc(cat.id) + '" data-fabric-cat' + (active ? ' checked' : '') + '> ' +
					'<span>' + esc(cat.name) + '</span></label></li>'
				);
			})
			.join('');
		container.querySelectorAll('[data-fabric-cat]').forEach(function (input) {
			input.addEventListener('change', function () {
				s.fabricFilters.category = parseInt(input.value, 10) || 0;
				s.fabricPage = 1;
				self.renderFilterToggle();
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
		var discount = parseInt(cfg.kitDiscount, 10) || 0;
		// Original price crossed out, then the price with the sewing project kit discount.
		var priceHtml = function (amount) {
			if (!discount) {
				return esc(money(amount));
			}
			return '<del>' + esc(money(amount)) + '</del> <ins>' + esc(money(amount * (100 - discount) / 100)) + '</ins>';
		};

		var html = '<div class="aimp-step aimp-step--summary">' + this.recapHtml() + '<h3>' + esc(t.summaryTitle) + '</h3>';
		html += '<div class="aimp-table-scroll"><table class="aimp-summary"><thead><tr><th>' + esc(t.product) + '</th><th>' + esc(t.quantity) + '</th><th>' + esc(t.price) + '</th></tr></thead><tbody>';
		lines.forEach(function (line) {
			var subtotal = line.price * line.qty;
			total += subtotal;
			html +=
				'<tr><td>' + esc(line.name) + (line.detail ? '<br><small>' + esc(line.detail) + '</small>' : '') + '</td>' +
				'<td>' + esc(line.qty) + '</td><td class="aimp-summary-price">' + priceHtml(subtotal) + '</td></tr>';
		});
		html += '</tbody><tfoot>';
		if (discount) {
			html += '<tr class="aimp-summary-discount"><th colspan="2">' + esc(fmt(t.kitDiscount, discount)) + '</th><td>−' + esc(money(total * discount / 100)) + '</td></tr>';
		}
		html += '<tr><th colspan="2">' + esc(t.total) + '</th><td class="aimp-summary-price">' + priceHtml(total) + '</td></tr></tfoot></table></div>';
		html += '<p class="aimp-help">' + esc(t.lockedNote) + '</p>';
		html += '<div data-role="messages"></div>';
		html +=
			'<div class="aimp-actions">' +
			'<button type="button" class="aimp-button aimp-button--ghost" data-action="back"' + (s.added ? ' hidden' : '') + '>' + esc(t.back) + '</button>' +
			'<button type="button" class="aimp-button aimp-button--ghost" data-action="save">' + esc(t.saveKit) + '</button>' +
			'<button type="button" class="aimp-button" data-action="add"' + (s.added ? ' hidden' : '') + '>' + esc(t.addToCart) + '</button>' +
			'</div><div data-role="save-message"></div></div>';
		this.body.innerHTML = html;

		this.body.querySelector('[data-action="back"]').addEventListener('click', function () {
			self.prev();
		});
		this.body.querySelector('[data-action="add"]').addEventListener('click', function () {
			self.addToCart(this);
		});
		this.body.querySelector('[data-action="save"]').addEventListener('click', function () {
			self.saveKit(this);
		});
	};

	// "Save this kit": to the customer's account, listed on the favorites page. Guests are asked to log in.
	Configurator.prototype.saveKit = function (button) {
		var s = this.state;
		var box = this.body.querySelector('[data-role="save-message"]');
		if (!cfg.loggedIn) {
			box.innerHTML = '<div class="aimp-notice"><p>' + esc(t.loginToSave) + ' <a href="#" class="aimp-login-tgr">' + esc(t.logIn) + '</a></p></div>';
			return;
		}
		var params = {
			nonce: cfg.nonce,
			variation: s.size.id,
			fabric: s.size.fabric_units > 0 && s.fabric ? s.fabric.id : 0
		};
		NOTIONS.forEach(function (n) {
			var selected = s.notions[n.type].selected;
			params[n.role] = n.qty(s.size) > 0 && selected ? selected.id : 0;
		});
		button.disabled = true;
		button.textContent = t.saving;
		request('save_kit', params)
			.then(function (data) {
				button.hidden = true;
				box.innerHTML =
					'<div class="aimp-notice aimp-notice--success"><p>' + esc(data.message) +
					(data.url ? ' <a href="' + esc(data.url) + '">' + esc(t.viewFavorites) + '</a>' : '') + '</p></div>';
			})
			.catch(function (err) {
				button.disabled = false;
				button.textContent = t.saveKit;
				this.showError(box, err);
			}.bind(this));
	};

	// "Continue" on a saved kit (favorites page) links here with ?aimp_kit=ID: open it with every choice made.
	Configurator.prototype.openSavedKit = function (id) {
		var self = this;
		var s = this.state;
		request('load_kit', { kit: id })
			.then(function (data) {
				var size = findById(data.sizes.sizes, data.size);
				s.pattern = data.sizes.pattern;
				s.patternImage = 0;
				s.sizesData = data.sizes;
				s.size = size && size.available ? size : null;
				self.resetMaterials();
				if (!s.size) {
					self.renderPatternGrid();
					self.renderSizePanel();
					self.renderSteps();
					return;
				}
				s.fabric = data.fabric || null;
				NOTIONS.forEach(function (n) {
					s.notions[n.type].selected = (data.notions && data.notions[n.type]) || null;
				});
				// The first step that still needs a choice, otherwise the overview.
				var step = 'summary';
				if (s.size.fabric_units > 0 && !s.fabric) {
					step = 'fabric';
				} else if (self.notionTypes().some(function (type) {
					return !s.notions[type].selected;
				})) {
					step = 'notions';
				}
				self.goTo(step);
				if (data.message) {
					var note = document.createElement('div');
					note.className = 'aimp-notice';
					note.innerHTML = '<p>' + esc(data.message) + '</p>';
					self.body.insertBefore(note, self.body.firstChild);
				}
			})
			.catch(function (err) {
				// The normal first step stays usable; the message goes above it.
				var box = document.createElement('div');
				self.body.insertBefore(box, self.body.firstChild);
				self.showError(box, err);
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
