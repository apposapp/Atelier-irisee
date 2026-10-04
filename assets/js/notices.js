/**
 * WooCommerce notices on every page: a close button (×) in the corner, and success and info messages
 * fade away after 4 seconds. Errors stay until they are closed, so they can't be missed.
 * Also handles notices that appear later (AJAX add to cart, cart and checkout blocks).
 */
(function () {
	'use strict';

	var t = window.aimpNotices || { close: 'Close' };
	var SELECTOR = '.woocommerce-message, .woocommerce-info, .woocommerce-error, .wc-block-components-notice-banner';
	// Notices that are part of a form or page, not a message: leave them alone.
	var SKIP = '.woocommerce-form-login-toggle, .woocommerce-form-coupon-toggle, .cart-empty, .woocommerce-no-products-found, .wc-block-components-notice-banner .wc-block-components-notice-banner';
	var DELAY = 4000;

	// Inline !important styles, so no theme rule (Divi uses !important on notices) can keep the notice visible.
	function hide(notice) {
		notice.classList.add('aimp-notice-fading');
		notice.style.setProperty('transition', 'opacity 0.4s ease', 'important');
		notice.style.setProperty('opacity', '0', 'important');
		setTimeout(function () {
			notice.classList.add('aimp-notice-hidden');
			notice.style.setProperty('display', 'none', 'important');
		}, 400);
	}

	function enhance(notice) {
		if (notice.aimpNotice || notice.closest(SKIP) || notice.classList.contains('cart-empty')) {
			return;
		}
		notice.aimpNotice = true;
		notice.classList.add('aimp-notice-box');

		var close = document.createElement('button');
		close.type = 'button';
		close.className = 'aimp-notice-close';
		close.setAttribute('aria-label', t.close);
		close.title = t.close;
		close.textContent = '×';
		close.addEventListener('click', function () {
			hide(notice);
		});
		notice.appendChild(close);

		var isError = notice.classList.contains('woocommerce-error') || notice.classList.contains('is-error');
		if (!isError) {
			var timer = setTimeout(function () {
				hide(notice);
			}, DELAY);
			// Keep it while the visitor reads it with the mouse on it.
			notice.addEventListener('mouseenter', function () {
				clearTimeout(timer);
			});
			notice.addEventListener('mouseleave', function () {
				timer = setTimeout(function () {
					hide(notice);
				}, DELAY / 2);
			});
		}
	}

	function scan(root) {
		if (root.matches && root.matches(SELECTOR)) {
			enhance(root);
		}
		if (root.querySelectorAll) {
			root.querySelectorAll(SELECTOR).forEach(enhance);
		}
	}

	function boot() {
		scan(document);
		if (window.MutationObserver) {
			new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					mutation.addedNodes.forEach(function (node) {
						if (node.nodeType === 1) {
							scan(node);
						}
					});
				});
			}).observe(document.body, { childList: true, subtree: true });
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
