/**
 * Design studio preview (class-aimp-design.php): runs inside the studio's preview frame. A click on the page selects
 * what is under the mouse in the studio: a part of the shop, or any other element (an own part), with the shop text
 * that was clicked and the elements around it. The studio sends the draft's styles while you edit. Links keep the
 * preview on.
 */
(function () {
	'use strict';

	var cfg = window.aimpDesignPreview;
	if (!cfg || window.parent === window) {
		return;
	}
	var studio = new URL(cfg.origin, window.location.href).origin;
	var ids = Object.keys(cfg.elements);
	var names = cfg.names || {};
	var words = cfg.words || {};
	var pick = true;
	// "Edit texts on the page": a click on a text makes it editable in place.
	var editMode = false;
	var editing = null;
	var selectedSel = '';
	var hovered = null;

	var style = document.getElementById('aimp-design-css');
	if (!style) {
		style = document.createElement('style');
		style.id = 'aimp-design-css';
		document.head.appendChild(style);
	}
	// The outline of the chosen part and of the part under the mouse.
	var marks = document.createElement('style');
	document.head.appendChild(marks);

	// The name of what is under the mouse, at its top left corner.
	var label = document.createElement('div');
	label.setAttribute('aria-hidden', 'true');
	label.style.cssText = 'position:fixed;z-index:2147483647;display:none;padding:2px 8px;border-radius:4px;background:#8a6a33;color:#fff;font:600 12px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;pointer-events:none;white-space:nowrap;max-width:60vw;overflow:hidden;text-overflow:ellipsis';
	// In the body: some browsers (extensions) hide full-screen layers placed directly in <html>.
	(document.body || document.documentElement).appendChild(label);

	/*
	 * What changed, drawn over the page: where the part was (dashed), where it is now (gold), an arrow and a label;
	 * the focal point of a picture as a cross. It fades away a moment after the last change.
	 */
	var overlay = document.createElement('div');
	overlay.setAttribute('aria-hidden', 'true');
	overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483646;pointer-events:none;display:none;transition:opacity .4s ease';
	(document.body || document.documentElement).appendChild(overlay);
	var overlayTimer = 0;
	var peekLayer = document.createElement('div');
	peekLayer.setAttribute('aria-hidden', 'true');
	peekLayer.style.cssText = 'position:fixed;inset:0;z-index:2147483646;pointer-events:none;display:none';
	(document.body || document.documentElement).appendChild(peekLayer);
	var peekNodes = [];
	var peekEl = null;
	var peekName = '';
	function drawPeek() {
		if (!peekEl || !peekEl.isConnected) {
			peekLayer.style.display = 'none';
			return;
		}
		var r = peekEl.getBoundingClientRect();
		peekLayer.innerHTML = '';
		peekLayer.style.display = 'block';
		var frameBox = document.createElement('div');
		frameBox.style.cssText = 'position:fixed;left:' + (r.left - 3) + 'px;top:' + (r.top - 3) + 'px;width:' + (r.width + 6) + 'px;height:' + (r.height + 6) + 'px;box-sizing:border-box;border:3px solid #b38f4f;background:rgba(179,143,79,.14);border-radius:4px';
		peekLayer.appendChild(frameBox);
		if (peekName) {
			var name = document.createElement('div');
			name.textContent = peekName;
			name.style.cssText = 'position:fixed;left:' + Math.max(4, Math.min(r.left, window.innerWidth - 240)) + 'px;top:' + (r.top - 30 > 0 ? r.top - 30 : r.top + 6) + 'px;padding:3px 10px;border-radius:100px;background:#8a6a33;color:#fff;font:600 13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;white-space:nowrap';
			peekLayer.appendChild(name);
		}
	}
	window.addEventListener('scroll', function () {
		if (peekEl) {
			drawPeek();
		}
	}, { passive: true });
	function box(r, css) {
		var d = document.createElement('div');
		d.style.cssText = 'position:fixed;left:' + r.left + 'px;top:' + r.top + 'px;width:' + Math.max(0, r.width) + 'px;height:' + Math.max(0, r.height) + 'px;box-sizing:border-box;' + css;
		overlay.appendChild(d);
		return d;
	}
	function tag(text, x, y) {
		var d = document.createElement('div');
		d.textContent = text;
		d.style.cssText = 'position:fixed;left:' + Math.max(4, Math.min(x, window.innerWidth - 220)) + 'px;top:' + Math.max(4, y) + 'px;padding:3px 10px;border-radius:100px;background:#8a6a33;color:#fff;font:600 13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,.25)';
		overlay.appendChild(d);
	}
	function showFocus(data) {
		var el = null;
		try {
			el = document.querySelector(prefixed(data.selector));
		} catch (e) {}
		if (!el) {
			return;
		}
		overlay.innerHTML = '';
		overlay.style.display = 'block';
		overlay.style.opacity = '1';
		var r = el.getBoundingClientRect();
		if (data.kind === 'move') {
			// Where it was: the same box without the move.
			var was = { left: r.left - data.x, top: r.top - data.y, width: r.width, height: r.height };
			box(was, 'border:2px dashed rgba(70,70,70,.75);background:rgba(255,255,255,.25)');
			box(r, 'border:3px solid #b38f4f');
			if (data.x || data.y) {
				var x1 = was.left + was.width / 2;
				var y1 = was.top + was.height / 2;
				var x2 = r.left + r.width / 2;
				var y2 = r.top + r.height / 2;
				var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
				svg.setAttribute('width', window.innerWidth);
				svg.setAttribute('height', window.innerHeight);
				svg.style.cssText = 'position:fixed;left:0;top:0;overflow:visible';
				svg.innerHTML = '<defs><marker id="aimp-ds-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M0 0L10 5L0 10z" fill="#8a6a33"/></marker></defs>' +
					'<circle cx="' + x1 + '" cy="' + y1 + '" r="4" fill="#8a6a33"/>' +
					'<line x1="' + x1 + '" y1="' + y1 + '" x2="' + x2 + '" y2="' + y2 + '" stroke="#8a6a33" stroke-width="3" marker-end="url(#aimp-ds-arrow)"/>';
				overlay.appendChild(svg);
			}
			tag(data.label, r.left, r.top - 30 > 0 ? r.top - 30 : r.bottom + 6);
		} else if (data.kind === 'focal') {
			box(r, 'border:2px solid rgba(179,143,79,.8)');
			var fx = r.left + r.width * (data.fx / 100);
			var fy = r.top + r.height * (data.fy / 100);
			box({ left: fx - 1, top: r.top, width: 2, height: r.height }, 'background:rgba(255,255,255,.9);box-shadow:0 0 0 1px rgba(0,0,0,.35)');
			box({ left: r.left, top: fy - 1, width: r.width, height: 2 }, 'background:rgba(255,255,255,.9);box-shadow:0 0 0 1px rgba(0,0,0,.35)');
			box({ left: fx - 11, top: fy - 11, width: 22, height: 22 }, 'border:3px solid #fff;border-radius:50%;background:#b38f4f;box-shadow:0 0 0 1px rgba(0,0,0,.4)');
			tag(data.label, fx + 16, fy - 14);
		} else if (data.kind === 'space') {
			box(r, 'border:2px solid #b38f4f');
			var h = Math.max(0, data.value || 0);
			if (h) {
				box({ left: r.left, top: data.side === 'top' ? r.top - h : r.bottom, width: r.width, height: h }, 'background:repeating-linear-gradient(45deg,rgba(179,143,79,.28) 0 6px,rgba(179,143,79,.12) 6px 12px)');
			}
			tag(data.label, r.left, data.side === 'top' ? r.top - h - 30 : r.bottom + h + 6);
		} else {
			box(r, 'border:3px solid #b38f4f');
			tag(data.label, r.left, r.top - 30 > 0 ? r.top - 30 : r.bottom + 6);
		}
		window.clearTimeout(overlayTimer);
		overlayTimer = window.setTimeout(function () {
			overlay.style.opacity = '0';
			window.setTimeout(function () {
				if (overlay.style.opacity === '0') {
					overlay.style.display = 'none';
				}
			}, 450);
		}, 2500);
	}

	/*
	 * Spacing like Divi's visual builder: the chosen part's margin (orange, outside) and padding (green, inside) as
	 * bands, with a handle per side to drag. The studio turns them on for parts with spacing (aimp-design-spacing).
	 */
	var spacing = { on: false, selector: '', labels: {} };
	var bands = document.createElement('div');
	bands.setAttribute('data-aimp-ds-layer', '');
	bands.setAttribute('aria-hidden', 'true');
	bands.style.cssText = 'position:fixed;inset:0;z-index:2147483645;pointer-events:none;display:none';
	(document.body || document.documentElement).appendChild(bands);
	var spaceDrag = null;
	var bandsFrame = 0;
	function spacingOf(el) {
		var cs = window.getComputedStyle(el);
		return {
			mt: parseFloat(cs.marginTop) || 0,
			mr: parseFloat(cs.marginRight) || 0,
			mb: parseFloat(cs.marginBottom) || 0,
			ml: parseFloat(cs.marginLeft) || 0,
			padt: parseFloat(cs.paddingTop) || 0,
			padr: parseFloat(cs.paddingRight) || 0,
			padb: parseFloat(cs.paddingBottom) || 0,
			padl: parseFloat(cs.paddingLeft) || 0
		};
	}
	function band(left, top, width, height, color) {
		if (width <= 0 || height <= 0) {
			return;
		}
		var d = document.createElement('div');
		d.style.cssText = 'position:fixed;left:' + left + 'px;top:' + top + 'px;width:' + width + 'px;height:' + height + 'px;background:' + color;
		bands.appendChild(d);
	}
	function handle(key, x, y, vertical, color) {
		var d = document.createElement('div');
		d.setAttribute('data-aimp-ds-layer', '');
		d.setAttribute('data-space', key);
		d.title = spacing.labels[key] || key;
		d.style.cssText = 'position:fixed;left:' + (x - (vertical ? 14 : 4)) + 'px;top:' + (y - (vertical ? 4 : 14)) + 'px;width:' + (vertical ? 28 : 8) + 'px;height:' + (vertical ? 8 : 28) +
			'px;border-radius:8px;background:' + color + ';border:2px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.35);pointer-events:auto;cursor:' + (vertical ? 'ns-resize' : 'ew-resize');
		bands.appendChild(d);
	}
	function drawBands() {
		bandsFrame = 0;
		bands.innerHTML = '';
		var el = null;
		if (spacing.on && pick && spacing.selector) {
			try {
				el = document.querySelector(prefixed(spacing.selector));
			} catch (e) {}
		}
		if (!el) {
			bands.style.display = 'none';
			return;
		}
		bands.style.display = 'block';
		var r = el.getBoundingClientRect();
		var s = spacingOf(el);
		var margin = 'rgba(247,166,74,.35)';
		var padding = 'rgba(94,180,120,.35)';
		var mt = Math.max(0, s.mt);
		var mb = Math.max(0, s.mb);
		var ml = Math.max(0, s.ml);
		var mr = Math.max(0, s.mr);
		band(r.left - ml, r.top - mt, r.width + ml + mr, mt, margin);
		band(r.left - ml, r.bottom, r.width + ml + mr, mb, margin);
		band(r.left - ml, r.top, ml, r.height, margin);
		band(r.right, r.top, mr, r.height, margin);
		band(r.left, r.top, r.width, s.padt, padding);
		band(r.left, r.bottom - s.padb, r.width, s.padb, padding);
		band(r.left, r.top + s.padt, s.padl, r.height - s.padt - s.padb, padding);
		band(r.right - s.padr, r.top + s.padt, s.padr, r.height - s.padt - s.padb, padding);
		var cx = r.left + r.width / 2;
		var cy = r.top + r.height / 2;
		var orange = '#e8892b';
		var green = '#3f9a5c';
		handle('mt', cx - 22, r.top - s.mt, true, orange);
		handle('mb', cx - 22, r.bottom + s.mb, true, orange);
		handle('ml', r.left - s.ml, cy - 22, false, orange);
		handle('mr', r.right + s.mr, cy - 22, false, orange);
		handle('padt', cx + 22, r.top + s.padt, true, green);
		handle('padb', cx + 22, r.bottom - s.padb, true, green);
		handle('padl', r.left + s.padl, cy + 22, false, green);
		handle('padr', r.right - s.padr, cy + 22, false, green);
		if (spaceDrag) {
			var label = document.createElement('div');
			label.textContent = (spacing.labels[spaceDrag.key] || spaceDrag.key) + ' ' + spaceDrag.value + ' px';
			label.style.cssText = 'position:fixed;left:' + Math.max(4, Math.min(spaceDrag.x + 14, window.innerWidth - 220)) + 'px;top:' + Math.max(4, spaceDrag.y - 34) + 'px;padding:3px 10px;border-radius:100px;background:#8a6a33;color:#fff;font:600 13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;white-space:nowrap';
			bands.appendChild(label);
		}
	}
	function redrawBands() {
		if (!bandsFrame) {
			bandsFrame = window.requestAnimationFrame(drawBands);
		}
	}
	window.addEventListener('scroll', redrawBands, true);
	window.addEventListener('resize', redrawBands);
	// Dragging a handle: the new value (the spacing it had plus the distance, in the direction that makes it bigger).
	var GROW = { mt: [0, -1], mb: [0, 1], ml: [-1, 0], mr: [1, 0], padt: [0, 1], padb: [0, -1], padl: [1, 0], padr: [-1, 0] };
	document.addEventListener('mousedown', function (e) {
		var knob = e.target.closest ? e.target.closest('[data-space]') : null;
		if (!knob) {
			return;
		}
		var el = null;
		try {
			el = document.querySelector(prefixed(spacing.selector));
		} catch (err) {}
		if (!el) {
			return;
		}
		var key = knob.getAttribute('data-space');
		spaceDrag = { key: key, start: spacingOf(el)[key], x0: e.clientX, y0: e.clientY, x: e.clientX, y: e.clientY, value: Math.round(spacingOf(el)[key]), started: false };
		e.preventDefault();
		e.stopPropagation();
	}, true);
	document.addEventListener('mousemove', function (e) {
		if (!spaceDrag) {
			return;
		}
		var grow = GROW[spaceDrag.key];
		spaceDrag.x = e.clientX;
		spaceDrag.y = e.clientY;
		spaceDrag.value = Math.round(spaceDrag.start + (e.clientX - spaceDrag.x0) * grow[0] + (e.clientY - spaceDrag.y0) * grow[1]);
		// Only a real drag changes something (a click on a handle does not).
		if (!spaceDrag.started) {
			spaceDrag.started = true;
			send({ type: 'aimp-design-space', phase: 'start', key: spaceDrag.key, value: spaceDrag.value });
		}
		send({ type: 'aimp-design-space', phase: 'move', key: spaceDrag.key, value: spaceDrag.value });
		redrawBands();
		e.stopPropagation();
	}, true);
	document.addEventListener('mouseup', function (e) {
		if (!spaceDrag) {
			return;
		}
		if (spaceDrag.started) {
			send({ type: 'aimp-design-space', phase: 'end', key: spaceDrag.key, value: spaceDrag.value });
		}
		spaceDrag = null;
		dragged = true;
		redrawBands();
		e.stopPropagation();
	}, true);

	// "html body" in front (as AIMP_Design::prefixed()); a selector that starts at body gets only "html".
	function prefixed(selector, extra) {
		return selector.split(',').map(function (part) {
			part = part.trim();
			return (part.indexOf('body') === 0 ? 'html ' : 'html body ') + part + (extra || '');
		}).join(',');
	}

	function drawMarks() {
		var css = '[data-aimp-design-hover]{outline:2px dashed #b38f4f!important;outline-offset:2px;cursor:pointer!important}';
		if (selectedSel) {
			css += prefixed(selectedSel) + '{outline:3px solid #b38f4f!important;outline-offset:3px}';
		}
		marks.textContent = pick ? css : '';
	}

	function send(message) {
		window.parent.postMessage(message, studio);
	}

	// The registered part that is exactly this element, or ''.
	function partOf(el) {
		for (var i = 0; i < ids.length; i++) {
			try {
				if (el.matches(cfg.elements[ids[i]])) {
					return ids[i];
				}
			} catch (e) {}
		}
		return '';
	}

	// The innermost registered part around an element.
	function find(el) {
		while (el && el.nodeType === 1) {
			var id = partOf(el);
			if (id) {
				return { id: id, el: el };
			}
			el = el.parentElement;
		}
		return null;
	}

	// What a click on an element means: not an icon's lines or a bold word, but the element they are in.
	var SMALL = /^(svg|path|g|use|circle|rect|line|polyline|polygon|ellipse|i|b|em|strong|small|br|sup|sub)$/i;
	function pickable(el) {
		while (el && el.parentElement && el !== document.body && SMALL.test(el.tagName)) {
			el = el.parentElement;
		}
		return el;
	}

	function norm(text) {
		return String(text || '').replace(/\s+/g, ' ').trim();
	}

	// A name anyone understands: the part's own name, else what kind of thing it is, with its first words.
	function nameOf(el) {
		var id = partOf(el);
		if (id && names[id]) {
			return names[id];
		}
		var cls = ' ' + (typeof el.className === 'string' ? el.className : '') + ' ';
		var tag = el.tagName.toLowerCase();
		var kind = /\set_pb_section\s/.test(cls) ? words.section : /\set_pb_row\s/.test(cls) ? words.row : /\set_pb_column\s/.test(cls) ? words.column :
			/\set_pb_module\s/.test(cls) ? words.module : /\saimp-card\s/.test(cls) ? words.card : /\saimp-grid\s/.test(cls) ? words.grid :
			/^h[1-6]$/.test(tag) ? words.title : tag === 'p' ? words.paragraph : tag === 'a' ? words.link : tag === 'button' ? words.button :
			tag === 'img' || tag === 'picture' ? words.picture : tag === 'li' ? words.item : (tag === 'ul' || tag === 'ol') ? words.list :
			tag === 'form' ? words.form : /^(input|select|textarea)$/.test(tag) ? words.field : /^(span|small|strong|em|label)$/.test(tag) ? words.text : words.block;
		// A plain block: what it is from its own class (e.g. "Block · shop chips"), so blocks can be told apart.
		var own = kind === words.block ? (cls.match(/\saimp-([a-z][a-z0-9-]*)\s/) || [])[1] : '';
		if (own) {
			kind += ' · ' + own.replace(/-/g, ' ');
		}
		var text = tag === 'img' ? '' : norm(el.textContent);
		return (kind || tag) + (text && text.length < 400 ? ' "' + (text.length > 28 ? text.slice(0, 28) + '…' : text) + '"' : '');
	}

	/*
	 * A selector for any element: its id, else tags and classes (with :nth-of-type() among equal brothers), up to five
	 * levels. Inside the page's own content it only counts on this page (body.page-id-… / body.postid-…).
	 */
	var SKIP_CLASS = /^(is-|has-|aimp-design|et_animated|et-animated|et_had_animation|et-waypoint|et_pb_section_parallax|active|current|hover|focus|lazy|loaded|swiper-slide-)/;
	function selectorOf(el) {
		var parts = [];
		var node = el;
		while (node && node.nodeType === 1 && node !== document.body && parts.length < 5) {
			if (node.id && /^[A-Za-z][\w-]*$/.test(node.id)) {
				parts.unshift('#' + node.id);
				break;
			}
			var classes = Array.prototype.filter.call(node.classList, function (c) {
				return /^[A-Za-z][\w-]*$/.test(c) && !SKIP_CLASS.test(c);
			}).slice(0, 3);
			var part = node.tagName.toLowerCase() + classes.map(function (c) {
				return '.' + c;
			}).join('');
			var parent = node.parentElement;
			if (parent) {
				var same = Array.prototype.filter.call(parent.children, function (s) {
					return s.tagName === node.tagName;
				});
				if (same.length > 1) {
					part += ':nth-of-type(' + (same.indexOf(node) + 1) + ')';
				}
			}
			parts.unshift(part);
			node = parent;
		}
		var selector = parts.join(' > ');
		var page = (document.body.className.match(/(?:^|\s)((?:page-id|postid)-\d+)(?:\s|$)/) || [])[1];
		// The site's header and footer are on every page; the page header (also a <header>) belongs to this page.
		if (page && !el.closest('.aimp-header, .aimp-footer, .aimp-side-menu, .aimp-tabbar, header:not(.aimp-page-header), footer')) {
			selector = 'body.' + page + ' ' + selector;
		}
		return selector;
	}

	// The clicked element and those around it (innermost first): what the studio offers to choose from.
	function pathOf(el, nodes) {
		var path = [];
		var node = el;
		while (node && node.nodeType === 1 && node !== document.body && path.length < 7) {
			var box = node.getBoundingClientRect();
			// Leave out wrappers of exactly the same size as the element inside them.
			var last = path.length ? path[path.length - 1] : null;
			if (!last || last.part || partOf(node) || Math.abs(box.width - last.w) > 2 || Math.abs(box.height - last.h) > 2) {
				path.push({ sel: selectorOf(node), name: nameOf(node), part: partOf(node), w: box.width, h: box.height });
				if (nodes) {
					nodes.push(node);
				}
			}
			node = node.parentElement;
		}
		return path.map(function (row) {
			return { sel: row.sel, name: row.name, part: row.part };
		});
	}

	/*
	 * The shop text under the mouse: the page lists the texts it shows (window.aimpDesignTexts, [ original, shown ];
	 * class-aimp-design.php). Texts with a number or name in them (%s, %d) are found as a pattern.
	 */
	var textIndex = null;
	function buildIndex() {
		textIndex = { exact: {}, patterns: [] };
		(window.aimpDesignTexts || []).forEach(function (row) {
			var shown = norm(row[1]);
			if (!shown) {
				return;
			}
			if (/%(\d+\$)?[sdfu]/.test(shown)) {
				var parts = shown.split(/%(?:\d+\$)?[sdfu]/).map(function (part) {
					return part.replace(/[.*+?^${}()|\[\]\\]/g, '\\$&');
				});
				if (parts.join('').length >= 3) {
					textIndex.patterns.push([new RegExp('^' + parts.join('.+?') + '$'), row[0]]);
				}
			} else if (!textIndex.exact[shown]) {
				textIndex.exact[shown] = row[0];
			}
		});
	}
	function textAt(el) {
		if (!textIndex) {
			buildIndex();
		}
		// The clicked element, then up to three around it (a word in a button, a line in a card).
		for (var depth = 0; el && el.nodeType === 1 && depth < 4; depth++, el = el.parentElement) {
			var text = norm(el.textContent);
			if (!text || text.length > 300) {
				break;
			}
			if (textIndex.exact[text]) {
				return { original: textIndex.exact[text], shown: text };
			}
			for (var i = 0; i < textIndex.patterns.length; i++) {
				if (textIndex.patterns[i][0].test(text)) {
					return { original: textIndex.patterns[i][1], shown: text };
				}
			}
		}
		return null;
	}

	// The element a click selects: the shop part around it, else the element itself.
	function target(el) {
		var hit = find(el);
		return hit ? hit.el : pickable(el);
	}

	document.addEventListener('mouseover', function (e) {
		if (!pick || (e.target.closest && e.target.closest('[data-aimp-ds-layer]'))) {
			return;
		}
		var el = target(e.target);
		if (hovered && hovered !== el) {
			hovered.removeAttribute('data-aimp-design-hover');
			hovered = null;
		}
		if (el && el !== document.body && el !== document.documentElement) {
			hovered = el;
			hovered.setAttribute('data-aimp-design-hover', '');
			var box = el.getBoundingClientRect();
			label.textContent = nameOf(el);
			label.style.display = 'block';
			label.style.left = Math.max(0, Math.min(box.left, window.innerWidth - 160)) + 'px';
			label.style.top = (box.top > 24 ? box.top - 22 : Math.max(0, box.top) + 2) + 'px';
		} else {
			label.style.display = 'none';
		}
	}, true);
	document.addEventListener('mouseleave', function () {
		label.style.display = 'none';
	});

	var drag = null;
	var dragged = false;
	document.addEventListener('mousedown', function (e) {
		if (!pick || !selectedSel || e.button !== 0 || spaceDrag || (e.target.closest && e.target.closest('[data-aimp-ds-layer]'))) {
			return;
		}
		var el = null;
		try {
			el = e.target.closest(prefixed(selectedSel));
		} catch (err) {}
		if (!el) {
			return;
		}
		// The page header and its title are not dragged: the title has the same place on every page (3.51.1).
		if (el.closest('.aimp-page-header')) {
			return;
		}
		drag = { x: e.clientX, y: e.clientY, started: false };
		e.preventDefault();
	}, true);
	document.addEventListener('mousemove', function (e) {
		if (!drag) {
			return;
		}
		var dx = e.clientX - drag.x;
		var dy = e.clientY - drag.y;
		if (!drag.started && Math.abs(dx) + Math.abs(dy) < 4) {
			return;
		}
		if (!drag.started) {
			drag.started = true;
			send({ type: 'aimp-design-drag', phase: 'start', dx: 0, dy: 0 });
		}
		send({ type: 'aimp-design-drag', phase: 'move', dx: dx, dy: dy });
	}, true);
	document.addEventListener('mouseup', function (e) {
		if (drag && drag.started) {
			send({ type: 'aimp-design-drag', phase: 'end', dx: e.clientX - drag.x, dy: e.clientY - drag.y });
			// The click that ends a drag does not select something else.
			dragged = true;
		}
		drag = null;
	}, true);
	/*
	 * Resizing like a picture in Word: 8 handles around the chosen part. A corner keeps the proportions (with Shift:
	 * free), a side changes only the width or the height. The studio keeps the new size for the screen shown.
	 */
	var handles = document.createElement('div');
	handles.setAttribute('data-aimp-ds-layer', '');
	handles.setAttribute('aria-hidden', 'true');
	handles.style.cssText = 'position:fixed;inset:0;z-index:2147483645;pointer-events:none;display:none';
	(document.body || document.documentElement).appendChild(handles);
	var CURSORS = { nw: 'nwse-resize', se: 'nwse-resize', ne: 'nesw-resize', sw: 'nesw-resize', n: 'ns-resize', s: 'ns-resize', e: 'ew-resize', w: 'ew-resize' };
	Object.keys(CURSORS).forEach(function (dir) {
		var h = document.createElement('div');
		h.setAttribute('data-dir', dir);
		h.style.cssText = 'position:fixed;width:12px;height:12px;margin:-6px 0 0 -6px;box-sizing:border-box;background:#fff;border:2px solid #b38f4f;border-radius:2px;box-shadow:0 1px 3px rgba(0,0,0,.35);pointer-events:auto;cursor:' + CURSORS[dir];
		handles.appendChild(h);
	});
	var sizeTag = document.createElement('div');
	sizeTag.style.cssText = 'position:fixed;display:none;padding:2px 8px;border-radius:100px;background:#8a6a33;color:#fff;font:600 12px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;white-space:nowrap';
	handles.appendChild(sizeTag);
	var resize = null;
	function chosenEl() {
		try {
			return selectedSel ? document.querySelector(prefixed(selectedSel)) : null;
		} catch (e) {
			return null;
		}
	}
	function drawHandles() {
		if (resize) {
			return;
		}
		var el = pick ? chosenEl() : null;
		var r = el ? el.getBoundingClientRect() : null;
		if (!r || r.width < 4 || r.height < 4) {
			handles.style.display = 'none';
			return;
		}
		handles.style.display = 'block';
		var x = { w: r.left, n: r.left + r.width / 2, s: r.left + r.width / 2, e: r.right, nw: r.left, sw: r.left, ne: r.right, se: r.right };
		var y = { n: r.top, s: r.bottom, e: r.top + r.height / 2, w: r.top + r.height / 2, nw: r.top, ne: r.top, sw: r.bottom, se: r.bottom };
		handles.querySelectorAll('[data-dir]').forEach(function (h) {
			var dir = h.getAttribute('data-dir');
			h.style.left = x[dir] + 'px';
			h.style.top = y[dir] + 'px';
		});
	}
	window.addEventListener('scroll', drawHandles, true);
	window.addEventListener('resize', drawHandles);
	// The page can change under it (styles, pictures loading): kept in place.
	window.setInterval(drawHandles, 400);
	handles.addEventListener('mousedown', function (e) {
		var dir = e.target.getAttribute && e.target.getAttribute('data-dir');
		var el = chosenEl();
		if (!dir || !el || e.button !== 0) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		var r = el.getBoundingClientRect();
		resize = { dir: dir, x: e.clientX, y: e.clientY, w: r.width, h: r.height, started: false, last: null };
	});
	document.addEventListener('mousemove', function (e) {
		if (!resize) {
			return;
		}
		var d = resize.dir;
		var w = resize.w;
		var h = resize.h;
		if (d.indexOf('e') !== -1) {
			w += e.clientX - resize.x;
		}
		if (d.indexOf('w') !== -1) {
			w -= e.clientX - resize.x;
		}
		if (d.indexOf('s') !== -1) {
			h += e.clientY - resize.y;
		}
		if (d.indexOf('n') !== -1) {
			h -= e.clientY - resize.y;
		}
		// A corner keeps the proportions (the larger change leads), unless Shift is held.
		if (d.length === 2 && !e.shiftKey) {
			if (Math.abs(w - resize.w) / resize.w >= Math.abs(h - resize.h) / resize.h) {
				h = w * resize.h / resize.w;
			} else {
				w = h * resize.w / resize.h;
			}
		}
		w = Math.max(20, Math.round(w));
		h = Math.max(20, Math.round(h));
		resize.last = { w: d === 'n' || d === 's' ? null : w, h: d === 'e' || d === 'w' ? null : h };
		send({ type: 'aimp-design-resize', phase: resize.started ? 'move' : 'start', w: resize.last.w, h: resize.last.h });
		resize.started = true;
		sizeTag.textContent = (resize.last.w !== null ? w : Math.round(resize.w)) + ' × ' + (resize.last.h !== null ? h : Math.round(resize.h)) + ' px';
		sizeTag.style.display = 'block';
		sizeTag.style.left = Math.min(e.clientX + 14, window.innerWidth - 120) + 'px';
		sizeTag.style.top = Math.max(4, e.clientY - 28) + 'px';
		e.preventDefault();
	}, true);
	document.addEventListener('mouseup', function () {
		if (!resize) {
			return;
		}
		if (resize.started && resize.last) {
			send({ type: 'aimp-design-resize', phase: 'end', w: resize.last.w, h: resize.last.h });
			// The click that ends the resizing does not select something else.
			dragged = true;
		}
		resize = null;
		sizeTag.style.display = 'none';
		window.setTimeout(drawHandles, 300);
	}, true);

	document.addEventListener('keydown', function (e) {
		if (pick && /^Arrow(Left|Right|Up|Down)$/.test(e.key) && !/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) {
			e.preventDefault();
			send({ type: 'aimp-design-key', key: e.key, shift: e.shiftKey });
		}
	}, true);

	/*
	 * "Edit texts on the page": a click on a text the studio knows (a Divi text, one of the studio's own texts or a
	 * shop text) makes it editable in place; Enter or clicking elsewhere keeps it, Esc goes back.
	 */
	var editStyle = document.createElement('style');
	editStyle.textContent = 'html.aimp-ds-editing :is(p,h1,h2,h3,h4,h5,h6,a,span,li,button,small,strong,em,label,figcaption,dt,dd):hover{outline:1px dashed #b38f4f;outline-offset:2px;cursor:text}' +
		'[data-aimp-inline-editing]{outline:2px solid #b38f4f!important;outline-offset:3px;background:rgba(255,255,255,.6);cursor:text}';
	document.head.appendChild(editStyle);

	function stripTags(html) {
		var box = document.createElement('div');
		box.innerHTML = html || '';
		return box.textContent || '';
	}

	function inlineMatch(el) {
		var data = window.aimpInlineTexts || {};
		var divi = data.divi || [];
		var settings = data.settings || [];
		for (var depth = 0; el && el.nodeType === 1 && el !== document.body && depth < 6; depth++, el = el.parentElement) {
			var text = norm(el.innerText);
			if (!text || text.length > 4000) {
				continue;
			}
			for (var i = 0; i < divi.length; i++) {
				if (norm(stripTags(divi[i].shown)) === text) {
					return { el: el, kind: 'divi', entry: divi[i], rich: divi[i].rich };
				}
			}
			for (var j = 0; j < settings.length; j++) {
				if (norm(settings[j].value) === text) {
					return { el: el, kind: 'setting', entry: settings[j], rich: false };
				}
			}
		}
		return null;
	}

	function startInline(target) {
		var match = inlineMatch(target);
		var shop = match ? null : textAt(target);
		if (!match && shop) {
			// A shop text: the element whose words are that text.
			var el = target;
			for (var d = 0; el && d < 4 && norm(el.textContent) !== shop.shown; d++) {
				el = el.parentElement;
			}
			match = { el: el && norm(el.textContent) === shop.shown ? el : target, kind: 'shop', entry: shop, rich: false };
		}
		if (!match) {
			send({ type: 'aimp-design-inline-none' });
			return;
		}
		var node = match.el;
		var before = node.innerHTML;
		var beforeText = norm(node.innerText);
		var long = match.rich || (match.entry && (match.entry.type === 'langlong' || match.entry.type === 'long'));
		node.setAttribute('data-aimp-inline-editing', '1');
		node.setAttribute('contenteditable', match.rich ? 'true' : 'plaintext-only');
		if (node.contentEditable !== 'plaintext-only' && !match.rich) {
			node.setAttribute('contenteditable', 'true');
		}
		node.focus();
		var range = document.createRange();
		range.selectNodeContents(node);
		range.collapse(false);
		var selection = window.getSelection();
		selection.removeAllRanges();
		selection.addRange(range);
		editing = { el: node };

		function finish(keep) {
			node.removeEventListener('keydown', onKey);
			node.removeEventListener('blur', onBlur);
			node.removeAttribute('contenteditable');
			node.removeAttribute('data-aimp-inline-editing');
			editing = null;
			if (!keep) {
				node.innerHTML = before;
				return;
			}
			var value = match.rich ? node.innerHTML.trim() : node.innerText.replace(/ /g, ' ').trim();
			if (norm(match.rich ? stripTags(value) : value) === beforeText || value === '') {
				node.innerHTML = before;
				return;
			}
			var lang = (window.aimpInlineTexts || {}).lang || 'nl';
			var message = { type: 'aimp-design-inline', kind: match.kind, value: value, lang: lang };
			if (match.kind === 'divi') {
				message.post = match.entry.post;
				message.hash = match.entry.hash;
				message.nl = match.entry.nl;
				message.fr = match.entry.fr;
				message.en = match.entry.en;
				message.rich = !!match.rich;
			} else if (match.kind === 'setting') {
				message.key = match.entry.key;
				message.base = match.entry.base;
				message.fieldType = match.entry.type;
				message.label = match.entry.label;
				message.hints = match.entry.hints;
			} else {
				message.original = match.entry.original;
			}
			send(message);
		}

		function onKey(e) {
			if (e.key === 'Escape') {
				e.preventDefault();
				finish(false);
			} else if (e.key === 'Enter' && !e.shiftKey && !long) {
				e.preventDefault();
				finish(true);
			}
		}

		function onBlur() {
			finish(true);
		}

		node.addEventListener('keydown', onKey);
		node.addEventListener('blur', onBlur);
	}

	document.addEventListener('click', function (e) {
		if (editMode) {
			// Clicks inside the text being edited move the cursor; any other click edits that text (links stay put).
			if (editing && editing.el.contains(e.target)) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			if (editing) {
				editing.el.blur();
			}
			startInline(e.target);
			return;
		}
		// A click on the studio's own layers (handles, labels) selects nothing.
		if (pick && e.target.closest && e.target.closest('[data-aimp-ds-layer]')) {
			dragged = false;
			e.preventDefault();
			e.stopPropagation();
			return;
		}
		if (pick && dragged) {
			dragged = false;
			e.preventDefault();
			e.stopPropagation();
			return;
		}
		if (pick) {
			e.preventDefault();
			e.stopPropagation();
			var hit = find(e.target);
			var inner = pickable(e.target);
			if (!inner || inner === document.body) {
				return;
			}
			var text = textAt(e.target);
			var nodes = [];
			var path = pathOf(inner, nodes);
			// The shop part when there is one; the studio also offers the elements inside and around it.
			var chosen = 0;
			if (hit) {
				path.forEach(function (row, i) {
					if (row.part === hit.id && !chosen) {
						chosen = i;
					}
				});
				if (!path[chosen] || path[chosen].part !== hit.id) {
					path.push({ sel: cfg.elements[hit.id], name: names[hit.id] || hit.id, part: hit.id });
					nodes.push(hit.el);
					chosen = path.length - 1;
				}
			}
			peekNodes = nodes;
			selectedSel = path[chosen].part ? cfg.elements[path[chosen].part] : path[chosen].sel;
			drawMarks();
			// The page's own post (product, page) for "Edit this in WordPress"; not for cards of other products, the
			// header, footer or shop lists (they are not this post's words).
			var post = e.target.closest('.aimp-card, .aimp-header, .aimp-footer, .aimp-shop, .aimp-side-menu') ? null : (document.body.className.match(/(?:^|\s)(?:postid|page-id)-(\d+)/) || [])[1];
			// A box of the mosaic: its number and the mosaic's kind, to choose its product in the studio.
			var tile = e.target.closest('.aimp-mosaic-tile');
			var tileNo = tile ? parseInt((tile.className.match(/aimp-mosaic-tile--(\d+)/) || [])[1], 10) || 0 : 0;
			var grid = tile ? tile.closest('[data-mosaic-type]') : null;
			send({
				type: 'aimp-design-select',
				tile: tileNo,
				tileType: grid ? grid.getAttribute('data-mosaic-type') : '',
				id: path[chosen].part,
				path: path,
				chosen: chosen,
				text: text,
				textMiss: !text && !!norm(e.target.textContent),
				post: post ? parseInt(post, 10) : 0,
				divi: !!document.querySelector('#et-boc .et_pb_section, .et-l--post .et_pb_section')
			});
			return;
		}
		// Browsing: links on this site stay in the preview.
		var link = e.target.closest('a[href]');
		if (link && link.origin === window.location.origin && !link.target) {
			var url = new URL(link.href);
			url.searchParams.set(cfg.param, '1');
			link.href = url.toString();
		}
	}, true);

	window.addEventListener('message', function (e) {
		if (e.origin !== studio || !e.data || typeof e.data !== 'object') {
			return;
		}
		var data = e.data;
		if (data.type === 'aimp-design-spacing') {
			spacing = { on: !!data.on, selector: String(data.selector || ''), labels: data.labels || {} };
			redrawBands();
		} else if (data.type === 'aimp-design-css') {
			style.textContent = String(data.css || '');
			redrawBands();
			// Slower or faster animations (the speed script of class-aimp-design.php).
			window.aimpDesignSpeed = data.speeds || {};
			if (window.aimpDesignSpeedApply) {
				window.aimpDesignSpeedApply();
			}
		} else if (data.type === 'aimp-design-reload') {
			// Own texts are made on the server: the page is loaded again, at the same place.
			try {
				window.sessionStorage.setItem('aimp-design-scroll', String(window.scrollY));
			} catch (err) {}
			window.location.reload();
		} else if (data.type === 'aimp-design-editmode') {
			editMode = !!data.on;
			document.documentElement.classList.toggle('aimp-ds-editing', editMode);
		} else if (data.type === 'aimp-design-pick') {
			pick = !!data.on;
			if (!pick && hovered) {
				hovered.removeAttribute('data-aimp-design-hover');
				hovered = null;
			}
			label.style.display = 'none';
			drawMarks();
			redrawBands();
		} else if (data.type === 'aimp-design-focus') {
			showFocus(data);
		} else if (data.type === 'aimp-design-peek') {
			// A chip of the clicked path (by its place) or a layer (by its selector); none: off.
			peekEl = null;
			if (typeof data.index === 'number' && peekNodes[data.index]) {
				peekEl = peekNodes[data.index];
			} else if (data.selector) {
				try {
					peekEl = document.querySelector(prefixed(String(data.selector)));
				} catch (err) {}
			}
			peekName = String(data.label || '');
			if (peekEl) {
				var pr = peekEl.getBoundingClientRect();
				if (pr.bottom < 0 || pr.top > window.innerHeight) {
					peekEl.scrollIntoView({ block: 'center', behavior: 'smooth' });
				}
			}
			drawPeek();
		} else if (data.type === 'aimp-design-show') {
			// A part chosen in the studio (registered, or an own part with its selector).
			selectedSel = data.selector || (data.id && cfg.elements[data.id] ? cfg.elements[data.id] : '');
			drawMarks();
			var el = null;
			try {
				el = selectedSel ? document.querySelector(prefixed(selectedSel)) : null;
			} catch (err) {}
			if (el && data.scroll !== false) {
				el.scrollIntoView({ block: 'center', behavior: 'smooth' });
			}
			send({ type: 'aimp-design-found', id: data.id || '', found: !!el });
		}
	});

	drawMarks();
	try {
		var scroll = window.sessionStorage.getItem('aimp-design-scroll');
		if (scroll !== null) {
			window.sessionStorage.removeItem('aimp-design-scroll');
			window.scrollTo(0, parseInt(scroll, 10) || 0);
		}
	} catch (err) {}
	// The parts on this page, so the studio lists those first.
	var present = ids.filter(function (id) {
		try {
			return !!document.querySelector(cfg.elements[id]);
		} catch (e) {
			return false;
		}
	});
	function layers() {
		var found = [];
		present.forEach(function (id) {
			var el = null;
			try {
				el = document.querySelector(prefixed(cfg.elements[id]));
			} catch (e) {}
			if (el) {
				found.push({ id: id, el: el });
			}
		});
		found.sort(function (a, b) {
			if (a.el === b.el) {
				return 0;
			}
			return a.el.compareDocumentPosition(b.el) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1;
		});
		return found.map(function (row) {
			var depth = 0;
			found.forEach(function (other) {
				if (other.el !== row.el && other.el.contains(row.el)) {
					depth++;
				}
			});
			return { id: row.id, depth: depth };
		});
	}
	// The texts are listed at the very end of the page: tell the studio once the page is read.
	function ready() {
		send({
			type: 'aimp-design-ready',
			url: window.location.href,
			title: document.title,
			present: present,
			layers: layers(),
			version: cfg.version || '',
			// The page itself (its page header settings are per page).
			postId: document.body.classList.contains('single-product') ? 'product' : ((document.body.className.match(/(?:^|\s)page-id-(\d+)/) || [])[1] || 'other'),
			texts: (window.aimpDesignTexts || []).map(function (row) {
				return row[0];
			})
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', ready);
	} else {
		ready();
	}
})();
