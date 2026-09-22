/**
 * Schedule sub-tab: intro, time, and three update cron cards.
 */
import { __ } from '@wordpress/i18n';
import { Card } from '@wordpress/ui';
import CronScheduleCard from './CronScheduleCard';
import CronTimeDisplay from './CronTimeDisplay';
import type { SnackbarType } from '../Tools/ToolsFooter';

type CronScheduleTabProps = {
	onNotify: ( message: string, type: SnackbarType ) => void;
};

/**
 * Update cron schedule configuration cards.
 *
 * @param props Snackbar callback.
 */
const CronScheduleTab = ( props: CronScheduleTabProps ) => {
	const { onNotify } = props;

	return (
		<div className="updatebee-cron-schedule-tab">
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
		</div>
	);
};

export default CronScheduleTab;
