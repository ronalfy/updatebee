/**
 * WordPress native automatic update email toggle.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { FormTokenField, ToggleControl } from '@wordpress/components';
import { Button, Card } from '@wordpress/ui';
import { isEmail } from '@wordpress/url';
import sendCommand from '../../../utils/SendCommand';
import validateEmail from '../../../utils/validateEmail';
import { formTokensToStringArray } from './email-form-utils';

type WordPressEmailsCardProps = {
	onNotify: ( message: string, type: 'success' | 'error' ) => void;
};

/**
 * Master toggle for WordPress automatic update notification emails.
 *
 * @param props Snackbar callback.
 */
const WordPressEmailsCard = ( props: WordPressEmailsCardProps ) => {
	const { onNotify } = props;
	const [ enabled, setEnabled ] = useState(
		updatebeeEmails.enableCoreEmails
	);
	const [ recipients, setRecipients ] = useState< string[] >(
		updatebeeEmails.coreEmailRecipients ?? []
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
						'Enter at least one recipient when WordPress emails are enabled.',
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
				enable_core_emails?: boolean;
				core_email_recipients?: string[];
				message?: string;
			} >( 'updatebee_save_wordpress_email_settings', {
				nonce: updatebeeEmails.wordpressSaveNonce,
				enabled,
				recipients,
			} );
			const saved = response.data.data.enable_core_emails;
			if ( 'boolean' === typeof saved ) {
				setEnabled( saved );
			}
			if ( Array.isArray( response.data.data.core_email_recipients ) ) {
				setRecipients( response.data.data.core_email_recipients );
			}
			onNotify(
				response.data.data.message ??
					__( 'Email settings saved.', 'updatebee' ),
				'success'
			);
		} catch {
			onNotify(
				__( 'Could not save WordPress email settings.', 'updatebee' ),
				'error'
			);
		} finally {
			setIsSaving( false );
		}
	}, [ enabled, onNotify, recipients ] );

	return (
		<Card.Root className="updatebee-emails-card">
			<Card.Header>
				<Card.Title>
					{ __( 'WordPress automatic update emails', 'updatebee' ) }
				</Card.Title>
			</Card.Header>
			<Card.Content className="updatebee-emails-card-content">
				<p className="description">
					{ sprintf(
						/* translators: %s: Plugin name. */
						__(
							'When disabled, %s suppresses WordPress default notification emails for core, plugin, theme, and debug summary updates.',
							'updatebee'
						),
						updatebeeEmails.pluginName
					) }
				</p>
				<ToggleControl
					label={ __(
						'Enable WordPress automatic update emails',
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
					</>
				) }
				<div className="updatebee-emails-actions">
					<span />
					<Button
						variant="solid"
						loading={ isSaving }
						loadingAnnouncement={ __(
							'Saving email settings…',
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

export default WordPressEmailsCard;
