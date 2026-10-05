/**
 * Step-by-step checkout: shows one step of WooCommerce's checkout form at a time.
 * Details → Delivery → Payment; the step bar shows where the customer is (Cart and Confirmation are
 * separate pages). Nothing is moved in the form, so WooCommerce's own updates keep working.
 */
(function ($) {
	'use strict';

	var t = window.aimpCheckout || {};
	var STEPS = ['details', 'delivery', 'payment'];

	function setup(root) {
		var form = root.querySelector('form.checkout');
		if (!form || root.classList.contains('is-confirmation')) {
			return;
		}
		var details = form.querySelector('#customer_details');
		var message = root.querySelector('.aimp-checkout-message');
		var current = 'details';

		// Back / Next under the customer details.
		var nav = document.createElement('div');
		nav.className = 'aimp-checkout-nav';
		nav.innerHTML =
			'<button type="button" class="aimp-button aimp-button--ghost" data-checkout-back>' + t.back + '</button>' +
			'<button type="button" class="aimp-button" data-checkout-next></button>';
		if (details) {
			details.parentNode.insertBefore(nav, details.nextSibling);
		} else {
			form.insertBefore(nav, form.firstChild);
		}
		var back = nav.querySelector('[data-checkout-back]');
		var next = nav.querySelector('[data-checkout-next]');

		function scrollTop() {
			var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
		}

		function show(step, scroll) {
			current = step;
			root.setAttribute('data-step', step);
			message.hidden = true;
			var index = STEPS.indexOf(step);
			root.querySelectorAll('.aimp-checkout-steps li[data-step-key]').forEach(function (li) {
				var key = li.getAttribute('data-step-key');
				var i = STEPS.indexOf(key);
				li.classList.toggle('is-current', key === step);
				li.classList.toggle('is-done', key === 'cart' || (i !== -1 && i < index));
				if (key === step) {
					li.setAttribute('aria-current', 'step');
				} else {
					li.removeAttribute('aria-current');
				}
			});
			back.hidden = index === 0;
			next.hidden = index === STEPS.length - 1;
			next.textContent = index === STEPS.length - 2 ? t.toPay : t.next;
			try {
				window.sessionStorage.setItem('aimp_checkout_step', step);
			} catch (e) {}
			if (scroll) {
				scrollTop();
			}
		}

		// The visible required fields of the current step must be filled in.
		function valid() {
			var first = null;
			form.querySelectorAll('.form-row.validate-required').forEach(function (row) {
				if (row.offsetParent === null) {
					return; // Hidden: another step, or "ship to a different address" is off.
				}
				var field = row.querySelector('input, select, textarea');
				if (!field || field.type === 'checkbox') {
					return;
				}
				var empty = !String(field.value || '').trim();
				var badEmail = !empty && row.classList.contains('validate-email') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value.trim());
				row.classList.toggle('woocommerce-invalid', empty || badEmail);
				row.classList.toggle('woocommerce-invalid-required-field', empty);
				if ((empty || badEmail) && !first) {
					first = field;
				}
			});
			if (first) {
				message.textContent = t.required;
				message.hidden = false;
				first.focus();
				return false;
			}
			return true;
		}

		next.addEventListener('click', function () {
			if (!valid()) {
				return;
			}
			show(STEPS[STEPS.indexOf(current) + 1], true);
		});
		back.addEventListener('click', function () {
			show(STEPS[Math.max(0, STEPS.indexOf(current) - 1)], true);
		});

		// Earlier steps in the step bar can be opened again.
		root.querySelectorAll('.aimp-checkout-steps li[data-step-key]').forEach(function (li) {
			var key = li.getAttribute('data-step-key');
			if (STEPS.indexOf(key) === -1) {
				return;
			}
			var label = li.querySelector('.aimp-checkout-step-label');
			label.setAttribute('role', 'button');
			label.setAttribute('tabindex', '0');
			var go = function () {
				if (STEPS.indexOf(key) < STEPS.indexOf(current)) {
					show(key, true);
				}
			};
			label.addEventListener('click', go);
			label.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					go();
				}
			});
		});

		// A checkout error about a field of an earlier step: open that step.
		if ($) {
			$(document.body).on('checkout_error', function () {
				var invalid = form.querySelector('.woocommerce-invalid');
				if (!invalid) {
					return;
				}
				if (invalid.closest('.woocommerce-billing-fields, .woocommerce-account-fields')) {
					show('details', true);
				} else if (invalid.closest('.woocommerce-shipping-fields, .woocommerce-additional-fields')) {
					show('delivery', true);
				}
			});
		}

		var start = 'details';
		try {
			var stored = window.sessionStorage.getItem('aimp_checkout_step');
			if (STEPS.indexOf(stored) !== -1) {
				start = stored;
			}
		} catch (e) {}
		root.classList.add('is-stepped');
		show(start, false);
	}

	function boot() {
		document.querySelectorAll('[data-aimp-checkout]').forEach(function (root) {
			if (!root.aimpReady) {
				root.aimpReady = true;
				setup(root);
			}
		});
		// After the order is placed, start at the first step next time.
		if (document.querySelector('[data-aimp-checkout].is-confirmation')) {
			try {
				window.sessionStorage.removeItem('aimp_checkout_step');
			} catch (e) {}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})(window.jQuery);
