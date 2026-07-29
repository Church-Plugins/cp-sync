/**
 * Groups/Events sync-enable toggles for the merged Connect tab.
 *
 * The two toggles ( stored under `connect.sync_groups` / `connect.sync_events` )
 * are declared in PHP as part of every ChMS's `connect` screen schema. They are
 * rendered here — once, in the merged Connect tab, for whichever platform is
 * active — rather than inside each platform's connect component, so they stay
 * editable regardless of connection state ( CCB's credential form disables itself
 * once connected, which must NOT lock the sync toggles ) and live in one place.
 *
 * When a companion plugin is inactive the served schema marks that toggle
 * `disabled: true` and swaps its help text for the "requires …" explanation;
 * <SchemaForm>'s field wrapper honours the per-field `disabled` flag.
 */

import { useSelect } from '@wordpress/data';
import globalStore from '../store/globalStore';
import { SchemaForm } from '../schema-form';

// The connect-screen field keys that carry the feed sync toggles.
export const SYNC_FIELD_KEYS = [ 'sync_groups', 'sync_events' ];

/**
 * Rebuild a connect screen schema keeping ONLY sections/fields whose key passes
 * the predicate. Sections left empty are dropped.
 *
 * @param {Object}   connectSchema The `connect` screen schema (or undefined).
 * @param {Function} keep          (fieldKey) => boolean
 * @return {Object|null} A new schema, or null when nothing survives.
 */
function filterConnectSchema( connectSchema, keep ) {
	if ( ! connectSchema || ! Array.isArray( connectSchema.sections ) ) {
		return null;
	}

	const sections = connectSchema.sections
		.map( ( section ) => {
			const fields = {};
			Object.keys( section.fields || {} ).forEach( ( key ) => {
				if ( keep( key ) ) {
					fields[ key ] = section.fields[ key ];
				}
			} );
			return { ...section, fields };
		} )
		.filter( ( section ) => Object.keys( section.fields ).length > 0 );

	return sections.length ? { ...connectSchema, sections } : null;
}

/**
 * A connect schema containing ONLY the sync toggle fields (for standalone render).
 *
 * @param {Object} connectSchema The `connect` screen schema.
 * @return {Object|null}
 */
export function pickSyncSchema( connectSchema ) {
	return filterConnectSchema( connectSchema, ( key ) =>
		SYNC_FIELD_KEYS.includes( key )
	);
}

/**
 * A connect schema with the sync toggle fields REMOVED (for a platform's own
 * credential form, so the toggles are not rendered twice).
 *
 * @param {Object} connectSchema The `connect` screen schema.
 * @return {Object|null}
 */
export function omitSyncSchema( connectSchema ) {
	// Preserve the original (possibly undefined) when there is nothing to strip so
	// callers can keep their existing "schema not yet loaded" handling.
	if ( ! connectSchema || ! Array.isArray( connectSchema.sections ) ) {
		return connectSchema;
	}
	const filtered = filterConnectSchema(
		connectSchema,
		( key ) => ! SYNC_FIELD_KEYS.includes( key )
	);
	// filterConnectSchema returns null when every field was a sync field; hand back
	// an empty-sections schema so <SchemaForm> renders nothing rather than crashing.
	return filtered || { ...connectSchema, sections: [] };
}

/**
 * Render the sync toggles for the active ChMS's connect screen.
 *
 * @param {Object}   props
 * @param {string}   props.chms        The active ChMS id.
 * @param {Object}   props.values      The `connect` settings slice.
 * @param {Function} props.updateField (fieldKey, value) => void — writes connect.<key>.
 */
export default function SyncToggles( { chms, values, updateField } ) {
	const connectSchema = useSelect(
		( select ) => select( globalStore ).getSchema( chms )?.connect,
		[ chms ]
	);

	const syncSchema = pickSyncSchema( connectSchema );

	if ( ! syncSchema ) {
		return null;
	}

	return (
		<SchemaForm
			schema={ syncSchema }
			values={ values || {} }
			onChange={ updateField }
		/>
	);
}
