/**
 * Dashboard automatic-update toggles.
 */
import { Button, Spinner, ToggleControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Controller, useFormContext } from 'react-hook-form';
import sendCommand from '../../../utils/SendCommand';
import { optionToBool } from '../../utils/bool';

export type DashboardSettingsForm = {
	majorUpdates: boolean;
	minorUpdates: boolean;
	pluginUpdates: boolean;
	themeUpdates: boolean;
	translationUpdates: boolean;
};

export const dashboardSettingsDefaults: DashboardSettingsForm = {
	majorUpdates: false,
	minorUpdates: true,
	pluginUpdates: false,
	themeUpdates: false,
	translationUpdates: true,
};

type DashboardOptionsPayload = {
	major_updates?: string;
	minor_updates?: string;
	plugin_updates?: string;
	theme_updates?: string;
	translation_updates?: string;
};

const DashboardUpdateOptions = () => {
	const { control, reset, setValue } =
		useFormContext<DashboardSettingsForm>();
	const [loading, setLoading] = useState(true);

	useEffect(() => {
		sendCommand<DashboardOptionsPayload>(
			'updatebee_get_dashboard_updates',
			{
				nonce: updatebeeDashboard.retrieveNonce,
			}
		)
			.then((response) => {
				const data = response.data.data;
				reset({
					majorUpdates: optionToBool(data.major_updates),
					minorUpdates: optionToBool(data.minor_updates),
					pluginUpdates: optionToBool(data.plugin_updates),
					themeUpdates: optionToBool(data.theme_updates),
					translationUpdates: optionToBool(
						data.translation_updates
					),
				});
			})
			.finally(() => {
				setLoading(false);
			});
	}, [reset]);

	const setAll = (enabled: boolean) => {
		setValue('majorUpdates', enabled, { shouldDirty: true });
		setValue('minorUpdates', enabled, { shouldDirty: true });
		setValue('pluginUpdates', enabled, { shouldDirty: true });
		setValue('themeUpdates', enabled, { shouldDirty: true });
		setValue('translationUpdates', enabled, { shouldDirty: true });
	};

	if (loading) {
		return (
			<div className="updatebee-admin-panel-area is-loading">
				<Spinner />
				<p>{__('Loading…', 'updatebee')}</p>
			</div>
		);
	}

	return (
		<div className="updatebee-admin-panel-area updatebee-dashboard-automatic-updates">
			<h3 className="updatebee-panel-heading">
				{__('Automatic Updates', 'updatebee')}
			</h3>
			<div className="updatebee-panel-row">
				<p className="description">
					{__(
						'Here are some quick options to allow you to enable or disable automatic updates for certain parts of WordPress.',
						'updatebee'
					)}
				</p>
			</div>
			<div className="updatebee-panel-row">
				<div className="updatebee-link-row">
					<ul>
						<li>
							<Button
								variant="link"
								onClick={() => {
									setAll(true);
								}}
							>
								{__('Enable All', 'updatebee')}
							</Button>
						</li>
						<li>
							<Button
								variant="link"
								onClick={() => {
									setAll(false);
								}}
							>
								{__('Disable All', 'updatebee')}
							</Button>
						</li>
					</ul>
				</div>
			</div>
			<div className="updatebee-panel-row-dashboard-options">
				<div className="updatebee-panel-row">
					<h4>{__('Major WordPress Updates', 'updatebee')}</h4>
					<p className="description">
						{__(
							'Major updates are when you go from 5.5 to 5.6. These are major point releases and are well tested and vetted before release.',
							'updatebee'
						)}
					</p>
					<div className="updatebee-dashboard-update-toggle">
						<Controller
							name="majorUpdates"
							control={control}
							render={({ field: { value, onChange } }) => (
								<ToggleControl
									checked={value}
									onChange={onChange}
									label={__(
										'Enable major core updates',
										'updatebee'
									)}
								/>
							)}
						/>
					</div>
				</div>
				<div className="updatebee-panel-row">
					<h4>{__('Minor WordPress Updates', 'updatebee')}</h4>
					<p className="description">
						{__(
							'Minor updates are when you go from 5.5.1 to 5.5.2. These are minor point releases and are usually security and maintenance patches. They are rarely unstable.',
							'updatebee'
						)}
					</p>
					<div className="updatebee-dashboard-update-toggle">
						<Controller
							name="minorUpdates"
							control={control}
							render={({ field: { value, onChange } }) => (
								<ToggleControl
									checked={value}
									onChange={onChange}
									label={__(
										'Enable minor core updates',
										'updatebee'
									)}
								/>
							)}
						/>
					</div>
				</div>
				<div className="updatebee-panel-row">
					<h4>{__('WordPress Plugin Updates', 'updatebee')}</h4>
					<p className="description">
						{__(
							'Plugin updates present their own risk, but it is very important to keep these up-to-date. If configured, you can still update only selected plugins.',
							'updatebee'
						)}
					</p>
					<div className="updatebee-dashboard-update-toggle">
						<Controller
							name="pluginUpdates"
							control={control}
							render={({ field: { value, onChange } }) => (
								<ToggleControl
									checked={value}
									onChange={onChange}
									label={__(
										'Enable plugin auto-updates',
										'updatebee'
									)}
								/>
							)}
						/>
					</div>
				</div>
				<div className="updatebee-panel-row">
					<h4>{__('WordPress Theme Updates', 'updatebee')}</h4>
					<p className="description">
						{__(
							'If you are using a child-theme, it is important to keep your parent themes updated, including deactivated themes. You can also configure select themes to enable.',
							'updatebee'
						)}
					</p>
					<div className="updatebee-dashboard-update-toggle">
						<Controller
							name="themeUpdates"
							control={control}
							render={({ field: { value, onChange } }) => (
								<ToggleControl

									checked={value}
									onChange={onChange}
									label={__(
										'Enable theme auto-updates',
										'updatebee'
									)}
								/>
							)}
						/>
					</div>
				</div>
				<div className="updatebee-panel-row">
					<h4>
						{__('WordPress Translation Updates', 'updatebee')}
					</h4>
					<p className="description">
						{__(
							'You ideally should keep your translations up-to-date. You can configure per translation which should be updated.',
							'updatebee'
						)}
					</p>
					<div className="updatebee-dashboard-update-toggle">
						<Controller
							name="translationUpdates"
							control={control}
							render={({ field: { value, onChange } }) => (
								<ToggleControl

									checked={value}
									onChange={onChange}
									label={__(
										'Enable translation auto-updates',
										'updatebee'
									)}
								/>
							)}
						/>
					</div>
				</div>
			</div>
		</div>
	);
};

export default DashboardUpdateOptions;
