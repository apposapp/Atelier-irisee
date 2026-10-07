/**
 * Step-by-step checkout: shows one step of WooCommerce's checkout form at a time.
 * Details → Overview → Payment; the step bar shows where the customer is (Cart and Confirmation are
 * separate pages). Nothing is moved in the form, so WooCommerce's own updates keep working.
 */
(function ($) {
	'use strict';

	var t = window.aimpCheckout || {};
	var STEPS = ['details', 'delivery', 'payment'];

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// Text of a checkout field: the chosen option of a list, or what was typed. A country or state that
	// can't be chosen is shown as text next to a hidden field.
	function fieldText(form, id) {
		var field = form.querySelector('#' + id);
		if (!field) {
			return '';
		}
		if (field.tagName === 'SELECT') {
			var option = field.options[field.selectedIndex];
			return option && field.value ? option.text.trim() : '';
		}
		if (field.type === 'hidden') {
			var row = form.querySelector('#' + id + '_field strong');
			return row ? row.textContent.trim() : '';
		}
		return String(field.value || '').trim();
	}

	/*
	 * Overview step: a "Delivery address" box at the top with the address that will be used: the details
	 * from step 2, or the other delivery address once "Ship to a different address" is ticked. It follows
	 * the fields as they change.
	 */
	function setupAddress(form, goToDetails) {
		var column = form.querySelector('#customer_details .col-2');
		if (!column) {
			return;
		}
		var box = document.createElement('div');
		box.className = 'aimp-checkout-address';
		box.innerHTML =
			'<div class="aimp-checkout-address-head"><h3>' + escapeHtml(t.address || '') + '</h3>' +
			'<button type="button" class="aimp-checkout-address-change">' + escapeHtml(t.change || '') + '</button></div>' +
			'<address></address>';
		column.insertBefore(box, column.firstChild);
		var address = box.querySelector('address');
		var change = box.querySelector('.aimp-checkout-address-change');
		var other = form.querySelector('#ship-to-different-address-checkbox');

		function render() {
			var prefix = other && other.checked ? 'shipping' : 'billing';
			var get = function (name) {
				return fieldText(form, prefix + '_' + name);
			};
			var lines = [
				[get('first_name'), get('last_name')].join(' ').trim(),
				get('company'),
				[get('address_1'), get('address_2')].join(' ').trim(),
				[get('postcode'), get('city')].join(' ').trim(),
				[get('state'), get('country')].filter(Boolean).join(', '),
				fieldText(form, 'billing_phone'),
				fieldText(form, 'billing_email')
			].filter(Boolean);
			address.innerHTML = lines.map(escapeHtml).join('<br>');
			// With another delivery address the fields are right below; otherwise they are on the details step.
			change.hidden = !!(other && other.checked);
		}

		change.addEventListener('click', goToDetails);
		form.addEventListener('input', render);
		form.addEventListener('change', render);
		if ($) {
			// Select2 (country, state) and WooCommerce's address updates.
			$(form).on('change', 'select', render);
			$(document.body).on('updated_checkout', render);
		}
		render();
	}

	/*
	 * Discount code in the Payment step. WooCommerce's coupon form lives outside the checkout form (forms
	 * can't be nested) and is hidden; the code is passed to it and WooCommerce applies it in the background
	 * and updates the totals. Its message is shown under the field.
	 */
	function setupCoupon(root) {
		if (!$) {
			return;
		}
		var waiting = false;

		function apply() {
			var field = root.querySelector('#aimp-checkout-coupon-code');
			var $form = $(root).find('form.checkout_coupon');
			var code = field ? field.value.trim() : '';
			if (!code || !$form.length) {
				return;
			}
			$form.find('input[name="coupon_code"]').val(code);
			waiting = true;
			$form.trigger('submit');
		}

		root.addEventListener('click', function (e) {
			if (e.target.closest && e.target.closest('[data-aimp-apply-coupon]')) {
				e.preventDefault();
				apply();
			}
		});
		root.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && e.target.id === 'aimp-checkout-coupon-code') {
				e.preventDefault(); // Not the order form.
				apply();
			}
		});
		$(document.body).on('applied_coupon_in_checkout', function () {
			if (!waiting) {
				return;
			}
			waiting = false;
			var msg = root.querySelector('.aimp-checkout-coupon-msg');
			var form = root.querySelector('form.checkout_coupon');
			var notice = form ? form.previousElementSibling : null;
			if (msg && notice && /woocommerce-(error|message|info)|is-(error|success)/.test(notice.className)) {
				msg.innerHTML = '';
				msg.appendChild(notice);
			}
			var field = root.querySelector('#aimp-checkout-coupon-code');
			if (field && notice && !/error/.test(notice.className)) {
				field.value = '';
			}
		});
	}

	/*
	 * Phones: a fixed bar at the bottom with the order total and the step's button ("Next", "To payment",
	 * "Place order"); it presses the real button.
	 */
	function setupMobileBar(root) {
		if (!window.matchMedia || root.classList.contains('is-confirmation') || !root.querySelector('form.checkout')) {
			return;
		}
		var phone = window.matchMedia('(max-width: 700px)');
		var bar = document.createElement('div');
		bar.className = 'aimp-checkout-bar';
		bar.hidden = true;
		bar.innerHTML = '<span class="aimp-checkout-bar-total"></span><button type="button" class="aimp-checkout-bar-button"></button>';
		document.body.appendChild(bar);

		function primary() {
			var next = root.querySelector('[data-checkout-next]');
			if (next && !next.hidden) {
				return next;
			}
			var place = root.querySelector('#place_order');
			return place && place.offsetParent !== null ? place : null;
		}

		function update() {
			var button = phone.matches ? primary() : null;
			bar.hidden = !button;
			document.body.classList.toggle('aimp-has-checkout-bar', !!button);
			if (!button) {
				return;
			}
			var total = root.querySelector('.order-total .woocommerce-Price-amount');
			bar.querySelector('.aimp-checkout-bar-total').textContent = total ? total.textContent.trim() : '';
			bar.querySelector('.aimp-checkout-bar-button').textContent = (button.textContent || button.value || '').trim();
		}

		bar.querySelector('button').addEventListener('click', function () {
			var button = primary();
			if (button) {
				button.click();
			}
		});
		new MutationObserver(function () {
			window.requestAnimationFrame(update);
		}).observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'data-step'] });
		if ($) {
			$(document.body).on('updated_checkout', update);
		}
		if (phone.addEventListener) {
			phone.addEventListener('change', update);
		}
		update();
	}

	// "Your order": folded open and closed on phones, always open on wider screens.
	function setupSummary(root) {
		var summary = root.querySelector('[data-aimp-checkout-summary]');
		if (!summary) {
			return;
		}
		var narrow = function () {
			return summary.parentNode.offsetWidth > 0 && getComputedStyle(summary).position !== 'sticky';
		};
		if (narrow()) {
			summary.open = false;
		}
		summary.querySelector('summary').addEventListener('click', function (e) {
			if (!narrow()) {
				e.preventDefault();
			}
		});
	}

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

		setupAddress(form, function () {
			show('details', true);
		});

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
				setupSummary(root);
				setupCoupon(root);
				setupMobileBar(root);
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
