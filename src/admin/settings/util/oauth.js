
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
		const onClosed = () => {
			reject('Authentication window was closed');
		}

		authWindow.addEventListener('close', onClosed);
		authWindow.addEventListener('beforeunload', onClosed);

		authWindow.addEventListener('message', (event) => {
			if (event.origin !== window.location.origin) {
				return reject('There was an error communicating with the authentication window');
			}

			if(event.data?.type !== 'cp_sync_oauth') {
				return reject('Unexpected message from authentication window');
			}

			authWindow.removeEventListener('close', onClosed);
			authWindow.removeEventListener('beforeunload', onClosed);
			authWindow.close();

			if (event.data?.success) {
				resolve();
			} else {
				reject(event.data?.message || 'Failed to authenticate');
			}
		})
	})
}
