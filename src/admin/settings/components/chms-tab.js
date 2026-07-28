import { __ } from '@wordpress/i18n';
import { SelectControl, Notice } from '@wordpress/components';
import platforms from '../platforms';
import { useSettings } from '../contexts/settingsContext';

function ChMSTab() {
	const { globalSettings, updateGlobalSettings } = useSettings();

	const options = [
		{ label: __( 'Select', 'cp-sync' ), value: '' },
		...Object.keys( platforms ).map( ( key ) => ( {
			label: platforms[ key ].name,
			value: key,
		} ) ),
	];

	return (
		<div className="cps-chms-tab">
			<SelectControl
				className="cps-chms-select"
				label={ __( 'ChMS', 'cp-sync' ) }
				value={ globalSettings.chms }
				options={ options }
				onChange={ ( value ) => updateGlobalSettings( 'chms', value ) }
				__nextHasNoMarginBottom
			/>
			<Notice
				status="info"
				isDismissible={ false }
				className="cps-chms-notice"
			>
				{ __( 'More platforms coming soon!', 'cp-sync' ) }
			</Notice>
		</div>
	);
}

export const chmsTab = {
	name: __( 'ChMS', 'cp-sync' ),
	component: ( props ) => <ChMSTab { ...props } />,
};
