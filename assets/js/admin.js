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
 * Pattern with designs (Atelier Irisee tab → Designs): the picture grids follow the product picture and
 * gallery (also before saving), and the first ticked picture of a design is marked as its card picture.
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

	function markCards() {
		$grids.each(function () {
			var $grid = $(this);
			$grid.find('li').removeClass('is-card').find('.aimp-design-card').remove();
			var $first = $grid.find('input:checked').first().closest('li');
			if ($first.length) {
				$first.addClass('is-card').append($('<span class="aimp-design-card"></span>').text($grid.attr('data-card-label') || ''));
			}
		});
	}

	function sync() {
		var pictures = productPictures();
		$grids.each(function () {
			var $grid = $(this);
			var name = $grid.attr('data-name');
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
							.append($('<input type="checkbox">').attr('name', name).val(picture.id))
							.append($('<img alt="">').attr('src', picture.src))
					);
				}
				$grid.append($li);
			});
			$grid.closest('.aimp-design').find('.aimp-design-empty').prop('hidden', pictures.length > 0);
		});
		markCards();
	}

	$(document).on('change', '.aimp-design-pictures input', markCards);
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
	markCards();
});

/**
 * "Button details" box: only for products in the Buttons category (or one of its subcategories), following the
 * category checkboxes live. The set size is only filled in for "Per set of".
 */
jQuery(function ($) {
	'use strict';

	var $details = $('.aimp-button-details');
	var $box = $('#aimp-button-details');
	if (!$details.length || !$box.length) {
		return;
	}
	var terms = ($details.data('button-terms') || []).map(function (id) {
		return parseInt(id, 10);
	});

	function refresh() {
		var isButton = $('#product_catchecklist input[type="checkbox"]:checked').filter(function () {
			return terms.indexOf(parseInt(this.value, 10)) !== -1;
		}).length > 0;
		$box.toggle(isButton);
	}

	$(document).on('change', '#product_catchecklist input[type="checkbox"]', refresh);
	refresh();

	// Typing a set size chooses "Per set of".
	$details.on('input', 'input[name="aimp_button[set_size]"]', function () {
		if (this.value !== '') {
			$details.find('input[name="aimp_button[sold]"][value="set"]').prop('checked', true);
		}
	});
});

/**
 * Pattern → Buttons: a switched-off type has no amount field on the sizes (Variations tab), also before saving.
 */
jQuery(function ($) {
	'use strict';

	var $rules = $('[data-aimp-button-rules]');
	if (!$rules.length) {
		return;
	}

	function fmt(str, a, b) {
		return String(str).replace('%1$s', a).replace('%2$s', b).replace('%s', a);
	}

	function apply() {
		var onList = [];
		var parts = [];
		$rules.find('.aimp-button-rule').each(function () {
			var $rule = $(this);
			var on = $rule.find('.aimp-button-rule-toggle').prop('checked');
			var material = $rule.attr('data-material');
			$rule.toggleClass('is-on', on);
			// A type that is off can't be filled in on the sizes (hidden and disabled, so it is not sent).
			var $fields = $('.aimp-variation-fields .aimp-material-field[data-material="' + material + '"]');
			$fields.prop('hidden', !on);
			$fields.find('input').prop('disabled', !on);
			if (on) {
				onList.push(material);
				var min = parseFloat($rule.find('input[name$="[min]"]').val()) || 0;
				var max = parseFloat($rule.find('input[name$="[max]"]').val()) || 0;
				var range = '';
				if (min && max) {
					range = min === max ? fmt($rules.attr('data-from'), min).replace(/^\D+/, '') : fmt($rules.attr('data-range'), Math.min(min, max), Math.max(min, max));
				} else if (min) {
					range = fmt($rules.attr('data-from'), min);
				} else if (max) {
					range = fmt($rules.attr('data-upto'), max);
				}
				parts.push($rule.attr('data-label') + (range ? ' ' + range : ''));
			}
		});
		var note = parts.length ? fmt($rules.attr('data-allowed'), parts.join(' · ')) : $rules.attr('data-none');
		$('.aimp-variation-fields [data-aimp-button-allowed]').text(note);
		$('.aimp-variation-fields .aimp-button-on').val(onList.join(','));
	}

	$rules.on('change', '.aimp-button-rule-toggle', apply);
	$rules.on('input', 'input[type="number"]', apply);
	$('#woocommerce-product-data').on('woocommerce_variations_loaded woocommerce_variations_added', apply);
	apply();
});
