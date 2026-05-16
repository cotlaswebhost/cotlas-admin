(function () {
	'use strict';

	var pendingUrl = '';
	var previousFocus = null;
	var modal = null;

	function buildModal() {
		if (modal) {
			return modal;
		}

		modal = document.createElement('div');
		modal.className = 'cotlas-delete-modal';
		modal.setAttribute('aria-hidden', 'true');
		modal.innerHTML = [
			'<div class="cotlas-delete-modal__backdrop" data-cotlas-delete-cancel></div>',
			'<div class="cotlas-delete-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cotlas-delete-title" aria-describedby="cotlas-delete-message">',
			'<button type="button" class="cotlas-delete-modal__close" data-cotlas-delete-cancel aria-label="Cancel delete">&times;</button>',
			'<div class="cotlas-delete-modal__icon" aria-hidden="true">!</div>',
			'<h2 class="cotlas-delete-modal__title" id="cotlas-delete-title">Delete this post?</h2>',
			'<p class="cotlas-delete-modal__message" id="cotlas-delete-message">This post will be deleted permanently. You cannot restore it later, and it will disappear from posts lists.</p>',
			'<div class="cotlas-delete-modal__actions">',
			'<button type="button" class="cotlas-delete-modal__btn cotlas-delete-modal__btn--cancel" data-cotlas-delete-cancel>Cancel</button>',
			'<button type="button" class="cotlas-delete-modal__btn cotlas-delete-modal__btn--delete" data-cotlas-delete-confirm>Delete</button>',
			'</div>',
			'</div>'
		].join('');

		document.body.appendChild(modal);

		modal.addEventListener('click', function (event) {
			if (event.target.closest('[data-cotlas-delete-cancel]')) {
				event.preventDefault();
				closeModal();
				return;
			}

			if (event.target.closest('[data-cotlas-delete-confirm]')) {
				event.preventDefault();
				if (pendingUrl) {
					window.location.href = pendingUrl;
				}
			}
		});

		return modal;
	}

	function openModal(url) {
		var dialog = buildModal();
		var cancelButton = dialog.querySelector('[data-cotlas-delete-cancel].cotlas-delete-modal__btn');

		pendingUrl = url;
		previousFocus = document.activeElement;
		dialog.setAttribute('aria-hidden', 'false');
		document.documentElement.classList.add('cotlas-delete-modal-open');

		window.setTimeout(function () {
			if (cancelButton) {
				cancelButton.focus();
			}
		}, 0);
	}

	function closeModal() {
		if (!modal) {
			return;
		}

		pendingUrl = '';
		modal.setAttribute('aria-hidden', 'true');
		document.documentElement.classList.remove('cotlas-delete-modal-open');

		if (previousFocus && typeof previousFocus.focus === 'function') {
			previousFocus.focus();
		}
	}

	function isDeletePostUrl(url) {
		return /[?&]action=cotlas_trash_post(?:&|$)/.test(url);
	}

	document.addEventListener('click', function (event) {
		var link = event.target.closest('a[href]');

		if (!link || !isDeletePostUrl(link.href)) {
			return;
		}

		event.preventDefault();
		openModal(link.href);
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeModal();
		}
	});
}());
