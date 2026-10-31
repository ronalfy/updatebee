/**
 * Advanced card and drawer for the toolbar shortcut menu.
 */
import { ToggleControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { cog } from '@wordpress/icons';
import { Button, Card, Drawer, IconButton } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import type { SnackbarType } from './AdvancedFooter';

type AdminBarMenuDrawerProps = {
	disabled: boolean;
	onSavingChange: ( isSaving: boolean ) => void;
	onSnackbar: ( message: string, type: SnackbarType ) => void;
};

type AdminBarPayload = {
	message?: string;
	admin_bar_enabled?: boolean;
	admin_bar_show_admin?: boolean;
	admin_bar_show_frontend?: boolean;
};

/**
 * Saved location summary for the card.
 *
 * @param showAdmin    Whether wp-admin is enabled.
 * @param showFrontend Whether the frontend is enabled.
 * @return Summary text.
 */
const formatLocationSummary = (
	showAdmin: boolean,
	showFrontend: boolean
): string => {
	if ( showAdmin && showFrontend ) {
		return __( 'Shown in wp-admin and on the frontend.', 'updatebee' );
	}

	if ( showAdmin ) {
		return __( 'Shown in wp-admin.', 'updatebee' );
	}

	if ( showFrontend ) {
		return __( 'Shown on the frontend.', 'updatebee' );
	}

	return __(
		'Enabled, but hidden in wp-admin and on the frontend.',
		'updatebee'
	);
};

/**
 * Toolbar menu card with a gear that opens the settings drawer.
 *
 * @param props Card availability and save callbacks.
 */
const AdminBarMenuDrawer = ( props: AdminBarMenuDrawerProps ) => {
	const { disabled, onSavingChange, onSnackbar } = props;
	const [ isOpen, setIsOpen ] = useState( false );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ savedEnabled, setSavedEnabled ] = useState(
		Boolean( updatebeeAdvanced.adminBarEnabled )
	);
	const [ savedShowAdmin, setSavedShowAdmin ] = useState(
		Boolean( updatebeeAdvanced.adminBarShowAdmin )
	);
	const [ savedShowFrontend, setSavedShowFrontend ] = useState(
		Boolean( updatebeeAdvanced.adminBarShowFrontend )
	);
	const [ draftEnabled, setDraftEnabled ] = useState( savedEnabled );
	const [ draftShowAdmin, setDraftShowAdmin ] = useState( savedShowAdmin );
	const [ draftShowFrontend, setDraftShowFrontend ] =
		useState( savedShowFrontend );

	/**
	 * Revert drawer drafts to the last saved values.
	 */
	const resetDraft = useCallback( () => {
		setDraftEnabled( savedEnabled );
		setDraftShowAdmin( savedShowAdmin );
		setDraftShowFrontend( savedShowFrontend );
	}, [ savedEnabled, savedShowAdmin, savedShowFrontend ] );

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
	 * Persist the toolbar menu toggles.
	 */
	const handleSave = useCallback( () => {
		if ( isSaving || disabled ) {
			return;
		}

		setIsSaving( true );
		onSavingChange( true );

		sendCommand< AdminBarPayload >( 'updatebee_save_advanced_settings', {
			nonce: updatebeeAdvanced.saveNonce,
			admin_bar_enabled: draftEnabled ? '1' : '0',
			admin_bar_show_admin: draftShowAdmin ? '1' : '0',
			admin_bar_show_frontend: draftShowFrontend ? '1' : '0',
		} )
			.then( ( response ) => {
				const payload = response.data.data;
				const nextEnabled = Boolean( payload.admin_bar_enabled );
				const nextShowAdmin = Boolean( payload.admin_bar_show_admin );
				const nextShowFrontend = Boolean(
					payload.admin_bar_show_frontend
				);
				setSavedEnabled( nextEnabled );
				setDraftEnabled( nextEnabled );
				setSavedShowAdmin( nextShowAdmin );
				setDraftShowAdmin( nextShowAdmin );
				setSavedShowFrontend( nextShowFrontend );
				setDraftShowFrontend( nextShowFrontend );
				onSnackbar(
					payload.message ||
						__( 'Admin bar menu has been saved.', 'updatebee' ),
					'success'
				);
				setIsOpen( false );
			} )
			.catch( () => {
				onSnackbar(
					__( 'Admin bar menu could not be saved.', 'updatebee' ),
					'error'
				);
			} )
			.finally( () => {
				setIsSaving( false );
				onSavingChange( false );
			} );
	}, [
		disabled,
		draftEnabled,
		draftShowAdmin,
		draftShowFrontend,
		isSaving,
		onSavingChange,
		onSnackbar,
	] );

	return (
		<Drawer.Root
			open={ isOpen }
			onOpenChange={ handleOpenChange }
			swipeDirection="right"
			disablePointerDismissal={ true }
		>
			<Card.Root className="updatebee-advanced-card">
				<Card.Header>
					<div className="updatebee-advanced-card-header">
						<Card.Title>
							{ __( 'Admin Bar Menu', 'updatebee' ) }
						</Card.Title>
						<div className="updatebee-advanced-keywords-controls">
							<span
								className={
									savedEnabled
										? 'updatebee-advanced-status-badge is-active'
										: 'updatebee-advanced-status-badge'
								}
							>
								{ savedEnabled
									? __( 'Enabled', 'updatebee' )
									: __( 'Disabled', 'updatebee' ) }
							</span>
							<IconButton
								variant="outline"
								icon={ cog }
								label={ __( 'Admin bar menu', 'updatebee' ) }
								disabled={ disabled || isSaving }
								onClick={ () => {
									if ( ! isSaving && ! disabled ) {
										resetDraft();
										setIsOpen( true );
									}
								} }
							/>
						</div>
					</div>
				</Card.Header>
				<Card.Content className="updatebee-advanced-card-content">
					<p className="updatebee-advanced-card-description">
						{ __(
							'Add a toolbar menu with shortcuts to Settings, Plugins, Themes, Logs, Tools, Emails, and Advanced.',
							'updatebee'
						) }
					</p>
					{ savedEnabled && (
						<p className="updatebee-advanced-keywords-preview">
							{ formatLocationSummary(
								savedShowAdmin,
								savedShowFrontend
							) }
						</p>
					) }
				</Card.Content>
			</Card.Root>
			<Drawer.Popup
				size="medium"
				portal={
					<Drawer.Portal className="updatebee-advanced-admin-bar-drawer-portal" />
				}
			>
				<Drawer.Header>
					<Drawer.Title>
						{ __( 'Admin Bar Menu', 'updatebee' ) }
					</Drawer.Title>
				</Drawer.Header>
				<Drawer.Content>
					<Drawer.Description>
						{ __(
							'Choose whether the toolbar menu is available, and where it appears.',
							'updatebee'
						) }
					</Drawer.Description>
					<div className="updatebee-advanced-keywords-fields">
						<ToggleControl
							label={ __( 'Enable admin bar menu', 'updatebee' ) }
							checked={ draftEnabled }
							disabled={ isSaving }
							onChange={ ( checked: boolean ) => {
								setDraftEnabled( checked );
							} }
							help={ __(
								'Turn this on to add the shortcut menu for users who can manage these settings. Location choices are saved and apply when the menu is enabled.',
								'updatebee'
							) }
						/>
						<ToggleControl
							label={ __( 'Show in wp-admin', 'updatebee' ) }
							checked={ draftShowAdmin }
							disabled={ isSaving || ! draftEnabled }
							onChange={ ( checked: boolean ) => {
								setDraftShowAdmin( checked );
							} }
							help={ __(
								'Show the menu in the WordPress admin toolbar.',
								'updatebee'
							) }
						/>
						<ToggleControl
							label={ __( 'Show on the frontend', 'updatebee' ) }
							checked={ draftShowFrontend }
							disabled={ isSaving || ! draftEnabled }
							onChange={ ( checked: boolean ) => {
								setDraftShowFrontend( checked );
							} }
							help={ __(
								'Show the menu in the toolbar on the site frontend.',
								'updatebee'
							) }
						/>
					</div>
				</Drawer.Content>
				<Drawer.Footer className="updatebee-advanced-keywords-footer">
					<Drawer.Action variant="outline" disabled={ isSaving }>
						{ __( 'Cancel', 'updatebee' ) }
					</Drawer.Action>
					<Button
						variant="solid"
						disabled={ isSaving || disabled }
						loading={ isSaving }
						loadingAnnouncement={ __(
							'Saving admin bar menu…',
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

export default AdminBarMenuDrawer;
