
/**
 * Launch a new window to authenticate with OAuth, a script loaded by CP Sync
 * will send a message to the parent window with the result.
 *
 * @param {string} url The URL to open in the new window.
 * @param {object} args Additional options to pass to `window.open`.
 * @return {Promise<void>}
 */
export const launchOauth = (url, args = {}) => {
	url = new URL(url)

	// open auth window over the current window
	const authWindow = window.open(url.toString(), '_blank', 'width=600,height=600');

	if(!authWindow) {
		return Promise.reject('Failed to open authentication window. Make sure your browser allows popups.');
	}

	return new Promise((resolve, reject) => {
		let settled = false;

		const cleanup = () => {
			window.removeEventListener('message', onMessage);
			clearInterval(closedTimer);
		};

		// The popup navigates cross-origin (WP → OAuth bridge → PCO → back) and
		// replaces its document — and any listeners on it — several times before
		// returning. So we listen on OUR OWN window, which never navigates: the
		// callback page (add_oauth_script) posts its result to `window.opener`.
		const onMessage = (event) => {
			if (event.origin !== window.location.origin) {
				return;
			}

			if (event.data?.type !== 'cp_sync_oauth') {
				return;
			}

			settled = true;
			cleanup();

			try {
				authWindow.close();
			} catch (e) {} // eslint-disable-line no-empty

			if (event.data?.success) {
				resolve();
			} else {
				reject(event.data?.message || 'Failed to authenticate');
			}
		};

		window.addEventListener('message', onMessage);

		// Fallback: if the user closes the popup without completing (or it lands
		// somewhere that never posts back), settle as cancelled instead of
		// spinning forever.
		const closedTimer = setInterval(() => {
			if (authWindow.closed && ! settled) {
				settled = true;
				cleanup();
				reject('Authentication window was closed');
			}
		}, 500);
	})
}
