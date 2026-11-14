/**
 * Status of WordPress cron as set in wp-config.php.
 */
import { Notice, PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import CronCrontabCommandBlock from './CronCrontabCommandBlock';

type CronStatus = 'disabled' | 'forced-on' | 'enabled';

/**
 * Resolve the wp-config.php cron status.
 *
 * @param state Localized constant snapshot.
 */
const getCronStatus = ( state: UpdateBeeWpCronStatus ): CronStatus => {
	if ( state.constantDefined && true === state.constantValue ) {
		return 'disabled';
	}

	if ( state.constantDefined && false === state.constantValue ) {
		return 'forced-on';
	}

	return 'enabled';
};

type CronStatusCopy = {
	noticeStatus: 'warning' | 'info' | 'success';
	summary: string;
	instructions: string;
};

/**
 * Notice and Advanced copy for the current cron status.
 *
 * @param status Resolved wp-config.php cron status.
 */
const getStatusCopy = ( status: CronStatus ): CronStatusCopy => {
	if ( 'disabled' === status ) {
		return {
			noticeStatus: 'warning',
			summary: __(
				'WordPress cron is disabled in wp-config.php or by other means because DISABLE_WP_CRON is true. Page visits will not run scheduled events and automatic updates may not work unless you have a server cron running.',
				'updatebee'
			),
			instructions: __(
				'While cron stays disabled, add a server Crontab entry about every five minutes to point to your desired endpoint. Several examples are provided below.',
				'updatebee'
			),
		};
	}

	if ( 'forced-on' === status ) {
		return {
			noticeStatus: 'info',
			summary: __(
				'WordPress Cron is on, likely via wp-config.php, because DISABLE_WP_CRON is false.',
				'updatebee'
			),
			instructions: __(
				'There\'s no need to change anything unless you want Cron to run from your server via a crontab..',
				'updatebee'
			),
		};
	}

	return {
		noticeStatus: 'success',
		summary: __(
			'WordPress cron is enabled. Page visits can run scheduled events.',
			'updatebee'
		),
		instructions: __(
			"To disable it, add define( 'DISABLE_WP_CRON', true ); to wp-config.php, then add a server cron so scheduled events still run. Each schedule card has an Advanced section with lines for that one update hook.",
			'updatebee'
		),
	};
};

/**
 * Status and instructions for DISABLE_WP_CRON under WordPress time.
 */
const WpCronStatus = () => {
	const state = updatebeeCron.wpCronStatus ?? {
		constantDefined: false,
		constantValue: null,
	};
	const status = getCronStatus( state );
	const commands = Array.isArray( updatebeeCron.systemCronCommands )
		? updatebeeCron.systemCronCommands
		: [];
	const { noticeStatus, summary, instructions } = getStatusCopy( status );

	return (
		<div className="updatebee-cron-wp-cron-status">
			<Notice status={ noticeStatus } isDismissible={ false }>
				{ summary }
			</Notice>
			<PanelBody
				title={ __( 'Advanced', 'updatebee' ) }
				initialOpen={ false }
				className="updatebee-cron-advanced"
			>
				<div className="updatebee-cron-crontab-commands">
					<p className="description updatebee-cron-crontab-note">
						{ instructions }
					</p>
					{ 'disabled' === status &&
						commands.map( ( item, index ) => (
							<CronCrontabCommandBlock
								key={ item.command }
								id={ `updatebee-wp-cron-spawn-${ index }` }
								label={ item.label }
								command={ item.command }
							/>
						) ) }
				</div>
			</PanelBody>
		</div>
	);
};

export default WpCronStatus;
