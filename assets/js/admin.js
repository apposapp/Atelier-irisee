/**
 * Pattern product edit screen: the "Fabric categories shown first" table (Atelier Irisee tab)
 * lists exactly the fabric categories ticked under "Fabric categories allowed" on the sizes.
 *
 * Sizes that are loaded in the Variations tab count with their current checkboxes (also before
 * saving); sizes that are not loaded count with their saved categories.
 */
jQuery(function ($) {
	'use strict';

	var $table = $('.aimp-priority-table');
	if (!$table.length) {
		return;
	}
	var saved = $table.data('saved') || {};

	function refresh() {
		var ticked = {};
		var loaded = {};

		$('#variable_product_options .woocommerce_variation').each(function () {
			var $variation = $(this);
			var id = parseInt($variation.find('input.variable_post_id').val(), 10);
			if (id) {
				loaded[id] = true;
			}
			$variation.find('.aimp-fabric-cat-checkbox:checked').each(function () {
				ticked[parseInt(this.value, 10)] = true;
			});
		});

		Object.keys(saved).forEach(function (variationId) {
			if (!loaded[variationId]) {
				(saved[variationId] || []).forEach(function (termId) {
					ticked[termId] = true;
				});
			}
		});

		var any = false;
		$table.find('tbody tr[data-term]').each(function () {
			var show = !!ticked[parseInt($(this).attr('data-term'), 10)];
			$(this).toggle(show);
			any = any || show;
		});
		$table.toggle(any);
		$('.aimp-priority-empty').toggle(!any);
	}

	$(document).on('change', '.aimp-fabric-cat-checkbox', refresh);
	$('#woocommerce-product-data').on('woocommerce_variations_loaded woocommerce_variations_added woocommerce_variations_removed', refresh);
	$('#woocommerce-product-data').on('click', '.aimp_pattern_options a, li.aimp_pattern_tab a', refresh);
	refresh();
});
