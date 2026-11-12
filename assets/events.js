(function () {
	'use strict';

	function flash(btn, text) {
		var old = btn.getAttribute('data-label');
		if (old === null) {
			btn.setAttribute('data-label', btn.innerHTML);
			old = btn.innerHTML;
		}
		btn.classList.add('is-copied');
		btn.setAttribute('title', text);
		window.setTimeout(function () {
			btn.classList.remove('is-copied');
			btn.removeAttribute('title');
		}, 1800);
	}

	function copy(url, btn) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(function () { flash(btn, 'Link copied'); }, function () { window.prompt('Copy this link:', url); });
		} else {
			window.prompt('Copy this link:', url);
		}
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-nica-share]') : null;
		if (!btn) { return; }
		e.preventDefault();
		var url = btn.getAttribute('data-url');
		var title = btn.getAttribute('data-title') || document.title;
		if (navigator.share) {
			navigator.share({ title: title, url: url }).catch(function () {});
		} else {
			copy(url, btn);
		}
	});
	/* Event type filter: shows/hides cards in the same list. Cards stay in the page, so no reload. */
function initFilter(wrap) {
	var buttons = wrap.querySelectorAll('.nica-filter');
	if (!buttons.length) { return; }
	var cards = wrap.querySelectorAll('.nica-card');
	var none = wrap.querySelector('.nica-events-none');

	function apply(type) {
		var shown = 0;
		Array.prototype.forEach.call(cards, function (card) {
			var match = !type || card.getAttribute('data-type') === type;
			card.hidden = !match;
			if (match) { shown++; }
		});
		Array.prototype.forEach.call(buttons, function (b) {
			b.setAttribute('aria-pressed', b.getAttribute('data-type') === type ? 'true' : 'false');
		});
		if (none) { none.hidden = shown !== 0; }
	}

	function remember(type) {
		try {
			var url = new URL(window.location.href);
			if (type) { url.searchParams.set('type', type); } else { url.searchParams.delete('type'); }
			window.history.replaceState(null, '', url.toString());
		} catch (e) { /* old browser: filtering still works */ }
	}

	Array.prototype.forEach.call(buttons, function (b) {
		b.addEventListener('click', function () {
			var type = b.getAttribute('data-type') || '';
			apply(type);
			remember(type);
		});
	});

	// A link like /events/?type=race-weekend opens already filtered.
	var start = '';
	try { start = new URL(window.location.href).searchParams.get('type') || ''; } catch (e) { start = ''; }
	var known = Array.prototype.some.call(buttons, function (b) { return b.getAttribute('data-type') === start; });
	apply(known ? start : '');
}

function init() {
	var wraps = document.querySelectorAll('[data-nica-events]');
	Array.prototype.forEach.call(wraps, initFilter);
}
if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
