/**
 * Dashboard settings view.
 */
import { useCallback, useState } from '@wordpress/element';
import { FormProvider, useForm } from 'react-hook-form';
import { __ } from '@wordpress/i18n';
import { Tabs, useEnableWpCompatOverlaySlot } from '@wordpress/ui';
import ErrorBoundary from '../../components/ErrorBoundary';
import DashboardUpdateOptions, {
	dashboardSettingsDefaults,
	type DashboardSettingsForm,
} from './DashboardUpdateOptions';
import DashboardFooter from './DashboardFooter';
import UpcomingUpdates from './UpcomingUpdates';

type SettingsSubtab = 'settings' | 'upcoming';

const resolveInitialSubtab = (): SettingsSubtab =>
	'upcoming' === updatebeeDashboard.initialSubtab ? 'upcoming' : 'settings';

/**
 * Settings screen with automatic-update and upcoming subtabs.
 */
const Dashboard = () => {
	const methods = useForm< DashboardSettingsForm >( {
		defaultValues: dashboardSettingsDefaults,
	} );
	useEnableWpCompatOverlaySlot();
	const [ activeTab, setActiveTab ] =
		useState< SettingsSubtab >( resolveInitialSubtab );

	const handleTabChange = useCallback( ( value: string ) => {
		const next = 'upcoming' === value ? 'upcoming' : 'settings';
		setActiveTab( next );

		try {
			const url = new URL(
				updatebeeDashboard.settingsUrl,
				window.location.href
			);
			url.searchParams.set( 'subtab', next );
			window.history.replaceState( null, '', url.toString() );
		} catch {
			// Ignore an invalid settings URL.
		}
	}, [] );

	return (
		<FormProvider { ...methods }>
			<div className="updatebee-dashboard-main">
				<Tabs.Root
					value={ activeTab }
					onValueChange={ handleTabChange }
				>
					<Tabs.List className="updatebee-dashboard-tabs-list">
						<Tabs.Tab value="settings">
							{ __( 'Automatic updates', 'updatebee' ) }
						</Tabs.Tab>
						<Tabs.Tab value="upcoming">
							{ __( 'Upcoming', 'updatebee' ) }
						</Tabs.Tab>
					</Tabs.List>
					<Tabs.Panel value="settings">
						<ErrorBoundary
							heading={ __( 'Automatic Updates', 'updatebee' ) }
							message={ __(
								'These settings could not be displayed. Try again or reload the page.',
								'updatebee'
							) }
						>
							<DashboardUpdateOptions />
						</ErrorBoundary>
					</Tabs.Panel>
					<Tabs.Panel value="upcoming">
						<UpcomingUpdates />
					</Tabs.Panel>
				</Tabs.Root>
				{ 'settings' === activeTab ? <DashboardFooter /> : null }
			</div>
		</FormProvider>
	);
};

export default Dashboard;
