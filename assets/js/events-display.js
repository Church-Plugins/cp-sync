/**
 * Replace an event image that fails to load.
 *
 * Kept out of the markup so a template override does not need an onerror
 * attribute.
 */
(function () {
	document.addEventListener(
		'error',
		function (event) {
			var image = event.target;

			if (!image || image.tagName !== 'IMG' || !image.closest) {
				return;
			}

			if (!image.closest('.cp-sync-events')) {
				return;
			}

			var media = image.parentNode;

			image.remove();

			if (!media || !media.appendChild || media.querySelector('.cp-sync-event__placeholder')) {
				return;
			}

			var placeholder = document.createElement('div');
			placeholder.className = 'cp-sync-event__placeholder';
			placeholder.setAttribute('aria-hidden', 'true');
			media.appendChild(placeholder);
		},
		true
	);
}());
