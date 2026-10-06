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

/**
 * Address forms in a lightbox (<dialog>): "Add" / "Edit" opens it, × / Cancel / Esc / a click on the
 * backdrop closes it. A form that comes back with errors opens again by itself.
 */
(function () {
	'use strict';

	var dialogs = document.querySelectorAll('[data-aimp-address-dialog]');
	if (!dialogs.length || typeof dialogs[0].showModal !== 'function') {
		return; // Old browsers: the links open the form on the page.
	}

	function open(dialog) {
		if (dialog.open) {
			dialog.close();
		}
		dialog.showModal();
		var first = dialog.querySelector('.aimp-address-errors') ? dialog.querySelector('.woocommerce-invalid input, input:not([type="hidden"]), select') : dialog.querySelector('input:not([type="hidden"]), select');
		if (first) {
			first.focus();
		}
	}

	dialogs.forEach(function (dialog) {
		var start = dialog.hasAttribute('data-aimp-open');
		dialog.removeAttribute('open');
		if (start) {
			open(dialog);
		}
		dialog.addEventListener('click', function (e) {
			if (e.target === dialog) {
				dialog.close(); // Click on the backdrop.
				return;
			}
			if (e.target.closest && e.target.closest('[data-aimp-address-close]')) {
				e.preventDefault();
				dialog.close();
			}
		});
	});

	document.addEventListener('click', function (e) {
		var button = e.target.closest ? e.target.closest('[data-aimp-address-open]') : null;
		if (!button) {
			return;
		}
		var dialog = document.querySelector('[data-aimp-address-dialog="' + button.getAttribute('data-aimp-address-open') + '"]');
		if (dialog) {
			e.preventDefault();
			open(dialog);
		}
	});
})();
