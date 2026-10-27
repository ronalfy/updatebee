/**
 * DataViews actions for log rows.
 */
import type { Action } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { info } from '@wordpress/icons';
import type { LogItem } from './LogsMain';

type GetLogActionsArgs = {
	onViewDetails: ( item: LogItem ) => void;
};

/**
 * Build DataViews row actions for logs.
 *
 * @param args Action factory args.
 */
export const getLogActions = (
	args: GetLogActionsArgs
): Action< LogItem >[] => {
	const { onViewDetails } = args;

	return [
		{
			id: 'view-details',
			label: __( 'View details', 'updatebee' ),
			icon: info,
			supportsBulk: false,
			isEligible: ( item ) => item.HasNotes || item.HasStacktrace,
			callback: ( items ) => {
				const item = items[ 0 ];
				if ( item ) {
					onViewDetails( item );
				}
			},
		},
	];
};
