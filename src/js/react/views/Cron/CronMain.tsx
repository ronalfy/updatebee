/**
 * Cron tab: three independent update check schedules.
 */
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, useEnableWpCompatOverlaySlot } from '@wordpress/ui';
import CronFooter from './CronFooter';
import CronScheduleCard from './CronScheduleCard';
import CronTimeDisplay from './CronTimeDisplay';
import type { SnackbarState, SnackbarType } from '../Tools/ToolsFooter';

/**
 * Cron settings screen.
 */
const CronMain = () => {
	useEnableWpCompatOverlaySlot();

	const [ snackbar, setSnackbar ] = useState< SnackbarState >( {
		isVisible: false,
		message: '',
		type: 'success',
	} );

	const onNotify = useCallback(
		( message: string, type: SnackbarType ) => {
			setSnackbar( {
				isVisible: true,
				message,
				type,
			} );
		},
		[]
	);

	const onSnackbarClose = useCallback( () => {
		setSnackbar( ( current ) => ( {
			...current,
			isVisible: false,
		} ) );
	}, [] );

	return (
		<div className="updatebee-cron-main">
			<Card.Root className="updatebee-cron-card updatebee-cron-intro-card">
				<Card.Header>
					<Card.Title>
						{ __( 'Update cron schedules', 'updatebee' ) }
					</Card.Title>
					<p className="description">
						{ __(
							'Modify when update cron schedules are run. This is useful if you would like updates to run during off-peak hours.',
							'updatebee'
						) }
					</p>
				</Card.Header>
				<Card.Content>
					<h2 className="updatebee-cron-time-heading">
						{ __( 'WordPress time', 'updatebee' ) }
					</h2>
					<CronTimeDisplay />
				</Card.Content>
			</Card.Root>
			<CronScheduleCard
				slug="plugin_updates"
				title={ __( 'Plugin updates', 'updatebee' ) }
				description={ __(
					'Schedule when WordPress checks for plugin updates.',
					'updatebee'
				) }
				onNotify={ onNotify }
			/>
			<CronScheduleCard
				slug="theme_updates"
				title={ __( 'Theme updates', 'updatebee' ) }
				description={ __(
					'Schedule when WordPress checks for theme updates.',
					'updatebee'
				) }
				onNotify={ onNotify }
			/>
			<CronScheduleCard
				slug="core_updates"
				title={ __( 'Core updates', 'updatebee' ) }
				description={ __(
					'Schedule when WordPress checks for core updates.',
					'updatebee'
				) }
				onNotify={ onNotify }
			/>
			<CronFooter
				snackbar={ snackbar }
				onSnackbarClose={ onSnackbarClose }
			/>
		</div>
	);
};

export default CronMain;
