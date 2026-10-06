/**
 * Atelier Irisee footer: the newsletter sign-up (sent in the background, answered under the field).
 *
 * Subscribed visitors see "You are subscribed" with an Unsubscribe button instead of the form; visitors
 * who still have to confirm see "Check your inbox". The state is asked in the background (pages may be
 * cached) and only when there is a reason: the aimp_nl cookie or a logged-in visitor. The answer is kept
 * for the rest of the visit.
 */
(function () {
	'use strict';

	var cfg = window.aimpFooter;
	if (!cfg) {
		return;
	}
	var STORE = 'aimp_nl_state';

	function post(url, data) {
		var body = new URLSearchParams();
		Object.keys(data || {}).forEach(function (key) {
			body.append(key, data[key]);
		});
		return fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json();
		});
	}

	function remember(state, message) {
		try {
			window.sessionStorage.setItem(STORE, JSON.stringify({ state: state, message: message }));
		} catch (e) {}
	}

	function remembered() {
		try {
			return JSON.parse(window.sessionStorage.getItem(STORE) || 'null');
		} catch (e) {
			return null;
		}
	}

	// confirmed / pending: the message and buttons instead of the form. none: the form (with a message).
	function render(state, message) {
		document.querySelectorAll('[data-aimp-newsletter]').forEach(function (form) {
			var box = form.parentNode.querySelector('[data-aimp-newsletter-state]');
			if (!box) {
				return;
			}
			var subscribed = state === 'confirmed' || state === 'pending';
			form.hidden = subscribed;
			// The invitation and the privacy line only make sense next to the form.
			form.parentNode.querySelectorAll('.aimp-newsletter-intro, .aimp-newsletter-consent').forEach(function (el) {
				el.hidden = subscribed;
			});
			box.hidden = !subscribed;
			box.querySelector('.aimp-newsletter-state-text').textContent = subscribed ? message : '';
			box.querySelector('[data-aimp-nl-resend]').hidden = state !== 'pending';
			var note = form.querySelector('.aimp-newsletter-message');
			if (!subscribed && message) {
				note.textContent = message;
				note.hidden = false;
				note.classList.remove('is-error');
			}
		});
	}

	// Sign up.
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

		button.disabled = true;
		post(cfg.endpoint, {
			email: email.value.trim(),
			aimp_hp: form.querySelector('input[name="aimp_hp"]').value,
			aimp_lang: (document.cookie.match(/(?:^|;\s*)aimp_lang=([a-z]+)/) || [])[1] || ''
		})
			.then(function (json) {
				var text = json && json.data && json.data.message ? json.data.message : cfg.error;
				if (json && json.success && json.data.state) {
					email.value = '';
					message.hidden = true;
					remember(json.data.state, text);
					render(json.data.state, text);
				} else {
					show(text, false);
				}
			})
			.catch(function () {
				show(cfg.error, false);
			})
			.then(function () {
				button.disabled = false;
			});
	});

	// Unsubscribe / send the confirmation email again.
	document.addEventListener('click', function (e) {
		var unsubscribe = e.target.closest ? e.target.closest('[data-aimp-nl-unsubscribe]') : null;
		var resend = e.target.closest ? e.target.closest('[data-aimp-nl-resend]') : null;
		var button = unsubscribe || resend;
		if (!button) {
			return;
		}
		button.disabled = true;
		post(unsubscribe ? cfg.unsubscribe : cfg.resend, {})
			.then(function (json) {
				var data = (json && json.data) || {};
				if (unsubscribe && json && json.success) {
					remember('none', '');
					render('none', data.message || '');
				} else if (data.message) {
					button.closest('[data-aimp-newsletter-state]').querySelector('.aimp-newsletter-state-text').textContent = data.message;
				}
			})
			.catch(function () {})
			.then(function () {
				button.disabled = false;
			});
	});

	// A notice after the link in the confirmation email (?aimp_nl_msg=confirmed|invalid).
	function notice(code) {
		var text = cfg.messages && cfg.messages[code];
		if (!text) {
			return;
		}
		var box = document.createElement('div');
		box.className = code === 'confirmed' ? 'woocommerce-message' : 'woocommerce-error';
		box.setAttribute('role', 'status');
		box.textContent = text;
		var main = document.querySelector('#main-content, main, #page-container') || document.body;
		main.insertBefore(box, main.firstChild);
		if (window.history && window.history.replaceState) {
			var url = new URL(window.location.href);
			url.searchParams.delete('aimp_nl_msg');
			window.history.replaceState(null, '', url.toString());
		}
	}

	function start() {
		var code = new URLSearchParams(window.location.search).get('aimp_nl_msg');
		if (code) {
			notice(code);
			try {
				window.sessionStorage.removeItem(STORE);
			} catch (e) {}
		}
		var known = remembered();
		if (known) {
			render(known.state, known.message);
			return;
		}
		var hasCookie = /(?:^|;\s*)aimp_nl=/.test(document.cookie);
		if (!hasCookie && !document.body.classList.contains('logged-in')) {
			return;
		}
		post(cfg.status, {})
			.then(function (json) {
				if (json && json.success) {
					remember(json.data.state, json.data.message || '');
					render(json.data.state, json.data.message || '');
				}
			})
			.catch(function () {});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
