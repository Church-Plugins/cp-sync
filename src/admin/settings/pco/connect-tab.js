import { __ } from '@wordpress/i18n'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Typography from '@mui/material/Typography'
import TextField from '@mui/material/TextField'
import Step from '@mui/material/Step'
import StepLabel from '@mui/material/StepLabel'
import StepButton from '@mui/material/StepButton'
import StepContent from '@mui/material/StepContent'
import Stepper from '@mui/material/Stepper'
import { useState, useEffect } from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'
import CircularProgress from '@mui/material/CircularProgress'

export default function ConnectTab({ data, updateField }) {
	const { app_id, secret, step: activeStep, authorized } = data 
	const [authLoading, setAuthLoading] = useState(false)
	const [authError, setAuthError] = useState(null)
	const [isDirty, setIsDirty] = useState(false)

	const isComplete = activeStep === 2;

	const updateAuthField = (e) => {
		if(!isDirty) setIsDirty(true)
		updateField(e.target.name, e.target.value)
	}

	const authorize = async () => {
		setAuthLoading(true)
		apiFetch({
			path: '/cp-sync/v1/pco/authenticate',
			method: 'POST',
			data: { app_id, secret }
		}).then((response) => {
			updateField('authorized', true)
			updateField('step', activeStep + 1)
			setIsDirty(false)
		}).catch(e => {
			setAuthError(e.message)
		}).finally(() => {
			setAuthLoading(false)
		})
	}

	useEffect(() => {
		setIsDirty(true)
	}, [activeStep])

	const steps = [
		{
			label: __( 'Get your API keys from PCO', 'cp-sync' ),
			description: <p>
				{ __( 'Follow the instructions from PCO to create an OAuth application. You can also create a personal access token.', 'cp-sync' ) }

				<br />

				{ __( 'You can get your authentication credentials by ', 'cp-sync' ) }
	
				<a href="https://api.planningcenteronline.com/oauth/applications" target="_blank" rel="noreferrer noopener">
					{ __( 'clicking here' )}
				</a>
	
				{ __( ' and scrolling down to "Personal Access Tokens"', 'cp-sync' ) }
			</p>
		},
		{
			label: __( 'Connect to PCO', 'cp-sync' ),
			description: <p>
				{ __( 'Enter the Application ID and Secret from the previous step into the fields below.', 'cp-sync' ) }
				<div style={{ marginTop: '1rem' }}>
					<TextField
						name='app_id'
						sx={{ width: '400px' }}
						label={__( 'Application ID', 'cp-sync' )}
						value={app_id}
						onChange={updateAuthField}
						variant="outlined"
					/>
				</div>
				<div style={{ marginTop: '1rem' }}>
					<TextField
						name='secret'
						sx={{ width: '400px' }}
						label={__( 'Application Secret', 'cp-sync' )}
						value={secret}
						onChange={updateAuthField}
						variant="outlined"
					/>
				</div>
			</p>
		}
	]	

	return (
		<div>
			<Typography variant="h5">{ __( 'PCO API Configuration', 'cp-sync' ) }</Typography>

			<Stepper activeStep={activeStep} orientation='vertical' sx={{ mt: 2 }}>
				{
					steps.map((step, index) => (
						<Step key={index} completed={activeStep > index}>
							<StepButton onClick={() => updateField('step', index)}>
								{step.label}
							</StepButton>
							<StepContent>
								{
									!isComplete &&
									<>
										{step.description}
										{
											authError &&
											<Alert severity="error" sx={{ mt: 2 }}>{authError}</Alert>
										}
										<div style={{ marginTop: '1rem' }}>
											{
												index === steps.length - 1 &&
												<Button
													variant="contained"
													color="primary"
													onClick={authorize}
													disabled={authLoading || !isDirty || !app_id || !secret}
													sx={{ display: 'inline-flex', gap: 2 }}
												>
													{
														authLoading &&
														<CircularProgress size={20} color="info" />
													}
													{ __( 'Connect', 'cp-sync' ) }
												</Button>
											}
											{
												index < steps.length - 1 &&
												<Button variant="text" color="primary" onClick={() => updateField('step', index + 1)}>
													{ __( 'Next', 'cp-sync' ) }
												</Button>
											}
											{
												index > 0 &&
												<Button variant="text" color="primary" sx={{ ml: 1 }} onClick={() => updateField('step', index - 1)}>
													{ __( 'Back', 'cp-sync' ) }
												</Button>
											}
										</div>
									</>
								}
							</StepContent>
						</Step>
					))
				}
			</Stepper>
			{
				isComplete &&
				<Alert severity="success" sx={{ mt: 2 }}>{ __( 'Connected', 'cp-sync' ) }</Alert>
			}
		</div>
	)
}

