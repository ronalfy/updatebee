/**
 * Per-plugin and per-theme settings drawer.
 *
 * Delay is the first field. Additional options belong in this same drawer.
 */
import { TextControl, ToggleControl, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { cog } from '@wordpress/icons';
import { Button, Drawer, IconButton } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';

type SnackbarType = 'success' | 'error' | 'warning' | 'info';

type ItemSettingsDrawerProps = {
	kind: 'plugin' | 'theme';
	assetName: string;
	assetId: string;
	delayDays: number | null;
	allowUntested: boolean;
	blockUntestedUpdates: boolean;
	settingsNonce: string;
	globalDelayDays: number;
	disabled?: boolean;
	onDelayChange: ( assetId: string, delayDays: number | null ) => void;
	onAllowUntestedChange: ( assetId: string, allowUntested: boolean ) => void;
	showSnackbar: ( message: string, type: SnackbarType ) => void;
};

type SettingsPayload = {
	message?: string;
	delay_days?: number | null;
	allow_untested?: boolean;
};

/**
 * Normalize a delay-days value.
 *
 * @param value Incoming days value.
 * @return Non-negative integer days.
 */
const normalizeDays = ( value: unknown ): number => {
	const days = Number( value );
	if ( Number.isFinite( days ) && days >= 0 ) {
		return Math.floor( days );
	}

	return 0;
};

/**
 * Normalize a saved delay override.
 *
 * @param value Incoming delay value.
 * @return Days, or null when the item uses the site default.
 */
export const normalizeDelayDays = ( value: unknown ): number | null => {
	if ( null === value || undefined === value || '' === value ) {
		return null;
	}

	return normalizeDays( value );
};

/**
 * Gear button and drawer for one plugin or theme.
 *
 * @param props Component props.
 */
const ItemSettingsDrawer = ( props: ItemSettingsDrawerProps ) => {
	const {
		kind,
		assetName,
		assetId,
		delayDays,
		allowUntested,
		blockUntestedUpdates,
		settingsNonce,
		globalDelayDays,
		disabled = false,
		onDelayChange,
		onAllowUntestedChange,
		showSnackbar,
	} = props;
	const [ isOpen, setIsOpen ] = useState( false );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ useSiteDefault, setUseSiteDefault ] = useState(
		null === delayDays
	);
	const [ customDelayDays, setCustomDelayDays ] = useState(
		delayDays ?? 0
	);
	const [ useUntestedDefault, setUseUntestedDefault ] = useState(
		! allowUntested
	);

	const title =
		'plugin' === kind
			? __( 'Plugin settings', 'updatebee' )
			: __( 'Theme settings', 'updatebee' );
	const buttonLabel =
		'plugin' === kind
			? sprintf(
					/* translators: %s: plugin name. */
					__( 'Plugin settings for %s', 'updatebee' ),
					assetName
			  )
			: sprintf(
					/* translators: %s: theme name. */
					__( 'Theme settings for %s', 'updatebee' ),
					assetName
			  );
	const siteDefaultLabel = sprintf(
		/* translators: %d: number of days. */
		_n(
			'Use site default (%d day)',
			'Use site default (%d days)',
			globalDelayDays,
			'updatebee'
		),
		globalDelayDays
	);

	/**
	 * Reset the draft to the last saved delay.
	 */
	const resetDraft = useCallback( () => {
		setUseSiteDefault( null === delayDays );
		setCustomDelayDays( delayDays ?? 0 );
		setUseUntestedDefault( ! allowUntested );
	}, [ allowUntested, delayDays ] );

	/**
	 * Open or close the drawer and revert unsaved edits.
	 *
	 * @param nextOpen Next open state.
	 */
	const handleOpenChange = useCallback(
		( nextOpen: boolean ) => {
			if ( isSaving ) {
				return;
			}

			setIsOpen( nextOpen );
			if ( ! nextOpen ) {
				resetDraft();
			}
		},
		[ isSaving, resetDraft ]
	);

	/**
	 * Persist delay settings for this item.
	 */
	const handleSave = useCallback( () => {
		if ( isSaving || disabled ) {
			return;
		}

		const nextDelay = useSiteDefault
			? null
			: normalizeDays( customDelayDays );
		const action =
			'plugin' === kind
				? 'updatebee_save_plugin_settings'
				: 'updatebee_save_theme_settings';
		const assetKey = 'plugin' === kind ? 'file' : 'stylesheet';

		setIsSaving( true );

		sendCommand< SettingsPayload >( action, {
			nonce: settingsNonce,
			[ assetKey ]: assetId,
			settings: JSON.stringify( {
				delay_days: nextDelay,
				allow_untested: useUntestedDefault ? null : true,
			} ),
		} )
			.then( ( response ) => {
				const payload = response.data.data;
				const savedDelay = normalizeDelayDays( payload.delay_days );
				const savedAllow = Boolean( payload.allow_untested );
				onDelayChange( assetId, savedDelay );
				onAllowUntestedChange( assetId, savedAllow );
				setUseSiteDefault( null === savedDelay );
				setCustomDelayDays( savedDelay ?? customDelayDays );
				setUseUntestedDefault( ! savedAllow );
				showSnackbar(
					payload.message ||
						( 'plugin' === kind
							? __(
									'Plugin settings have been saved.',
									'updatebee'
							  )
							: __(
									'Theme settings have been saved.',
									'updatebee'
							  ) ),
					'success'
				);
				setIsOpen( false );
			} )
			.catch( () => {
				showSnackbar(
					'plugin' === kind
						? __(
								'Plugin settings could not be saved.',
								'updatebee'
						  )
						: __(
								'Theme settings could not be saved.',
								'updatebee'
						  ),
					'error'
				);
			} )
			.finally( () => {
				setIsSaving( false );
			} );
	}, [
		assetId,
		customDelayDays,
		disabled,
		isSaving,
		kind,
		onAllowUntestedChange,
		onDelayChange,
		settingsNonce,
		showSnackbar,
		useSiteDefault,
		useUntestedDefault,
	] );

	return (
		<Drawer.Root
			open={ isOpen }
			onOpenChange={ handleOpenChange }
			swipeDirection="right"
			disablePointerDismissal={ true }
		>
			<div className="updatebee-item-settings-trigger">
				<IconButton
					variant="outline"
					icon={ cog }
					label={ buttonLabel }
					disabled={ disabled || isSaving }
					onClick={ () => {
						if ( ! isSaving && ! disabled ) {
							resetDraft();
							setIsOpen( true );
						}
					} }
				/>
			</div>
			<Drawer.Popup
				size="medium"
				portal={
					<Drawer.Portal className="updatebee-item-settings-drawer-portal" />
				}
			>
				<Drawer.Header>
					<Drawer.Title>{ title }</Drawer.Title>
				</Drawer.Header>
				<Drawer.Content>
					<Drawer.Description>
						{ sprintf(
							/* translators: %s: plugin or theme name. */
							__(
								'These options apply only to %s.',
								'updatebee'
							),
							assetName
						) }
					</Drawer.Description>
					<div className="updatebee-item-settings-fields">
						<ToggleControl
							label={ siteDefaultLabel }
							checked={ useSiteDefault }
							disabled={ isSaving }
							onChange={ ( checked: boolean ) => {
								setUseSiteDefault( checked );
							} }
						/>
						{ ! useSiteDefault && (
							<TextControl
								label={ __(
									'Delay automatic updates',
									'updatebee'
								) }
								type="number"
								min={ 0 }
								value={ String( customDelayDays ) }
								onChange={ ( value ) => {
									setCustomDelayDays(
										normalizeDays( value )
									);
								} }
								help={ __(
									'Days to wait before this item is updated automatically. Use 0 for no delay.',
									'updatebee'
								) }
								disabled={ isSaving }
							/>
						) }
						<ToggleControl
							label={ __(
								'Use site default for untested updates',
								'updatebee'
							) }
							checked={ useUntestedDefault }
							disabled={ isSaving }
							help={ __(
								'Turn this off to allow untested updates for this item.',
								'updatebee'
							) }
							onChange={ ( checked: boolean ) => {
								setUseUntestedDefault( checked );
							} }
						/>
						{ ! blockUntestedUpdates && (
							<Notice status="info" isDismissible={ false }>
								{ __(
									'Untested updates are already allowed for every plugin and theme.',
									'updatebee'
								) }
							</Notice>
						) }
					</div>
				</Drawer.Content>
				<Drawer.Footer className="updatebee-item-settings-footer">
					<Drawer.Action variant="outline" disabled={ isSaving }>
						{ __( 'Cancel', 'updatebee' ) }
					</Drawer.Action>
					<Button
						variant="solid"
						disabled={ isSaving || disabled }
						loading={ isSaving }
						loadingAnnouncement={ __(
							'Saving settings…',
							'updatebee'
						) }
						onClick={ handleSave }
					>
						{ __( 'Save', 'updatebee' ) }
					</Button>
				</Drawer.Footer>
			</Drawer.Popup>
		</Drawer.Root>
	);
};

export default ItemSettingsDrawer;
