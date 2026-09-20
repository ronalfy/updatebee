/**
 * Dashboard settings view.
 */
import { FormProvider, useForm } from 'react-hook-form';
import { useEnableWpCompatOverlaySlot } from '@wordpress/ui';
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
			<DashboardUpdateOptions />
			<DashboardFooter />
		</FormProvider>
	);
};

export default Dashboard;
