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
		return !same(draft, live) || !same(draftTexts, liveTexts);
	}

	function fmt(text) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return String(text).replace(/%(\d)\$d|%d/g, function (all, n) {
			return String(args[n ? parseInt(n, 10) - 1 : i++]);
		});
	}

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', 'aimp_design_' + action);
		body.append('nonce', cfg.nonce);
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
		}
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
		status.classList.toggle('is-draft', unpublished());
	}

	/* ---------------- the CSS (the same as AIMP_Design::css()) ---------------- */

	function prefixed(selector, extra) {
		return selector.split(',').map(function (part) {
			return 'html body ' + part.trim() + (extra || '');
		}).join(',');
	}

	function css(styles) {
		var all = '';
		var computer = '';
		var phone = '';
		Object.keys(styles).forEach(function (id) {
			var el = cfg.elements[id];
			var row = styles[id];
			// "Whole site" parts: their CSS comes from the server (siteCss).
			if (!el || !el.selector) {
				return;
			}
			var base = el.selector;
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
				if (row[p + 'mt'] !== undefined) {
					out += 'margin-top:' + parseInt(row[p + 'mt'], 10) + 'px!important;';
				}
				if (row[p + 'mb'] !== undefined) {
					out += 'margin-bottom:' + parseInt(row[p + 'mb'], 10) + 'px!important;';
				}
				if (row[p + 'hide']) {
					out += 'display:none!important;';
				}
				return out;
			};
			var d = device('');
			var m = device('p');
			if (d) {
				computer += sel + '{' + d + '}';
			}
			if (m) {
				phone += sel + '{' + m + '}';
			}
		});
		var out = all;
		if (computer) {
			out += '@media (min-width:' + (cfg.phone + 1) + 'px){' + computer + '}';
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
		post('save', { styles: JSON.stringify(draft), texts: JSON.stringify(draftTexts) }).then(function (data) {
			setState(data, true);
			showStatus(unpublished() ? t.saved + ' · ' + t.unpublished : t.published);
			// Texts are made on the server: the preview loads again once you stop typing.
			if (reload) {
				window.clearTimeout(reloadTimer);
				reloadTimer = window.setTimeout(function () {
					toFrame({ type: 'aimp-design-reload' });
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

	function renderList() {
		var term = search.value.trim().toLowerCase();
		var groups = {};
		Object.keys(cfg.elements).forEach(function (id) {
			var el = cfg.elements[id];
			if (term && (el.label + ' ' + el.group).toLowerCase().indexOf(term) === -1) {
				return;
			}
			(groups[el.group] = groups[el.group] || []).push(id);
		});
		list.innerHTML = '';
		// "Whole site" first.
		Object.keys(groups).sort(function (a, b) {
			var siteA = !cfg.elements[groups[a][0]].selector;
			var siteB = !cfg.elements[groups[b][0]].selector;
			return siteA === siteB ? 0 : (siteA ? -1 : 1);
		}).forEach(function (group) {
			var h = document.createElement('h3');
			h.textContent = group;
			list.appendChild(h);
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
				list.appendChild(b);
			});
		});
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
	function select(id, scroll) {
		current = id;
		renderList();
		showEdit();
		if (scroll && cfg.elements[id].selector) {
			toFrame({ type: 'aimp-design-show', id: id });
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
		edit.querySelectorAll('.aimp-ds-group').forEach(function (group) {
			group.hidden = controls.indexOf(group.getAttribute('data-group')) === -1;
		});
		q('.aimp-ds-edit-name').textContent = cfg.elements[current].label;
		var row = draft[current] || {};
		edit.querySelectorAll('[data-key]').forEach(function (input) {
			var key = input.getAttribute('data-key');
			if (input.type === 'checkbox') {
				input.checked = !!row[key];
			} else if (input.type === 'color') {
				input.value = row[key] || '#ffffff';
				input.closest('.aimp-ds-color').classList.toggle('is-set', !!row[key]);
			} else if (input.tagName === 'SELECT' || input.tagName === 'TEXTAREA' || input.type === 'text' || input.type === 'url') {
				input.value = row[key] !== undefined ? row[key] : '';
			} else {
				// "Standard" outside the range (e.g. -1 for corners): an empty box.
				var standard = neutral(key);
				input.value = row[key] !== undefined ? row[key] : (standard !== null && standard < parseInt(input.min, 10) ? '' : standard);
			}
		});
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
		var row = draft[current] || {};
		if (value === '' || value === null || value === false || (Array.isArray(value) && !value.length) || (typeof value === 'number' && value === neutral(key))) {
			delete row[key];
		} else {
			row[key] = value;
		}
		if (Object.keys(row).length) {
			draft[current] = row;
		} else {
			delete draft[current];
		}
		sendCss();
		save(reloads(key));
		renderList();
	}

	// Whether a setting is made on the server when the page is built (the preview then loads again).
	function reloads(key) {
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

	function loadTexts() {
		if (texts) {
			return;
		}
		texts = [];
		textCount.textContent = t.loading;
		post('texts').then(function (data) {
			texts = data;
			renderTexts();
		}).catch(function (e) {
			texts = null;
			textCount.textContent = e.message;
		});
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
				label.appendChild(tag);
				label.appendChild(input);
				item.appendChild(label);
			});
			textBox.appendChild(item);
		});
	}

	textSearch.addEventListener('input', function () {
		window.clearTimeout(textTimer);
		textTimer = window.setTimeout(renderTexts, 200);
	});
	textOwn.addEventListener('change', renderTexts);

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
			toFrame({ type: 'aimp-design-reload' });
		}).catch(function (e) {
			showStatus(e.message);
		});
	});

	twice(q('.aimp-ds-discard'), function () {
		window.clearTimeout(saveTimer);
		post('discard').then(function (data) {
			setState(data);
			toFrame({ type: 'aimp-design-reload' });
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
					toFrame({ type: 'aimp-design-reload' });
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
					toFrame({ type: 'aimp-design-reload' });
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
			toFrame({ type: 'aimp-design-reload' });
			showStatus(t.imported);
		}).catch(function (e) {
			showStatus(e instanceof SyntaxError ? t.badFile : e.message);
		}).then(function () {
			input.value = '';
		});
	});

	/* ---------------- the preview ---------------- */

	var pageSelect = q('.aimp-ds-page');
	cfg.pages.forEach(function (page) {
		var o = document.createElement('option');
		o.value = page[1];
		o.textContent = page[0];
		pageSelect.appendChild(o);
	});

	function load(url) {
		var u = new URL(url, window.location.href);
		u.searchParams.set(cfg.param, '1');
		frameOrigin = u.origin;
		frame.src = u.toString();
	}

	pageSelect.addEventListener('change', function () {
		load(pageSelect.value);
	});

	function fit() {
		var available = stage.clientWidth - 24;
		var scale = Math.min(1, available / width);
		var height = stage.clientHeight - 24;
		frame.style.width = width + 'px';
		frame.style.height = Math.round(height / scale) + 'px';
		frame.style.transform = 'scale(' + scale + ')';
		wrap.style.width = Math.round(width * scale) + 'px';
		wrap.style.height = height + 'px';
	}

	root.querySelectorAll('.aimp-ds-devices button').forEach(function (b) {
		b.addEventListener('click', function () {
			width = parseInt(b.getAttribute('data-width'), 10);
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

	window.addEventListener('message', function (e) {
		if (e.origin !== frameOrigin || !e.data || typeof e.data !== 'object') {
			return;
		}
		if (e.data.type === 'aimp-design-ready') {
			sendCss();
			toFrame({ type: 'aimp-design-pick', on: pickBox.checked });
			if (current) {
				toFrame({ type: 'aimp-design-show', id: current });
			}
		} else if (e.data.type === 'aimp-design-select' && cfg.elements[e.data.id]) {
			root.querySelector('.aimp-ds-tabs [data-tab="parts"]').click();
			select(e.data.id, false);
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
	load(cfg.pages.length ? cfg.pages[0][1] : '/');
	var hint = document.createElement('p');
	hint.className = 'aimp-ds-hint';
	hint.textContent = t.pick;
	list.parentNode.insertBefore(hint, list);
})();
