import { useSelect } from '@wordpress/data';
import globalStore from '../../store/globalStore';

/**
 * React hook for fetching the necessary data for a condition
 *
 * @param {Object} filter
 * @param {Object?} filter.options - An array of options to use directly instead of fetching
 * @param {Function?} filter.optionsSelector - A selector to fetch options with from the store
 * @param {Object} currentPreFilters 
 * @param {*} currentPreFilters 
 * @returns {FilterData}
 */
const useFilters = (filter, currentPreFilters) => {
	const { options, loading } = useSelect((select) => {
		if(!filter) {
			return { options: [], loading: false }
		}

		if(filter.options) { // if options are provided directly, use them
			return { options: filter.options, loading: false }
		}

		if(filter.optionsFetcher) {
			return {
				options: select(globalStore).getOptions(filter.optionsFetcher.endpoint) || [],
				loading: select(globalStore).getResolutionState('getOptions', [filter.optionsFetcher.endpoint])?.status === 'resolving'
			}
		}

		return { options: [], loading: false }
	}, [currentPreFilters])

	return { options, loading, type: filter?.type || 'none' }
}

export default useFilters;
