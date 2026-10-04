/**
 * Atelier Irisee gift cards: product-page form, gift card code field (cart / checkout),
 * and the gift card tab of the account page.
 */
(function () {
	'use strict';

	var cfg = window.aimpGiftcards;
	if (!cfg) {
		return;
	}
	var t = cfg.i18n;

	function money(amount) {
		var c = cfg.currency;
		var parts = Number(amount).toFixed(c.decimals).split('.');
		parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousand);
		var value = parts.join(c.decimal);
		switch (c.position) {
			case 'right':
				return value + c.symbol;
			case 'left_space':
				return c.symbol + ' ' + value;
			case 'right_space':
				return value + ' ' + c.symbol;
			default:
				return c.symbol + value;
		}
	}

	function post(action, data) {
		var body = new URLSearchParams();
		Object.keys(data).forEach(function (key) {
			body.append(key, data[key]);
		});
		body.append('nonce', cfg.nonce);
		body.append('aimp_lang', cfg.lang);
		return fetch(cfg.endpoint.replace('%%endpoint%%', 'aimp_gc_' + action), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.catch(function () {
				return { success: false, data: { message: t.error } };
			});
	}

	function message(el, text, ok) {
		if (!el) {
			return;
		}
		el.textContent = text;
		el.hidden = !text;
		el.classList.toggle('is-error', !ok);
		el.classList.toggle('is-success', !!ok);
	}

	/* ---------------------------------------------------------------
	 * Product page form
	 * ------------------------------------------------------------- */

	function initForm(form) {
		function checked(name) {
			var el = form.querySelector('input[name="aimp_gc[' + name + ']"]:checked');
			return el ? el.value : '';
		}

		function update() {
			var amountChoice = checked('amount');
			var delivery = checked('delivery');

			form.querySelectorAll('[data-aimp-gc-show]').forEach(function (el) {
				var rule = el.getAttribute('data-aimp-gc-show').split(':');
				var current = rule[0] === 'amount' ? amountChoice : delivery;
				var show = rule[1].split(',').indexOf(current) !== -1;
				el.hidden = !show;
				// Hidden fields are not sent with the form.
				el.querySelectorAll('input, textarea').forEach(function (input) {
					input.disabled = !show;
				});
			});

			var email = form.querySelector('#aimp-gc-recipient-email');
			var name = form.querySelector('#aimp-gc-recipient-name');
			var custom = form.querySelector('#aimp-gc-custom');
			if (email) {
				email.required = delivery === 'email_other';
			}
			if (name) {
				name.required = delivery === 'email_other';
			}
			if (custom) {
				custom.required = amountChoice === 'custom';
			}

			var amount = amountChoice === 'custom' ? parseFloat(((custom && custom.value) || '0').replace(',', '.')) || 0 : parseFloat(amountChoice) || 0;
			var total = amount + (delivery === 'post' ? parseFloat(form.getAttribute('data-fee')) || 0 : 0);
			var out = form.querySelector('[data-aimp-gc-total]');
			if (out) {
				out.textContent = money(total);
			}
			// The product page's price bar shows this total times the quantity.
			form.dispatchEvent(new CustomEvent('aimp:gc-total', { bubbles: true, detail: { total: total } }));
		}

		form.addEventListener('change', update);
		form.addEventListener('input', update);
		update();
	}

	document.querySelectorAll('[data-aimp-gc-form]').forEach(initForm);

	/* ---------------------------------------------------------------
	 * Code field, account buttons
	 * ------------------------------------------------------------- */

	function apply(box, button) {
		var input = box.querySelector('#aimp-gc-code');
		var msg = box.querySelector('.aimp-gc-redeem-msg');
		var code = input ? input.value.trim() : '';
		if (!code) {
			message(msg, t.enter, false);
			return;
		}
		button.disabled = true;
		message(msg, t.working, true);
		post('apply', { code: code }).then(function (res) {
			var text = (res && res.data && res.data.message) || t.error;
			message(msg, text, res && res.success);
			if (res && res.success) {
				window.location.reload();
			} else {
				button.disabled = false;
			}
		});
	}

	function check(box, button) {
		var input = box.querySelector('#aimp-gc-check-code');
		var msg = box.querySelector('.aimp-gc-redeem-msg');
		var code = input ? input.value.trim() : '';
		if (!code) {
			message(msg, t.enter, false);
			return;
		}
		button.disabled = true;
		post('check', { code: code }).then(function (res) {
			message(msg, (res && res.data && res.data.message) || t.error, res && res.success);
			button.disabled = false;
		});
	}

	document.addEventListener('click', function (e) {
		var target = e.target;

		var applyButton = target.closest('[data-aimp-gc-apply]');
		if (applyButton) {
			e.preventDefault();
			apply(applyButton.closest('[data-aimp-gc-redeem]'), applyButton);
			return;
		}

		var removeButton = target.closest('[data-aimp-gc-remove]');
		if (removeButton) {
			e.preventDefault();
			removeButton.disabled = true;
			post('remove', { code: removeButton.getAttribute('data-aimp-gc-remove') }).then(function () {
				window.location.reload();
			});
			return;
		}

		var copyButton = target.closest('[data-aimp-gc-copy]');
		if (copyButton) {
			var done = function () {
				copyButton.textContent = t.copied;
				setTimeout(function () {
					copyButton.textContent = t.copy;
				}, 1600);
			};
			try {
				navigator.clipboard.writeText(copyButton.getAttribute('data-aimp-gc-copy')).then(done);
			} catch (err) {}
			return;
		}

		var useButton = target.closest('[data-aimp-gc-use]');
		if (useButton) {
			useButton.disabled = true;
			post('apply', { code: useButton.getAttribute('data-aimp-gc-use') }).then(function (res) {
				if (res && res.success) {
					window.location.href = (res.data && res.data.cartUrl) || cfg.cartUrl;
					return;
				}
				useButton.disabled = false;
				var msg = useButton.parentNode.querySelector('.aimp-gc-use-msg');
				if (!msg) {
					msg = document.createElement('p');
					msg.className = 'aimp-gc-use-msg aimp-gc-redeem-msg';
					msg.setAttribute('role', 'status');
					useButton.parentNode.appendChild(msg);
				}
				message(msg, (res && res.data && res.data.message) || t.error, false);
			});
			return;
		}

		var checkButton = target.closest('[data-aimp-gc-check-button]');
		if (checkButton) {
			check(checkButton.closest('[data-aimp-gc-check]'), checkButton);
		}
	});

	// Enter in a code field: apply or check the code instead of submitting the surrounding (checkout) form.
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter' || !e.target.matches) {
			return;
		}
		if (e.target.matches('#aimp-gc-code')) {
			e.preventDefault();
			var box = e.target.closest('[data-aimp-gc-redeem]');
			apply(box, box.querySelector('[data-aimp-gc-apply]'));
		} else if (e.target.matches('#aimp-gc-check-code')) {
			e.preventDefault();
			var checkBox = e.target.closest('[data-aimp-gc-check]');
			check(checkBox, checkBox.querySelector('[data-aimp-gc-check-button]'));
		}
	});
})();
