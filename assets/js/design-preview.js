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
	document.documentElement.appendChild(label);

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
			/^h[1-6]$/.test(tag) ? words.title : tag === 'p' ? words.paragraph : tag === 'a' ? words.link : tag === 'button' ? words.button :
			tag === 'img' ? words.picture : tag === 'li' ? words.item : (tag === 'ul' || tag === 'ol') ? words.list : words.block;
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
		if (page && !el.closest('.aimp-header, .aimp-footer, .aimp-side-menu, .aimp-tabbar, header, footer')) {
			selector = 'body.' + page + ' ' + selector;
		}
		return selector;
	}

	// The clicked element and those around it (innermost first): what the studio offers to choose from.
	function pathOf(el) {
		var path = [];
		var node = el;
		while (node && node.nodeType === 1 && node !== document.body && path.length < 7) {
			var box = node.getBoundingClientRect();
			// Leave out wrappers of exactly the same size as the element inside them.
			var last = path.length ? path[path.length - 1] : null;
			if (!last || last.part || partOf(node) || Math.abs(box.width - last.w) > 2 || Math.abs(box.height - last.h) > 2) {
				path.push({ sel: selectorOf(node), name: nameOf(node), part: partOf(node), w: box.width, h: box.height });
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
		if (!pick) {
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

	document.addEventListener('click', function (e) {
		if (pick) {
			e.preventDefault();
			e.stopPropagation();
			var hit = find(e.target);
			var inner = pickable(e.target);
			if (!inner || inner === document.body) {
				return;
			}
			var text = textAt(e.target);
			var path = pathOf(inner);
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
					chosen = path.length - 1;
				}
			}
			selectedSel = path[chosen].part ? cfg.elements[path[chosen].part] : path[chosen].sel;
			drawMarks();
			// The page's own post (product, page) for "Edit this in WordPress"; not for cards of other products, the
			// header, footer or shop lists (they are not this post's words).
			var post = e.target.closest('.aimp-card, .aimp-header, .aimp-footer, .aimp-shop, .aimp-side-menu') ? null : (document.body.className.match(/(?:^|\s)(?:postid|page-id)-(\d+)/) || [])[1];
			send({
				type: 'aimp-design-select',
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
		if (data.type === 'aimp-design-css') {
			style.textContent = String(data.css || '');
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
		} else if (data.type === 'aimp-design-pick') {
			pick = !!data.on;
			if (!pick && hovered) {
				hovered.removeAttribute('data-aimp-design-hover');
				hovered = null;
			}
			label.style.display = 'none';
			drawMarks();
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
	// The texts are listed at the very end of the page: tell the studio once the page is read.
	function ready() {
		send({
			type: 'aimp-design-ready',
			url: window.location.href,
			title: document.title,
			present: present,
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
