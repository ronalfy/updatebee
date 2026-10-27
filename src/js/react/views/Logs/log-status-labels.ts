/**
 * Labels for log update_status values.
 */
import { __ } from '@wordpress/i18n';

export type LogUpdateStatus =
	| 'success'
	| 'failure'
	| 'blocked'
	| 'skipped'
	| 'delayed';

type LogUpdateStatusOption = {
	value: LogUpdateStatus;
	label: string;
};

/**
 * Filter options for log update status.
 */
export const LOG_UPDATE_STATUS_OPTIONS: LogUpdateStatusOption[] = [
	{
		value: 'success',
		label: __( 'Success', 'updatebee' ),
	},
	{
		value: 'failure',
		label: __( 'Failure', 'updatebee' ),
	},
	{
		value: 'blocked',
		label: __( 'Blocked', 'updatebee' ),
	},
	{
		value: 'skipped',
		label: __( 'Skipped', 'updatebee' ),
	},
	{
		value: 'delayed',
		label: __( 'Delayed', 'updatebee' ),
	},
];

const statusLabelMap = new Map< string, string >(
	LOG_UPDATE_STATUS_OPTIONS.map( ( option ) => [
		option.value,
		option.label,
	] )
);

/**
 * Human-readable label for a stored update_status slug.
 *
 * @param status Raw status from the API.
 * @return Localized label or a title-cased fallback.
 */
export const getLogUpdateStatusLabel = ( status: string ): string => {
	if ( ! status ) {
		return '';
	}

	const known = statusLabelMap.get( status );
	if ( known ) {
		return known;
	}

	return status.charAt( 0 ).toUpperCase() + status.slice( 1 );
};
