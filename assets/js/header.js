/**
 * Atelier Irisee header: the side menu that slides in from the left.
 * Opens with "Menu", closes with ×, Esc or a click next to it. Focus stays in the menu while it is open,
 * and items with sub-items get an arrow to open them: the text follows the link, the rest of the row (with the
 * arrow) opens or closes the sub-items. Every time the menu opens, "All products" starts open and the other
 * sub-items closed.
 */
(function () {
	'use strict';

	function setup(header) {
		var panel = header.querySelector('.aimp-side-menu');
		var backdrop = header.querySelector('.aimp-side-menu-backdrop');
		// "Menu" in the header and in the phone tab bar.
		var toggles = Array.prototype.slice.call(header.querySelectorAll('[data-aimp-menu-open]'));
		if (!panel || !toggles.length) {
			return;
		}
		var expanded = function (value) {
			toggles.forEach(function (el) {
				el.setAttribute('aria-expanded', value);
			});
		};
		var lastFocus = null;
		var closers = [];
		var startOpen = null;

		panel.inert = true;

		function focusables() {
			return Array.prototype.filter.call(panel.querySelectorAll('a[href], button:not([disabled])'), function (el) {
				return el.offsetParent !== null && !el.closest('.aimp-sub-wrap[inert]');
			});
		}

		function open() {
			lastFocus = document.activeElement;
			closers.forEach(function (entry) {
				entry.set(openMode === 'all' || entry.item === startOpen);
			});
			panel.inert = false;
			panel.setAttribute('aria-hidden', 'false');
			backdrop.hidden = false;
			// Next frame, so the fade and slide animate.
			window.requestAnimationFrame(function () {
				header.classList.add('is-menu-open');
			});
			expanded('true');
			document.documentElement.classList.add('aimp-menu-open');
			var first = focusables()[0];
			if (first) {
				first.focus();
			}
		}

		function close() {
			header.classList.remove('is-menu-open');
			expanded('false');
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

		toggles.forEach(function (el) {
			el.addEventListener('click', open);
		});
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

		// "All products" (the item that links to the All products page, or else the first item with sub-items).
		var clean = function (url) {
			return String(url || '').replace(/[?#].*$/, '').replace(/\/+$/, '');
		};
		var allUrl = clean(panel.getAttribute('data-all-url'));
		var parents = panel.querySelectorAll('.menu-item-has-children');
		Array.prototype.forEach.call(parents, function (item) {
			var a = item.querySelector(':scope > a');
			if (!startOpen && a && allUrl && clean(a.href) === allUrl) {
				startOpen = item;
			}
		});
		startOpen = startOpen || parents[0] || null;
		// Design studio (Side menu): every dropdown closed, or all of them open.
		var openMode = panel.getAttribute('data-open') || '';
		if (openMode === 'none') {
			startOpen = null;
		}

		// Sub-items: a button next to the item (the rest of its row, with the arrow) opens them; they fade and
		// unfold (see header.css).
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
			setOpen(openMode === 'all' || item === startOpen);
			closers.push({ item: item, set: setOpen });
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
		// The header is fixed (header.css, .is-fixed); a spacer in the page keeps its full height, so the header
		// getting slimmer on scroll never moves the page (that made it flicker near the top).
		var spacer = document.createElement('div');
		spacer.className = 'aimp-header-spacer';
		spacer.setAttribute('aria-hidden', 'true');
		header.parentNode.insertBefore(spacer, header);

		// The full (not scrolled) height, also while scrolled: measured without transitions, in one go.
		var fullHeight = function () {
			if (!header.classList.contains('is-scrolled')) {
				return header.offsetHeight;
			}
			header.classList.add('is-measuring');
			header.classList.remove('is-scrolled');
			var height = header.offsetHeight;
			header.classList.add('is-scrolled');
			// Reading the height again applies the scrolled look before transitions come back.
			void header.offsetHeight;
			header.classList.remove('is-measuring');
			return height;
		};

		// The slim height once scrolled (for scrolling to a section, e.g. the hero's arrow), measured the same way.
		var slimHeight = function () {
			if (header.classList.contains('is-scrolled')) {
				return header.offsetHeight;
			}
			header.classList.add('is-measuring');
			header.classList.add('is-scrolled');
			var height = header.offsetHeight;
			header.classList.remove('is-scrolled');
			void header.offsetHeight;
			header.classList.remove('is-measuring');
			return height;
		};

		// The WordPress admin bar: fixed on wide screens; on phones it scrolls away, so the header follows it up.
		var barTop = function () {
			var bar = document.getElementById('wpadminbar');
			if (!bar) {
				return 0;
			}
			var height = bar.offsetHeight;
			return window.getComputedStyle(bar).position === 'fixed' ? height : Math.max(0, height - window.scrollY);
		};

		var layout = function () {
			spacer.style.height = fullHeight() + 'px';
			document.documentElement.style.setProperty('--aimp-header-slim', slimHeight() + 'px');
			header.classList.add('is-fixed');
			update();
		};
		var update = function () {
			var top = barTop();
			document.documentElement.style.setProperty('--aimp-adminbar-top', top + 'px');
			document.documentElement.style.setProperty('--aimp-header-offset', header.offsetHeight + top + 'px');
		};
		var width = window.innerWidth;
		layout();
		// Phones fire resize while scrolling (the address bar); only a new width changes the header.
		window.addEventListener('resize', function () {
			if (window.innerWidth !== width) {
				width = window.innerWidth;
				layout();
			} else {
				update();
			}
		});
		window.addEventListener('load', layout);
		window.addEventListener('scroll', update, { passive: true });
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
					// Design studio (Gold info bar): seconds per text.
					var speed = parseFloat(bar.getAttribute('data-speed')) || 3.5;
					timer = setInterval(function () {
						index = (index + 1) % items.length;
						show();
					}, speed * 1000);
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

	/*
	 * The cart number: from aimpBoot() (class-aimp-boot.php) once the page is there (cached pages can show an old
	 * number), and again after something was added to the cart.
	 */
	function setupCartCount() {
		if (!window.aimpBoot) {
			return;
		}
		var apply = function (data) {
			if (!data || !data.cart || typeof data.cart.badge !== 'string') {
				return;
			}
			document.querySelectorAll('span.aimp-header-cart-count').forEach(function (badge) {
				var fresh = document.createElement('div');
				fresh.innerHTML = data.cart.badge;
				if (fresh.firstElementChild) {
					badge.parentNode.replaceChild(fresh.firstElementChild, badge);
				}
			});
		};
		var refresh = function () {
			window.aimpBoot(true).then(apply);
		};
		window.aimpBoot().then(apply);
		document.body.addEventListener('wc-blocks_added_to_cart', refresh);
		if (window.jQuery) {
			window.jQuery(document.body).on('wc_fragment_refresh added_to_cart removed_from_cart', refresh);
		}
	}

	function boot() {
		document.querySelectorAll('[data-aimp-header]').forEach(function (header) {
			if (!header.aimpReady) {
				header.aimpReady = true;
				setup(header);
				// Design studio (Header): it can scroll away with the page, and stay the same size.
				if (header.getAttribute('data-sticky') !== '0') {
					trackOffset(header);
				} else {
					document.documentElement.style.setProperty('--aimp-header-offset', '0px');
				}
				if (header.getAttribute('data-shrink') !== '0') {
					trackScroll(header);
				}
				setupInfoBar(header);
				setupLanguages(header);
				// Design studio (Phone tab bar): a Search tab opens the search in the header.
				header.querySelectorAll('[data-aimp-tab-search]').forEach(function (tab) {
					tab.addEventListener('click', function () {
						var toggle = document.querySelector('[data-aimp-search-toggle]');
						if (toggle) {
							window.scrollTo({ top: 0 });
							toggle.click();
						}
					});
				});
			}
		});
		if (!window.aimpCartCount) {
			window.aimpCartCount = true;
			setupCartCount();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
