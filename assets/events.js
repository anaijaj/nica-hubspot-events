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
})();
