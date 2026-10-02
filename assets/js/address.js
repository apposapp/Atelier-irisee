/**
 * Atelier Irisee address autocomplete (Google Places API "New").
 *
 * Works on:
 * - registration / profile fields saved as billing_ or shipping_address_1 (data-aimp-map),
 * - classic checkout (#billing_address_1, #shipping_address_1),
 * - checkout blocks (#billing-address_1, #shipping-address_1).
 */
(function () {
	'use strict';

	var cfg = window.aimpAddress;
	if (!cfg || !cfg.key) {
		return;
	}

	var API = 'https://places.googleapis.com/v1/';
	var NUMBER_FIRST = ['us', 'gb', 'ca', 'au', 'nz', 'ie', 'fr'];
	var listbox = null;
	var active = null; // { input, items, index, session }
	var timer = null;
	var counter = 0;

	function sessionToken() {
		if (window.crypto && window.crypto.randomUUID) {
			return window.crypto.randomUUID();
		}
		return String(Date.now()) + Math.random().toString(16).slice(2);
	}

	function language() {
		return (document.documentElement.lang || 'en').slice(0, 2);
	}

	/* ---------------------------------------------------------------
	 * Which fields belong together
	 * ------------------------------------------------------------- */

	/**
	 * The related fields of an address input: { postcode, city, state, country } (elements or null).
	 */
	function related(input) {
		var map = input.getAttribute('data-aimp-map');
		var group;
		if (map) {
			group = map.indexOf('shipping_') === 0 ? 'shipping_' : 'billing_';
			var form = input.form || document;
			var pick = function (name) {
				return form.querySelector('[data-aimp-map="' + group + name + '"]');
			};
			return { postcode: pick('postcode'), city: pick('city'), state: pick('state'), country: pick('country') };
		}
		var id = input.id;
		var blocks = id.indexOf('-address_1') !== -1;
		group = id.replace(blocks ? '-address_1' : '_address_1', '');
		var sep = blocks ? '-' : '_';
		var byId = function (name) {
			return document.getElementById(group + sep + name);
		};
		return { postcode: byId('postcode'), city: byId('city'), state: byId('state'), country: byId('country') };
	}

	function isAddressInput(el) {
		if (!el || el.tagName !== 'INPUT') {
			return false;
		}
		var map = el.getAttribute('data-aimp-map');
		if (map === 'billing_address_1' || map === 'shipping_address_1') {
			return true;
		}
		return !!cfg.checkout && /^(billing|shipping)(_|-)address_1$/.test(el.id);
	}

	/* ---------------------------------------------------------------
	 * Setting values (also for React-controlled checkout blocks)
	 * ------------------------------------------------------------- */

	function setValue(el, value) {
		if (!el || value === undefined || value === null || value === '') {
			return;
		}
		if (el.tagName === 'SELECT') {
			var match = Array.prototype.filter.call(el.options, function (o) {
				return o.value.toLowerCase() === String(value).toLowerCase();
			})[0];
			if (!match) {
				return;
			}
			value = match.value;
		}
		var proto = el.tagName === 'SELECT' ? window.HTMLSelectElement.prototype : el.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
		var setter = Object.getOwnPropertyDescriptor(proto, 'value').set;
		setter.call(el, value);
		el.dispatchEvent(new Event('input', { bubbles: true }));
		el.dispatchEvent(new Event('change', { bubbles: true }));
		// select2 / selectWoo (WooCommerce country & state) listen to jQuery events.
		if (window.jQuery) {
			window.jQuery(el).trigger('change');
		}
	}

	function component(components, type, short) {
		var found = (components || []).filter(function (c) {
			return c.types && c.types.indexOf(type) !== -1;
		})[0];
		return found ? (short ? found.shortText : found.longText) : '';
	}

	function fill(input, place) {
		var c = place.addressComponents || [];
		var country = component(c, 'country', true).toLowerCase();
		var street = component(c, 'route');
		var number = component(c, 'street_number');
		var line = NUMBER_FIRST.indexOf(country) !== -1 ? [number, street].join(' ') : [street, number].join(' ');
		var fields = related(input);
		var city = component(c, 'locality') || component(c, 'postal_town') || component(c, 'administrative_area_level_2');

		// Country first: WooCommerce rebuilds the state field and postcode rules when it changes.
		setValue(fields.country, country.toUpperCase());
		setTimeout(function () {
			setValue(input, line.trim());
			setValue(fields.postcode, component(c, 'postal_code'));
			setValue(fields.city, city);
			if (fields.state) {
				setValue(fields.state, component(c, 'administrative_area_level_1', true));
			}
		}, 250);
	}

	/* ---------------------------------------------------------------
	 * Requests
	 * ------------------------------------------------------------- */

	function suggest(input, text) {
		var body = { input: text, languageCode: language(), sessionToken: active.session };
		if (cfg.countries && cfg.countries.length) {
			body.includedRegionCodes = cfg.countries;
		}
		fetch(API + 'places:autocomplete', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-Goog-Api-Key': cfg.key },
			body: JSON.stringify(body)
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				if (!active || active.input !== input) {
					return;
				}
				var items = (data.suggestions || [])
					.filter(function (s) {
						return s.placePrediction;
					})
					.map(function (s) {
						return { id: s.placePrediction.placeId, text: s.placePrediction.text ? s.placePrediction.text.text : '' };
					});
				render(items);
			})
			.catch(function () {
				hide();
			});
	}

	function choose(item) {
		var input = active.input;
		var session = active.session;
		hide();
		fetch(API + 'places/' + encodeURIComponent(item.id) + '?languageCode=' + language() + '&sessionToken=' + encodeURIComponent(session), {
			headers: { 'X-Goog-Api-Key': cfg.key, 'X-Goog-FieldMask': 'addressComponents' }
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (place) {
				fill(input, place);
			})
			.catch(function () {});
		// A finished session starts a new one (Google bills per session).
		if (input.aimpAddress) {
			input.aimpAddress.session = sessionToken();
		}
	}

	/* ---------------------------------------------------------------
	 * Dropdown
	 * ------------------------------------------------------------- */

	function ensureListbox() {
		if (listbox) {
			return listbox;
		}
		listbox = document.createElement('ul');
		listbox.className = 'aimp-address-list';
		listbox.id = 'aimp-address-list';
		listbox.setAttribute('role', 'listbox');
		listbox.hidden = true;
		document.body.appendChild(listbox);
		listbox.addEventListener('mousedown', function (e) {
			// Keep focus in the input while clicking a suggestion.
			e.preventDefault();
			var option = e.target.closest('[data-index]');
			if (option && active) {
				choose(active.items[parseInt(option.getAttribute('data-index'), 10)]);
			}
		});
		return listbox;
	}

	function position() {
		if (!active || !listbox || listbox.hidden) {
			return;
		}
		var rect = active.input.getBoundingClientRect();
		listbox.style.top = rect.bottom + 'px';
		listbox.style.left = rect.left + 'px';
		listbox.style.width = rect.width + 'px';
	}

	function render(items) {
		var list = ensureListbox();
		active.items = items;
		active.index = -1;
		list.innerHTML = '';
		if (!items.length) {
			var empty = document.createElement('li');
			empty.className = 'aimp-address-empty';
			empty.textContent = cfg.noResults;
			list.appendChild(empty);
		}
		items.forEach(function (item, i) {
			var li = document.createElement('li');
			li.id = 'aimp-address-option-' + i;
			li.setAttribute('role', 'option');
			li.setAttribute('data-index', i);
			li.textContent = item.text;
			list.appendChild(li);
		});
		list.hidden = false;
		active.input.setAttribute('aria-expanded', 'true');
		position();
	}

	function hide() {
		if (listbox) {
			listbox.hidden = true;
		}
		if (active) {
			active.input.setAttribute('aria-expanded', 'false');
			active.input.removeAttribute('aria-activedescendant');
		}
	}

	function highlight(index) {
		if (!active || !active.items.length) {
			return;
		}
		active.index = (index + active.items.length) % active.items.length;
		Array.prototype.forEach.call(listbox.children, function (li, i) {
			li.classList.toggle('is-active', i === active.index);
			li.setAttribute('aria-selected', i === active.index ? 'true' : 'false');
		});
		active.input.setAttribute('aria-activedescendant', 'aimp-address-option-' + active.index);
	}

	/* ---------------------------------------------------------------
	 * Wiring
	 * ------------------------------------------------------------- */

	function attach(input) {
		if (input.aimpAddress) {
			return;
		}
		input.aimpAddress = { session: sessionToken(), id: ++counter };
		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-autocomplete', 'list');
		input.setAttribute('aria-expanded', 'false');
		input.setAttribute('aria-controls', 'aimp-address-list');
		input.setAttribute('autocomplete', 'off');

		input.addEventListener('input', function () {
			active = { input: input, items: [], index: -1, session: input.aimpAddress.session };
			clearTimeout(timer);
			var text = input.value.trim();
			if (text.length < 3) {
				hide();
				return;
			}
			timer = setTimeout(function () {
				suggest(input, text);
			}, 250);
		});

		input.addEventListener('keydown', function (e) {
			if (!active || active.input !== input || !listbox || listbox.hidden) {
				return;
			}
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				highlight(active.index + 1);
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				highlight(active.index - 1);
			} else if (e.key === 'Enter' && active.index >= 0) {
				e.preventDefault();
				choose(active.items[active.index]);
			} else if (e.key === 'Escape') {
				hide();
			}
		});

		input.addEventListener('blur', function () {
			setTimeout(hide, 150);
		});
	}

	// Fields can appear later (popup, checkout blocks): attach when they get focus.
	document.addEventListener('focusin', function (e) {
		if (isAddressInput(e.target)) {
			attach(e.target);
		}
	});
	window.addEventListener('scroll', position, true);
	window.addEventListener('resize', position);
})();
