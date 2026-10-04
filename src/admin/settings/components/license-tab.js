import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useState } from '@wordpress/element';
import { useSettings } from '../contexts/settingsContext';
import { SchemaForm } from '../schema-form';
import licenseSchema from '../schema-form/schemas/license';

// License key activate/deactivate widget.
//
// This is an ACTION widget, not a pure setting: it talks to the
// `/churchplugins/v1/license/cps_license` REST endpoint and surfaces status
// feedback. It stays a custom component (composed alongside <SchemaForm>) rather
// than becoming a schema field. It writes the `license` and `status` global
// settings keys — unchanged from the previous MUI implementation.
function LicenseActions( { save } ) {
	const [ pending, setPending ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ success, setSuccess ] = useState( false );
	const { globalSettings, updateGlobalSettings } = useSettings();

	const { license, status } = globalSettings;

	const activateLicense = () => {
		setSuccess( null );
		setError( null );
		setPending( true );
		apiFetch( {
			path: '/churchplugins/v1/license/cps_license',
			method: 'POST',
			data: { license: globalSettings.license },
		} )
			.then( ( data ) => {
				updateGlobalSettings( 'status', data.status );
				setSuccess( data.message );
				setError( null );
				save();
			} )
			.catch( ( e ) => {
				setError( e.message );
			} )
			.finally( () => {
				setPending( false );
			} );
	};

	const deactivateLicense = () => {
		setSuccess( null );
		setError( null );
		setPending( true );
		apiFetch( {
			path: '/churchplugins/v1/license/cps_license',
			method: 'DELETE',
		} )
			.then( ( data ) => {
				updateGlobalSettings( 'status', data.status );
				setSuccess( data.message );
				setError( null );
				save();
			} )
			.catch( ( e ) => {
				setError( e.message );
			} )
			.finally( () => {
				setPending( false );
			} );
	};

	return (
		<div className="cps-license-actions">
			{ !! error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ success && (
				<Notice status="success" isDismissible={ false }>
					{ success }
				</Notice>
			) }

			<div className="cps-license-actions__row">
				<TextControl
					label={ __( 'License Key', 'cp-sync' ) }
					value={ license || '' }
					onChange={ ( value ) =>
						updateGlobalSettings( 'license', value )
					}
					disabled={ status === 'valid' }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<Button
					variant="secondary"
					disabled={ pending }
					onClick={
						status === 'valid' ? deactivateLicense : activateLicense
					}
				>
					{ pending && <Spinner /> }
					{ pending
						? __( 'Processing', 'cp-sync' )
						: status === 'valid'
						? __( 'Deactivate', 'cp-sync' )
						: __( 'Activate', 'cp-sync' ) }
				</Button>
			</div>
		</div>
	);
}

function LicenseTab( { save } ) {
	const { globalSettings, updateGlobalSettings } = useSettings();

	return (
		<div className="cps-settings-screen">
			<LicenseActions save={ save } />
			<SchemaForm
				schema={ licenseSchema }
				values={ globalSettings }
				onChange={ updateGlobalSettings }
			/>
		</div>
	);
}

export const licenseTab = {
	name: 'License',
	group: 'license',
	component: ( props ) => <LicenseTab { ...props } />,
};
