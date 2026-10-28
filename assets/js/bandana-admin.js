/**
 * Atelier Irisee → Dog bandana: the pictures (generate with Google Gemini, choose from the media library, remove
 * from the guide, own prompt) and the logo picker of the settings form.
 */
(function ($) {
	'use strict';

	var cfg = window.aimpBandanaAdmin || {};

	function post(card, op, extra) {
		var data = $.extend({
			action: 'aimp_bandana_pic',
			_ajax_nonce: $('.aimp-bpics').data('nonce'),
			slot: card.data('slot'),
			op: op
		}, extra || {});
		card.addClass('is-busy').find('button').prop('disabled', true);
		card.find('.aimp-bpic-status').removeClass('is-error').text('generate' === op ? cfg.busy : '');
		return $.post(window.ajaxurl, data).then(function (res) {
			if (res && res.success) {
				var fresh = $(res.data.html);
				card.replaceWith(fresh);
				return fresh;
			}
			return $.Deferred().reject(res && res.data && res.data.message ? res.data.message : cfg.failed).promise();
		}, function () {
			return $.Deferred().reject(cfg.failed).promise();
		}).fail(function (message) {
			card.removeClass('is-busy').find('button').prop('disabled', false);
			card.find('.aimp-bpic-status').addClass('is-error').text(message);
		});
	}

	function choose(title, done) {
		if (!window.wp || !wp.media) {
			return;
		}
		var frame = wp.media({ title: title, library: { type: 'image' }, multiple: false });
		frame.on('select', function () {
			done(frame.state().get('selection').first().toJSON());
		});
		frame.open();
	}

	$(document).on('click', '.aimp-bpic button[data-op]', function () {
		var card = $(this).closest('.aimp-bpic');
		var op = $(this).data('op');
		if ('choose' === op) {
			choose(cfg.choose, function (picture) {
				post(card, 'choose', { id: picture.id });
			});
			return;
		}
		if ('prompt' === op) {
			post(card, 'prompt', { prompt: card.find('textarea').val() });
			return;
		}
		post(card, op);
	});

	// Every slot without a picture, one after the other; an error stops the run.
	$(document).on('click', '#aimp-bpic-all', function () {
		var button = $(this);
		var cards = $('.aimp-bpic[data-missing="1"]');
		var progress = $('#aimp-bpic-progress');
		if (!cards.length) {
			progress.text(cfg.none);
			return;
		}
		if (!window.confirm(cfg.confirm.replace('%d', cards.length))) {
			return;
		}
		button.prop('disabled', true);
		var done = 0;
		(function next() {
			var slot = cards.eq(done).data('slot');
			if (done >= cards.length) {
				progress.text(cfg.ready);
				button.prop('disabled', false);
				return;
			}
			progress.text(cfg.progress.replace('%1$d', done + 1).replace('%2$d', cards.length));
			post($('.aimp-bpic[data-slot="' + slot + '"]'), 'generate').then(function () {
				done++;
				next();
			}, function () {
				progress.text(cfg.stopped);
				button.prop('disabled', false);
			});
		}());
	});

	// The logo for the PDF (settings form).
	$(document).on('click', '#aimp-bandana-logo-choose', function () {
		choose(cfg.logo, function (picture) {
			$('#aimp-bandana-logo').val(picture.id);
			var url = picture.sizes && picture.sizes.thumbnail ? picture.sizes.thumbnail.url : picture.url;
			$('#aimp-bandana-logo-preview').html($('<img>', { src: url, alt: '' }));
		});
	});
	$(document).on('click', '#aimp-bandana-logo-clear', function () {
		$('#aimp-bandana-logo').val('0');
		$('#aimp-bandana-logo-preview').empty();
	});
}(jQuery));
