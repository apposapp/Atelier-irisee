/**
 * Atelier Irisee product pages: gallery with lightbox, size pictures, the buy bar (amount with arrows
 * and the price for that amount) and remembering the chosen language.
 */
(function () {
	'use strict';

	var cfg = window.aimpProduct;
	var UI = window.aimpUI;
	if (!cfg || !UI) {
		return;
	}

	var t = cfg.i18n;

	function setupGallery(root) {
		var gallery = root.querySelector('[data-aimp-gallery]');
		if (!gallery) {
			return;
		}
		var images = [];
		try {
			images = JSON.parse(gallery.getAttribute('data-aimp-gallery')) || [];
		} catch (e) {}
		if (!images.length) {
			return;
		}

		var current = 0;
		var show = UI.bindGallery(root.querySelector('.aimp-product-media'), images, function (i) {
			current = i;
		});

		gallery.querySelector('.aimp-gallery-main img').addEventListener('click', function () {
			UI.openLightbox(root, images, current, t);
		});

		// Pattern sizes (and other variations) with their own picture: show it when the size is chosen.
		if (window.jQuery) {
			window.jQuery(root)
				.on('found_variation', 'form.variations_form', function (e, variation) {
					var imageId = variation && variation.image_id ? parseInt(variation.image_id, 10) : 0;
					for (var i = 0; i < images.length; i++) {
						if (images[i].id === imageId) {
							show(i);
							return;
						}
					}
				})
				.on('reset_image', 'form.variations_form', function () {
					show(0);
				});
		}
	}

	/**
	 * Buy bar: gold ‹ › arrows around the amount, and the price for the chosen amount.
	 * Fabric, ribbon and bias tape are chosen in cm (steps of 10); the server turns cm into units of 10 cm.
	 */
	function setupBuyBar(root) {
		var bar = root.querySelector('[data-aimp-buy]');
		var field = bar ? bar.querySelector('.aimp-stepper-input') : null;
		if (!field) {
			return;
		}
		var per10cm = bar.getAttribute('data-per-10cm') === '1';
		var unitPrice = parseFloat(bar.getAttribute('data-unit-price')) || 0;
		var step = parseInt(field.getAttribute('step'), 10) || 1;
		var min = parseInt(field.getAttribute('min'), 10) || step;
		var max = parseInt(field.getAttribute('max'), 10) || 0;
		var price = bar.querySelector('[data-aimp-price]');
		var unitLine = bar.querySelector('[data-aimp-unit-line]');
		var giftcard = bar.hasAttribute('data-giftcard');

		// Round up to a whole step and keep it within min and max (a typed 25 cm becomes 30 cm).
		var clean = function (value) {
			var n = Math.ceil((parseFloat(value) || 0) / step) * step;
			n = Math.max(min, n);
			return max ? Math.min(max, n) : n;
		};

		// Gift cards: the arrows choose the card's value; the price adds the printing fee when it goes by post.
		var giftcardFee = function () {
			var form = root.querySelector('[data-aimp-gc-form]');
			var post = root.querySelector('input[name="aimp_gc[delivery]"]:checked');
			return form && post && post.value === 'post' ? parseFloat(form.getAttribute('data-fee')) || 0 : 0;
		};

		var showPrice = function (amount) {
			if (giftcard) {
				if (price) {
					price.innerHTML = '<span class="woocommerce-Price-amount amount">' + UI.esc(UI.money(amount + giftcardFee(), cfg.currency)) + '</span>';
				}
				return;
			}
			if (!unitPrice) {
				return;
			}
			var units = per10cm ? amount / 10 : amount;
			if (price) {
				price.innerHTML = '<span class="woocommerce-Price-amount amount">' + UI.esc(UI.money(unitPrice * units, cfg.currency)) + '</span>';
			}
			if (unitLine) {
				if (per10cm) {
					unitLine.textContent = UI.money(unitPrice, cfg.currency) + ' ' + t.per10cm;
				} else {
					unitLine.textContent = UI.fmt(t.perPiece, UI.money(unitPrice, cfg.currency));
					unitLine.hidden = units < 2;
				}
			}
		};
		var set = function (value) {
			field.value = clean(value);
			showPrice(parseInt(field.value, 10));
		};

		bar.querySelectorAll('.aimp-stepper-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				set((parseInt(field.value, 10) || min) + step * parseInt(btn.getAttribute('data-dir'), 10));
			});
		});
		field.addEventListener('input', function () {
			if (field.value !== '') {
				showPrice(clean(field.value));
			}
		});
		field.addEventListener('change', function () {
			set(field.value);
		});
		set(field.value);

		if (giftcard) {
			root.addEventListener('change', function (e) {
				if (e.target && e.target.name === 'aimp_gc[delivery]') {
					showPrice(parseInt(field.value, 10) || min);
				}
			});
		}
	}

	// Gift cards: the main picture shows the chosen design.
	function setupGiftcardDesign(root) {
		var main = root.querySelector('.aimp-gallery-main img');
		if (!main) {
			return;
		}
		root.addEventListener('change', function (e) {
			var input = e.target;
			if (input && input.name === 'aimp_gc[design]' && input.checked && input.getAttribute('data-large')) {
				main.src = input.getAttribute('data-large');
			}
		});
		var checked = root.querySelector('input[name="aimp_gc[design]"]:checked');
		if (checked && checked.getAttribute('data-large')) {
			main.src = checked.getAttribute('data-large');
		}
	}

	// The flags are links (?aimp_lang=…); remember the choice so the next pages follow.
	function setupLanguages(root) {
		root.querySelectorAll('.aimp-languages [data-lang]').forEach(function (link) {
			link.addEventListener('click', function () {
				UI.persistLang(link.getAttribute('data-lang'));
			});
		});
	}

	function boot() {
		document.querySelectorAll('[data-aimp-product]').forEach(function (root) {
			if (root.aimpProductReady) {
				return;
			}
			root.aimpProductReady = true;
			setupGallery(root);
			setupBuyBar(root);
			setupGiftcardDesign(root);
			setupLanguages(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();

/**
 * "Email me when it's back" on sold-out products: sent in the background, answered under the field.
 */
(function () {
	'use strict';

	document.addEventListener('submit', function (e) {
		var form = e.target.closest ? e.target.closest('[data-aimp-stock-alert]') : null;
		if (!form) {
			return;
		}
		e.preventDefault();
		var email = form.querySelector('input[name="email"]');
		var button = form.querySelector('button[type="submit"]');
		var message = form.querySelector('.aimp-stock-alert-message');
		var show = function (text, ok) {
			message.textContent = text;
			message.hidden = false;
			message.classList.toggle('is-error', !ok);
		};
		if (!email.value.trim() || !email.checkValidity()) {
			email.focus();
			show(email.validationMessage || form.getAttribute('data-error'), false);
			return;
		}
		var body = new URLSearchParams(new FormData(form));
		button.disabled = true;
		fetch(form.getAttribute('data-endpoint'), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				var text = json && json.data && json.data.message ? json.data.message : form.getAttribute('data-error');
				show(text, !!(json && json.success));
				if (json && json.success) {
					form.querySelector('.aimp-stock-alert-row').hidden = true;
				}
			})
			.catch(function () {
				show(form.getAttribute('data-error'), false);
			})
			.then(function () {
				button.disabled = false;
			});
	});
})();

/**
 * Phones: when the product's own price bar scrolls out of view, a small bar with the name, the price and
 * "Add to cart" stays at the bottom of the screen. It presses the real button, so the amount chosen in
 * the price bar is used. It lives in <body>: the page wrapper is a CSS container and would trap it.
 */
(function () {
	'use strict';

	if (!('IntersectionObserver' in window) || !window.matchMedia) {
		return;
	}

	function setup(bar) {
		var real = bar.querySelector('.aimp-add-to-cart');
		if (!real || real.disabled) {
			return; // Sold out: nothing to buy.
		}
		var product = bar.closest('.aimp-product') || document;
		var title = product.querySelector('h1');
		var price = bar.querySelector('[data-aimp-price]');
		var needsChoice = bar.hasAttribute('data-giftcard');

		var mobile = document.createElement('div');
		mobile.className = 'aimp-mobile-buy';
		mobile.setAttribute('aria-hidden', 'true');
		mobile.innerHTML =
			'<div class="aimp-mobile-buy-info"><span class="aimp-mobile-buy-name"></span><span class="aimp-mobile-buy-price"></span></div>' +
			'<button type="button" class="aimp-mobile-buy-button" tabindex="-1"></button>';
		mobile.querySelector('.aimp-mobile-buy-name').textContent = title ? title.textContent.trim() : '';
		mobile.querySelector('.aimp-mobile-buy-button').textContent = real.textContent.trim();
		document.body.appendChild(mobile);

		function syncPrice() {
			mobile.querySelector('.aimp-mobile-buy-price').innerHTML = price ? price.innerHTML : '';
		}
		syncPrice();
		if (price && 'MutationObserver' in window) {
			new MutationObserver(syncPrice).observe(price, { childList: true, subtree: true, characterData: true });
		}

		mobile.querySelector('button').addEventListener('click', function () {
			if (needsChoice) {
				bar.scrollIntoView({ behavior: 'smooth', block: 'center' });
				return;
			}
			real.click();
		});

		var phone = window.matchMedia('(max-width: 700px)');
		var outOfView = false;
		function update() {
			var show = phone.matches && outOfView;
			mobile.classList.toggle('is-visible', show);
			mobile.setAttribute('aria-hidden', show ? 'false' : 'true');
			document.body.classList.toggle('aimp-has-mobile-buy', phone.matches);
		}
		new IntersectionObserver(function (entries) {
			// Only once the bar is above the screen (scrolled past), not before reaching it.
			outOfView = !entries[0].isIntersecting && entries[0].boundingClientRect.top < 0;
			update();
		}).observe(bar);
		if (phone.addEventListener) {
			phone.addEventListener('change', update);
		}
		update();
	}

	function boot() {
		var bar = document.querySelector('.aimp-product [data-aimp-buy]');
		if (bar) {
			setup(bar);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
