/**
 * Validate an email address string.
 *
 * @param email Email address.
 * @return True when the address looks valid.
 */
const validateEmail = ( email: string ): boolean => {
	const regex =
		/^(([^<>()[\].,;:\s@"]+(\.[^<>()[\].,;:\s@"]+)*)|(".+"))@(([^<>()[\].,;:\s@"]+\.)+[^<>()[\].,;:\s@"]{2,})$/i;

	return regex.test( email );
};

export default validateEmail;
