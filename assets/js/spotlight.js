/**
 * [atelier_irisee_kit_spotlight]: a gold needle sews a running stitch round the kit's picture, again and again. The
 * stitches fade behind it, so the circle never fills up. It passes under the pattern and the haberdashery and over
 * the fabric: the thread lies in a layer behind the elements, and in front of the elements it goes over (clipped to
 * them), and the needle moves between the two layers. Plays only while the block is on screen; with reduced motion
 * the circle is simply stitched, the needle resting on it.
 *
 * Also: picking a size (price, fabric needed) and adding the kit to the cart.
 */
(function () {
	'use strict';

	var NS = 'http://www.w3.org/2000/svg';
	var LOOP = 9000; // One round, in ms (Kit spotlights → speed).
	var TRAIL = 0.8; // The share of the circle that stays stitched behind the needle.
	var FADES = [1, 0.8, 0.6, 0.42, 0.26, 0.12];
	var uid = 0;

	function el(name, attrs, parent) {
		var node = document.createElementNS(NS, name);
		Object.keys(attrs || {}).forEach(function (key) {
			node.setAttribute(key, attrs[key]);
		});
		if (parent) {
			parent.appendChild(node);
		}
		return node;
	}

	function f(n) {
		return n.toFixed(1);
	}

	// A mask that shows the thread from the needle's eye backwards, fading in steps.
	function trailMask(svg, id) {
		var defs = svg.querySelector('defs') || svg.insertBefore(el('defs'), svg.firstChild);
		var mask = el('mask', { id: id, maskUnits: 'userSpaceOnUse' }, defs);
		return {
			mask: mask,
			parts: FADES.map(function (opacity) {
				return el('path', { fill: 'none', stroke: '#fff', 'stroke-width': '16', 'stroke-opacity': String(opacity) }, mask);
			})
		};
	}

	function sew(block) {
		var stage = block.querySelector('.aimp-spotlight-stage');
		var back = block.querySelector('.aimp-spotlight-layer.is-back');
		var front = block.querySelector('.aimp-spotlight-layer.is-front');
		var needle = front ? front.querySelector('.ks-needle') : null;
		if (!stage || !back || !front || !needle || !window.SVGPathElement || !SVGPathElement.prototype.getTotalLength) {
			return;
		}
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var n = ++uid;

		// Behind: the faint guide and the stitches. In front: the stitches again, only over the "over" elements.
		var guide = el('path', { class: 'sp-guide' }, back);
		var backMask = trailMask(back, 'aimp-sp-trail-b' + n);
		var sewnBack = el('path', { class: 'sp-sewn', mask: 'url(#aimp-sp-trail-b' + n + ')' }, back);
		var frontMask = trailMask(front, 'aimp-sp-trail-f' + n);
		// Not drawn: twice round, to measure along (the stitches and the guide go round once).
		var track = el('path', {}, back.querySelector('defs'));
		var clip = el('clipPath', { id: 'aimp-sp-over' + n }, front.querySelector('defs'));
		var over = el('g', { 'clip-path': 'url(#aimp-sp-over' + n + ')' });
		front.insertBefore(over, needle);
		var sewnFront = el('path', { class: 'sp-sewn', mask: 'url(#aimp-sp-trail-f' + n + ')' }, over);

		var len = 0; // One round.
		var scale = 0.8;
		var under = []; // [ from, to ] along the round where the needle goes under an element.
		var duration = LOOP;

		function layout() {
			var box = stage.getBoundingClientRect();
			var w = Math.max(1, box.width);
			var h = Math.max(1, box.height);
			var view = '0 0 ' + f(w) + ' ' + f(h);
			back.setAttribute('viewBox', view);
			front.setAttribute('viewBox', view);
			[backMask.mask, frontMask.mask].forEach(function (mask) {
				mask.setAttribute('x', '-60');
				mask.setAttribute('y', '-60');
				mask.setAttribute('width', f(w + 120));
				mask.setAttribute('height', f(h + 120));
			});
			// The same circle as the elements' places (AIMP_Spotlight::element(): 42% from the centre), twice round, so
			// the stitches behind the needle never have to wrap past the start.
			var cx = w / 2;
			var cy = h / 2;
			var rx = w * 0.42;
			var ry = h * 0.42;
			var half = ' A ' + f(rx) + ' ' + f(ry) + ' 0 0 1 ';
			var round = half + f(cx - rx) + ' ' + f(cy) + half + f(cx + rx) + ' ' + f(cy);
			var once = 'M ' + f(cx + rx) + ' ' + f(cy) + round;
			var d = once + round;
			track.setAttribute('d', d);
			guide.setAttribute('d', once);
			sewnBack.setAttribute('d', once);
			sewnFront.setAttribute('d', once);
			backMask.parts.concat(frontMask.parts).forEach(function (part) {
				part.setAttribute('d', d);
			});
			len = track.getTotalLength() / 2;
			// Whole stitches: the pattern repeats exactly once round.
			var count = Math.max(12, Math.round(len / 15));
			var step = len / count;
			var dash = f(step * 0.58) + ' ' + f(step * 0.42);
			sewnBack.setAttribute('stroke-dasharray', dash);
			sewnFront.setAttribute('stroke-dasharray', dash);
			scale = Math.max(0.5, Math.min(0.95, w / 680));
			duration = LOOP / ((parseFloat(block.getAttribute('data-speed')) || 100) / 100);

			// Where the needle meets each element: under (behind the elements' layer) or over (a clipped copy in front).
			while (clip.firstChild) {
				clip.removeChild(clip.firstChild);
			}
			under = [];
			var samples = [];
			for (var i = 0; i < 180; i++) {
				var p = track.getPointAtLength(len * i / 180);
				samples.push({ at: len * i / 180, x: p.x, y: p.y });
			}
			block.querySelectorAll('.aimp-sp-el').forEach(function (item) {
				var shape = item.querySelector('.aimp-sp-disc, .aimp-sp-badge, .aimp-sp-tag') || item;
				var r = shape.getBoundingClientRect();
				var ex = r.left - box.left + r.width / 2;
				var ey = r.top - box.top + r.height / 2;
				var radius = Math.max(r.width, r.height) / 2;
				var best = samples[0];
				var bestD = Infinity;
				samples.forEach(function (s) {
					var dist = (s.x - ex) * (s.x - ex) + (s.y - ey) * (s.y - ey);
					if (dist < bestD) {
						bestD = dist;
						best = s;
					}
				});
				if (item.classList.contains('is-over')) {
					el('circle', { cx: f(ex), cy: f(ey), r: f(radius * 1.25) }, clip);
				} else {
					under.push([best.at - radius * 0.9, best.at + radius * 0.9]);
				}
			});
		}

		function isUnder(at) {
			return under.some(function (range) {
				return (at >= range[0] && at <= range[1]) || (at + len >= range[0] && at + len <= range[1]) || (at - len >= range[0] && at - len <= range[1]);
			});
		}

		// The needle at a point of the second round (len … 2 len), the stitches behind it.
		function place(at) {
			var a = track.getPointAtLength(at - 1);
			var b = track.getPointAtLength(at + 1);
			var p = track.getPointAtLength(at);
			var angle = Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI;
			needle.setAttribute('transform', 'translate(' + f(p.x) + ' ' + f(p.y) + ') rotate(' + f(angle) + ') scale(' + scale.toFixed(2) + ')');
			var layer = isUnder(at - len) ? back : front;
			if (needle.parentNode !== layer) {
				layer.appendChild(needle);
			}
			// The thread runs into the eye, 28 units behind the needle's centre.
			var eye = at - 28 * scale;
			var piece = len * TRAIL / FADES.length;
			var gap = len * 4;
			for (var i = 0; i < FADES.length; i++) {
				var dash = '0 ' + f(Math.max(0, eye - piece * (i + 1))) + ' ' + f(piece) + ' ' + f(gap);
				backMask.parts[i].setAttribute('stroke-dasharray', dash);
				frontMask.parts[i].setAttribute('stroke-dasharray', dash);
			}
		}

		layout();
		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(layout);
		}
		window.addEventListener('load', layout);
		block.classList.add('is-sewing');

		if (reduce) {
			// The whole circle stitched, the needle resting on it.
			sewnBack.removeAttribute('mask');
			sewnFront.removeAttribute('mask');
			place(len * 1.86);
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
			place(len + ((now - start) % duration) / duration * len);
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
		place(len * 1.1);
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						play();
					} else {
						stop();
					}
				});
			}).observe(block);
		} else {
			play();
		}
		var timer = 0;
		window.addEventListener('resize', function () {
			window.clearTimeout(timer);
			timer = window.setTimeout(layout, 150);
		});
	}

	/* ------------------------------------------------------------------ sizes and the cart */

	function shop(block) {
		var pills = Array.prototype.slice.call(block.querySelectorAll('.aimp-spotlight-size'));
		var button = block.querySelector('[data-add]');
		var price = block.querySelector('.aimp-spotlight-price [data-price]');
		var regular = block.querySelector('.aimp-spotlight-price [data-regular]');
		var need = block.querySelector('.aimp-spotlight-need');
		var message = block.querySelector('.aimp-spotlight-message');
		var note = block.querySelector('.aimp-spotlight-note');
		var chosen = 0;
		if (!button) {
			return;
		}

		function say(lines, error) {
			message.innerHTML = '';
			lines.forEach(function (line) {
				var p = document.createElement('p');
				p.textContent = line;
				message.appendChild(p);
			});
			message.classList.toggle('is-error', !!error);
			message.hidden = !lines.length;
		}

		pills.forEach(function (pill) {
			pill.addEventListener('click', function () {
				if (pill.disabled) {
					return;
				}
				pills.forEach(function (other) {
					other.setAttribute('aria-pressed', other === pill ? 'true' : 'false');
				});
				chosen = parseInt(pill.getAttribute('data-size'), 10) || 0;
				price.textContent = pill.getAttribute('data-price') || '';
				var full = pill.getAttribute('data-regular') || '';
				regular.textContent = full;
				regular.hidden = !full;
				if (need) {
					var text = pill.getAttribute('data-need') || '';
					need.textContent = text ? '(' + text + ')' : '';
					need.hidden = !text;
				}
				button.disabled = false;
				button.textContent = button.getAttribute('data-label');
				say([]);
				note.hidden = true;
			});
		});

		button.addEventListener('click', function () {
			if (!chosen) {
				return;
			}
			button.disabled = true;
			button.textContent = button.getAttribute('data-busy');
			var body = new FormData();
			body.append('id', block.getAttribute('data-id'));
			body.append('variation', String(chosen));
			fetch(block.getAttribute('data-endpoint'), { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (response) {
					return response.json();
				})
				.then(function (json) {
					button.disabled = false;
					button.textContent = button.getAttribute('data-label');
					if (!json || !json.success) {
						say((json && json.data && json.data.errors) || [button.getAttribute('data-error')], true);
						return;
					}
					say([json.data.message]);
					note.hidden = false;
					if (window.jQuery) {
						window.jQuery(document.body).trigger('wc_fragment_refresh');
					}
					document.body.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart', { bubbles: true, detail: { preserveCartData: false } }));
				})
				.catch(function () {
					button.disabled = false;
					button.textContent = button.getAttribute('data-label');
					say([button.getAttribute('data-error')], true);
				});
		});
	}

	function boot() {
		document.querySelectorAll('[data-aimp-spotlight]').forEach(function (block) {
			if (block.aimpSpotlight) {
				return;
			}
			block.aimpSpotlight = true;
			shop(block);
			if (block.getAttribute('data-needle') === '1') {
				sew(block);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
