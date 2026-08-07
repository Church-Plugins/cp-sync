import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';
import { useSettings } from '../contexts/settingsContext';

/**
 * "Pull Now" action button with save-first semantics.
 *
 * Unsaved settings edits are saved before the pull starts ( the same pattern
 * <Preview> uses ), so the import always runs against what the admin sees on
 * screen — previously an edited source/filter was silently ignored until the
 * next manual save. If the save fails, the pull is NOT started and the save
 * error is surfaced instead.
 *
 * @param {Object} props
 * @param {string} props.type            Integration type for /cp-sync/v1/pull/{type}.
 * @param {string} props.noticeClassName Optional className for the notices.
 * @returns {JSX.Element}
 */
export default function PullNow( { type, noticeClassName = 'cps-pco-notice' } ) {
	const [ pulling, setPulling ] = useState( false );
	const [ awaitingPull, setAwaitingPull ] = useState( false );
	const [ pullSuccess, setPullSuccess ] = useState( false );
	const [ error, setError ] = useState( null );
	const { save, isDirty, isSaving, error: saveError } = useSettings();

	const handlePull = () => {
		setPulling( true );
		setPullSuccess( false );
		setError( null );

		if ( isDirty ) {
			save();
		}

		setAwaitingPull( true );
	};

	const doPull = () => {
		apiFetch( {
			path: `/cp-sync/v1/pull/${ type }`,
			method: 'POST',
		} )
			.then( ( response ) => {
				if ( response.success ) {
					setPullSuccess( true );
				} else {
					setError( response.message );
				}
			} )
			.catch( ( err ) => {
				setError( err.message );
			} )
			.finally( () => {
				setPulling( false );
			} );
	};

	useEffect( () => {
		if ( ! awaitingPull || isSaving ) {
			return;
		}

		if ( ! isDirty ) {
			// Save finished (or nothing needed saving) — start the pull.
			setAwaitingPull( false );
			doPull();
		} else if ( saveError ) {
			// Save failed: settings on the server do not match the screen, so
			// starting the pull would import against stale settings. Bail.
			setAwaitingPull( false );
			setPulling( false );
			setError( saveError );
		}
	}, [ awaitingPull, isDirty, isSaving, saveError ] );

	return (
		<>
			<Button
				variant="primary"
				onClick={ handlePull }
				disabled={ pulling }
			>
				{ pulling
					? __( 'Starting import', 'cp-sync' )
					: __( 'Pull Now', 'cp-sync' ) }
			</Button>

			{ pullSuccess && (
				<Notice
					status="success"
					isDismissible={ false }
					className={ noticeClassName }
				>
					{ __( 'Import started', 'cp-sync' ) }
				</Notice>
			) }
			{ error && (
				<Notice
					status="error"
					isDismissible={ false }
					className={ noticeClassName }
				>
					<div>{ error }</div>
				</Notice>
			) }
		</>
	);
}
