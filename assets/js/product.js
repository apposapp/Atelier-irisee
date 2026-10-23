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
		var dots = [];
		var show = UI.bindGallery(root.querySelector('.aimp-product-media'), images, function (i) {
			current = i;
			dots.forEach(function (dot, n) {
				dot.classList.toggle('is-active', n === i);
				dot.setAttribute('aria-pressed', n === i ? 'true' : 'false');
			});
		});

		var main = gallery.querySelector('.aimp-gallery-main');
		var img = main.querySelector('img');
		img.addEventListener('click', function () {
			UI.openLightbox(root, images, current, t);
		});

		// Desktop: zoom in where the mouse is.
		if (window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
			main.addEventListener('mousemove', function (e) {
				var box = main.getBoundingClientRect();
				img.style.transformOrigin = ((e.clientX - box.left) / box.width) * 100 + '% ' + ((e.clientY - box.top) / box.height) * 100 + '%';
				main.classList.add('is-zooming');
			});
			main.addEventListener('mouseleave', function () {
				main.classList.remove('is-zooming');
			});
		}

		// Phones: swipe through the pictures, with dots under the picture (the thumbnails are hidden there).
		if (images.length > 1) {
			var dotRow = document.createElement('div');
			dotRow.className = 'aimp-gallery-dots';
			images.forEach(function (image, i) {
				var dot = document.createElement('button');
				dot.type = 'button';
				dot.className = 'aimp-gallery-dot' + (i === 0 ? ' is-active' : '');
				dot.setAttribute('aria-pressed', i === 0 ? 'true' : 'false');
				dot.setAttribute('aria-label', (t.showPicture || '%d').replace('%d', i + 1));
				dot.addEventListener('click', function () {
					show(i);
				});
				dots.push(dot);
				dotRow.appendChild(dot);
			});
			main.insertAdjacentElement('afterend', dotRow);

			var startX = null;
			var startY = 0;
			main.addEventListener('touchstart', function (e) {
				startX = e.touches[0].clientX;
				startY = e.touches[0].clientY;
			}, { passive: true });
			main.addEventListener('touchend', function (e) {
				if (startX === null) {
					return;
				}
				var dx = e.changedTouches[0].clientX - startX;
				var dy = e.changedTouches[0].clientY - startY;
				startX = null;
				if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
					show((current + (dx < 0 ? 1 : images.length - 1)) % images.length);
				}
			}, { passive: true });
		}

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
		// Haberdashery: what the price is for ("per piece", "per set of 6", "per roll of 3 m"), always shown.
		var unitText = bar.getAttribute('data-unit-text') || '';
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
				} else if (unitText) {
					unitLine.textContent = UI.money(unitPrice, cfg.currency) + ' ' + unitText;
					unitLine.hidden = false;
				} else {
					// Other products: "€x per piece" from 2 pieces.
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

	// Patterns: "How to measure your body measurements" above the size table opens the measuring picture.
	function setupMeasureGuide(root) {
		if (!cfg.measureImage) {
			return;
		}
		root.querySelectorAll('[data-aimp-measure]').forEach(function (button) {
			button.addEventListener('click', function () {
				var dialog = UI.openLightbox(root, [{ src: cfg.measureImage, alt: cfg.howToMeasure }], 0, t);
				if (dialog && dialog.setAttribute) {
					dialog.setAttribute('aria-label', cfg.howToMeasure);
				}
			});
		});
	}

	/**
	 * Phones: once the buy bar has scrolled away, a slim bar at the bottom keeps the price and "Add to cart" in
	 * reach (above the tab bar). Its button presses the buy bar's own button, so the chosen amount is used.
	 */
	function setupStickyBuy(root) {
		var bar = root.querySelector('[data-aimp-buy]');
		var add = bar ? bar.querySelector('.aimp-add-to-cart:not([disabled])') : null;
		// Design studio: on every screen, or never.
		var mode = root.getAttribute('data-pp-sticky') || '';
		if (!add || mode === 'none' || bar.hasAttribute('data-giftcard') || !('IntersectionObserver' in window) || !window.matchMedia) {
			return;
		}
		var phone = window.matchMedia(mode === 'all' ? 'all' : '(max-width: 700px)');
		var price = bar.querySelector('[data-aimp-price]');
		var title = root.querySelector('.aimp-product-title');
		var sticky = document.createElement('div');
		sticky.className = 'aimp-sticky-buy' + (mode === 'all' ? ' aimp-sticky-buy--all' : '');
		sticky.setAttribute('aria-hidden', 'true');
		sticky.innerHTML =
			'<div class="aimp-sticky-buy-text"><span class="aimp-sticky-buy-name"></span><span class="aimp-sticky-buy-price"></span></div>' +
			'<button type="button" class="aimp-sticky-buy-button" tabindex="-1"></button>';
		sticky.querySelector('.aimp-sticky-buy-name').textContent = title ? title.textContent : '';
		sticky.querySelector('.aimp-sticky-buy-button').textContent = add.textContent;
		document.body.appendChild(sticky);

		var syncPrice = function () {
			sticky.querySelector('.aimp-sticky-buy-price').innerHTML = price ? price.innerHTML : '';
		};
		syncPrice();
		if (price && window.MutationObserver) {
			new MutationObserver(syncPrice).observe(price, { childList: true, subtree: true, characterData: true });
		}
		sticky.querySelector('button').addEventListener('click', function () {
			add.click();
		});

		var passed = false;
		var update = function () {
			var show = phone.matches && passed;
			sticky.classList.toggle('is-visible', show);
			sticky.setAttribute('aria-hidden', show ? 'false' : 'true');
		};
		new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				// Only once the buy bar is above the screen (scrolled past), not before it is reached.
				passed = !entry.isIntersecting && entry.boundingClientRect.top < 0;
				update();
			});
		}).observe(bar);
		if (phone.addEventListener) {
			phone.addEventListener('change', update);
		}
	}

	/**
	 * Design studio: the blocks under the pictures as dropdowns (the first one open) or as tabs, in the order they
	 * are shown in.
	 */
	function setupLayout(root) {
		var layout = root.getAttribute('data-pp-layout');
		if (layout !== 'accordion' && layout !== 'tabs') {
			return;
		}
		var blocks = Array.prototype.filter.call(root.children, function (el) {
			return /(^|\s)aimp-pp-(?!recommend)/.test(el.className) && el.querySelector('h2, h3') && window.getComputedStyle(el).display !== 'none';
		});
		var order = function (el) {
			return parseInt(window.getComputedStyle(el).order, 10) || 0;
		};
		blocks.sort(function (a, b) {
			return order(a) - order(b);
		});
		if (!blocks.length) {
			return;
		}
		var opens = [];
		if (layout === 'accordion') {
			blocks.forEach(function (el, i) {
				var head = el.querySelector('h2, h3');
				var details = document.createElement('details');
				details.className = 'aimp-pp-acc';
				details.open = i === 0;
				var summary = document.createElement('summary');
				summary.className = 'aimp-pp-acc-title';
				summary.textContent = head.textContent;
				head.parentNode.removeChild(head);
				var body = document.createElement('div');
				body.className = 'aimp-pp-acc-body';
				while (el.firstChild) {
					body.appendChild(el.firstChild);
				}
				details.appendChild(summary);
				details.appendChild(body);
				el.appendChild(details);
				el.classList.add('is-accordion');
				opens.push(function () {
					details.open = true;
				});
			});
		} else {
			var nav = document.createElement('div');
			nav.className = 'aimp-pp-tabs';
			nav.setAttribute('role', 'tablist');
			var tabs = [];
			var show = function (n) {
				blocks.forEach(function (el, i) {
					el.classList.toggle('is-tab-hidden', i !== n);
					tabs[i].setAttribute('aria-selected', i === n ? 'true' : 'false');
					tabs[i].tabIndex = i === n ? 0 : -1;
				});
			};
			blocks.forEach(function (el, i) {
				var head = el.querySelector('h2, h3');
				var tab = document.createElement('button');
				tab.type = 'button';
				tab.className = 'aimp-pp-tab';
				tab.setAttribute('role', 'tab');
				tab.textContent = head.textContent;
				head.classList.add('screen-reader-text');
				el.classList.add('is-tab');
				el.setAttribute('role', 'tabpanel');
				tab.addEventListener('click', function () {
					show(i);
				});
				tab.addEventListener('keydown', function (e) {
					var step = e.key === 'ArrowRight' ? 1 : (e.key === 'ArrowLeft' ? -1 : 0);
					if (step) {
						e.preventDefault();
						var next = (i + step + tabs.length) % tabs.length;
						show(next);
						tabs[next].focus();
					}
				});
				tabs.push(tab);
				nav.appendChild(tab);
				opens.push(function () {
					show(i);
				});
			});
			// The tabs right before the first block (same order: the flex layout keeps them together).
			nav.style.order = window.getComputedStyle(blocks[0]).order;
			blocks[0].parentNode.insertBefore(nav, blocks[0]);
			show(0);
		}
		// "3 reviews" next to the stars opens the reviews.
		root.addEventListener('click', function (e) {
			var link = e.target.closest ? e.target.closest('a[href^="#aimp-"]') : null;
			var target = link ? document.getElementById(link.getAttribute('href').slice(1)) : null;
			var i = target ? blocks.indexOf(target) : -1;
			if (i !== -1) {
				opens[i]();
			}
		});
	}

	// Design studio: "Copy link" in the share buttons.
	function setupShare(root) {
		root.querySelectorAll('[data-aimp-copy]').forEach(function (button) {
			var label = button.textContent;
			button.addEventListener('click', function () {
				var done = function () {
					button.textContent = t.linkCopied || label;
					setTimeout(function () {
						button.textContent = label;
					}, 2000);
				};
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(button.getAttribute('data-aimp-copy')).then(done, function () {
						window.prompt('', button.getAttribute('data-aimp-copy'));
					});
				} else {
					window.prompt('', button.getAttribute('data-aimp-copy'));
				}
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
			setupStickyBuy(root);
			setupMeasureGuide(root);
			setupGiftcardDesign(root);
			setupLanguages(root);
			setupShare(root);
			setupLayout(root);
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

/**
 * Phones: the recommendation box ("Recommended pattern" and the sewing kit buttons) moves from under the buy bar
 * to its own section after the description (product.css puts that section in place); back on wider screens.
 */
(function () {
	'use strict';

	function boot() {
		var page = document.querySelector('.aimp-configurator.aimp-product');
		var box = page ? page.querySelector('.aimp-product-summary .aimp-recommend-box') : null;
		if (!box || !window.matchMedia) {
			return;
		}
		var home = document.createComment('aimp-recommend-box');
		box.parentNode.insertBefore(home, box);
		var section = document.createElement('section');
		section.className = 'aimp-product-section aimp-recommend-section aimp-pp-recommend';
		page.appendChild(section);
		var phone = window.matchMedia('(max-width: 700px)');
		function place() {
			if (phone.matches) {
				section.appendChild(box);
			} else if (home.parentNode) {
				home.parentNode.insertBefore(box, home.nextSibling);
			}
			section.style.display = phone.matches ? '' : 'none';
		}
		if (phone.addEventListener) {
			phone.addEventListener('change', place);
		}
		place();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();

/**
 * A pattern's "Add to cart" on a page from the cache (LiteSpeed): the page can be days old and its security code
 * expired, so a fresh one is fetched first when the page is older than 10 hours.
 */
(function () {
	'use strict';

	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form.classList || !form.classList.contains('aimp-buy-form') || form.aimpFreshNonce) {
			return;
		}
		var field = form.querySelector('input[name="aimp_pattern_nonce"]');
		var rendered = parseInt(form.getAttribute('data-rendered'), 10) || 0;
		if (!field || !window.fetch || Date.now() / 1000 - rendered < 36000) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		var send = function () {
			form.aimpFreshNonce = true;
			if (form.requestSubmit) {
				form.requestSubmit();
			} else {
				form.submit();
			}
		};
		fetch(form.getAttribute('data-nonces'), { method: 'POST', credentials: 'same-origin' })
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				if (json && json.success && json.data.add_pattern) {
					field.value = json.data.add_pattern;
				}
			})
			.catch(function () {})
			.then(send);
	}, true);
})();
