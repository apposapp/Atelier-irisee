/**
 * Atelier Irisee home page blocks: the sliders of the reviews and "New in". The track scrolls by itself (swipe,
 * trackpad, keyboard); the arrows move it one screen at a time and are greyed out at the ends, or hidden when
 * everything fits.
 */
(function () {
	'use strict';

	function init(block) {
		var track = block.querySelector('.aimp-slider-track');
		var arrows = block.querySelectorAll('.aimp-slider-arrow');
		if (!track || !arrows.length) {
			return;
		}
		var update = function () {
			var max = track.scrollWidth - track.clientWidth;
			block.classList.toggle('is-static', max <= 2);
			arrows.forEach(function (arrow) {
				var dir = parseInt(arrow.getAttribute('data-dir'), 10);
				arrow.disabled = dir < 0 ? track.scrollLeft <= 2 : track.scrollLeft >= max - 2;
			});
		};
		arrows.forEach(function (arrow) {
			arrow.addEventListener('click', function () {
				var dir = parseInt(arrow.getAttribute('data-dir'), 10);
				track.scrollBy({ left: dir * track.clientWidth * 0.9, behavior: 'smooth' });
			});
		});
		track.addEventListener('scroll', function () {
			window.requestAnimationFrame(update);
		}, { passive: true });
		window.addEventListener('resize', update);
		update();
	}

	// The blocks are as wide as the screen without its scrollbar (no sideways scrolling on computers).
	function scrollbar() {
		var width = window.innerWidth - document.documentElement.clientWidth;
		document.documentElement.style.setProperty('--aimp-hero-sb', Math.max(0, width) + 'px');
	}

	// The video of a page header: the phone video on phones (when there is one), never with "reduced motion" or the
	// data saver; it fades in over the picture once it plays.
	function video(el) {
		var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var saver = navigator.connection && navigator.connection.saveData;
		if (calm || saver) {
			return;
		}
		var phone = window.matchMedia && window.matchMedia('(max-width: 700px)').matches;
		var src = (phone && el.getAttribute('data-psrc')) || el.getAttribute('data-src');
		if (!src) {
			return;
		}
		el.addEventListener('playing', function () {
			el.classList.add('is-playing');
		});
		el.src = src;
		el.muted = true;
		var play = el.play();
		if (play && play.catch) {
			play.catch(function () {});
		}
	}

	// The page header's standard height: twice the menu bar plus the gold bar (page-header.css --aimp-ph-base). On
	// tablets and phones the menu bar is hidden: then as high as it is on computers (about 1.35 × the gold bar), so the
	// header is as compact there.
	function headerBase() {
		var height = function (selector) {
			var el = document.querySelector(selector);
			return el ? el.getBoundingClientRect().height : 0;
		};
		var gold = height('.aimp-info-bar') || 35;
		var base = (height('.aimp-header-bar') || Math.round(gold * 1.35)) + gold;
		if (base > 20) {
			document.documentElement.style.setProperty('--aimp-ph-base', Math.round(base) + 'px');
		}
	}

	function start() {
		scrollbar();
		window.addEventListener('resize', scrollbar);
		if (document.querySelector('.aimp-page-header')) {
			headerBase();
			window.addEventListener('resize', headerBase);
		}
		// The page can grow after loading (a shop or the customizer filled in later): the scrollbar appears then.
		if (window.ResizeObserver && document.body) {
			new window.ResizeObserver(scrollbar).observe(document.body);
		}
		document.querySelectorAll('[data-aimp-slider]').forEach(init);
		document.querySelectorAll('[data-aimp-ph-video]').forEach(video);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
