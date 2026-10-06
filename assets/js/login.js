/**
 * Atelier Irisee login & registration: popup / slider / inline forms, all submitted with AJAX.
 */
(function () {
	'use strict';

	var cfg = window.aimpLogin;
	if (!cfg) {
		return;
	}

	/* ---------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------- */

	function lang() {
		var match = document.cookie.match(/(?:^|;\s*)aimp_lang=([a-z]+)/);
		if (match && cfg.i18n[match[1]]) {
			return match[1];
		}
		return cfg.i18n[cfg.defaultLanguage] ? cfg.defaultLanguage : Object.keys(cfg.i18n)[0];
	}

	function t() {
		return cfg.i18n[lang()];
	}

	function urlParam(name) {
		try {
			return new URL(window.location.href).searchParams.get(name) || '';
		} catch (e) {
			return '';
		}
	}

	function post(action, formData) {
		formData.append('aimp_lang', lang());
		return fetch(cfg.endpoint.replace('%%endpoint%%', 'aimp_el_' + action), {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		}).then(function (response) {
			return response.json().catch(function () {
				return { success: false, data: { errors: { _form: t().error } } };
			});
		});
	}

	var nonce = null;
	function getNonce() {
		if (nonce) {
			return Promise.resolve(nonce);
		}
		return post('nonce', new FormData()).then(function (res) {
			nonce = res && res.success ? res.data.nonce : '';
			return nonce;
		});
	}

	var scripts = {};
	function loadScript(src) {
		if (!scripts[src]) {
			scripts[src] = new Promise(function (resolve, reject) {
				var el = document.createElement('script');
				el.src = src;
				el.async = true;
				el.onload = resolve;
				el.onerror = reject;
				document.head.appendChild(el);
			});
		}
		return scripts[src];
	}

	/* ---------------------------------------------------------------
	 * Messages & field errors
	 * ------------------------------------------------------------- */

	function notice(form, message, type) {
		var box = form.querySelector('.aimp-el-notice');
		if (!box) {
			return;
		}
		box.textContent = message || '';
		box.className = 'aimp-el-notice aimp-el-notice--' + (type || 'error');
		box.hidden = !message;
	}

	function clearErrors(form) {
		notice(form, '');
		form.querySelectorAll('.has-error').forEach(function (el) {
			el.classList.remove('has-error');
		});
		form.querySelectorAll('.aimp-el-field-error').forEach(function (el) {
			el.textContent = '';
		});
		form.querySelectorAll('[aria-invalid]').forEach(function (el) {
			el.removeAttribute('aria-invalid');
		});
	}

	function fieldError(form, key, message) {
		var field = form.querySelector('[data-field="' + key + '"]');
		if (!field) {
			return false;
		}
		field.classList.add('has-error');
		var box = field.querySelector('.aimp-el-field-error');
		if (box) {
			box.textContent = message;
		}
		var input = field.querySelector('input, select, textarea');
		if (input) {
			input.setAttribute('aria-invalid', 'true');
		}
		return true;
	}

	function showErrors(form, errors) {
		var general = [];
		Object.keys(errors || {}).forEach(function (key) {
			if (key === '_form' || !fieldError(form, key, errors[key])) {
				general.push(errors[key]);
			}
		});
		if (general.length) {
			notice(form, general.join(' '), 'error');
		}
		var first = form.querySelector('.has-error input, .has-error select, .has-error textarea');
		if (first) {
			first.focus();
		}
	}

	/* ---------------------------------------------------------------
	 * Sections
	 * ------------------------------------------------------------- */

	function sectionName(container, name) {
		// With "email first" the login/register triggers start at the email step.
		if (cfg.singleField && (name === 'login' || name === 'register') && container.querySelector('[data-aimp-section="email"]') && !container.dataset.emailDone) {
			return 'email';
		}
		if (name === 'register' && !container.querySelector('[data-aimp-section="register"]')) {
			return 'login';
		}
		return name;
	}

	function show(container, name, skipEmailStep) {
		if (!skipEmailStep) {
			name = sectionName(container, name);
		}
		container.dataset.active = name;
		container.querySelectorAll('[data-aimp-section]').forEach(function (section) {
			section.hidden = section.getAttribute('data-aimp-section') !== name;
		});
		container.querySelectorAll('.aimp-el-tab').forEach(function (tab) {
			tab.setAttribute('aria-selected', tab.getAttribute('data-aimp-goto') === name ? 'true' : 'false');
		});
		var tabs = container.querySelector('.aimp-el-tabs');
		if (tabs) {
			tabs.hidden = name !== 'login' && name !== 'register';
		}
		var section = container.querySelector('[data-aimp-section="' + name + '"]');
		if (section) {
			section.querySelectorAll('form').forEach(function (form) {
				clearErrors(form);
				initCaptcha(form);
			});
			var input = section.querySelector('input:not([type="hidden"]):not([tabindex="-1"]), button');
			if (input && container.closest('[data-aimp-modal]')) {
				setTimeout(function () {
					input.focus();
				}, 50);
			}
		}
		return section;
	}

	function showMessage(container, message) {
		var section = show(container, 'message', true);
		if (section) {
			section.querySelector('.aimp-el-message-text').textContent = message;
		}
	}

	/* ---------------------------------------------------------------
	 * Popup
	 * ------------------------------------------------------------- */

	var modal = document.querySelector('[data-aimp-modal]');
	var lastFocus = null;

	function popupContainer() {
		return modal ? modal.querySelector('[data-aimp-el]') : null;
	}

	function inlineContainer() {
		return document.querySelector('.aimp-el--inline[data-aimp-el], .aimp-el--myaccount[data-aimp-el]');
	}

	/**
	 * Open a form: in the popup, or (without popup) in a form on the page.
	 *
	 * @param {string} name     Section.
	 * @param {Object} options  { redirect }
	 * @return {Element|null}   The container that shows the form.
	 */
	function open(name, options) {
		options = options || {};
		var container = popupContainer();
		if (!container) {
			container = inlineContainer();
			if (container) {
				if (options.redirect) {
					container.dataset.redirect = options.redirect;
				}
				show(container, name);
				container.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
			return container;
		}
		container.dataset.redirect = options.redirect || '';
		lastFocus = document.activeElement;
		modal.hidden = false;
		document.documentElement.classList.add('aimp-el-open');
		window.requestAnimationFrame(function () {
			modal.classList.add('is-open');
		});
		show(container, name);
		return container;
	}

	function close() {
		if (!modal || modal.hidden) {
			return;
		}
		modal.classList.remove('is-open');
		document.documentElement.classList.remove('aimp-el-open');
		setTimeout(function () {
			modal.hidden = true;
		}, 250);
		if (lastFocus && lastFocus.focus) {
			lastFocus.focus();
		}
	}

	function trapFocus(e) {
		if (!modal || modal.hidden || e.key !== 'Tab') {
			return;
		}
		var focusable = Array.prototype.filter.call(
			modal.querySelectorAll('a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]):not([tabindex="-1"]), select, textarea, iframe, [tabindex="0"]'),
			function (el) {
				return el.offsetParent !== null;
			}
		);
		if (!focusable.length) {
			return;
		}
		var first = focusable[0];
		var last = focusable[focusable.length - 1];
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	/* ---------------------------------------------------------------
	 * Captcha
	 * ------------------------------------------------------------- */

	function captchaReady() {
		var c = cfg.captcha;
		return loadScript(c.script).then(function () {
			return new Promise(function (resolve) {
				(function wait() {
					var ok =
						(c.type.indexOf('recaptcha') === 0 && window.grecaptcha && window.grecaptcha.render) ||
						(c.type === 'turnstile' && window.turnstile) ||
						(c.type === 'friendly' && window.friendlyChallenge);
					if (ok) {
						if (c.type.indexOf('recaptcha') === 0) {
							window.grecaptcha.ready(resolve);
						} else {
							resolve();
						}
					} else {
						setTimeout(wait, 100);
					}
				})();
			});
		});
	}

	function initCaptcha(form) {
		var el = form.querySelector('[data-aimp-captcha]');
		if (!cfg.captcha || !el || el.dataset.rendered || form.closest('[hidden]')) {
			return;
		}
		el.dataset.rendered = '1';
		var c = cfg.captcha;
		captchaReady().then(function () {
			if (c.type === 'recaptcha_v2') {
				el.dataset.widget = window.grecaptcha.render(el, { sitekey: c.siteKey });
			} else if (c.type === 'turnstile') {
				el.dataset.widget = window.turnstile.render(el, { sitekey: c.siteKey });
			} else if (c.type === 'friendly') {
				el.classList.add('frc-captcha');
				el.aimpWidget = new window.friendlyChallenge.WidgetInstance(el, { sitekey: c.siteKey, startMode: 'auto' });
			}
		});
	}

	function captchaToken(form, action) {
		var el = form.querySelector('[data-aimp-captcha]');
		var c = cfg.captcha;
		if (!c || !el) {
			return Promise.resolve('');
		}
		return captchaReady().then(function () {
			var token = '';
			if (c.type === 'recaptcha_v3') {
				return window.grecaptcha.execute(c.siteKey, { action: action });
			}
			if (c.type === 'recaptcha_v2') {
				token = window.grecaptcha.getResponse(el.dataset.widget);
			} else if (c.type === 'turnstile') {
				token = window.turnstile.getResponse(el.dataset.widget);
			} else if (c.type === 'friendly') {
				var input = el.querySelector('input[name="frc-captcha-solution"]');
				token = input ? input.value : '';
				if (token.indexOf('.') === -1) {
					token = ''; // Still solving.
				}
			}
			if (!token) {
				throw new Error(t().captcha);
			}
			return token;
		});
	}

	function resetCaptcha(form) {
		var el = form.querySelector('[data-aimp-captcha]');
		if (!cfg.captcha || !el) {
			return;
		}
		try {
			if (cfg.captcha.type === 'recaptcha_v2') {
				window.grecaptcha.reset(el.dataset.widget);
			} else if (cfg.captcha.type === 'turnstile') {
				window.turnstile.reset(el.dataset.widget);
			} else if (cfg.captcha.type === 'friendly' && el.aimpWidget) {
				el.aimpWidget.reset();
			}
		} catch (e) {}
	}

	/* ---------------------------------------------------------------
	 * Passwords
	 * ------------------------------------------------------------- */

	function fallbackScore(value) {
		var score = 0;
		if (value.length >= 6) {
			score++;
		}
		if (value.length >= 8) {
			score++;
		}
		if (/[a-z]/i.test(value) && /\d/.test(value)) {
			score++;
		}
		if (value.length >= 12 && /[A-Z]/.test(value) && /[a-z]/.test(value) && /[^A-Za-z0-9]/.test(value)) {
			score++;
		}
		return score;
	}

	function updateStrength(input) {
		var field = input.closest('.aimp-el-field');
		var meter = field ? field.querySelector('.aimp-el-strength') : null;
		if (!meter) {
			return;
		}
		var value = input.value;
		var score = -1;
		if (value) {
			if (window.wp && window.wp.passwordStrength && window.wp.passwordStrength.meter) {
				var disallowed = window.wp.passwordStrength.userInputDisallowedList ? window.wp.passwordStrength.userInputDisallowedList() : [];
				score = window.wp.passwordStrength.meter(value, disallowed, '');
			}
			if (score < 0) {
				score = fallbackScore(value);
			}
		}
		meter.className = 'aimp-el-strength' + (score >= 0 ? ' is-' + score : '');
		meter.querySelector('.aimp-el-strength-text').textContent = score >= 0 ? t().strength[String(score)] : '';
	}

	/* ---------------------------------------------------------------
	 * Submitting
	 * ------------------------------------------------------------- */

	function validate(form) {
		var errors = {};
		form.querySelectorAll('[required]').forEach(function (input) {
			var field = input.closest('[data-field]');
			if (!field || field.closest('[hidden]')) {
				return;
			}
			var empty = input.type === 'checkbox' ? !input.checked : input.type === 'file' ? !input.files.length : !input.value.trim();
			if (input.type === 'radio') {
				empty = !form.querySelector('input[name="' + input.name + '"]:checked');
			}
			if (empty) {
				errors[field.getAttribute('data-field')] = t().required;
			}
		});
		form.querySelectorAll('[data-aimp-confirm]').forEach(function (input) {
			var other = document.getElementById(input.getAttribute('data-aimp-confirm'));
			if (other && input.value && other.value !== input.value) {
				errors[input.closest('[data-field]').getAttribute('data-field')] = t().mismatch;
			}
		});
		return errors;
	}

	function setBusy(form, busy) {
		form.classList.toggle('is-busy', busy);
		var button = form.querySelector('.aimp-el-submit');
		if (button) {
			button.disabled = busy;
			if (busy) {
				button.dataset.label = button.textContent;
				button.textContent = t().loading;
			} else if (button.dataset.label) {
				button.textContent = button.dataset.label;
			}
		}
	}

	function redirectFor(container, key) {
		var param = urlParam('aimp_redirect');
		if (container.dataset.redirect) {
			return container.dataset.redirect;
		}
		if (param) {
			return param;
		}
		return key === 'register' ? container.dataset.registerRedirect || '' : container.dataset.loginRedirect || '';
	}

	function submitForm(form) {
		var key = form.getAttribute('data-aimp-form');
		var container = form.closest('[data-aimp-el]');
		clearErrors(form);

		var errors = validate(form);
		if (Object.keys(errors).length) {
			showErrors(form, errors);
			return;
		}

		setBusy(form, true);
		captchaToken(form, key)
			.then(function (token) {
				var data = new FormData(form);
				if (token) {
					data.set('aimp_captcha', token);
				}
				data.append('current', window.location.href);
				var redirect = redirectFor(container, key);
				if (redirect) {
					data.append('redirect', redirect);
				}
				return getNonce().then(function (value) {
					data.append('nonce', value);
					return post(key, data);
				});
			})
			.then(function (res) {
				handle(form, container, key, res);
			})
			.catch(function (err) {
				notice(form, err && err.message ? err.message : t().error, 'error');
			})
			.then(function () {
				setBusy(form, false);
			});
	}

	function handle(form, container, key, res) {
		var data = (res && res.data) || {};
		if (!res || !res.success) {
			if (data.resetCaptcha) {
				resetCaptcha(form);
			}
			nonce = null; // Fetch a fresh one next time (the session may have expired).
			showErrors(form, data.errors || { _form: t().error });
			if (data.unverified && data.email) {
				container.dataset.email = data.email;
				if (data.codeMode) {
					openVerify(container, data.email, '');
				}
			}
			return;
		}

		if (Object.prototype.hasOwnProperty.call(data, 'redirect')) {
			if (data.redirect) {
				window.location.href = data.redirect;
			} else {
				window.location.reload();
			}
			return;
		}

		if (key === 'check_email') {
			container.dataset.emailDone = '1';
			var target = data.exists ? 'login' : data.register ? 'register' : 'login';
			var section = show(container, target, true);
			var input = section ? section.querySelector(target === 'login' ? 'input[name="aimp[username]"]' : 'input[name="aimp[email]"]') : null;
			if (input) {
				input.value = data.email;
				var next = section.querySelector('input[type="password"]');
				if (next) {
					next.focus();
				}
			}
			return;
		}

		if (data.next === 'verify') {
			openVerify(container, data.email, data.message);
			return;
		}

		if (key === 'profile') {
			notice(form, data.message, 'success');
			if (data.reload) {
				setTimeout(function () {
					window.location.reload();
				}, 1500);
			}
			return;
		}

		showMessage(container, data.message || '');
	}

	function openVerify(container, email, message) {
		container.dataset.email = email;
		var section = show(container, 'verify', true);
		if (!section) {
			return;
		}
		section.querySelector('input[name="email"]').value = email;
		if (message) {
			section.querySelector('.aimp-el-verify-text').textContent = message;
		}
	}

	function resend(container, button) {
		var form = container.querySelector('[data-aimp-section="' + container.dataset.active + '"] form') || container.querySelector('form');
		var data = new FormData();
		data.append('email', container.dataset.email || '');
		button.disabled = true;
		getNonce()
			.then(function (value) {
				data.append('nonce', value);
				return post('resend', data);
			})
			.then(function (res) {
				if (form) {
					notice(form, res && res.data && res.data.message ? res.data.message : t().error, res && res.success ? 'success' : 'error');
				}
			})
			.then(function () {
				button.disabled = false;
			});
	}

	function unlink(button) {
		var data = new FormData();
		data.append('provider', button.getAttribute('data-aimp-unlink'));
		button.disabled = true;
		getNonce()
			.then(function (value) {
				data.append('nonce', value);
				return post('social_unlink', data);
			})
			.then(function (res) {
				if (res && res.success) {
					window.location.reload();
					return;
				}
				button.disabled = false;
				var errors = (res && res.data && res.data.errors) || {};
				window.alert(errors._form || t().error);
			});
	}

	/* ---------------------------------------------------------------
	 * Events
	 * ------------------------------------------------------------- */

	document.addEventListener('click', function (e) {
		var target = e.target;

		var trigger = target.closest('.aimp-login-tgr, .aimp-reg-tgr, .aimp-lostpw-tgr');
		if (trigger) {
			e.preventDefault();
			var type = trigger.classList.contains('aimp-reg-tgr') ? 'register' : trigger.classList.contains('aimp-lostpw-tgr') ? 'lostpw' : 'login';
			open(type, { redirect: trigger.getAttribute('data-aimp-redirect') || '' });
			return;
		}

		// Checkout blocks: "Log in" link above the contact form.
		var blocksLogin = cfg.checkoutPopup && modal ? target.closest('.wc-block-checkout__login-prompt') : null;
		if (blocksLogin) {
			e.preventDefault();
			open('login', { redirect: window.location.href });
			return;
		}

		var goTo = target.closest('[data-aimp-goto]');
		if (goTo) {
			var container = goTo.closest('[data-aimp-el]');
			if (container) {
				e.preventDefault();
				show(container, goTo.getAttribute('data-aimp-goto'), goTo.getAttribute('data-aimp-goto') === 'login' && !!container.dataset.emailDone);
			}
			return;
		}

		if (target.closest('[data-aimp-close]')) {
			e.preventDefault();
			close();
			return;
		}

		var toggle = target.closest('.aimp-el-toggle-pw');
		if (toggle) {
			var input = document.getElementById(toggle.getAttribute('aria-controls'));
			if (input) {
				var visible = input.type === 'password';
				input.type = visible ? 'text' : 'password';
				toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
				toggle.setAttribute('aria-label', visible ? t().hide : t().show);
			}
			return;
		}

		var resendButton = target.closest('[data-aimp-resend]');
		if (resendButton) {
			resend(resendButton.closest('[data-aimp-el]'), resendButton);
			return;
		}

		var unlinkButton = target.closest('[data-aimp-unlink]');
		if (unlinkButton) {
			unlink(unlinkButton);
			return;
		}

		// Social buttons: remember where to come back to.
		var social = target.closest('[data-aimp-social]');
		if (social) {
			var socialContainer = social.closest('[data-aimp-el]');
			var back = (socialContainer && redirectFor(socialContainer, 'login')) || window.location.href;
			social.href = social.href.replace(/([?&])redirect=[^&]*/, '$1').replace(/[?&]$/, '') + '&redirect=' + encodeURIComponent(encodeURIComponent(back));
		}
	});

	document.addEventListener('submit', function (e) {
		var form = e.target.closest('form[data-aimp-form]');
		if (form && form.closest('[data-aimp-el]')) {
			e.preventDefault();
			submitForm(form);
		}
	});

	document.addEventListener('input', function (e) {
		if (e.target.matches && e.target.matches('[data-aimp-strength]')) {
			updateStrength(e.target);
		}
	});

	document.addEventListener('change', function (e) {
		var input = e.target;
		if (!input.matches || !input.matches('[data-aimp-avatar]') || !input.files || !input.files[0] || !window.URL) {
			return;
		}
		var field = input.closest('.aimp-el-field');
		var preview = field.querySelector('.aimp-el-avatar-preview');
		if (!preview) {
			preview = document.createElement('span');
			preview.className = 'aimp-el-avatar-preview';
			input.parentNode.insertBefore(preview, input);
		}
		preview.innerHTML = '';
		var img = document.createElement('img');
		img.src = window.URL.createObjectURL(input.files[0]);
		img.alt = '';
		preview.appendChild(img);
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && modal && !modal.hidden) {
			close();
		}
		trapFocus(e);
	});

	/* ---------------------------------------------------------------
	 * Links from emails, social login answers, auto-open
	 * ------------------------------------------------------------- */

	function cleanUrl(params) {
		try {
			var url = new URL(window.location.href);
			params.forEach(function (name) {
				url.searchParams.delete(name);
			});
			window.history.replaceState(null, '', url.toString());
		} catch (e) {}
	}

	function fromUrl() {
		var action = urlParam('aimp_el');
		var msg = urlParam('aimp_el_msg');
		var container;

		if (action === 'resetpw' && urlParam('key')) {
			container = open('resetpw');
			if (container) {
				var section = show(container, 'resetpw', true);
				section.querySelector('input[name="key"]').value = urlParam('key');
				section.querySelector('input[name="login"]').value = urlParam('login');
			}
			cleanUrl(['aimp_el', 'key', 'login']);
			return true;
		}
		if (action === 'social_email' && urlParam('aimp_token')) {
			container = open('social_email');
			if (container) {
				show(container, 'social_email', true).querySelector('input[name="token"]').value = urlParam('aimp_token');
			}
			cleanUrl(['aimp_el', 'aimp_token']);
			return true;
		}
		// "Create an account" on the thank-you page: the registration form, with the order's email filled in.
		if (action === 'register') {
			container = open('register');
			if (container && urlParam('aimp_email')) {
				container.querySelectorAll('input[type="email"]').forEach(function (field) {
					if (!field.value) {
						field.value = urlParam('aimp_email');
					}
				});
			}
			cleanUrl(['aimp_el', 'aimp_email']);
			return true;
		}
		if (action === 'verify' && urlParam('aimp_email')) {
			container = open('verify');
			if (container) {
				openVerify(container, urlParam('aimp_email'), '');
			}
			cleanUrl(['aimp_el', 'aimp_email']);
			return true;
		}
		if (msg && t().messages[msg]) {
			container = open('message');
			if (container) {
				showMessage(container, t().messages[msg]);
			}
			cleanUrl(['aimp_el_msg']);
			return true;
		}
		return false;
	}

	function autoOpen() {
		if (!cfg.autoOpen || !modal) {
			return;
		}
		try {
			if (window.sessionStorage.getItem('aimp_el_auto')) {
				return;
			}
			window.sessionStorage.setItem('aimp_el_auto', '1');
		} catch (e) {}
		setTimeout(function () {
			if (modal.hidden) {
				open(cfg.autoOpenForm || 'login');
			}
		}, Math.max(0, cfg.autoOpenDelay || 0) * 1000);
	}

	function boot() {
		// Captchas of forms that are already visible on the page (inline forms, My Account).
		document.querySelectorAll('[data-aimp-el]:not([data-aimp-modal] [data-aimp-el]) form').forEach(initCaptcha);
		if (!fromUrl()) {
			autoOpen();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	window.aimpLoginOpen = open;
})();
