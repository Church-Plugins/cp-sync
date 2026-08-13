import { createContext, useContext, useEffect, useState } from 'react'
import globalStore from '../store/globalStore'
import { useDispatch, useSelect, select as selectStore } from '@wordpress/data'
import LoadingSkeleton from '../components/loading-skeleton'

const SettingsContext = createContext({
	chms: null,
	error: null,
	isConnected: false,
	isSaving: false,
	isDirty: false,
	settings: {},
	updateField: () => {},
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
	// One-way "ready" latch: once the initial connection check has resolved, the
	// app stays mounted. Without this, the disconnect flow (which invalidates the
	// getIsConnected resolution to force a re-check) would unmount the entire SPA
	// for the duration of that round-trip.
	const [isReady, setIsReady] = useState(false)

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

	useEffect(() => {
		if (seeded && isConnectionLoaded) {
			setIsReady(true)
		}
	}, [seeded, isConnectionLoaded])

	// Persist the global slice and the active ChMS slice through the store's single
	// save action. Request bodies match the legacy per-ChMS/global POSTs exactly.
	//
	// Values are read FRESH from the store registry at call time — NOT from this
	// render's `globalSettings`/`settings` — because action widgets dispatch an
	// update and call save() in the same tick (e.g. the license tab writing the
	// new `status` then saving). The render-snapshot closure would persist the
	// PRE-dispatch state and silently overwrite the change just made.
	const save = () => {
		const store = selectStore(globalStore)
		const global = store.getGlobalSettings() || {}
		const chms = global.chms

		saveSettings(global, chms, chms ? (store.getSettings(chms) || {}) : {})
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

	/**
	 * Gets the filter config for a filter group, e.g. 'groups' or 'events'
	 * @param {*} filterGroup
	 */
	const getFilterConfig = (filterGroup) => {
		return filterConfig[filterGroup] || false
	}

	// NOTE: this context is a convenience wrapper for the common cases (flat field
	// reads/updates + save). For anything beyond that — multi-step flows like
	// "save then check connection", schema/options selectors, resolution
	// invalidation — use the `cp-sync/global-settings` store directly via
	// useSelect/useDispatch, as the connect tabs do. It is not a complete facade.
	const value = {
		chms: globalSettings.chms,
		error,
		isConnected,
		isSaving,
		isDirty,
		settings,
		updateField,
		getFilterConfig,
		save,
		globalSettings,
		updateGlobalSettings,
		compareOptions,
	}

	return (
		<SettingsContext.Provider value={value}>
			{isReady ? children : <LoadingSkeleton />}
		</SettingsContext.Provider>
	)
}
