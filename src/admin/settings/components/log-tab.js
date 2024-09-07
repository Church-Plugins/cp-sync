import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import TextField from '@mui/material/TextField';
import CircularProgress from '@mui/material/CircularProgress';
import Alert from '@mui/material/Alert';
import FormControl from '@mui/material/FormControl';
import FormLabel from '@mui/material/FormLabel';
import FormControlLabel from '@mui/material/FormControlLabel';
import RadioGroup from '@mui/material/RadioGroup';
import Radio from '@mui/material/Radio';
import { useSettings } from '../contexts/settingsContext'
import Button from '@mui/material/Button';

function LogTab({ data, updateField }) {
  const [logContent, setLogContent] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const { globalSettings, updateGlobalSettings, globalUnsavedChanges } = useSettings()
  const { debugMode } = globalSettings

  const clearLogFile = () => {
    setLoading( true );
    apiFetch({ path: '/cp-sync/v1/clear-log', method: 'POST' })
      .then(() => {
        getLog();
      })
      .catch((err) => {
        setError(err.message);
      });
  }

  const getLog = () => {
    apiFetch({ path: '/cp-sync/v1/get-log' })
      .then((response) => {
        setLogContent(response);
        setLoading(false);
      })
      .catch((err) => {
        setError(err.message);
        setLoading(false);
      });
  }

  useEffect(() => {
    getLog();
  }, []);

  return (
    <div>
      <FormControl component="fieldset" sx={{ marginTop: 2, marginBottom: 2 }}>
        <FormLabel component="legend">{__('Enable Debug Mode', 'cp-sync')}</FormLabel>
        <RadioGroup
          row
          aria-label="debugMode"
          value={debugMode}
          onChange={(e) => updateGlobalSettings('debugMode', e.target.value)}
        >
          <FormControlLabel value="1" control={<Radio />} label={__('Enable', 'cp-sync')} />
          <FormControlLabel value="0" control={<Radio />} label={__('Disable', 'cp-sync')} />
        </RadioGroup>

        <Button sx={{marginTop: 2, marginBottom: 2}} variant="outlined" onClick={clearLogFile}>{ __( 'Clear Log File', 'cp-sync' ) }</Button>
      </FormControl>

      <div>
        {loading ? (
          <CircularProgress />
        ) : error ? (
          <Alert severity="error">{error}</Alert>
        ) : (
          <>
            <TextField
              label={__('Log File Content', 'cp-sync')}
              multiline
              rows={20}
              value={logContent}
              variant="outlined"
              fullWidth
              InputProps={{
                readOnly: true,
              }}
            />
          </>
        )}
      </div>
    </div>
  );
}

export const logTab = {
  name: 'Log',
  group: 'logTab',
  component: (props) => <LogTab {...props} />,
}