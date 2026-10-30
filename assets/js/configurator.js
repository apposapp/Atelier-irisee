/**
 * Atelier Irisee pattern configurator.
 *
 * Steps: pattern & size -> fabric -> lining -> interfacing -> haberdashery (buttons, zips, ribbons, bias tape) -> summary
 * -> add to cart. Lining and interfacing only when the size needs them; both follow the chosen fabric. The summary
 * offers a matching dog bandana (its pattern, extra of the fabric, a thread and a band; Settings → Dog bandana).
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

	// Design studio (Customizer): see AIMP_Design::customizer().
	var design = cfg.design || {};

	// A text written as "NL | FR | EN" (or one text for all) in the chosen language.
	function localText(text) {
		var parts = String(text || '').split('|').map(function (part) {
			return part.trim();
		});
		var codes = (cfg.languages || []).map(function (language) {
			return language.code;
		});
		var i = codes.indexOf(lang);
		return parts.length > 1 && i !== -1 && parts[i] ? parts[i] : parts[0];
	}

	// The line at the top of a step (Design studio).
	function introHtml(step) {
		var text = design['cz_intro_' + step];
		return text ? '<p class="aimp-cz-intro">' + esc(localText(text)) + '</p>' : '';
	}

	var fmt = UI.fmt;
	// "I already have my own …": a sewing box with a heart (stroke icon like the need cards).
	var OWN_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M3.5 10h17v9.5a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1z"/><path d="M3.5 10l2-4.5h13l2 4.5"/><path d="M9 7.8V5.5a3 3 0 0 1 6 0v2.3"/><path d="M12 17.5s-3-1.8-3-3.6a1.6 1.6 0 0 1 3-.8 1.6 1.6 0 0 1 3 .8c0 1.8-3 3.6-3 3.6z"/></svg>';

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
	// The steps come from the server (zip types, button types, bias tape, ribbon); each size tells per type
	// what it needs: size.notions[type] = { qty, need, willAdd } (only types that can be bought).
	var NOTIONS = (cfg.notions || []).map(function (def) {
		var of = function (size) {
			return (size && size.notions && size.notions[def.type]) || null;
		};
		return {
			type: def.type,
			role: def.role,
			title: 'nt_' + def.type,
			label: 'nl_' + def.type,
			unitLabel: def.unitLabel,
			qty: function (size) { var n = of(size); return n ? n.qty : 0; },
			need: function (size) { var n = of(size); return n ? n.need : ''; },
			willAdd: function (size) { var n = of(size); return n ? n.willAdd : ''; }
		};
	});

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

	// One value, or "88–92 cm" when the size has a "to" above it.
	function measure(value, to) {
		if (value === '' || value === null || value === undefined) {
			return '–';
		}
		var top = parseFloat(to);
		return esc(num(value)) + (top > parseFloat(value) ? '–' + esc(num(top)) : '') + ' ' + esc(t.cm);
	}

	function request(action, data) {
		return UI.request(cfg.endpoint, action, data, lang, t.error);
	}

	// The next step's lists are asked for in the background while the customer is still choosing; the step then
	// uses that answer instead of asking again.
	var prefetched = {};

	function prefetch(action, params) {
		var key = action + lang + JSON.stringify(params);
		if (!prefetched[key]) {
			prefetched[key] = request(action, params);
			prefetched[key].catch(function () {
				delete prefetched[key];
			});
		}
	}

	function fetchOnce(action, params) {
		var key = action + lang + JSON.stringify(params);
		var ready = prefetched[key];
		delete prefetched[key];
		return ready || request(action, params);
	}

	// Adding to the cart and saving need a security code; a page from the cache (LiteSpeed) can have an expired one:
	// then a fresh code is fetched and the request is sent once more.
	function secureRequest(action, params) {
		return request(action, params).catch(function (err) {
			if (err.status !== 403) {
				throw err;
			}
			return request('nonces', {}).then(function (fresh) {
				cfg.nonce = fresh.configurator;
				params.nonce = cfg.nonce;
				return request(action, params);
			});
		});
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
		return { category: [], search: '', inStock: false, sort: 'recommended' };
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
		this.setupHowto();
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
				self.showPanel(self.findPanel('size-panel'));
			})
			.catch(function () {});
	};

	// Fit the side panels and mark the favorite hearts after every change (see ui.js).
	Configurator.prototype.watchLayout = function () {
		UI.watchLayout(this.root);
		this.setupMobileBar();
		this.setupSparkle();
	};

	/*
	 * Phones: the button that leads to the next step (Confirm, Confirm fabric, Continue, Add to cart) sparkles once
	 * when it appears in the gold bar at the bottom (configurator.css), like the kit steps bar's button.
	 */
	Configurator.prototype.setupSparkle = function () {
		var self = this;
		if (design.cz_nosparkle || this.sparkleObserver || !window.MutationObserver || !window.matchMedia) {
			return;
		}
		var phone = window.matchMedia('(max-width: 700px)');
		var shown = {};
		var frame = 0;
		var selector = '.aimp-actions [data-action="confirm"], .aimp-actions [data-action="confirm-fabric"], .aimp-actions [data-action="continue"], .aimp-actions [data-action="add"]';
		var check = function () {
			frame = 0;
			var now = {};
			[self.root, self.sheet].forEach(function (scope) {
				if (!scope) {
					return;
				}
				Array.prototype.forEach.call(scope.querySelectorAll(selector), function (button) {
					if (button.hidden || button.disabled || button.offsetParent === null) {
						return;
					}
					var action = button.getAttribute('data-action');
					now[action] = true;
					// Only when it newly appears (not on every redraw of a step).
					if (phone.matches && !shown[action]) {
						button.classList.remove('is-sparked');
						void button.offsetWidth; // Restart the animation.
						button.classList.add('is-sparked');
						window.setTimeout(function () {
							button.classList.remove('is-sparked');
						}, 1950);
					}
				});
			});
			shown = now;
		};
		var schedule = function () {
			if (!frame) {
				frame = window.requestAnimationFrame(check);
			}
		};
		this.sparkleObserver = new MutationObserver(schedule);
		this.sparkleObserver.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'disabled'] });
		schedule();
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
			if (s.step === 'fabric' || s.step === 'lining') {
				return s[s.step] ? s[s.step].name : '';
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
			category: [],
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
		// The lining (optional): the same kind of list as the fabric.
		s.liningPage = 1;
		s.linings = null;
		s.lining = null;
		s.liningImage = 0;
		s.liningFilters = defaultFabricFilters();
		s.liningCategories = null;
		// "I already have my own lining / interfacing / …" (the cards of those steps); own: per haberdashery type.
		s.ownLining = false;
		s.ownInterfacing = false;
		this.resetBandana();
		s.notions = {};
		NOTIONS.forEach(function (n) {
			s.notions[n.type] = { page: 1, data: null, selected: null, image: 0, own: false };
		});
	};

	// The matching dog bandana (summary): the offer follows the size and the fabric.
	Configurator.prototype.resetBandana = function () {
		var s = this.state;
		s.bandana = { on: false, thread: null, band: null };
		s.bandanaOffer = null;
		s.bandanaKey = '';
	};

	// Another fabric: its lining and interfacing choices no longer apply (they follow the fabric).
	Configurator.prototype.fabricChanged = function () {
		var s = this.state;
		this.resetBandana();
		s.liningPage = 1;
		s.linings = null;
		s.lining = null;
		s.liningImage = 0;
		s.liningCategories = null;
		s.ownLining = false;
		s.ownInterfacing = false;
		if (s.notions.interfacing) {
			s.notions.interfacing = { page: 1, data: null, selected: null, image: 0 };
		}
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
		s.linings = null;
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
		// Lining: when the chosen size needs it.
		if (size && size.lining_units > 0) {
			steps.push({ key: 'lining', label: t.stepLining, optional: true });
		}
		// Interfacing: its own step, from the fabric's normal / volume interfacing (or the ones that fit the pattern).
		if (this.needsInterfacing()) {
			steps.push({ key: 'interfacing', label: t.stepInterfacing, optional: true });
		}
		if (size ? this.notionTypes().length : NOTIONS.length) {
			steps.push({ key: 'notions', label: t.stepNotions, optional: true });
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

	/* ---------------------------------------------------------------
	 * "How it works": the 4-step bar in a pop-up (a sheet on phones), on the first visit and via the ? button.
	 * ------------------------------------------------------------- */
	var HOWTO_SEEN = 'aimp_howto_seen';

	Configurator.prototype.setupHowto = function () {
		var self = this;
		var tpl = document.querySelector('template[data-aimp-howto]');
		if (!tpl || !tpl.content || !tpl.content.firstElementChild) {
			return;
		}
		this.howtoTpl = tpl;
		var open = document.createElement('button');
		open.type = 'button';
		open.className = 'aimp-howto-open';
		open.innerHTML = '<span class="aimp-howto-q" aria-hidden="true">?</span> ' + esc(t.howItWorks);
		open.addEventListener('click', function () {
			self.openHowto();
		});
		if (!design.cz_nohowto) {
			this.root.querySelector('.aimp-topbar').insertBefore(open, this.languagesEl);
		}
		var seen = false;
		try {
			seen = !!window.localStorage.getItem(HOWTO_SEEN);
		} catch (e) {}
		// Not when coming from a product page or a saved kit: then the visitor already knows what to do.
		if (!seen && cfg.howtoAuto !== false && !/[?&]aimp_(pattern|kit)=/.test(window.location.search)) {
			this.openHowto();
		}
	};

	Configurator.prototype.openHowto = function () {
		var self = this;
		var box = document.createElement('div');
		box.className = 'aimp-howto';
		box.appendChild(this.howtoTpl.content.firstElementChild.cloneNode(true));
		var close = function () {
			try {
				window.localStorage.setItem(HOWTO_SEEN, '1');
			} catch (e) {}
			if (self.howtoDialog) {
				self.howtoDialog.parentNode.removeChild(self.howtoDialog);
				self.howtoDialog = null;
				document.documentElement.classList.remove('aimp-sheet-open');
			} else {
				self.closeSheet();
			}
		};
		// The bar's button ("Start") closes the pop-up: the customizer is right behind it.
		box.addEventListener('click', function (e) {
			if (e.target.closest('.aimp-kit-steps-button')) {
				e.preventDefault();
				close();
			}
		});
		if (window.matchMedia && window.matchMedia('(max-width: 700px)').matches) {
			this.openSheet(box);
			// The sheet's × and backdrop close it too; remember it then as well.
			this.sheet.addEventListener('click', function remember(e) {
				if (e.target.closest('[data-sheet-close]')) {
					try {
						window.localStorage.setItem(HOWTO_SEEN, '1');
					} catch (err) {}
					self.sheet.removeEventListener('click', remember);
				}
			});
		} else {
			var dialog = document.createElement('div');
			dialog.className = 'aimp-howto-dialog';
			dialog.innerHTML = '<div class="aimp-howto-backdrop"></div>' +
				'<div class="aimp-howto-card aimp-configurator" role="dialog" aria-modal="true" aria-label="' + esc(t.howItWorks) + '">' +
				'<button type="button" class="aimp-sheet-close" aria-label="' + esc(t.close || 'Close') + '">×</button></div>';
			dialog.querySelector('.aimp-howto-card').appendChild(box);
			dialog.addEventListener('click', function (e) {
				if (e.target.closest('.aimp-sheet-close') || e.target.classList.contains('aimp-howto-backdrop')) {
					close();
				}
			});
			document.addEventListener('keydown', function onKey(e) {
				if (e.key === 'Escape' && self.howtoDialog) {
					close();
					document.removeEventListener('keydown', onKey);
				}
			});
			document.body.appendChild(dialog);
			document.documentElement.classList.add('aimp-sheet-open');
			this.howtoDialog = dialog;
		}
		// The needle measures the cards: start it once the pop-up is laid out.
		window.setTimeout(function () {
			if (window.aimpKitSteps) {
				window.aimpKitSteps();
			}
		}, 350);
	};

	/* ---------------------------------------------------------------
	 * Phones and tablets: the details of a chosen fabric or haberdashery product open as a sheet from the
	 * bottom (the shop pages' sheet, shop.css), with its button always in reach. Wider screens keep the panel.
	 * ------------------------------------------------------------- */

	// A details panel, in the step or in the sheet.
	Configurator.prototype.findPanel = function (role) {
		var selector = '[data-role="' + role + '"]';
		return this.body.querySelector(selector) || (this.sheet ? this.sheet.querySelector(selector) : null);
	};

	Configurator.prototype.isSheetMode = function () {
		return !!(window.matchMedia && window.matchMedia('(max-width: 999px)').matches);
	};

	// After choosing: the sheet on phones and tablets, otherwise scroll to the panel when needed.
	Configurator.prototype.showPanel = function (panel) {
		if (!panel) {
			return;
		}
		if (this.isSheetMode()) {
			this.openSheet(panel);
		} else {
			scrollIntoViewIfNeeded(panel);
		}
	};

	Configurator.prototype.openSheet = function (panel) {
		var self = this;
		if (!this.sheet) {
			var sheet = document.createElement('div');
			sheet.className = 'aimp-sheet aimp-config-sheet';
			sheet.hidden = true;
			sheet.innerHTML =
				'<div class="aimp-sheet-backdrop" data-sheet-close></div>' +
				'<div class="aimp-sheet-panel aimp-configurator" role="dialog" aria-modal="true">' +
				'<div class="aimp-sheet-head"><span class="aimp-sheet-handle" aria-hidden="true"></span>' +
				'<button type="button" class="aimp-sheet-close" data-sheet-close aria-label="' + esc(t.close || 'Close') + '">×</button></div>' +
				'<div class="aimp-sheet-body"></div></div>';
			document.body.appendChild(sheet);
			this.sheet = sheet;
			sheet.addEventListener('click', function (e) {
				if (e.target.closest('[data-sheet-close]')) {
					self.closeSheet();
				}
			});
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && self.sheet && !self.sheet.hidden) {
					self.closeSheet();
				}
			});
			// Swipe down on the top of the sheet to close it.
			var head = sheet.querySelector('.aimp-sheet-head');
			var startY = null;
			head.addEventListener('touchstart', function (e) {
				startY = e.touches[0].clientY;
			}, { passive: true });
			head.addEventListener('touchend', function (e) {
				if (startY !== null && e.changedTouches[0].clientY - startY > 60) {
					self.closeSheet();
				}
				startY = null;
			}, { passive: true });
		}
		if (this.sheetPanel && this.sheetPanel !== panel) {
			this.restoreSheetPanel();
		}
		if (!this.sheetPanel) {
			this.sheetHome = { parent: panel.parentNode, next: panel.nextSibling };
			this.sheetPanel = panel;
			this.sheet.querySelector('.aimp-sheet-body').appendChild(panel);
		}
		this.sheet.hidden = false;
		document.documentElement.classList.add('aimp-sheet-open');
		window.cancelAnimationFrame(this.sheetFrame);
		this.sheetFrame = window.requestAnimationFrame(function () {
			self.sheet.classList.add('is-open');
		});
	};

	// The panel goes back to its place in the step (if that step is still on the page).
	Configurator.prototype.restoreSheetPanel = function () {
		var home = this.sheetHome;
		if (this.sheetPanel && home && home.parent && document.body.contains(home.parent)) {
			home.parent.insertBefore(this.sheetPanel, home.next && home.next.parentNode === home.parent ? home.next : null);
		} else if (this.sheetPanel && this.sheetPanel.parentNode) {
			this.sheetPanel.parentNode.removeChild(this.sheetPanel);
		}
		this.sheetPanel = null;
		this.sheetHome = null;
	};

	Configurator.prototype.closeSheet = function (immediately) {
		var self = this;
		if (!this.sheet || this.sheet.hidden) {
			return;
		}
		// A slide-in that has not started yet must not open the sheet after this.
		window.cancelAnimationFrame(this.sheetFrame);
		this.sheet.classList.remove('is-open');
		document.documentElement.classList.remove('aimp-sheet-open');
		var done = function () {
			self.sheet.hidden = true;
			self.restoreSheetPanel();
		};
		if (immediately) {
			done();
		} else {
			window.setTimeout(done, 300);
		}
	};

	// Every new step starts at the very top of the page.
	Configurator.prototype.goTo = function (key) {
		this.closeSheet(true);
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
			case 'lining':
				this.matKind = this.state.step;
				this.renderFabricStep();
				break;
			case 'interfacing':
				this.renderInterfacingStep();
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
		var count = this.steps().length;
		// Design studio: a gold progress bar under the steps, no numbers, or no step indicator at all.
		this.stepsEl.classList.toggle('aimp-steps--bar', design.cz_progress === 'bar');
		this.stepsEl.classList.toggle('aimp-steps--none', design.cz_progress === 'none');
		this.stepsEl.classList.toggle('aimp-steps--nonum', !!design.cz_nonum);
		this.stepsEl.style.setProperty('--cz-progress', (count > 1 ? Math.round(current / (count - 1) * 100) : 100) + '%');
		this.stepsEl.innerHTML = this.steps()
			.map(function (step, i) {
				var cls = i === current ? 'is-current' : i < current ? 'is-done' : '';
				var inner = '<span class="aimp-step-num">' + (i + 1) + '</span> ' + esc(step.label) +
					(step.optional ? ' <small class="aimp-step-optional">' + esc(t.optional) + '</small>' : '');
				// After adding to the cart, start over via "Configure another" instead of editing the added set.
				if (i < current && !self.state.added) {
					inner = '<button type="button" data-step="' + esc(step.key) + '">' + inner + '</button>';
				}
				return '<li class="' + cls + '"' + (i === current ? ' aria-current="step"' : '') + '>' + inner + '</li>';
			})
			.join('') +
			// Phones: "‹ Back" sits with the steps (configurator.css), instead of in the bar at the bottom.
			(current > 0 && !self.state.added ? '<li class="aimp-steps-back"><button type="button" data-action="steps-back">' +
				'<svg class="aimp-steps-back-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12H4M10 6l-6 6 6 6"/></svg>' +
				esc(t.back) + '</button></li>' : '');
		var back = this.stepsEl.querySelector('[data-action="steps-back"]');
		if (back) {
			back.addEventListener('click', function () {
				self.prev();
			});
		}
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
		html += '<div><dt>' + esc(unitLabel) + '</dt><dd>' + item.price_html + (item.price_suffix ? ' <small>' + esc(item.price_suffix) + '</small>' : '') + '</dd></div>';
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
		var html = '<div class="aimp-step aimp-step--pattern">' + introHtml('pattern');

		if (cfg.categories && cfg.categories.length) {
			html += '<div class="aimp-filters" role="group">';
			[{ id: 0, name: t.all }].concat(cfg.categories).forEach(function (cat) {
				// Several categories can be on; "All" is on when none is.
				var active = cat.id ? s.category.indexOf(cat.id) !== -1 : !s.category.length;
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
				var id = parseInt(btn.getAttribute('data-cat'), 10) || 0;
				var on = s.category.indexOf(id) !== -1;
				s.category = !id ? [] : on ? s.category.filter(function (other) {
					return other !== id;
				}) : s.category.concat([id]);
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
		var wanted = lang + ':' + s.category.join(',') + ':' + s.patternPage;
		s.patterns = null;
		this.renderPatternGrid();
		request('patterns', { category: s.category.join(','), page: s.patternPage })
			.then(function (data) {
				// Ignore stale responses after a quick filter or language change.
				if (wanted !== lang + ':' + s.category.join(',') + ':' + s.patternPage) {
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
			// Phones and tablets: tapping the chosen pattern again opens its sheet again.
			if (this.isSheetMode()) {
				this.showPanel(this.findPanel('size-panel'));
			}
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
		// Phones and tablets: the pattern, its sizes and the size chart in a sheet (like the fabrics).
		this.showPanel(this.findPanel('size-panel'));

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
				var panel = self.findPanel('size-panel');
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
		if (size.lining_units > 0) {
			rows.push(card('fabric', t.liningNeeded, size.lining_text));
		}
		NOTIONS.forEach(function (n) {
			if (n.qty(size) > 0) {
				// A named button group ("Buttons – Cuffs") by its own name.
				var own = size.notions && size.notions[n.type] ? size.notions[n.type].name : '';
				rows.push(card(n.type, own || t[n.label], n.need(size)));
			}
		});
		// Also needed, not in the kit (elastic, cord, …): only the filled-in ones.
		(size.extras || []).forEach(function (extra) {
			rows.push(card('extra', extra.label + ' (' + t.notInKit + ')', extra.text));
		});
		if (!rows.length) {
			rows.push(card('', t.none, ''));
		}
		return '<ul class="aimp-needs">' + rows.join('') + '</ul>';
	};

	Configurator.prototype.renderSizePanel = function () {
		var self = this;
		var s = this.state;
		var panel = this.findPanel('size-panel');
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
		html += this.galleryHtml(pattern.gallery, s.patternImage, pattern.product_id || pattern.id);
		html += detailsTitle(pattern.name);
		if (pattern.short_description) {
			// Phones: after the sizes, folded to a few lines with "Read more" (configurator.css).
			html += '<div class="aimp-description aimp-desc-fold">' + pattern.short_description + '</div>' +
				'<button type="button" class="aimp-fold-toggle" data-action="fold" aria-expanded="false">' + esc(t.readMore) + '</button>';
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
			// Phones and tablets: the measurements are in the size chart below (only the chosen size).
			var measures = this.isSheetMode() ? [] : usedMeasures([size]);
			if (measures.length) {
				html += '<dl class="aimp-measurements">';
				measures.forEach(function (m) {
					html += '<div><dt>' + esc(t[m[1]]) + '</dt><dd>' + measure(size[m[0]], size[m[0] + '_to']) + '</dd></div>';
				});
				html += '</dl>';
			}
			html += '<p class="aimp-needs-title">' + esc(t.needs) + ':</p>' + this.needsHtml(size);
			html += '<p class="aimp-size-price">' + size.price_html + '</p>';
		}
		// Phones and tablets: the size chart in the sheet too, under the sizes, once a size is chosen.
		if (this.isSheetMode() && size) {
			html += '<section class="aimp-size-chart-box" data-role="size-chart-in" hidden></section>';
		}

		// In the sheet the button bar only appears once a size is chosen.
		if (size || !this.isSheetMode()) {
			html +=
				'<div class="aimp-actions">' +
				'<button type="button" class="aimp-button aimp-button--block" data-action="confirm"' + (size ? '' : ' disabled') + '>' + esc(t.confirmPattern) + '</button>' +
				'</div>';
		}
		html += '</div>';
		panel.innerHTML = html;
		this.renderSizeChart();

		this.bindGallery(panel, pattern.gallery, function (i) {
			s.patternImage = i;
		});
		var fold = panel.querySelector('[data-action="fold"]');
		if (fold) {
			fold.addEventListener('click', function () {
				var open = panel.querySelector('.aimp-desc-fold').classList.toggle('is-open');
				fold.textContent = open ? t.readLess : t.readMore;
				fold.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
		}

		panel.querySelectorAll('[data-size]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var id = parseInt(btn.getAttribute('data-size'), 10);
				self.selectSize(id, true);
				var again = panel.querySelector('[data-size="' + id + '"]');
				if (again) {
					try {
						again.focus({ preventScroll: true });
					} catch (e) {
						again.focus();
					}
				}
			});
		});
		var confirm = panel.querySelector('[data-action="confirm"]');
		if (confirm) {
			confirm.addEventListener('click', function () {
				if (s.size) {
					self.closeSheet(true);
					self.next();
				}
			});
		}
	};

	// Used by the size buttons in the details panel (toSizes: the sizes then slide to the top of the screen, so
	// the measurements and the size chart under them are in view) and the rows of the size chart.
	Configurator.prototype.selectSize = function (id, toSizes) {
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
		// The page stays where it is (phones and tablets jumped up): keep the panel's height while it is rebuilt
		// and its picture loads, and put the scroll position back.
		var y = window.pageYOffset;
		var panel = this.findPanel('size-panel');
		var sheetBody = this.sheet && !this.sheet.hidden ? this.sheet.querySelector('.aimp-sheet-panel') : null;
		var sheetY = sheetBody ? sheetBody.scrollTop : 0;
		if (panel) {
			panel.style.minHeight = panel.offsetHeight + 'px';
		}
		this.resetMaterials();
		this.renderSizePanel();
		this.renderSteps();
		window.scrollTo(window.pageXOffset, y);
		if (chosen.fabric_units > 0) {
			prefetch('fabrics', this.fabricParams('fabric'));
		}
		if (sheetBody) {
			sheetBody.scrollTop = sheetY;
		}
		if (panel) {
			var release = function () {
				panel.style.minHeight = '';
			};
			var img = panel.querySelector('img');
			if (img && !img.complete) {
				img.addEventListener('load', release);
				img.addEventListener('error', release);
				window.setTimeout(release, 1500);
			} else {
				window.requestAnimationFrame(release);
			}
		}
		if (toSizes) {
			this.scrollToSizes(sheetBody);
		}
	};

	// The size buttons at the top of the screen (under the header), or of the sheet on phones and tablets.
	Configurator.prototype.scrollToSizes = function (sheetBody) {
		var panel = this.findPanel('size-panel');
		var sizes = panel ? panel.querySelector('.aimp-sizes') : null;
		if (!sizes) {
			return;
		}
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var behavior = reduce ? 'auto' : 'smooth';
		var gap = 12;
		if (sheetBody) {
			var top = sizes.getBoundingClientRect().top - sheetBody.getBoundingClientRect().top + sheetBody.scrollTop - gap;
			sheetBody.scrollTo({ top: Math.max(0, top), behavior: behavior });
			return;
		}
		// Computers with the details beside the patterns: that panel already follows the page (sticky).
		var side = sizes.closest('.aimp-split-side');
		if (side && window.getComputedStyle(side).position === 'sticky') {
			return;
		}
		var header = document.querySelector('.aimp-header');
		var offset = header ? Math.max(0, header.getBoundingClientRect().bottom) : 0;
		window.scrollTo({ top: Math.max(0, sizes.getBoundingClientRect().top + window.pageYOffset - offset - gap), behavior: behavior });
	};

	// Size chart in its own box under the patterns and details, so it stays in place when a size is chosen.
	Configurator.prototype.renderSizeChart = function () {
		var self = this;
		var s = this.state;
		// Phones and tablets: in the sheet, under the sizes (the box under the patterns stays empty).
		var page = this.body.querySelector('[data-role="size-chart"]');
		var panel = this.findPanel('size-panel');
		var inner = panel ? panel.querySelector('[data-role="size-chart-in"]') : null;
		var box = inner || page;
		if (inner && page) {
			page.hidden = true;
			page.innerHTML = '';
		}
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
		// In the sheet only the chosen size, right under the sizes (less to scroll on a phone).
		var rows = inner && s.size ? sizes.filter(function (sz) {
			return sz.id === s.size.id;
		}) : sizes;
		rows.forEach(function (sz) {
			var selected = s.size && s.size.id === sz.id;
			html +=
				'<tr data-size-row="' + esc(sz.id) + '" class="' + (selected ? 'is-selected' : '') + (sz.available ? ' is-clickable' : ' is-unavailable') + '">' +
				'<th scope="row">' + esc(sz.label) + '</th>';
			measures.forEach(function (m) {
				// The column name for the phone cards (the sheet is outside the configurator's table labelling).
				html += '<td data-label="' + esc(t[m[1]]) + '">' + measure(sz[m[0]], sz[m[0] + '_to']) + '</td>';
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
		if (s.step !== 'lining' && s.step !== 'fabric' && s.lining && s.size.lining_units > 0) {
			items.push({ image: s.lining.image, name: s.lining.name, detail: t.stepLining });
		}
		if (s.step === 'notions' && s.notions.interfacing && s.notions.interfacing.selected) {
			items.push({ image: s.notions.interfacing.selected.image, name: s.notions.interfacing.selected.name, detail: t.stepInterfacing });
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
		var k = this.matKind || 'fabric';
		var self = this;
		var s = this.state;
		var f = s[k + 'Filters'];
		// The haberdashery lists of the next step in the background.
		this.notionTypes().forEach(function (type) {
			if (!s.notions[type].data) {
				prefetch('notions', { variation: s.size.id, type: type, page: s.notions[type].page });
			}
		});
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
			'<div class="aimp-step aimp-step--fabric aimp-step--' + k + ' aimp-fabric-filters">' +
			introHtml(k) +
			this.recapHtml() +
			'<h3>' + esc(k === 'lining' ? t.chooseLining : t.chooseFabric) + '</h3>' +
			(k === 'lining' ? '<div class="aimp-notice aimp-lining-note" role="note"><p>' + esc(t.liningNote) + '</p></div><div data-own="lining"></div>' : '') +
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
		// "I already have my own lining": no lining in the kit, on to the next step.
		if (k === 'lining') {
			this.ownCard('lining', t.ownLining, function () {
				return s.ownLining;
			}, function (on) {
				s.ownLining = on;
				if (on) {
					s.lining = null;
					self.next();
				}
			});
		}

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
			s[k + 'Page'] = 1;
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
		if (!s[k + 's']) {
			this.loadFabrics();
		}
	};

	// "Filters" with the number of active filters, like on the shop pages.
	Configurator.prototype.renderFilterToggle = function () {
		var k = this.matKind || 'fabric';
		var toggle = this.body.querySelector('[data-action="toggle-filters"]');
		if (!toggle) {
			return;
		}
		var f = this.state[k + 'Filters'];
		var count = f.category.length + (f.search ? 1 : 0) + (f.inStock ? 1 : 0);
		toggle.innerHTML =
			'<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>' +
			'<span>' + esc(t.filters) + '</span>' +
			(count ? '<span class="aimp-shop-count">' + count + '</span>' : '');
	};

	// Fabric categories in the filter panel: tick boxes, several at once.
	Configurator.prototype.renderFabricCategories = function () {
		var k = this.matKind || 'fabric';
		var self = this;
		var s = this.state;
		var container = this.body.querySelector('[data-role="fabric-cats"]');
		var group = this.body.querySelector('[data-role="fabric-cats-group"]');
		if (!container) {
			return;
		}
		var cats = s[k + 'Categories'] || [];
		if (cats.length < 2) {
			container.innerHTML = '';
			group.hidden = true;
			return;
		}
		group.hidden = false;
		// Several can be ticked; none ticked = all allowed fabrics.
		container.innerHTML = cats
			.map(function (cat) {
				var active = s[k + 'Filters'].category.indexOf(cat.id) !== -1;
				return (
					'<li><label class="aimp-filter-option">' +
					'<input type="checkbox" value="' + esc(cat.id) + '" data-fabric-cat' + (active ? ' checked' : '') + '> ' +
					'<span>' + esc(cat.name) + '</span></label></li>'
				);
			})
			.join('');
		container.querySelectorAll('[data-fabric-cat]').forEach(function (input) {
			input.addEventListener('change', function () {
				var id = parseInt(input.value, 10) || 0;
				s[k + 'Filters'].category = s[k + 'Filters'].category.filter(function (other) {
					return other !== id;
				});
				if (input.checked && id) {
					s[k + 'Filters'].category.push(id);
				}
				s[k + 'Page'] = 1;
				self.renderFilterToggle();
				self.loadFabrics();
			});
		});
	};

	Configurator.prototype.fabricParams = function (kind) {
		var k = kind || this.matKind || 'fabric';
		var s = this.state;
		var f = s[k + 'Filters'];
		return {
			variation: s.size.id,
			page: s[k + 'Page'],
			category: f.category.join(','),
			search: f.search,
			in_stock: f.inStock ? 1 : 0,
			sort: f.sort,
			// The lining step: only the linings that fit the chosen fabric.
			fabric: k === 'lining' && s.fabric ? s.fabric.id : 0
		};
	};

	Configurator.prototype.loadFabrics = function () {
		var k = this.matKind || 'fabric';
		var self = this;
		var s = this.state;
		var params = this.fabricParams();
		var key = lang + JSON.stringify(params);
		this.fabricRequestKey = key;
		s[k + 's'] = null;
		this.renderFabricGrid();
		fetchOnce(k + 's', params)
			.then(function (data) {
				if (self.fabricRequestKey !== key) {
					return;
				}
				s[k + 's'] = data;
				s[k + 'Page'] = data.page;
				s[k] = rebind(s[k], data.items);
				var hadCategories = s[k + 'Categories'] !== null;
				s[k + 'Categories'] = data.categories || [];
				if (s.step === k) {
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
		var k = this.matKind || 'fabric';
		var self = this;
		var s = this.state;
		var container = this.body.querySelector('[data-role="fabrics"]');
		if (!container) {
			return;
		}
		var filtered = s[k + 'Filters'].category.length || s[k + 'Filters'].search || s[k + 'Filters'].inStock;
		this.renderGrid(container, s[k + 's'], {
			selectedId: s[k] ? s[k].id : null,
			emptyText: filtered ? t.noFabricsMatch : (k === 'lining' ? t.noLinings : t.noFabrics),
			unavailableText: t.notEnoughStock,
			compact: true,
			priceSuffix: t.per10cm,
			onSelect: function (item) {
				if (!s[k] || s[k].id !== item.id) {
					if (k === 'fabric' && s.fabric) {
						self.fabricChanged();
					}
					s[k] = item;
					s[k + 'Image'] = 0;
				}
				if (k === 'lining' && s.ownLining) {
					s.ownLining = false;
					self.redrawOwn('lining');
				}
				self.renderFabricGrid();
				self.renderFabricPanel();
				self.showPanel(self.findPanel('fabric-panel'));
			},
			onPage: function (page) {
				s[k + 'Page'] = page;
				self.loadFabrics();
			}
		});
	};

	Configurator.prototype.renderFabricPanel = function () {
		var k = this.matKind || 'fabric';
		var self = this;
		var s = this.state;
		var panel = this.findPanel('fabric-panel');
		if (!panel) {
			return;
		}
		if (!s[k]) {
			panel.innerHTML = '<div class="aimp-side-placeholder">' + esc(k === 'lining' ? t.selectLiningHint : t.selectFabricHint) + '</div>';
			return;
		}
		var html = this.materialDetailsHtml(s[k], s[k + 'Image'], t.pricePerUnit, s.size[k + '_text']);
		html +=
			'<div class="aimp-actions">' +
			'<button type="button" class="aimp-button aimp-button--block" data-action="confirm-fabric">' + esc(k === 'lining' ? t.confirmLining : t.confirmFabric) + '</button>' +
			'</div>';
		panel.innerHTML = html;

		this.bindGallery(panel, s[k].gallery, function (i) {
			s[k + 'Image'] = i;
		});
		panel.querySelector('[data-action="confirm-fabric"]').addEventListener('click', function () {
			self.closeSheet(true);
			self.next();
		});
	};

	/* ---------------------------------------------------------------
	 * Step 3: haberdashery (buttons, zips, ribbons, bias tape)
	 * ------------------------------------------------------------- */

	/* "I already have my own …": a card of its own above the grid, filled when "on". */

	// slot: the [data-own] place; isOn(): its state; toggle(on): what a click does (then the card is drawn again).
	Configurator.prototype.ownCard = function (slot, label, isOn, toggle) {
		var self = this;
		this.ownDraw = this.ownDraw || {};
		this.ownDraw[slot] = function () {
			var box = self.body.querySelector('[data-own="' + slot + '"]');
			if (!box) {
				return;
			}
			var on = !!isOn();
			box.className = 'aimp-own-choice';
			box.innerHTML =
				'<button type="button" class="aimp-card aimp-card--own' + (on ? ' is-selected' : '') + '" aria-pressed="' + (on ? 'true' : 'false') + '">' +
				'<span class="aimp-card-own-icon" aria-hidden="true">' + OWN_ICON + '</span>' +
				'<span class="aimp-card-name">' + esc(label) + '<small class="aimp-card-sub">' + esc(t.ownSub) + '</small></span>' +
				(on ? '<span class="aimp-badge aimp-badge--selected">' + esc(t.selected) + '</span>' : '') +
				'</button>';
			box.querySelector('button').addEventListener('click', function () {
				toggle(!isOn());
				self.redrawOwn(slot);
			});
		};
		this.ownDraw[slot]();
	};

	Configurator.prototype.redrawOwn = function (slot) {
		if (this.ownDraw && this.ownDraw[slot]) {
			this.ownDraw[slot]();
		}
	};

	// Interfacing keeps its own flag (saved kits, the summary); the other types use their bucket.
	Configurator.prototype.setOwn = function (type, on) {
		if (type === 'interfacing') {
			this.state.ownInterfacing = on;
		} else {
			this.state.notions[type].own = on;
		}
		this.redrawOwn(type);
	};

	Configurator.prototype.ownNotionCard = function (type, label) {
		var self = this;
		var s = this.state;
		this.ownCard(type, label, function () {
			return type === 'interfacing' ? s.ownInterfacing : s.notions[type].own;
		}, function (on) {
			self.setOwn(type, on);
			if (on) {
				s.notions[type].selected = null;
				s.notions[type].preview = null;
				self.closeSheet();
				self.renderNotionGrid(type);
			}
		});
	};

	// What a size calls a haberdashery type ("Buttons – Cuffs"), or its standard name.
	Configurator.prototype.notionName = function (type) {
		var size = this.state.size;
		var own = size && size.notions && size.notions[type] ? size.notions[type].name : '';
		return own || t[notionInfo(type).label] || '';
	};

	// The haberdashery types the chosen size needs.
	Configurator.prototype.notionTypes = function () {
		var size = this.state.size;
		if (!size) {
			return [];
		}
		return NOTIONS.filter(function (n) {
			// Interfacing has its own step.
			return n.type !== 'interfacing' && n.qty(size) > 0;
		}).map(function (n) {
			return n.type;
		});
	};

	Configurator.prototype.needsInterfacing = function () {
		var info = notionInfo('interfacing');
		return !!(info && this.state.size && info.qty(this.state.size) > 0);
	};

	/* ---------------------------------------------------------------
	 * Interfacing: the fabric's normal and/or volume interfacing, or (a fabric without) the ones that fit the
	 * pattern; or "I have my own interfacing".
	 * ------------------------------------------------------------- */

	Configurator.prototype.renderInterfacingStep = function () {
		var self = this;
		var s = this.state;
		var info = notionInfo('interfacing');
		this.body.innerHTML =
			'<div class="aimp-step aimp-step--notions aimp-step--interfacing">' + introHtml('interfacing') + this.recapHtml() +
			'<section class="aimp-notion-section">' +
			'<h3>' + esc(t.chooseInterfacing) + ' <span class="aimp-optional">' + esc(t.optional) + '</span></h3>' +
			'<p class="aimp-help">' + esc(t.interfacingHint) + ' ' + esc(info.willAdd(s.size)) + '</p>' +
			'<div data-own="interfacing"></div>' +
			'<div class="aimp-split">' +
			'<div class="aimp-split-main" data-role="notions-interfacing"></div>' +
			'<aside class="aimp-split-side" data-role="notion-panel-interfacing"></aside>' +
			'</div>' +
			'</section>' +
			'<div data-role="messages"></div>' +
			'<div class="aimp-actions">' +
			'<button type="button" class="aimp-button aimp-button--ghost" data-action="back">' + esc(t.back) + '</button>' +
			'<button type="button" class="aimp-button" data-action="continue">' + esc(t.continue) + '</button>' +
			'</div></div>';

		this.body.querySelector('[data-action="back"]').addEventListener('click', function () {
			self.prev();
		});
		this.ownNotionCard('interfacing', t.ownInterfacing);
		this.body.querySelector('[data-action="continue"]').addEventListener('click', function () {
			var data = s.notions.interfacing.data;
			var nothing = data && !(data.items || []).some(function (item) {
				return item.available;
			});
			if (s.notions.interfacing.selected || s.ownInterfacing || nothing) {
				self.next();
				return;
			}
			self.body.querySelector('[data-role="messages"]').innerHTML = '<div class="aimp-notice" role="alert"><p>' + esc(t.chooseInterfacingFirst) + '</p></div>';
		});
		this.renderNotionGrid('interfacing');
		if (!s.notions.interfacing.data) {
			this.loadNotions('interfacing');
		}
	};

	Configurator.prototype.renderNotionsStep = function () {
		var self = this;
		var s = this.state;
		var html = '<div class="aimp-step aimp-step--notions">' + introHtml('notions') + this.recapHtml();

		this.notionTypes().forEach(function (type) {
			var info = notionInfo(type);
			// A button group the pattern named ("Cuffs"): its name after the title.
			var group = s.size.notions && s.size.notions[type] ? s.size.notions[type].label : '';
			html +=
				'<section class="aimp-notion-section">' +
				'<h3>' + esc(t[info.title]) + (group ? ' <span class="aimp-notion-group">· ' + esc(group) + '</span>' : '') + ' <span class="aimp-optional">' + esc(t.optional) + '</span></h3>' +
				'<p class="aimp-help">' + esc(info.willAdd(s.size)) + ' ' + esc(t.deselectHint) + '</p>' +
				'<div data-own="' + esc(type) + '"></div>' +
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
			self.ownNotionCard(type, fmt(t.ownNotion, self.notionName(type).toLowerCase()));
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
		var pending = type === 'interfacing' ?
			fetchOnce('interfacing', { variation: sizeId, fabric: s.fabric ? s.fabric.id : 0, page: page }) :
			fetchOnce('notions', { variation: sizeId, type: type, page: page });
		pending
			.then(function (data) {
				if (!s.size || s.size.id !== sizeId || s.notions[type].page !== page || lang !== reqLang) {
					return;
				}
				s.notions[type].data = data;
				s.notions[type].selected = rebind(s.notions[type].selected, data.items);
				if (s.step === 'notions' || s.step === 'interfacing') {
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
		var panel = this.findPanel('notion-panel-' + type);
		if (!container) {
			return;
		}
		this.redrawOwn(type);
		this.renderGrid(container, bucket.data, {
			selectedId: bucket.selected ? bucket.selected.id : null,
			emptyText: t.noNotions,
			unavailableText: t.notEnoughStock,
			compact: true,
			onSelect: function (item) {
				bucket.image = 0;
				// Phones and tablets: the card only shows the product in the sheet; "Choose this" there selects it.
				if (self.isSheetMode()) {
					bucket.preview = item;
					self.renderNotionGrid(type);
					self.showPanel(self.findPanel('notion-panel-' + type));
					return;
				}
				bucket.selected = bucket.selected && bucket.selected.id === item.id ? null : item;
				if (bucket.selected) {
					self.setOwn(type, false);
				}
				self.renderNotionGrid(type);
				if (bucket.selected) {
					self.showPanel(panel);
				} else {
					self.closeSheet();
				}
			},
			onPage: function (page) {
				bucket.page = page;
				self.loadNotions(type);
			}
		});
		if (panel) {
			// In the sheet: the product that was tapped (bucket.preview); beside the grid: the chosen one.
			var sheet = this.isSheetMode();
			var shown = (sheet && bucket.preview) || bucket.selected;
			if (!shown) {
				panel.innerHTML = '<div class="aimp-side-placeholder">' + esc(t.selectItemHint) + '</div>';
				return;
			}
			var chosen = !!(bucket.selected && bucket.selected.id === shown.id);
			var info = notionInfo(type);
			// Buttons sold per set: also how many sets are added ("2 × set of 6").
			var needText = info.need(s.size) + (shown.qty_text ? ' · ' + shown.qty_text : '');
			// Sets: they can only be bought in whole sets, which can be more than needed.
			panel.innerHTML = (shown.set_note ? '<div class="aimp-notice aimp-set-note" role="status">' + esc(shown.set_note) + '</div>' : '') +
				// The card says what its price is for ("per roll of 3 m", "per 10 cm", …), next to the price.
				this.materialDetailsHtml(shown, bucket.image, shown.price_suffix ? t.price : t[info.unitLabel], needText) +
				(sheet ?
					'<div class="aimp-actions"><button type="button" class="aimp-button aimp-button--block' + (chosen ? ' aimp-button--ghost' : '') + '" data-action="choose-notion">' +
					esc(chosen ? t.removeFromKit : t.chooseThis) + '</button></div>' : '');
			this.bindGallery(panel, shown.gallery, function (i) {
				bucket.image = i;
			});
			var choose = panel.querySelector('[data-action="choose-notion"]');
			if (choose) {
				choose.addEventListener('click', function () {
					bucket.selected = chosen ? null : shown;
					bucket.preview = null;
					if (bucket.selected) {
						self.setOwn(type, false);
					}
					self.closeSheet();
					self.renderNotionGrid(type);
				});
			}
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
		if (s.size.lining_units > 0 && s.lining) {
			lines.push({ name: s.lining.name, detail: t.stepLining + ' · ' + s.size.lining_text, qty: s.size.lining_units, price: s.lining.price });
		}
		NOTIONS.forEach(function (n) {
			var selected = s.notions[n.type].selected;
			if (n.qty(s.size) > 0 && selected) {
				var detail = selected.qty_text ? selected.qty_text + (selected.set_note ? ' · ' + selected.set_note : '') : (n.unitLabel === 'pricePerUnit' ? n.need(s.size) : '');
				// The fabric's own interfacing: normal or volume.
				if (selected.slot) {
					detail = t[selected.slot === 'volume' ? 'volumeInterfacing' : 'normalInterfacing'] + (detail ? ' · ' + detail : '');
				}
				lines.push({
					name: selected.name,
					detail: detail,
					// Sets: the card's own quantity (whole sets).
					qty: selected.qty || n.qty(s.size),
					price: selected.price
				});
			}
		});
		return lines.concat(this.bandanaLines());
	};

	// The bandana's lines when it is added: the pattern, the extra fabric, and the chosen thread and band.
	Configurator.prototype.bandanaLines = function () {
		var s = this.state;
		var offer = s.bandanaOffer;
		if (!s.bandana.on || !offer || !offer.available || !s.fabric) {
			return [];
		}
		var lines = [
			{ name: offer.pattern.name, detail: t.bandanaFor, qty: 1, price: offer.pattern.price },
			{ name: s.fabric.name, detail: t.bandanaFor + ' · ' + offer.fabric_text, qty: offer.fabric_units, price: offer.fabric_price }
		];
		[s.bandana.thread, s.bandana.band].forEach(function (card) {
			if (card) {
				lines.push({ name: card.name, detail: t.bandanaFor + (card.qty_text ? ' · ' + card.qty_text : ''), qty: card.qty, price: card.price });
			}
		});
		return lines;
	};

	// The offer for this kit, asked once per size, fabric and language; then the summary is drawn again.
	Configurator.prototype.loadBandana = function () {
		var self = this;
		var s = this.state;
		if (!cfg.bandana || !s.size || s.size.fabric_units <= 0 || !s.fabric) {
			return;
		}
		var key = s.size.id + '-' + s.fabric.id + '-' + lang;
		if (s.bandanaKey === key) {
			return;
		}
		s.bandanaKey = key;
		request('bandana', { variation: s.size.id, fabric: s.fabric.id })
			.then(function (offer) {
				if (s.bandanaKey !== key) {
					return;
				}
				s.bandanaOffer = offer;
				// A saved kit: its choices again, when they can still be bought.
				var keep = s.bandanaRestore;
				s.bandanaRestore = null;
				if (keep && keep.on && offer.available) {
					s.bandana.on = true;
					s.bandana.thread = findById(offer.threads, keep.thread) || null;
					s.bandana.band = findById(offer.bands, keep.band) || null;
				}
				if (s.step === 'summary' && !s.added && offer.available) {
					self.redrawSummary();
				}
			})
			.catch(function () {
				s.bandanaKey = '';
			});
	};

	// The summary again, without moving the page.
	Configurator.prototype.redrawSummary = function () {
		var y = window.pageYOffset;
		this.renderSummaryStep();
		window.scrollTo(window.pageXOffset, y);
	};

	// "A matching dog bandana?": a switch, and when it is on a thread and a band to choose (or none).
	Configurator.prototype.bandanaHtml = function (discount) {
		var s = this.state;
		var offer = s.bandanaOffer;
		if (!offer || !offer.available || s.added) {
			return '';
		}
		// The price with the pattern and the fabric (and what is chosen), with the kit discount.
		var on = s.bandana.on;
		s.bandana.on = true;
		var sum = 0;
		this.bandanaLines().forEach(function (line) {
			sum += line.price * line.qty;
		});
		s.bandana.on = on;
		var html =
			'<section class="aimp-bandana-offer' + (on ? ' is-on' : '') + '">' +
			'<div class="aimp-bandana-offer-head">' +
			(offer.pattern.image ? '<img src="' + esc(offer.pattern.image) + '" alt="" loading="lazy">' : '') +
			'<div><h4>' + esc(t.bandanaTitle) + '</h4><p>' + esc(fmt(t.bandanaText, offer.fabric_text)) +
			(offer.guide_url ? ' <a href="' + esc(offer.guide_url) + '" target="_blank" rel="noopener">' + esc(t.bandanaGuide) + '</a>' : '') + '</p>' +
			'<label class="aimp-bandana-switch"><input type="checkbox" data-role="bandana"' + (on ? ' checked' : '') + '> <span>' + esc(t.bandanaAdd) + '</span> <strong>+ ' + esc(money(sum * (100 - discount) / 100)) + '</strong></label>' +
			'</div></div>';
		if (on) {
			var pick = function (key, title, cards) {
				if (!cards || !cards.length) {
					return '';
				}
				var chosen = s.bandana[key];
				var out = '<div class="aimp-bandana-pick" role="group" aria-label="' + esc(title) + '"><p class="aimp-bandana-pick-title">' + esc(title) + '</p><div class="aimp-bandana-choices">' +
					'<button type="button" class="aimp-bandana-choice aimp-bandana-choice--none" data-pick="' + key + '" data-id="0" aria-pressed="' + (chosen ? 'false' : 'true') + '">' + esc(t.bandanaNone) + '</button>';
				cards.forEach(function (card) {
					var active = chosen && chosen.id === card.id;
					out += '<button type="button" class="aimp-bandana-choice" data-pick="' + key + '" data-id="' + esc(card.id) + '" aria-pressed="' + (active ? 'true' : 'false') + '">' +
						'<img src="' + esc(card.image) + '" alt="' + esc(card.image_alt || '') + '" loading="lazy"><span class="aimp-bandana-choice-name">' + esc(card.name) + '</span>' +
						'<span class="aimp-bandana-choice-price">' + esc(money(card.price * card.qty)) + '</span></button>';
				});
				return out + '</div></div>';
			};
			html += pick('thread', t.bandanaThread, offer.threads) + pick('band', fmt(t.bandanaBand, offer.band_text), offer.bands);
		}
		return html + '</section>';
	};

	Configurator.prototype.bindBandana = function () {
		var self = this;
		var s = this.state;
		var box = this.body.querySelector('.aimp-bandana-offer');
		if (!box) {
			return;
		}
		box.querySelector('[data-role="bandana"]').addEventListener('change', function (e) {
			s.bandana.on = e.target.checked;
			self.redrawSummary();
		});
		box.querySelectorAll('[data-pick]').forEach(function (button) {
			button.addEventListener('click', function () {
				var key = button.getAttribute('data-pick');
				var list = key === 'thread' ? s.bandanaOffer.threads : s.bandanaOffer.bands;
				s.bandana[key] = findById(list, parseInt(button.getAttribute('data-id'), 10)) || null;
				self.redrawSummary();
				var again = self.body.querySelector('[data-pick="' + key + '"][data-id="' + button.getAttribute('data-id') + '"]');
				if (again) {
					try {
						again.focus({ preventScroll: true });
					} catch (err) {
						again.focus();
					}
				}
			});
		});
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

		var html = '<div class="aimp-step aimp-step--summary">' + introHtml('summary') + this.recapHtml() + '<h3>' + esc(t.summaryTitle) + '</h3>';
		// Design studio: the kit discount only at the total (and as a gold badge above the table).
		var perLine = design.cz_discount !== 'total' && design.cz_discount !== 'badge';
		if (discount && design.cz_discount === 'badge') {
			html += '<p class="aimp-cz-badge">' + esc(fmt(t.kitDiscount, discount)) + '</p>';
		}
		html += '<div class="aimp-table-scroll"><table class="aimp-summary"><thead><tr><th>' + esc(t.product) + '</th><th>' + esc(t.quantity) + '</th><th>' + esc(t.price) + '</th></tr></thead><tbody>';
		lines.forEach(function (line) {
			var subtotal = line.price * line.qty;
			total += subtotal;
			html +=
				'<tr><td>' + esc(line.name) + (line.detail ? '<br><small>' + esc(line.detail) + '</small>' : '') + '</td>' +
				'<td>' + esc(line.qty) + '</td><td class="aimp-summary-price">' + (perLine ? priceHtml(subtotal) : esc(money(subtotal))) + '</td></tr>';
		});
		html += '</tbody><tfoot>';
		if (discount) {
			html += '<tr class="aimp-summary-discount"><th colspan="2">' + esc(fmt(t.kitDiscount, discount)) + '</th><td>−' + esc(money(total * discount / 100)) + '</td></tr>';
		}
		html += '<tr><th colspan="2">' + esc(t.total) + '</th><td class="aimp-summary-price">' + priceHtml(total) + '</td></tr></tfoot></table></div>';
		html += this.bandanaHtml(discount);
		if (s.size.lining_units > 0 && !s.lining) {
			html += '<p class="aimp-help aimp-summary-note">' + esc(s.ownLining ? t.ownLiningNote : t.noLiningNote) + '</p>';
		}
		if (this.needsInterfacing() && s.ownInterfacing) {
			html += '<p class="aimp-help aimp-summary-note">' + esc(t.ownInterfacingNote) + '</p>';
		}
		this.notionTypes().forEach(function (type) {
			if (s.notions[type].own && !s.notions[type].selected) {
				html += '<p class="aimp-help aimp-summary-note">' + esc(fmt(t.ownNotionNote, this.notionName(type))) + '</p>';
			}
		}, this);
		html += '<p class="aimp-help">' + esc(t.lockedNote) + '</p>';
		if (design.cz_note) {
			html += '<p class="aimp-help aimp-cz-note">' + esc(localText(design.cz_note)) + '</p>';
		}
		html += '<div data-role="messages"></div>';
		html +=
			'<div class="aimp-actions">' +
			'<button type="button" class="aimp-button aimp-button--ghost" data-action="back"' + (s.added ? ' hidden' : '') + '>' + esc(t.back) + '</button>' +
			'<button type="button" class="aimp-button aimp-button--ghost" data-action="save"' + (design.cz_nosave ? ' hidden' : '') + '>' + esc(t.saveKit) + '</button>' +
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
		this.bindBandana();
		this.loadBandana();
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
			fabric: s.size.fabric_units > 0 && s.fabric ? s.fabric.id : 0,
			lining: s.size.lining_units > 0 && s.lining ? s.lining.id : 0,
			own_interfacing: s.ownInterfacing ? 1 : 0,
			own_lining: s.ownLining && !s.lining ? 1 : 0,
			own_notions: this.notionTypes().filter(function (type) {
				return s.notions[type].own && !s.notions[type].selected;
			}).join(',')
		};
		this.bandanaParams(params);
		NOTIONS.forEach(function (n) {
			var selected = s.notions[n.type].selected;
			params[n.role] = n.qty(s.size) > 0 && selected ? selected.id : 0;
		});
		button.disabled = true;
		button.textContent = t.saving;
		secureRequest('save_kit', params)
			.then(function (data) {
				button.hidden = true;
				box.innerHTML =
					'<div class="aimp-notice aimp-notice--success aimp-notice--saved' + (design.cz_nosparkle ? '' : ' is-sparked') + '" role="status"><p>' + esc(data.message) +
					(data.url ? ' <a href="' + esc(data.url) + '">' + esc(t.viewFavorites) + '</a>' : '') + '</p></div>';
			})
			.catch(function (err) {
				button.disabled = false;
				button.textContent = t.saveKit;
				this.showError(box, err);
			}.bind(this));
	};

	Configurator.prototype.bandanaParams = function (params) {
		var b = this.state.bandana;
		var on = b.on && this.bandanaLines().length > 0;
		params.bandana = on ? 1 : 0;
		params.bandana_thread = on && b.thread ? b.thread.id : 0;
		params.bandana_band = on && b.band ? b.band.id : 0;
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
				s.lining = data.lining || null;
				NOTIONS.forEach(function (n) {
					s.notions[n.type].selected = (data.notions && data.notions[n.type]) || null;
				});
				s.ownInterfacing = !!data.own_interfacing && !s.notions.interfacing.selected;
				s.ownLining = !!data.own_lining && !s.lining;
				(data.own_notions || []).forEach(function (type) {
					if (s.notions[type] && !s.notions[type].selected) {
						s.notions[type].own = true;
					}
				});
				// The bandana's choices come back once its offer is loaded (summary).
				s.bandanaRestore = data.bandana || null;
				// The first step that still needs a choice, otherwise the overview.
				var step = 'summary';
				if (s.size.fabric_units > 0 && !s.fabric) {
					step = 'fabric';
				} else if (self.needsInterfacing() && !s.notions.interfacing.selected && !s.ownInterfacing) {
					step = 'interfacing';
				} else if (self.notionTypes().some(function (type) {
					return !s.notions[type].selected && !s.notions[type].own;
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
			fabric: s.size.fabric_units > 0 && s.fabric ? s.fabric.id : 0,
			lining: s.size.lining_units > 0 && s.lining ? s.lining.id : 0
		};
		this.bandanaParams(params);
		// One parameter per haberdashery role: button, zip, ribbon, bias.
		NOTIONS.forEach(function (n) {
			var selected = s.notions[n.type].selected;
			params[n.role] = n.qty(s.size) > 0 && selected ? selected.id : 0;
		});

		secureRequest('add_to_cart', params)
			.then(function (data) {
				s.added = true;
				button.hidden = true;
				back.hidden = true;
				self.renderSteps();
				messages.innerHTML = '<div class="aimp-notice aimp-notice--success"><p>' + esc(data.message) + '</p></div>';
				// The step's button bar (at the bottom on phones) now offers the cart or a new kit.
				var bar = self.body.querySelector('.aimp-step--summary > .aimp-actions');
				bar.innerHTML =
					'<button type="button" class="aimp-button aimp-button--ghost" data-action="again">' + esc(t.makeAnother) + '</button>' +
					'<a class="aimp-button" href="' + esc(data.cart_url || cfg.cartUrl) + '">' + esc(t.goToCart) + '</a>';
				bar.querySelector('[data-action="again"]').addEventListener('click', function () {
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
				s.linings = null;
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
