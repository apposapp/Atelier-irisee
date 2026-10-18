/**
 * Settings → AI pictures (class-aimp-ai-looks.php): generate one combination, generate the missing pictures one by
 * one (Leonardo works in the background: its pictures are asked for every few seconds), and approve or reject
 * the pictures.
 */
(function () {
	'use strict';

	var box = document.querySelector('[data-aimp-ai]');
	var cfg = window.aimpAi;
	if (!box || !cfg || !window.fetch) {
		return;
	}
	var t = cfg.i18n;
	var design = box.querySelector('.aimp-ai-design');
	var fabric = box.querySelector('.aimp-ai-fabric');
	var one = box.querySelector('.aimp-ai-one');
	var count = box.querySelector('.aimp-ai-count');
	var start = box.querySelector('.aimp-ai-start');
	var stop = box.querySelector('.aimp-ai-stop');
	var message = box.querySelector('.aimp-ai-message');
	var list = box.querySelector('.aimp-ai-list');
	var busy = false;
	var stopped = false;
	var missing = [];
	var armed = false;
	var steps = 1;

	function fmt(text, values) {
		var i = 0;
		return text.replace(/%(\d)\$d|%d/g, function (all, n) {
			return String(values[n ? parseInt(n, 10) - 1 : i++]);
		});
	}

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', 'aimp_ai_' + action);
		body.append('nonce', cfg.nonce);
		Object.keys(data || {}).forEach(function (key) {
			body.append(key, data[key]);
		});
		return fetch(cfg.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json();
		}).then(function (json) {
			if (!json || !json.success) {
				throw new Error(json && json.data && json.data.message ? json.data.message : 'error');
			}
			return json.data;
		});
	}

	function say(text) {
		message.textContent = text;
		message.hidden = !text;
	}

	function wait(ms) {
		return new Promise(function (resolve) {
			window.setTimeout(resolve, ms);
		});
	}

	/** One picture: generate, and for Leonardo ask every 4 seconds until it is ready. */
	function generate(key) {
		return post('generate', { key: key }).then(function (data) {
			if (data.state !== 'running') {
				return data;
			}
			var poll = function () {
				return wait(4000).then(function () {
					return post('poll', { key: key });
				}).then(function (result) {
					return result.state === 'running' ? poll() : result;
				});
			};
			return poll();
		});
	}

	function setBusy(value) {
		busy = value;
		[one, count, design, fabric].forEach(function (el) {
			el.disabled = value || (el === one && !fabric.value) || (el === fabric && !design.value);
		});
		start.disabled = value;
	}

	/* --- one combination --- */

	design.addEventListener('change', function () {
		fabric.innerHTML = '';
		var first = document.createElement('option');
		first.value = '';
		first.textContent = t.fabric;
		fabric.appendChild(first);
		fabric.disabled = true;
		one.disabled = true;
		if (!design.value) {
			return;
		}
		post('fabrics', { key: design.value }).then(function (items) {
			items.forEach(function (item) {
				var option = document.createElement('option');
				option.value = item.key;
				option.textContent = item.name + (item.status ? ' (' + (t[item.status === 'failed' ? 'error' : item.status] || item.status) + ')' : '');
				fabric.appendChild(option);
			});
			fabric.disabled = busy;
		}).catch(function (e) {
			say(t.failed + ' ' + e.message);
		});
	});

	fabric.addEventListener('change', function () {
		one.disabled = busy || !fabric.value;
	});

	one.addEventListener('click', function () {
		if (busy || !fabric.value) {
			return;
		}
		setBusy(true);
		say(t.busy);
		generate(fabric.value).then(function () {
			say(t.done);
		}).catch(function (e) {
			say(t.failed + ' ' + e.message);
		}).then(function () {
			setBusy(false);
			load();
		});
	});

	/* --- missing pictures --- */

	count.addEventListener('click', function () {
		if (busy) {
			return;
		}
		armed = false;
		post('missing').then(function (data) {
			missing = data.keys.slice(0, data.left);
			steps = data.steps || 1;
			if (!data.keys.length) {
				start.hidden = true;
				say(t.none);
				return;
			}
			say('');
			start.textContent = fmt(t.start, [missing.length]);
			start.hidden = !missing.length;
		}).catch(function (e) {
			say(t.failed + ' ' + e.message);
		});
	});

	start.addEventListener('click', function () {
		if (busy || !missing.length) {
			return;
		}
		// First click: say what it costs; the second click starts.
		if (!armed) {
			armed = true;
			say(fmt(t.confirm, [missing.length, missing.length * steps]));
			return;
		}
		armed = false;
		stopped = false;
		start.hidden = true;
		stop.hidden = false;
		setBusy(true);
		var keys = missing.slice();
		var errors = [];
		var i = 0;
		var next = function () {
			if (stopped || i >= keys.length) {
				stop.hidden = true;
				setBusy(false);
				say((stopped ? t.stopped : t.finished) + (errors.length ? ' ' + t.failed + ' ' + errors[errors.length - 1] : ''));
				load();
				return;
			}
			say(fmt(t.progress, [i + 1, keys.length]));
			generate(keys[i]).catch(function (e) {
				errors.push(e.message);
			}).then(function () {
				i++;
				if (i % 5 === 0) {
					load();
				}
				next();
			});
		};
		next();
	});

	stop.addEventListener('click', function () {
		stopped = true;
	});

	/* --- the pictures --- */

	function button(label, key, action, primary) {
		var el = document.createElement('button');
		el.type = 'button';
		el.className = 'button' + (primary ? ' button-primary' : '');
		el.textContent = label;
		el.addEventListener('click', function () {
			if (busy) {
				return;
			}
			el.disabled = true;
			var done = action === 'again' ? generate(key) : post('review', { key: key, 'do': action });
			if (action === 'again') {
				setBusy(true);
				say(t.busy);
			}
			done.then(function () {
				if (action === 'again') {
					say(t.done);
				}
			}).catch(function (e) {
				say(t.failed + ' ' + e.message);
			}).then(function () {
				if (action === 'again') {
					setBusy(false);
				}
				load();
			});
		});
		return el;
	}

	function load() {
		post('list').then(function (data) {
			list.innerHTML = '';
			if (!data.items.length) {
				var p = document.createElement('p');
				p.textContent = t.empty;
				list.appendChild(p);
				return;
			}
			var grid = document.createElement('ul');
			grid.className = 'aimp-ai-grid';
			data.items.forEach(function (item) {
				var li = document.createElement('li');
				li.className = 'aimp-ai-item is-' + item.status;
				if (item.thumb) {
					var link = document.createElement('a');
					link.href = item.large;
					link.target = '_blank';
					link.rel = 'noopener';
					var img = document.createElement('img');
					img.src = item.thumb;
					img.alt = '';
					img.loading = 'lazy';
					link.appendChild(img);
					li.appendChild(link);
					// The fabric in the corner, to compare.
					if (item.swatch) {
						var swatch = document.createElement('img');
						swatch.className = 'aimp-ai-swatch';
						swatch.src = item.swatch;
						swatch.alt = '';
						link.appendChild(swatch);
					}
				} else {
					var blank = document.createElement('span');
					blank.className = 'aimp-ai-blank';
					li.appendChild(blank);
				}
				var name = document.createElement('strong');
				name.textContent = item.design;
				li.appendChild(name);
				var cloth = document.createElement('span');
				cloth.textContent = item.fabric;
				li.appendChild(cloth);
				var state = document.createElement('em');
				state.textContent = t[item.status === 'failed' ? 'error' : item.status] || item.status;
				li.appendChild(state);
				if (item.error) {
					var error = document.createElement('small');
					error.textContent = item.error;
					li.appendChild(error);
				}
				var actions = document.createElement('p');
				if (item.status === 'pending') {
					actions.appendChild(button(t.approve, item.key, 'approve', true));
					actions.appendChild(button(t.reject, item.key, 'reject'));
				}
				if (item.status !== 'running') {
					actions.appendChild(button(t.again, item.key, 'again'));
				}
				if (item.status !== 'pending' && item.status !== 'running') {
					actions.appendChild(button(t.remove, item.key, 'remove'));
				}
				li.appendChild(actions);
				grid.appendChild(li);
			});
			list.appendChild(grid);
			// Pictures still being made (e.g. the page was closed meanwhile): ask again in a few seconds.
			var running = data.items.filter(function (item) {
				return item.status === 'running';
			});
			if (running.length && !busy) {
				wait(5000).then(function () {
					return Promise.all(running.map(function (item) {
						return post('poll', { key: item.key }).catch(function () {});
					}));
				}).then(load);
			}
		}).catch(function (e) {
			list.textContent = t.failed + ' ' + e.message;
		});
	}

	load();
})();
