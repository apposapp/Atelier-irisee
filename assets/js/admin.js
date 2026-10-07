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
