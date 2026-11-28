/**
 * Logs settings drawer for retention days and Slack and Discord notifications.
 * Delayed updates can be left out of those messages without removing them from the logs.
 */
import { TextControl, ToggleControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { cog } from '@wordpress/icons';
import { Button, Drawer, IconButton } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import type { SnackbarType } from './LogsFooter';

type LogsSettingsDrawerProps = {
	onSnackbar: ( message: string, type: SnackbarType ) => void;
};

type LogsSettingsPayload = {
	message?: string;
	clear_logs_days?: number;
	slack_enabled?: boolean;
	slack_skip_delayed?: boolean;
	discord_enabled?: boolean;
	discord_skip_delayed?: boolean;
};

type LogsSettings = {
	clearLogsDays: number;
	slackEnabled: boolean;
	slackSkipDelayed: boolean;
	discordEnabled: boolean;
	discordSkipDelayed: boolean;
};

/**
 * Normalize the localized days value.
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
 * Read the localized logs settings.
 *
 * @return Saved settings.
 */
const getInitialSettings = (): LogsSettings => ( {
	clearLogsDays: normalizeDays( updatebeeLogs.clearLogsDays ),
	slackEnabled: Boolean( updatebeeLogs.slackEnabled ),
	slackSkipDelayed: Boolean( updatebeeLogs.slackSkipDelayed ),
	discordEnabled: Boolean( updatebeeLogs.discordEnabled ),
	discordSkipDelayed: Boolean( updatebeeLogs.discordSkipDelayed ),
} );

/**
 * Persistent drawer for log retention and Slack notification settings.
 *
 * @param props Snackbar callback.
 */
const LogsSettingsDrawer = ( props: LogsSettingsDrawerProps ) => {
	const { onSnackbar } = props;
	const [ isOpen, setIsOpen ] = useState( false );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ savedSettings, setSavedSettings ] =
		useState< LogsSettings >( getInitialSettings );
	const [ draftSettings, setDraftSettings ] =
		useState< LogsSettings >( savedSettings );

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
				setDraftSettings( savedSettings );
			}
		},
		[ isSaving, savedSettings ]
	);

	/**
	 * Persist log settings.
	 */
	const handleSave = useCallback( () => {
		if ( isSaving ) {
			return;
		}

		const nextDays = normalizeDays( draftSettings.clearLogsDays );
		const nextEnabled = draftSettings.slackEnabled;
		const nextSkipDelayed = draftSettings.slackSkipDelayed;
		const nextDiscordEnabled = draftSettings.discordEnabled;
		const nextDiscordSkipDelayed = draftSettings.discordSkipDelayed;

		setIsSaving( true );

		sendCommand< LogsSettingsPayload >( 'updatebee_save_logs_settings', {
			nonce: updatebeeLogs.saveNonce,
			clear_logs_days: nextDays,
			slack_enabled: nextEnabled,
			slack_skip_delayed: nextSkipDelayed,
			discord_enabled: nextDiscordEnabled,
			discord_skip_delayed: nextDiscordSkipDelayed,
		} )
			.then( ( response ) => {
				const payload = response.data.data;
				const nextSettings: LogsSettings = {
					clearLogsDays: normalizeDays( payload.clear_logs_days ),
					slackEnabled:
						'boolean' === typeof payload.slack_enabled
							? payload.slack_enabled
							: nextEnabled,
					slackSkipDelayed:
						'boolean' === typeof payload.slack_skip_delayed
							? payload.slack_skip_delayed
							: nextSkipDelayed,
					discordEnabled:
						'boolean' === typeof payload.discord_enabled
							? payload.discord_enabled
							: nextDiscordEnabled,
					discordSkipDelayed:
						'boolean' === typeof payload.discord_skip_delayed
							? payload.discord_skip_delayed
							: nextDiscordSkipDelayed,
				};

				setSavedSettings( nextSettings );
				setDraftSettings( nextSettings );
				onSnackbar(
					payload.message ||
						__( 'Log settings have been saved.', 'updatebee' ),
					'success'
				);
				setIsOpen( false );
			} )
			.catch( () => {
				onSnackbar(
					__( 'Log settings could not be saved.', 'updatebee' ),
					'error'
				);
			} )
			.finally( () => {
				setIsSaving( false );
			} );
	}, [ draftSettings, isSaving, onSnackbar ] );

	return (
		<Drawer.Root
			open={ isOpen }
			onOpenChange={ handleOpenChange }
			swipeDirection="right"
			disablePointerDismissal={ true }
		>
			<IconButton
				variant="outline"
				icon={ cog }
				label={ __( 'Log settings', 'updatebee' ) }
				onClick={ () => {
					if ( ! isSaving ) {
						setDraftSettings( savedSettings );
						setIsOpen( true );
					}
				} }
			/>
			<Drawer.Popup
				size="medium"
				portal={
					<Drawer.Portal className="updatebee-logs-settings-drawer-portal" />
				}
			>
				<Drawer.Header>
					<Drawer.Title>
						{ __( 'Log settings', 'updatebee' ) }
					</Drawer.Title>
				</Drawer.Header>
				<Drawer.Content>
					<Drawer.Description>
						{ updatebeeLogs.showWebhooks
							? __(
									'Choose how long logs are kept, whether finished updates are sent to Slack or Discord, and whether those messages include delayed updates.',
									'updatebee'
								)
							: __(
									'Choose how long logs are kept.',
									'updatebee'
								) }
					</Drawer.Description>
					<div className="updatebee-logs-settings-fields">
						<TextControl
							label={ __( 'Clear days', 'updatebee' ) }
							type="number"
							min={ 0 }
							value={ String( draftSettings.clearLogsDays ) }
							onChange={ ( value ) => {
								setDraftSettings( ( current ) => ( {
									...current,
									clearLogsDays: normalizeDays( value ),
								} ) );
							} }
							help={ __(
								'Automatically delete logs older than this many days. Set to 0 to keep logs forever.',
								'updatebee'
							) }
							disabled={ isSaving }
						/>
						{ updatebeeLogs.showWebhooks && (
							<>
								<ToggleControl
									label={ __(
										'Enable Slack notifications',
										'updatebee'
									) }
									checked={ draftSettings.slackEnabled }
									disabled={
										isSaving ||
										! updatebeeLogs.slackWebhookConfigured
									}
									onChange={ ( checked: boolean ) => {
										setDraftSettings( ( current ) => ( {
											...current,
											slackEnabled: checked,
										} ) );
									} }
									help={
										updatebeeLogs.slackWebhookConfigured
											? __(
													'Post a summary to Slack when automatic or manual updates finish.',
													'updatebee'
												)
											: __(
													'Add a Slack webhook URL in Advanced settings to enable this.',
													'updatebee'
												)
									}
								/>
								<ToggleControl
									label={ __(
										'Skip delayed updates',
										'updatebee'
									) }
									checked={ draftSettings.slackSkipDelayed }
									disabled={
										isSaving ||
										! updatebeeLogs.slackWebhookConfigured ||
										! draftSettings.slackEnabled
									}
									onChange={ ( checked: boolean ) => {
										setDraftSettings( ( current ) => ( {
											...current,
											slackSkipDelayed: checked,
										} ) );
									} }
									help={ __(
										'Delayed updates stay in the logs and are left out of Slack messages if enabled.',
										'updatebee'
									) }
								/>
								<ToggleControl
									label={ __(
										'Send to Discord',
										'updatebee'
									) }
									checked={ draftSettings.discordEnabled }
									disabled={
										isSaving ||
										! updatebeeLogs.discordWebhookConfigured
									}
									onChange={ ( checked: boolean ) => {
										setDraftSettings( ( current ) => ( {
											...current,
											discordEnabled: checked,
										} ) );
									} }
									help={
										updatebeeLogs.discordWebhookConfigured
											? __(
													'Post a summary to Discord when automatic or manual updates finish.',
													'updatebee'
												)
											: __(
													'Add a Discord webhook URL in Advanced settings to enable this.',
													'updatebee'
												)
									}
								/>
								<ToggleControl
									label={ __(
										'Skip delayed updates',
										'updatebee'
									) }
									checked={ draftSettings.discordSkipDelayed }
									disabled={
										isSaving ||
										! updatebeeLogs.discordWebhookConfigured ||
										! draftSettings.discordEnabled
									}
									onChange={ ( checked: boolean ) => {
										setDraftSettings( ( current ) => ( {
											...current,
											discordSkipDelayed: checked,
										} ) );
									} }
									help={ __(
										'Delayed updates stay in the logs and are left out of Discord messages if enabled.',
										'updatebee'
									) }
								/>
							</>
						) }
					</div>
				</Drawer.Content>
				<Drawer.Footer className="updatebee-logs-settings-footer">
					<Drawer.Action variant="outline" disabled={ isSaving }>
						{ __( 'Cancel', 'updatebee' ) }
					</Drawer.Action>
					<Button
						variant="solid"
						disabled={ isSaving }
						loading={ isSaving }
						loadingAnnouncement={ __(
							'Saving log settings…',
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

export default LogsSettingsDrawer;
