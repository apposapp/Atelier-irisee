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

	/* ------------------------------------------------------------------ the parts the kit is missing */

	// What the pattern needs in the chosen size that is not in the kit (Atelier Irisee → Kit spotlights: 1 in the block,
	// 2 a popup after adding, 3 both). Chosen items are added at their normal price, not as part of the kit.
	function extras(block) {
		var mode = parseInt(block.getAttribute('data-extras'), 10) || 0;
		if (!mode) {
			return null;
		}
		var t = {};
		try {
			t = JSON.parse(block.getAttribute('data-texts') || '{}');
		} catch (err) {}
		var m = t.money || { decimals: 2, decimal: ',', thousand: '.', format: '%1$s%2$s', symbol: '€' };

		function money(n) {
			var fixed = Math.abs(n).toFixed(m.decimals).split('.');
			var whole = fixed[0].replace(/\B(?=(\d{3})+(?!\d))/g, m.thousand);
			var num = whole + (fixed[1] ? m.decimal + fixed[1] : '');
			return m.format.replace('%1$s', m.symbol).replace('%2$s', num);
		}

		function post(url, data) {
			var body = new FormData();
			Object.keys(data).forEach(function (key) {
				body.append(key, data[key]);
			});
			return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) {
				return r.json();
			});
		}

		function el(tag, cls, text) {
			var node = document.createElement(tag);
			if (cls) {
				node.className = cls;
			}
			if (text !== undefined) {
				node.textContent = text;
			}
			return node;
		}

		// One row per missing part, with "Choose" opening the items that fit (a page at a time).
		function rows(list, missing, size, picks, changed) {
			list.innerHTML = '';
			missing.forEach(function (part) {
				var row = el('li', 'aimp-sp-extra');
				var head = el('div', 'aimp-sp-extra-head');
				var info = el('div', 'aimp-sp-extra-info');
				info.appendChild(el('span', 'aimp-sp-extra-label', part.label));
				info.appendChild(el('span', 'aimp-sp-extra-need', part.need));
				var pick = el('div', 'aimp-sp-extra-pick');
				var choose = el('button', 'aimp-sp-extra-choose', t.choose);
				choose.type = 'button';
				choose.setAttribute('aria-expanded', 'false');
				head.appendChild(info);
				head.appendChild(choose);
				row.appendChild(head);
				row.appendChild(pick);
				var grid = el('div', 'aimp-sp-extra-grid');
				grid.hidden = true;
				row.appendChild(grid);
				var page = 0;
				var pages = 1;

				function showPick() {
					pick.innerHTML = '';
					var item = picks[part.type];
					choose.textContent = item ? t.change : t.choose;
					if (!item) {
						return;
					}
					pick.appendChild(el('span', 'aimp-sp-extra-chosen', item.name));
					pick.appendChild(el('span', 'aimp-sp-extra-price', item.total_html));
					var remove = el('button', 'aimp-sp-extra-remove', t.remove);
					remove.type = 'button';
					remove.addEventListener('click', function () {
						delete picks[part.type];
						showPick();
						mark();
						changed();
					});
					pick.appendChild(remove);
				}

				function mark() {
					grid.querySelectorAll('.aimp-sp-extra-card').forEach(function (card) {
						card.setAttribute('aria-pressed', picks[part.type] && String(picks[part.type].id) === card.getAttribute('data-id') ? 'true' : 'false');
					});
				}

				function load() {
					var more = grid.querySelector('.aimp-sp-extra-more');
					if (more) {
						more.remove();
					}
					var wait = el('p', 'aimp-sp-extra-wait', t.loading);
					grid.appendChild(wait);
					post(block.getAttribute('data-options'), { id: block.getAttribute('data-id'), variation: String(size), type: part.type, page: String(page + 1) }).then(function (json) {
						wait.remove();
						var data = json && json.success ? json.data : { items: [], page: 1, pages: 1 };
						page = data.page;
						pages = data.pages;
						if (!data.items.length && !grid.querySelector('.aimp-sp-extra-card')) {
							grid.appendChild(el('p', 'aimp-sp-extra-wait', t.none));
							return;
						}
						data.items.forEach(function (item) {
							var card = el('button', 'aimp-sp-extra-card');
							card.type = 'button';
							card.setAttribute('data-id', String(item.id));
							card.disabled = !item.available;
							if (item.image) {
								var img = el('img');
								img.src = item.image;
								img.alt = '';
								img.loading = 'lazy';
								card.appendChild(img);
							}
							card.appendChild(el('span', 'aimp-sp-extra-name', item.name));
							card.appendChild(el('span', 'aimp-sp-extra-text', item.available ? item.text : t.soldOut));
							card.appendChild(el('strong', 'aimp-sp-extra-total', item.total_html));
							card.addEventListener('click', function () {
								picks[part.type] = item;
								mark();
								showPick();
								grid.hidden = true;
								choose.setAttribute('aria-expanded', 'false');
								changed();
								choose.focus();
							});
							grid.appendChild(card);
						});
						mark();
						if (page < pages) {
							var button = el('button', 'aimp-sp-extra-more', t.more);
							button.type = 'button';
							button.addEventListener('click', load);
							grid.appendChild(button);
						}
					}).catch(function () {
						wait.textContent = t.none;
					});
				}

				choose.addEventListener('click', function () {
					grid.hidden = !grid.hidden;
					choose.setAttribute('aria-expanded', grid.hidden ? 'false' : 'true');
					if (!grid.hidden && !page) {
						load();
					}
				});
				showPick();
				list.appendChild(row);
			});
		}

		function count(picks) {
			return Object.keys(picks).length;
		}

		function sum(picks) {
			return Object.keys(picks).reduce(function (total, type) {
				return total + (parseFloat(picks[type].total) || 0);
			}, 0);
		}

		function add(size, picks) {
			var data = { id: block.getAttribute('data-id'), variation: String(size) };
			Object.keys(picks).forEach(function (type) {
				data['extras[' + type + ']'] = String(picks[type].id);
			});
			return post(block.getAttribute('data-extras-add'), data);
		}

		// The popup after adding: the same rows, "Add to cart" and "No thanks".
		function popup(missing, size, done) {
			var picks = {};
			var back = document.activeElement;
			var overlay = el('div', 'aimp-sp-dialog');
			var panel = el('div', 'aimp-sp-dialog-panel');
			panel.setAttribute('role', 'dialog');
			panel.setAttribute('aria-modal', 'true');
			var title = el('p', 'aimp-sp-dialog-title', t.popTitle);
			title.id = 'aimp-sp-dialog-' + block.getAttribute('data-id');
			panel.setAttribute('aria-labelledby', title.id);
			var x = el('button', 'aimp-sp-dialog-x', '×');
			x.type = 'button';
			x.setAttribute('aria-label', t.close);
			var list = el('ul', 'aimp-spotlight-extras-rows');
			var foot = el('div', 'aimp-sp-dialog-foot');
			var sumLine = el('p', 'aimp-sp-dialog-sum');
			var addButton = el('button', 'aimp-spotlight-button', t.popAdd);
			addButton.type = 'button';
			addButton.disabled = true;
			var skip = el('button', 'aimp-sp-dialog-skip', t.popSkip);
			skip.type = 'button';
			var said = el('div', 'aimp-spotlight-message');
			said.setAttribute('role', 'status');
			said.hidden = true;
			foot.appendChild(sumLine);
			foot.appendChild(addButton);
			foot.appendChild(skip);
			panel.appendChild(x);
			panel.appendChild(title);
			panel.appendChild(el('p', 'aimp-spotlight-extras-note', t.popNote));
			panel.appendChild(list);
			panel.appendChild(said);
			panel.appendChild(foot);
			overlay.appendChild(panel);
			document.body.appendChild(overlay);

			function close() {
				overlay.remove();
				document.removeEventListener('keydown', keys, true);
				if (back && back.focus) {
					back.focus();
				}
			}
			function keys(e) {
				if (e.key === 'Escape') {
					e.preventDefault();
					close();
				} else if (e.key === 'Tab') {
					var all = Array.prototype.filter.call(panel.querySelectorAll('button, a[href]'), function (node) {
						return !node.disabled && node.offsetParent !== null;
					});
					if (!all.length) {
						return;
					}
					var first = all[0];
					var last = all[all.length - 1];
					if (e.shiftKey && document.activeElement === first) {
						e.preventDefault();
						last.focus();
					} else if (!e.shiftKey && document.activeElement === last) {
						e.preventDefault();
						first.focus();
					}
				}
			}
			document.addEventListener('keydown', keys, true);
			overlay.addEventListener('click', function (e) {
				if (e.target === overlay) {
					close();
				}
			});
			x.addEventListener('click', close);
			skip.addEventListener('click', close);
			rows(list, missing, size, picks, function () {
				var n = count(picks);
				addButton.disabled = !n;
				sumLine.textContent = n ? t.extraSum.replace('%s', money(sum(picks))) : '';
			});
			addButton.addEventListener('click', function () {
				addButton.disabled = true;
				addButton.textContent = t.loading;
				add(size, picks).then(function (json) {
					var data = json && json.data ? json.data : { errors: [] };
					done(data);
					if (data.errors && data.errors.length) {
						said.textContent = data.errors.join(' ');
						said.classList.add('is-error');
						said.hidden = false;
						addButton.textContent = t.popAdd;
						addButton.disabled = false;
						return;
					}
					close();
				}).catch(function () {
					addButton.textContent = t.popAdd;
					addButton.disabled = false;
				});
			});
			x.focus();
		}

		return { mode: mode, t: t, money: money, rows: rows, count: count, sum: sum, add: add, popup: popup };
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
		var chart = block.querySelector('[data-chart]');
		var chosen = 0;
		if (!button) {
			return;
		}
		// The parts the kit is missing (none: the setting is off).
		var extra = extras(block);
		var listBox = block.querySelector('[data-extras-list]');
		var missing = [];
		var picks = {};
		var extraLine = null;
		if (extra && listBox) {
			extraLine = document.createElement('p');
			extraLine.className = 'aimp-spotlight-extra-sum';
			extraLine.hidden = true;
			block.querySelector('.aimp-spotlight-price').insertAdjacentElement('afterend', extraLine);
		}
		// The button and the extra line follow the chosen extras.
		function label() {
			var n = extra ? extra.count(picks) : 0;
			if (extraLine) {
				extraLine.textContent = n ? extra.t.extraSum.replace('%s', extra.money(extra.sum(picks))) : '';
				extraLine.hidden = !n;
			}
			return n ? (n === 1 ? extra.t.withOne : extra.t.withExtra.replace('%d', n)) : button.getAttribute('data-label');
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

		// The mini cart and the cart count follow.
		function refresh() {
			if (window.jQuery) {
				window.jQuery(document.body).trigger('wc_fragment_refresh');
			}
			document.body.dispatchEvent(new CustomEvent('wc-blocks_added_to_cart', { bubbles: true, detail: { preserveCartData: false } }));
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
				// The size chart: only the chosen size's row.
				if (chart) {
					chart.querySelectorAll('[data-size-row]').forEach(function (row) {
						row.hidden = parseInt(row.getAttribute('data-size-row'), 10) !== chosen;
					});
					chart.hidden = false;
				}
				// What this size needs that is not in the kit: new choices for each size.
				if (extra) {
					try {
						missing = JSON.parse(pill.getAttribute('data-missing') || '[]');
					} catch (err) {
						missing = [];
					}
					picks = {};
					if (listBox) {
						extra.rows(listBox.querySelector('[data-rows]'), missing, chosen, picks, function () {
							button.textContent = label();
						});
						listBox.hidden = !missing.length;
					}
				}
				button.disabled = false;
				button.textContent = label();
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
					if (!json || !json.success) {
						button.disabled = false;
						button.textContent = label();
						say((json && json.data && json.data.errors) || [button.getAttribute('data-error')], true);
						return null;
					}
					var kitMessage = json.data.message;
					// Then the chosen extras (normal price); the kit stays in the cart if one of them cannot be added.
					var next = extra && extra.count(picks) ? extra.add(chosen, picks) : Promise.resolve(null);
					return next.then(function (more) {
						var data = more && more.data ? more.data : { message: '', errors: [] };
						var lines = [kitMessage];
						if (data.message) {
							lines.push(data.message);
						}
						say(lines.concat(data.errors || []), !!(data.errors && data.errors.length));
						var hadPicks = extra && extra.count(picks) > 0;
						button.disabled = false;
						button.textContent = label();
						note.hidden = false;
						refresh();
						// The popup after adding (2), or (3) when nothing was chosen in the block.
						if (extra && missing.length && (extra.mode === 2 || (extra.mode === 3 && !hadPicks))) {
							extra.popup(missing, chosen, function (added) {
								if (added && added.message) {
									say([kitMessage, added.message]);
									refresh();
								}
							});
						}
					});
				})
				.catch(function () {
					button.disabled = false;
					button.textContent = label();
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
