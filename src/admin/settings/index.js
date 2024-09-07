import { createRoot, useState, useEffect, useRef } from '@wordpress/element';
import './index.scss';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import Tabs from '@mui/material/Tabs';
import Tab from '@mui/material/Tab';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import Skeleton from '@mui/material/Skeleton';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import platforms from './platforms';
import { __ } from '@wordpress/i18n';
import { chmsTab } from './components/chms-tab';
import { licenseTab } from './components/license-tab';
import { logTab } from './components/log-tab';
import SettingsProvider, { useSettings } from './contexts/settingsContext';
import '@fontsource/roboto/300.css';
import '@fontsource/roboto/400.css';
import '@fontsource/roboto/500.css';
import '@fontsource/roboto/700.css';
import SettingsProvider, { useSettings } from './contexts/settingsContext';

const theme = createTheme({
	palette: {
		mode: "light"
	},
})

function DynamicTab({ tab, value, index }) {
	const { group, defaultData = {}, component } = tab

	const { settings, save, updateField, isDirty, isHydrating } = useSettings()

	useEffect(() => {
		if(isDirty) {
			const handleBeforeUnload = (e) => {
				e.preventDefault()
				return false
			}

			window.addEventListener('beforeunload', handleBeforeUnload)

			return () => {
				window.removeEventListener('beforeunload', handleBeforeUnload)
			}
		}
	}, [isDirty])

	return (
		<TabPanel value={value} index={index}>
			<Box>
				{
					isHydrating &&
					<>
						<Skeleton variant="text" width={500} />
						<Skeleton variant="text" width={200} />
						<Skeleton variant="text" width={250} />
						<Skeleton variant="text" width={300} height={40} />
						<Skeleton variant="text" width={300} height={40} />
						<Skeleton variant="text" width={300} height={40} />
					</>
				}
				{
					!isHydrating &&
					component({
						data: { ...defaultData, ...settings[group] },
						updateField: (field, value) => updateField(group, field, value),
						save,
					})
				}
			</Box>
		</TabPanel>
	)
}

function TabPanel(props) {
	const { children, value, index, ...other } = props;

	return (
		<div
			role="tabpanel"
			hidden={value !== index}
			id={`simple-tabpanel-${index}`}
			aria-labelledby={`simple-tab-${index}`}
			{...other}
			style={{ height: '100%' }}
		>
			{value === index && (
				<Card sx={{ p: 4, overflowY: 'auto', maxHeight: '100%', boxSizing: 'border-box' }} variant="outlined">
					<Typography component="div">{children}</Typography>
				</Card>
			)}
		</div>
	);
}

function Settings() {
	const { globalSettings, save, isSaving, isDirty, error, isConnected } = useSettings()

	const chmsData = platforms[globalSettings.chms] || { tabs: [] }
	const tabs     = chmsData.tabs.filter(tab => isConnected ? true : tab.group === 'connect')

	// creates a list of tabs based on the selected ChMS
	const tabNames = [
		'select',
		...tabs.map(tab => tab.group),
		'license'
	]

	const openTab = (index) => {
		const url = new URL(window.location.href)
		url.searchParams.set('tab', tabNames[index])
		window.history.pushState({}, '', url)
		setCurrentTab(index)
	}

	const getTabIndex = () => {
		const url = new URL(window.location.href)
		const tab = url.searchParams.get('tab')

		if (tab) {
			const tabIndex = tabNames.indexOf(tab)
			if (tabIndex !== -1) {
				return tabIndex
			}
		}

		return 0
	}

	const [currentTab, setCurrentTab] = useState(getTabIndex)

	useEffect(() => {
		if(isConnected) {
			setCurrentTab(getTabIndex())
		}
	}, [isConnected])

	return (
		<ThemeProvider theme={theme}>
			<Box sx={{ height: '100%', p: 2, maxHeight: '100%', display: 'flex', flexDirection: 'column', gap: 0 }}>
				<h1>CP Sync</h1>
				<Tabs value={currentTab} onChange={(_, value) => openTab(value)} sx={{ px: 2, mb: '-2px', mt: 4 }}>
					<Tab label={__( 'Select a ChMS', 'cp-sync' )} />
					{
						tabs.map((tab) => (
							<Tab key={tab.group} label={tab.name} />
						))
					}
					<Tab label={__( 'Log', 'cp-sync' )} key={logTab.group} />
					<Tab label={__( 'License', 'cp-sync' )} key={licenseTab.group} />
				</Tabs>
				<Box sx={{ flexGrow: 1, minHeight: 0 }}>
					<DynamicTab tab={chmsTab} value={currentTab} index={0} key={chmsTab.group} />
					{
						tabs.map((tab, index) => (
							<DynamicTab
								key={tab.group}
								tab={tab}
								value={currentTab}
								index={index + 1}
							/>
						))
					}
					<DynamicTab tab={logTab} value={currentTab} index={tabs.length + 1} key={logTab.group} />
					<DynamicTab tab={licenseTab} value={currentTab} index={tabs.length + 2} key={licenseTab.group} />
				</Box>
				{
					error &&
					<Alert severity="error">{error}</Alert>
				}
				<Button
					sx={{ mt: 4, alignSelf: 'flex-start' }}
					variant="contained"
					onClick={save}
					disabled={isSaving || !isDirty}
				>{ isSaving ? __( 'Saving...', 'cp-sync' ) : __( 'Save all Settings', 'cp-sync' ) }</Button>
			</Box>
		</ThemeProvider>
	)
}

document.addEventListener('DOMContentLoaded', function () {
	const root = document.querySelector('.cp_settings_root.cp-sync')

	const globalSettings = JSON.parse(root.dataset.settings) // get the initial data from the root element
	const globalData     = JSON.parse(root.dataset.entrypoint) // get global data from the backend

	if (root) {
		createRoot(root).render(
			<SettingsProvider globalSettings={globalSettings} globalData={globalData}>
				<Settings />
			</SettingsProvider>
		)
	}
})