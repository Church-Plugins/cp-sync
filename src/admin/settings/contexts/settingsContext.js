import { createContext, useContext, useEffect, useState } from 'react'
import globalStore from '../store/globalStore'
import { useDispatch, useSelect } from '@wordpress/data'

const SettingsContext = createContext({
	chms: null,
	debugMode: null,
	setChms: () => {},
	isConnected: false,
	isSaving: false,
	isDirty: false,
	settings: {},
	updateSettings: () => {},
	updateField: () => {},
	getField: () => {},
	getFilterConfig: (filterGroup) => {},
	save: () => {},
	globalSettings: {},
	updateGlobalSettings: () => {},
	compareOptions: {},
})

const defaultGlobalSettings = {
	chms: 'pco',
	debugMode: 0,
	license: '',
	beta: false,
	status: '',
}

export const useSettings = () => {
	const context = useContext(SettingsContext)
	if (!context) {
		throw new Error('useSettings must be used within a SettingsProvider')
	}
	return context
}

/**
 * Thin wrapper over the single `cp-sync/global-settings` store.
 *
 * This provider no longer holds any settings state of its own — everything
 * (global + per-ChMS values, dirty/saving/error, connection, filters) lives in
 * the store. It just seeds the store's global slice from the localized page data
 * and re-exposes the same `useSettings()` API the tab components already consume.
 */
export default function SettingsProvider({ globalSettings: initialGlobalSettings, children, compareOptions }) {
	const [seeded, setSeeded] = useState(false)

	const {
		setGlobalSettings,
		setGlobalField,
		setSettings,
		persistGlobalSettings,
		save: saveSettings,
	} = useDispatch(globalStore)

	// Seed the store's global slice once from the localized data. Everything that
	// reads `globalSettings.chms` (connection resolver, per-ChMS settings) keys off
	// this, so children are gated until it has run.
	useEffect(() => {
		setGlobalSettings({ ...defaultGlobalSettings, ...initialGlobalSettings })
		setSeeded(true)
	}, [])

	const {
		globalSettings,
		settings,
		isConnected,
		isConnectionLoaded,
		isSaving,
		isDirty,
		error,
		filterConfig,
	} = useSelect((select) => {
		const store = select(globalStore)
		const global = store.getGlobalSettings() || {}
		const chms = global.chms

		return {
			globalSettings: global,
			settings: chms ? (store.getSettings(chms) || {}) : {},
			isConnected: chms ? store.getIsConnected(chms) : false,
			isConnectionLoaded: chms ? store.hasFinishedResolution('getIsConnected', [chms]) : false,
			isSaving: store.getIsSaving(),
			isDirty: store.getIsDirty(),
			error: store.getError(),
			filterConfig: chms ? (store.getFilters(chms) || {}) : {},
		}
	}, [seeded])

	// Persist the global slice and the active ChMS slice through the store's single
	// save action. Request bodies match the legacy per-ChMS/global POSTs exactly.
	const save = () => {
		saveSettings(globalSettings, globalSettings.chms, settings)
	}

	// Kept for API-surface compatibility; delegates to the store.
	const saveGlobal = (data = false) => {
		persistGlobalSettings(data || globalSettings)
	}

	const updateGlobalSettings = (field, value) => {
		// Update the store's global slice. Switching ChMS re-persists immediately
		// via a single deliberate save (the old double-save — an inline saveGlobal
		// plus a redundant useEffect on chms change — is gone).
		if (field === 'chms' && value !== globalSettings.chms) {
			setGlobalField(field, value)
			persistGlobalSettings({ ...globalSettings, [field]: value })
		} else {
			setGlobalField(field, value)
		}
	}

	const updateSettings = (newSettings) => {
		setSettings(globalSettings.chms, {
			...settings,
			...newSettings
		})
	}

	const updateField = (group, field, value) => {
		updateSettings({
			[group]: {
				...settings[group],
				[field]: value
			}
		})
	}

	const getField = (group, field) => {
		// Guarded: return undefined instead of throwing when the group is absent.
		return settings?.[group]?.[field]
	}

	/**
	 * Gets the filter config for a filter group, e.g. 'groups' or 'events'
	 * @param {*} filterGroup
	 */
	const getFilterConfig = (filterGroup) => {
		return filterConfig[filterGroup] || false
	}

	const value = {
		chms: globalSettings.chms,
		error,
		isConnected,
		isSaving,
		isDirty,
		settings,
		updateSettings,
		updateField,
		getField,
		getFilterConfig,
		save,
		saveGlobal,
		globalUnsavedChanges: isDirty,
		globalSettings,
		updateGlobalSettings,
		compareOptions,
	}

	return (
		<SettingsContext.Provider value={value}>
			{seeded && isConnectionLoaded && children}
		</SettingsContext.Provider>
	)
}
