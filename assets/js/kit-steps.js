/**
 * [atelier_irisee_kit_steps]: a small gold needle sews a looping seam through the steps, from left to right, in a
 * loop while the bar is on screen. The seam's coordinates follow the bar's real size, so nothing stretches.
 * With reduced motion (or without this script) the seam is simply shown, the needle resting at the end.
 */
(function () {
	'use strict';

	var DURATION = 8000; // One pass, in ms.
	var PAUSE = 900; // Before the next pass.
	var SCALE = 0.8; // Needle size.
	var uid = 0;

	/**
	 * The seam: a wave that dips through the cards and makes a playful loop in each gap between them
	 * (a loop is a cubic curve whose handles cross). On phones a gentle line with two loops above the grid.
	 *
	 * @param {number}   w     Width.
	 * @param {number}   h     Height.
	 * @param {number[]} gaps  Centres of the gaps between the cards (x).
	 * @param {boolean}  phone Phone layout.
	 * @return {string} Path data.
	 */
	function seam(w, h, gaps, phone) {
		var mid = h * 0.5;
		var r = Math.max(11, Math.min(26, w * 0.022)); // Loop size.
		var f = function (n) {
			return n.toFixed(1);
		};
		var points = phone ? [w * 0.33, w * 0.67] : gaps;
		var d = 'M0 ' + f(mid);
		var x = 0;
		points.forEach(function (gx, i) {
			// A wave through the card, up then down (alternating), arriving at the loop.
			var dir = i % 2 ? 1 : -1;
			var span = gx - r - x;
			d += ' C ' + f(x + span * 0.35) + ' ' + f(mid + dir * h * 0.42) + ', ' + f(x + span * 0.7) + ' ' + f(mid - dir * h * 0.42) + ', ' + f(gx - r) + ' ' + f(mid);
			// The loop: the thread curls up and around itself.
			d += ' C ' + f(gx + r * 2.6) + ' ' + f(mid - r * 2.8) + ', ' + f(gx - r * 2.6) + ' ' + f(mid - r * 2.8) + ', ' + f(gx + r) + ' ' + f(mid);
			x = gx + r;
		});
		var last = w - x;
		d += ' C ' + f(x + last * 0.35) + ' ' + f(mid + h * 0.42) + ', ' + f(x + last * 0.7) + ' ' + f(mid - h * 0.3) + ', ' + f(w) + ' ' + f(mid - h * 0.1);
		return d;
	}

	function setup(bar) {
		var thread = bar.querySelector('.aimp-kit-steps-thread');
		var sewing = bar.querySelector('.aimp-kit-steps-sewing');
		var list = bar.querySelector('.aimp-kit-steps-list');
		var guide = thread ? thread.querySelector('.ks-seam-guide') : null;
		var sewn = thread ? thread.querySelector('.ks-seam-sewn') : null;
		var needle = sewing ? sewing.querySelector('.ks-needle') : null;
		if (!guide || !sewn || !needle || !list || !sewn.getTotalLength) {
			return;
		}
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var ns = 'http://www.w3.org/2000/svg';

		// The sewn stitches are shown up to the needle's eye: a mask that grows along the seam's length
		// (by length, not by x, because the loops turn back).
		var maskId = 'aimp-ks-mask-' + ++uid;
		var mask = document.createElementNS(ns, 'mask');
		mask.setAttribute('id', maskId);
		mask.setAttribute('maskUnits', 'userSpaceOnUse');
		var reveal = document.createElementNS(ns, 'path');
		reveal.setAttribute('fill', 'none');
		reveal.setAttribute('stroke', '#fff');
		reveal.setAttribute('stroke-width', '14');
		mask.appendChild(reveal);
		var defs = document.createElementNS(ns, 'defs');
		defs.appendChild(mask);
		thread.insertBefore(defs, thread.firstChild);
		sewn.setAttribute('mask', 'url(#' + maskId + ')');

		var len = 0;
		function layout() {
			var box = thread.getBoundingClientRect();
			var w = Math.max(1, Math.round(box.width));
			var h = Math.max(1, Math.round(box.height));
			var view = '0 0 ' + w + ' ' + h;
			thread.setAttribute('viewBox', view);
			sewing.setAttribute('viewBox', view);
			mask.setAttribute('x', '-50');
			mask.setAttribute('y', '-50');
			mask.setAttribute('width', String(w + 100));
			mask.setAttribute('height', String(h + 100));
			// The gaps between the cards, measured, relative to the seam.
			var cards = Array.prototype.slice.call(list.children);
			var gaps = [];
			for (var i = 0; i < cards.length - 1; i++) {
				var a = cards[i].getBoundingClientRect();
				var b = cards[i + 1].getBoundingClientRect();
				var gx = (a.right + b.left) / 2 - box.left;
				if (gx > 0 && gx < w) {
					gaps.push(gx);
				}
			}
			var d = seam(w, h, gaps, w < 520 || gaps.length < 3);
			guide.setAttribute('d', d);
			sewn.setAttribute('d', d);
			reveal.setAttribute('d', d);
			len = sewn.getTotalLength();
			reveal.setAttribute('stroke-dasharray', len + ' ' + (len + 10));
		}

		// The needle at a share (0–1) of the seam, pointing along it; the seam is sewn up to its eye.
		function place(share, opacity) {
			var at = Math.max(0, Math.min(len, share * len));
			var a = sewn.getPointAtLength(Math.max(0, at - 1));
			var b = sewn.getPointAtLength(Math.min(len, at + 1));
			var p = sewn.getPointAtLength(at);
			var angle = Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI;
			needle.setAttribute('transform', 'translate(' + p.x.toFixed(1) + ' ' + p.y.toFixed(1) + ') rotate(' + angle.toFixed(1) + ') scale(' + SCALE + ')');
			needle.style.opacity = String(opacity);
			// The eye is 28 units (scaled) behind the needle's centre: the thread runs into it.
			reveal.setAttribute('stroke-dashoffset', String(len - Math.max(0, at - 28 * SCALE)));
		}

		layout();
		bar.classList.add('is-sewing');
		if (reduce) {
			place(1, 1);
			reveal.setAttribute('stroke-dashoffset', '0');
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
			var opacity = share < 0.04 ? share / 0.04 : share > 0.93 ? Math.max(0, (1 - share) / 0.07) : 1;
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
