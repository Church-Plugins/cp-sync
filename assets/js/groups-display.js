/**
 * Replace a group image that fails to load.
 *
 * The script is enqueued in the footer, so an image can already have failed
 * before this file runs. Those images do not fire another error event.
 * Images still loading are left alone until a later error event.
 *
 * The listener is external on purpose. An inline onerror attribute would be
 * part of the markup a block theme later filters.
 */
(function () {
	var rootSelector = '.cp-sync-groups';
	var placeholderClass = 'cp-sync-groups__placeholder';

	function replaceFailedImage(image) {
		if (!image || image.tagName !== 'IMG' || !image.closest) {
			return;
		}

		if (!image.closest(rootSelector)) {
			return;
		}

		if (image.getAttribute('data-cp-sync-fallback') === 'done') {
			return;
		}

		image.setAttribute('data-cp-sync-fallback', 'done');

		var media = image.parentNode;

		if (image.parentNode) {
			image.remove();
		}

		if (!media || !media.appendChild || media.querySelector('.' + placeholderClass)) {
			return;
		}

		var placeholder = document.createElement('div');
		placeholder.className = placeholderClass;
		placeholder.setAttribute('aria-hidden', 'true');
		media.appendChild(placeholder);
	}

	function alreadyFailed(image) {
		return !!(image && image.tagName === 'IMG' && image.complete && image.naturalWidth === 0);
	}

	document.addEventListener(
		'error',
		function (event) {
			replaceFailedImage(event.target);
		},
		true
	);

	var images = document.querySelectorAll(rootSelector + ' img');
	var index;

	for (index = 0; index < images.length; index++) {
		if (alreadyFailed(images[index])) {
			replaceFailedImage(images[index]);
		}
	}
}());
