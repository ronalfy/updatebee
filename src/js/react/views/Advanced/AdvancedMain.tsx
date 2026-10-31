/**
 * Advanced settings (delayed automatic updates and reset options).
 */
import { TextControl, Notice, ToggleControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, useEnableWpCompatOverlaySlot } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import AdminBarMenuDrawer from './AdminBarMenuDrawer';
import AdvancedFooter, {
	type SnackbarState,
	type SnackbarType,
} from './AdvancedFooter';
import AdvancedReset, { type ResetItem } from './AdvancedReset';
import UpgradeNoticeKeywordsDrawer from './UpgradeNoticeKeywordsDrawer';

type AdvancedMessagePayload = {
	message?: string;
	delay_updates?: number;
	block_untested_updates?: boolean;
	upgrade_notice_keywords?: string;
	slack_webhook_url?: string;
	admin_bar_enabled?: boolean;
	admin_bar_show_admin?: boolean;
	admin_bar_show_frontend?: boolean;
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

const AdvancedMain = () => {
	useEnableWpCompatOverlaySlot();
	const [ delayUpdates, setDelayUpdates ] = useState(
		normalizeDays( updatebeeAdvanced.delayUpdates )
	);
	const [ blockUntestedUpdates, setBlockUntestedUpdates ] = useState(
		Boolean( updatebeeAdvanced.blockUntestedUpdates )
	);
	const [ isSaving, setIsSaving ] = useState( false );
	const [ isSavingUntested, setIsSavingUntested ] = useState( false );
	const [ isSavingKeywords, setIsSavingKeywords ] = useState( false );
	const [ isSavingAdminBar, setIsSavingAdminBar ] = useState( false );
	const [ isSavingSlack, setIsSavingSlack ] = useState( false );
	const [ isResetting, setIsResetting ] = useState( false );
	const [ snackbar, setSnackbar ] = useState< SnackbarState >( {
		isVisible: false,
		message: '',
		type: 'success',
	} );
	const [ slackWebhookUrl, setSlackWebhookUrl ] = useState(
		'string' === typeof updatebeeAdvanced.slackWebhookUrl
			? updatebeeAdvanced.slackWebhookUrl
			: ''
	);

	const isBusy =
		isSaving ||
		isSavingUntested ||
		isSavingKeywords ||
		isSavingAdminBar ||
		isSavingSlack ||
		isResetting;

	const handleSnackbarClose = useCallback( () => {
		setSnackbar( ( current ) => ( {
			...current,
			isVisible: false,
		} ) );
	}, [] );

	/**
	 * Show a snackbar notice.
	 *
	 * @param message Notice text.
	 * @param type    Notice type.
	 */
	const showSnackbar = useCallback(
		( message: string, type: SnackbarType ) => {
			setSnackbar( {
				isVisible: true,
				message,
				type,
			} );
		},
		[]
	);

	/**
	 * Persist delayed automatic update days.
	 */
	const handleSave = useCallback( () => {
		if ( isBusy ) {
			return;
		}

		const nextDays = normalizeDays( delayUpdates );
		setIsSaving( true );

		sendCommand< AdvancedMessagePayload >(
			'updatebee_save_advanced_settings',
			{
				nonce: updatebeeAdvanced.saveNonce,
				delay_updates: nextDays,
			}
		)
			.then( ( response ) => {
				const payload = response.data.data;
				setDelayUpdates( normalizeDays( payload.delay_updates ) );
				showSnackbar(
					payload.message ||
						__( 'Update delay has been saved.', 'updatebee' ),
					'success'
				);
			} )
			.catch( () => {
				showSnackbar(
					__( 'Update delay could not be saved.', 'updatebee' ),
					'error'
				);
			} )
			.finally( () => {
				setIsSaving( false );
			} );
	}, [ delayUpdates, isBusy, showSnackbar ] );

	/**
	 * Persist the untested-updates block.
	 */
	const handleSaveUntested = useCallback( () => {
		if ( isBusy ) {
			return;
		}

		setIsSavingUntested( true );

		sendCommand< AdvancedMessagePayload >(
			'updatebee_save_advanced_settings',
			{
				nonce: updatebeeAdvanced.saveNonce,
				block_untested_updates: blockUntestedUpdates ? '1' : '0',
			}
		)
			.then( ( response ) => {
				const payload = response.data.data;
				setBlockUntestedUpdates(
					Boolean( payload.block_untested_updates )
				);
				showSnackbar(
					payload.message ||
						__(
							'Untested updates setting has been saved.',
							'updatebee'
						),
					'success'
				);
			} )
			.catch( () => {
				showSnackbar(
					__(
						'Untested updates setting could not be saved.',
						'updatebee'
					),
					'error'
				);
			} )
			.finally( () => {
				setIsSavingUntested( false );
			} );
	}, [ blockUntestedUpdates, isBusy, showSnackbar ] );

	/**
	 * Persist the Slack webhook URL.
	 */
	const handleSaveSlack = useCallback( () => {
		if ( isBusy ) {
			return;
		}

		setIsSavingSlack( true );

		sendCommand< AdvancedMessagePayload >(
			'updatebee_save_advanced_settings',
			{
				nonce: updatebeeAdvanced.saveNonce,
				slack_webhook_url: slackWebhookUrl.trim(),
			}
		)
			.then( ( response ) => {
				const payload = response.data.data;
				setSlackWebhookUrl(
					'string' === typeof payload.slack_webhook_url
						? payload.slack_webhook_url
						: slackWebhookUrl.trim()
				);
				showSnackbar(
					payload.message ||
						__( 'Slack webhook has been saved.', 'updatebee' ),
					'success'
				);
			} )
			.catch( () => {
				showSnackbar(
					__( 'Slack webhook could not be saved.', 'updatebee' ),
					'error'
				);
			} )
			.finally( () => {
				setIsSavingSlack( false );
			} );
	}, [ slackWebhookUrl, isBusy, showSnackbar ] );

	/**
	 * Reset selected option groups.
	 *
	 * @param items Selected reset item ids.
	 * @return True when the request succeeds.
	 */
	const handleReset = useCallback(
		( items: ResetItem[] ): Promise< boolean > => {
			if ( isBusy ) {
				return Promise.resolve( false );
			}

			setIsResetting( true );

			return sendCommand< AdvancedMessagePayload >(
				'updatebee_reset_options',
				{
					nonce: updatebeeAdvanced.resetNonce,
					items,
				}
			)
				.then( ( response ) => {
					const message =
						response.data.data?.message ||
						__(
							'Your selected options have been reset.',
							'updatebee'
						);
					showSnackbar( message, 'success' );
					return true;
				} )
				.catch( () => {
					showSnackbar(
						__(
							'Selected options could not be reset.',
							'updatebee'
						),
						'error'
					);
					return false;
				} )
				.finally( () => {
					setIsResetting( false );
				} );
		},
		[ isBusy, showSnackbar ]
	);

	return (
		<div className="updatebee-advanced-main">
			<div className="updatebee-advanced-bento">
				<Card.Root className="updatebee-advanced-card">
					<Card.Header>
						<Card.Title>
							{ __(
								'Delay Automatic Updates on Plugins and Themes',
								'updatebee'
							) }
						</Card.Title>
					</Card.Header>
					<Card.Content className="updatebee-advanced-card-content">
						<p className="updatebee-advanced-card-description">
							{ __(
								'Occasionally, a plugin or theme update is problematic and can lead to quick fixes being released in rapid succession.',
								'updatebee'
							) }
						</p>
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Warning: If an update is fixing a security issue, then your site will be unfixed for the time of the delay.',
								'updatebee'
							) }
						</Notice>
						<div className="updatebee-advanced-card-field">
							<TextControl
								label={ __(
									'Delay Automatic Updates',
									'updatebee'
								) }
								type="number"
								min={ 0 }
								value={ String( delayUpdates ) }
								onChange={ ( value ) => {
									setDelayUpdates( normalizeDays( value ) );
								} }
								help={ __(
									'Time in days for how long to delay an update. Use 0 for no delay. Individual plugins and themes can override this from their own settings.',
									'updatebee'
								) }
								disabled={ isBusy }
							/>
						</div>
						<p className="updatebee-advanced-card-actions">
							<Button
								variant="solid"
								loading={ isSaving }
								loadingAnnouncement={ __(
									'Saving delay…',
									'updatebee'
								) }
								disabled={ isBusy }
								onClick={ handleSave }
							>
								{ __( 'Save delay', 'updatebee' ) }
							</Button>
						</p>
					</Card.Content>
				</Card.Root>

				<Card.Root className="updatebee-advanced-card">
					<Card.Header>
						<Card.Title>
							{ __( 'Block Untested Updates', 'updatebee' ) }
						</Card.Title>
					</Card.Header>
					<Card.Content className="updatebee-advanced-card-content">
						<p className="updatebee-advanced-card-description">
							{ __(
								'Automatic plugin and theme updates are skipped when the package has not been tested with this site’s WordPress version.',
								'updatebee'
							) }
						</p>
						<div className="updatebee-advanced-card-field">
							<ToggleControl
								label={ __(
									'Block untested updates',
									'updatebee'
								) }
								checked={ blockUntestedUpdates }
								disabled={ isBusy }
								onChange={ ( checked: boolean ) => {
									setBlockUntestedUpdates( checked );
								} }
								help={ __(
									'Individual plugins and themes can still allow untested updates from their own settings.',
									'updatebee'
								) }
							/>
						</div>
						<p className="updatebee-advanced-card-actions">
							<Button
								variant="solid"
								loading={ isSavingUntested }
								loadingAnnouncement={ __(
									'Saving untested updates setting…',
									'updatebee'
								) }
								disabled={ isBusy }
								onClick={ handleSaveUntested }
							>
								{ __( 'Save', 'updatebee' ) }
							</Button>
						</p>
					</Card.Content>
				</Card.Root>

				<Card.Root className="updatebee-advanced-card">
					<Card.Header>
						<Card.Title>
							{ __( 'Slack Notifications', 'updatebee' ) }
						</Card.Title>
					</Card.Header>
					<Card.Content className="updatebee-advanced-card-content">
						<p className="updatebee-advanced-card-description">
							{ __(
								'Incoming webhook URL for Slack. Used by update completion reports and optional email digests.',
								'updatebee'
							) }
						</p>
						<div className="updatebee-advanced-card-field">
							<TextControl
								label={ __( 'Slack webhook URL', 'updatebee' ) }
								type="url"
								value={ slackWebhookUrl }
								onChange={ ( value ) => {
									setSlackWebhookUrl( value );
								} }
								help={ __(
									'Save an incoming webhook URL to enable Slack notifications across the plugin.',
									'updatebee'
								) }
								disabled={ isBusy }
							/>
						</div>
						<p className="updatebee-advanced-card-actions">
							<Button
								variant="solid"
								loading={ isSavingSlack }
								loadingAnnouncement={ __(
									'Saving Slack webhook…',
									'updatebee'
								) }
								disabled={ isBusy }
								onClick={ handleSaveSlack }
							>
								{ __( 'Save webhook', 'updatebee' ) }
							</Button>
						</p>
					</Card.Content>
				</Card.Root>

				<UpgradeNoticeKeywordsDrawer
					disabled={
						isSaving ||
						isSavingUntested ||
						isSavingSlack ||
						isSavingAdminBar ||
						isResetting
					}
					onSavingChange={ setIsSavingKeywords }
					onSnackbar={ showSnackbar }
				/>

				<AdminBarMenuDrawer
					disabled={
						isSaving ||
						isSavingUntested ||
						isSavingKeywords ||
						isSavingSlack ||
						isResetting
					}
					onSavingChange={ setIsSavingAdminBar }
					onSnackbar={ showSnackbar }
				/>

				<AdvancedReset
					disabled={ isBusy }
					isResetting={ isResetting }
					onReset={ handleReset }
				/>
			</div>

			<AdvancedFooter
				snackbar={ snackbar }
				onSnackbarClose={ handleSnackbarClose }
			/>
		</div>
	);
};

export default AdvancedMain;
