/**
 * Product edit screen for fabrics and patterns: moves the existing fields into the "Atelier Irisee"
 * workspace cards, and the rest into "Show all other fields". Nothing is copied, so everything saves as
 * before. Runs right away in the footer, before the text editors start (moving a running editor would
 * empty it).
 */
(function () {
	'use strict';

	var ws = document.querySelector('[data-aimp-ws]');
	if (!ws) {
		return;
	}
	var type = ws.getAttribute('data-type');
	var other = ws.querySelector('[data-ws-slot="other"]');
	var STORE = 'aimp_ws_other_open';
	var pendingOther = []; // Made here, tucked under "other fields" below.

	function slot(name) {
		return ws.querySelector('[data-ws-slot="' + name + '"]');
	}

	// Move elements (in order) into a card; ignores ones that aren't on the page.
	function move(name, elements) {
		var target = slot(name);
		if (!target) {
			return;
		}
		elements.forEach(function (el) {
			if (el) {
				target.appendChild(el);
			}
		});
	}

	function q(selector, root) {
		return (root || document).querySelector(selector);
	}

	function closestGroup(selector) {
		var el = q(selector);
		return el ? el.closest('.options_group') : null;
	}

	// A meta box into "Show all other fields", as a foldable group with its own title.
	function tuck(box, title) {
		if (!box || box.closest('[data-aimp-ws]') || box.id === 'aimp-workspace') {
			return;
		}
		var label = title || (q('.hndle', box) ? q('.hndle', box).textContent.trim() : box.id);
		var group = document.createElement('details');
		group.className = 'aimp-ws-group';
		group.innerHTML = '<summary></summary>';
		group.querySelector('summary').textContent = label;
		other.appendChild(group);
		group.appendChild(box);
		box.classList.add('aimp-ws-tucked');
	}

	// Price and stock: WooCommerce's own rows.
	var inventory = q('#inventory_product_data');
	var stockRows = inventory ? [q('._manage_stock_field', inventory), q('.stock_fields', inventory), q('.stock_status_field', inventory)] : [];

	if (type === 'fabric') {
		move('price', [q('#general_product_data .pricing')].concat(stockRows));
		var texts = q('.aimp-fabric-texts');
		if (texts) {
			var lists = texts.querySelectorAll('.aimp-fabric-lists > fieldset');
			move('inspiration', [q('.aimp-fabric-cards', texts), q('.aimp-fabric-editors', texts)]);
			move('specs', [lists[0]]);
			move('washing', [lists[1]]);
			var textsBox = q('#aimp-fabric-texts');
			if (textsBox) {
				textsBox.classList.add('aimp-ws-emptied');
			}
		}
	} else if (type === 'pattern') {
		// The short description is the pattern's description; the long one goes under "other fields".
		var excerpt = q('#postexcerpt');
		if (excerpt) {
			move('description', [excerpt]);
			excerpt.classList.add('aimp-ws-inline-box');
		}
		var longText = q('#postdivrich');
		if (longText) {
			var longBox = document.createElement('div');
			longBox.className = 'postbox aimp-ws-longtext';
			longBox.innerHTML = '<div class="postbox-header"><h2 class="hndle"></h2></div>';
			longBox.querySelector('.hndle').textContent = ws.getAttribute('data-long-label') || 'Long description';
			longBox.appendChild(longText);
			pendingOther.push(longBox);
		}
		var panel = q('#aimp_pattern_data');
		move('check', [panel ? q('.aimp-availability', panel) : null]);
		move('price', [closestGroup('#' + ws.getAttribute('data-price'))].concat(stockRows));
		move('details', [closestGroup('#' + ws.getAttribute('data-skill'))]);
		move('priority', [panel ? q('.aimp-priority', panel) : null]);
		// Sizes: WooCommerce's product data box, showing only Attributes and Variations.
		var data = q('#woocommerce-product-data');
		if (data) {
			move('sizes', [data]);
			data.classList.add('aimp-ws-sizes');
		}
		var tab = q('.aimp_pattern_options');
		if (tab) {
			tab.classList.add('aimp-ws-emptied');
		}
		// Open the Variations tab once WooCommerce's scripts are ready.
		window.addEventListener('load', function () {
			var link = q('.aimp-ws .variations_options a');
			if (link) {
				link.click();
			}
		});
	}

	// Recommendation: the side box's content.
	var rec = q('#aimp-recommendation');
	if (rec) {
		var inside = q('.inside', rec);
		if (inside) {
			move('recommendation', Array.prototype.slice.call(inside.children));
		}
		rec.classList.add('aimp-ws-emptied');
	}

	// Everything else, grouped.
	pendingOther.forEach(function (box) {
		tuck(box);
	});
	if (type === 'fabric') {
		tuck(q('#woocommerce-product-data'));
	}
	tuck(q('#postexcerpt'));
	tuck(q('#tagsdiv-product_tag'));
	['#normal-sortables', '#advanced-sortables'].forEach(function (area) {
		var container = q(area);
		if (!container) {
			return;
		}
		Array.prototype.slice.call(container.children).forEach(function (box) {
			if (box.classList.contains('postbox') && !box.classList.contains('aimp-ws-emptied') && box.style.display !== 'none') {
				tuck(box);
			}
		});
	});

	// "Show all other fields": remember open or closed; for patterns it also shows every product data tab.
	var details = ws.querySelector('[data-ws-other]');
	try {
		details.open = window.localStorage.getItem(STORE) === '1';
	} catch (e) {}
	var sync = function () {
		ws.classList.toggle('is-showing-all', details.open);
		try {
			window.localStorage.setItem(STORE, details.open ? '1' : '0');
		} catch (e) {}
	};
	details.addEventListener('toggle', sync);
	sync();

	ws.classList.add('is-ready');
})();
