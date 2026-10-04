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

		// Round up to a whole step and keep it within min and max (a typed 25 cm becomes 30 cm).
		var clean = function (value) {
			var n = Math.ceil((parseFloat(value) || 0) / step) * step;
			n = Math.max(min, n);
			return max ? Math.min(max, n) : n;
		};
		var showPrice = function (amount) {
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

		// Gift cards: the price is the chosen amount (+ printing fee), sent by giftcards.js.
		if (bar.hasAttribute('data-giftcard')) {
			root.addEventListener('aimp:gc-total', function (e) {
				if (e.detail && e.detail.total > 0) {
					unitPrice = e.detail.total;
					showPrice(parseInt(field.value, 10) || min);
				}
			});
			var gcForm = root.querySelector('[data-aimp-gc-form]');
			if (gcForm) {
				gcForm.dispatchEvent(new Event('change', { bubbles: true }));
			}
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
