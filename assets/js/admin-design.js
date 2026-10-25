/**
 * Design studio (class-aimp-design.php): the parts and their settings, own texts, saved looks, the preview frame,
 * saving the draft, publishing, the history and the backup file.
 */
(function () {
	'use strict';

	var root = document.querySelector('[data-aimp-design]');
	var cfg = window.aimpDesign;
	if (!root || !cfg || !window.fetch) {
		return;
	}
	var t = cfg.i18n;
	var q = function (sel) {
		return root.querySelector(sel);
	};
	var draft = clone(cfg.draft);
	var live = clone(cfg.live);
	var draftTexts = clone(cfg.draftTexts);
	var liveTexts = clone(cfg.liveTexts);
	// Texts of the Atelier Irisee settings ("option:key"): changed in the studio, and as published now.
	var draftSettings = clone(cfg.draftSettings);
	var liveSettings = clone(cfg.liveSettings);
	var images = clone(cfg.images);
	var siteCss = cfg.siteCss || '';
	var files = clone(cfg.files);
	var history = cfg.history || [];
	var looks = cfg.looks || [];
	var current = '';
	var frame = q('.aimp-ds-frame');
	var wrap = q('.aimp-ds-frame-wrap');
	var stage = q('.aimp-ds-stage');
	var status = q('.aimp-ds-status');
	var width = 1280;
	var saveTimer = 0;
	var reloadTimer = 0;
	var saving = false;
	var saveAgain = false;
	var textsChanged = false;
	var frameOrigin = '';
	// The parts found on the page in the preview (null until it has loaded), and computer or phone settings.
	var present = null;
	var device = 'computer';
	// Like Divi: a part's settings on three tabs. Content = the clicked text and the part's own settings,
	// Design = how it looks, Advanced = where it sits, when it shows, how it moves.
	var DESIGN = ['typo', 'colors', 'image', 'imgpos', 'size', 'space', 'radius'];
	var ADVANCED = ['move', 'hide', 'anim'];
	var activeTab = 'content';
	// The shop text clicked in the preview ({ original, shown }), or that the clicked words are not a shop text.
	var currentText = null;
	var currentMiss = false;
	// The part and text the panel shows now (to keep its tab and open block when it is drawn again).
	var shownPart = '';
	var shownText = null;
	// The preview's own post (product or page), for "Edit this in WordPress".
	var currentPost = 0;
	// The shop texts on the page in the preview (originals), or null.
	var pageTexts = null;
	// "Only a text": a click on a shop text outside the registered parts.
	cfg.elements.__text = { group: '', label: t.textOnly, selector: '', controls: [] };
	// Own parts: any element clicked in the preview ("el_…"; their selector and name are kept with their settings).
	var OWN_CONTROLS = ['typo', 'colors', 'image', 'size', 'space', 'radius', 'move', 'hide', 'anim'];
	function registerOwn(id, selector, name) {
		cfg.elements[id] = { group: t.ownParts, label: name || id, selector: selector, controls: OWN_CONTROLS, own: true };
	}
	[cfg.live || {}, cfg.draft || {}].forEach(function (styles) {
		Object.keys(styles).forEach(function (id) {
			if (/^el_[a-f0-9]{8}$/.test(id) && styles[id].sel) {
				registerOwn(id, styles[id].sel, styles[id].name);
			}
		});
	});
	// The own part for an element of the preview (the same one when it was picked before).
	function ownFor(entry) {
		var found = '';
		Object.keys(cfg.elements).forEach(function (id) {
			if (cfg.elements[id].own && cfg.elements[id].selector === entry.sel) {
				found = id;
			}
		});
		if (!found) {
			found = 'el_' + ('00000000' + Math.floor(Math.random() * 4294967295).toString(16)).slice(-8);
			registerOwn(found, entry.sel, entry.name);
		}
		return found;
	}
	// What was clicked in the preview and what is around it (innermost first), the page's address.
	var lastPath = [];
	// Navigation: the parts chosen before (back), the layers of the page in the preview and its title.
	var partHistory = [];
	var goingBack = false;
	var layerRows = null;
	var frameTitle = '';
	var foldedLayers = {};
	var framePostId = 0;
	var frameUrl = '';
	var currentDivi = false;

	function clone(value) {
		return JSON.parse(JSON.stringify(value || {}));
	}

	function sorted(obj) {
		var out = {};
		Object.keys(obj || {}).sort().forEach(function (key) {
			var row = obj[key];
			var clean = {};
			Object.keys(row).sort().forEach(function (k) {
				clean[k] = row[k];
			});
			out[key] = clean;
		});
		return out;
	}

	function same(a, b) {
		return JSON.stringify(sorted(a)) === JSON.stringify(sorted(b));
	}

	function unpublished() {
		return !same(draft, live) || !same(draftTexts, liveTexts) || Object.keys(draftSettings).length > 0;
	}

	function fmt(text) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return String(text).replace(/%(\d)\$d|%d/g, function (all, n) {
			return String(args[n ? parseInt(n, 10) - 1 : i++]);
		});
	}

	// This studio tab was opened before a plugin update: a banner to reload (the server does not save from it).
	var staleShown = false;
	function staleStudio() {
		if (staleShown) {
			return;
		}
		staleShown = true;
		var banner = document.createElement('div');
		banner.className = 'aimp-ds-stale';
		banner.setAttribute('role', 'alert');
		var text = document.createElement('span');
		text.textContent = t.staleStudio;
		var reload = document.createElement('button');
		reload.type = 'button';
		reload.className = 'button button-primary';
		reload.textContent = t.reloadStudio;
		reload.addEventListener('click', function () {
			window.location.reload();
		});
		banner.appendChild(text);
		banner.appendChild(reload);
		document.body.appendChild(banner);
	}

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', 'aimp_design_' + action);
		body.append('nonce', cfg.nonce);
		// The server only saves from a studio of its own plugin version (an old tab: reload first).
		body.append('version', cfg.version || '');
		Object.keys(data || {}).forEach(function (key) {
			body.append(key, data[key]);
		});
		return fetch(cfg.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (r) {
			return r.json();
		}).then(function (json) {
			if (!json || !json.success) {
				if (json && json.data && json.data.reload) {
					staleStudio();
				}
				throw new Error(json && json.data && json.data.message ? json.data.message : t.failed);
			}
			return json.data;
		});
	}

	// Everything the server sent back after a change.
	function setState(data, keepDraft) {
		if (!keepDraft) {
			draft = clone(data.draft);
			draftTexts = clone(data.draftTexts);
			draftSettings = clone(data.draftSettings);
		}
		liveSettings = clone(data.liveSettings);
		live = clone(data.live);
		liveTexts = clone(data.liveTexts);
		siteCss = data.siteCss || '';
		Object.keys(data.files || {}).forEach(function (id) {
			files[id] = data.files[id];
		});
		Object.keys(data.images || {}).forEach(function (id) {
			images[id] = data.images[id];
		});
		history = data.history || history;
		looks = data.looks || looks;
		renderList();
		renderHistory();
		renderLooks();
		showEdit();
		sendCss();
		if (!keepDraft) {
			renderTexts();
		}
		showStatus();
	}

	function showStatus(text) {
		status.textContent = text || (unpublished() ? t.unpublished : t.published);
		// The whole text on hover: the bar keeps one line (a long text is cut off with …).
		status.title = status.textContent;
		status.classList.toggle('is-draft', unpublished());
	}

	/* ---------------- the CSS (the same as AIMP_Design::css()) ---------------- */

	function prefixed(selector, extra) {
		return selector.split(',').map(function (part) {
			part = part.trim();
			return (part.indexOf('body') === 0 ? 'html ' : 'html body ') + part + (extra || '');
		}).join(',');
	}

	// Spacing like Divi: margin and padding on every side (key => CSS property).
	var SIDES = [['mt', 'margin-top'], ['mr', 'margin-right'], ['mb', 'margin-bottom'], ['ml', 'margin-left'],
		['padt', 'padding-top'], ['padr', 'padding-right'], ['padb', 'padding-bottom'], ['padl', 'padding-left']];

	function css(styles) {
		var all = '';
		var computer = '';
		var tablet = '';
		var phone = '';
		Object.keys(styles).forEach(function (id) {
			var el = cfg.elements[id];
			var row = styles[id];
			// "Whole site" parts: their CSS comes from the server (siteCss).
			if (!el || !el.selector) {
				return;
			}
			var base = el.selector;
			// An own part in a page header that is not kept to one page (made by an old studio tab): no CSS (as the server).
			if (el.own && base.indexOf('aimp-page-header') !== -1 && base.indexOf('body.') === -1) {
				return;
			}
			var sel = prefixed(base);
			var common = '';
			[['color', 'color'], ['bg', 'background-color'], ['border', 'border-color']].forEach(function (pair) {
				if (row[pair[0]]) {
					common += pair[1] + ':' + row[pair[0]] + '!important;';
				}
			});
			if (row.radius !== undefined) {
				common += 'border-radius:' + parseInt(row.radius, 10) + 'px!important;';
			}
			if (common) {
				all += sel + '{' + common + '}';
			}
			var typo = '';
			if (row.fsize) {
				typo += 'font-size:' + parseInt(row.fsize, 10) + 'px!important;';
			}
			if (row.fweight) {
				typo += 'font-weight:' + parseInt(row.fweight, 10) + '!important;';
			}
			if (row.tcase) {
				typo += 'text-transform:' + (row.tcase === 'upper' ? 'uppercase' : 'none') + '!important;';
			}
			if (typo) {
				all += sel + ',' + prefixed(base, ' *') + '{' + typo + '}';
			}
			if (row.talign === 'left' || row.talign === 'center' || row.talign === 'right') {
				all += sel + '{text-align:' + row.talign + '!important}';
			}
			var image = '';
			if (row.img && images[row.img]) {
				image += 'content:url("' + images[row.img].replace(/"/g, '%22') + '")!important;object-fit:cover;';
			}
			if (row.fx !== undefined || row.fy !== undefined) {
				image += 'object-position:' + (row.fx !== undefined ? parseInt(row.fx, 10) : 50) + '% ' + (row.fy !== undefined ? parseInt(row.fy, 10) : 50) + '%!important;';
			}
			if (image) {
				all += prefixed(base, ':is(img)') + ',' + prefixed(base, ' img') + '{' + image + '}';
			}
			if (row.noanim) {
				all += [sel, prefixed(base, ' *'), prefixed(base, '::before'), prefixed(base, '::after'), prefixed(base, ' *::before'), prefixed(base, ' *::after')].join(',') + '{animation:none!important;transition:none!important}';
			}
			var device = function (p) {
				var out = '';
				var x = parseInt(row[p + 'x'] || 0, 10);
				var y = parseInt(row[p + 'y'] || 0, 10);
				if (x || y) {
					out += 'translate:' + x + 'px ' + y + 'px!important;';
				}
				if (row[p + 'zoom'] !== undefined) {
					out += 'zoom:' + (parseInt(row[p + 'zoom'], 10) / 100) + '!important;';
				}
				if (row[p + 'hgt'] !== undefined) {
					out += 'height:' + parseInt(row[p + 'hgt'], 10) + 'px!important;min-height:0!important;';
				}
				if (row[p + 'wdt'] !== undefined) {
					out += 'width:' + parseInt(row[p + 'wdt'], 10) + 'px!important;max-width:100%!important;';
				}
				SIDES.forEach(function (pair) {
					if (row[p + pair[0]] !== undefined) {
						out += pair[1] + ':' + parseInt(row[p + pair[0]], 10) + 'px!important;';
					}
				});
				if (row[p + 'hide']) {
					out += 'display:none!important;';
				}
				return out;
			};
			// Picture position: moved inside the frame (which clips it) and zoomed around the focal point.
			var frames = prefixed(base, ':has(> img)') + ',' + prefixed(base, ' :has(> img)');
			[['', 'computer'], ['p', 'phone']].forEach(function (screen) {
				var p = screen[0];
				var ix = parseInt(row[p + 'ix'] || 0, 10);
				var iy = parseInt(row[p + 'iy'] || 0, 10);
				var zoom = row[p + 'izoom'] !== undefined ? parseInt(row[p + 'izoom'], 10) : 100;
				if (!ix && !iy && zoom === 100) {
					return;
				}
				var origin = (row.fx !== undefined ? parseInt(row.fx, 10) : 50) + '% ' + (row.fy !== undefined ? parseInt(row.fy, 10) : 50) + '%';
				var rule = prefixed(base, ':is(img)') + ',' + prefixed(base, ' img') + '{transform:translate(' + ix + '%,' + iy + '%) scale(' + (zoom / 100) + ')!important;transform-origin:' + origin + '!important}' + frames + '{overflow:hidden!important}';
				if (p) {
					phone += rule;
				} else {
					computer += rule;
				}
			});
			var d = device('');
			var tb = device('t');
			var m = device('p');
			if (d) {
				computer += sel + '{' + d + '}';
			}
			if (tb) {
				tablet += sel + '{' + tb + '}';
			}
			if (m) {
				phone += sel + '{' + m + '}';
			}
		});
		var out = all;
		if (computer) {
			out += '@media (min-width:' + (parseInt(cfg.phone, 10) + 1) + 'px){' + computer + '}';
		}
		// Tablets: their own spacing where it is set (as AIMP_Design::css()).
		if (tablet) {
			out += '@media (min-width:' + (parseInt(cfg.phone, 10) + 1) + 'px) and (max-width:' + (parseInt(cfg.tablet, 10) || 1024) + 'px){' + tablet + '}';
		}
		if (phone) {
			out += '@media (max-width:' + cfg.phone + 'px){' + phone + '}';
		}
		return out;
	}

	function speeds(styles) {
		var map = {};
		Object.keys(styles).forEach(function (id) {
			if (cfg.elements[id] && styles[id].speed !== undefined && !styles[id].noanim) {
				map[cfg.elements[id].selector] = parseInt(styles[id].speed, 10);
			}
		});
		return map;
	}

	function toFrame(message) {
		if (frame.contentWindow && frameOrigin) {
			frame.contentWindow.postMessage(message, frameOrigin);
		}
	}

	function sendCss() {
		toFrame({ type: 'aimp-design-css', css: siteCss + css(draft), speeds: speeds(draft) });
	}

	/* ---------------- saving ---------------- */

	function save(texts) {
		textsChanged = textsChanged || !!texts;
		window.clearTimeout(saveTimer);
		// The whole-site look is made on the server: saved sooner, so the preview follows quickly.
		var site = current && cfg.elements[current] && !cfg.elements[current].selector;
		saveTimer = window.setTimeout(flush, site ? 300 : 700);
		showStatus(t.saving);
	}

	function flush() {
		if (saving) {
			saveAgain = true;
			return;
		}
		saving = true;
		var reload = textsChanged;
		textsChanged = false;
		post('save', { styles: JSON.stringify(draft), texts: JSON.stringify(draftTexts), settings: JSON.stringify(draftSettings) }).then(function (data) {
			setState(data, true);
			showStatus(unpublished() ? t.saved + ' · ' + t.unpublished : t.published);
			// Texts are made on the server: the preview loads again once you stop typing.
			if (reload) {
				window.clearTimeout(reloadTimer);
				reloadTimer = window.setTimeout(function () {
					reloadPreview();
				}, 600);
			}
		}).catch(function (e) {
			showStatus(e.message);
		}).then(function () {
			saving = false;
			if (saveAgain) {
				saveAgain = false;
				flush();
			}
		});
	}

	/* ---------------- tabs ---------------- */

	root.querySelectorAll('.aimp-ds-tabs [data-tab]').forEach(function (tab) {
		tab.addEventListener('click', function () {
			var name = tab.getAttribute('data-tab');
			root.querySelectorAll('.aimp-ds-tabs [data-tab]').forEach(function (other) {
				other.setAttribute('aria-selected', other === tab ? 'true' : 'false');
			});
			root.querySelectorAll('.aimp-ds-pane').forEach(function (pane) {
				pane.hidden = pane.getAttribute('data-pane') !== name;
			});
			if (name === 'texts') {
				loadTexts();
			}
		});
	});

	/* ---------------- the list of parts ---------------- */

	var list = q('.aimp-ds-list');
	var search = q('.aimp-ds-search');

	// Buttons for parts, under the name of their group (Header and menu, Shop pages, …).
	function addItems(container, ids) {
		var groups = {};
		ids.forEach(function (id) {
			(groups[cfg.elements[id].group] = groups[cfg.elements[id].group] || []).push(id);
		});
		Object.keys(groups).forEach(function (group) {
			var h = document.createElement('h3');
			h.textContent = group;
			container.appendChild(h);
			groups[group].forEach(function (id) {
				var b = document.createElement('button');
				b.type = 'button';
				b.className = 'aimp-ds-item' + (id === current ? ' is-current' : '') + (draft[id] ? ' is-changed' : '');
				b.textContent = cfg.elements[id].label;
				if (draft[id]) {
					var dot = document.createElement('span');
					dot.className = 'aimp-ds-dot';
					dot.title = t.changed;
					b.appendChild(dot);
				}
				b.addEventListener('click', function () {
					select(id, true);
				});
				container.appendChild(b);
			});
		});
	}

	// A folded section of the list ("Whole site", "Other pages"); open while searching or when it holds the chosen part.
	function addFold(title, ids, open) {
		if (!ids.length) {
			return;
		}
		var fold = document.createElement('details');
		fold.className = 'aimp-ds-fold';
		fold.open = !!open || ids.indexOf(current) !== -1;
		var summary = document.createElement('summary');
		summary.textContent = title + ' (' + ids.length + ')';
		fold.appendChild(summary);
		addItems(fold, ids);
		list.appendChild(fold);
	}

	// Whether a selector is found on the page in the preview (the same site, so the studio can look).
	function onPage(selector) {
		try {
			return !!(frame.contentDocument && frame.contentDocument.querySelector(prefixed(selector)));
		} catch (e) {
			return false;
		}
	}

	function renderList() {
		var term = search.value.trim().toLowerCase();
		var site = [];
		var here = [];
		var other = [];
		Object.keys(cfg.elements).forEach(function (id) {
			var el = cfg.elements[id];
			if (id === '__text' || (term && (el.label + ' ' + el.group).toLowerCase().indexOf(term) === -1)) {
				return;
			}
			if (!el.selector) {
				site.push(id);
			} else if (!present || present.indexOf(id) !== -1 || (el.own && onPage(el.selector))) {
				here.push(id);
			} else {
				other.push(id);
			}
		});
		list.innerHTML = '';
		var h = document.createElement('p');
		h.className = 'aimp-ds-list-title';
		h.textContent = present ? (layerRows ? t.layers : t.onPage) : t.allParts;
		list.appendChild(h);
		if (here.length && layerRows) {
			// Layers: the parts of this page in page order, inside each other; own picks after them.
			var inTree = addLayers(list, here);
			var rest = here.filter(function (id) {
				return inTree.indexOf(id) === -1;
			});
			if (rest.length) {
				addItems(list, rest);
			}
		} else if (here.length) {
			addItems(list, here);
		} else {
			var none = document.createElement('p');
			none.className = 'description';
			none.textContent = t.noneHere;
			list.appendChild(none);
		}
		addFold(t.wholeSite, site, !!term);
		addFold(t.otherPages, other, !!term);
	}

	search.addEventListener('input', renderList);

	/* ---------------- the chosen part ---------------- */

	var edit = q('.aimp-ds-edit');

	// The standard value of a number setting (cfg.groups: [ min, max, standard ]), else null.
	function neutral(key) {
		var found = null;
		Object.keys(cfg.groups).forEach(function (group) {
			var rule = cfg.groups[group][key];
			if (Array.isArray(rule) && typeof rule[0] === 'number') {
				found = rule[2];
			}
		});
		return found;
	}

	// A chosen part: only its settings, without the list (‹ All parts goes back).
	function select(id, scroll, text, miss) {
		if (scroll) {
			lastPath = [];
		}
		// "Back to the previous part": the parts chosen before this one.
		if (!goingBack && current && current !== id) {
			partHistory.push(current);
			if (partHistory.length > 30) {
				partHistory.shift();
			}
		}
		goingBack = false;
		toFrame({ type: 'aimp-design-peek' });
		current = id;
		currentText = text || null;
		currentMiss = !!miss && !text;
		renderList();
		showEdit();
		if (cfg.elements[id] && cfg.elements[id].selector) {
			toFrame({ type: 'aimp-design-show', id: id, selector: cfg.elements[id].selector, scroll: !!scroll });
		}
	}

	function focusMode(on) {
		[q('.aimp-ds-search'), list, q('.aimp-ds-hint')].forEach(function (el) {
			if (el) {
				el.hidden = on;
			}
		});
	}

	q('.aimp-ds-back').addEventListener('click', function () {
		current = '';
		toFrame({ type: 'aimp-design-show', id: '' });
		renderList();
		showEdit();
		sendSpacing();
	});

	function showEdit() {
		if (!current || !cfg.elements[current]) {
			edit.hidden = true;
			focusMode(false);
			return;
		}
		edit.hidden = false;
		focusMode(true);
		var controls = cfg.elements[current].controls || [];
		// The same part again (e.g. after saving): the same tab and the same open block.
		var samePart = current === shownPart && currentText === shownText;
		var openGroup = edit.querySelector('.aimp-ds-group.is-open');
		var openKey = samePart && openGroup ? (openGroup.getAttribute('data-group') || 'text') : '';
		shownPart = current;
		shownText = currentText;
		edit.querySelectorAll('.aimp-ds-group').forEach(function (group) {
			var key = group.getAttribute('data-group');
			group.classList.remove('is-open');
			if (key) {
				group.hidden = controls.indexOf(key) === -1;
				group.setAttribute('data-tab', DESIGN.indexOf(key) !== -1 ? 'design' : (ADVANCED.indexOf(key) !== -1 ? 'advanced' : 'content'));
			}
		});
		// In order: the clicked text, the part's own settings, then design and advanced in a fixed order.
		var home = groupsHome;
		home.appendChild(textGroup);
		home.appendChild(settingsGroup);
		controls.filter(function (key) {
			return DESIGN.indexOf(key) === -1 && ADVANCED.indexOf(key) === -1;
		}).concat(DESIGN, ADVANCED).forEach(function (key) {
			var group = edit.querySelector('.aimp-ds-group[data-group="' + key + '"]');
			if (group) {
				home.appendChild(group);
			}
		});
		textGroup.hidden = !currentText && !currentMiss;
		renderTextGroup();
		drawFocalPads();
		settingsGroup.hidden = !renderSettingsGroup();
		renderPath();
		// The tabs that have something; a clicked text opens on Content.
		var filled = {};
		edit.querySelectorAll('.aimp-ds-group').forEach(function (group) {
			if (!group.hidden) {
				filled[group.getAttribute('data-tab')] = true;
			}
		});
		partTabs.querySelectorAll('button').forEach(function (button) {
			button.hidden = !filled[button.getAttribute('data-ptab')];
		});
		partTabs.hidden = Object.keys(filled).length < 2;
		if ((!samePart && (currentText || currentMiss)) || !filled[activeTab]) {
			activeTab = filled.content ? 'content' : (filled.design ? 'design' : 'advanced');
		}
		setTab(activeTab);
		var keep = openKey === 'text' ? (openGroup === settingsGroup ? settingsGroup : textGroup) : (openKey ? edit.querySelector('.aimp-ds-group[data-group="' + openKey + '"]') : null);
		if (keep && !keep.hidden && !keep.classList.contains('is-off-tab')) {
			edit.querySelectorAll('.aimp-ds-group.is-open').forEach(function (other) {
				other.classList.remove('is-open');
			});
			keep.classList.add('is-open');
		} else if (!samePart && !currentText && !currentMiss) {
			// The tab and block that were open the last time this part was chosen.
			restoreOpen();
		}
		showPageBlocks();
		renderCrumbs();
		searchSettings();
		markGroups();
		q('.aimp-ds-edit-name').textContent = cfg.elements[current].label;
		var row = draft[current] || {};
		edit.querySelectorAll('[data-key]').forEach(function (input) {
			var key = input.getAttribute('data-key');
			if (input.type === 'checkbox') {
				input.checked = !!row[key];
			} else if (input.type === 'color') {
				input.value = row[key] ? colorHex(row[key]) : '#ffffff';
				input.closest('.aimp-ds-color').classList.toggle('is-set', !!row[key]);
				markSwatches(input, row[key] || '');
			} else if (input.tagName === 'SELECT' || input.tagName === 'TEXTAREA' || input.type === 'text' || input.type === 'url') {
				input.value = row[key] !== undefined ? row[key] : '';
			} else {
				// "Standard" outside the range (e.g. -1 for corners): an empty box.
				var standard = neutral(key);
				input.value = row[key] !== undefined ? row[key] : (standard !== null && standard < parseInt(input.min, 10) ? '' : standard);
			}
		});
		// Orders of blocks (product page): the saved order, the blocks left out in their standard place after it.
		edit.querySelectorAll('[data-order]').forEach(function (list) {
			var keys = row[list.getAttribute('data-order')] ? String(row[list.getAttribute('data-order')]).split(',') : [];
			var place = function (li) {
				var i = keys.indexOf(li.getAttribute('data-block'));
				return i === -1 ? 1000 + parseInt(li.getAttribute('data-std'), 10) : i;
			};
			Array.prototype.slice.call(list.children).sort(function (a, b) {
				return place(a) - place(b);
			}).forEach(function (li) {
				list.appendChild(li);
			});
		});
		edit.querySelectorAll('.aimp-ds-boxmodel[data-device="tablet"] input[type="number"]').forEach(function (input) {
			var computerKey = input.getAttribute('data-key').replace(/^t/, '');
			input.placeholder = row[computerKey] !== undefined ? row[computerKey] : '';
		});
		sendSpacing();
		edit.querySelectorAll('[data-image]').forEach(function (box) {
			var value = row[box.getAttribute('data-image')];
			var img = box.querySelector('.aimp-ds-image-preview');
			if (img) {
				img.hidden = !(value && images[value]);
				img.src = value && images[value] ? images[value] : '';
			}
			// Several pictures (hero slides).
			var gallery = box.querySelector('.aimp-ds-image-list');
			if (gallery) {
				gallery.innerHTML = '';
				(Array.isArray(value) ? value : []).forEach(function (id) {
					if (images[id]) {
						var thumb = document.createElement('img');
						thumb.src = images[id];
						thumb.alt = '';
						gallery.appendChild(thumb);
					}
				});
			}
			// A video: its file name.
			var name = box.querySelector('.aimp-ds-file-name');
			if (name) {
				name.textContent = value ? (files[value] || '#' + value) : '';
			}
		});
	}

	function change(key, value) {
		if (!current) {
			return;
		}
		remember();
		var row = draft[current] || {};
		if (value === '' || value === null || value === false || (Array.isArray(value) && !value.length) || (typeof value === 'number' && value === neutral(key))) {
			delete row[key];
		} else {
			row[key] = value;
		}
		if (cfg.elements[current] && cfg.elements[current].own) {
			var real = Object.keys(row).filter(function (k) {
				return k !== 'sel' && k !== 'name';
			});
			if (real.length) {
				row.sel = cfg.elements[current].selector;
				row.name = cfg.elements[current].label;
			} else {
				delete row.sel;
				delete row.name;
			}
		}
		if (Object.keys(row).length) {
			draft[current] = row;
		} else {
			delete draft[current];
		}
		sendCss();
		save(reloads(key));
		if (!reloads(key) && isOptionKey(key)) {
			fastCss();
		}
		showFocus(key);
		renderList();
		markGroups();
	}

	// A setting whose CSS the server makes (the "Whole site" and the parts' own options), not the studio itself.
	var PART_GROUPS = ['move', 'size', 'space', 'hide', 'colors', 'anim', 'radius', 'image', 'imgpos', 'typo'];
	function isOptionKey(key) {
		var option = false;
		Object.keys(cfg.groups).forEach(function (group) {
			if (cfg.groups[group][key] !== undefined && PART_GROUPS.indexOf(group) === -1) {
				option = true;
			}
		});
		return option || !(cfg.elements[current] && cfg.elements[current].selector);
	}

	// The server's CSS for the styles in the studio, without saving: shown within a moment.
	var cssTimer = 0;
	var cssTurn = 0;
	function fastCss() {
		window.clearTimeout(cssTimer);
		cssTimer = window.setTimeout(function () {
			var turn = ++cssTurn;
			post('css', { styles: JSON.stringify(draft) }).then(function (data) {
				if (turn === cssTurn) {
					siteCss = data.css || '';
					sendCss();
				}
			}).catch(function () {});
		}, 120);
	}

	/* ---------------- what moved where: shown in the preview ---------------- */

	function px(n) {
		return Math.abs(n) + ' px';
	}
	// The preview draws the part where it was (dashed), where it is now, an arrow and a label.
	function showFocus(key) {
		var el = cfg.elements[current];
		var row = draft[current] || {};
		if (current === 'hero' && (key === 'h_fx' || key === 'h_fy')) {
			toFrame({ type: 'aimp-design-focus', kind: 'focal', selector: '.aimp-hero', fx: row.h_fx !== undefined ? row.h_fx : 75, fy: row.h_fy !== undefined ? row.h_fy : 40, label: t.focal + ' ' + (row.h_fx !== undefined ? row.h_fx : 75) + '% / ' + (row.h_fy !== undefined ? row.h_fy : 40) + '%' });
			return;
		}
		if (!el || !el.selector) {
			return;
		}
		var space = key.match(/^([tp]?)(m[trbl]|pad[trbl])$/);
		if (space) {
			toFrame({ type: 'aimp-design-focus', kind: 'box', selector: el.selector, label: spaceLabel(space[2]) + ' ' + (row[key] !== undefined ? row[key] + ' px' : t.standardWord) });
			return;
		}
		var p = /^p(x|y|zoom)$/.test(key) ? 'p' : '';
		var base = key.replace(/^p(?=(x|y|zoom)$)/, '');
		if (base === 'x' || base === 'y') {
			var x = parseInt(row[p + 'x'] || 0, 10);
			var y = parseInt(row[p + 'y'] || 0, 10);
			var parts = [];
			if (x) {
				parts.push((x > 0 ? '→ ' : '← ') + px(x));
			}
			if (y) {
				parts.push((y > 0 ? '↓ ' : '↑ ') + px(y));
			}
			toFrame({ type: 'aimp-design-focus', kind: 'move', selector: el.selector, x: x, y: y, label: parts.length ? parts.join('   ') : t.notMoved });
		} else if (base === 'zoom') {
			toFrame({ type: 'aimp-design-focus', kind: 'box', selector: el.selector, label: t.sizeWord + ' ' + (row[p + 'zoom'] !== undefined ? row[p + 'zoom'] : 100) + ' %' });
		} else if (base === 'mt' || base === 'mb') {
			toFrame({ type: 'aimp-design-focus', kind: 'space', side: base === 'mt' ? 'top' : 'bottom', selector: el.selector, value: parseInt(row[p + base] || 0, 10), label: (base === 'mt' ? t.spaceAbove : t.spaceBelow) + ' ' + parseInt(row[p + base] || 0, 10) + ' px' });
		} else if (key === 'fx' || key === 'fy') {
			toFrame({ type: 'aimp-design-focus', kind: 'focal', selector: el.selector, fx: row.fx !== undefined ? row.fx : 50, fy: row.fy !== undefined ? row.fy : 50, label: t.focal + ' ' + (row.fx !== undefined ? row.fx : 50) + '% / ' + (row.fy !== undefined ? row.fy : 50) + '%' });
		}
	}

	// "Margin top", "Padding left", … for a spacing key without its screen prefix.
	function spaceLabel(base) {
		var side = { t: t.sideTop, r: t.sideRight, b: t.sideBottom, l: t.sideLeft }[base.slice(-1)];
		return (base.indexOf('pad') === 0 ? t.paddingWord : t.marginWord) + ' ' + side;
	}

	// The preview shows the margin and padding bands of the chosen part, with handles to drag.
	function sendSpacing() {
		var el = cfg.elements[current];
		var on = !!(el && el.selector && (el.controls || []).indexOf('space') !== -1);
		var labels = {};
		SIDES.forEach(function (pair) {
			labels[pair[0]] = spaceLabel(pair[0]);
		});
		toFrame({ type: 'aimp-design-spacing', on: on, selector: on ? el.selector : '', labels: labels });
	}

	// A band dragged in the preview: the value for the screen chosen at the top (one Undo step per drag).
	function onSpaceDrag(data) {
		var el = cfg.elements[current];
		if (!el || (el.controls || []).indexOf('space') === -1 || !/^(m[trbl]|pad[trbl])$/.test(data.key)) {
			return;
		}
		var prefix = device === 'phone' ? 'p' : (device === 'tablet' ? 't' : '');
		var min = data.key.indexOf('pad') === 0 ? 0 : -150;
		if (data.phase !== 'start') {
			lastSnap = Date.now();
		}
		change(prefix + data.key, Math.max(min, Math.min(300, Math.round(data.value))));
		if (data.phase === 'end') {
			showEdit();
		}
	}

	// Move the chosen part: dragged in the preview, or with the arrow keys (1 px, Shift: 10 px).
	var dragBase = null;
	function canMove() {
		return !!(current && cfg.elements[current] && cfg.elements[current].selector && (cfg.elements[current].controls || []).indexOf('move') !== -1);
	}
	function moveBy(dx, dy, base) {
		var p = device === 'phone' ? 'p' : '';
		var clamp = function (n) {
			return Math.max(-1000, Math.min(1000, Math.round(n)));
		};
		var row = draft[current] || {};
		var from = base || { x: parseInt(row[p + 'x'] || 0, 10), y: parseInt(row[p + 'y'] || 0, 10) };
		change(p + 'x', clamp(from.x + dx));
		change(p + 'y', clamp(from.y + dy));
	}
	// A setting of another part than the chosen one (e.g. the page header while its title is chosen).
	function changeOn(part, key, value) {
		var was = current;
		current = part;
		change(key, value);
		current = was;
		markGroups();
	}
	function onDrag(data) {
		// The title of a page header: dragged to a place in the header (% of it), kept per page.
		if (data.tx !== undefined && data.ty !== undefined) {
			if (data.phase !== 'start') {
				lastSnap = Date.now();
			}
			var base = 'ph_' + (framePostId || 'other') + '_';
			changeOn('page-header', base + 'tx', Math.max(0, Math.min(100, Math.round(data.tx))));
			changeOn('page-header', base + 'ty', Math.max(0, Math.min(100, Math.round(data.ty))));
			if (data.phase === 'end') {
				showEdit();
			}
			return;
		}
		if (!canMove()) {
			return;
		}
		if (device === 'tablet' && data.phase === 'start') {
			showStatus(t.resizeTablet);
		}
		var p = device === 'phone' ? 'p' : '';
		var row = draft[current] || {};
		if (data.phase === 'start' || !dragBase) {
			dragBase = { x: parseInt(row[p + 'x'] || 0, 10), y: parseInt(row[p + 'y'] || 0, 10) };
		} else {
			// One Undo step for the whole drag.
			lastSnap = Date.now();
		}
		moveBy(data.dx || 0, data.dy || 0, dragBase);
		if (data.phase === 'end') {
			dragBase = null;
			showEdit();
		}
	}
	function onResize(data) {
		if (!current || !cfg.elements[current] || !cfg.elements[current].selector) {
			return;
		}
		var p = device === 'phone' ? 'p' : (device === 'tablet' ? 't' : '');
		if (data.phase !== 'start') {
			// One Undo step for the whole drag.
			lastSnap = Date.now();
		}
		if (data.w !== undefined && data.w !== null) {
			change(p + 'wdt', Math.max(20, Math.min(2400, Math.round(data.w))));
		}
		if (data.h !== undefined && data.h !== null) {
			change(p + 'hgt', Math.max(20, Math.min(1500, Math.round(data.h))));
		}
		if (data.phase === 'end') {
			showEdit();
		}
	}
	function nudge(key, shift) {
		var step = shift ? 10 : 1;
		var d = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[key];
		if (!d || !canMove()) {
			return false;
		}
		moveBy(d[0], d[1]);
		window.clearTimeout(nudgeTimer);
		nudgeTimer = window.setTimeout(showEdit, 400);
		return true;
	}
	var nudgeTimer = 0;
	document.addEventListener('keydown', function (e) {
		if (/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.ctrlKey || e.metaKey || e.altKey) {
			return;
		}
		if (nudge(e.key, e.shiftKey)) {
			e.preventDefault();
		}
	});

	/* ---------------- the focal point: a picture with a cross to click or drag ---------------- */

	var FOCAL = { h_fx: ['h_fx', 'h_fy', 'h_img', 75, 40], fx: ['fx', 'fy', 'img', 50, 50] };
	function focalPicture(pictureKey) {
		var row = draft[current] || {};
		if (row[pictureKey] && images[row[pictureKey]]) {
			return images[row[pictureKey]];
		}
		// The picture the page shows now.
		try {
			var doc = frame.contentDocument;
			var sel = pictureKey === 'h_img' ? '.aimp-hero' : cfg.elements[current].selector;
			var el = doc.querySelector(prefixed(sel));
			// The hero: its photo (a background, or the first slide), never the round seal on it.
			var img = el && (el.tagName === 'IMG' ? el : el.querySelector(pictureKey === 'h_img' ? '.aimp-hero-media img' : 'img'));
			if (img && img.currentSrc) {
				return img.currentSrc;
			}
			var bg = el ? window.getComputedStyle(el).backgroundImage : '';
			var m = bg && bg.match(/url\("?(.*?)"?\)/);
			return m ? m[1] : '';
		} catch (e) {
			return '';
		}
	}
	function drawFocalPads() {
		Object.keys(FOCAL).forEach(function (first) {
			var spec = FOCAL[first];
			var input = edit.querySelector('input[type="number"][data-key="' + spec[0] + '"]');
			if (!input) {
				return;
			}
			var holder = input.closest('label');
			var pad = holder.parentNode.querySelector('.aimp-ds-focal[data-focal="' + spec[0] + '"]');
			if (!pad) {
				pad = document.createElement('div');
				pad.className = 'aimp-ds-focal';
				pad.setAttribute('data-focal', spec[0]);
				pad.innerHTML = '<span class="aimp-ds-focal-cross" aria-hidden="true"></span><span class="aimp-ds-focal-hint"></span>';
				pad.querySelector('.aimp-ds-focal-hint').textContent = t.focalHint;
				holder.parentNode.insertBefore(pad, holder);
				var set = function (e) {
					var box = pad.getBoundingClientRect();
					var fx = Math.max(0, Math.min(100, Math.round((e.clientX - box.left) / box.width * 100)));
					var fy = Math.max(0, Math.min(100, Math.round((e.clientY - box.top) / box.height * 100)));
					change(spec[0], fx);
					change(spec[1], fy);
					placeCross(pad, fx, fy);
					edit.querySelectorAll('[data-key="' + spec[0] + '"]').forEach(function (i) {
						i.value = fx;
					});
					edit.querySelectorAll('[data-key="' + spec[1] + '"]').forEach(function (i) {
						i.value = fy;
					});
				};
				var down = false;
				pad.addEventListener('mousedown', function (e) {
					down = true;
					remember();
					set(e);
					e.preventDefault();
				});
				window.addEventListener('mousemove', function (e) {
					if (down) {
						set(e);
					}
				});
				window.addEventListener('mouseup', function () {
					down = false;
				});
			}
			var row = draft[current] || {};
			var url = focalPicture(spec[2]);
			pad.style.backgroundImage = url ? 'url("' + url.replace(/"/g, '%22') + '")' : '';
			pad.classList.toggle('is-empty', !url);
			placeCross(pad, row[spec[0]] !== undefined ? row[spec[0]] : spec[3], row[spec[1]] !== undefined ? row[spec[1]] : spec[4]);
		});
	}
	function placeCross(pad, fx, fy) {
		var cross = pad.querySelector('.aimp-ds-focal-cross');
		cross.style.left = fx + '%';
		cross.style.top = fy + '%';
	}

	/* ---------------- undo and redo ---------------- */

	var undoStack = [];
	var redoStack = [];
	var lastSnap = 0;
	function snapshot() {
		return JSON.stringify({ d: draft, x: draftTexts, s: draftSettings });
	}
	// Before a change: what it was (one step per burst of changes, e.g. dragging a slider).
	function remember() {
		var now = Date.now();
		if (now - lastSnap > 800) {
			undoStack.push(snapshot());
			if (undoStack.length > 60) {
				undoStack.shift();
			}
			redoStack = [];
		}
		lastSnap = now;
		updateUndo();
	}
	function restore(snap) {
		var s = JSON.parse(snap);
		var textsDiffer = JSON.stringify(s.x) !== JSON.stringify(draftTexts) || JSON.stringify(s.s || {}) !== JSON.stringify(draftSettings);
		draft = s.d;
		draftTexts = s.x;
		draftSettings = s.s || {};
		Object.keys(draft).forEach(function (id) {
			if (/^el_[a-f0-9]{8}$/.test(id) && draft[id].sel && !cfg.elements[id]) {
				registerOwn(id, draft[id].sel, draft[id].name);
			}
		});
		lastSnap = 0;
		showEdit();
		renderList();
		sendCss();
		save(textsDiffer);
		updateUndo();
	}
	function undo() {
		if (undoStack.length) {
			redoStack.push(snapshot());
			restore(undoStack.pop());
		}
	}
	function redo() {
		if (redoStack.length) {
			undoStack.push(snapshot());
			restore(redoStack.pop());
		}
	}
	var undoButton = document.createElement('button');
	undoButton.type = 'button';
	undoButton.className = 'button aimp-ds-undo';
	undoButton.textContent = '↶ ' + t.undo;
	undoButton.title = t.undo + ' (Ctrl+Z)';
	var redoButton = document.createElement('button');
	redoButton.type = 'button';
	redoButton.className = 'button aimp-ds-redo';
	redoButton.textContent = '↷ ' + t.redo;
	redoButton.title = t.redo + ' (Ctrl+Shift+Z)';
	q('.aimp-ds-status').parentNode.insertBefore(undoButton, q('.aimp-ds-status'));
	q('.aimp-ds-status').parentNode.insertBefore(redoButton, q('.aimp-ds-status'));
	undoButton.addEventListener('click', undo);
	redoButton.addEventListener('click', redo);
	function updateUndo() {
		undoButton.disabled = !undoStack.length;
		redoButton.disabled = !redoStack.length;
	}
	updateUndo();
	document.addEventListener('keydown', function (e) {
		if (!(e.ctrlKey || e.metaKey) || /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) {
			return;
		}
		var key = e.key.toLowerCase();
		if (key === 'z' || key === 'y') {
			e.preventDefault();
			if (key === 'y' || e.shiftKey) {
				redo();
			} else {
				undo();
			}
		}
	});

	// "Change instead": what was clicked and what is around it, as buttons (outermost first).
	function renderPath() {
		pathBox.innerHTML = '';
		var el = cfg.elements[current];
		ownNote.hidden = !(el && el.own);
		if (lastPath.length < 2) {
			pathBox.hidden = true;
			return;
		}
		pathBox.hidden = false;
		var title = document.createElement('span');
		title.className = 'aimp-ds-path-title';
		title.textContent = t.pathTitle;
		pathBox.appendChild(title);
		lastPath.slice().reverse().forEach(function (entry, i) {
			var chip = document.createElement('button');
			chip.type = 'button';
			chip.className = 'aimp-ds-chip';
			var on = entry.part ? entry.part === current : !!(el && el.own && el.selector === entry.sel);
			chip.classList.toggle('is-current', on);
			chip.textContent = entry.part && cfg.elements[entry.part] ? cfg.elements[entry.part].label : entry.name;
			// Pointing at a chip outlines that element in the preview.
			var index = lastPath.length - 1 - i;
			chip.addEventListener('mouseenter', function () {
				toFrame({ type: 'aimp-design-peek', index: index, label: chip.textContent });
			});
			chip.addEventListener('mouseleave', function () {
				toFrame({ type: 'aimp-design-peek' });
			});
			chip.addEventListener('click', function () {
				var id = entry.part && cfg.elements[entry.part] ? entry.part : ownFor(entry);
				select(id, false, currentText, currentMiss);
			});
			pathBox.appendChild(chip);
		});
	}

	// A tab of the chosen part: its blocks, the first one open.
	function setTab(name) {
		activeTab = name;
		partTabs.querySelectorAll('button').forEach(function (button) {
			var on = button.getAttribute('data-ptab') === name;
			button.classList.toggle('is-active', on);
			button.setAttribute('aria-selected', on ? 'true' : 'false');
		});
		var first = null;
		edit.querySelectorAll('.aimp-ds-group').forEach(function (group) {
			var off = group.getAttribute('data-tab') !== name;
			group.classList.toggle('is-off-tab', off);
			group.classList.remove('is-open');
			if (!off && !group.hidden && !first) {
				first = group;
			}
		});
		if (first) {
			first.classList.add('is-open');
		}
		// "Computer or phone" only when this tab has settings for one screen.
		var note = q('.aimp-ds-device-note');
		if (note) {
			note.hidden = !edit.querySelector('.aimp-ds-group:not(.is-off-tab):not([hidden]) [data-device]');
		}
	}

	/* ---------------- texts of the Atelier Irisee settings ---------------- */

	var LANGS = ['nl', 'fr', 'en'];
	// The settings texts of the chosen part (cfg.settingTexts: id, label, type, parts, key, hints).
	function partSettings() {
		return (cfg.settingTexts || []).filter(function (field) {
			return field.parts.indexOf(current) !== -1;
		});
	}
	function settingKeys(field) {
		return field.type === 'lang' || field.type === 'langlong' ? LANGS.map(function (code) {
			return field.key + '_' + code;
		}) : [field.key];
	}
	function settingValue(key) {
		return draftSettings[key] !== undefined ? draftSettings[key] : (liveSettings[key] || '');
	}
	function setSetting(key, value) {
		remember();
		if (value === (liveSettings[key] || '')) {
			delete draftSettings[key];
		} else {
			draftSettings[key] = value;
		}
		// Made on the server: the preview loads again once you stop typing.
		save(true);
		markGroups();
	}
	// "Translate to the empty languages": the first filled field (Dutch first) translated into the empty ones, with the
	// AI pictures key (server: aimp_design_translate). The fields get an input event, so they are saved as draft.
	function translateButton(fields) {
		var wrap = document.createElement('div');
		wrap.className = 'aimp-ds-translate';
		var button = document.createElement('button');
		button.type = 'button';
		button.className = 'button-link';
		button.textContent = '🌐 ' + t.translate;
		var note = document.createElement('span');
		note.className = 'aimp-ds-translate-note';
		note.setAttribute('aria-live', 'polite');
		button.addEventListener('click', function () {
			var source = fields.filter(function (f) {
				return f.el.value.trim() !== '';
			})[0];
			var empty = fields.filter(function (f) {
				return f.el.value.trim() === '';
			});
			if (!source || !empty.length) {
				note.textContent = source ? t.translateFull : t.translateNothing;
				return;
			}
			button.disabled = true;
			note.textContent = t.translating;
			post('translate', { text: source.el.value, from: source.code, to: empty.map(function (f) {
				return f.code;
			}).join(',') }).then(function (data) {
				empty.forEach(function (f) {
					if (data && data[f.code] && f.el.value.trim() === '') {
						f.el.value = data[f.code];
						f.el.dispatchEvent(new Event('input', { bubbles: true }));
					}
				});
				note.textContent = '';
			}).catch(function (e) {
				note.textContent = e.message;
			}).then(function () {
				button.disabled = false;
			});
		});
		wrap.appendChild(button);
		wrap.appendChild(note);
		return wrap;
	}

	// "NL | FR | EN" in three fields (a missing language: empty). Long: text boxes, kept on one line (each item of a
	// list is one line).
	function trioFields(value, onChange, long) {
		var parts = String(value || '').split('|').map(function (part) {
			return part.trim();
		});
		var box = document.createElement('div');
		box.className = 'aimp-ds-trio' + (long ? ' is-long' : '');
		var inputs = LANGS.map(function (code, i) {
			var label = document.createElement('label');
			var tag = document.createElement('span');
			tag.textContent = code.toUpperCase();
			var input = document.createElement(long ? 'textarea' : 'input');
			if (long) {
				input.rows = 2;
			} else {
				input.type = 'text';
			}
			input.value = parts.length > 1 ? (parts[i] || '') : (i === 0 ? parts[0] : '');
			label.appendChild(tag);
			label.appendChild(input);
			box.appendChild(label);
			return input;
		});
		inputs.forEach(function (input) {
			input.addEventListener('input', function () {
				var values = inputs.map(function (other) {
					return other.value.replace(/[\r\n|]+/g, ' ').trim();
				});
				onChange(values[1] || values[2] ? values.join(' | ') : values[0]);
			});
		});
		box.appendChild(translateButton(LANGS.map(function (code, i) {
			return { code: code, el: inputs[i] };
		})));
		return box;
	}
	function renderSettingsGroup() {
		var body = settingsGroup.querySelector('.aimp-ds-textgroup-body');
		body.innerHTML = '';
		var fields = partSettings();
		fields.forEach(function (field) {
			var wrap = document.createElement('div');
			wrap.className = 'aimp-ds-setting';
			var title = document.createElement('p');
			title.className = 'aimp-ds-setting-title';
			title.textContent = field.label;
			// Back to standard: the draft of this text goes (the published or standard wording again).
			wrap.setAttribute('data-setting-keys', settingKeys(field).join(','));
			var undoText = document.createElement('button');
			undoText.type = 'button';
			undoText.className = 'aimp-ds-reset';
			undoText.textContent = '↺';
			undoText.title = t.resetOne;
			undoText.setAttribute('aria-label', t.resetOne + ': ' + field.label);
			undoText.addEventListener('click', function () {
				remember();
				settingKeys(field).forEach(function (key) {
					delete draftSettings[key];
				});
				save(true);
				renderSettingsGroup();
				markGroups();
			});
			title.appendChild(undoText);
			wrap.appendChild(title);
			if (field.type === 'lang' || field.type === 'langlong') {
				var langFields = [];
				LANGS.forEach(function (code) {
					var key = field.key + '_' + code;
					var label = document.createElement('label');
					var tag = document.createElement('span');
					tag.textContent = code.toUpperCase();
					var input = document.createElement(field.type === 'langlong' ? 'textarea' : 'input');
					if (field.type === 'langlong') {
						input.rows = 3;
					} else {
						input.type = 'text';
					}
					input.value = settingValue(key);
					input.placeholder = field.hints && field.hints[code] ? field.hints[code] : '';
					input.addEventListener('input', function () {
						setSetting(key, input.value);
					});
					langFields.push({ code: code, el: input });
					label.appendChild(tag);
					label.appendChild(input);
					wrap.appendChild(label);
				});
				wrap.appendChild(translateButton(langFields));
			} else if (field.type === 'trio') {
				wrap.appendChild(trioFields(settingValue(field.key), function (value) {
					setSetting(field.key, value);
				}));
			} else if (field.type === 'lines' || field.type === 'pairs') {
				// One item per line, each in three languages; items can be added and removed. Pairs: two texts per
				// item ("… || …"), e.g. a question and its answer.
				var pairs = field.type === 'pairs';
				var sub = field.sub || [['', false], ['', false]];
				var lines = settingValue(field.key).split(/\r\n|\r|\n/).filter(function (line) {
					return line.trim() !== '';
				});
				var list = document.createElement('div');
				var store = function () {
					setSetting(field.key, lines.filter(function (line) {
						return line.replace(/\|/g, '').trim() !== '';
					}).join('\n'));
				};
				var draw = function () {
					list.innerHTML = '';
					lines.forEach(function (line, i) {
						var item = document.createElement('div');
						item.className = 'aimp-ds-line';
						var head = document.createElement('div');
						head.className = 'aimp-ds-line-head';
						head.textContent = '#' + (i + 1);
						var remove = document.createElement('button');
						remove.type = 'button';
						remove.className = 'button-link aimp-ds-line-remove';
						remove.textContent = t.removeItem;
						remove.addEventListener('click', function () {
							lines.splice(i, 1);
							store();
							draw();
						});
						head.appendChild(remove);
						item.appendChild(head);
						if (!pairs) {
							item.appendChild(trioFields(line, function (value) {
								lines[i] = value;
								store();
							}));
							list.appendChild(item);
							return;
						}
						var halves = line.split('||');
						halves[1] = halves[1] || '';
						[0, 1].forEach(function (n) {
							var name = document.createElement('p');
							name.className = 'aimp-ds-line-sub';
							name.textContent = sub[n][0];
							item.appendChild(name);
							item.appendChild(trioFields(halves[n].trim(), function (value) {
								halves[n] = value;
								lines[i] = halves[0].trim() + ' || ' + halves[1].trim();
								store();
							}, sub[n][1]));
						});
						list.appendChild(item);
					});
				};
				draw();
				wrap.appendChild(list);
				var add = document.createElement('button');
				add.type = 'button';
				add.className = 'button';
				add.textContent = '+ ' + t.addItem;
				add.addEventListener('click', function () {
					lines.push('');
					draw();
				});
				wrap.appendChild(add);
			} else {
				var label = document.createElement('label');
				label.className = 'aimp-ds-setting-single';
				var input = document.createElement(field.type === 'long' ? 'textarea' : 'input');
				if (field.type === 'long') {
					input.rows = 3;
				} else {
					input.type = 'text';
				}
				input.value = settingValue(field.key);
				input.addEventListener('input', function () {
					setSetting(field.key, input.value);
				});
				label.appendChild(input);
				wrap.appendChild(label);
			}
			body.appendChild(wrap);
		});
		if (fields.length) {
			var help = document.createElement('p');
			help.className = 'description';
			help.textContent = t.settingsHelp;
			body.appendChild(help);
		}
		return fields.length > 0;
	}

	// The clicked shop text: its words in Dutch, French and English, nothing else.
	function renderTextGroup() {
		var body = textGroup.querySelector('.aimp-ds-textgroup-body');
		body.innerHTML = '';
		if (currentMiss && !currentText) {
			var miss = document.createElement('p');
			miss.className = 'description';
			miss.textContent = t.textMiss;
			body.appendChild(miss);
			if (currentPost) {
				var editLink = document.createElement('a');
				editLink.className = 'button';
				editLink.href = cfg.editPost.replace('%d', currentPost);
				editLink.target = '_blank';
				editLink.rel = 'noopener';
				editLink.textContent = t.editPost;
				body.appendChild(editLink);
			}
			if (currentDivi && frameUrl) {
				var builder = new URL(frameUrl);
				builder.searchParams.delete(cfg.param);
				builder.searchParams.set('et_fb', '1');
				var diviLink = document.createElement('a');
				diviLink.className = 'button';
				diviLink.href = builder.toString();
				diviLink.target = '_blank';
				diviLink.rel = 'noopener';
				diviLink.textContent = t.diviBuilder;
				body.appendChild(diviLink);
			}
			return;
		}
		if (!currentText) {
			return;
		}
		var asked = currentText.original;
		loadTexts().then(function () {
			if (!currentText || currentText.original !== asked) {
				return;
			}
			var row = null;
			(texts || []).forEach(function (r) {
				if (r[0] === asked) {
					row = r;
				}
			});
			body.appendChild(textEditor(row || [asked, '', '']));
			var help = document.createElement('p');
			help.className = 'description';
			help.textContent = t.textHelp;
			body.appendChild(help);
		});
	}

	// A dot on the title of a folded block when one of its settings is changed.
	function markGroups() {
		markResets();
		var row = draft[current] || {};
		var textToggle = textGroup.querySelector('.aimp-ds-group-toggle');
		textToggle.classList.toggle('is-changed', !!(currentText && draftTexts[currentText.original]));
		settingsGroup.querySelector('.aimp-ds-group-toggle').classList.toggle('is-changed', partSettings().some(function (field) {
			return settingKeys(field).some(function (key) {
				return draftSettings[key] !== undefined;
			});
		}));
		edit.querySelectorAll('.aimp-ds-group[data-group]').forEach(function (group) {
			var fields = cfg.groups[group.getAttribute('data-group')] || {};
			var changed = Object.keys(fields).some(function (key) {
				return row[key] !== undefined;
			});
			var toggle = group.querySelector('.aimp-ds-group-toggle');
			if (toggle) {
				toggle.classList.toggle('is-changed', changed);
			}
		});
	}

	// Whether a setting is made on the server when the page is built (the preview then loads again).
	function reloads(key) {
		if (cfg.reloadKeys) {
			return cfg.reloadKeys.indexOf(key) !== -1;
		}
		var found = false;
		(cfg.reload || []).forEach(function (group) {
			if (cfg.groups[group] && cfg.groups[group][key] !== undefined) {
				found = true;
			}
		});
		return found;
	}

	edit.addEventListener('input', function (e) {
		var input = e.target;
		var key = input.getAttribute('data-key');
		if (!key) {
			return;
		}
		if (input.type === 'checkbox') {
			change(key, input.checked ? 1 : false);
		} else if (input.tagName === 'SELECT') {
			change(key, input.value);
		} else if (input.type === 'text' || input.type === 'url' || input.tagName === 'TEXTAREA') {
			change(key, input.value.trim());
		} else if (input.type === 'color') {
			input.closest('.aimp-ds-color').classList.add('is-set');
			change(key, input.value);
		} else {
			var n = parseInt(input.value, 10);
			if (isNaN(n)) {
				var standard = neutral(key);
				if (input.value === '' && standard !== null && standard < parseInt(input.min, 10)) {
					change(key, '');
				}
				return;
			}
			n = Math.max(parseInt(input.min, 10), Math.min(parseInt(input.max, 10), n));
			// The slider and the number box of a setting move together.
			edit.querySelectorAll('[data-key="' + key + '"]').forEach(function (other) {
				if (other !== input) {
					other.value = n;
				}
			});
			change(key, n);
		}
	});

	// An order of blocks: dragged, or moved with the arrows (keyboard).
	function orderChanged(list) {
		var keys = [];
		var standard = true;
		Array.prototype.forEach.call(list.children, function (li, i) {
			keys.push(li.getAttribute('data-block'));
			if (parseInt(li.getAttribute('data-std'), 10) !== i) {
				standard = false;
			}
		});
		change(list.getAttribute('data-order'), standard ? '' : keys.join(','));
	}
	// Spacing: with "the same on all sides" on, a value goes to the four sides of that box.
	edit.addEventListener('input', function (e) {
		var input = e.target;
		var model = input.closest('.aimp-ds-boxmodel');
		var area = input.closest('[data-bm]');
		if (!model || !area || input.type !== 'number') {
			return;
		}
		var kind = area.getAttribute('data-bm');
		var link = model.querySelector('.aimp-ds-bm-link[data-link="' + kind + '"]');
		if (!link || !link.checked) {
			return;
		}
		Array.prototype.forEach.call(area.querySelectorAll(':scope > input[type="number"]'), function (other) {
			if (other !== input) {
				other.value = input.value;
				var n = parseInt(input.value, 10);
				change(other.getAttribute('data-key'), isNaN(n) ? '' : n);
			}
		});
	});

	edit.addEventListener('click', function (e) {
		var move = e.target.closest('[data-move]');
		if (!move) {
			return;
		}
		var li = move.closest('li');
		var list = li.parentNode;
		if (move.getAttribute('data-move') === '-1') {
			if (li.previousElementSibling) {
				list.insertBefore(li, li.previousElementSibling);
			}
		} else if (li.nextElementSibling) {
			list.insertBefore(li.nextElementSibling, li);
		}
		move.focus();
		orderChanged(list);
	});
	var dragged = null;
	edit.addEventListener('dragstart', function (e) {
		var li = e.target.closest ? e.target.closest('.aimp-ds-order li') : null;
		if (!li) {
			return;
		}
		dragged = li;
		li.classList.add('is-dragging');
		e.dataTransfer.effectAllowed = 'move';
		try {
			e.dataTransfer.setData('text/plain', li.getAttribute('data-block'));
		} catch (err) {}
	});
	edit.addEventListener('dragover', function (e) {
		var li = e.target.closest ? e.target.closest('.aimp-ds-order li') : null;
		if (!dragged || !li || li.parentNode !== dragged.parentNode) {
			return;
		}
		e.preventDefault();
		if (li !== dragged) {
			var box = li.getBoundingClientRect();
			li.parentNode.insertBefore(dragged, e.clientY > box.top + box.height / 2 ? li.nextSibling : li);
		}
	});
	edit.addEventListener('drop', function (e) {
		if (dragged) {
			e.preventDefault();
		}
	});
	edit.addEventListener('dragend', function () {
		if (!dragged) {
			return;
		}
		var list = dragged.parentNode;
		dragged.classList.remove('is-dragging');
		dragged = null;
		orderChanged(list);
	});

	edit.addEventListener('click', function (e) {
		var clear = e.target.closest('[data-clear]');
		if (clear) {
			clear.getAttribute('data-clear').split(',').forEach(function (key) {
				change(key, '');
			});
			showEdit();
		}
	});

	// A picture from the media library (a part's picture, the page background, the browser icon), a video, or
	// several pictures (hero slides).
	var mediaFrames = {};
	var mediaKey = '';
	function mediaUrl(file) {
		return file.sizes && file.sizes.large ? file.sizes.large.url : file.url;
	}
	edit.addEventListener('click', function (e) {
		var button = e.target.closest('.aimp-ds-image-choose');
		if (!button || !window.wp || !window.wp.media || !current) {
			return;
		}
		mediaKey = button.getAttribute('data-image-key');
		var kind = button.getAttribute('data-media') || 'image';
		if (!mediaFrames[kind]) {
			var frame = window.wp.media({
				title: t.choose,
				button: { text: t.use },
				library: { type: kind === 'video' ? 'video' : 'image' },
				multiple: kind === 'images' ? 'add' : false
			});
			frame.on('select', function () {
				var chosen = frame.state().get('selection').toJSON();
				if (kind === 'images') {
					change(mediaKey, chosen.slice(0, 6).map(function (file) {
						images[file.id] = mediaUrl(file);
						return file.id;
					}));
				} else if (chosen[0]) {
					if (kind === 'video') {
						files[chosen[0].id] = chosen[0].filename;
					} else {
						images[chosen[0].id] = mediaUrl(chosen[0]);
					}
					change(mediaKey, chosen[0].id);
				}
				showEdit();
			});
			mediaFrames[kind] = frame;
		}
		mediaFrames[kind].open();
	});

	q('.aimp-ds-reset-one').addEventListener('click', function () {
		if (current) {
			remember();
			delete draft[current];
			showEdit();
			sendCss();
			save();
			renderList();
		}
	});

	/* ---------------- own texts ---------------- */

	var texts = null;
	var textBox = q('.aimp-ds-texts');
	var textSearch = q('.aimp-ds-text-search');
	var textOwn = q('.aimp-ds-text-own');
	var textCount = q('.aimp-ds-text-count');
	var textTimer = 0;

	function codes(text) {
		return (String(text).match(/%(?:\d+\$)?[sdfu]/g) || []).sort();
	}

	var textsLoading = null;
	function loadTexts() {
		if (!textsLoading) {
			textCount.textContent = t.loading;
			textsLoading = post('texts').then(function (data) {
				texts = data;
				renderTexts();
			}).catch(function (e) {
				textsLoading = null;
				textCount.textContent = e.message;
			});
		}
		return textsLoading;
	}

	function renderTexts() {
		if (!texts || !texts.length) {
			return;
		}
		var term = textSearch.value.trim().toLowerCase();
		var found = texts.filter(function (row) {
			var own = draftTexts[row[0]];
			if (textOwn.checked && !own) {
				return false;
			}
			// Only the texts of the page in the preview (unless searching).
			if (textPage.checked && pageTexts && !term && pageTexts.indexOf(row[0]) === -1) {
				return false;
			}
			if (!term) {
				return true;
			}
			var hay = (row[0] + ' ' + row[1] + ' ' + row[2] + ' ' + (own ? (own.nl || '') + ' ' + (own.fr || '') + ' ' + (own.en || '') : '')).toLowerCase();
			return hay.indexOf(term) !== -1;
		});
		var shown = found.slice(0, 40);
		textCount.textContent = fmt(t.textsShown, shown.length, found.length);
		textBox.innerHTML = '';
		shown.forEach(function (row) {
			textBox.appendChild(textEditor(row));
		});
	}

	// One text with a field per language (empty = the standard text).
	function textEditor(row) {
		var original = row[0];
		var item = document.createElement('div');
			item.className = 'aimp-ds-text' + (draftTexts[original] ? ' is-own' : '');
			var head = document.createElement('p');
			head.className = 'aimp-ds-text-original';
			head.textContent = original;
			item.appendChild(head);
			var need = codes(original);
			if (need.length) {
				var hint = document.createElement('p');
				hint.className = 'aimp-ds-text-codes';
				hint.textContent = t.keepCodes + ' ' + need.join(' ');
				item.appendChild(hint);
			}
			var textFields = [];
			[['nl', row[1]], ['fr', row[2]], ['en', original]].forEach(function (pair) {
				var lang = pair[0];
				var label = document.createElement('label');
				var tag = document.createElement('span');
				tag.textContent = lang.toUpperCase();
				var input = document.createElement('textarea');
				input.rows = 1;
				input.placeholder = pair[1];
				input.value = draftTexts[original] && draftTexts[original][lang] ? draftTexts[original][lang] : '';
				input.addEventListener('input', function () {
					remember();
					var value = input.value;
					var ok = value.trim() === '' || codes(value).join() === need.join();
					input.classList.toggle('is-wrong', !ok);
					var own = draftTexts[original] || {};
					if (value.trim() === '' || !ok) {
						delete own[lang];
					} else {
						own[lang] = value;
					}
					if (Object.keys(own).length) {
						draftTexts[original] = own;
					} else {
						delete draftTexts[original];
					}
					item.classList.toggle('is-own', !!draftTexts[original]);
					save(true);
				});
				textFields.push({ code: lang, el: input });
				label.appendChild(tag);
				label.appendChild(input);
				item.appendChild(label);
			});
			item.appendChild(translateButton(textFields));
		return item;
	}

	textSearch.addEventListener('input', function () {
		window.clearTimeout(textTimer);
		textTimer = window.setTimeout(renderTexts, 200);
	});
	textOwn.addEventListener('change', renderTexts);
	// "Only the texts on this page" (on unless searching).
	var textPageLabel = document.createElement('label');
	textPageLabel.className = 'aimp-ds-check';
	var textPage = document.createElement('input');
	textPage.type = 'checkbox';
	textPage.checked = true;
	textPageLabel.appendChild(textPage);
	textPageLabel.appendChild(document.createTextNode(' ' + t.textsOnPage));
	textOwn.closest('label').parentNode.insertBefore(textPageLabel, textOwn.closest('label'));
	textPage.addEventListener('change', renderTexts);

	/* ---------------- confirm by clicking twice ---------------- */

	function twice(button, action) {
		button.addEventListener('click', function () {
			if (!button.classList.contains('is-confirming')) {
				button.classList.add('is-confirming');
				button.dataset.label = button.textContent;
				button.textContent = t.confirm;
				window.setTimeout(function () {
					if (button.classList.contains('is-confirming')) {
						button.classList.remove('is-confirming');
						button.textContent = button.dataset.label;
					}
				}, 4000);
				return;
			}
			button.classList.remove('is-confirming');
			button.textContent = button.dataset.label;
			action();
		});
	}

	twice(q('.aimp-ds-publish'), function () {
		window.clearTimeout(saveTimer);
		showStatus(t.saving);
		post('publish', { styles: JSON.stringify(draft), texts: JSON.stringify(draftTexts) }).then(function (data) {
			setState(data);
			reloadPreview();
		}).catch(function (e) {
			showStatus(e.message);
		});
	});

	twice(q('.aimp-ds-discard'), function () {
		window.clearTimeout(saveTimer);
		post('discard').then(function (data) {
			setState(data);
			reloadPreview();
		}).catch(function (e) {
			showStatus(e.message);
		});
	});

	twice(q('.aimp-ds-reset-all'), function () {
		draft = {};
		showEdit();
		sendCss();
		save();
		renderList();
	});

	/* ---------------- saved looks ---------------- */

	var lookList = q('.aimp-ds-looks');
	var lookName = q('.aimp-ds-look-name');

	function renderLooks() {
		lookList.innerHTML = '';
		if (!looks.length) {
			var empty = document.createElement('li');
			empty.textContent = t.noLooks;
			lookList.appendChild(empty);
			return;
		}
		looks.forEach(function (look) {
			var li = document.createElement('li');
			li.className = 'aimp-ds-look' + (look.on ? ' is-on' : '');
			var title = document.createElement('strong');
			title.textContent = look.name;
			var info = document.createElement('span');
			info.textContent = ' · ' + fmt(t.parts, look.count) + (look.on ? ' · ' + t.onNow : (look.from ? ' · ' + t.planned + ' ' + look.from + ' – ' + look.to : ''));
			var row = document.createElement('p');
			var from = document.createElement('input');
			from.type = 'date';
			from.value = look.from;
			from.setAttribute('aria-label', t.from);
			var to = document.createElement('input');
			to.type = 'date';
			to.value = look.to;
			to.setAttribute('aria-label', t.to);
			var plan = document.createElement('button');
			plan.type = 'button';
			plan.className = 'button';
			plan.textContent = t.plan;
			plan.addEventListener('click', function () {
				if ((from.value || to.value) && (!from.value || !to.value || to.value < from.value)) {
					showStatus(t.datesNeeded);
					return;
				}
				post('look_plan', { id: look.id, from: from.value, to: to.value }).then(function (data) {
					setState(data, true);
				}).catch(function (e) {
					showStatus(e.message);
				});
			});
			var load = document.createElement('button');
			load.type = 'button';
			load.className = 'button-link';
			load.textContent = t.load;
			load.addEventListener('click', function () {
				post('look_load', { id: look.id }).then(function (data) {
					setState(data);
					reloadPreview();
					showStatus(t.lookLoaded);
				}).catch(function (e) {
					showStatus(e.message);
				});
			});
			var del = document.createElement('button');
			del.type = 'button';
			del.className = 'button-link aimp-ds-danger';
			del.textContent = t.delete;
			twice(del, function () {
				post('look_delete', { id: look.id }).then(function (data) {
					setState(data, true);
				}).catch(function (e) {
					showStatus(e.message);
				});
			});
			row.appendChild(from);
			row.appendChild(to);
			row.appendChild(plan);
			li.appendChild(title);
			li.appendChild(info);
			li.appendChild(row);
			var actions = document.createElement('p');
			actions.appendChild(load);
			actions.appendChild(del);
			li.appendChild(actions);
			lookList.appendChild(li);
		});
	}

	q('.aimp-ds-look-save').addEventListener('click', function () {
		var name = lookName.value.trim();
		if (!name) {
			showStatus(t.nameNeeded);
			lookName.focus();
			return;
		}
		window.clearTimeout(saveTimer);
		// The draft as it is now is saved first, then kept as a look.
		post('save', { styles: JSON.stringify(draft), texts: JSON.stringify(draftTexts) }).then(function () {
			return post('look_save', { name: name });
		}).then(function (data) {
			lookName.value = '';
			setState(data, true);
			showStatus(t.lookSaved);
		}).catch(function (e) {
			showStatus(e.message);
		});
	});

	/* ---------------- history and backup ---------------- */

	var historyList = q('.aimp-ds-history');

	function renderHistory() {
		historyList.innerHTML = '';
		if (!history.length) {
			var li = document.createElement('li');
			li.textContent = t.noHistory;
			historyList.appendChild(li);
			return;
		}
		history.forEach(function (item) {
			var li = document.createElement('li');
			var text = document.createElement('span');
			text.textContent = item.when + (item.who ? ' · ' + item.who : '') + ' · ' + fmt(t.parts, item.count);
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'button-link';
			b.textContent = t.restore;
			b.addEventListener('click', function () {
				window.clearTimeout(saveTimer);
				post('restore', { index: item.index }).then(function (data) {
					setState(data);
					reloadPreview();
					showStatus(t.restored);
				}).catch(function (e) {
					showStatus(e.message);
				});
			});
			li.appendChild(text);
			li.appendChild(b);
			historyList.appendChild(li);
		});
	}

	q('.aimp-ds-export').addEventListener('click', function () {
		var blob = new Blob([JSON.stringify({ aimpDesign: 1, styles: draft, texts: draftTexts }, null, 2)], { type: 'application/json' });
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = 'atelier-irisee-design-' + new Date().toISOString().slice(0, 10) + '.json';
		document.body.appendChild(a);
		a.click();
		window.setTimeout(function () {
			URL.revokeObjectURL(a.href);
			a.remove();
		}, 1000);
	});

	q('.aimp-ds-import input').addEventListener('change', function () {
		var file = this.files && this.files[0];
		var input = this;
		if (!file) {
			return;
		}
		file.text().then(function (text) {
			var json = JSON.parse(text);
			if (!json || json.aimpDesign !== 1 || typeof json.styles !== 'object') {
				throw new Error(t.badFile);
			}
			return post('import', { styles: JSON.stringify(json.styles), texts: JSON.stringify(json.texts || {}) });
		}).then(function (data) {
			setState(data);
			reloadPreview();
			showStatus(t.imported);
		}).catch(function (e) {
			showStatus(e instanceof SyntaxError ? t.badFile : e.message);
		}).then(function () {
			input.value = '';
		});
	});

	/* ---------------- folding blocks, computer or phone ---------------- */

	// Every block of settings folds open with its title; one block at a time.
	edit.querySelectorAll('.aimp-ds-group').forEach(function (group) {
		var legend = group.querySelector('legend');
		if (!legend) {
			return;
		}
		var toggle = document.createElement('button');
		toggle.type = 'button';
		toggle.className = 'aimp-ds-group-toggle';
		toggle.textContent = legend.textContent;
		legend.textContent = '';
		legend.appendChild(toggle);
		toggle.addEventListener('click', function () {
			var open = !group.classList.contains('is-open');
			edit.querySelectorAll('.aimp-ds-group.is-open').forEach(function (other) {
				other.classList.remove('is-open');
			});
			group.classList.toggle('is-open', open);
			// The opened block at the top of the panel (only the panel scrolls, not the page).
			if (open) {
				var panel = q('.aimp-ds-panel');
				panel.scrollTop += group.getBoundingClientRect().top - panel.getBoundingClientRect().top - 8;
			}
		});
		// Settings under a "Computer" or "Phone" heading belong to that screen.
		var mode = '';
		Array.prototype.forEach.call(group.children, function (child) {
			if (child.classList.contains('aimp-ds-sub')) {
				mode = child.getAttribute('data-device-sub') || '';
			}
			if (mode && child.tagName !== 'LEGEND') {
				child.setAttribute('data-device', mode);
			}
		});
	});
	var partTabs = document.createElement('div');
	partTabs.className = 'aimp-ds-ptabs';
	partTabs.setAttribute('role', 'tablist');
	[['content', t.tabContent], ['design', t.tabDesign], ['advanced', t.tabAdvanced]].forEach(function (pair) {
		var button = document.createElement('button');
		button.type = 'button';
		button.setAttribute('role', 'tab');
		button.setAttribute('data-ptab', pair[0]);
		button.textContent = pair[1];
		button.addEventListener('click', function () {
			setTab(pair[0]);
		});
		partTabs.appendChild(button);
	});
	edit.appendChild(partTabs);
	var textGroup = document.createElement('fieldset');
	textGroup.className = 'aimp-ds-group aimp-ds-textgroup';
	textGroup.setAttribute('data-tab', 'content');
	textGroup.innerHTML = '<legend><button type="button" class="aimp-ds-group-toggle"></button></legend><div class="aimp-ds-textgroup-body"></div>';
	textGroup.querySelector('.aimp-ds-group-toggle').textContent = t.textTitle;
	textGroup.querySelector('.aimp-ds-group-toggle').addEventListener('click', function () {
		var open = !textGroup.classList.contains('is-open');
		edit.querySelectorAll('.aimp-ds-group.is-open').forEach(function (other) {
			other.classList.remove('is-open');
		});
		textGroup.classList.toggle('is-open', open);
	});
	// The tabs right under the head, the blocks under the tabs.
	var pathBox = document.createElement('div');
	pathBox.className = 'aimp-ds-path';
	pathBox.hidden = true;
	q('.aimp-ds-edit-head').insertAdjacentElement('afterend', pathBox);
	var ownNote = document.createElement('p');
	ownNote.className = 'aimp-ds-own-note';
	ownNote.textContent = t.ownScope;
	ownNote.hidden = true;
	pathBox.insertAdjacentElement('afterend', ownNote);
	var settingsGroup = textGroup.cloneNode(true);
	settingsGroup.className = 'aimp-ds-group aimp-ds-textgroup aimp-ds-settingsgroup';
	settingsGroup.querySelector('.aimp-ds-group-toggle').textContent = t.settingsTitle;
	settingsGroup.querySelector('.aimp-ds-group-toggle').addEventListener('click', function () {
		var open = !settingsGroup.classList.contains('is-open');
		edit.querySelectorAll('.aimp-ds-group.is-open').forEach(function (other) {
			other.classList.remove('is-open');
		});
		settingsGroup.classList.toggle('is-open', open);
	});
	var groupsHome = document.createElement('div');
	groupsHome.className = 'aimp-ds-groups';
	edit.appendChild(groupsHome);
	edit.insertBefore(partTabs, q('.aimp-ds-device-note') || groupsHome);
	edit.querySelectorAll('.aimp-ds-group').forEach(function (group) {
		groupsHome.appendChild(group);
	});
	var deviceNote = q('.aimp-ds-device-note');

	/* ---------------- finding your way: layers, breadcrumb, back, search, changed only ---------------- */

	// The parts of the page as a tree (from the preview: page order, inside each other). Returns the ids shown.
	function addLayers(container, ids) {
		var rows = layerRows.filter(function (row) {
			return ids.indexOf(row.id) !== -1;
		});
		var tree = document.createElement('div');
		tree.className = 'aimp-ds-layers';
		var hiddenBelow = -1;
		rows.forEach(function (row, i) {
			if (hiddenBelow !== -1 && row.depth > hiddenBelow) {
				return;
			}
			hiddenBelow = -1;
			var hasChildren = rows[i + 1] && rows[i + 1].depth > row.depth;
			var line = document.createElement('div');
			line.className = 'aimp-ds-layer';
			line.style.paddingLeft = (row.depth * 14) + 'px';
			var fold = document.createElement('button');
			fold.type = 'button';
			fold.className = 'aimp-ds-layer-fold';
			fold.textContent = hasChildren ? (foldedLayers[row.id] ? '▸' : '▾') : '';
			fold.disabled = !hasChildren;
			fold.setAttribute('aria-label', cfg.elements[row.id].label);
			fold.addEventListener('click', function () {
				foldedLayers[row.id] = !foldedLayers[row.id];
				renderList();
			});
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'aimp-ds-item' + (row.id === current ? ' is-current' : '') + (draft[row.id] ? ' is-changed' : '');
			b.textContent = cfg.elements[row.id].label;
			if (draft[row.id]) {
				var dot = document.createElement('span');
				dot.className = 'aimp-ds-dot';
				dot.title = t.changed;
				b.appendChild(dot);
			}
			b.addEventListener('click', function () {
				select(row.id, true);
			});
			// Pointing at a layer outlines that part in the preview.
			b.addEventListener('mouseenter', function () {
				toFrame({ type: 'aimp-design-peek', selector: cfg.elements[row.id].selector, label: cfg.elements[row.id].label });
			});
			b.addEventListener('mouseleave', function () {
				toFrame({ type: 'aimp-design-peek' });
			});
			line.appendChild(fold);
			line.appendChild(b);
			tree.appendChild(line);
			if (hasChildren && foldedLayers[row.id]) {
				hiddenBelow = row.depth;
			}
		});
		container.appendChild(tree);
		return rows.map(function (row) {
			return row.id;
		});
	}

	// Above the settings, staying in view: back, the breadcrumb, a search field and "only what I changed".
	var sticky = document.createElement('div');
	sticky.className = 'aimp-ds-sticky';
	var navBar = document.createElement('div');
	navBar.className = 'aimp-ds-nav';
	var backPart = document.createElement('button');
	backPart.type = 'button';
	backPart.className = 'button aimp-ds-back-part';
	backPart.textContent = '←';
	backPart.title = t.backPart;
	backPart.setAttribute('aria-label', t.backPart);
	backPart.addEventListener('click', function () {
		var id = partHistory.pop();
		if (id && cfg.elements[id]) {
			goingBack = true;
			select(id, true);
		}
	});
	var crumbs = document.createElement('nav');
	crumbs.className = 'aimp-ds-crumbs';
	navBar.appendChild(backPart);
	navBar.appendChild(crumbs);
	var finder = document.createElement('div');
	finder.className = 'aimp-ds-finder';
	var findInput = document.createElement('input');
	findInput.type = 'search';
	findInput.className = 'aimp-ds-find';
	findInput.placeholder = t.findSetting;
	var changedLabel = document.createElement('label');
	changedLabel.className = 'aimp-ds-check aimp-ds-only-changed';
	var changedBox = document.createElement('input');
	changedBox.type = 'checkbox';
	changedLabel.appendChild(changedBox);
	changedLabel.appendChild(document.createTextNode(' ' + t.onlyChanged));
	finder.appendChild(findInput);
	finder.appendChild(changedLabel);
	var findNone = document.createElement('p');
	findNone.className = 'description aimp-ds-find-none';
	findNone.textContent = t.noSetting;
	findNone.hidden = true;
	var head = q('.aimp-ds-edit-head');
	head.parentNode.insertBefore(sticky, head);
	sticky.appendChild(navBar);
	sticky.appendChild(head);
	sticky.appendChild(partTabs);
	sticky.appendChild(finder);
	groupsHome.parentNode.insertBefore(findNone, groupsHome);

	// Page › the parts around this one › this part (from the layers), else the part's group.
	function renderCrumbs() {
		if (!crumbs) {
			return;
		}
		backPart.disabled = !partHistory.length;
		crumbs.innerHTML = '';
		if (!current || !cfg.elements[current]) {
			return;
		}
		var chain = [];
		var rows = layerRows || [];
		var at = -1;
		rows.forEach(function (row, i) {
			if (row.id === current) {
				at = i;
			}
		});
		if (at !== -1) {
			var depth = rows[at].depth;
			for (var i = at - 1; i >= 0 && depth > 0; i--) {
				if (rows[i].depth < depth) {
					chain.unshift(rows[i].id);
					depth = rows[i].depth;
				}
			}
		}
		var first = document.createElement('span');
		first.textContent = at !== -1 && frameTitle ? frameTitle : cfg.elements[current].group;
		crumbs.appendChild(first);
		chain.forEach(function (id) {
			crumbs.appendChild(document.createTextNode(' › '));
			var link = document.createElement('button');
			link.type = 'button';
			link.className = 'button-link';
			link.textContent = cfg.elements[id].label;
			link.addEventListener('click', function () {
				select(id, true);
			});
			link.addEventListener('mouseenter', function () {
				toFrame({ type: 'aimp-design-peek', selector: cfg.elements[id].selector, label: cfg.elements[id].label });
			});
			link.addEventListener('mouseleave', function () {
				toFrame({ type: 'aimp-design-peek' });
			});
			crumbs.appendChild(link);
		});
		crumbs.appendChild(document.createTextNode(' › '));
		var here = document.createElement('strong');
		here.textContent = cfg.elements[current].label;
		crumbs.appendChild(here);
	}

	// One row of the settings: a slider, choice, tick box, text, colour, picture or order list (and the texts).
	var ROWS = '.aimp-ds-num, .aimp-ds-select, .aimp-ds-check, .aimp-ds-text-field, .aimp-ds-color, .aimp-ds-image, .aimp-ds-order-field, .aimp-ds-boxmodel, .aimp-ds-icon-row, .aimp-ds-setting';
	function rowKeys(rowEl) {
		if (rowEl.hasAttribute('data-setting-keys')) {
			return [];
		}
		var keys = [];
		rowEl.querySelectorAll('[data-key]').forEach(function (input) {
			keys.push(input.getAttribute('data-key'));
		});
		if (rowEl.hasAttribute('data-image')) {
			keys.push(rowEl.getAttribute('data-image'));
		}
		rowEl.querySelectorAll('[data-order]').forEach(function (orderList) {
			keys.push(orderList.getAttribute('data-order'));
		});
		return keys;
	}
	function rowChanged(rowEl, row) {
		if (rowEl.hasAttribute('data-setting-keys')) {
			return rowEl.getAttribute('data-setting-keys').split(',').some(function (key) {
				return draftSettings[key] !== undefined;
			});
		}
		return rowKeys(rowEl).some(function (key) {
			return row[key] !== undefined;
		});
	}

	// A ↺ on every setting (once: the settings are the same blocks for every part); shown when it is changed.
	edit.querySelectorAll('.aimp-ds-num, .aimp-ds-select, .aimp-ds-check, .aimp-ds-text-field, .aimp-ds-color, .aimp-ds-image, .aimp-ds-order-field').forEach(function (rowEl) {
		var keys = rowKeys(rowEl);
		if (!keys.length || rowEl.closest('.aimp-ds-boxmodel')) {
			return;
		}
		var reset = document.createElement('button');
		reset.type = 'button';
		reset.className = 'aimp-ds-reset';
		reset.textContent = '↺';
		reset.title = t.resetOne;
		reset.setAttribute('aria-label', t.resetOne);
		reset.setAttribute('data-clear', keys.filter(function (key, i) {
			return keys.indexOf(key) === i;
		}).join(','));
		reset.hidden = true;
		rowEl.appendChild(reset);
	});
	function markResets() {
		if (!changedBox) {
			return;
		}
		var row = draft[current] || {};
		edit.querySelectorAll('.aimp-ds-reset[data-clear]').forEach(function (reset) {
			reset.hidden = !reset.getAttribute('data-clear').split(',').some(function (key) {
				return row[key] !== undefined;
			});
		});
		settingsGroup.querySelectorAll('[data-setting-keys]').forEach(function (rowEl) {
			var changed = rowChanged(rowEl, row);
			rowEl.classList.toggle('is-changed', changed);
			var reset = rowEl.querySelector('.aimp-ds-reset');
			if (reset) {
				reset.hidden = !changed;
			}
		});
		if (changedBox.checked || findInput.value.trim()) {
			searchSettings();
		}
	}

	// Find a setting / only what I changed: the matching settings of this part, in every tab, their blocks open.
	function searchSettings() {
		if (!findInput) {
			return;
		}
		var term = findInput.value.trim().toLowerCase();
		var onlyChanged = changedBox.checked;
		var on = !!term || onlyChanged;
		var row = draft[current] || {};
		var was = edit.classList.contains('is-searching');
		edit.classList.toggle('is-searching', on);
		var any = false;
		edit.querySelectorAll('.aimp-ds-group').forEach(function (group) {
			group.classList.remove('is-search-hit', 'is-search-miss');
			group.querySelectorAll('.is-search-off').forEach(function (rowEl) {
				rowEl.classList.remove('is-search-off');
			});
			if (!on || group.hidden) {
				return;
			}
			var legend = group.querySelector('legend');
			var groupMatch = !!term && !!legend && legend.textContent.toLowerCase().indexOf(term) !== -1;
			var hit = false;
			group.querySelectorAll(ROWS).forEach(function (rowEl) {
				if (rowEl.parentNode.closest(ROWS)) {
					return;
				}
				var ok = (!term || groupMatch || rowEl.textContent.toLowerCase().indexOf(term) !== -1 || (rowEl.querySelector('input[placeholder]') && rowEl.querySelector('input[placeholder]').placeholder.toLowerCase().indexOf(term) !== -1)) &&
					(!onlyChanged || rowChanged(rowEl, row));
				rowEl.classList.toggle('is-search-off', !ok);
				hit = hit || ok;
			});
			group.classList.add(hit ? 'is-search-hit' : 'is-search-miss');
			group.classList.toggle('is-open', hit);
			any = any || hit;
		});
		findNone.hidden = !on || any;
		// Search cleared: the tab as it was.
		if (!on && was) {
			setTab(activeTab);
		}
	}
	findInput.addEventListener('input', searchSettings);
	changedBox.addEventListener('change', searchSettings);

	// Page header: only the settings of the page in the preview (they are per page); a note when it has none.
	function showPageBlocks() {
		var any = false;
		edit.querySelectorAll('[data-ph-page]').forEach(function (block) {
			var mine = String(block.getAttribute('data-ph-page')) === String(framePostId);
			block.classList.toggle('is-other-page', !mine);
			any = any || mine;
		});
		var none = edit.querySelector('.aimp-ds-ph-none');
		if (none) {
			none.classList.toggle('is-other-page', any);
		}
	}

	/* ---------------- the site's colours as one-click choices ---------------- */

	// The brand colours (Design studio → Brand colours): key in that part, CSS name, standard.
	var SITE_COLORS = [['gold', 'gold', '#b38f4f'], ['gold_text', 'gold-text', '#8a6a33'], ['brown', 'brown', '#613907'], ['success', 'success', '#2e6b3a'], ['error', 'error', '#a3352b']];
	function siteColor(key, fallback) {
		var row = draft['site-colors'] || {};
		return row[key] && /^#[0-9a-f]{3,6}$/i.test(row[key]) ? row[key] : fallback;
	}
	// The colour a value shows: hex as it is, a link to a site colour as that colour now.
	function colorHex(value) {
		var m = /^var\(--aimp-site-([a-z-]+),\s?(#[0-9a-f]{3,6})\)$/i.exec(String(value || ''));
		if (!m) {
			return /^#[0-9a-f]{6}$/i.test(value) ? value : '#ffffff';
		}
		var found = SITE_COLORS.filter(function (c) {
			return c[1] === m[1];
		})[0];
		return found ? siteColor(found[0], m[2]) : m[2];
	}
	function markSwatches(input, value) {
		var box = input.closest('.aimp-ds-color');
		var swatches = box ? box.nextElementSibling : null;
		if (!swatches || !swatches.classList.contains('aimp-ds-swatches')) {
			return;
		}
		swatches.querySelectorAll('[data-swatch]').forEach(function (button) {
			var c = SITE_COLORS.filter(function (s) {
				return s[1] === button.getAttribute('data-swatch');
			})[0];
			if (c) {
				button.style.background = siteColor(c[0], c[2]);
			}
			button.classList.toggle('is-current', button.getAttribute('data-value') === value || button.getAttribute('data-link') === value);
		});
		var linked = /^var\(--aimp-site-/.test(value);
		swatches.querySelector('.aimp-ds-swatch-note').hidden = !linked;
	}
	edit.querySelectorAll('.aimp-ds-color').forEach(function (rowEl) {
		var input = rowEl.querySelector('input[type="color"]');
		// Not for the brand colours themselves.
		if (!input || rowEl.closest('[data-group="palette"]')) {
			return;
		}
		var key = input.getAttribute('data-key');
		// Emails cannot use the site's colour links: the colour itself.
		var hexOnly = key.indexOf('em_') === 0;
		var swatches = document.createElement('div');
		swatches.className = 'aimp-ds-swatches';
		SITE_COLORS.concat([['white', '', '#ffffff'], ['black', '', '#000000']]).forEach(function (c) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'aimp-ds-swatch';
			button.title = t['color_' + c[0]] || c[0];
			button.setAttribute('aria-label', button.title);
			button.style.background = c[1] ? siteColor(c[0], c[2]) : c[2];
			if (c[1]) {
				button.setAttribute('data-swatch', c[1]);
				button.setAttribute('data-link', 'var(--aimp-site-' + c[1] + ',' + c[2] + ')');
			}
			button.setAttribute('data-value', c[2]);
			button.addEventListener('click', function () {
				var value = c[1] && !hexOnly ? button.getAttribute('data-link') : (c[1] ? siteColor(c[0], c[2]) : c[2]);
				change(key, value);
				input.value = colorHex(value);
				rowEl.classList.add('is-set');
				markSwatches(input, value);
			});
			swatches.appendChild(button);
		});
		var note = document.createElement('span');
		note.className = 'aimp-ds-swatch-note';
		note.textContent = t.colorLinked;
		note.hidden = true;
		swatches.appendChild(note);
		rowEl.insertAdjacentElement('afterend', swatches);
	});

	// The tab and open block per part, kept in this browser for the next time.
	var OPEN_KEY = 'aimp-ds-open';
	function openMemory() {
		try {
			return JSON.parse(window.localStorage.getItem(OPEN_KEY) || '{}') || {};
		} catch (e) {
			return {};
		}
	}
	function rememberOpen() {
		if (!current) {
			return;
		}
		var open = edit.querySelector('.aimp-ds-group.is-open');
		var memory = openMemory();
		memory[current] = { tab: activeTab, group: open ? (open === settingsGroup ? '__settings' : (open.getAttribute('data-group') || '')) : '' };
		try {
			window.localStorage.setItem(OPEN_KEY, JSON.stringify(memory));
		} catch (e) {}
	}
	function restoreOpen() {
		var saved = openMemory()[current];
		if (!saved) {
			return;
		}
		var tabButton = partTabs.querySelector('[data-ptab="' + saved.tab + '"]');
		if (tabButton && !tabButton.hidden) {
			setTab(saved.tab);
		}
		var group = saved.group === '__settings' ? settingsGroup : (saved.group ? edit.querySelector('.aimp-ds-group[data-group="' + saved.group + '"]') : null);
		if (group && !group.hidden && !group.classList.contains('is-off-tab')) {
			edit.querySelectorAll('.aimp-ds-group.is-open').forEach(function (other) {
				other.classList.remove('is-open');
			});
			group.classList.add('is-open');
		}
	}
	edit.addEventListener('click', function (e) {
		if (e.target.closest('.aimp-ds-group-toggle, .aimp-ds-ptabs button')) {
			window.setTimeout(rememberOpen, 0);
		}
	});

	function setDevice(name) {
		device = name;
		root.setAttribute('data-device-view', name);
		if (deviceNote) {
			deviceNote.textContent = name === 'phone' ? t.devicePhone : (name === 'tablet' ? t.deviceTablet : t.deviceComputer);
		}
	}
	setDevice('computer');

	/* ---------------- the preview ---------------- */

	var pageSelect = q('.aimp-ds-page');
	var pageGroups = {};
	cfg.pages.forEach(function (page) {
		var group = page[2] || t.shopPages;
		if (!pageGroups[group]) {
			pageGroups[group] = document.createElement('optgroup');
			pageGroups[group].label = group;
			pageSelect.appendChild(pageGroups[group]);
		}
		var o = document.createElement('option');
		o.value = page[1];
		o.textContent = page[0];
		pageGroups[group].appendChild(o);
	});
	var hereOption = null;
	function showPageInList(url, title) {
		var clean = new URL(url);
		clean.searchParams.delete(cfg.param);
		var key = clean.toString().replace(/\/$/, '');
		var match = Array.prototype.filter.call(pageSelect.options, function (o) {
			return o !== hereOption && o.value.replace(/\/$/, '') === key;
		})[0];
		if (match) {
			pageSelect.value = match.value;
			return;
		}
		if (!hereOption) {
			hereOption = document.createElement('option');
			pageSelect.insertBefore(hereOption, pageSelect.firstChild);
		}
		hereOption.value = clean.toString();
		hereOption.textContent = t.thisPageNow + ': ' + (title || clean.pathname);
		pageSelect.value = hereOption.value;
	}

	function load(url) {
		var u = new URL(url, window.location.href);
		u.searchParams.set(cfg.param, '1');
		frameOrigin = u.origin;
		frame.src = u.toString();
	}

	// "View page" and "Preview draft" follow the chosen page at once (and again once it has loaded).
	function setOpenLinks(url) {
		try {
			var visit = new URL(url, window.location.href);
			visit.searchParams.delete(cfg.param);
			q('.aimp-ds-open-page').href = visit.toString();
			visit.searchParams.set(cfg.param, '1');
			q('.aimp-ds-open-draft').href = visit.toString();
		} catch (err) {}
	}
	pageSelect.addEventListener('change', function () {
		setOpenLinks(pageSelect.value);
		load(pageSelect.value);
	});

	function fit() {
		var available = stage.clientWidth - 24;
		// Computer: as wide as there is room for (at least 1280, scaled down on smaller screens).
		var shown = width >= 1280 ? Math.max(1280, available) : width;
		var scale = Math.min(1, available / shown);
		var height = stage.clientHeight - 24;
		frame.style.width = shown + 'px';
		frame.style.height = Math.round(height / scale) + 'px';
		frame.style.transform = 'scale(' + scale + ')';
		wrap.style.width = Math.round(shown * scale) + 'px';
		wrap.style.height = height + 'px';
	}

	// The WordPress menu on the left: hidden in the studio for room, shown with this button (remembered).
	var menuButton = document.createElement('button');
	menuButton.type = 'button';
	menuButton.className = 'button aimp-ds-wpmenu-toggle';
	menuButton.textContent = '☰ ' + t.wpMenu;
	q('.aimp-ds-bar h1').insertAdjacentElement('beforebegin', menuButton);
	function showMenu(on) {
		document.body.classList.toggle('aimp-ds-wpmenu', on);
		menuButton.setAttribute('aria-pressed', on ? 'true' : 'false');
		try {
			window.localStorage.setItem('aimp-ds-wpmenu', on ? '1' : '');
		} catch (e) {}
		window.setTimeout(fit, 50);
	}
	var menuWanted = false;
	try {
		menuWanted = window.localStorage.getItem('aimp-ds-wpmenu') === '1';
	} catch (e) {}
	showMenu(menuWanted);
	menuButton.addEventListener('click', function () {
		showMenu(!document.body.classList.contains('aimp-ds-wpmenu'));
	});

	root.querySelectorAll('.aimp-ds-devices button').forEach(function (b) {
		b.addEventListener('click', function () {
			width = parseInt(b.getAttribute('data-width'), 10);
			// Phone: the settings for phones; computer and tablet: those for computers.
			setDevice(width < 700 ? 'phone' : (width < 1100 ? 'tablet' : 'computer'));
			root.querySelectorAll('.aimp-ds-devices button').forEach(function (other) {
				other.setAttribute('aria-pressed', other === b ? 'true' : 'false');
			});
			fit();
		});
	});
	window.addEventListener('resize', fit);

	var pickBox = q('.aimp-ds-pickmode input');
	pickBox.addEventListener('change', function () {
		toFrame({ type: 'aimp-design-pick', on: pickBox.checked });
	});

	// A new page in a second frame behind the visible one, at the same place; swapped once it is ready.
	var loadingFrame = null;
	function reloadPreview() {
		var y = 0;
		try {
			y = frame.contentWindow.scrollY;
			window.sessionStorage.setItem('aimp-design-scroll', String(y));
		} catch (err) {}
		var url = '';
		try {
			url = frame.contentWindow.location.href;
		} catch (err) {
			url = frame.src;
		}
		if (loadingFrame && loadingFrame.parentNode) {
			loadingFrame.parentNode.removeChild(loadingFrame);
		}
		loadingFrame = frame.cloneNode(false);
		loadingFrame.removeAttribute('src');
		loadingFrame.style.position = 'absolute';
		loadingFrame.style.top = '0';
		loadingFrame.style.left = '0';
		loadingFrame.style.visibility = 'hidden';
		frame.parentNode.appendChild(loadingFrame);
		loadingFrame.src = url;
	}
	function swapFrames() {
		var old = frame;
		frame = loadingFrame;
		loadingFrame = null;
		frame.style.position = '';
		frame.style.top = '';
		frame.style.left = '';
		frame.style.visibility = '';
		if (old.parentNode) {
			old.parentNode.removeChild(old);
		}
		fit();
	}

	window.addEventListener('message', function (e) {
		if (e.origin !== frameOrigin || !e.data || typeof e.data !== 'object') {
			return;
		}
		if (loadingFrame && e.source === loadingFrame.contentWindow) {
			if (e.data.type !== 'aimp-design-ready') {
				return;
			}
			swapFrames();
		} else if (frame.contentWindow && e.source !== frame.contentWindow) {
			return;
		}
		if (e.data.type === 'aimp-design-drag') {
			onDrag(e.data);
			return;
		}
		if (e.data.type === 'aimp-design-space') {
			onSpaceDrag(e.data);
			return;
		}
		if (e.data.type === 'aimp-design-resize') {
			onResize(e.data);
			return;
		}
		if (e.data.type === 'aimp-design-key') {
			nudge(e.data.key, e.data.shift);
			return;
		}
		if (e.data.type === 'aimp-design-ready') {
			// The page runs another plugin version than this studio tab: the plugin was updated, reload first.
			if (e.data.version && cfg.version && e.data.version !== cfg.version) {
				staleStudio();
			}
			// The parts and shop texts on this page: the lists show those first.
			present = Array.isArray(e.data.present) ? e.data.present : null;
			layerRows = Array.isArray(e.data.layers) && e.data.layers.length ? e.data.layers : null;
			frameTitle = String(e.data.title || '').split(/ [|–-] /)[0];
			framePostId = String(e.data.postId || 'other');
			if (current) {
				showPageBlocks();
			}
			pageTexts = Array.isArray(e.data.texts) ? e.data.texts : null;
			frameUrl = e.data.url || '';
			if (frameUrl) {
				showPageInList(frameUrl, String(e.data.title || '').split(/ [|–-] /)[0]);
				// "View page" (without the preview marker) and "Preview draft" (with it) open the page in the preview.
				setOpenLinks(frameUrl);
			}
			if (texts) {
				renderTexts();
			}
			renderList();
			sendCss();
			toFrame({ type: 'aimp-design-pick', on: pickBox.checked });
			if (current) {
				toFrame({ type: 'aimp-design-show', id: current, selector: cfg.elements[current] ? cfg.elements[current].selector : '', scroll: false });
			}
			sendSpacing();
		} else if (e.data.type === 'aimp-design-select' && (cfg.elements[e.data.id] || e.data.text || e.data.textMiss || (e.data.path && e.data.path.length))) {
			root.querySelector('.aimp-ds-tabs [data-tab="parts"]').click();
			// A click on a text: that text (in its part); on other words: the part, with a note.
			currentPost = parseInt(e.data.post, 10) || 0;
			currentDivi = !!e.data.divi;
			lastPath = Array.isArray(e.data.path) ? e.data.path : [];
			// A shop part, else the clicked element itself as an own part.
			var entry = lastPath[parseInt(e.data.chosen, 10) || 0];
			var pickId = cfg.elements[e.data.id] ? e.data.id : (entry && entry.sel ? ownFor(entry) : '__text');
			select(pickId, false, e.data.text && e.data.text.original ? e.data.text : null, e.data.textMiss);
			var item = list.querySelector('.is-current');
			if (item) {
				item.scrollIntoView({ block: 'nearest' });
			}
		}
	});

	renderList();
	renderHistory();
	renderLooks();
	showStatus();
	fit();
	// Opened from the admin bar on the site (?aimp_page=…): that page first, when it is a page of this site.
	var start = cfg.pages.length ? cfg.pages[0][1] : '/';
	var asked = new URLSearchParams(window.location.search).get('aimp_page');
	if (asked) {
		try {
			var page = new URL(asked, window.location.href);
			if (page.origin === new URL(start, window.location.href).origin) {
				page.searchParams.delete(cfg.param);
				var option = document.createElement('option');
				option.value = page.toString();
				option.textContent = t.thisPage || page.pathname;
				pageSelect.insertBefore(option, pageSelect.firstChild);
				pageSelect.value = option.value;
				start = option.value;
			}
		} catch (e) {}
	}
	load(start);
	var hint = document.createElement('p');
	hint.className = 'aimp-ds-hint';
	hint.textContent = t.pick;
	list.parentNode.insertBefore(hint, list);
})();
