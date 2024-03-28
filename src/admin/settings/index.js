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

function DynamicTab({ tab, prefix, globalData }) {
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
		setOptionGroup(prefixedOptionGroup, {
			...data,
			[field]: value
		})
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
			<Button
				sx={{ mt: 4 }}
				variant="contained"
				onClick={save}
				disabled={isSaving || !isDirty}
			>{ __( 'Save', 'cp-connect' ) }</Button>
		</Box>
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
				<h1>CP Connect</h1>
				<Tabs value={currentTab} onChange={(_, value) => openTab(value)} sx={{ px: 2, mb: '-2px', mt: 4 }}>
					<Tab label={__( 'Select a ChMS', 'cp-connect' )} />
					{
						chmsData.tabs.map((tab) => (
							<Tab key={tab.optionGroup} label={tab.name} />
						))
					}
					<Tab label={__( 'License', 'cp-connect' )} />
				</Tabs>
				<Box sx={{ flexGrow: 1, minHeight: 0 }}>
					<TabPanel value={currentTab} index={0}>
						<DynamicTab tab={chmsTab} globalData={globalData} />
					</TabPanel>
					{
						chmsData.tabs.map((tab, index) => (
							<TabPanel key={tab.optionGroup} value={currentTab} index={index + 1}>
								<DynamicTab tab={tab} prefix={chms} globalData={globalData} />
							</TabPanel>
						))
					}
					<TabPanel value={currentTab} index={chmsData.tabs.length + 1}>
						<DynamicTab tab={licenseTab} globalData={globalData} />
					</TabPanel>
				</Box>
			</Box>
		</ThemeProvider>
	)
}

document.addEventListener('DOMContentLoaded', function () {
	const root = document.querySelector('.cp_settings_root.cp-connect')

	const globalData = JSON.parse(root.dataset.initial) // get the initial data from the root element

	if (root) {
		createRoot(root).render(
			<Settings globalData={globalData} />
		)
	}
})
