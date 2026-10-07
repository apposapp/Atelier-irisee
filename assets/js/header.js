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

	/**
	 * --aimp-header-offset on the page: the height of the sticky header (plus the WordPress admin bar),
	 * so sticky panels, pictures and totals stop below the header instead of sliding under it.
	 */
	function trackOffset(header) {
		var update = function () {
			var bar = document.getElementById('wpadminbar');
			var barHeight = bar && window.getComputedStyle(bar).position === 'fixed' ? bar.offsetHeight : 0;
			document.documentElement.style.setProperty('--aimp-header-offset', header.offsetHeight + barHeight + 'px');
		};
		update();
		window.addEventListener('resize', update);
		window.addEventListener('load', update);
		if (window.ResizeObserver) {
			new window.ResizeObserver(update).observe(header);
		}
	}

	// Scrolled down: a slimmer header, and the gold info bar folds away (header.css, .is-scrolled).
	function trackScroll(header) {
		var ticking = false;
		var update = function () {
			ticking = false;
			// Two thresholds, so the header doesn't flicker when its own height change moves the page.
			var y = window.scrollY;
			if (y > 60) {
				header.classList.add('is-scrolled');
			} else if (y < 10) {
				header.classList.remove('is-scrolled');
			}
		};
		window.addEventListener(
			'scroll',
			function () {
				if (!ticking) {
					ticking = true;
					window.requestAnimationFrame(update);
				}
			},
			{ passive: true }
		);
		update();
	}

	// Info bar: when the items don't fit next to each other (phones), they take turns.
	function setupInfoBar(header) {
		var bar = header.querySelector('[data-aimp-info-bar]');
		var items = bar ? bar.querySelectorAll('li') : [];
		if (items.length < 2) {
			return;
		}
		var list = bar.querySelector('ul');
		var index = 0;
		var timer = null;
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var show = function () {
			items.forEach(function (item, i) {
				item.classList.toggle('is-current', i === index);
			});
		};
		var check = function () {
			bar.classList.remove('is-rotating');
			var fits = list.scrollWidth <= bar.clientWidth;
			clearInterval(timer);
			if (!fits) {
				bar.classList.add('is-rotating');
				show();
				if (!reduce) {
					timer = setInterval(function () {
						index = (index + 1) % items.length;
						show();
					}, 3500);
				}
			}
		};
		check();
		window.addEventListener('resize', check);
	}

	// Language: the active flag opens a list with the other languages (links that also set the cookie).
	function setupLanguages(header) {
		var wrap = header.querySelector('[data-aimp-lang-switch]');
		if (!wrap) {
			return;
		}
		var toggle = wrap.querySelector('.aimp-lang-toggle');
		var list = wrap.querySelector('.aimp-lang-list');
		var setOpen = function (open) {
			wrap.classList.toggle('is-open', open);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			list.hidden = !open;
		};
		toggle.addEventListener('click', function (e) {
			e.stopPropagation();
			setOpen(!wrap.classList.contains('is-open'));
		});
		document.addEventListener('click', function (e) {
			if (!wrap.contains(e.target)) {
				setOpen(false);
			}
		});
		wrap.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				setOpen(false);
				toggle.focus();
			}
		});
		list.querySelectorAll('[data-lang]').forEach(function (link) {
			link.addEventListener('click', function () {
				document.cookie = 'aimp_lang=' + link.getAttribute('data-lang') + '; path=/; max-age=31536000; SameSite=Lax';
				try {
					window.localStorage.setItem('aimp_lang', link.getAttribute('data-lang'));
				} catch (err) {}
			});
		});
	}

	function boot() {
		document.querySelectorAll('[data-aimp-header]').forEach(function (header) {
			if (!header.aimpReady) {
				header.aimpReady = true;
				setup(header);
				trackOffset(header);
				trackScroll(header);
				setupInfoBar(header);
				setupLanguages(header);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
