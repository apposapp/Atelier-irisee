/**
 * [atelier_irisee_kit_steps]: the gold thread is sewn from step to step once the bar comes into view.
 * Without this script (or without IntersectionObserver) the thread is simply shown.
 */
(function () {
	'use strict';

	function boot() {
		if (!('IntersectionObserver' in window)) {
			return;
		}
		document.querySelectorAll('[data-aimp-kit-steps]').forEach(function (bar) {
			bar.classList.add('is-ready');
			var observer = new IntersectionObserver(
				function (entries) {
					entries.forEach(function (entry) {
						if (entry.isIntersecting) {
							bar.classList.add('is-sewn');
							observer.disconnect();
						}
					});
				},
				{ threshold: 0.35 }
			);
			observer.observe(bar);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
