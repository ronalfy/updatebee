/**
 * DataViews row actions for upcoming updates.
 */
import type { Action } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { lock, scheduled, unlock, update } from '@wordpress/icons';
import type { UpcomingItem } from './UpcomingUpdates';

type GetUpcomingActionsArgs = {
	onSkip: ( item: UpcomingItem ) => void;
	onBlock: ( item: UpcomingItem ) => void;
	onUpdate: ( item: UpcomingItem ) => void;
};

/**
 * Build skip, block, and update actions for an upcoming row.
 *
 * @param args Action callbacks.
 */
export const getUpcomingActions = (
	args: GetUpcomingActionsArgs
): Action< UpcomingItem >[] => {
	const { onSkip, onBlock, onUpdate } = args;

	return [
		{
			id: 'skip-version',
			label: __( 'Skip this version', 'updatebee' ),
			icon: scheduled,
			supportsBulk: false,
			isEligible: ( item ) => ! item.Skipped,
			callback: ( items ) => {
				const item = items[ 0 ];
				if ( item ) {
					onSkip( item );
				}
			},
		},
		{
			id: 'schedule-version',
			label: __( 'Schedule update', 'updatebee' ),
			icon: scheduled,
			supportsBulk: false,
			isEligible: ( item ) => item.Skipped,
			callback: ( items ) => {
				const item = items[ 0 ];
				if ( item ) {
					onSkip( item );
				}
			},
		},
		{
			id: 'block-updates',
			label: __( 'Block updates', 'updatebee' ),
			icon: lock,
			supportsBulk: false,
			isEligible: ( item ) => ! item.FullyBlocked && ! item.Blocked,
			callback: ( items ) => {
				const item = items[ 0 ];
				if ( item ) {
					onBlock( item );
				}
			},
		},
		{
			id: 'unblock-updates',
			label: __( 'Unblock updates', 'updatebee' ),
			icon: unlock,
			supportsBulk: false,
			isEligible: ( item ) => ! item.FullyBlocked && item.Blocked,
			callback: ( items ) => {
				const item = items[ 0 ];
				if ( item ) {
					onBlock( item );
				}
			},
		},
		{
			id: 'update-now',
			label: __( 'Update now', 'updatebee' ),
			icon: update,
			supportsBulk: false,
			isEligible: ( item ) => item.CanUpdate,
			callback: ( items ) => {
				const item = items[ 0 ];
				if ( item ) {
					onUpdate( item );
				}
			},
		},
	];
};
