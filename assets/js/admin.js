
jQuery($ => {

	const debounce = (cb, delay) => {
		let timeout
		return (...args) => {
			clearTimeout(timeout)
			timeout = setTimeout(() => cb(...args), delay)
		}
	}	

	$('.cp-sync-field-select').each(buildMultiSelect)

	function buildMultiSelect() {
		const optionsContainer = $(this).find('.cp-sync-field-select__options')
		const addFieldInput    = $(this).find('.cp-sync-field-select__add-input')
		const addFieldButton   = $(this).find('.cp-sync-field-select__add-button')
		const hiddenField      = $(this).find('input[type="hidden"]')
		const fieldsPreview    = $(this).find('.cp-sync-fields-preview')
		const defaultFields    = $(this).data('default-fields')

		let options = JSON.parse(hiddenField.val() || '[]')

		const updateValue = debounce(() => {
			options = optionsContainer.find('.cp-sync-field-select-item-value').map((i, e) => $(e).val()).toArray()
			hiddenField.val(JSON.stringify(options))
			fieldsPreview.html([...defaultFields, ...options].join(', ')) // update field preview
		}, 300)

		// prevent submitting form on enter
		addFieldInput.on( 'keypress', e => {
			if ( e.keyCode === 13 ) {
				e.preventDefault()
				addFieldButton.click()
			}
		} )

		addFieldButton.on('click', e => {
			const value = addFieldInput.val()
			if ( value ) {
				options.push(value)
				updateList()
				updateValue()
				addFieldInput.val('')
			}
		})

		setTimeout(() => {
			updateList()
			updateValue()
		}, 10)

		function updateList() {
			const listItemTemplate = `<li class="cp-sync-field-select-item">
				<input class="cp-sync-field-select-item-value regular-text" type="text" value="{value}" />
				<button class="cp-sync-field-select-item-remove button button-secondary"><i class="material-icons">delete</i></button>
			</li>`;

			optionsContainer.html('')

			options.forEach(value => {
				const elem = $(listItemTemplate.replaceAll('{value}', value))

				elem.find('.cp-sync-field-select-item-value').on('keypress', (e) => {
					if(e.keyCode === 13) {
						e.preventDefault()
					} else {
						updateValue()
					}
				})

				elem.find('.cp-sync-field-select-item-remove').on('click', () => {
					elem.remove()
					updateValue()
				})

				optionsContainer.append(elem)
			})
		}
	}

	$('.cps-custom-mapping').each(buildCustomMapping)

	function buildCustomMapping() {
		const optionTemplate = $(this).find('.cp-sync-custom-mapping-template')
		const initialMapping = $(this).data('mapping')
		const objectType     = $(this).data('object-type')
		const itemContainer  = $(this).find('.cps-custom-mapping--rows')
		const addItemBtn     = $(this).find('.cps-custom-mapping--add')
		const hiddenField    = $(this).find('input[type="hidden"]')
		
		let currentMapping  = initialMapping

		const updateValue = debounce(() => {
			const newState = {}
			itemContainer.find('.cps-custom-mapping--row').each(function() {
				const metaKey   = $(this).find('.cps-custom-mapping--meta-key').val()
				const fieldName = $(this).find('.cps-custom-mapping--field-name').val()
				newState[fieldName] = metaKey
			})
			currentMapping = newState
			hiddenField.val(JSON.stringify(currentMapping))
		}, 300)

		const addRow = (fieldName = '', metaKey = '', ) => {
			const row = $(optionTemplate.clone().html())
			
			const metaKeyField   = row.find('.cps-custom-mapping--meta-key')
			const fieldNameField = row.find('.cps-custom-mapping--field-name')
			const removeBtn      = row.find('.cps-custom-mapping--remove')

			metaKeyField.val(metaKey)
			fieldNameField.val(fieldName)

			metaKeyField.on('keypress', (e) => {
				if(e.keyCode === 13) {
					e.preventDefault()
				} else {
					updateValue()
				}
			})

			fieldNameField.on('change', (e) => {
				updateValue()
			})

			removeBtn.on('click', (e) => {
				e.preventDefault()
				row.remove()
				updateValue()
			})

			itemContainer.append(row)
		}

		addItemBtn.off('click').on('click', () => {
			addRow()
		})

		if( initialMapping && Object.keys(initialMapping).length > 0 ) {
			Object.entries(initialMapping).forEach(([fieldName, metaKey]) => addRow(fieldName, metaKey))
		}
	}
})
