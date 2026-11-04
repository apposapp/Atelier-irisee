/**
 * Atelier Irisee statistics: counts this page view (also on cached pages), and lets other scripts count an event
 * (window.aimpStats.send(kind, item, extra)). Anonymous: no cookie, nothing about the visitor is sent but the
 * page, where they came from and the screen size.
 */
(function () {
	'use strict';

	var cfg = window.aimpStatsConfig;
	if (!cfg || !cfg.endpoint || !navigator.sendBeacon) {
		return;
	}

	function post(params) {
		try {
			var body = new URLSearchParams();
			Object.keys(params).forEach(function (key) {
				body.append(key, params[key] === undefined || params[key] === null ? '' : String(params[key]));
			});
			navigator.sendBeacon(cfg.endpoint, body);
		} catch (e) {}
	}

	window.aimpStats = {
		send: function (kind, item, extra) {
			post({ k: kind, i: item, x: extra });
		}
	};

	function pageView() {
		var path = location.pathname;
		// The shop's category and brand pages are one page with a parameter.
		var query = new URLSearchParams(location.search);
		['aimp_cat', 'aimp_brand'].forEach(function (key) {
			if (query.get(key)) {
				path += (path.indexOf('?') === -1 ? '?' : '&') + key + '=' + query.get(key);
			}
		});
		var product = /\bsingle-product\b/.test(document.body.className) ? (document.body.className.match(/\bpostid-(\d+)\b/) || [])[1] : '';
		var referrer = '';
		try {
			referrer = document.referrer ? new URL(document.referrer).hostname : '';
		} catch (e) {}
		if (referrer === location.hostname) {
			referrer = 'self';
		}
		var width = window.innerWidth || document.documentElement.clientWidth || 0;
		post({
			k: 'view',
			i: path,
			p: product || '',
			r: referrer,
			d: width < 768 ? 'phone' : (width < 1100 ? 'tablet' : 'computer'),
			s: query.get('s') || '',
			n: document.body.classList.contains('search-no-results') ? 1 : 0
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', pageView);
	} else {
		pageView();
	}
})();
