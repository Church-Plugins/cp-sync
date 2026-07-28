import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Spinner } from '@wordpress/components';
import { useSettings } from '../contexts/settingsContext';

/**
 * Truncate and strip HTML from content
 *
 * @param {string} html - HTML content to clean
 * @param {number} maxLength - Maximum length
 * @returns {string} - Cleaned text
 */
const cleanContent = ( html, maxLength = 200 ) => {
	if ( ! html ) return '';

	// Strip HTML tags using DOM Parser (safer than innerHTML)
	const doc = new DOMParser().parseFromString( html, 'text/html' );
	const text = doc.body.textContent || '';

	// Truncate
	if ( text.length > maxLength ) {
		return text.substring( 0, maxLength ).trim() + '...';
	}

	return text.trim();
};

/**
 * Displays a preview of the PCO settings.
 *
 * @param {Object} props
 * @param {string} props.type the type of data being previewed.
 * @returns
 */
export default function Preview( { type } ) {
	const [ previewData, setPreviewData ] = useState( null );
	const [ totalCount, setTotalCount ] = useState( null );
	const [ dateRange, setDateRange ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ awaitingPreview, setAwaitingPreview ] = useState( false );
	const { save, isDirty, chms } = useSettings();

	const generatePreview = async () => {
		setLoading( true );

		if ( isDirty ) {
			save();
		}

		setAwaitingPreview( true );
	};

	const doFetch = async () => {
		const data = await apiFetch( {
			path: `/cp-sync/v1/${ chms }/preview/${ type }`,
			method: 'GET',
		} );

		setPreviewData( data.items );
		setTotalCount( data.count );

		// Store date range if available (for events)
		if ( data.date_start && data.date_end ) {
			setDateRange( {
				start: data.date_start,
				end: data.date_end,
				mode: data.date_mode,
			} );
		}

		setLoading( false );
	};

	useEffect( () => {
		if ( ! isDirty && awaitingPreview ) {
			doFetch();
			setAwaitingPreview( false );
		}
	}, [ awaitingPreview, isDirty ] );

	return (
		<div
			className="cps-preview"
			style={ {
				background: '#eee',
				position: 'relative',
				height: '100%',
				padding: '1rem',
			} }
		>
			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					marginBottom: '1rem',
				} }
			>
				<Button
					variant="secondary"
					onClick={ generatePreview }
					disabled={ loading }
				>
					{ __( 'Generate Preview', 'cp-sync' ) }
				</Button>

				{ totalCount !== null && totalCount > 0 && (
					<div
						style={ {
							fontSize: '0.85em',
							color: '#666',
							fontStyle: 'italic',
						} }
					>
						{ totalCount < 10
							? `Showing all ${ totalCount } ${
									totalCount === 1 ? 'item' : 'items'
							  }`
							: 'Showing the first 10 items' }
					</div>
				) }
			</div>

			{ dateRange && (
				<div
					style={ {
						fontSize: '0.85em',
						color: '#555',
						padding: '0.5rem',
						backgroundColor: '#f0f0f0',
						borderRadius: '4px',
						marginBottom: '1rem',
						border: '1px solid #ddd',
					} }
				>
					<strong>{ __( 'Date Range:', 'cp-sync' ) }</strong>{ ' ' }
					{ dateRange.start } to { dateRange.end }
				</div>
			) }

			{ 0 === totalCount && (
				<div
					style={ {
						padding: '2rem',
						textAlign: 'center',
						backgroundColor: '#fff',
						borderRadius: '4px',
					} }
				>
					<p style={ { margin: 0, color: '#666' } }>
						{ __(
							'No items found matching your filters.',
							'cp-sync'
						) }
					</p>
				</div>
			) }

			<div
				style={ {
					overflowY: 'auto',
					maxHeight: 'calc(100% - 5rem)',
					minHeight: 100,
					position: 'relative',
					marginTop: '0.5rem',
				} }
			>
				{ loading && (
					<div style={ { position: 'absolute', top: 0, left: 0 } }>
						<Spinner />
					</div>
				) }

				{ previewData &&
					previewData.map( ( item ) => (
						<div
							key={ item.chmsID }
							style={ {
								display: 'flex',
								alignItems: 'flex-start',
								gap: '1rem',
								borderBottom: '1px solid #ddd',
								padding: '1rem',
								backgroundColor: '#fff',
								marginBottom: '0.5rem',
								borderRadius: '4px',
								boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
							} }
						>
							{ item.thumbnail && (
								<img
									src={ item.thumbnail }
									alt={ item.title }
									style={ {
										maxWidth: '80px',
										maxHeight: '80px',
										objectFit: 'cover',
										borderRadius: '4px',
									} }
								/>
							) }
							<div style={ { flex: 1, fontSize: '.85em' } }>
								<h3
									style={ {
										marginTop: '0',
										marginBottom: '0.5rem',
										fontSize: '1.1em',
									} }
								>
									{ item.title }
								</h3>

								{ item.content && (
									<p
										style={ {
											color: '#666',
											marginBottom: '0.75rem',
											lineHeight: '1.4',
										} }
									>
										{ cleanContent( item.content, 200 ) }
									</p>
								) }

								{ /* Display key fields in a grid */ }
								{ item.fields &&
									Object.keys( item.fields ).length > 0 && (
										<div
											style={ {
												display: 'grid',
												gridTemplateColumns:
													'repeat(auto-fit, minmax(150px, 1fr))',
												gap: '0.5rem',
												marginBottom: '0.75rem',
												padding: '0.75rem',
												backgroundColor: '#f8f9fa',
												borderRadius: '4px',
											} }
										>
											{ Object.entries( item.fields ).map(
												( [ key, value ] ) => (
													<div
														key={ key }
														style={ {
															fontSize: '0.9em',
														} }
													>
														<strong
															style={ {
																color: '#555',
															} }
														>
															{ key }:
														</strong>
														<span
															style={ {
																marginLeft:
																	'0.25rem',
																color: '#333',
															} }
														>
															{ value }
														</span>
													</div>
												)
											) }
										</div>
									) }

								{ /* Display taxonomies */ }
								{ item.meta &&
									Object.keys( item.meta ).length > 0 && (
										<div
											style={ {
												display: 'flex',
												flexWrap: 'wrap',
												gap: '0.5rem',
												fontSize: '0.85em',
											} }
										>
											{ Object.entries( item.meta ).map(
												( [ key, values ] ) => (
													<div
														key={ key }
														style={ {
															display: 'flex',
															gap: '0.25rem',
															alignItems:
																'center',
														} }
													>
														<span
															style={ {
																color: '#666',
																fontSize:
																	'0.9em',
																textTransform:
																	'uppercase',
																letterSpacing:
																	'0.5px',
															} }
														>
															{ key }:
														</span>
														{ Array.isArray(
															values
														) ? (
															values.map(
																(
																	value,
																	idx
																) => (
																	<span
																		key={
																			idx
																		}
																		style={ {
																			backgroundColor:
																				'#e3f2fd',
																			padding:
																				'2px 8px',
																			borderRadius:
																				'12px',
																			color: '#1976d2',
																			fontSize:
																				'0.9em',
																		} }
																	>
																		{
																			value
																		}
																	</span>
																)
															)
														) : (
															<span
																style={ {
																	backgroundColor:
																		'#e3f2fd',
																	padding:
																		'2px 8px',
																	borderRadius:
																		'12px',
																	color: '#1976d2',
																	fontSize:
																		'0.9em',
																} }
															>
																{ values }
															</span>
														) }
													</div>
												)
											) }
										</div>
									) }
							</div>
						</div>
					) ) }
			</div>
		</div>
	);
}
