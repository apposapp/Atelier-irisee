/**
 * Atelier Irisee product pages: gallery with lightbox, size pictures, the length field in cm
 * (with the price for the chosen length) and remembering the chosen language.
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
	 * Fabric, ribbon and bias tape are sold per 10 cm. The cart counts units of 10 cm, so WooCommerce's
	 * quantity field stays (hidden) and a field in cm, stepping by 10, sets it. The price shows the
	 * total for the chosen length. Without JavaScript the normal quantity field is used.
	 */
	function setupLength(root) {
		var buy = root.querySelector('[data-aimp-per-10cm]');
		var qty = buy ? buy.querySelector('form.cart input.qty') : null;
		if (!qty || qty.type === 'hidden') {
			return;
		}
		var quantity = qty.closest('.quantity') || qty;
		var unitPrice = parseFloat(buy.getAttribute('data-unit-price')) || 0;
		var minUnits = Math.max(1, parseInt(qty.getAttribute('min'), 10) || 1);
		var maxUnits = parseInt(qty.getAttribute('max'), 10) || 0;
		var stepUnits = Math.max(1, parseInt(qty.getAttribute('step'), 10) || 1);

		var wrap = document.createElement('div');
		wrap.className = 'aimp-length';
		wrap.innerHTML =
			'<input type="number" class="aimp-length-input" inputmode="numeric"' +
			' min="' + minUnits * 10 + '" step="' + stepUnits * 10 + '"' + (maxUnits > 0 ? ' max="' + maxUnits * 10 + '"' : '') +
			' aria-label="' + UI.esc(t.length) + '">' +
			'<span class="aimp-length-unit" aria-hidden="true">' + UI.esc(t.cm) + '</span>';
		quantity.parentNode.insertBefore(wrap, quantity);
		quantity.hidden = true;

		var field = wrap.querySelector('input');
		var price = root.querySelector('[data-aimp-price]');
		var unitLine = root.querySelector('[data-aimp-unit-line]');

		var units = function (cm) {
			var n = Math.ceil((parseFloat(cm) || 0) / 10);
			n = Math.max(minUnits, Math.ceil(n / stepUnits) * stepUnits);
			return maxUnits > 0 ? Math.min(maxUnits, n) : n;
		};
		var showPrice = function (n) {
			if (!unitPrice) {
				return;
			}
			if (price) {
				price.innerHTML = '<span class="woocommerce-Price-amount amount">' + UI.esc(UI.money(unitPrice * n, cfg.currency)) + '</span>';
			}
			if (unitLine) {
				unitLine.textContent = UI.money(unitPrice, cfg.currency) + ' ' + t.per10cm;
			}
		};
		var apply = function (normalize) {
			var n = units(field.value);
			if (String(qty.value) !== String(n)) {
				qty.value = n;
				qty.dispatchEvent(new Event('change', { bubbles: true }));
			}
			if (normalize) {
				field.value = n * 10;
			}
			showPrice(n);
		};

		field.value = units((parseInt(qty.value, 10) || minUnits) * 10) * 10;
		field.addEventListener('input', function () {
			if (field.value !== '') {
				apply(false);
			}
		});
		field.addEventListener('change', function () {
			apply(true);
		});
		field.addEventListener('blur', function () {
			apply(true);
		});
		apply(true);
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
			setupLength(root);
			setupLanguages(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
