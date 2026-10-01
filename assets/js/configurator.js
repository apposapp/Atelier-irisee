/**
 * Atelier Irisee pattern configurator.
 *
 * Steps: pattern & size -> fabric -> buttons & zips -> summary -> add to cart.
 * All rules (allowed fabrics, quantities, stock) are enforced again on the server.
 */
(function () {
	'use strict';

	var cfg = window.aimpConfig;
	if (!cfg) {
		return;
	}
	var t = cfg.i18n;

	/* ---------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------- */

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
		var num = parts.join(c.decimal);
		switch (c.position) {
			case 'right':
				return num + c.symbol;
			case 'left_space':
				return c.symbol + ' ' + num;
			case 'right_space':
				return num + ' ' + c.symbol;
			default:
				return c.symbol + num;
		}
	}

	function measure(value) {
		return value === '' || value === null || value === undefined ? '–' : esc(value) + ' ' + esc(t.cm);
	}

	function request(action, data) {
		var body = new URLSearchParams();
		Object.keys(data || {}).forEach(function (key) {
			body.append(key, data[key]);
		});
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

	/* ---------------------------------------------------------------
	 * Configurator
	 * ------------------------------------------------------------- */

	function Configurator(root) {
		this.root = root;
		this.reset();
		this.root.innerHTML =
			'<ol class="aimp-steps"></ol>' +
			'<div class="aimp-body" aria-live="polite"></div>';
		this.stepsEl = root.querySelector('.aimp-steps');
		this.body = root.querySelector('.aimp-body');
		this.render();
	}

	Configurator.prototype.reset = function () {
		this.state = {
			step: 'pattern',
			category: 0,
			patternPage: 1,
			patterns: null,
			pattern: null,
			sizesData: null,
			size: null,
			fabricPage: 1,
			fabrics: null,
			fabric: null,
			notions: {
				buttons: { page: 1, data: null, selected: null },
				zips: { page: 1, data: null, selected: null }
			},
			added: false
		};
	};

	// Clears every choice that depends on the selected size.
	Configurator.prototype.resetMaterials = function () {
		var s = this.state;
		s.fabricPage = 1;
		s.fabrics = null;
		s.fabric = null;
		s.notions.buttons = { page: 1, data: null, selected: null };
		s.notions.zips = { page: 1, data: null, selected: null };
	};

	Configurator.prototype.steps = function () {
		var size = this.state.size;
		var steps = [{ key: 'pattern', label: t.stepPattern }];
		if (!size || size.fabric_units > 0) {
			steps.push({ key: 'fabric', label: t.stepFabric });
		}
		if (!size || size.button_count > 0 || size.zip_count > 0) {
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
	 * Shared grid
	 * ------------------------------------------------------------- */

	/**
	 * @param {Element} container
	 * @param {Object}  data        { items, page, pages }
	 * @param {Object}  opts        { selectedId, onSelect(item), onPage(page), emptyText, unavailableText, toggle }
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

		var html = '<div class="aimp-grid">';
		data.items.forEach(function (item) {
			var selected = opts.selectedId === item.id;
			var unavailable = item.available === false;
			html +=
				'<button type="button" class="aimp-card' + (selected ? ' is-selected' : '') + (unavailable ? ' is-unavailable' : '') + '"' +
				' data-id="' + esc(item.id) + '" aria-pressed="' + (selected ? 'true' : 'false') + '"' +
				(unavailable ? ' disabled' : '') + '>' +
				'<span class="aimp-card-image"><img src="' + esc(item.image) + '" alt="' + esc(item.image_alt || item.name) + '" loading="lazy"></span>' +
				'<span class="aimp-card-name">' + esc(item.name) + '</span>' +
				'<span class="aimp-card-price">' + (item.price_html || '') + '</span>' +
				(unavailable ? '<span class="aimp-badge">' + esc(opts.unavailableText || '') + '</span>' : '') +
				(selected ? '<span class="aimp-badge aimp-badge--selected">' + esc(t.selected) + '</span>' : '') +
				'</button>';
		});
		html += '</div>';

		if (data.pages > 1) {
			html +=
				'<nav class="aimp-pagination">' +
				'<button type="button" class="aimp-button aimp-button--ghost" data-page="' + (data.page - 1) + '"' + (data.page <= 1 ? ' disabled' : '') + '>' + esc(t.previous) + '</button>' +
				'<span>' + esc(fmt(t.pageOf, data.page, data.pages)) + '</span>' +
				'<button type="button" class="aimp-button aimp-button--ghost" data-page="' + (data.page + 1) + '"' + (data.page >= data.pages ? ' disabled' : '') + '>' + esc(t.next) + '</button>' +
				'</nav>';
		}

		container.innerHTML = html;

		container.querySelectorAll('.aimp-card').forEach(function (card) {
			card.addEventListener('click', function () {
				var id = parseInt(card.getAttribute('data-id'), 10);
				for (var i = 0; i < data.items.length; i++) {
					if (data.items[i].id === id) {
						opts.onSelect(data.items[i]);
						return;
					}
				}
			});
		});
		container.querySelectorAll('[data-page]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				opts.onPage(parseInt(btn.getAttribute('data-page'), 10));
			});
		});
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

		html += '<div class="aimp-grid-wrap" data-role="patterns"></div>';
		html += '<div data-role="size-panel"></div>';
		html += '</div>';
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
		var wanted = s.category + ':' + s.patternPage;
		s.patterns = null;
		this.renderPatternGrid();
		request('patterns', { category: s.category, page: s.patternPage })
			.then(function (data) {
				// Ignore stale responses after a quick filter change.
				if (wanted !== s.category + ':' + s.patternPage) {
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
				scrollIntoViewIfNeeded(container);
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
		s.sizesData = null;
		s.size = null;
		this.resetMaterials();
		this.renderPatternGrid();
		this.renderSizePanel();
		this.renderSteps();

		request('sizes', { pattern: item.id })
			.then(function (data) {
				if (!s.pattern || s.pattern.id !== item.id) {
					return;
				}
				s.sizesData = data;
				self.renderSizePanel();
				scrollIntoViewIfNeeded(self.body.querySelector('[data-role="size-panel"]'));
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
		rows.push('<li><strong>' + esc(t.buttons) + ':</strong> ' + (size.button_count > 0 ? esc(size.button_count) : esc(t.none)) + '</li>');
		rows.push(
			'<li><strong>' + esc(t.zips) + ':</strong> ' +
			(size.zip_count > 0 ? esc(fmt(t.zipOf, size.zip_count, size.zip_length)) : esc(t.none)) +
			'</li>'
		);
		return '<ul class="aimp-needs">' + rows.join('') + '</ul>';
	};

	Configurator.prototype.renderSizePanel = function () {
		var self = this;
		var s = this.state;
		var panel = this.body.querySelector('[data-role="size-panel"]');
		if (!panel) {
			return;
		}
		if (!s.pattern) {
			panel.innerHTML = '';
			return;
		}
		if (!s.sizesData) {
			panel.innerHTML = '<div class="aimp-panel"><p class="aimp-loading">' + esc(t.loading) + '</p></div>';
			return;
		}

		var pattern = s.sizesData.pattern;
		var sizes = s.sizesData.sizes;
		var size = s.size;

		var html = '<div class="aimp-panel aimp-size-panel">';
		html += '<div class="aimp-panel-media"><img src="' + esc(pattern.image_large) + '" alt="' + esc(pattern.image_alt || pattern.name) + '"></div>';
		html += '<div class="aimp-panel-info">';
		html += '<h3 class="aimp-panel-title">' + esc(pattern.name) + '</h3>';
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
			html += '<div class="aimp-size-details">';
			html +=
				'<dl class="aimp-measurements">' +
				'<div><dt>' + esc(t.bust) + '</dt><dd>' + measure(size.bust) + '</dd></div>' +
				'<div><dt>' + esc(t.waist) + '</dt><dd>' + measure(size.waist) + '</dd></div>' +
				'<div><dt>' + esc(t.height) + '</dt><dd>' + measure(size.height) + '</dd></div>' +
				'</dl>';
			html += '<p class="aimp-needs-title">' + esc(t.needs) + ':</p>' + this.needsHtml(size);
			html += '<p class="aimp-size-price">' + size.price_html + '</p>';
			html += '</div>';
		}
		html += '</div>'; // info

		html += '<div class="aimp-size-chart-wrap"><h4>' + esc(t.sizeChart) + '</h4><div class="aimp-table-scroll"><table class="aimp-size-chart">';
		html += '<thead><tr><th>' + esc(t.size) + '</th><th>' + esc(t.bust) + '</th><th>' + esc(t.waist) + '</th><th>' + esc(t.height) + '</th></tr></thead><tbody>';
		sizes.forEach(function (sz) {
			html +=
				'<tr' + (size && size.id === sz.id ? ' class="is-selected"' : '') + '>' +
				'<th scope="row">' + esc(sz.label) + '</th>' +
				'<td>' + measure(sz.bust) + '</td><td>' + measure(sz.waist) + '</td><td>' + measure(sz.height) + '</td>' +
				'</tr>';
		});
		html += '</tbody></table></div></div>';

		html +=
			'<div class="aimp-actions">' +
			'<button type="button" class="aimp-button" data-action="confirm"' + (size ? '' : ' disabled') + '>' + esc(t.confirmPattern) + '</button>' +
			'</div>';
		html += '</div>';
		panel.innerHTML = html;

		panel.querySelectorAll('[data-size]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var id = parseInt(btn.getAttribute('data-size'), 10);
				var chosen = sizes.filter(function (sz) {
					return sz.id === id;
				})[0];
				if (!chosen || (s.size && s.size.id === id)) {
					return;
				}
				s.size = chosen;
				self.resetMaterials();
				self.renderSizePanel();
				self.renderSteps();
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

	/* ---------------------------------------------------------------
	 * Step 2: fabric
	 * ------------------------------------------------------------- */

	Configurator.prototype.recapHtml = function () {
		var s = this.state;
		return (
			'<div class="aimp-recap"><strong>' + esc(s.pattern.name) + '</strong> – ' + esc(t.size) + ' ' + esc(s.size.label) +
			this.needsHtml(s.size) +
			'</div>'
		);
	};

	Configurator.prototype.renderFabricStep = function () {
		var self = this;
		var s = this.state;
		this.body.innerHTML =
			'<div class="aimp-step aimp-step--fabric">' +
			this.recapHtml() +
			'<h3>' + esc(t.chooseFabric) + '</h3>' +
			'<div class="aimp-grid-wrap" data-role="fabrics"></div>' +
			'<div data-role="fabric-panel"></div>' +
			'<div class="aimp-actions">' +
			'<button type="button" class="aimp-button aimp-button--ghost" data-action="back">' + esc(t.back) + '</button>' +
			'<button type="button" class="aimp-button" data-action="confirm"' + (s.fabric ? '' : ' disabled') + '>' + esc(t.confirmFabric) + '</button>' +
			'</div>' +
			'</div>';

		this.body.querySelector('[data-action="back"]').addEventListener('click', function () {
			self.prev();
		});
		this.body.querySelector('[data-action="confirm"]').addEventListener('click', function () {
			if (s.fabric) {
				self.next();
			}
		});

		this.renderFabricGrid();
		this.renderFabricPanel();
		if (!s.fabrics) {
			this.loadFabrics();
		}
	};

	Configurator.prototype.loadFabrics = function () {
		var self = this;
		var s = this.state;
		var sizeId = s.size.id;
		var page = s.fabricPage;
		s.fabrics = null;
		this.renderFabricGrid();
		request('fabrics', { variation: sizeId, page: page })
			.then(function (data) {
				if (!s.size || s.size.id !== sizeId || s.fabricPage !== page) {
					return;
				}
				s.fabrics = data;
				if (s.step === 'fabric') {
					self.renderFabricGrid();
				}
			})
			.catch(function (err) {
				var container = self.body.querySelector('[data-role="fabrics"]');
				if (container) {
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
		this.renderGrid(container, s.fabrics, {
			selectedId: s.fabric ? s.fabric.id : null,
			emptyText: t.noFabrics,
			unavailableText: t.notEnoughStock,
			onSelect: function (item) {
				s.fabric = item;
				self.renderFabricGrid();
				self.renderFabricPanel();
				self.body.querySelector('[data-action="confirm"]').disabled = false;
				scrollIntoViewIfNeeded(self.body.querySelector('[data-role="fabric-panel"]'));
			},
			onPage: function (page) {
				s.fabricPage = page;
				self.loadFabrics();
				scrollIntoViewIfNeeded(container);
			}
		});
	};

	Configurator.prototype.materialInfoHtml = function (item, unitLabel, needText) {
		var html = '<div class="aimp-panel aimp-material-panel">';
		html += '<div class="aimp-panel-media"><img src="' + esc(item.image_large) + '" alt="' + esc(item.image_alt || item.name) + '"></div>';
		html += '<div class="aimp-panel-info">';
		html += '<h3 class="aimp-panel-title">' + esc(item.name) + '</h3>';
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
		html += '</dl></div></div>';
		return html;
	};

	Configurator.prototype.renderFabricPanel = function () {
		var s = this.state;
		var panel = this.body.querySelector('[data-role="fabric-panel"]');
		if (!panel) {
			return;
		}
		panel.innerHTML = s.fabric ? this.materialInfoHtml(s.fabric, t.pricePerUnit, s.size.fabric_text) : '';
	};

	/* ---------------------------------------------------------------
	 * Step 3: buttons & zips
	 * ------------------------------------------------------------- */

	Configurator.prototype.notionTypes = function () {
		var size = this.state.size;
		var types = [];
		if (size.button_count > 0) {
			types.push('buttons');
		}
		if (size.zip_count > 0) {
			types.push('zips');
		}
		return types;
	};

	Configurator.prototype.renderNotionsStep = function () {
		var self = this;
		var s = this.state;
		var html = '<div class="aimp-step aimp-step--notions">' + this.recapHtml();

		this.notionTypes().forEach(function (type) {
			var willAdd = type === 'zips' ? fmt(t.zipsWillAdd, s.size.zip_count, s.size.zip_length) : fmt(t.buttonsWillAdd, s.size.button_count);
			html +=
				'<section class="aimp-notion-section">' +
				'<h3>' + esc(type === 'zips' ? t.chooseZip : t.chooseButtons) + '</h3>' +
				'<p class="aimp-help">' + esc(willAdd) + ' ' + esc(t.deselectHint) + '</p>' +
				'<div class="aimp-grid-wrap" data-role="notions-' + type + '"></div>' +
				'<div data-role="notion-panel-' + type + '"></div>' +
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
		bucket.data = null;
		this.renderNotionGrid(type);
		request('notions', { variation: sizeId, type: type, page: page })
			.then(function (data) {
				if (!s.size || s.size.id !== sizeId || s.notions[type].page !== page) {
					return;
				}
				s.notions[type].data = data;
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
			onSelect: function (item) {
				bucket.selected = bucket.selected && bucket.selected.id === item.id ? null : item;
				self.renderNotionGrid(type);
			},
			onPage: function (page) {
				bucket.page = page;
				self.loadNotions(type);
			}
		});
		if (panel) {
			var count = type === 'zips' ? s.size.zip_count : s.size.button_count;
			panel.innerHTML = bucket.selected ? this.materialInfoHtml(bucket.selected, t.pricePerPiece, String(count)) : '';
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
		if (s.size.button_count > 0 && s.notions.buttons.selected) {
			lines.push({ name: s.notions.buttons.selected.name, qty: s.size.button_count, price: s.notions.buttons.selected.price });
		}
		if (s.size.zip_count > 0 && s.notions.zips.selected) {
			lines.push({ name: s.notions.zips.selected.name, qty: s.size.zip_count, price: s.notions.zips.selected.price });
		}
		return lines;
	};

	Configurator.prototype.renderSummaryStep = function () {
		var self = this;
		var s = this.state;
		var lines = this.summaryLines();
		var total = 0;

		var html = '<div class="aimp-step aimp-step--summary"><h3>' + esc(t.summaryTitle) + '</h3>';
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

		request('add_to_cart', {
			nonce: cfg.nonce,
			variation: s.size.id,
			fabric: s.size.fabric_units > 0 && s.fabric ? s.fabric.id : 0,
			button: s.size.button_count > 0 && s.notions.buttons.selected ? s.notions.buttons.selected.id : 0,
			zip: s.size.zip_count > 0 && s.notions.zips.selected ? s.notions.zips.selected.id : 0
		})
			.then(function (data) {
				s.added = true;
				button.hidden = true;
				back.hidden = true;
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
				s.notions.buttons.data = null;
				s.notions.zips.data = null;
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
