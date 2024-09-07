import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import { useEffect, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import { useSettings } from "../contexts/settingsContext";
import CircularProgress from '@mui/material/CircularProgress';
import React from 'react';

/**
 * Displays a preview of the PCO settings.
 *
 * @param {Object} props
 * @param {string} props.type the type of data being previewed.
 * @returns 
 */
export default function Preview({ type }) {
	const [previewData, setPreviewData] = useState(null);
	const [totalCount, setTotalCount] = useState(null);
	const [loading, setLoading] = useState(false);
	const [awaitingPreview, setAwaitingPreview] = useState(false);
	const { save, isDirty } = useSettings()

	const generatePreview = async () => {
		setLoading(true);

		if(	isDirty ) {
			save()
		}

		setAwaitingPreview(true);
	}

	const doFetch = async () => {
		const data = await apiFetch({
			path: `/cp-sync/v1/pco/preview/${type}`,
			method: 'GET',
		})

		setPreviewData(data.items);
		setTotalCount(data.count);
		setLoading(false)
	}

	useEffect(() => {
		if(!isDirty && awaitingPreview) {
			doFetch()
			setAwaitingPreview(false)
			console.log('preview awaited')
		}
	}, [awaitingPreview, isDirty])

	return (
		<Box sx={{background: '#eee', position: 'relative', height: '100%'}}>
			<Button variant="outlined" onClick={generatePreview} disabled={loading}>{ __( 'Generate Preview', 'cp-sync' ) }</Button>

			<Box sx={{overflow: 'scroll', height: 'calc(100% - 5rem)', position: 'absolute', bottom: '0'}}>

				{loading &&
				 <CircularProgress/>
				}

				{
					previewData &&
					previewData.map((item) => (
						<Box key={item.chmsID}
						     sx={{display: 'flex', alignItems: 'center', borderBottom: '1px solid #ddd', padding: '1rem 0'}}
						     gap={2}>
							{
								item.thumbnail &&
								<img src={item.thumbnail} alt={item.title} style={{maxWidth: '100px'}}/>
							}
							<Box sx={{fontSize: '.75em'}}>
								<h3 style={{marginTop: '0'}}>{item.title}</h3>
								<div dangerouslySetInnerHTML={{ __html: item.content }}></div>
								{
									Object.entries(item.meta).map(([key, value]) => (
										<p key={key}><strong>{key}:</strong> {value}</p>
									))
								}
							</Box>
						</Box>
					))
				}
			</Box>

			{
				0 === totalCount ?
				<p>{ __( 'No items found.', 'cp-sync' ) }</p> :
				totalCount > 0 ?
				<p>{ __( 'Total items: ', 'cp-sync' ) }{totalCount}</p> :
				null
			}
		</Box>
	)
}
