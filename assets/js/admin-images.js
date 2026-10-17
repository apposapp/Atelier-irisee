/**
 * Settings → Images: "Optimize all images" converts the media library in short batches (class-aimp-images.php),
 * with a progress bar, until everything is done.
 */
(function () {
	'use strict';

	var box = document.querySelector('[data-aimp-images]');
	if (!box || !window.fetch) {
		return;
	}
	var start = box.querySelector('.aimp-images-start');
	var restart = box.querySelector('.aimp-images-restart');
	var status = box.querySelector('.aimp-images-status');
	var bar = box.querySelector('.aimp-images-bar');
	var fill = bar.querySelector('span');
	var message = box.querySelector('.aimp-images-message');
	var running = false;
	var failed = [];

	function fmt(text, values) {
		var i = 0;
		return text.replace(/%(\d)\$s|%s|%%/g, function (all, n) {
			if (all === '%%') {
				return '%';
			}
			return String(values[n ? parseInt(n, 10) - 1 : i++]);
		});
	}

	function size(bytes) {
		var mb = bytes / 1048576;
		return mb >= 1 ? mb.toFixed(1) + ' MB' : Math.round(bytes / 1024) + ' kB';
	}

	function show(data) {
		var saved = data.before > 0 ? Math.round(100 - data.after / data.before * 100) : 0;
		status.textContent = fmt(box.getAttribute('data-status'), [data.optimized, data.total, size(data.before), size(data.after), saved]);
		fill.style.width = (data.total ? Math.round(data.optimized / data.total * 100) : 100) + '%';
	}

	function say(text) {
		message.textContent = text;
		message.hidden = !text;
	}

	function batch(fresh) {
		var body = new URLSearchParams();
		body.append('action', 'aimp_images_batch');
		body.append('nonce', box.getAttribute('data-nonce'));
		if (fresh) {
			body.append('restart', '1');
		}
		return fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.json();
		});
	}

	function run(fresh) {
		if (running) {
			return;
		}
		running = true;
		failed = [];
		start.disabled = true;
		restart.disabled = true;
		bar.hidden = false;
		say('');
		var tries = 0;
		var step = function (first) {
			batch(first).then(function (json) {
				if (!json || !json.success) {
					throw new Error('failed');
				}
				var data = json.data;
				tries = 0;
				failed = failed.concat(data.failed || []);
				show(data);
				if (data.done) {
					finish(box.getAttribute('data-ready'));
					return;
				}
				say(fmt(box.getAttribute('data-busy'), [data.optimized, data.total]));
				step(false);
			}).catch(function () {
				// A batch can time out on a busy server: try a few times before giving up.
				if (++tries < 3) {
					window.setTimeout(function () {
						step(false);
					}, 2000);
				} else {
					finish(box.getAttribute('data-failed'));
				}
			});
		};
		step(!!fresh);
	}

	function finish(text) {
		running = false;
		start.disabled = false;
		restart.disabled = false;
		say(text + (failed.length ? ' ' + fmt(box.getAttribute('data-error'), [failed.join(', ')]) : ''));
	}

	start.addEventListener('click', function () {
		run(false);
	});
	restart.addEventListener('click', function () {
		run(true);
	});
})();
