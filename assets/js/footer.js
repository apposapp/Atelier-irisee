/**
 * Atelier Irisee footer: the newsletter sign-up (sent in the background, answered under the field).
 */
(function () {
	'use strict';

	var cfg = window.aimpFooter;
	if (!cfg) {
		return;
	}

	document.addEventListener('submit', function (e) {
		var form = e.target.closest ? e.target.closest('[data-aimp-newsletter]') : null;
		if (!form) {
			return;
		}
		e.preventDefault();

		var email = form.querySelector('input[name="email"]');
		var button = form.querySelector('button[type="submit"]');
		var message = form.querySelector('.aimp-newsletter-message');
		var show = function (text, ok) {
			message.textContent = text;
			message.hidden = false;
			message.classList.toggle('is-error', !ok);
		};

		if (!email.value.trim() || !email.checkValidity()) {
			email.focus();
			show(email.validationMessage || cfg.error, false);
			return;
		}

		var body = new URLSearchParams();
		body.append('email', email.value.trim());
		body.append('aimp_hp', form.querySelector('input[name="aimp_hp"]').value);
		body.append('aimp_lang', (document.cookie.match(/(?:^|;\s*)aimp_lang=([a-z]+)/) || [])[1] || '');
		button.disabled = true;

		fetch(cfg.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				var text = json && json.data && json.data.message ? json.data.message : cfg.error;
				show(text, !!(json && json.success));
				if (json && json.success) {
					email.value = '';
				}
			})
			.catch(function () {
				show(cfg.error, false);
			})
			.then(function () {
				button.disabled = false;
			});
	});
})();
