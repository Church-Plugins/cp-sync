import { createRoot, useState, useEffect, useRef } from '@wordpress/element';
import './index.scss';
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
import { useSelect, useDispatch } from '@wordpress/data';
import optionsStore from './store';
import { chmsTab } from './chms-tab';
import { licenseTab } from './license-tab';

import '@fontsource/roboto/300.css';
import '@fontsource/roboto/400.css';
import '@fontsource/roboto/500.css';
import '@fontsource/roboto/700.css';

const theme = createTheme({
	palette: {
		mode: "light"
	},
})

function DynamicTab({ tab, prefix, globalData, value, index, onChange }) {
	const { optionGroup, defaultData, component } = tab
	const isDirtyRef = useRef(false)

	const prefixedOptionGroup = prefix ? `${prefix}_${optionGroup}` : optionGroup

	const { data, isSaving, error, isDirty, isHydrating } = useSelect((select) => {
		return {
			data: select(optionsStore).getOptionGroup(prefixedOptionGroup),
			isSaving: select(optionsStore).isSaving(),
			error: select(optionsStore).getError(),
			isDirty: select(optionsStore).isDirty(prefixedOptionGroup),
			isHydrating: select(optionsStore).isResolving( 'getOptionGroup', [ prefixedOptionGroup ] )
		}
	}, [prefixedOptionGroup])

	const { persistOptionGroup, setOptionGroup } = useDispatch(optionsStore)

	const updateField = (field, value) => {
		const newValue = { ...data, [field]: value }
		onChange(prefixedOptionGroup, newValue)
		setOptionGroup(prefixedOptionGroup, newValue)
	}

	const save = () => {
		persistOptionGroup(prefixedOptionGroup, data)
	}

	useEffect(() => {
		isDirtyRef.current = isDirty
	}, [isDirty])

	const handleBeforeUnload = (e) => {
		if (isDirtyRef.current) {
			e.preventDefault()
			return false;
		}
	}

	useEffect(() => {
		window.addEventListener('beforeunload', handleBeforeUnload)

		return () => {
			window.removeEventListener('beforeunload', handleBeforeUnload)
		}
	}, [])

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
						data: { ...defaultData, ...data },
						updateField,
						save,
						isSaving,
						error,
						isDirty,
						isHydrating,
						globalData
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
          <Typography>{children}</Typography>
        </Card>
      )}
    </div>
  );
}

function Settings({ globalData }) {
	const { chms = globalData.chms } = useSelect((select) => {
		return {
			chms: select(optionsStore).getOptionGroup('main_options')?.chms,
		}
	})
	const [unsavedChanges, setUnsavedChanges] = useState({})
	const isSaving = useSelect((select) => select(optionsStore).isSaving())

	const { persistOptionGroup } = useDispatch(optionsStore)

	const addUnsavedChange = (key, value) => {
		setUnsavedChanges((prev) => ({ ...prev, [key]: value }))
	}

	const save = () => {
		Object.entries(unsavedChanges).forEach(([key, value]) => {
			persistOptionGroup(key, value)
		})
		setUnsavedChanges({})
	}

	const chmsData = platforms[chms] || { tabs: [] }

	// creates a list of tabs based on the selected ChMS
	const tabsNames = [
		'select',
		...chmsData.tabs.map((tab) => tab.optionGroup),
		'license'
	]

	const openTab = (index) => {
		const url = new URL(window.location.href)
		url.searchParams.set('tab', tabsNames[index])
		window.history.pushState({}, '', url)
		setCurrentTab(index)
	}

	const [currentTab, setCurrentTab] = useState(() => {
		const url = new URL(window.location.href)
		const tab = url.searchParams.get('tab')

		if (tab) {
			const tabIndex = tabsNames.indexOf(tab)
			if (tabIndex !== -1) {
				return tabIndex
			}
		}

		return 0
	})

	return (
		<ThemeProvider theme={theme}>
			<Box sx={{ height: '100%', p: 2, maxHeight: '100%', display: 'flex', flexDirection: 'column', gap: 0 }}>
				<h1>CP Sync</h1>
				<Tabs value={currentTab} onChange={(_, value) => openTab(value)} sx={{ px: 2, mb: '-2px', mt: 4 }}>
					<Tab label={__( 'Select a ChMS', 'cp-sync' )} />
					{
						chmsData.tabs.map((tab) => (
							<Tab key={tab.optionGroup} label={tab.name} />
						))
					}
					<Tab label={__( 'License', 'cp-sync' )} />
				</Tabs>
				<Box sx={{ flexGrow: 1, minHeight: 0 }}>
					<DynamicTab tab={chmsTab} globalData={globalData} value={currentTab} index={0} onChange={addUnsavedChange} />
					{
						chmsData.tabs.map((tab, index) => (
							<DynamicTab
								key={tab.optionGroup}
								tab={tab}
								prefix={chms}
								globalData={globalData}
								value={currentTab}
								index={index + 1}
								onChange={addUnsavedChange}
							/>
						))
					}
					<DynamicTab tab={licenseTab} globalData={globalData} value={currentTab} index={chmsData.tabs.length + 1} onChange={addUnsavedChange} />
				</Box>
				<Button
					sx={{ mt: 4, alignSelf: 'flex-start' }}
					variant="contained"
					onClick={save}
					disabled={isSaving || !Object.keys(unsavedChanges).length}
				>{ isSaving ? __( 'Saving...', 'cp-sync' ) : __( 'Save', 'cp-sync' ) }</Button>
			</Box>
		</ThemeProvider>
	)
}

document.addEventListener('DOMContentLoaded', function () {
	const root = document.querySelector('.cp_settings_root.cp-sync')

	const globalData = JSON.parse(root.dataset.initial) // get the initial data from the root element

	if (root) {
		createRoot(root).render(
			<Settings globalData={globalData} />
		)
	}
})
