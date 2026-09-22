/**
 * DataViews row actions for cron events.
 */
import type { Action } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { play, trash } from '@wordpress/icons';
import type { CronEventRow } from './cron-events-types';

type GetCronEventActionsArgs = {
	onRun: ( item: CronEventRow ) => void;
	onDelete: ( item: CronEventRow ) => void;
};

/**
 * Build run and delete actions for cron event rows.
 *
 * @param args Action callbacks.
 */
export const getCronEventActions = (
	args: GetCronEventActionsArgs
): Action< CronEventRow >[] => {
	const { onRun, onDelete } = args;

	return [
		{
			id: 'run-cron-event',
			label: __( 'Run now', 'updatebee' ),
			icon: play,
			supportsBulk: false,
			isEligible: ( item ) => item.canRun,
			callback: ( items ) => {
				const item = items[ 0 ];
				if ( item ) {
					onRun( item );
				}
			},
		},
		{
			id: 'delete-cron-event',
			label: __( 'Delete', 'updatebee' ),
			icon: trash,
			supportsBulk: false,
			isEligible: ( item ) => item.canDelete,
			callback: ( items ) => {
				const item = items[ 0 ];
				if ( item ) {
					onDelete( item );
				}
			},
		},
	];
};
