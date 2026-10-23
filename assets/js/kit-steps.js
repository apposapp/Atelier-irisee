/**
 * [atelier_irisee_kit_steps]: a small gold needle sews a looping seam through the steps to the button, which then
 * sparkles; again and again while the bar is on screen. The seam's coordinates follow the bar's real size, so nothing stretches.
 * With reduced motion (or without this script) the seam is simply shown, the needle resting at the end.
 */
(function () {
	'use strict';

	var DURATION = 6000; // One pass, in ms.
	var PAUSE = 2300; // The spark, then the next pass.
	var SCALE = 0.8; // Needle size.
	var uid = 0;

	// Every loop its own size, height and direction, so the seam looks hand-made (the same on every visit).
	var LOOPS = [
		{ size: 1, dy: -0.1, dir: -1 },
		{ size: 0.72, dy: 0.12, dir: 1 },
		{ size: 1.28, dy: -0.04, dir: -1 },
		{ size: 0.9, dy: 0.06, dir: 1 }
	];

	function f(n) {
		return n.toFixed(1);
	}

	// Design studio (Kit steps bar): a smooth thread without loops (each loop becomes a straight piece).
	var straight = false;

	// A loop at (x, y): from (x - r, y) the thread curls above (dir -1) or below (dir 1) and comes out at (x + r, y).
	function loop(x, y, r, dir) {
		if (straight) {
			return ' L ' + f(x + r) + ' ' + f(y);
		}
		return ' C ' + f(x + r * 2.6) + ' ' + f(y + dir * r * 2.8) + ', ' + f(x - r * 2.6) + ' ' + f(y + dir * r * 2.8) + ', ' + f(x + r) + ' ' + f(y);
	}

	// The same loop for a thread going left: from (x + r, y) out at (x - r, y).
	function loopBack(x, y, r, dir) {
		if (straight) {
			return ' L ' + f(x - r) + ' ' + f(y);
		}
		return ' C ' + f(x - r * 2.6) + ' ' + f(y + dir * r * 2.8) + ', ' + f(x + r * 2.6) + ' ' + f(y + dir * r * 2.8) + ', ' + f(x - r) + ' ' + f(y);
	}

	// A sideways loop on a line going down, at (x, y): from (x, y - r) out at (x, y + r).
	function loopDown(x, y, r) {
		if (straight) {
			return ' L ' + f(x) + ' ' + f(y + r);
		}
		return ' C ' + f(x + r * 2.8) + ' ' + f(y + r * 2.6) + ', ' + f(x + r * 2.8) + ' ' + f(y - r * 2.6) + ', ' + f(x) + ' ' + f(y + r);
	}

	/**
	 * The seam, in the coordinates of the row: through the cards at the height of their drawings with a loop in
	 * each gap, to the button. Phones: around the cards (never over their texts), with loops between them, to the
	 * button under the grid.
	 *
	 * @param {DOMRect}   row    The row.
	 * @param {DOMRect[]} cards  The cards.
	 * @param {DOMRect}   button The button.
	 * @param {boolean}   phone  Phone layout (two columns).
	 * @return {string} Path data.
	 */
	function seam(row, cards, button, phone) {
		var rel = function (r) {
			return { left: r.left - row.left, right: r.right - row.left, top: r.top - row.top, bottom: r.bottom - row.top, width: r.width, height: r.height };
		};
		var c = cards.map(rel);
		var b = rel(button);
		var r0 = Math.max(10, Math.min(24, row.width * 0.02));
		var d;
		if (phone && c.length >= 4) {
			// Phones: only in the lanes around the cards, never over their texts. Down the left side, right
			// through the gap between the rows (a loop curling up between the top cards), down the right side,
			// left under the bottom cards (a loop curling down below them) and up to the button.
			var cx = (c[0].right + c[1].left) / 2;
			var gapY = (Math.max(c[0].bottom, c[1].bottom) + Math.min(c[2].top, c[3].top)) / 2;
			// Just outside the cards (they may reach past the row a little; the SVG shows what lies outside it).
			var x0 = Math.min(c[0].left, c[2].left) - 7;
			var xr = Math.max(c[1].right, c[3].right) + 7;
			var bot = Math.max(c[2].bottom, c[3].bottom) + 12;
			// Loops big enough to read as loops at speed (they only touch the empty corners of the cards).
			var ra = 11 * LOOPS[0].size;
			var rb = 11 * LOOPS[1].size;
			var bx = b.left + b.width / 2;
			var top = c[0].top + 10;
			d = 'M' + f(x0) + ' ' + f(top);
			d += ' C ' + f(x0 + 4) + ' ' + f(top + (gapY - top) * 0.35) + ', ' + f(x0 - 3) + ' ' + f(top + (gapY - top) * 0.7) + ', ' + f(x0) + ' ' + f(gapY - 16);
			d += ' C ' + f(x0 + 1) + ' ' + f(gapY - 4) + ', ' + f(x0 + 12) + ' ' + f(gapY) + ', ' + f(x0 + 24) + ' ' + f(gapY);
			d += ' C ' + f(x0 + 24 + (cx - x0) * 0.4) + ' ' + f(gapY - 4) + ', ' + f(cx - ra - 14) + ' ' + f(gapY + 3) + ', ' + f(cx - ra) + ' ' + f(gapY);
			d += loop(cx, gapY, ra, -1);
			d += ' C ' + f(cx + ra + 30) + ' ' + f(gapY + 4) + ', ' + f(xr - 6) + ' ' + f(gapY) + ', ' + f(xr) + ' ' + f(gapY + 16);
			d += ' C ' + f(xr - 4) + ' ' + f(gapY + (bot - gapY) * 0.4) + ', ' + f(xr + 3) + ' ' + f(bot - 30) + ', ' + f(xr) + ' ' + f(bot - 14);
			d += ' C ' + f(xr) + ' ' + f(bot - 4) + ', ' + f(xr - 12) + ' ' + f(bot) + ', ' + f(xr - 24) + ' ' + f(bot);
			d += ' C ' + f(cx + rb + 30) + ' ' + f(bot + 3) + ', ' + f(cx + rb + 12) + ' ' + f(bot - 2) + ', ' + f(cx + rb) + ' ' + f(bot);
			d += loopBack(cx, bot, rb, 1);
			d += ' C ' + f(cx - rb - 22) + ' ' + f(bot + 6) + ', ' + f(bx - 18) + ' ' + f(b.top - 4) + ', ' + f(bx) + ' ' + f(b.top - 3);
			return d;
		}
		// Desktop and tablet: through the drawings, a loop in each gap, then to the button's left edge.
		var top = Math.min.apply(null, c.map(function (x) { return x.top; }));
		var cardH = Math.max.apply(null, c.map(function (x) { return x.height; }));
		var y0 = top + cardH * 0.32;
		var amp = cardH * 0.16;
		d = 'M0 ' + f(y0);
		var x = 0;
		var y = y0;
		for (var i = 0; i < c.length - 1; i++) {
			var cfg = LOOPS[i % LOOPS.length];
			var gx = (c[i].right + c[i + 1].left) / 2;
			var gy = y0 + cardH * cfg.dy;
			var r = r0 * cfg.size;
			var span = gx - r - x;
			var dir = i % 2 ? 1 : -1;
			d += ' C ' + f(x + span * 0.35) + ' ' + f(y + dir * amp) + ', ' + f(x + span * 0.7) + ' ' + f(gy - dir * amp) + ', ' + f(gx - r) + ' ' + f(gy);
			d += loop(gx, gy, r, cfg.dir);
			x = gx + r;
			y = gy;
		}
		var ex = b.left - 2;
		var ey = b.top + b.height / 2;
		d += ' C ' + f(x + (ex - x) * 0.4) + ' ' + f(y + amp) + ', ' + f(x + (ex - x) * 0.75) + ' ' + f(ey - amp) + ', ' + f(ex) + ' ' + f(ey);
		return d;
	}

	function setup(bar) {
		var thread = bar.querySelector('.aimp-kit-steps-thread');
		var sewing = bar.querySelector('.aimp-kit-steps-sewing');
		var list = bar.querySelector('.aimp-kit-steps-list');
		var row = bar.querySelector('.aimp-kit-steps-row');
		var button = bar.querySelector('.aimp-kit-steps-button');
		var guide = thread ? thread.querySelector('.ks-seam-guide') : null;
		var sewn = thread ? thread.querySelector('.ks-seam-sewn') : null;
		var needle = sewing ? sewing.querySelector('.ks-needle') : null;
		if (!guide || !sewn || !needle || !list || !row || !button || !sewn.getTotalLength) {
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
		// Phones: a slower pass, so the needle doesn't seem to jump through the loops.
		var duration = DURATION;
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
			var cards = Array.prototype.slice.call(list.children).map(function (card) {
				return card.getBoundingClientRect();
			});
			// Two columns: the phone layout.
			var phone = cards.length >= 4 && Math.abs(cards[0].top - cards[1].top) < 30 && cards[2].top > cards[0].bottom - 5;
			straight = bar.getAttribute('data-loops') === '0';
			var d = seam(box, cards, button.getBoundingClientRect(), phone);
			// Design studio: the needle's speed in percent of the normal speed.
			var speed = (parseFloat(bar.getAttribute('data-speed')) || 100) / 100;
			duration = (phone ? 8000 : DURATION) / speed;
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
		// Again once the fonts and pictures are in: the cards may have grown.
		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(layout);
		}
		window.addEventListener('load', layout);
		bar.classList.add('is-sewing');
		if (reduce) {
			place(1, 1);
			reveal.setAttribute('stroke-dashoffset', '0');
			return;
		}

		var sparked = false;
		function spark() {
			if (bar.getAttribute('data-sparkle') === '0') {
				return;
			}
			button.classList.remove('is-sparked');
			void button.offsetWidth; // Restart the animation.
			button.classList.add('is-sparked');
			window.setTimeout(function () {
				button.classList.remove('is-sparked');
			}, 1950);
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
			var elapsed = (now - start) % (duration + PAUSE);
			var share = Math.min(1, elapsed / duration);
			// The needle stays visible up to the button, then fades during the pause.
			var opacity = share < 0.04 ? share / 0.04 : 1;
			if (elapsed > duration) {
				opacity = Math.max(0, 1 - (elapsed - duration) / (PAUSE * 0.6));
			}
			if (share >= 1 && !sparked) {
				sparked = true;
				spark();
			} else if (share < 1) {
				sparked = false;
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

	// For bars added later (the customizer's "How it works" pop-up).
	window.aimpKitSteps = boot;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
