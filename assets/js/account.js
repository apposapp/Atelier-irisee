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

/**
 * Phones: the account menu is a drawer from the left. The button with the open tab's name opens it; the ×, the
 * backdrop, Esc or choosing a tab closes it.
 */
(function () {
	'use strict';

	var account = document.querySelector('.aimp-account');
	var toggle = account ? account.querySelector('.aimp-account-menu-toggle') : null;
	if (!toggle) {
		return;
	}
	var backdrop = account.querySelector('.aimp-account-backdrop');
	var nav = account.querySelector('.aimp-account-tabs');

	function setOpen(open) {
		account.classList.toggle('is-menu-open', open);
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (backdrop) {
			backdrop.hidden = !open;
		}
		if (open) {
			var active = nav.querySelector('.aimp-account-tab.is-active') || nav.querySelector('a');
			if (active) {
				active.focus({ preventScroll: true });
			}
		}
	}

	toggle.addEventListener('click', function () {
		setOpen(!account.classList.contains('is-menu-open'));
	});
	account.querySelector('.aimp-account-close').addEventListener('click', function () {
		setOpen(false);
		toggle.focus();
	});
	if (backdrop) {
		backdrop.addEventListener('click', function () {
			setOpen(false);
		});
	}
	nav.addEventListener('click', function (event) {
		if (event.target.closest('.aimp-account-tab')) {
			setOpen(false);
		}
	});
	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key && account.classList.contains('is-menu-open')) {
			setOpen(false);
			toggle.focus();
		}
	});
})();

/**
 * My orders on phones: "View" opens the order's details in a sheet from the bottom instead of a new page
 * (the details are read from that page). Without JavaScript the link simply opens the page.
 */
(function () {
	'use strict';

	var account = document.querySelector('.aimp-account');
	if (!account || !window.fetch || !window.DOMParser || !window.matchMedia) {
		return;
	}
	var phone = window.matchMedia('(max-width: 700px)');
	var sheet = null;

	function close() {
		if (!sheet) {
			return;
		}
		sheet.classList.remove('is-open');
		document.documentElement.classList.remove('aimp-sheet-open');
		window.setTimeout(function () {
			sheet.hidden = true;
		}, 450);
	}

	function open(html) {
		if (!sheet) {
			sheet = document.createElement('div');
			sheet.className = 'aimp-account-sheet';
			sheet.hidden = true;
			sheet.innerHTML =
				'<div class="aimp-account-sheet-backdrop" data-close></div>' +
				'<div class="aimp-account-sheet-panel aimp-account" role="dialog" aria-modal="true">' +
				'<div class="aimp-account-sheet-head"><button type="button" class="aimp-account-sheet-close" data-close aria-label="' +
				(account.getAttribute('data-close-label') || 'Close').replace(/"/g, '&quot;') + '">×</button></div>' +
				'<div class="aimp-account-sheet-body"></div></div>';
			document.body.appendChild(sheet);
			sheet.addEventListener('click', function (e) {
				if (e.target.closest('[data-close]')) {
					close();
				}
			});
			document.addEventListener('keydown', function (e) {
				if ('Escape' === e.key && sheet && !sheet.hidden) {
					close();
				}
			});
		}
		var body = sheet.querySelector('.aimp-account-sheet-body');
		body.innerHTML = '';
		body.appendChild(html);
		sheet.querySelector('.aimp-account-sheet-panel').scrollTop = 0;
		sheet.hidden = false;
		document.documentElement.classList.add('aimp-sheet-open');
		window.requestAnimationFrame(function () {
			sheet.classList.add('is-open');
		});
	}

	account.addEventListener('click', function (e) {
		var link = e.target.closest('.woocommerce-orders-table a.view');
		if (!link || !phone.matches || e.ctrlKey || e.metaKey || e.shiftKey) {
			return;
		}
		e.preventDefault();
		link.classList.add('is-loading');
		fetch(link.href, { credentials: 'same-origin' })
			.then(function (response) {
				return response.text();
			})
			.then(function (text) {
				var page = new DOMParser().parseFromString(text, 'text/html');
				var panel = page.querySelector('.aimp-account-panel');
				if (!panel) {
					throw new Error('no details');
				}
				// Without the "← All orders" link: the sheet closes instead.
				var back = panel.querySelector('.aimp-account-back');
				if (back && back.parentNode) {
					back.parentNode.parentNode.removeChild(back.parentNode);
				}
				var box = document.createElement('div');
				while (panel.firstChild) {
					box.appendChild(document.importNode(panel.firstChild, true));
					panel.removeChild(panel.firstChild);
				}
				open(box);
			})
			.catch(function () {
				window.location.href = link.href;
			})
			.then(function () {
				link.classList.remove('is-loading');
			});
	});
})();
