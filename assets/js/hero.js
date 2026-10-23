/**
 * [atelier_irisee_hero]: the arrow slides the page to the next section (the kit steps bar when it is on the
 * page), stopping under the fixed header. Also keeps the full-width hero clear of a desktop scrollbar.
 */
(function () {
	'use strict';

	// The section after the hero: the kit steps bar, otherwise the next Divi section or element. The Design studio
	// (Hero arrow) can choose the next section always, or a part of the page by its #name.
	function target(hero) {
		var mode = hero.getAttribute('data-arrow') || '';
		var anchor = (hero.getAttribute('data-anchor') || '').replace(/^#/, '');
		if (mode === 'anchor' && anchor && document.getElementById(anchor)) {
			return document.getElementById(anchor);
		}
		var steps = mode === 'next' ? null : document.querySelector('[data-aimp-kit-steps]');
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
		// No white band above the hero: the Divi section and row around it get no top padding (hero.css does this
		// with :has() where the browser supports it).
		['.et_pb_section', '.et_pb_row'].forEach(function (selector) {
			var wrap = hero.closest(selector);
			if (wrap) {
				wrap.style.paddingTop = '0px';
			}
		});
		// Slides (Design studio → Hero): the pictures take turns.
		var slides = hero.querySelectorAll('.aimp-hero-slide');
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (slides.length > 1 && !reduce) {
			var current = 0;
			window.setInterval(function () {
				slides[current].classList.remove('is-current');
				current = (current + 1) % slides.length;
				slides[current].classList.add('is-current');
			}, (parseFloat(hero.getAttribute('data-interval')) || 6) * 1000);
		}
		var arrow = hero.querySelector('.aimp-hero-arrow');
		if (!arrow) {
			return;
		}
		arrow.addEventListener('click', function () {
			var next = target(hero);
			if (!next) {
				return;
			}
			// The kit steps bar: its title lands at the top, just under the header as it looks once scrolled (slimmer;
			// on phones the admin bar has scrolled away by then).
			var title = next.querySelector('.aimp-kit-steps-title');
			var root = getComputedStyle(document.documentElement);
			var slim = parseFloat(root.getPropertyValue('--aimp-header-slim'));
			var bar = document.getElementById('wpadminbar');
			// --aimp-header-offset already counts the admin bar; the slim height does not.
			var header = slim ? slim + (bar && 'fixed' === getComputedStyle(bar).position ? bar.offsetHeight : 0) : parseFloat(root.getPropertyValue('--aimp-header-offset')) || 0;
			var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			var top = (title || next).getBoundingClientRect().top + window.pageYOffset - header - (title ? 12 : 0);
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
