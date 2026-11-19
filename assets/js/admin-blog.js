/**
 * Atelier Irisee blog layout (post edit screen): rows of at most three columns side by side, each with blocks under
 * each other — a title, a text (WordPress editor), a picture (shape, gold frame, focal point) or a video (media library,
 * or a YouTube / Vimeo link).
 * The rows are kept in the post's content field as <!-- aimp-blog:base64 json -->, so saving, the preview and
 * autosave carry them (class-aimp-blog-builder.php turns them into the post's rows on saving).
 */
(function ($) {
	'use strict';

	var cfg = window.aimpBlogBuilder || {};
	var t = cfg.i18n || {};
	var media = cfg.media || {};
	var root = document.getElementById('aimp-blog-builder');
	var field = document.getElementById('content');
	if (!root || !field || !window.wp) {
		return;
	}

	var uid = 0;
	var rows = read();

	function esc(value) {
		return String(value === undefined || value === null ? '' : value).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function read() {
		var found = /<!-- aimp-blog:([A-Za-z0-9+\/=]*) -->/.exec(field.value);
		try {
			var list = found ? JSON.parse(decodeURIComponent(escape(window.atob(found[1])))) : [];
			return Array.isArray(list) ? list.map(upgrade) : [];
		} catch (e) {
			return [];
		}
	}

	// A row of before 3.75 (blocks side by side) as columns of one block.
	function upgrade(row) {
		row = row && typeof row === 'object' ? row : {};
		if (!Array.isArray(row.cols)) {
			row.cols = (Array.isArray(row.blocks) ? row.blocks : []).map(function (block) {
				return [block];
			});
		}
		delete row.blocks;
		row.stack = row.stack || '';
		return row;
	}

	function write() {
		field.value = '<!-- aimp-blog:' + window.btoa(unescape(encodeURIComponent(JSON.stringify(rows)))) + ' -->';
	}

	function newBlock(type, from) {
		var text = '';
		if (from) {
			text = from.t === 'title' ? from.text : (from.t === 'text' ? $('<div>').html(from.html || '').text().trim() : '');
		}
		switch (type) {
			case 'title':
				return { t: 'title', gap: '', text: text.slice(0, 300), size: 'h2', align: '' };
			case 'image':
				return { t: 'image', gap: '', id: 0, shape: '', frame: 0, fx: -1, fy: -1, caption: '', link: '', zoom: 1 };
			case 'video':
				return { t: 'video', gap: '', id: 0, url: '', poster: 0, auto: 0, shape: '', caption: '' };
			default:
				return { t: 'text', gap: '', html: from && from.t === 'text' ? from.html : esc(text), align: '' };
		}
	}

	function newRow(types) {
		return { w: '', v: '', space: '', stack: '', bg: 0, cols: types.map(function (type) { return [newBlock(type)]; }) };
	}

	/* ---------------------------------------------------------------
	 * Text editors (TinyMCE): rebuilt with the rows
	 * ------------------------------------------------------------- */

	function editorFor(id) {
		return window.tinymce ? window.tinymce.get(id) : null;
	}

	function blockOf(el) {
		var r = parseInt(el.getAttribute('data-r'), 10);
		var c = parseInt(el.getAttribute('data-c'), 10);
		var b = parseInt(el.getAttribute('data-b'), 10);
		return rows[r] && rows[r].cols[c] && rows[r].cols[c][b] ? rows[r].cols[c][b] : null;
	}

	// The editors' texts into the rows.
	function sync() {
		root.querySelectorAll('textarea.aimp-bb-editor').forEach(function (area) {
			var block = blockOf(area);
			if (!block) {
				return;
			}
			var editor = editorFor(area.id);
			block.html = editor && !editor.isHidden() ? editor.getContent() : area.value;
		});
	}

	function destroyEditors() {
		root.querySelectorAll('textarea.aimp-bb-editor').forEach(function (area) {
			if (window.wp.editor && window.wp.editor.remove) {
				window.wp.editor.remove(area.id);
			}
		});
	}

	function initEditors() {
		if (!window.wp.editor || !window.wp.editor.initialize) {
			return;
		}
		var defaults = window.wp.editor.getDefaultSettings ? window.wp.editor.getDefaultSettings() : {};
		root.querySelectorAll('textarea.aimp-bb-editor').forEach(function (area) {
			var id = area.id;
			window.wp.editor.initialize(id, {
				tinymce: $.extend({}, defaults.tinymce || {}, {
					wpautop: true,
					height: 200,
					toolbar1: 'formatselect,bold,italic,underline,bullist,numlist,blockquote,link,unlink,alignleft,aligncenter,alignright,undo,redo',
					toolbar2: '',
					setup: function (editor) {
						editor.on('change keyup input undo redo', function () {
							var block = blockOf(area);
							if (block) {
								block.html = editor.getContent();
								write();
							}
						});
					}
				}),
				quicktags: true,
				mediaButtons: false
			});
		});
	}

	/* ---------------------------------------------------------------
	 * Building the rows
	 * ------------------------------------------------------------- */

	// Where a field or button belongs: { r } (a row), { r, c } (a column) or { r, c, b } (a block).
	function attrs(at, k) {
		return ' data-r="' + at.r + '"' + (at.c === undefined ? '' : ' data-c="' + at.c + '"') + (at.b === undefined ? '' : ' data-b="' + at.b + '"') + (k ? ' data-k="' + k + '"' : '');
	}

	function select(label, k, at, value, options) {
		return '<label class="aimp-bb-field"><span>' + esc(label) + '</span><select' + attrs(at, k) + '>' +
			options.map(function (o) {
				return '<option value="' + esc(o[0]) + '"' + (String(value || '') === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
			}).join('') + '</select></label>';
	}

	function check(label, k, at, value) {
		return '<label class="aimp-bb-check"><input type="checkbox"' + attrs(at, k) + (value ? ' checked' : '') + '> ' + esc(label) + '</label>';
	}

	function input(label, k, at, value, type, placeholder) {
		return '<label class="aimp-bb-field is-wide"><span>' + esc(label) + '</span><input type="' + (type || 'text') + '"' + attrs(at, k) + ' value="' + esc(value) + '"' + (placeholder ? ' placeholder="' + esc(placeholder) + '"' : '') + '></label>';
	}

	function tool(act, symbol, label, at, disabled) {
		return '<button type="button" class="aimp-bb-tool" data-act="' + act + '"' + attrs(at) + ' title="' + esc(label) + '" aria-label="' + esc(label) + '"' + (disabled ? ' disabled' : '') + '>' + symbol + '</button>';
	}

	function button(act, label, at, extra) {
		return '<button type="button" class="button" data-act="' + act + '"' + attrs(at) + (extra || '') + '>' + esc(label) + '</button>';
	}

	var ALIGN = [['', t.alignAuto], ['left', t.alignLeft], ['center', t.alignCenter], ['right', t.alignRight]];
	var SPACES = [['', t.spaceNormal], ['none', t.spaceNone], ['small', t.spaceSmall], ['large', t.spaceLarge]];

	function body(block, at) {
		var html = '';
		if (block.t === 'title') {
			html += '<input type="text" class="aimp-bb-title-input is-' + esc(block.size) + '"' + attrs(at, 'text') + ' value="' + esc(block.text) + '" placeholder="' + esc(t.titleText) + '">';
			html += '<div class="aimp-bb-fields">' + select(t.size, 'size', at, block.size, [['h2', t.large], ['h3', t.small]]) + select(t.align, 'align', at, block.align, ALIGN) + '</div>';
		} else if (block.t === 'text') {
			html += '<textarea id="aimpbb' + (++uid) + '" class="aimp-bb-editor" rows="8"' + attrs(at, 'html') + '>' + esc(block.html) + '</textarea>';
			html += '<div class="aimp-bb-fields">' + select(t.align, 'align', at, block.align, ALIGN) + '</div>';
		} else if (block.t === 'image') {
			var picture = block.id && media[block.id] ? media[block.id] : null;
			if (picture) {
				html += '<div class="aimp-bb-image is-' + esc(block.shape || 'plain') + (block.frame ? ' has-frame' : '') + '" data-act="focus"' + attrs(at) + ' title="' + esc(t.focus) + '">' +
					'<img src="' + esc(picture.thumb || picture.url) + '" alt="" style="object-position:' + (block.fx >= 0 ? block.fx : 50) + '% ' + (block.fy >= 0 ? block.fy : 50) + '%">' +
					'<span class="aimp-bb-dot" style="left:' + (block.fx >= 0 ? block.fx : 50) + '%;top:' + (block.fy >= 0 ? block.fy : 50) + '%"></span></div>';
				html += '<p class="aimp-bb-hint">' + esc(t.focus) + '</p>';
			}
			html += '<p class="aimp-bb-buttons">' + button('pick-image', picture ? t.change : t.choose, at) + (picture ? ' ' + button('clear-image', t.remove, at) : '') + '</p>';
			html += '<div class="aimp-bb-fields">' +
				select(t.shape, 'shape', at, block.shape, [['', t.plain], ['arch', t.arch], ['round', t.round], ['square', t.square]]) +
				check(t.frame, 'frame', at, block.frame) + check(t.zoom, 'zoom', at, block.zoom) + '</div>';
			html += input(t.caption, 'caption', at, block.caption) + input(t.link, 'link', at, block.link, 'url', 'https://');
		} else if (block.t === 'video') {
			var file = block.id && media[block.id] ? media[block.id] : null;
			var poster = block.poster && media[block.poster] ? media[block.poster] : null;
			if (file) {
				html += '<p class="aimp-bb-file">🎬 ' + esc(file.name || file.url) + '</p>';
			}
			html += '<p class="aimp-bb-buttons">' + button('pick-video', file ? t.change : t.chooseVideo, at) + (file ? ' ' + button('clear-video', t.remove, at) : '') + '</p>';
			if (!file) {
				html += input(t.videoUrl, 'url', at, block.url, 'url', 'https://www.youtube.com/watch?v=…');
			}
			html += '<div class="aimp-bb-poster">' + (poster ? '<img src="' + esc(poster.thumb || poster.url) + '" alt="">' : '') +
				'<span>' + esc(t.poster) + '</span> ' + button('pick-poster', poster ? t.change : t.choose, at) + (poster ? ' ' + button('clear-poster', t.remove, at) : '') + '</div>';
			html += '<div class="aimp-bb-fields">' + select(t.videoShape, 'shape', at, block.shape, [['', t.rounded], ['plain', t.square2]]) + (file ? check(t.auto, 'auto', at, block.auto) : '') + '</div>';
			html += input(t.caption, 'caption', at, block.caption);
		}
		// A block under another one: its own space above (standard: the row's).
		if (at.b > 0) {
			html += '<div class="aimp-bb-fields">' + select(t.gap, 'gap', at, block.gap, SPACES) + '</div>';
		}
		return html;
	}

	function blockHtml(block, at, count) {
		return '<div class="aimp-bb-block is-' + esc(block.t) + '">' +
			'<div class="aimp-bb-block-bar">' +
			'<select class="aimp-bb-type"' + attrs(at, 't') + ' aria-label="' + esc(t.text) + '">' +
			[['title', t.title], ['text', t.text], ['image', t.image], ['video', t.video]].map(function (o) {
				return '<option value="' + o[0] + '"' + (block.t === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
			}).join('') + '</select>' +
			'<span class="aimp-bb-tools">' +
			(count > 1 ? tool('block-up', '↑', t.up, at, at.b === 0) + tool('block-down', '↓', t.down, at, at.b === count - 1) : '') +
			tool('block-remove', '×', t.remove, at, false) + '</span></div>' +
			'<div class="aimp-bb-block-body">' + body(block, at) + '</div></div>';
	}

	function colHtml(col, r, c, count) {
		var at = { r: r, c: c };
		var html = '<div class="aimp-bb-col">';
		if (count > 1) {
			html += '<div class="aimp-bb-col-bar"><span>' + esc(t.column) + ' ' + (c + 1) + '</span><span class="aimp-bb-tools">' +
				tool('col-left', '←', t.left, at, c === 0) + tool('col-right', '→', t.right, at, c === count - 1) +
				tool('col-remove', '×', t.removeCol, at, false) + '</span></div>';
		}
		col.forEach(function (block, b) {
			html += blockHtml(block, { r: r, c: c, b: b }, col.length);
		});
		if (col.length < 8) {
			html += '<button type="button" class="aimp-bb-add-under" data-act="add-under"' + attrs(at) + '>' + esc(t.addUnder) + '</button>';
		}
		return html + '</div>';
	}

	function rowHtml(row, r) {
		var at = { r: r };
		var count = row.cols.length;
		var stacked = row.cols.some(function (col) {
			return col.length > 1;
		});
		var bar = '<div class="aimp-bb-row-bar"><strong>' + esc(t.row) + ' ' + (r + 1) + '</strong>';
		if (count === 2) {
			bar += select(t.widths, 'w', at, row.w, [['', t.half], ['12', t.narrowWide], ['21', t.wideNarrow]]);
		}
		if (count > 1) {
			bar += select(t.vertical, 'v', at, row.v, [['', t.vMiddle], ['top', t.vTop], ['bottom', t.vBottom]]);
		}
		bar += select(t.space, 'space', at, row.space, [['', t.spaceNormal], ['small', t.spaceSmall], ['large', t.spaceLarge]]);
		if (stacked) {
			bar += select(t.stack, 'stack', at, row.stack, SPACES);
		}
		bar += check(t.background, 'bg', at, row.bg);
		bar += '<span class="aimp-bb-tools">' + tool('row-up', '↑', t.up, at, r === 0) + tool('row-down', '↓', t.down, at, r === rows.length - 1) +
			tool('row-copy', '⧉', t.copy, at, false) + tool('row-remove', '×', t.remove, at, false) + '</span></div>';
		return '<div class="aimp-bb-row' + (row.bg ? ' has-bg' : '') + '">' + bar +
			'<div class="aimp-bb-blocks is-n-' + count + (count === 2 && row.w ? ' is-w-' + esc(row.w) : '') + '">' +
			row.cols.map(function (col, c) {
				return colHtml(col, r, c, count);
			}).join('') +
			(count < 3 ? '<button type="button" class="aimp-bb-add-block" data-act="add-col"' + attrs(at) + '>' + esc(t.addColumn) + '</button>' : '') +
			'</div></div>';
	}

	function render() {
		sync();
		destroyEditors();
		var html = rows.length ? '' : '<p class="aimp-bb-empty">' + esc(t.empty) + '</p>';
		rows.forEach(function (row, r) {
			html += rowHtml(row, r);
		});
		html += '<div class="aimp-bb-add">' +
			'<button type="button" class="button button-primary" data-act="add-row" data-kind="title">' + esc(t.addTitleRow) + '</button> ' +
			'<button type="button" class="button" data-act="add-row" data-kind="text">' + esc(t.addTextRow) + '</button> ' +
			'<button type="button" class="button" data-act="add-row" data-kind="pictext">' + esc(t.addPicText) + '</button> ' +
			'<button type="button" class="button" data-act="add-row" data-kind="video">' + esc(t.video) + '</button></div>';
		root.innerHTML = html;
		initEditors();
		write();
	}

	/* ---------------------------------------------------------------
	 * Media library
	 * ------------------------------------------------------------- */

	function pick(kind, done) {
		var frame = window.wp.media({
			title: kind === 'video' ? t.chooseVideo : t.choose,
			library: { type: kind },
			multiple: false,
			button: { text: t.use }
		});
		frame.on('select', function () {
			var item = frame.state().get('selection').first().toJSON();
			var sizes = item.sizes || {};
			media[item.id] = {
				url: item.url,
				thumb: (sizes.medium || sizes.large || sizes.full || {}).url || (kind === 'image' ? item.url : ''),
				kind: kind,
				name: item.filename || item.title || ''
			};
			done(item.id);
			render();
		});
		frame.open();
	}

	/* ---------------------------------------------------------------
	 * Events
	 * ------------------------------------------------------------- */

	function num(el, name) {
		return parseInt(el.getAttribute('data-' + name), 10);
	}

	function onField(e) {
		var el = e.target;
		var key = el.getAttribute && el.getAttribute('data-k');
		if (!key) {
			return;
		}
		var row = rows[num(el, 'r')];
		if (!row) {
			return;
		}
		var value = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
		var block = el.hasAttribute('data-b') ? blockOf(el) : null;
		if (key === 't' && block) {
			if (e.type !== 'change') {
				return;
			}
			sync();
			var fresh = newBlock(value, block);
			fresh.gap = block.gap || '';
			row.cols[num(el, 'c')][num(el, 'b')] = fresh;
			render();
			return;
		}
		(block || row)[key] = value;
		// Choices and ticks change what is shown: build again. Typing only saves.
		if (e.type === 'change' && (el.tagName === 'SELECT' || el.type === 'checkbox')) {
			render();
		} else {
			write();
		}
	}

	root.addEventListener('input', onField);
	root.addEventListener('change', onField);

	function move(list, from, to) {
		if (to < 0 || to >= list.length) {
			return false;
		}
		list.splice(to, 0, list.splice(from, 1)[0]);
		return true;
	}

	function big(what) {
		return JSON.stringify(what).length > 120;
	}

	root.addEventListener('click', function (e) {
		var el = e.target.closest('[data-act]');
		if (!el || !root.contains(el)) {
			return;
		}
		var act = el.getAttribute('data-act');
		var r = num(el, 'r');
		var c = num(el, 'c');
		var b = num(el, 'b');
		var row = rows[r];
		var col = row && !isNaN(c) ? row.cols[c] : null;
		var block = col && !isNaN(b) ? col[b] : null;
		e.preventDefault();
		sync();
		switch (act) {
			case 'add-row':
				var kind = el.getAttribute('data-kind');
				rows.push(newRow(kind === 'pictext' ? ['image', 'text'] : [kind || 'text']));
				break;
			case 'row-up':
			case 'row-down':
				if (!move(rows, r, act === 'row-up' ? r - 1 : r + 1)) {
					return;
				}
				break;
			case 'row-copy':
				rows.splice(r + 1, 0, JSON.parse(JSON.stringify(row)));
				break;
			case 'row-remove':
				// eslint-disable-next-line no-alert
				if (big(row.cols) && !window.confirm(t.removeRow)) {
					return;
				}
				rows.splice(r, 1);
				break;
			case 'add-col':
				if (row.cols.length < 3) {
					row.cols.push([newBlock('text')]);
				}
				break;
			case 'add-under':
				if (col && col.length < 8) {
					col.push(newBlock('text'));
				}
				break;
			case 'col-left':
			case 'col-right':
				if (!move(row.cols, c, act === 'col-left' ? c - 1 : c + 1)) {
					return;
				}
				break;
			case 'col-remove':
				// eslint-disable-next-line no-alert
				if (big(col) && !window.confirm(t.removeCol + '?')) {
					return;
				}
				row.cols.splice(c, 1);
				if (!row.cols.length) {
					rows.splice(r, 1);
				}
				break;
			case 'block-up':
			case 'block-down':
				if (!move(col, b, act === 'block-up' ? b - 1 : b + 1)) {
					return;
				}
				break;
			case 'block-remove':
				col.splice(b, 1);
				if (!col.length) {
					row.cols.splice(c, 1);
				}
				if (!row.cols.length) {
					rows.splice(r, 1);
				}
				break;
			case 'pick-image':
				pick('image', function (id) {
					block.id = id;
					block.fx = -1;
					block.fy = -1;
				});
				return;
			case 'pick-video':
				pick('video', function (id) {
					block.id = id;
					block.url = '';
				});
				return;
			case 'pick-poster':
				pick('image', function (id) {
					block.poster = id;
				});
				return;
			case 'clear-image':
			case 'clear-video':
				block.id = 0;
				break;
			case 'clear-poster':
				block.poster = 0;
				break;
			case 'focus':
				// What stays in view when the shape crops the picture.
				var box = el.getBoundingClientRect();
				block.fx = Math.max(0, Math.min(100, Math.round((e.clientX - box.left) / box.width * 100)));
				block.fy = Math.max(0, Math.min(100, Math.round((e.clientY - box.top) / box.height * 100)));
				var img = el.querySelector('img');
				var dot = el.querySelector('.aimp-bb-dot');
				img.style.objectPosition = block.fx + '% ' + block.fy + '%';
				dot.style.left = block.fx + '%';
				dot.style.top = block.fy + '%';
				write();
				return;
			default:
				return;
		}
		render();
	});

	// Saving and the preview: the latest texts of the editors.
	$('#post').on('submit', function () {
		sync();
		write();
	});

	render();
})(jQuery);
