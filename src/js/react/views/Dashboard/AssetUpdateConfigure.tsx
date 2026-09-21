/**
 * Shared asset auto-update configure menu.
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

export type AssetKind = 'plugin' | 'theme' | 'translation';

type AssetFieldConfig = {
	autoUpdates:
		| 'pluginAutoUpdates'
		| 'themeAutoUpdates'
		| 'translationAutoUpdates';
	autoUpdatesCustom:
		| 'pluginAutoUpdatesCustom'
		| 'themeAutoUpdatesCustom'
		| 'translationAutoUpdatesCustom';
	autoUpdatesPatchesOnly?:
		| 'pluginAutoUpdatesPatchesOnly'
		| 'themeAutoUpdatesPatchesOnly';
	updatesDisabled:
		| 'pluginUpdatesDisabled'
		| 'themeUpdatesDisabled'
		| 'translationUpdatesDisabled';
	manualUpdatesOnly:
		| 'pluginManualUpdatesOnly'
		| 'themeManualUpdatesOnly'
		| 'translationManualUpdatesOnly';
	hasPatches: boolean;
};

export const assetFieldConfig: Record< AssetKind, AssetFieldConfig > = {
	plugin: {
		autoUpdates: 'pluginAutoUpdates',
		autoUpdatesCustom: 'pluginAutoUpdatesCustom',
		autoUpdatesPatchesOnly: 'pluginAutoUpdatesPatchesOnly',
		updatesDisabled: 'pluginUpdatesDisabled',
		manualUpdatesOnly: 'pluginManualUpdatesOnly',
		hasPatches: true,
	},
	theme: {
		autoUpdates: 'themeAutoUpdates',
		autoUpdatesCustom: 'themeAutoUpdatesCustom',
		autoUpdatesPatchesOnly: 'themeAutoUpdatesPatchesOnly',
		updatesDisabled: 'themeUpdatesDisabled',
		manualUpdatesOnly: 'themeManualUpdatesOnly',
		hasPatches: true,
	},
	translation: {
		autoUpdates: 'translationAutoUpdates',
		autoUpdatesCustom: 'translationAutoUpdatesCustom',
		updatesDisabled: 'translationUpdatesDisabled',
		manualUpdatesOnly: 'translationManualUpdatesOnly',
		hasPatches: false,
	},
};

type AssetUpdateLabels = {
	selected: string;
	patches: string;
	disableAll: string;
	summaryAll: string;
	summarySelected: string;
	summaryPatches: string;
	summarySelectedPatches: string;
};

/**
 * Copy for an asset configure menu.
 *
 * @param kind Asset group.
 * @return Localized labels.
 */
const getAssetLabels = ( kind: AssetKind ): AssetUpdateLabels => {
	if ( 'theme' === kind ) {
		return {
			selected: __( 'Selected themes', 'updatebee' ),
			patches: __( 'Patches only', 'updatebee' ),
			disableAll: __( 'Disable all theme updates', 'updatebee' ),
			summaryAll: __( 'All themes', 'updatebee' ),
			summarySelected: __( 'Selected themes', 'updatebee' ),
			summaryPatches: __( 'Patches only', 'updatebee' ),
			summarySelectedPatches: __( 'Selected · Patches', 'updatebee' ),
		};
	}

	if ( 'translation' === kind ) {
		return {
			selected: __( 'Selected translations', 'updatebee' ),
			patches: '',
			disableAll: __( 'Disable all translation updates', 'updatebee' ),
			summaryAll: __( 'All translations', 'updatebee' ),
			summarySelected: __( 'Selected translations', 'updatebee' ),
			summaryPatches: '',
			summarySelectedPatches: '',
		};
	}

	return {
		selected: __( 'Selected plugins', 'updatebee' ),
		patches: __( 'Patches only', 'updatebee' ),
		disableAll: __( 'Disable all plugin updates', 'updatebee' ),
		summaryAll: __( 'All plugins', 'updatebee' ),
		summarySelected: __( 'Selected plugins', 'updatebee' ),
		summaryPatches: __( 'Patches only', 'updatebee' ),
		summarySelectedPatches: __( 'Selected · Patches', 'updatebee' ),
	};
};

/**
 * Keep one asset group's flags consistent with its auto-update toggle.
 *
 * @param values Form values.
 * @param kind   Asset group.
 * @return Normalized form values.
 */
export const normalizeAssetSettings = (
	values: DashboardSettingsForm,
	kind: AssetKind
): DashboardSettingsForm => {
	const fields = assetFieldConfig[ kind ];
	const next = { ...values };

	if ( next[ fields.autoUpdates ] ) {
		next[ fields.updatesDisabled ] = false;
		next[ fields.manualUpdatesOnly ] = false;
		return next;
	}

	next[ fields.autoUpdatesCustom ] = false;

	if ( fields.hasPatches && fields.autoUpdatesPatchesOnly ) {
		next[ fields.autoUpdatesPatchesOnly ] = false;
	}

	const isDisabled = Boolean( next[ fields.updatesDisabled ] );
	next[ fields.updatesDisabled ] = isDisabled;
	next[ fields.manualUpdatesOnly ] = ! isDisabled;

	return next;
};

/**
 * Keep plugin, theme, and translation flags consistent.
 *
 * @param values Form values.
 * @return Normalized form values.
 */
export const normalizeDashboardSettings = (
	values: DashboardSettingsForm
): DashboardSettingsForm => {
	return normalizeAssetSettings(
		normalizeAssetSettings(
			normalizeAssetSettings( values, 'plugin' ),
			'theme'
		),
		'translation'
	);
};

/**
 * Apply asset auto-update on/off side effects.
 *
 * @param kind      Asset group.
 * @param setValue  Form setValue.
 * @param getValues Form getValues.
 * @param enabled   Whether auto-updates are enabled.
 */
export const applyAssetAutoUpdatesEnabled = (
	kind: AssetKind,
	setValue: UseFormSetValue< DashboardSettingsForm >,
	getValues: UseFormGetValues< DashboardSettingsForm >,
	enabled: boolean
): void => {
	const fields = assetFieldConfig[ kind ];

	setValue( fields.autoUpdates, enabled, dirty );

	if ( enabled ) {
		setValue( fields.updatesDisabled, false, dirty );
		setValue( fields.manualUpdatesOnly, false, dirty );
		return;
	}

	setValue( fields.autoUpdatesCustom, false, dirty );

	if ( fields.hasPatches && fields.autoUpdatesPatchesOnly ) {
		setValue( fields.autoUpdatesPatchesOnly, false, dirty );
	}

	if ( getValues( fields.updatesDisabled ) ) {
		setValue( fields.manualUpdatesOnly, false, dirty );
		return;
	}

	setValue( fields.updatesDisabled, false, dirty );
	setValue( fields.manualUpdatesOnly, true, dirty );
};

/**
 * Force asset auto-updates off and select Manual only.
 *
 * @param kind     Asset group.
 * @param setValue Form setValue.
 */
export const applyAssetAutoUpdatesDisabledAll = (
	kind: AssetKind,
	setValue: UseFormSetValue< DashboardSettingsForm >
): void => {
	const fields = assetFieldConfig[ kind ];

	setValue( fields.autoUpdates, false, dirty );
	setValue( fields.autoUpdatesCustom, false, dirty );

	if ( fields.hasPatches && fields.autoUpdatesPatchesOnly ) {
		setValue( fields.autoUpdatesPatchesOnly, false, dirty );
	}

	setValue( fields.updatesDisabled, false, dirty );
	setValue( fields.manualUpdatesOnly, true, dirty );
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
	applyAssetAutoUpdatesEnabled( 'plugin', setValue, getValues, enabled );
};

/**
 * Force plugin auto-updates off and select Manual only.
 *
 * @param setValue Form setValue.
 */
export const applyPluginAutoUpdatesDisabledAll = (
	setValue: UseFormSetValue< DashboardSettingsForm >
): void => {
	applyAssetAutoUpdatesDisabledAll( 'plugin', setValue );
};

type AssetConfigureSummaryValues = {
	autoUpdates: boolean;
	autoUpdatesCustom: boolean;
	autoUpdatesPatchesOnly: boolean;
	updatesDisabled: boolean;
};

/**
 * Summary label for the current asset configure state.
 *
 * @param kind   Asset group.
 * @param values Asset flags.
 * @return Summary text.
 */
const getAssetConfigureSummary = (
	kind: AssetKind,
	values: AssetConfigureSummaryValues
): string => {
	if ( ! values.autoUpdates ) {
		if ( values.updatesDisabled ) {
			return __( 'Disabled', 'updatebee' );
		}

		return __( 'Manual only', 'updatebee' );
	}

	const fields = assetFieldConfig[ kind ];
	const labels = getAssetLabels( kind );

	if (
		fields.hasPatches &&
		values.autoUpdatesCustom &&
		values.autoUpdatesPatchesOnly
	) {
		return labels.summarySelectedPatches;
	}

	if ( values.autoUpdatesCustom ) {
		return labels.summarySelected;
	}

	if ( fields.hasPatches && values.autoUpdatesPatchesOnly ) {
		return labels.summaryPatches;
	}

	return labels.summaryAll;
};

type AssetUpdateConfigureProps = {
	kind: AssetKind;
};

const AssetUpdateConfigure = ( { kind }: AssetUpdateConfigureProps ) => {
	const { setValue } = useFormContext< DashboardSettingsForm >();
	const fields = assetFieldConfig[ kind ];
	const labels = getAssetLabels( kind );
	const formValues = useWatch< DashboardSettingsForm >();

	const autoUpdates = Boolean( formValues?.[ fields.autoUpdates ] );
	const autoUpdatesCustom = Boolean(
		formValues?.[ fields.autoUpdatesCustom ]
	);
	const updatesDisabled = Boolean( formValues?.[ fields.updatesDisabled ] );
	const patchesField = fields.autoUpdatesPatchesOnly;
	const autoUpdatesPatchesOnly =
		fields.hasPatches && patchesField
			? Boolean( formValues?.[ patchesField ] )
			: false;

	const [ isMenuOpen, setIsMenuOpen ] = useState( false );

	const summary = getAssetConfigureSummary( kind, {
		autoUpdates,
		autoUpdatesCustom,
		autoUpdatesPatchesOnly,
		updatesDisabled,
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
					{ autoUpdates ? (
						<Menu.Group>
							<Menu.GroupLabel>
								{ __( 'Auto-update options', 'updatebee' ) }
							</Menu.GroupLabel>
							<Menu.CheckboxItem
								checked={ autoUpdatesCustom }
								closeOnClick={ false }
								onCheckedChange={ ( checked ) => {
									setValue(
										fields.autoUpdatesCustom,
										checked,
										dirty
									);
								} }
							>
								<Menu.ItemLabel>
									{ labels.selected }
								</Menu.ItemLabel>
							</Menu.CheckboxItem>
							{ fields.hasPatches && patchesField && (
								<Menu.CheckboxItem
									checked={ autoUpdatesPatchesOnly }
									closeOnClick={ false }
									onCheckedChange={ ( checked ) => {
										setValue(
											patchesField,
											checked,
											dirty
										);
									} }
								>
									<Menu.ItemLabel>
										{ labels.patches }
									</Menu.ItemLabel>
								</Menu.CheckboxItem>
							) }
						</Menu.Group>
					) : (
						<Menu.RadioGroup
							value={ updatesDisabled ? 'disabled' : 'manual' }
							onValueChange={ ( nextValue ) => {
								if (
									'disabled' !== nextValue &&
									'manual' !== nextValue
								) {
									return;
								}

								const isDisabled = 'disabled' === nextValue;
								setValue(
									fields.updatesDisabled,
									isDisabled,
									dirty
								);
								setValue(
									fields.manualUpdatesOnly,
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
									{ labels.disableAll }
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

export default AssetUpdateConfigure;
