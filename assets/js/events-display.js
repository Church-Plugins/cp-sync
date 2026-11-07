/**
 * Replace a hero or series image that fails to load, or remove a list card's.
 *
 * Images in a description are left alone. Only the media slots have a space
 * sized for the placeholder.
 *
 * The script is enqueued in the footer, so an image can already have failed
 * before this file runs. Those images do not fire another error event.
 * Images still loading are left alone until a later error event.
 *
 * Kept out of the markup so a template override does not need an onerror
 * attribute.
 */
(function () {
	// Matched directly because shortcode lists have no .cp-sync-events wrapper.
	var mediaSelector = '.cp-sync-event__media, .cp-sync-event-card__media, .cp-sync-series-card__media';
	var imageSelector = '.cp-sync-event__media img, .cp-sync-event-card__media img, .cp-sync-series-card__media img';
	var placeholderClass = 'cp-sync-event__placeholder';

	function replaceFailedImage(image) {
		if (!image || image.tagName !== 'IMG' || !image.closest) {
			return;
		}

		if (!image.closest(mediaSelector)) {
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

		// A list card with no image has no image slot, so a failed one leaves none.
		if (media && media.classList && media.classList.contains('cp-sync-event-card__media')) {
			media.remove();
			return;
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
		// An image with no src yet, such as a lazy-load placeholder, has not failed.
		return !!(image && image.tagName === 'IMG' && image.getAttribute('src') && image.complete && image.naturalWidth === 0);
	}

	document.addEventListener(
		'error',
		function (event) {
			replaceFailedImage(event.target);
		},
		true
	);

	var images = document.querySelectorAll(imageSelector);
	var index;

	for (index = 0; index < images.length; index++) {
		if (alreadyFailed(images[index])) {
			replaceFailedImage(images[index]);
		}
	}
}());

/**
 * Apply the category filter as soon as one is chosen. Without JavaScript, the
 * form has a Filter button instead.
 */
(function () {
	var select = document.getElementById('cp-sync-event-category');

	if (!select || !select.form) {
		return;
	}

	select.addEventListener('change', function () {
		// All categories is the archive itself, not an empty filter.
		if ('' === select.value) {
			select.disabled = true;
		}

		select.form.submit();
	});
}());
