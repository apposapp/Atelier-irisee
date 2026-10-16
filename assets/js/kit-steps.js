/**
 * [atelier_irisee_kit_steps]: a sewing needle sews the gold seam from left to right, in a loop, while the bar is
 * on screen. The seam's coordinates follow the bar's real size, so the needle never stretches.
 * With reduced motion (or without this script) the seam is simply shown, the needle resting at the end.
 */
(function () {
	'use strict';

	var DURATION = 7000; // One pass, in ms.
	var PAUSE = 900; // Before the next pass.
	var SCALE = 1.3; // Needle size.
	var uid = 0;

	// A wave through the steps (desktop and tablet), or a gentle line above the grid (phone).
	function wave(w, h, phone) {
		var y = function (f) {
			return (h * f).toFixed(1);
		};
		var x = function (f) {
			return (w * f).toFixed(1);
		};
		if (phone) {
			return 'M0 ' + y(0.55) + ' C ' + x(0.2) + ' ' + y(0.05) + ', ' + x(0.4) + ' ' + y(0.95) + ', ' + x(0.6) + ' ' + y(0.5) + ' S ' + x(0.88) + ' ' + y(0.1) + ', ' + w + ' ' + y(0.5);
		}
		return 'M0 ' + y(0.62) +
			' C ' + x(0.09) + ' ' + y(0.08) + ', ' + x(0.16) + ' ' + y(0.08) + ', ' + x(0.25) + ' ' + y(0.52) +
			' S ' + x(0.41) + ' ' + y(0.96) + ', ' + x(0.5) + ' ' + y(0.52) +
			' S ' + x(0.66) + ' ' + y(0.06) + ', ' + x(0.75) + ' ' + y(0.52) +
			' S ' + x(0.91) + ' ' + y(0.96) + ', ' + w + ' ' + y(0.44);
	}

	function setup(bar) {
		var thread = bar.querySelector('.aimp-kit-steps-thread');
		var sewing = bar.querySelector('.aimp-kit-steps-sewing');
		var guide = thread ? thread.querySelector('.ks-seam-guide') : null;
		var sewn = thread ? thread.querySelector('.ks-seam-sewn') : null;
		var needle = sewing ? sewing.querySelector('.ks-needle') : null;
		if (!guide || !sewn || !needle || !sewn.getTotalLength) {
			return;
		}
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var ns = 'http://www.w3.org/2000/svg';

		// The sewn part of the seam is revealed up to the needle.
		var clipId = 'aimp-ks-clip-' + ++uid;
		var clip = document.createElementNS(ns, 'clipPath');
		clip.setAttribute('id', clipId);
		var rect = document.createElementNS(ns, 'rect');
		rect.setAttribute('x', '0');
		rect.setAttribute('y', '-50');
		clip.appendChild(rect);
		var defs = document.createElementNS(ns, 'defs');
		defs.appendChild(clip);
		thread.insertBefore(defs, thread.firstChild);
		sewn.setAttribute('clip-path', 'url(#' + clipId + ')');

		var len = 0;
		var height = 0;
		function layout() {
			var box = thread.getBoundingClientRect();
			var w = Math.max(1, Math.round(box.width));
			height = Math.max(1, Math.round(box.height));
			var view = '0 0 ' + w + ' ' + height;
			thread.setAttribute('viewBox', view);
			sewing.setAttribute('viewBox', view);
			var d = wave(w, height, w < 520);
			guide.setAttribute('d', d);
			sewn.setAttribute('d', d);
			rect.setAttribute('height', String(height + 100));
			len = sewn.getTotalLength();
		}

		// The needle at a share (0–1) of the seam, pointing along it; the seam is sewn up to its eye.
		function place(share, opacity) {
			var at = Math.max(0, Math.min(len, share * len));
			var a = sewn.getPointAtLength(Math.max(0, at - 1));
			var b = sewn.getPointAtLength(Math.min(len, at + 1));
			var p = sewn.getPointAtLength(at);
			var angle = Math.atan2(b.y - a.y, b.x - a.x);
			needle.setAttribute('transform', 'translate(' + p.x.toFixed(1) + ' ' + p.y.toFixed(1) + ') rotate(' + (angle * 180 / Math.PI).toFixed(1) + ') scale(' + SCALE + ')');
			needle.style.opacity = String(opacity);
			// The eye is 28 units (scaled) behind the needle's centre: the thread runs into it.
			rect.setAttribute('width', String(Math.max(0, p.x - 28 * SCALE * Math.cos(angle))));
		}

		layout();
		if (reduce) {
			place(1, 1);
			rect.setAttribute('width', String(len + 100));
			bar.classList.add('is-sewing');
			return;
		}

		var start = null;
		var running = false;
		var frame = 0;
		function tick(now) {
			if (!running) {
				return;
			}
			if (start === null) {
				start = now;
			}
			var elapsed = (now - start) % (DURATION + PAUSE);
			var share = Math.min(1, elapsed / DURATION);
			var opacity = share < 0.05 ? share / 0.05 : share > 0.92 ? Math.max(0, (1 - share) / 0.08) : 1;
			if (elapsed > DURATION) {
				opacity = 0;
			}
			place(share, opacity);
			frame = window.requestAnimationFrame(tick);
		}
		function play() {
			if (!running) {
				running = true;
				frame = window.requestAnimationFrame(tick);
			}
		}
		function stop() {
			running = false;
			window.cancelAnimationFrame(frame);
		}

		bar.classList.add('is-sewing');
		place(0, 0);
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						play();
					} else {
						stop();
					}
				});
			}).observe(bar);
		} else {
			play();
		}
		var timer = 0;
		window.addEventListener('resize', function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(layout, 150);
		});
	}

	function boot() {
		document.querySelectorAll('[data-aimp-kit-steps]').forEach(function (bar) {
			if (!bar.aimpKitSteps) {
				bar.aimpKitSteps = true;
				setup(bar);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
