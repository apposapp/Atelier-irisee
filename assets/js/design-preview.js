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
			e.preventDefault();
			e.stopPropagation();
			if (hit) {
				selected = hit.id;
				drawMarks();
				send({ type: 'aimp-design-select', id: hit.id });
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
	send({ type: 'aimp-design-ready', url: window.location.href });
})();
