import { __ } from '@wordpress/i18n'

/**
 * App-level loading skeleton shown while the initial connection check (and the
 * store's global seed) resolves. It mirrors the `.cps-app` shell — title, tab
 * bar, and content card — so the layout does not jump when the real UI mounts.
 *
 * This replaces the previous blank screen during the `!isReady` window in
 * `SettingsProvider`. It is presentational only (aria-hidden shimmer blocks)
 * with an accessible status label for screen readers.
 */
export default function LoadingSkeleton() {
	return (
		<div className="cps-app cps-skeleton" aria-busy="true">
			<span className="screen-reader-text" role="status">
				{ __( 'Loading settings…', 'cp-sync' ) }
			</span>

			<div className="cps-app__title">
				<span className="cps-skeleton__block cps-skeleton__title" />
			</div>

			<div className="cps-tab-bar" aria-hidden="true">
				{ [ 90, 70, 80, 60, 75 ].map( ( width, i ) => (
					<span
						key={ i }
						className="cps-skeleton__block cps-skeleton__tab"
						style={ { width: `${ width }px` } }
					/>
				) ) }
			</div>

			<div className="cps-tab-content" aria-hidden="true">
				<div className="cps-skeleton__card">
					<span className="cps-skeleton__block cps-skeleton__line is-wide" />
					<span className="cps-skeleton__block cps-skeleton__line" />
					<span className="cps-skeleton__block cps-skeleton__line is-short" />
					<span className="cps-skeleton__block cps-skeleton__field" />
					<span className="cps-skeleton__block cps-skeleton__field" />
					<span className="cps-skeleton__block cps-skeleton__field is-short" />
				</div>
			</div>
		</div>
	)
}
