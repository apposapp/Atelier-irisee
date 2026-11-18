/**
 * Atelier Irisee blog: the heart (like, once per visitor), share (the phone's share sheet, or a small menu with
 * social media and "Copy link"), pictures larger on a click, YouTube / Vimeo that only load when played, the view of
 * a post (counted by the statistics), and the overview's filter bar: categories, search, sorting, more posts.
 */
(function () {
	'use strict';

	var cfg = window.aimpBlog || {};
	var t = cfg.i18n || {};
	var DRAWER = '(max-width: 999px)';
	var SIDEBAR_KEY = 'aimp_blog_filters';

	function esc(value) {
		return String(value === undefined || value === null ? '' : value).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function request(action, data) {
		var body = new URLSearchParams();
		Object.keys(data).forEach(function (key) {
			body.append(key, data[key]);
		});
		if (cfg.lang) {
			body.append('aimp_lang', cfg.lang);
		}
		return fetch(String(cfg.endpoint || '').replace('%%endpoint%%', action), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				if (!json || !json.success) {
					throw new Error('aimp_blog');
				}
				return json.data;
			});
	}

	function isDrawer() {
		return !!(window.matchMedia && window.matchMedia(DRAWER).matches);
	}

	/* ---------------------------------------------------------------
	 * Like
	 * ------------------------------------------------------------- */

	function likedIds() {
		var found = document.cookie.match(/(?:^|;\s*)aimp_blog_likes=([^;]*)/);
		if (!found) {
			return [];
		}
		try {
			return decodeURIComponent(found[1]).split(',').filter(Boolean);
		} catch (e) {
			return [];
		}
	}

	function markLiked(scope) {
		var ids = likedIds();
		(scope || document).querySelectorAll('[data-aimp-blog-like]').forEach(function (button) {
			var on = ids.indexOf(button.getAttribute('data-aimp-blog-like')) !== -1;
			button.classList.toggle('is-liked', on);
			button.setAttribute('aria-pressed', on ? 'true' : 'false');
		});
	}

	function setLike(button, on, count, shown, label) {
		button.classList.toggle('is-liked', on);
		button.setAttribute('aria-pressed', on ? 'true' : 'false');
		var number = button.querySelector('.aimp-blog-count');
		if (number) {
			number.setAttribute('data-count', count);
			number.textContent = shown === undefined ? count : shown;
		}
		var text = button.querySelector('[data-label]');
		if (text && label) {
			text.textContent = label;
		}
	}

	function like(button) {
		var id = button.getAttribute('data-aimp-blog-like');
		var all = document.querySelectorAll('[data-aimp-blog-like="' + id + '"]');
		if (button.classList.contains('is-busy')) {
			return;
		}
		var on = !button.classList.contains('is-liked');
		var before = [];
		// At once on the screen; the answer of the server puts the real count.
		all.forEach(function (b) {
			var number = b.querySelector('.aimp-blog-count');
			var count = number ? parseInt(number.getAttribute('data-count'), 10) || 0 : 0;
			before.push([b, b.classList.contains('is-liked'), count, number ? number.textContent : '']);
			setLike(b, on, Math.max(0, count + (on ? 1 : -1)));
			b.classList.add('is-busy');
			if (on) {
				b.classList.remove('is-popping');
				// Restart the little heart animation.
				void b.offsetWidth;
				b.classList.add('is-popping');
			}
		});
		request('aimp_blog_like', { id: id })
			.then(function (data) {
				all.forEach(function (b) {
					setLike(b, !!data.liked, data.likes, data.shown, data.label);
				});
			})
			.catch(function () {
				before.forEach(function (row) {
					setLike(row[0], row[1], row[2], row[3]);
				});
			})
			.then(function () {
				all.forEach(function (b) {
					b.classList.remove('is-busy');
				});
			});
	}

	/* ---------------------------------------------------------------
	 * Share
	 * ------------------------------------------------------------- */

	var menu = null;

	function closeMenu() {
		if (menu) {
			var owner = menu.owner;
			menu.remove();
			menu = null;
			if (owner) {
				owner.setAttribute('aria-expanded', 'false');
			}
		}
	}

	function openMenu(button) {
		var url = button.getAttribute('data-aimp-blog-share');
		var title = button.getAttribute('data-title') || '';
		var image = button.getAttribute('data-image') || '';
		var u = encodeURIComponent(url);
		var items = [
			['facebook', 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' + u],
			['pinterest', 'Pinterest', 'https://pinterest.com/pin/create/button/?url=' + u + (image ? '&media=' + encodeURIComponent(image) : '') + '&description=' + encodeURIComponent(title)],
			['whatsapp', 'WhatsApp', 'https://wa.me/?text=' + encodeURIComponent(title + ' ' + url)],
			['email', t.email || 'E-mail', 'mailto:?subject=' + encodeURIComponent(title) + '&body=' + u]
		];
		var icons = {
			facebook: '<path d="M14 8.5V7c0-.8.4-1.2 1.3-1.2H17V2.4c-.5-.1-1.6-.2-2.7-.2-2.8 0-4.3 1.6-4.3 4.5v1.8H7.2v3.6H10V22h4v-9.9h2.8l.5-3.6z"/>',
			pinterest: '<path d="M12.2 2C6.7 2 4 5.9 4 9.2c0 2 .8 3.8 2.4 4.4.3.1.5 0 .6-.3l.2-.9c.1-.3 0-.4-.2-.7-.5-.6-.8-1.3-.8-2.4 0-3 2.3-5.8 6-5.8 3.3 0 5.1 2 5.1 4.7 0 3.5-1.6 6.5-3.9 6.5-1.3 0-2.2-1-1.9-2.3.4-1.5 1.1-3.2 1.1-4.3 0-1-.5-1.8-1.6-1.8-1.3 0-2.3 1.3-2.3 3.1 0 1.1.4 1.9.4 1.9l-1.5 6.5c-.4 1.9-.1 4.3 0 4.5 0 .1.2.2.3.1.1-.2 1.6-2 2.1-3.8l.8-3.2c.4.8 1.6 1.4 2.8 1.4 3.7 0 6.2-3.4 6.2-7.9C20 5.3 16.9 2 12.2 2z"/>',
			whatsapp: '<path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.5-3.9-4.7-4.1-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.9 2.1c.1.2.1.3 0 .5l-.3.5-.4.5c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1.1c.2-.3.4-.2.6-.1l2 1c.3.1.5.2.5.3.1.2.1.7-.1 1.3z"/>',
			email: '<path d="M3 5h18v14H3z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m3 6 9 7 9-7" fill="none" stroke="currentColor" stroke-width="1.8"/>',
			copy: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>'
		};
		var svg = function (key) {
			return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + icons[key] + '</svg>';
		};
		closeMenu();
		menu = document.createElement('div');
		menu.className = 'aimp-blog-share-menu';
		menu.setAttribute('role', 'menu');
		menu.owner = button;
		menu.innerHTML = items.map(function (item) {
			return '<a role="menuitem" href="' + esc(item[2]) + '" target="_blank" rel="noopener">' + svg(item[0]) + '<span>' + esc(item[1]) + '</span></a>';
		}).join('') + '<button type="button" role="menuitem" data-copy>' + svg('copy') + '<span>' + esc(t.copy || 'Copy link') + '</span></button>';
		document.body.appendChild(menu);
		var box = button.getBoundingClientRect();
		var width = menu.offsetWidth;
		var left = Math.max(8, Math.min(window.innerWidth - width - 8, box.left + box.width / 2 - width / 2));
		var below = box.bottom + menu.offsetHeight + 12 < window.innerHeight;
		menu.style.left = left + 'px';
		menu.style.top = (below ? box.bottom + 8 : box.top - menu.offsetHeight - 8) + 'px';
		button.setAttribute('aria-expanded', 'true');
		menu.querySelector('[data-copy]').addEventListener('click', function (e) {
			var label = e.currentTarget.querySelector('span');
			var done = function () {
				label.textContent = t.copied || 'Link copied';
				window.setTimeout(closeMenu, 1200);
			};
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(url).then(done, function () {
					window.prompt('', url); // eslint-disable-line no-alert
				});
			} else {
				window.prompt('', url); // eslint-disable-line no-alert
			}
		});
		menu.querySelector('a, button').focus();
	}

	function share(button) {
		if (menu && menu.owner === button) {
			closeMenu();
			return;
		}
		var touch = window.matchMedia && window.matchMedia('(hover: none)').matches;
		if (navigator.share && touch) {
			navigator.share({ title: button.getAttribute('data-title') || '', url: button.getAttribute('data-aimp-blog-share') }).catch(function () {});
			return;
		}
		openMenu(button);
	}

	/* ---------------------------------------------------------------
	 * Pictures larger, videos that load when played
	 * ------------------------------------------------------------- */

	function zoom(link) {
		var back = document.activeElement;
		var img = link.querySelector('img');
		var box = document.createElement('div');
		box.className = 'aimp-blog-lightbox';
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-modal', 'true');
		box.innerHTML = '<button type="button" class="aimp-blog-lightbox-close" aria-label="' + esc(t.close || 'Close') + '">×</button>' +
			'<img src="' + esc(link.getAttribute('href')) + '" alt="' + esc(img ? img.getAttribute('alt') : '') + '">';
		var close = function () {
			box.remove();
			document.removeEventListener('keydown', onKey);
			document.documentElement.classList.remove('aimp-blog-zoomed');
			if (back && back.focus) {
				back.focus();
			}
		};
		var onKey = function (e) {
			if (e.key === 'Escape' || e.key === 'Tab') {
				e.preventDefault();
				if (e.key === 'Escape') {
					close();
				}
			}
		};
		box.addEventListener('click', close);
		document.addEventListener('keydown', onKey);
		document.body.appendChild(box);
		document.documentElement.classList.add('aimp-blog-zoomed');
		box.querySelector('button').focus();
	}

	function play(button) {
		var frame = document.createElement('iframe');
		frame.src = button.getAttribute('data-embed');
		frame.title = button.getAttribute('aria-label') || 'Video';
		frame.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
		frame.setAttribute('allowfullscreen', '');
		button.replaceWith(frame);
		frame.focus();
	}

	document.addEventListener('click', function (e) {
		var el = e.target.closest('[data-aimp-blog-like], [data-aimp-blog-share], [data-aimp-zoom], .aimp-blog-play');
		if (!el) {
			if (menu && !e.target.closest('.aimp-blog-share-menu')) {
				closeMenu();
			}
			return;
		}
		if (el.hasAttribute('data-aimp-blog-like')) {
			e.preventDefault();
			like(el);
		} else if (el.hasAttribute('data-aimp-blog-share')) {
			e.preventDefault();
			share(el);
		} else if (el.hasAttribute('data-aimp-zoom')) {
			e.preventDefault();
			zoom(el);
		} else {
			e.preventDefault();
			play(el);
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && menu) {
			var owner = menu.owner;
			closeMenu();
			if (owner) {
				owner.focus();
			}
		}
	});
	window.addEventListener('resize', closeMenu);
	window.addEventListener('scroll', closeMenu, { passive: true });

	/* ---------------------------------------------------------------
	 * The overview: filters, sorting, more posts
	 * ------------------------------------------------------------- */

	function Overview(root) {
		var self = this;
		this.root = root;
		try {
			this.cfg = JSON.parse(root.getAttribute('data-config') || '{}');
		} catch (e) {
			this.cfg = {};
		}
		this.page = parseInt(this.cfg.page, 10) || 1;
		this.seq = 0;
		this.grid = root.querySelector('[data-role="grid"]');
		this.summary = root.querySelector('[data-role="summary"]');
		this.more = root.querySelector('[data-role="more"]');
		this.none = root.querySelector('[data-role="none"]');
		this.sortEl = root.querySelector('[data-role="sort"]');
		this.searchEl = root.querySelector('[data-role="search"]');
		this.countEl = root.querySelector('[data-role="count"]');
		this.toggle = root.querySelector('[data-action="toggle-filters"]');
		this.sidebar = root.querySelector('.aimp-shop-sidebar');
		this.backdrop = root.querySelector('.aimp-shop-backdrop');

		root.addEventListener('click', function (e) {
			var target = e.target.closest('[data-action], [data-page]');
			if (!target || !root.contains(target)) {
				return;
			}
			if (target.hasAttribute('data-page')) {
				e.preventDefault();
				self.load(parseInt(target.getAttribute('data-page'), 10) || 1, false, true);
				return;
			}
			var action = target.getAttribute('data-action');
			if (action === 'toggle-filters') {
				self.setSidebar(!self.open);
			} else if (action === 'close-filters') {
				self.setSidebar(false);
			} else if (action === 'more') {
				self.load(self.page + 1, true);
			} else if (action === 'remove-filter') {
				self.remove(target.getAttribute('data-kind'), target.getAttribute('data-value'));
			} else if (action === 'clear-filters') {
				self.clear();
			}
		});
		root.addEventListener('change', function (e) {
			if (e.target.matches('[data-role="category"], [data-role="sort"]')) {
				self.load(1);
			}
		});
		if (this.searchEl) {
			var timer = 0;
			this.searchEl.addEventListener('input', function () {
				window.clearTimeout(timer);
				timer = window.setTimeout(function () {
					self.load(1);
				}, 450);
			});
			this.searchEl.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					window.clearTimeout(timer);
					self.load(1);
				}
			});
		}
		root.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && self.open && isDrawer()) {
				self.setSidebar(false);
				self.toggle.focus();
			}
		});
		if (this.toggle) {
			var start = function () {
				var stored = null;
				try {
					stored = window.localStorage.getItem(SIDEBAR_KEY);
				} catch (e) {}
				self.setSidebar(!isDrawer() && stored === 'open', true);
			};
			if (window.matchMedia) {
				var mq = window.matchMedia(DRAWER);
				if (mq.addEventListener) {
					mq.addEventListener('change', start);
				} else if (mq.addListener) {
					mq.addListener(start);
				}
			}
			start();
		}
	}

	Overview.prototype.setSidebar = function (open, initial) {
		this.open = open;
		this.root.classList.toggle('is-filters-open', open);
		this.root.classList.toggle('is-filters-closed', !open);
		this.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (this.backdrop) {
			this.backdrop.hidden = !(open && isDrawer());
		}
		if (!initial && !isDrawer()) {
			try {
				window.localStorage.setItem(SIDEBAR_KEY, open ? 'open' : 'closed');
			} catch (e) {}
		}
		if (open && !initial && isDrawer()) {
			var first = this.sidebar.querySelector('input, select, button');
			if (first) {
				first.focus();
			}
		}
	};

	Overview.prototype.cats = function () {
		return Array.prototype.map.call(this.root.querySelectorAll('[data-role="category"]:checked'), function (box) {
			return box.value;
		});
	};

	Overview.prototype.remove = function (kind, value) {
		if (kind === 'category') {
			this.root.querySelectorAll('[data-role="category"]').forEach(function (box) {
				if (box.value === value) {
					box.checked = false;
				}
			});
		} else if (kind === 'search' && this.searchEl) {
			this.searchEl.value = '';
		}
		this.load(1);
	};

	Overview.prototype.clear = function () {
		this.root.querySelectorAll('[data-role="category"]').forEach(function (box) {
			box.checked = false;
		});
		if (this.searchEl) {
			this.searchEl.value = '';
		}
		this.load(1);
	};

	/**
	 * @param {number}  page   Page.
	 * @param {boolean} append "Load more": under the cards already there.
	 * @param {boolean} scroll Page numbers: back to the top of the overview.
	 */
	Overview.prototype.load = function (page, append, scroll) {
		var self = this;
		var seq = ++this.seq;
		var cats = this.cats();
		var q = this.searchEl ? this.searchEl.value.trim() : '';
		this.root.classList.add('is-loading');
		this.root.setAttribute('aria-busy', 'true');
		if (this.countEl) {
			var active = cats.length + (q ? 1 : 0);
			this.countEl.textContent = active;
			this.countEl.hidden = !active;
		}
		request('aimp_blog', {
			atts: JSON.stringify(this.cfg.atts || {}),
			aimp_bcat: cats.join(','),
			aimp_bq: q,
			aimp_bsort: this.sortEl ? this.sortEl.value : (this.cfg.sort || ''),
			aimp_bpage: page
		})
			.then(function (data) {
				if (seq !== self.seq) {
					return;
				}
				if (append) {
					var before = self.grid.children.length;
					self.grid.insertAdjacentHTML('beforeend', data.html);
					var first = self.grid.children[before];
					if (first) {
						var link = first.querySelector('.aimp-blog-card-title a');
						if (link) {
							link.focus({ preventScroll: true });
						}
					}
				} else {
					self.grid.innerHTML = data.html;
				}
				self.summary.innerHTML = data.summary;
				self.more.innerHTML = data.more;
				self.none.hidden = data.total > 0;
				self.page = data.page;
				markLiked(self.grid);
				var params = new URLSearchParams(data.args || {}).toString();
				window.history.replaceState(null, '', window.location.pathname + (params ? '?' + params : '') + window.location.hash);
				if (scroll) {
					self.root.scrollIntoView({ behavior: 'smooth', block: 'start' });
				}
			})
			.catch(function () {
				if (seq === self.seq) {
					self.more.innerHTML = '<p class="aimp-blog-error" role="alert">' + esc(t.error || 'Something went wrong.') + '</p>';
				}
			})
			.then(function () {
				if (seq === self.seq) {
					self.root.classList.remove('is-loading');
					self.root.removeAttribute('aria-busy');
				}
			});
	};

	function start() {
		markLiked(document);
		document.querySelectorAll('[data-aimp-blog]').forEach(function (root) {
			if (!root.aimpBlog) {
				root.aimpBlog = new Overview(root);
			}
		});
		// A post was read: counted once a day per visitor (not the shop's own people: no statistics script then).
		var post = document.querySelector('[data-aimp-blog-post]');
		if (post && window.aimpStats && window.aimpStats.send) {
			window.aimpStats.send('blog', post.getAttribute('data-aimp-blog-post'));
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
