import { useSelect } from '@wordpress/data';
import fetchStore from '../fetchStore';

/**
 * React hook for fetching the necessary data for a condition
 *
 * @param {Object} filter
 * @param {Object?} filter.options - An array of options to use directly instead of fetching
 * @param {Function?} filter.optionsSelector - A selector to fetch options with from the store
 * @param {Object} currentPreFilters 
 * @param {*} currentPreFilters 
 * @returns {FilterData}
 * @since 1.0.0
 */
const useFilters = (filter, currentPreFilters) => {
	const { options, loading } = useSelect((select) => {
		if(!filter) {
			return { options: false, loading: false }
		}

		if(filter.options) { // if options are provided directly, use them
			return { options: filter.options, loading: false }
		}

		if(filter.optionsFetcher) {
			return {
				options: select(fetchStore).getData(filter.optionsFetcher.endpoint) || [],
				loading: select(fetchStore).getIsResolving('getData', [filter.optionsFetcher.endpoint])
			}
		}

		return { options: false, loading: false }
	}, [currentPreFilters])

	return { options, loading, type: filter?.type || 'none' }
}

export default useFilters;
