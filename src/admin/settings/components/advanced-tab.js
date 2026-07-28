import { Button, Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { useSettings } from '../contexts/settingsContext';
import { SchemaForm } from '../schema-form';
import advancedSchema, { advancedUninstallSchema } from '../schema-form/schemas/advanced';
import DangerZone from './danger-zone';

// Manual hard-pull action button.
//
// ACTION widget (kicks a REST import), kept custom alongside <SchemaForm>. The
// `updateInterval` select is the pure setting and lives in the schema.
function HardPullButton( { save } ) {
	const [ isStartingPull, setIsStartingPull ] = useState( false );
	const [ success, setSuccess ] = useState( false );
	const [ error, setError ] = useState( false );
	const { isDirty } = useSettings();

	const handleHardPull = async () => {
		setIsStartingPull( true );

		if ( isDirty ) {
			await save();
		}

		apiFetch( {
			path: '/cp-sync/v1/pull',
			method: 'POST',
		} )
			.then( ( res ) => {
				if ( res.success ) {
					setSuccess( true );
					setError( false );
				}
			} )
			.catch( ( err ) => {
				console.error( err );
				setError( err.message );
			} )
			.finally( () => {
				setIsStartingPull( false );
			} );
	};

	return (
		<div className="cps-advanced-actions">
			{ success && (
				<Notice status="success" isDismissible={ false }>
					{ __( 'Hard pull started successfully', 'cp-sync' ) }
				</Notice>
			) }
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<Button
				variant="primary"
				onClick={ handleHardPull }
				disabled={ isStartingPull }
			>
				{ isStartingPull && <Spinner /> }
				{ isStartingPull
					? __( 'Starting hard pull', 'cp-sync' )
					: __( 'Pull now', 'cp-sync' ) }
			</Button>
		</div>
	);
}

// Render the advanced tab. `save` is the global save handler.
function AdvancedTab( { save } ) {
	const { globalSettings, updateGlobalSettings } = useSettings();

	return (
		<div className="cps-settings-screen">
			<SchemaForm
				schema={ advancedSchema }
				values={ globalSettings }
				onChange={ updateGlobalSettings }
			/>
			<HardPullButton save={ save } />
			<SchemaForm
				schema={ advancedUninstallSchema }
				values={ globalSettings }
				onChange={ updateGlobalSettings }
			/>
			<DangerZone />
		</div>
	);
}

export const advancedTab = {
	name: 'Advanced',
	group: 'advanced',
	component: ( props ) => <AdvancedTab { ...props } />,
};
