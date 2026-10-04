/**
 * Atelier Irisee header: the side menu that slides in from the left.
 * Opens with "Menu", closes with ×, Esc or a click next to it. Focus stays in the menu while it is open,
 * and items with sub-items get a small arrow to open them.
 */
(function () {
	'use strict';

	function setup(header) {
		var panel = header.querySelector('.aimp-side-menu');
		var backdrop = header.querySelector('.aimp-side-menu-backdrop');
		var toggle = header.querySelector('[data-aimp-menu-open]');
		if (!panel || !toggle) {
			return;
		}
		var lastFocus = null;

		panel.inert = true;

		function focusables() {
			return Array.prototype.filter.call(panel.querySelectorAll('a[href], button:not([disabled])'), function (el) {
				return el.offsetParent !== null && !el.closest('.aimp-sub-wrap[inert]');
			});
		}

		function open() {
			lastFocus = document.activeElement;
			panel.inert = false;
			panel.setAttribute('aria-hidden', 'false');
			backdrop.hidden = false;
			// Next frame, so the fade and slide animate.
			window.requestAnimationFrame(function () {
				header.classList.add('is-menu-open');
			});
			toggle.setAttribute('aria-expanded', 'true');
			document.documentElement.classList.add('aimp-menu-open');
			var first = focusables()[0];
			if (first) {
				first.focus();
			}
		}

		function close() {
			header.classList.remove('is-menu-open');
			toggle.setAttribute('aria-expanded', 'false');
			panel.setAttribute('aria-hidden', 'true');
			panel.inert = true;
			document.documentElement.classList.remove('aimp-menu-open');
			setTimeout(function () {
				if (!header.classList.contains('is-menu-open')) {
					backdrop.hidden = true;
				}
			}, 350);
			if (lastFocus && lastFocus.focus) {
				lastFocus.focus();
			}
		}

		toggle.addEventListener('click', open);
		header.querySelectorAll('[data-aimp-menu-close]').forEach(function (el) {
			el.addEventListener('click', close);
		});

		document.addEventListener('keydown', function (e) {
			if (!header.classList.contains('is-menu-open')) {
				return;
			}
			if (e.key === 'Escape') {
				close();
			} else if (e.key === 'Tab') {
				var items = focusables();
				if (!items.length) {
					return;
				}
				var first = items[0];
				var last = items[items.length - 1];
				if (e.shiftKey && document.activeElement === first) {
					e.preventDefault();
					last.focus();
				} else if (!e.shiftKey && document.activeElement === last) {
					e.preventDefault();
					first.focus();
				}
			}
		});

		// Sub-items: an arrow button next to the item opens them; they fade and unfold (see header.css).
		panel.querySelectorAll('.menu-item-has-children').forEach(function (item, i) {
			var sub = item.querySelector(':scope > .sub-menu');
			var link = item.querySelector(':scope > a');
			if (!sub || !link) {
				return;
			}
			var wrap = document.createElement('div');
			wrap.className = 'aimp-sub-wrap';
			wrap.id = 'aimp-sub-menu-' + i;
			sub.parentNode.insertBefore(wrap, sub);
			wrap.appendChild(sub);

			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'aimp-side-menu-sub-toggle';
			btn.setAttribute('aria-controls', wrap.id);
			btn.setAttribute('aria-label', link.textContent.trim());
			link.insertAdjacentElement('afterend', btn);

			var setOpen = function (open) {
				item.classList.toggle('is-open', open);
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
				wrap.inert = !open; // Closed sub-items can't be reached with Tab.
			};
			setOpen(item.classList.contains('current-menu-ancestor'));
			btn.addEventListener('click', function () {
				setOpen(!item.classList.contains('is-open'));
			});
		});
	}

	function boot() {
		document.querySelectorAll('[data-aimp-header]').forEach(function (header) {
			if (!header.aimpReady) {
				header.aimpReady = true;
				setup(header);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
