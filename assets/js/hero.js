/**
 * [atelier_irisee_hero]: the arrow slides the page to the next section (the kit steps bar when it is on the
 * page), stopping under the fixed header. Also keeps the full-width hero clear of a desktop scrollbar.
 */
(function () {
	'use strict';

	// The section after the hero: the kit steps bar, otherwise the next Divi section or element.
	function target(hero) {
		var steps = document.querySelector('[data-aimp-kit-steps]');
		if (steps) {
			return steps;
		}
		var section = hero.closest('.et_pb_section');
		var node = section || hero;
		while (node && !node.nextElementSibling) {
			node = node.parentElement;
		}
		return node ? node.nextElementSibling : null;
	}

	function scrollbar() {
		var width = window.innerWidth - document.documentElement.clientWidth;
		document.documentElement.style.setProperty('--aimp-hero-sb', Math.max(0, width) + 'px');
	}

	function setup(hero) {
		var arrow = hero.querySelector('.aimp-hero-arrow');
		if (!arrow) {
			return;
		}
		arrow.addEventListener('click', function () {
			var next = target(hero);
			if (!next) {
				return;
			}
			var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			var offset = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--aimp-header-offset')) || 0;
			var top = next.getBoundingClientRect().top + window.pageYOffset - offset;
			window.scrollTo({ top: Math.max(0, top), behavior: reduce ? 'auto' : 'smooth' });
		});
	}

	function boot() {
		var heroes = document.querySelectorAll('[data-aimp-hero]');
		if (!heroes.length) {
			return;
		}
		scrollbar();
		window.addEventListener('resize', scrollbar);
		heroes.forEach(function (hero) {
			if (!hero.aimpHero) {
				hero.aimpHero = true;
				setup(hero);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
