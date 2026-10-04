import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import { useSettings } from '../contexts/settingsContext';
import { SchemaForm } from '../schema-form';
import logSchema from '../schema-form/schemas/log';

/**
 * Readonly log-file viewer with a clear action.
 *
 * Custom component (fetches `/get-log`, clears via `/clear-log`) composed
 * alongside <SchemaForm>. It owns no settings.
 */
function LogViewer() {
	const [ logContent, setLogContent ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const clearLogFile = () => {
		setLoading( true );
		apiFetch( { path: '/cp-sync/v1/clear-log', method: 'POST' } )
			.then( () => {
				getLog();
			} )
			.catch( ( err ) => {
				setError( err.message );
			} );
	};

	const getLog = () => {
		apiFetch( { path: '/cp-sync/v1/get-log' } )
			.then( ( response ) => {
				setLogContent( response );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message );
				setLoading( false );
			} );
	};

	useEffect( () => {
		getLog();
	}, [] );

	return (
		<div className="cps-log-viewer">
			<Button variant="secondary" onClick={ clearLogFile }>
				{ __( 'Clear Log File', 'cp-sync' ) }
			</Button>

			<div className="cps-log-viewer__content">
				{ loading ? (
					<Spinner />
				) : error ? (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) : (
					<TextareaControl
						label={ __( 'Log File Content', 'cp-sync' ) }
						value={ logContent }
						rows={ 20 }
						readOnly
						onChange={ () => {} }
						__nextHasNoMarginBottom
					/>
				) }
			</div>
		</div>
	);
}

function LogTab() {
	const { globalSettings, updateGlobalSettings } = useSettings();

	return (
		<div className="cps-settings-screen">
			<SchemaForm
				schema={ logSchema }
				values={ globalSettings }
				onChange={ updateGlobalSettings }
			/>
			<LogViewer />
		</div>
	);
}

export const logTab = {
	name: 'Log',
	group: 'logTab',
	component: ( props ) => <LogTab { ...props } />,
};
