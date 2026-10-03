/**
 * Atelier Irisee account page: show the items of the order chosen in the refund form.
 */
(function () {
	'use strict';

	document.querySelectorAll('[data-aimp-refund-form]').forEach(function (form) {
		var select = form.querySelector('select[name="aimp_refund[order]"]');
		if (!select) {
			return;
		}
		function update() {
			form.querySelectorAll('.aimp-refund-items').forEach(function (fieldset) {
				var show = fieldset.getAttribute('data-order') === select.value;
				fieldset.hidden = !show;
				// Only the chosen order's items are sent.
				fieldset.querySelectorAll('input').forEach(function (input) {
					if (!input.closest('.is-disabled')) {
						input.disabled = !show;
					}
				});
			});
		}
		select.addEventListener('change', update);
		update();
	});
})();
