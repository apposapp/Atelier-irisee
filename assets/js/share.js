/**
 * Share buttons ([data-aimp-share="address"], AIMP_Share::button()): the device's own share sheet on phones and
 * tablets, a small menu on computers (Facebook, Pinterest, WhatsApp, email, copy the link). On product cards the
 * button lies over the card's link, so its click never opens the product.
 */
(function () {
	'use strict';

	var t = window.aimpShare || {};
	var menu = null;

	function esc(value) {
		return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	var icons = {
		facebook: '<path d="M14 8.5V7c0-.8.4-1.2 1.3-1.2H17V2.4c-.5-.1-1.6-.2-2.7-.2-2.8 0-4.3 1.6-4.3 4.5v1.8H7.2v3.6H10V22h4v-9.9h2.8l.5-3.6z"/>',
		pinterest: '<path d="M12.2 2C6.7 2 4 5.9 4 9.2c0 2 .8 3.8 2.4 4.4.3.1.5 0 .6-.3l.2-.9c.1-.3 0-.4-.2-.7-.5-.6-.8-1.3-.8-2.4 0-3 2.3-5.8 6-5.8 3.3 0 5.1 2 5.1 4.7 0 3.5-1.6 6.5-3.9 6.5-1.3 0-2.2-1-1.9-2.3.4-1.5 1.1-3.2 1.1-4.3 0-1-.5-1.8-1.6-1.8-1.3 0-2.3 1.3-2.3 3.1 0 1.1.4 1.9.4 1.9l-1.5 6.5c-.4 1.9-.1 4.3 0 4.5 0 .1.2.2.3.1.1-.2 1.6-2 2.1-3.8l.8-3.2c.4.8 1.6 1.4 2.8 1.4 3.7 0 6.2-3.4 6.2-7.9C20 5.3 16.9 2 12.2 2z"/>',
		whatsapp: '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.3 0 .5l-.3.5-.4.5c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1.1c.2-.3.4-.2.6-.1l2 1c.3.1.5.2.5.3.1.2.1.7-.1 1.3z"/>',
		email: '<path d="M3 5h18v14H3z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m3 6 9 7 9-7" fill="none" stroke="currentColor" stroke-width="1.8"/>',
		copy: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'
	};

	function svg(key) {
		return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + icons[key] + '</svg>';
	}

	function closeMenu() {
		if (menu) {
			var owner = menu.owner;
			menu.remove();
			menu = null;
			if (owner) {
				owner.setAttribute('aria-expanded', 'false');
			}
		}
	}

	function openMenu(button) {
		var url = button.getAttribute('data-aimp-share');
		var title = button.getAttribute('data-title') || '';
		var image = button.getAttribute('data-image') || '';
		var u = encodeURIComponent(url);
		var items = [
			['facebook', 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' + u],
			['pinterest', 'Pinterest', 'https://pinterest.com/pin/create/button/?url=' + u + (image ? '&media=' + encodeURIComponent(image) : '') + '&description=' + encodeURIComponent(title)],
			['whatsapp', 'WhatsApp', 'https://wa.me/?text=' + encodeURIComponent(title + ' ' + url)],
			['email', t.email || 'E-mail', 'mailto:?subject=' + encodeURIComponent(title) + '&body=' + u]
		];
		closeMenu();
		menu = document.createElement('div');
		menu.className = 'aimp-share-menu';
		menu.setAttribute('role', 'menu');
		menu.owner = button;
		menu.innerHTML = items.map(function (item) {
			return '<a role="menuitem" href="' + esc(item[2]) + '"' + (item[0] === 'email' ? '' : ' target="_blank" rel="noopener"') + '>' + svg(item[0]) + '<span>' + esc(item[1]) + '</span></a>';
		}).join('') + '<button type="button" role="menuitem" data-copy>' + svg('copy') + '<span>' + esc(t.copy || 'Copy link') + '</span></button>';
		document.body.appendChild(menu);
		var box = button.getBoundingClientRect();
		var width = menu.offsetWidth;
		var left = Math.max(8, Math.min(window.innerWidth - width - 8, box.left + box.width / 2 - width / 2));
		var below = box.bottom + menu.offsetHeight + 12 < window.innerHeight;
		menu.style.left = left + 'px';
		menu.style.top = (below ? box.bottom + 8 : box.top - menu.offsetHeight - 8) + 'px';
		button.setAttribute('aria-expanded', 'true');
		menu.querySelector('[data-copy]').addEventListener('click', function (e) {
			var label = e.currentTarget.querySelector('span');
			var done = function () {
				label.textContent = t.copied || 'Link copied';
				window.setTimeout(closeMenu, 1200);
			};
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(url).then(done, function () {
					window.prompt('', url); // eslint-disable-line no-alert
				});
			} else {
				window.prompt('', url); // eslint-disable-line no-alert
			}
		});
		menu.querySelector('a, button').focus();
	}

	function share(button) {
		if (menu && menu.owner === button) {
			closeMenu();
			return;
		}
		var touch = window.matchMedia && window.matchMedia('(hover: none)').matches;
		if (navigator.share && touch) {
			navigator.share({ title: button.getAttribute('data-title') || '', url: button.getAttribute('data-aimp-share') }).catch(function () {});
			return;
		}
		openMenu(button);
	}

	// Capture: before a card's own click (the shop's cards open a quick view).
	document.addEventListener('click', function (e) {
		var button = e.target.closest ? e.target.closest('[data-aimp-share]') : null;
		if (!button) {
			if (menu && !e.target.closest('.aimp-share-menu')) {
				closeMenu();
			}
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		share(button);
	}, true);

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && menu) {
			var owner = menu.owner;
			closeMenu();
			if (owner) {
				owner.focus();
			}
		}
	});
	window.addEventListener('resize', closeMenu);
	window.addEventListener('scroll', closeMenu, { passive: true });
})();
