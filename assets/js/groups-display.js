/**
 * Clear a group image that fails to load.
 *
 * The script is enqueued in the footer, so an image can already have failed
 * before this file runs. Those images do not fire another error event.
 * Images still loading are left alone until a later error event.
 *
 * The listener is external on purpose. An inline onerror attribute would be
 * part of the markup a block theme later filters.
 */
(function () {
	var rootSelector = '.cp-group-item--thumb--canvas, .cp-group-single--thumb';

	function replaceFailedImage(image) {
		if (!image || image.tagName !== 'IMG' || !image.closest) {
			return;
		}

		var slot = image.closest(rootSelector);

		if (!slot) {
			return;
		}

		image.remove();

		if (slot.classList.contains('cp-group-item--thumb--canvas')) {
			// The list card shows the image as a background of this link.
			slot.style.background = '';
			slot.classList.add('is-empty');
		} else {
			slot.remove();
		}
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

	var images = document.querySelectorAll('.cp-group-item--thumb--canvas img, .cp-group-single--thumb img');
	var index;

	for (index = 0; index < images.length; index++) {
		if (alreadyFailed(images[index])) {
			replaceFailedImage(images[index]);
		}
	}
}());
