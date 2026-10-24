/**
 * Atelier Irisee contact page: the form is checked here and sent in the background (contact form → wc-ajax
 * aimp_contact); a mistake shows under its field, a sent message turns the form into a thank-you. The email address
 * can be copied with one click.
 */
(function () {
	'use strict';

	var cfg = window.aimpContact;
	if (!cfg) {
		return;
	}
	// Sending before this many ms after the page opened is a bot (the server checks it too).
	var opened = Date.now();
	var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

	function fieldOf(input) {
		return input.closest('.aimp-contact-field');
	}

	function setError(input, message) {
		var field = fieldOf(input);
		if (!field) {
			return;
		}
		var note = field.querySelector('.aimp-contact-error');
		field.classList.toggle('is-invalid', !!message);
		input.setAttribute('aria-invalid', message ? 'true' : 'false');
		if (!message) {
			if (note) {
				note.remove();
			}
			input.removeAttribute('aria-describedby');
			return;
		}
		if (!note) {
			note = document.createElement('p');
			note.className = 'aimp-contact-error';
			note.id = (input.id || input.name) + '-error';
			field.appendChild(note);
		}
		note.textContent = message;
		input.setAttribute('aria-describedby', note.id);
	}

	// The mistakes in the form: [ input, message ].
	function check(form) {
		var errors = [];
		var name = form.elements.name;
		var email = form.elements.email;
		var message = form.elements.message;
		var consent = form.elements.consent;
		if (name && !name.value.trim()) {
			errors.push([name, cfg.required]);
		}
		if (email && !EMAIL.test(email.value.trim())) {
			errors.push([email, email.value.trim() ? cfg.email : cfg.required]);
		}
		if (message && message.value.trim().length < 10) {
			errors.push([message, message.value.trim() ? cfg.short : cfg.required]);
		}
		if (consent && !consent.checked) {
			errors.push([consent, cfg.consent]);
		}
		return errors;
	}

	function say(form, text, isError) {
		var note = form.querySelector('.aimp-contact-message');
		note.textContent = text || '';
		note.hidden = !text;
		note.classList.toggle('is-error', !!isError);
	}

	document.addEventListener('submit', function (e) {
		var form = e.target.closest ? e.target.closest('[data-aimp-contact-form]') : null;
		if (!form) {
			return;
		}
		e.preventDefault();
		Array.prototype.forEach.call(form.querySelectorAll('input, select, textarea'), function (input) {
			if (input.name !== 'aimp_hp') {
				setError(input, '');
			}
		});
		var errors = check(form);
		if (errors.length) {
			errors.forEach(function (error) {
				setError(error[0], error[1]);
			});
			errors[0][0].focus();
			say(form, '', false);
			return;
		}

		var button = form.querySelector('button[type="submit"]');
		var label = button.textContent;
		var body = new URLSearchParams(new FormData(form));
		body.set('elapsed', String(Date.now() - opened));
		body.set('lang', cfg.lang || '');
		button.disabled = true;
		button.textContent = cfg.sending;
		say(form, '', false);

		fetch(cfg.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (res) {
				if (res && res.success) {
					var done = form.parentNode.querySelector('[data-aimp-contact-done]');
					form.hidden = true;
					done.hidden = false;
					done.focus();
					return;
				}
				var data = (res && res.data) || {};
				var input = data.field ? form.elements[data.field] : null;
				if (input) {
					setError(input, data.message || cfg.error);
					input.focus();
				} else {
					say(form, data.message || cfg.error, true);
				}
			})
			.catch(function () {
				say(form, cfg.error, true);
			})
			.then(function () {
				button.disabled = false;
				button.textContent = label;
			});
	});

	// A mistake disappears as soon as it is corrected.
	document.addEventListener('input', function (e) {
		var form = e.target.closest ? e.target.closest('[data-aimp-contact-form]') : null;
		if (form && e.target.getAttribute('aria-invalid') === 'true') {
			var still = check(form).filter(function (error) {
				return error[0] === e.target;
			})[0];
			setError(e.target, still ? still[1] : '');
		}
	});

	document.addEventListener('click', function (e) {
		// "Send another message": the form again, empty except name and email.
		var again = e.target.closest ? e.target.closest('[data-aimp-contact-again]') : null;
		if (again) {
			var done = again.closest('[data-aimp-contact-done]');
			var form = done.parentNode.querySelector('[data-aimp-contact-form]');
			form.elements.message.value = '';
			if (form.elements.order) {
				form.elements.order.value = '';
			}
			done.hidden = true;
			form.hidden = false;
			opened = Date.now();
			form.elements.message.focus();
			return;
		}

		var copy = e.target.closest ? e.target.closest('[data-copy]') : null;
		if (!copy) {
			return;
		}
		var text = copy.getAttribute('data-copy');
		var shown = copy.querySelector('.aimp-contact-copied');
		var confirm = function () {
			shown.textContent = cfg.copied;
			window.setTimeout(function () {
				shown.textContent = '';
			}, 2000);
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(confirm, function () {});
		}
	});
})();
