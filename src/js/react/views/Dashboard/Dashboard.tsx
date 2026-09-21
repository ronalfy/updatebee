/**
 * Dashboard settings view.
 */
import { FormProvider, useForm } from 'react-hook-form';
import { __ } from '@wordpress/i18n';
import { useEnableWpCompatOverlaySlot } from '@wordpress/ui';
import ErrorBoundary from '../../components/ErrorBoundary';
import DashboardUpdateOptions, {
	dashboardSettingsDefaults,
	type DashboardSettingsForm,
} from './DashboardUpdateOptions';
import DashboardFooter from './DashboardFooter';

const Dashboard = () => {
	const methods = useForm< DashboardSettingsForm >( {
		defaultValues: dashboardSettingsDefaults,
	} );
	useEnableWpCompatOverlaySlot();

	return (
		<FormProvider { ...methods }>
			<ErrorBoundary
				heading={ __( 'Automatic Updates', 'updatebee' ) }
				message={ __(
					'These settings could not be displayed. Try again or reload the page.',
					'updatebee'
				) }
			>
				<DashboardUpdateOptions />
			</ErrorBoundary>
			<DashboardFooter />
		</FormProvider>
	);
};

export default Dashboard;
