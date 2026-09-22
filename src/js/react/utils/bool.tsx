/**
 * Boolean utilities.
 */
/**
 * Convert a form boolean to an on/off option value.
 *
 * @param value Form boolean.
 * @return Option value.
 */
const boolToOption = ( value: boolean ): 'on' | 'off' => {
	return value ? 'on' : 'off';
};

/**
 * Convert an on/off option value to a form boolean.
 *
 * @param value Option value.
 * @return Form boolean.
 */
const optionToBool = ( value?: string ): boolean => {
	return 'on' === value;
};

export { boolToOption, optionToBool };
