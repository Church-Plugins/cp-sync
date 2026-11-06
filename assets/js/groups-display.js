/**
 * Replace a group image that fails to load.
 *
 * The listener is external on purpose. An inline onerror attribute would be
 * part of the markup a block theme later filters.
 */
(function () {
	document.addEventListener(
		'error',
		function (event) {
			var image = event.target;

			if (!image || image.tagName !== 'IMG' || !image.closest) {
				return;
			}

			if (!image.closest('.cp-sync-groups')) {
				return;
			}

			var media = image.parentNode;

			image.remove();

			if (!media || !media.appendChild || media.querySelector('.cp-sync-groups__placeholder')) {
				return;
			}

			var placeholder = document.createElement('div');
			placeholder.className = 'cp-sync-groups__placeholder';
			placeholder.setAttribute('aria-hidden', 'true');
			media.appendChild(placeholder);
		},
		true
	);
}());
