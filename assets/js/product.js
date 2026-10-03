/**
 * Atelier Irisee product pages: gallery with lightbox, size pictures, measuring guide,
 * the "× 10 cm" helper next to the quantity, and remembering the chosen language.
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

	function setupMeasureGuide(root) {
		var button = root.querySelector('[data-aimp-measure]');
		if (!button) {
			return;
		}
		button.addEventListener('click', function () {
			var dialog = UI.openLightbox(root, [{ src: cfg.measureImage, alt: button.textContent.trim() }], 0, t);
			dialog.setAttribute('aria-label', button.textContent.trim());
		});
	}

	// Fabric, ribbon and bias tape are sold per 10 cm: "3 × 10 cm = 30 cm".
	function setupUnitHelp(root) {
		var buy = root.querySelector('[data-aimp-per-10cm]');
		var help = buy ? buy.querySelector('[data-aimp-unit-help]') : null;
		var input = buy ? buy.querySelector('input.qty') : null;
		if (!help || !input) {
			return;
		}
		var update = function () {
			var qty = Math.max(0, parseInt(input.value, 10) || 0);
			help.textContent = qty ? UI.fmt(t.unitHelp, qty, qty * 10) : '';
		};
		input.addEventListener('input', update);
		input.addEventListener('change', update);
		update();
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
			setupMeasureGuide(root);
			setupUnitHelp(root);
			setupLanguages(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
