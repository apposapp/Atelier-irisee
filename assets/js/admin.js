/**
 * Pattern product edit screen: "Fitting fabrics" (Atelier Irisee tab). A position can only be given to a
 * fabric category that is ticked as allowed.
 */
jQuery(function ($) {
	'use strict';

	$(document).on('change', '.aimp-fitting-checkbox', function () {
		var $row = $(this).closest('tr');
		$row.toggleClass('is-off', !this.checked);
		$row.find('input[type="number"]').prop('disabled', !this.checked);
	});
});

/**
 * "Fabric texts" box: only for products in the Fabrics category (or one of its subcategories).
 * Follows the category checkboxes live, also before saving.
 */
jQuery(function ($) {
	'use strict';

	var $texts = $('.aimp-fabric-texts');
	var $box = $('#aimp-fabric-texts');
	if (!$texts.length || !$box.length) {
		return;
	}
	var terms = ($texts.data('fabric-terms') || []).map(function (id) {
		return parseInt(id, 10);
	});

	function refresh() {
		var isFabric = $('#product_catchecklist input[type="checkbox"]:checked').filter(function () {
			return terms.indexOf(parseInt(this.value, 10)) !== -1;
		}).length > 0;
		$box.toggle(isFabric);
	}

	$(document).on('change', '#product_catchecklist input[type="checkbox"]', refresh);
	refresh();
});

/**
 * Pattern sizes: price and stock come from the pattern (Atelier Irisee tab and Inventory tab), so the
 * price fields of a size are read-only and its own "Manage stock?" box is hidden.
 */
jQuery(function ($) {
	'use strict';

	function lockSizes() {
		$('#variable_product_options .woocommerce_variation').each(function () {
			var $variation = $(this);
			if (!$variation.find('.aimp-variation-fields').length) {
				return;
			}
			$variation.find('.variable_pricing input[type="text"]').prop('readonly', true).addClass('aimp-locked');
			$variation.find('.sale_schedule, .cancel_sale_schedule').hide();
			var $manage = $variation.find('input.variable_manage_stock');
			if ($manage.prop('checked')) {
				$manage.prop('checked', false).trigger('change');
			}
			$manage.closest('label').hide();
		});
	}

	$('#woocommerce-product-data').on('woocommerce_variations_loaded woocommerce_variations_added', lockSizes);
	lockSizes();
});

/**
 * Pattern with designs (Atelier Irisee tab → Designs): the "Main picture" and "Other pictures" grids follow the
 * product picture and gallery (also before saving). The main picture can't be ticked again as another picture.
 */
jQuery(function ($) {
	'use strict';

	var $grids = $('.aimp-design-pictures');
	if (!$grids.length) {
		return;
	}

	function productPictures() {
		var list = [];
		var mainId = parseInt($('#_thumbnail_id').val(), 10);
		var $mainImg = $('#postimagediv .inside img').first();
		if (mainId > 0 && $mainImg.length) {
			list.push({ id: mainId, src: $mainImg.attr('src') });
		}
		$('#product_images_container ul.product_images li.image').each(function () {
			var id = parseInt($(this).attr('data-attachment_id'), 10);
			var src = $(this).find('img').attr('src');
			if (id > 0 && src && !list.some(function (p) { return p.id === id; })) {
				list.push({ id: id, src: src });
			}
		});
		return list;
	}

	// The main picture of each design is not one of its "other pictures".
	function linkMain() {
		$('.aimp-design').each(function () {
			var $design = $(this);
			var main = $design.find('.aimp-design-main input:checked').val() || '';
			$design.find('.aimp-design-others li').each(function () {
				var $li = $(this);
				var isMain = $li.attr('data-id') === main;
				$li.toggleClass('is-main', isMain);
				var input = $li.find('input').get(0);
				if (input) {
					if (isMain) {
						input.checked = false;
					}
					input.disabled = isMain;
				}
			});
		});
	}

	function sync() {
		var pictures = productPictures();
		$grids.each(function () {
			var $grid = $(this);
			var name = $grid.attr('data-name');
			var type = $grid.attr('data-type') === 'radio' ? 'radio' : 'checkbox';
			var existing = {};
			$grid.children('li').each(function () {
				existing[$(this).attr('data-id')] = $(this);
			});
			$grid.children('li').detach();
			pictures.forEach(function (picture) {
				var $li = existing[picture.id];
				if (!$li) {
					$li = $('<li></li>').attr('data-id', picture.id).append(
						$('<label></label>')
							.append($('<input>').attr({ type: type, name: name }).val(picture.id))
							.append($('<img alt="">').attr('src', picture.src))
					);
				}
				$grid.append($li);
			});
			$grid.closest('.aimp-design').find('.aimp-design-empty').prop('hidden', pictures.length > 0);
		});
		linkMain();
	}

	$(document).on('change', '.aimp-design-main input', linkMain);
	var gallery = document.getElementById('product_images_container');
	var thumb = document.getElementById('postimagediv');
	if (window.MutationObserver) {
		var observer = new MutationObserver(function () {
			window.clearTimeout(sync.timer);
			sync.timer = window.setTimeout(sync, 150);
		});
		if (gallery) {
			observer.observe(gallery, { childList: true, subtree: true });
		}
		if (thumb) {
			observer.observe(thumb, { childList: true, subtree: true });
		}
	}
	linkMain();
});

/**
 * "Haberdashery details" box: only for products in one of the haberdashery categories (buttons, zips,
 * interfacing, elastic, ribbons & cords, bias tape), showing the fields of that kind. Follows the category
 * checkboxes live, also before saving. Typing a set size or a roll length chooses that way of selling.
 */
jQuery(function ($) {
	'use strict';

	var $details = $('.aimp-notion-details');
	var $box = $('#aimp-notion-details');
	if (!$details.length || !$box.length) {
		return;
	}
	var kindTerms = $details.data('kind-terms') || {};

	function currentKind() {
		var ticked = $('#product_catchecklist input[type="checkbox"]:checked').map(function () {
			return parseInt(this.value, 10);
		}).get();
		var found = '';
		Object.keys(kindTerms).some(function (kind) {
			var terms = (kindTerms[kind] || []).map(function (id) {
				return parseInt(id, 10);
			});
			if (ticked.some(function (id) { return terms.indexOf(id) !== -1; })) {
				found = kind;
				return true;
			}
			return false;
		});
		return found;
	}

	function refresh() {
		var kind = currentKind();
		$box.toggle(kind !== '');
		$details.find('.aimp-notion-kind').each(function () {
			this.hidden = this.getAttribute('data-kind') !== kind;
		});
	}

	$(document).on('change', '#product_catchecklist input[type="checkbox"]', refresh);
	refresh();

	$details.on('input', 'input[data-sold]', function () {
		if (this.value !== '') {
			$(this).closest('label').find('input[type="radio"]').prop('checked', true);
		}
	});
});

/**
 * Pattern → Haberdashery: what is switched off can't be filled in on the sizes (Variations tab), also before
 * saving; each group of a size says what is allowed.
 */
jQuery(function ($) {
	'use strict';

	var $rules = $('[data-aimp-notion-rules]');
	if (!$rules.length) {
		return;
	}

	function fmt(str, a, b) {
		return String(str).replace('%1$s', a).replace('%2$s', b).replace('%s', a);
	}

	function rangeText($rule) {
		if (!$rule.attr('data-range')) {
			return '';
		}
		var min = parseFloat($rule.find('input[name$="[min]"]').val()) || 0;
		var max = parseFloat($rule.find('input[name$="[max]"]').val()) || 0;
		if (min && max) {
			return min === max ? min + ' ' + $rule.attr('data-unit') : fmt($rule.attr('data-range'), Math.min(min, max), Math.max(min, max));
		}
		if (min) {
			return fmt($rule.attr('data-from'), min);
		}
		if (max) {
			return fmt($rule.attr('data-upto'), max);
		}
		return '';
	}

	function apply() {
		var onList = [];
		var texts = {};
		$rules.find('.aimp-notion-rule').each(function () {
			var $rule = $(this);
			var on = $rule.find('.aimp-notion-rule-toggle').prop('checked');
			var material = $rule.attr('data-material');
			$rule.toggleClass('is-on', on);
			// Switched off: hidden and disabled on every size, so nothing is typed or sent.
			var $fields = $('.aimp-variation-fields .aimp-material-field[data-material="' + material + '"]');
			$fields.prop('hidden', !on);
			$fields.find('input').prop('disabled', !on);
			if (on) {
				onList.push(material);
				var range = rangeText($rule);
				texts[material] = $rule.attr('data-label') + (range ? ' ' + range : '');
			}
		});
		$('.aimp-variation-fields [data-aimp-allowed]').each(function () {
			var parts = this.getAttribute('data-aimp-allowed').split(',').filter(function (material) {
				return !!texts[material];
			}).map(function (material) {
				return texts[material];
			});
			this.textContent = parts.length ? fmt($rules.attr('data-allowed'), parts.join(' · ')) : $rules.attr('data-none');
		});
		$('.aimp-variation-fields .aimp-notion-on').val(onList.join(','));
	}

	$rules.on('change', '.aimp-notion-rule-toggle', apply);
	$rules.on('input', 'input[type="number"]', apply);
	$('#woocommerce-product-data').on('woocommerce_variations_loaded woocommerce_variations_added', apply);
	apply();
});
