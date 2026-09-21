/**
 * Plugin auto-update configure menu.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Menu } from '@wordpress/ui';
import {
	useFormContext,
	useWatch,
	type UseFormGetValues,
	type UseFormSetValue,
} from 'react-hook-form';
import {
	chevronUp as ChevronUpIcon,
	chevronDown as ChevronDownIcon,
} from '@wordpress/icons';
import { Button } from '@wordpress/components';
import type { DashboardSettingsForm } from './DashboardUpdateOptions';

const dirty = {
	shouldDirty: true,
};

/**
 * Apply plugin auto-update on/off side effects.
 *
 * @param setValue  Form setValue.
 * @param getValues Form getValues.
 * @param enabled   Whether auto-updates are enabled.
 */
export const applyPluginAutoUpdatesEnabled = (
	setValue: UseFormSetValue< DashboardSettingsForm >,
	getValues: UseFormGetValues< DashboardSettingsForm >,
	enabled: boolean
): void => {
	setValue( 'pluginAutoUpdates', enabled, dirty );

	if ( enabled ) {
		setValue( 'pluginUpdatesDisabled', false, dirty );
		setValue( 'pluginManualUpdatesOnly', false, dirty );
		return;
	}

	setValue( 'pluginAutoUpdatesCustom', false, dirty );
	setValue( 'pluginAutoUpdatesPatchesOnly', false, dirty );

	if ( getValues( 'pluginUpdatesDisabled' ) ) {
		setValue( 'pluginManualUpdatesOnly', false, dirty );
		return;
	}

	setValue( 'pluginUpdatesDisabled', false, dirty );
	setValue( 'pluginManualUpdatesOnly', true, dirty );
};

/**
 * Force plugin auto-updates off and select Manual only.
 *
 * @param setValue Form setValue.
 */
export const applyPluginAutoUpdatesDisabledAll = (
	setValue: UseFormSetValue< DashboardSettingsForm >
): void => {
	setValue( 'pluginAutoUpdates', false, dirty );
	setValue( 'pluginAutoUpdatesCustom', false, dirty );
	setValue( 'pluginAutoUpdatesPatchesOnly', false, dirty );
	setValue( 'pluginUpdatesDisabled', false, dirty );
	setValue( 'pluginManualUpdatesOnly', true, dirty );
};

/**
 * Summary label for the current plugin configure state.
 *
 * @param values Plugin flags.
 * @return Summary text.
 */
const getPluginConfigureSummary = (
	values: Pick<
		DashboardSettingsForm,
		| 'pluginAutoUpdates'
		| 'pluginAutoUpdatesCustom'
		| 'pluginAutoUpdatesPatchesOnly'
		| 'pluginUpdatesDisabled'
	>
): string => {
	if ( ! values.pluginAutoUpdates ) {
		if ( values.pluginUpdatesDisabled ) {
			return __( 'Disabled', 'updatebee' );
		}

		return __( 'Manual only', 'updatebee' );
	}

	if (
		values.pluginAutoUpdatesCustom &&
		values.pluginAutoUpdatesPatchesOnly
	) {
		return __( 'Selected · Patches', 'updatebee' );
	}

	if ( values.pluginAutoUpdatesCustom ) {
		return __( 'Selected plugins', 'updatebee' );
	}

	if ( values.pluginAutoUpdatesPatchesOnly ) {
		return __( 'Patches only', 'updatebee' );
	}

	return __( 'All plugins', 'updatebee' );
};

const PluginUpdateConfigure = () => {
	const { setValue } = useFormContext< DashboardSettingsForm >();
	const pluginAutoUpdates = useWatch<
		DashboardSettingsForm,
		'pluginAutoUpdates'
	>( {
		name: 'pluginAutoUpdates',
	} );
	const pluginAutoUpdatesCustom = useWatch<
		DashboardSettingsForm,
		'pluginAutoUpdatesCustom'
	>( {
		name: 'pluginAutoUpdatesCustom',
	} );
	const pluginAutoUpdatesPatchesOnly = useWatch<
		DashboardSettingsForm,
		'pluginAutoUpdatesPatchesOnly'
	>( {
		name: 'pluginAutoUpdatesPatchesOnly',
	} );
	const pluginUpdatesDisabled = useWatch<
		DashboardSettingsForm,
		'pluginUpdatesDisabled'
	>( {
		name: 'pluginUpdatesDisabled',
	} );

	const [ isMenuOpen, setIsMenuOpen ] = useState( false );

	const summary = getPluginConfigureSummary( {
		pluginAutoUpdates: Boolean( pluginAutoUpdates ),
		pluginAutoUpdatesCustom: Boolean( pluginAutoUpdatesCustom ),
		pluginAutoUpdatesPatchesOnly: Boolean( pluginAutoUpdatesPatchesOnly ),
		pluginUpdatesDisabled: Boolean( pluginUpdatesDisabled ),
	} );

	return (
		<div className="updatebee-dashboard-configure-options">
			<Menu.Root
				onOpenChange={ ( open ) => {
					setIsMenuOpen( open );
				} }
			>
				<Menu.Trigger
					render={
						<Button
							variant="tertiary"
							icon={
								isMenuOpen ? ChevronUpIcon : ChevronDownIcon
							}
							className="updatebee-dashboard-configure-options-trigger-button"
						/>
					}
				>
					{ __( 'Configure', 'updatebee' ) }
				</Menu.Trigger>
				<Menu.Popup>
					{ pluginAutoUpdates ? (
						<Menu.Group>
							<Menu.GroupLabel>
								{ __( 'Auto-update options', 'updatebee' ) }
							</Menu.GroupLabel>
							<Menu.CheckboxItem
								checked={ Boolean( pluginAutoUpdatesCustom ) }
								closeOnClick={ false }
								onCheckedChange={ ( checked ) => {
									setValue(
										'pluginAutoUpdatesCustom',
										checked,
										dirty
									);
								} }
							>
								<Menu.ItemLabel>
									{ __( 'Selected plugins', 'updatebee' ) }
								</Menu.ItemLabel>
							</Menu.CheckboxItem>
							<Menu.CheckboxItem
								checked={ Boolean(
									pluginAutoUpdatesPatchesOnly
								) }
								closeOnClick={ false }
								onCheckedChange={ ( checked ) => {
									setValue(
										'pluginAutoUpdatesPatchesOnly',
										checked,
										dirty
									);
								} }
							>
								<Menu.ItemLabel>
									{ __( 'Patches only', 'updatebee' ) }
								</Menu.ItemLabel>
							</Menu.CheckboxItem>
						</Menu.Group>
					) : (
						<Menu.RadioGroup
							value={
								pluginUpdatesDisabled ? 'disabled' : 'manual'
							}
							onValueChange={ ( nextValue ) => {
								if (
									'disabled' !== nextValue &&
									'manual' !== nextValue
								) {
									return;
								}

								const isDisabled = 'disabled' === nextValue;
								setValue(
									'pluginUpdatesDisabled',
									isDisabled,
									dirty
								);
								setValue(
									'pluginManualUpdatesOnly',
									! isDisabled,
									dirty
								);
							} }
						>
							<Menu.GroupLabel>
								{ __(
									'When auto-updates are off',
									'updatebee'
								) }
							</Menu.GroupLabel>
							<Menu.RadioItem value="manual">
								<Menu.ItemLabel>
									{ __( 'Manual updates only', 'updatebee' ) }
								</Menu.ItemLabel>
							</Menu.RadioItem>
							<Menu.RadioItem value="disabled">
								<Menu.ItemLabel>
									{ __(
										'Disable all plugin updates',
										'updatebee'
									) }
								</Menu.ItemLabel>
							</Menu.RadioItem>
						</Menu.RadioGroup>
					) }
				</Menu.Popup>
			</Menu.Root>
			<span className="updatebee-dashboard-configure-summary">
				{ summary }
			</span>
		</div>
	);
};

export default PluginUpdateConfigure;
