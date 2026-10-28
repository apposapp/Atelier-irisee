/**
 * [atelier_irisee_bandana]: the steps bar is sewn and the steps slide in (their line drawings draw themselves) when
 * they come into view. Without this script, or with reduced motion, everything is simply shown (bandana.css).
 */
(function () {
	'use strict';

	function start() {
		var pages = document.querySelectorAll('.aimp-bandana');
		if (!pages.length) {
			return;
		}
		var parts = [];
		Array.prototype.forEach.call(pages, function (page) {
			page.classList.add('has-js');
			Array.prototype.push.apply(parts, page.querySelectorAll('[data-bd-anim]'));
		});
		if (!('IntersectionObserver' in window)) {
			parts.forEach(function (el) {
				el.classList.add('is-in');
			});
			return;
		}
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-in');
					observer.unobserve(entry.target);
				}
			});
		}, { rootMargin: '0px 0px -12% 0px', threshold: 0.15 });
		parts.forEach(function (el) {
			observer.observe(el);
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
}());
