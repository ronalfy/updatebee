/**
 * DataViews actions for translation locales.
 */
import type { Action } from '@wordpress/dataviews';
import { _n, __, sprintf } from '@wordpress/i18n';
import {
	lock as LockIcon,
	unlock as UnlockIcon,
	update as UpdateIcon,
	cancelCircleFilled as CancelCircleFilledIcon,
} from '@wordpress/icons';
import type { AutoUpdatesStatus, TranslationItem } from './TranslationsMain';

export type TranslationActionId = 'enable' | 'disable' | 'block' | 'unblock';

type GetTranslationActionsArgs = {
	autoUpdatesStatus: AutoUpdatesStatus;
	isBusy: boolean;
	onAction: (
		actionId: TranslationActionId,
		items: TranslationItem[]
	) => void;
};

/**
 * Success copy after a translation action.
 *
 * @param actionId Action id.
 * @param items    Affected locales.
 */
export const getTranslationActionMessage = (
	actionId: TranslationActionId,
	items: TranslationItem[]
): string => {
	const count = items.length;
	const name = items[ 0 ]?.EnglishName || '';

	switch ( actionId ) {
		case 'enable':
			if ( 1 === count ) {
				return sprintf(
					/* translators: %s: Translation name. */
					__( 'Automatic updates enabled for %s.', 'updatebee' ),
					name
				);
			}

			return sprintf(
				/* translators: %d: Number of translations. */
				_n(
					'Automatic updates enabled for %d translation.',
					'Automatic updates enabled for %d translations.',
					count,
					'updatebee'
				),
				count
			);
		case 'disable':
			if ( 1 === count ) {
				return sprintf(
					/* translators: %s: Translation name. */
					__( 'Automatic updates disabled for %s.', 'updatebee' ),
					name
				);
			}

			return sprintf(
				/* translators: %d: Number of translations. */
				_n(
					'Automatic updates disabled for %d translation.',
					'Automatic updates disabled for %d translations.',
					count,
					'updatebee'
				),
				count
			);
		case 'block':
			if ( 1 === count ) {
				return sprintf(
					/* translators: %s: Translation name. */
					__( 'Updates blocked for %s.', 'updatebee' ),
					name
				);
			}

			return sprintf(
				/* translators: %d: Number of translations. */
				_n(
					'Updates blocked for %d translation.',
					'Updates blocked for %d translations.',
					count,
					'updatebee'
				),
				count
			);
		case 'unblock':
			if ( 1 === count ) {
				return sprintf(
					/* translators: %s: Translation name. */
					__( 'Updates unblocked for %s.', 'updatebee' ),
					name
				);
			}

			return sprintf(
				/* translators: %d: Number of translations. */
				_n(
					'Updates unblocked for %d translation.',
					'Updates unblocked for %d translations.',
					count,
					'updatebee'
				),
				count
			);
		default:
			return __( 'Translation updates have been updated.', 'updatebee' );
	}
};

/**
 * Build DataViews row and bulk actions for translations.
 *
 * @param args Action factory args.
 */
export const getTranslationActions = (
	args: GetTranslationActionsArgs
): Action< TranslationItem >[] => {
	const { autoUpdatesStatus, isBusy, onAction } = args;
	const isCustom = 'custom' === autoUpdatesStatus;
	const isGloballyBlocked = 'blocked' === autoUpdatesStatus;

	return [
		{
			id: 'enable',
			label: __( 'Enable automatic updates', 'updatebee' ),
			icon: UpdateIcon,
			supportsBulk: true,
			disabled: isBusy,
			isEligible: ( item ) =>
				isCustom && ! item.Blocked && ! item.Autoupdates,
			callback: ( items ) => {
				onAction( 'enable', items );
			},
		},
		{
			id: 'disable',
			label: __( 'Disable automatic updates', 'updatebee' ),
			icon: CancelCircleFilledIcon,
			supportsBulk: true,
			disabled: isBusy,
			isEligible: ( item ) =>
				isCustom && ! item.Blocked && item.Autoupdates,
			callback: ( items ) => {
				onAction( 'disable', items );
			},
		},
		{
			id: 'block',
			label: __( 'Block updates', 'updatebee' ),
			icon: LockIcon,
			supportsBulk: true,
			disabled: isBusy,
			isEligible: ( item ) => ! isGloballyBlocked && ! item.Blocked,
			callback: ( items ) => {
				onAction( 'block', items );
			},
		},
		{
			id: 'unblock',
			label: __( 'Unblock updates', 'updatebee' ),
			icon: UnlockIcon,
			supportsBulk: true,
			disabled: isBusy,
			isEligible: ( item ) => ! isGloballyBlocked && item.Blocked,
			callback: ( items ) => {
				onAction( 'unblock', items );
			},
		},
	];
};
