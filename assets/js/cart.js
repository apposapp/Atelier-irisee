/**
 * Cart page: gold ‹ › arrows around the amounts; a changed amount updates the cart right away.
 */
(function () {
	'use strict';

	var timer = null;

	function submitUpdate(form) {
		clearTimeout(timer);
		timer = setTimeout(function () {
			var button = form.querySelector('.aimp-cart-update');
			if (button) {
				// Clicking the button sends update_cart, as WooCommerce expects.
				button.click();
			}
		}, 600);
	}

	function clean(field, value) {
		var step = parseInt(field.getAttribute('step'), 10) || 1;
		var max = parseInt(field.getAttribute('max'), 10) || 0;
		var n = Math.max(0, Math.ceil((parseFloat(value) || 0) / step) * step);
		return max ? Math.min(max, n) : n;
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-aimp-cart-stepper] .aimp-stepper-btn') : null;
		if (!btn) {
			return;
		}
		var stepper = btn.closest('[data-aimp-cart-stepper]');
		var field = stepper.querySelector('.aimp-stepper-input');
		var step = parseInt(field.getAttribute('step'), 10) || 1;
		var next = clean(field, (parseInt(field.value, 10) || 0) + step * parseInt(btn.getAttribute('data-dir'), 10));
		// Going below the first step would remove the product; use the remove link (×) for that.
		if (next < step) {
			return;
		}
		field.value = next;
		submitUpdate(stepper.closest('form'));
	});

	/*
	 * "[Remove]" next to a discount code: WooCommerce's cart script removes it in the background and then
	 * refreshes a cart form this page doesn't have, so nothing seemed to happen. Here the link is simply
	 * followed (WooCommerce removes the code and shows a notice). The capture phase runs before
	 * WooCommerce's handler.
	 */
	document.addEventListener(
		'click',
		function (e) {
			var link = e.target.closest ? e.target.closest('.aimp-cart a.woocommerce-remove-coupon') : null;
			if (!link) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			window.location.href = link.href;
		},
		true
	);

	document.addEventListener('change', function (e) {
		var field = e.target;
		if (!field.matches || !field.matches('[data-aimp-cart-stepper] .aimp-stepper-input')) {
			return;
		}
		field.value = clean(field, field.value);
		submitUpdate(field.closest('form'));
	});
})();
