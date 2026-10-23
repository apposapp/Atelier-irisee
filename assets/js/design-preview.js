/**
 * Design studio preview (class-aimp-design.php): runs inside the studio's preview frame. A click on a part of the page
 * selects it in the studio; the studio sends the draft's styles while you edit. Links keep the preview on.
 */
(function () {
	'use strict';

	var cfg = window.aimpDesignPreview;
	if (!cfg || window.parent === window) {
		return;
	}
	var studio = new URL(cfg.origin, window.location.href).origin;
	var ids = Object.keys(cfg.elements);
	var pick = true;
	var selected = '';
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

	function prefixed(selector, extra) {
		return selector.split(',').map(function (part) {
			return 'html body ' + part.trim() + (extra || '');
		}).join(',');
	}

	function drawMarks() {
		var css = '[data-aimp-design-hover]{outline:2px dashed #b38f4f!important;outline-offset:2px;cursor:pointer!important}';
		if (selected && cfg.elements[selected]) {
			css += prefixed(cfg.elements[selected]) + '{outline:3px solid #b38f4f!important;outline-offset:3px}';
		}
		marks.textContent = pick ? css : '';
	}

	function send(message) {
		window.parent.postMessage(message, studio);
	}

	// The innermost registered part around an element.
	function find(el) {
		while (el && el.nodeType === 1) {
			for (var i = 0; i < ids.length; i++) {
				try {
					if (el.matches(cfg.elements[ids[i]])) {
						return { id: ids[i], el: el };
					}
				} catch (e) {}
			}
			el = el.parentElement;
		}
		return null;
	}

	/*
	 * The shop text under the mouse: the page lists the texts it shows (window.aimpDesignTexts, [ original, shown ];
	 * class-aimp-design.php). Texts with a number or name in them (%s, %d) are found as a pattern.
	 */
	var textIndex = null;
	function norm(text) {
		return String(text || '').replace(/\s+/g, ' ').trim();
	}
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

	document.addEventListener('mouseover', function (e) {
		if (!pick) {
			return;
		}
		var hit = find(e.target);
		if (hovered && (!hit || hit.el !== hovered)) {
			hovered.removeAttribute('data-aimp-design-hover');
			hovered = null;
		}
		if (hit && hit.el !== hovered) {
			hovered = hit.el;
			hovered.setAttribute('data-aimp-design-hover', '');
		}
	}, true);

	document.addEventListener('click', function (e) {
		if (pick) {
			var hit = find(e.target);
			var text = textAt(e.target);
			e.preventDefault();
			e.stopPropagation();
			if (hit || text) {
				selected = hit ? hit.id : '';
				drawMarks();
				// The part, and the clicked shop text (or that the words clicked are not a shop text).
				send({ type: 'aimp-design-select', id: hit ? hit.id : '', text: text, textMiss: !text && !!norm(e.target.textContent) });
			}
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
			drawMarks();
		} else if (data.type === 'aimp-design-show') {
			selected = data.id || '';
			drawMarks();
			var el = selected && cfg.elements[selected] ? document.querySelector(cfg.elements[selected]) : null;
			if (el) {
				el.scrollIntoView({ block: 'center', behavior: 'smooth' });
			}
			send({ type: 'aimp-design-found', id: selected, found: !!el });
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
