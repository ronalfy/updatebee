/**
 * Dashboard automatic-update toggles.
 */
import { Button, ToggleControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Controller, useFormContext } from 'react-hook-form';
import sendCommand from '../../../utils/SendCommand';
import { ErrorFallback } from '../../components/ErrorBoundary';
import Loader from '../../components/Loader';
import { optionToBool } from '../../utils/bool';
import { Menu } from '@wordpress/ui';

export type DashboardSettingsForm = {
	majorUpdates: boolean;
	minorUpdates: boolean;
	pluginUpdates: boolean;
	pluginUpdatesDisabled: boolean;
	pluginManualUpdatesOnly: boolean;
	pluginPatchesOnly: boolean;
	themeUpdates: boolean;
	themeUpdatesDisabled: boolean;
	themeManualUpdatesOnly: boolean;
	themePatchesOnly: boolean;
	translationUpdates: boolean;
	translationUpdatesDisabled: boolean;
};

export const dashboardSettingsDefaults: DashboardSettingsForm = {
	majorUpdates: false,
	minorUpdates: true,
	pluginUpdates: false,
	pluginUpdatesDisabled: false,
	pluginManualUpdatesOnly: false,
	pluginPatchesOnly: false,
	themeUpdates: false,
	themeUpdatesDisabled: false,
	themeManualUpdatesOnly: false,
	themePatchesOnly: false,
	translationUpdates: true,
	translationUpdatesDisabled: false,
};

type DashboardOptionsPayload = {
	major_updates?: string;
	minor_updates?: string;
	plugin_updates?: string;
	plugin_updates_disabled?: string;
	plugin_manual_updates_only?: string;
	plugin_patches_only?: string;
	theme_updates?: string;
	theme_updates_disabled?: string;
	theme_manual_updates_only?: string;
	theme_patches_only?: string;
	translation_updates?: string;
	translation_updates_disabled?: string;
};

/**
 * Map a dashboard options payload to form values.
 *
 * @param data Option payload.
 * @return Form values.
 */
const payloadToForm = (
	data: DashboardOptionsPayload
): DashboardSettingsForm => {
	return {
		majorUpdates: optionToBool(data.major_updates),
		minorUpdates: optionToBool(data.minor_updates),
		pluginUpdates: optionToBool(data.plugin_updates),
		pluginUpdatesDisabled: optionToBool(data.plugin_updates_disabled),
		pluginManualUpdatesOnly: optionToBool(data.plugin_manual_updates_only),
		pluginPatchesOnly: optionToBool(data.plugin_patches_only),
		themeUpdates: optionToBool(data.theme_updates),
		themeUpdatesDisabled: optionToBool(data.theme_updates_disabled),
		themeManualUpdatesOnly: optionToBool(data.theme_manual_updates_only),
		themePatchesOnly: optionToBool(data.theme_patches_only),
		translationUpdates: optionToBool(data.translation_updates),
		translationUpdatesDisabled: optionToBool(data.translation_updates_disabled),
	};
};

const DashboardUpdateOptions = () => {
	const { control, reset, setValue, getValues } =
		useFormContext<DashboardSettingsForm>();
	const [loading, setLoading] = useState(true);
	const [loadError, setLoadError] = useState(false);
	const [loadAttempt, setLoadAttempt] = useState(0);

	useEffect(() => {
		let cancelled = false;

		setLoading(true);
		setLoadError(false);

		sendCommand<DashboardOptionsPayload>(
			'updatebee_get_dashboard_updates',
			{
				nonce: updatebeeDashboard.retrieveNonce,
			}
		)
			.then((response) => {
				const data = response.data?.data;
				if (!data) {
					throw new Error('Invalid response.');
				}

				if (cancelled) {
					return;
				}

				reset(payloadToForm(data));
			})
			.catch(() => {
				if (!cancelled) {
					setLoadError(true);
				}
			})
			.finally(() => {
				if (!cancelled) {
					setLoading(false);
				}
			});

		return () => {
			cancelled = true;
		};
	}, [reset, loadAttempt]);

	const setAll = (enabled: boolean) => {
		setValue('majorUpdates', enabled, { shouldDirty: true });
		setValue('minorUpdates', enabled, { shouldDirty: true });
		setValue('pluginUpdates', enabled, { shouldDirty: true });
		setValue('themeUpdates', enabled, { shouldDirty: true });
		setValue('translationUpdates', enabled, { shouldDirty: true });
	};

	if (loading) {
		return <Loader />;
	}

	if (loadError) {
		return (
			<ErrorFallback
				heading={__('Automatic Updates', 'updatebee')}
				message={__(
					'Settings could not be loaded. Try again or reload the page.',
					'updatebee'
				)}
				onRetry={() => {
					setLoadAttempt((attempt) => attempt + 1);
				}}
			/>
		);
	}

	console.log(getValues('pluginUpdatesDisabled'));

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
					<Menu.Root
					>
						<Menu.Trigger>
							Configure
						</Menu.Trigger>
						<Menu.Popup>

							<Controller
								name="pluginUpdatesDisabled"
								control={control}
								render={({ field: { value, onChange } }) => {
									console.log( value );
									return (<>
										<Menu.RadioGroup value={value ? 'on' : 'off'} onValueChange={(value) => setValue('pluginUpdatesDisabled', value === 'on' ? true : false)}>
											<Menu.GroupLabel>Disable Plugin Updates</Menu.GroupLabel>
											<Menu.RadioItem value="on">
												<Menu.ItemLabel>Plugin Updates Allowed</Menu.ItemLabel>
											</Menu.RadioItem>
											<Menu.RadioItem value="off">
												<Menu.ItemLabel>Plugin Updates Disabled</Menu.ItemLabel>
											</Menu.RadioItem>
										</Menu.RadioGroup>
										</>)
								}}


							/>



						</Menu.Popup>
					</Menu.Root>
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
