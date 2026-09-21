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
import { boolToOption, optionToBool } from '../../utils/bool';
import PluginUpdateConfigure, {
	applyPluginAutoUpdatesDisabledAll,
	applyPluginAutoUpdatesEnabled,
} from './PluginUpdateConfigure';

export type DashboardSettingsForm = {
	majorAutoUpdates: boolean;
	minorAutoUpdates: boolean;
	pluginAutoUpdates: boolean;
	pluginAutoUpdatesCustom: boolean;
	pluginUpdatesDisabled: boolean;
	pluginManualUpdatesOnly: boolean;
	pluginAutoUpdatesPatchesOnly: boolean;
	themeAutoUpdates: boolean;
	themeAutoUpdatesCustom: boolean;
	themeUpdatesDisabled: boolean;
	themeManualUpdatesOnly: boolean;
	themeAutoUpdatesPatchesOnly: boolean;
	translationAutoUpdates: boolean;
	translationAutoUpdatesCustom: boolean;
	translationUpdatesDisabled: boolean;
	translationManualUpdatesOnly: boolean;
};

export const dashboardSettingsDefaults: DashboardSettingsForm = {
	majorAutoUpdates: false,
	minorAutoUpdates: true,
	pluginAutoUpdates: false,
	pluginAutoUpdatesCustom: false,
	pluginUpdatesDisabled: false,
	pluginManualUpdatesOnly: true,
	pluginAutoUpdatesPatchesOnly: false,
	themeAutoUpdates: false,
	themeAutoUpdatesCustom: false,
	themeUpdatesDisabled: false,
	themeManualUpdatesOnly: false,
	themeAutoUpdatesPatchesOnly: false,
	translationAutoUpdates: true,
	translationUpdatesDisabled: false,
	translationAutoUpdatesCustom: false,
	translationManualUpdatesOnly: false,
};

export type DashboardOptionsPayload = {
	major_auto_updates?: string;
	minor_auto_updates?: string;
	plugin_auto_updates?: string;
	plugin_auto_updates_custom?: string;
	plugin_updates_disabled?: string;
	plugin_manual_updates_only?: string;
	plugin_auto_updates_patches_only?: string;
	theme_auto_updates?: string;
	theme_auto_updates_custom?: string;
	theme_updates_disabled?: string;
	theme_manual_updates_only?: string;
	theme_auto_updates_patches_only?: string;
	translation_auto_updates?: string;
	translation_auto_updates_custom?: string;
	translation_updates_disabled?: string;
	translation_manual_updates_only?: string;
};

/**
 * Keep plugin flags consistent with the auto-update toggle.
 *
 * @param values Form values.
 * @return Normalized form values.
 */
export const normalizePluginSettings = (
	values: DashboardSettingsForm
): DashboardSettingsForm => {
	if ( values.pluginAutoUpdates ) {
		return {
			...values,
			pluginUpdatesDisabled: false,
			pluginManualUpdatesOnly: false,
		};
	}

	return {
		...values,
		pluginAutoUpdatesCustom: false,
		pluginAutoUpdatesPatchesOnly: false,
		pluginUpdatesDisabled: values.pluginUpdatesDisabled,
		pluginManualUpdatesOnly: ! values.pluginUpdatesDisabled,
	};
};

/**
 * Map camelCase form values to option keys.
 *
 * @param values Form values.
 * @return Option payload.
 */
export const formToOptions = (
	values: DashboardSettingsForm
): DashboardOptionsPayload => {
	const pluginValues = normalizePluginSettings( values );

	return {
		major_auto_updates: boolToOption( pluginValues.majorAutoUpdates ),
		minor_auto_updates: boolToOption( pluginValues.minorAutoUpdates ),
		plugin_auto_updates: boolToOption( pluginValues.pluginAutoUpdates ),
		plugin_auto_updates_custom: boolToOption(
			pluginValues.pluginAutoUpdatesCustom
		),
		plugin_updates_disabled: boolToOption(
			pluginValues.pluginUpdatesDisabled
		),
		plugin_manual_updates_only: boolToOption(
			pluginValues.pluginManualUpdatesOnly
		),
		plugin_auto_updates_patches_only: boolToOption(
			pluginValues.pluginAutoUpdatesPatchesOnly
		),
		theme_auto_updates: boolToOption( values.themeAutoUpdates ),
		theme_auto_updates_custom: boolToOption(
			values.themeAutoUpdatesCustom
		),
		theme_updates_disabled: boolToOption( values.themeUpdatesDisabled ),
		theme_manual_updates_only: boolToOption(
			values.themeManualUpdatesOnly
		),
		theme_auto_updates_patches_only: boolToOption(
			values.themeAutoUpdatesPatchesOnly
		),
		translation_auto_updates: boolToOption( values.translationAutoUpdates ),
		translation_auto_updates_custom: boolToOption(
			values.translationAutoUpdatesCustom
		),
		translation_updates_disabled: boolToOption(
			values.translationUpdatesDisabled
		),
		translation_manual_updates_only: boolToOption(
			values.translationManualUpdatesOnly
		),
	};
};

/**
 * Map option keys back to camelCase form values.
 *
 * @param data Option payload.
 * @return Form values.
 */
export const optionsToForm = (
	data?: DashboardOptionsPayload
): DashboardSettingsForm => {
	if ( ! data ) {
		return dashboardSettingsDefaults;
	}

	return normalizePluginSettings( {
		majorAutoUpdates: optionToBool( data.major_auto_updates ),
		minorAutoUpdates: optionToBool( data.minor_auto_updates ),
		pluginAutoUpdates: optionToBool( data.plugin_auto_updates ),
		pluginAutoUpdatesCustom: optionToBool(
			data.plugin_auto_updates_custom
		),
		pluginUpdatesDisabled: optionToBool( data.plugin_updates_disabled ),
		pluginManualUpdatesOnly: optionToBool(
			data.plugin_manual_updates_only
		),
		pluginAutoUpdatesPatchesOnly: optionToBool(
			data.plugin_auto_updates_patches_only
		),
		themeAutoUpdates: optionToBool( data.theme_auto_updates ),
		themeAutoUpdatesCustom: optionToBool( data.theme_auto_updates_custom ),
		themeUpdatesDisabled: optionToBool( data.theme_updates_disabled ),
		themeManualUpdatesOnly: optionToBool( data.theme_manual_updates_only ),
		themeAutoUpdatesPatchesOnly: optionToBool(
			data.theme_auto_updates_patches_only
		),
		translationAutoUpdates: optionToBool( data.translation_auto_updates ),
		translationAutoUpdatesCustom: optionToBool(
			data.translation_auto_updates_custom
		),
		translationUpdatesDisabled: optionToBool(
			data.translation_updates_disabled
		),
		translationManualUpdatesOnly: optionToBool(
			data.translation_manual_updates_only
		),
	} );
};

const DashboardUpdateOptions = () => {
	const { control, getValues, reset, setValue } =
		useFormContext< DashboardSettingsForm >();
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState( false );
	const [ loadAttempt, setLoadAttempt ] = useState( 0 );

	useEffect( () => {
		let cancelled = false;

		setLoading( true );
		setLoadError( false );

		sendCommand< DashboardOptionsPayload >(
			'updatebee_get_dashboard_updates',
			{
				nonce: updatebeeDashboard.retrieveNonce,
			}
		)
			.then( ( response ) => {
				const data = response.data?.data;
				if ( ! data ) {
					throw new Error( 'Invalid response.' );
				}

				if ( cancelled ) {
					return;
				}

				reset( optionsToForm( data ) );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setLoadError( true );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ reset, loadAttempt ] );

	const setAll = ( enabled: boolean ) => {
		setValue( 'majorAutoUpdates', enabled, { shouldDirty: true } );
		setValue( 'minorAutoUpdates', enabled, { shouldDirty: true } );
		setValue( 'themeAutoUpdates', enabled, { shouldDirty: true } );
		setValue( 'translationAutoUpdates', enabled, { shouldDirty: true } );

		if ( enabled ) {
			applyPluginAutoUpdatesEnabled( setValue, getValues, true );
			return;
		}

		applyPluginAutoUpdatesDisabledAll( setValue );
	};

	if ( loading ) {
		return <Loader />;
	}

	if ( loadError ) {
		return (
			<ErrorFallback
				heading={ __( 'Automatic Updates', 'updatebee' ) }
				message={ __(
					'Settings could not be loaded. Try again or reload the page.',
					'updatebee'
				) }
				onRetry={ () => {
					setLoadAttempt( ( attempt ) => attempt + 1 );
				} }
			/>
		);
	}

	return (
		<div className="updatebee-admin-panel-area updatebee-dashboard-automatic-updates">
			<h3 className="updatebee-panel-heading">
				{ __( 'Automatic Updates', 'updatebee' ) }
			</h3>
			<div className="updatebee-panel-row">
				<p className="description">
					{ __(
						'Here are some quick options to allow you to enable or disable automatic updates for certain parts of WordPress.',
						'updatebee'
					) }
				</p>
			</div>
			<div className="updatebee-panel-row">
				<div className="updatebee-link-row">
					<ul>
						<li>
							<Button
								variant="link"
								onClick={ () => {
									setAll( true );
								} }
							>
								{ __( 'Enable All', 'updatebee' ) }
							</Button>
						</li>
						<li>
							<Button
								variant="link"
								onClick={ () => {
									setAll( false );
								} }
							>
								{ __( 'Disable All', 'updatebee' ) }
							</Button>
						</li>
					</ul>
				</div>
			</div>
			<div className="updatebee-panel-row-dashboard-options">
				<div className="updatebee-panel-row">
					<h4>{ __( 'Major WordPress Updates', 'updatebee' ) }</h4>
					<p className="description">
						{ __(
							'Major updates are when you go from 5.5 to 5.6. These are major point releases and are well tested and vetted before release.',
							'updatebee'
						) }
					</p>
					<div className="updatebee-dashboard-update-toggle">
						<Controller
							name="majorAutoUpdates"
							control={ control }
							render={ ( { field: { value, onChange } } ) => (
								<ToggleControl
									checked={ value }
									onChange={ onChange }
									label={ __(
										'Enable major core updates',
										'updatebee'
									) }
								/>
							) }
						/>
					</div>
				</div>
				<div className="updatebee-panel-row">
					<h4>{ __( 'Minor WordPress Updates', 'updatebee' ) }</h4>
					<p className="description">
						{ __(
							'Minor updates are when you go from 5.5.1 to 5.5.2. These are minor point releases and are usually security and maintenance patches. They are rarely unstable.',
							'updatebee'
						) }
					</p>
					<div className="updatebee-dashboard-update-toggle">
						<Controller
							name="minorAutoUpdates"
							control={ control }
							render={ ( { field: { value, onChange } } ) => (
								<ToggleControl
									checked={ value }
									onChange={ onChange }
									label={ __(
										'Enable minor core updates',
										'updatebee'
									) }
								/>
							) }
						/>
					</div>
				</div>
				<div className="updatebee-panel-row">
					<h4>{ __( 'WordPress Plugin Updates', 'updatebee' ) }</h4>
					<p className="description">
						{ __(
							'Plugin updates present their own risk, but it is very important to keep these up-to-date. If configured, you can still update only selected plugins.',
							'updatebee'
						) }
					</p>
					<div className="updatebee-dashboard-update-toggle">
						<Controller
							name="pluginAutoUpdates"
							control={ control }
							render={ ( { field: { value } } ) => (
								<ToggleControl
									checked={ value }
									onChange={ ( checked ) => {
										applyPluginAutoUpdatesEnabled(
											setValue,
											getValues,
											checked
										);
									} }
									label={ __(
										'Enable plugin auto-updates',
										'updatebee'
									) }
								/>
							) }
						/>
					</div>
					<PluginUpdateConfigure />
				</div>
				<div className="updatebee-panel-row">
					<h4>{ __( 'WordPress Theme Updates', 'updatebee' ) }</h4>
					<p className="description">
						{ __(
							'If you are using a child-theme, it is important to keep your parent themes updated, including deactivated themes. You can also configure select themes to enable.',
							'updatebee'
						) }
					</p>
					<div className="updatebee-dashboard-update-toggle">
						<Controller
							name="themeAutoUpdates"
							control={ control }
							render={ ( { field: { value, onChange } } ) => (
								<ToggleControl
									checked={ value }
									onChange={ onChange }
									label={ __(
										'Enable theme auto-updates',
										'updatebee'
									) }
								/>
							) }
						/>
					</div>
				</div>
				<div className="updatebee-panel-row">
					<h4>
						{ __( 'WordPress Translation Updates', 'updatebee' ) }
					</h4>
					<p className="description">
						{ __(
							'You ideally should keep your translations up-to-date. You can configure per translation which should be updated.',
							'updatebee'
						) }
					</p>
					<div className="updatebee-dashboard-update-toggle">
						<Controller
							name="translationAutoUpdates"
							control={ control }
							render={ ( { field: { value, onChange } } ) => (
								<ToggleControl
									checked={ value }
									onChange={ onChange }
									label={ __(
										'Enable translation auto-updates',
										'updatebee'
									) }
								/>
							) }
						/>
					</div>
				</div>
			</div>
		</div>
	);
};

export default DashboardUpdateOptions;
