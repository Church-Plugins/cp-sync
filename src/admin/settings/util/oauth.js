import { __ } from '@wordpress/i18n'
import apiFetch from '@wordpress/api-fetch'

const CHANNEL_NAME = 'cp_sync_oauth'

// How long to wait for a result before giving up on the auth window.
const AUTH_TIMEOUT = 5 * 60 * 1000

// After the window closes, how long to let an already-sent message arrive.
const CLOSE_GRACE = 400

/**
 * Ask the server whether the ChMS is connected.
 *
 * Used as a fallback whenever the auth window goes away without us hearing from it.
 * The token is stored server-side during the redirect, so a closed window tells us
 * nothing about whether the flow succeeded -- only the server knows.
 *
 * @param {string} chms The ChMS to check.
 * @return {Promise<boolean>}
 */
const checkConnection = (chms) =>
	apiFetch({ path: `/cp-sync/v1/${chms}/check-connection` })
		.then((response) => !!response?.connected)
		.catch(() => false)

/**
 * Launch a new window to authenticate with OAuth. The page CP Sync renders at the end
 * of the redirect posts the result back to this window.
 *
 * @param {string} url The URL to open in the new window.
 * @param {object} options Options.
 * @param {string} options.chms The ChMS being connected, used for the fallback check.
 * @return {Promise<void>} Resolves once the ChMS is connected.
 */
export const launchOauth = (url, { chms } = {}) => {
	// Must stay in the click's task -- anything awaited before this trips popup blockers.
	const authWindow = window.open(url, '_blank', 'width=600,height=600')

	if (!authWindow) {
		return Promise.reject(
			__('Failed to open the authentication window. Make sure your browser allows popups.', 'cp-sync')
		)
	}

	return new Promise((resolve, reject) => {
		let settled = false
		let channel = null
		let closedPoll = null
		let timer = null

		try {
			channel = new BroadcastChannel(CHANNEL_NAME)
		} catch (e) {
			// No BroadcastChannel support; postMessage and the fallback check still cover us.
		}

		const cleanup = () => {
			settled = true
			window.removeEventListener('message', onMessage)
			clearInterval(closedPoll)
			clearTimeout(timer)

			if (channel) {
				channel.close()
			}

			try {
				authWindow.close()
			} catch (e) {
				// Already gone.
			}
		}

		const onResult = (data) => {
			if (settled || data?.type !== CHANNEL_NAME) {
				return
			}

			cleanup()

			if (data.success) {
				resolve()
			} else {
				reject(data.message || __('Failed to authenticate', 'cp-sync'))
			}
		}

		// Ignore foreign messages rather than failing on them -- other scripts post here too.
		const onMessage = (event) => {
			if (event.origin !== window.location.origin) {
				return
			}

			onResult(event.data)
		}

		window.addEventListener('message', onMessage)

		if (channel) {
			channel.onmessage = (event) => onResult(event.data)
		}

		// The window can close without reaching us: the user closes it by hand, or a COOP
		// header severs window.opener. Ask the server what actually happened.
		closedPoll = setInterval(() => {
			if (settled || !authWindow.closed) {
				return
			}

			clearInterval(closedPoll)

			setTimeout(() => {
				if (settled) {
					return
				}

				checkConnection(chms).then((connected) => {
					if (settled) {
						return
					}

					cleanup()

					if (connected) {
						resolve()
					} else {
						reject(__('The authentication window closed before authorization finished.', 'cp-sync'))
					}
				})
			}, CLOSE_GRACE)
		}, 500)

		timer = setTimeout(() => {
			if (settled) {
				return
			}

			checkConnection(chms).then((connected) => {
				if (settled) {
					return
				}

				cleanup()

				if (connected) {
					resolve()
				} else {
					reject(__('Timed out waiting for authorization.', 'cp-sync'))
				}
			})
		}, AUTH_TIMEOUT)
	})
}
