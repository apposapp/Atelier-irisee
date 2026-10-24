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

	function start() {
		document.querySelectorAll('[data-aimp-slider]').forEach(init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
