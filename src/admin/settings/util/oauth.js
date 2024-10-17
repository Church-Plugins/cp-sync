
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
	const authWindow = window.open(url.toString(), '_blank', 'width=600,height=600,noreferrer')

	return new Promise((resolve, reject) => {
		const onClosed = () => {
			reject('Authentication window was closed');
		}

		authWindow.addEventListener('close', onClosed);

		authWindow.addEventListener('message', (event) => {
			if (event.origin !== url.origin) {
				return;
			}

			if(event.data?.type !== 'cp_sync_oauth') {
				return;
			}

			authWindow.removeEventListener('close', onClosed);
	
			if (event.data?.success) {
				authWindow.close();
				resolve();
			} else {
				authWindow.close();
				reject(event.data?.message || 'Failed to authenticate');
			}
		})
	})
}
