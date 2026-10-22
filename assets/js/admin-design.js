/**
 * Design studio (class-aimp-design.php): the list of parts, the settings of the chosen part, the preview frame,
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
	var history = cfg.history || [];
	var current = '';
	var frame = q('.aimp-ds-frame');
	var wrap = q('.aimp-ds-frame-wrap');
	var stage = q('.aimp-ds-stage');
	var status = q('.aimp-ds-status');
	var width = 1280;
	var saveTimer = 0;
	var saving = false;
	var saveAgain = false;
	var frameOrigin = '';

	function clone(value) {
		return JSON.parse(JSON.stringify(value || {}));
	}

	function same(a, b) {
		return JSON.stringify(sorted(a)) === JSON.stringify(sorted(b));
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

	function fmt(text, n) {
		return String(text).replace('%d', n);
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

	function setState(data) {
		draft = clone(data.draft);
		live = clone(data.live);
		history = data.history || history;
		renderList();
		renderHistory();
		showEdit();
		sendCss();
		showStatus();
	}

	function showStatus(text) {
		status.textContent = text || (same(draft, live) ? t.published : t.unpublished);
		status.classList.toggle('is-draft', !same(draft, live));
	}

	/* ---------------- the CSS (the same as AIMP_Design::css()) ---------------- */

	function prefixed(selector) {
		return selector.split(',').map(function (part) {
			return 'html body ' + part.trim();
		}).join(',');
	}

	function css(styles) {
		var all = '';
		var computer = '';
		var phone = '';
		Object.keys(styles).forEach(function (id) {
			var el = cfg.elements[id];
			var row = styles[id];
			if (!el) {
				return;
			}
			var sel = prefixed(el.selector);
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

	function toFrame(message) {
		if (frame.contentWindow && frameOrigin) {
			frame.contentWindow.postMessage(message, frameOrigin);
		}
	}

	function sendCss() {
		toFrame({ type: 'aimp-design-css', css: css(draft) });
	}

	/* ---------------- saving ---------------- */

	function save() {
		window.clearTimeout(saveTimer);
		saveTimer = window.setTimeout(flush, 700);
		showStatus(t.saving);
	}

	function flush() {
		if (saving) {
			saveAgain = true;
			return;
		}
		saving = true;
		post('save', { styles: JSON.stringify(draft) }).then(function (data) {
			live = clone(data.live);
			showStatus(same(draft, live) ? t.published : t.saved + ' · ' + t.unpublished);
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
		Object.keys(groups).forEach(function (group) {
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

	function select(id, scroll) {
		current = id;
		renderList();
		showEdit();
		if (scroll) {
			toFrame({ type: 'aimp-design-show', id: id });
		}
	}

	function showEdit() {
		if (!current || !cfg.elements[current]) {
			edit.hidden = true;
			return;
		}
		edit.hidden = false;
		q('.aimp-ds-edit-name').textContent = cfg.elements[current].label;
		var row = draft[current] || {};
		edit.querySelectorAll('[data-key]').forEach(function (input) {
			var key = input.getAttribute('data-key');
			var neutral = /zoom$/.test(key) ? 100 : 0;
			if (input.type === 'checkbox') {
				input.checked = !!row[key];
			} else if (input.type === 'color') {
				input.value = row[key] || '#ffffff';
				input.closest('.aimp-ds-color').classList.toggle('is-set', !!row[key]);
			} else {
				input.value = row[key] !== undefined ? row[key] : neutral;
			}
		});
	}

	function change(key, value) {
		if (!current) {
			return;
		}
		var row = draft[current] || {};
		var neutral = /zoom$/.test(key) ? 100 : 0;
		if (value === '' || value === null || value === false || (typeof value === 'number' && value === neutral)) {
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
		save();
		renderList();
	}

	edit.addEventListener('input', function (e) {
		var input = e.target;
		var key = input.getAttribute('data-key');
		if (!key) {
			return;
		}
		if (input.type === 'checkbox') {
			change(key, input.checked ? 1 : false);
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
			change(clear.getAttribute('data-clear'), '');
			showEdit();
		}
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
		post('publish', { styles: JSON.stringify(draft) }).then(setState).catch(function (e) {
			showStatus(e.message);
		});
	});

	twice(q('.aimp-ds-discard'), function () {
		window.clearTimeout(saveTimer);
		post('discard').then(setState).catch(function (e) {
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
		var blob = new Blob([JSON.stringify({ aimpDesign: 1, styles: draft }, null, 2)], { type: 'application/json' });
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
			return post('import', { styles: JSON.stringify(json.styles) });
		}).then(function (data) {
			setState(data);
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
			select(e.data.id, false);
			var item = list.querySelector('.is-current');
			if (item) {
				item.scrollIntoView({ block: 'nearest' });
			}
		}
	});

	renderList();
	renderHistory();
	showStatus();
	fit();
	load(cfg.pages.length ? cfg.pages[0][1] : '/');
	if (!current) {
		var hint = document.createElement('p');
		hint.className = 'aimp-ds-hint';
		hint.textContent = t.pick;
		list.parentNode.insertBefore(hint, list);
	}
})();
