/**
 * Immediate fatal error report toggle and recipients.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	CheckboxControl,
	FormTokenField,
	ToggleControl,
} from '@wordpress/components';
import { Button, Card } from '@wordpress/ui';
import { isEmail } from '@wordpress/url';
import sendCommand from '../../../utils/SendCommand';
import validateEmail from '../../../utils/validateEmail';
import { formTokensToStringArray } from './email-form-utils';

type FatalEmailsCardProps = {
	onNotify: ( message: string, type: 'success' | 'error' ) => void;
};

/**
 * Enable post-update fatal detection and choose who gets the email.
 *
 * @param props Snackbar callback.
 */
const FatalEmailsCard = ( props: FatalEmailsCardProps ) => {
	const { onNotify } = props;
	const [ enabled, setEnabled ] = useState(
		Boolean( updatebeeEmails.fatalErrorDetection )
	);
	const [ recipients, setRecipients ] = useState< string[] >(
		updatebeeEmails.fatalEmailRecipients ?? []
	);
	const [ notifySlack, setNotifySlack ] = useState(
		Boolean( updatebeeEmails.fatalNotifySlack )
	);
	const [ isSaving, setIsSaving ] = useState( false );
	const [ recipientError, setRecipientError ] = useState( '' );
	const recipientErrorTimer = useRef< number | undefined >( undefined );

	const clearRecipientError = useCallback( () => {
		if ( undefined !== recipientErrorTimer.current ) {
			window.clearTimeout( recipientErrorTimer.current );
			recipientErrorTimer.current = undefined;
		}
		setRecipientError( '' );
	}, [] );

	const showRecipientError = useCallback( ( message: string ) => {
		setRecipientError( message );
		if ( undefined !== recipientErrorTimer.current ) {
			window.clearTimeout( recipientErrorTimer.current );
		}
		recipientErrorTimer.current = window.setTimeout( () => {
			setRecipientError( '' );
			recipientErrorTimer.current = undefined;
		}, 4000 );
	}, [] );

	useEffect( () => {
		return () => {
			if ( undefined !== recipientErrorTimer.current ) {
				window.clearTimeout( recipientErrorTimer.current );
			}
		};
	}, [] );

	const handleSave = useCallback( async () => {
		if ( enabled ) {
			const addresses = recipients
				.map( ( email ) => email.trim() )
				.filter( ( email ) => '' !== email );

			if ( 0 === addresses.length ) {
				onNotify(
					__(
						'Enter at least one recipient when fatal error reports are enabled.',
						'updatebee'
					),
					'error'
				);
				return;
			}

			const invalid = addresses.find(
				( email ) => ! validateEmail( email )
			);
			if ( invalid ) {
				onNotify(
					__(
						'One or more email addresses are invalid.',
						'updatebee'
					),
					'error'
				);
				return;
			}
		}

		setIsSaving( true );
		try {
			const response = await sendCommand< {
				fatal_error_detection?: boolean;
				fatal_email_recipients?: string[];
				fatal_notify_slack?: boolean;
				message?: string;
			} >( 'updatebee_save_fatal_email_settings', {
				nonce: updatebeeEmails.fatalSaveNonce,
				enabled,
				recipients,
				notifySlack,
			} );
			const saved = response.data.data.fatal_error_detection;
			if ( 'boolean' === typeof saved ) {
				setEnabled( saved );
			}
			if ( Array.isArray( response.data.data.fatal_email_recipients ) ) {
				setRecipients( response.data.data.fatal_email_recipients );
			}
			if ( 'boolean' === typeof response.data.data.fatal_notify_slack ) {
				setNotifySlack( response.data.data.fatal_notify_slack );
			}
			onNotify(
				response.data.data.message ??
					__( 'Fatal error report settings saved.', 'updatebee' ),
				'success'
			);
		} catch {
			onNotify(
				__(
					'Could not save fatal error report settings.',
					'updatebee'
				),
				'error'
			);
		} finally {
			setIsSaving( false );
		}
	}, [ enabled, notifySlack, onNotify, recipients ] );

	return (
		<Card.Root className="updatebee-emails-card">
			<Card.Header>
				<Card.Title>
					{ __( 'Fatal error reports', 'updatebee' ) }
				</Card.Title>
			</Card.Header>
			<Card.Content className="updatebee-emails-card-content">
				<p className="description">
					{ __(
						'UpdateBee checks the front of the site and wp-admin after automatic plugin and theme updates. It never deactivates plugins. WordPress 6.6 or later may still restore a previous plugin version on its own.',
						'updatebee'
					) }
				</p>
				<ToggleControl
					label={ __(
						'Email immediately when a PHP fatal error is detected after automatic updates',
						'updatebee'
					) }
					checked={ enabled }
					onChange={ setEnabled }
				/>
				{ enabled && (
					<>
						<FormTokenField
							label={ __( 'Email Recipients', 'updatebee' ) }
							value={ recipients }
							onChange={ ( tokens ) => {
								setRecipients(
									formTokensToStringArray( tokens )
								);
							} }
							onInputChange={ ( value ) => {
								const nextValue = value.trim();
								if (
									'' === nextValue ||
									isEmail( nextValue )
								) {
									clearRecipientError();
								}
							} }
							tokenizeOnSpace={ true }
							tokenizeOnBlur={ true }
							__experimentalValidateInput={ ( token ) => {
								if ( ! isEmail( token ) ) {
									showRecipientError(
										__(
											'Enter a valid email address.',
											'updatebee'
										)
									);
									return false;
								}
								clearRecipientError();
								return true;
							} }
							help={ __(
								'Separate multiple addresses with commas, spaces, or the Enter key.',
								'updatebee'
							) }
						/>
						{ '' !== recipientError && (
							<p
								className="updatebee-emails-inline-error"
								role="alert"
							>
								{ recipientError }
							</p>
						) }
						<CheckboxControl
							label={ __(
								'Notify Slack when a fatal error is detected',
								'updatebee'
							) }
							checked={ notifySlack }
							disabled={
								! updatebeeEmails.slackWebhookConfigured
							}
							onChange={ setNotifySlack }
							help={
								updatebeeEmails.slackWebhookConfigured
									? __(
											'Uses the Slack webhook saved under Advanced settings.',
											'updatebee'
									  )
									: __(
											'Add a Slack webhook URL in Advanced settings to enable this.',
											'updatebee'
									  )
							}
						/>
					</>
				) }
				<div className="updatebee-emails-actions">
					<span />
					<Button
						variant="solid"
						loading={ isSaving }
						loadingAnnouncement={ __(
							'Saving fatal error report settings…',
							'updatebee'
						) }
						disabled={ isSaving }
						onClick={ () => {
							void handleSave();
						} }
					>
						{ __( 'Save', 'updatebee' ) }
					</Button>
				</div>
			</Card.Content>
		</Card.Root>
	);
};

export default FatalEmailsCard;
