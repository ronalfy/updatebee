/**
 * Cron tab: Schedule and Cron Events sub-views.
 */
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Tabs, useEnableWpCompatOverlaySlot } from '@wordpress/ui';
import CronEventsMain from './CronEventsMain';
import CronFooter from './CronFooter';
import CronScheduleTab from './CronScheduleTab';
import type { SnackbarState, SnackbarType } from '../Tools/ToolsFooter';

type CronSubtab = 'schedule' | 'events';

const resolveInitialSubtab = (): CronSubtab =>
	'events' === updatebeeCron.initialSubtab ? 'events' : 'schedule';

/**
 * Cron settings screen with tabs.
 */
const CronMain = () => {
	useEnableWpCompatOverlaySlot();

	const [ activeTab, setActiveTab ] =
		useState< CronSubtab >( resolveInitialSubtab );
	const [ snackbar, setSnackbar ] = useState< SnackbarState >( {
		isVisible: false,
		message: '',
		type: 'success',
	} );

	const onNotify = useCallback( ( message: string, type: SnackbarType ) => {
		setSnackbar( {
			isVisible: true,
			message,
			type,
		} );
	}, [] );

	const onSnackbarClose = useCallback( () => {
		setSnackbar( ( current ) => ( {
			...current,
			isVisible: false,
		} ) );
	}, [] );

	const handleTabChange = useCallback( ( value: string ) => {
		const next = 'events' === value ? 'events' : 'schedule';
		setActiveTab( next );

		try {
			const url = new URL(
				updatebeeCron.settingsUrl,
				window.location.href
			);
			url.searchParams.set( 'subtab', next );
			window.history.replaceState( null, '', url.toString() );
		} catch {
			// Ignore invalid settings URL.
		}
	}, [] );

	return (
		<div className="updatebee-cron-main">
			<Tabs.Root value={ activeTab } onValueChange={ handleTabChange }>
				<Tabs.List className="updatebee-cron-tabs-list">
					<Tabs.Tab value="schedule">
						{ __( 'Schedule', 'updatebee' ) }
					</Tabs.Tab>
					<Tabs.Tab value="events">
						{ __( 'Cron Events', 'updatebee' ) }
					</Tabs.Tab>
				</Tabs.List>
				<Tabs.Panel value="schedule">
					<CronScheduleTab onNotify={ onNotify } />
				</Tabs.Panel>
				<Tabs.Panel value="events">
					<CronEventsMain
						onNotify={ onNotify }
						snackbar={ snackbar }
						onSnackbarClose={ onSnackbarClose }
					/>
				</Tabs.Panel>
			</Tabs.Root>
			{ 'schedule' === activeTab ? (
				<CronFooter
					snackbar={ snackbar }
					onSnackbarClose={ onSnackbarClose }
				/>
			) : null }
		</div>
	);
};

export default CronMain;
