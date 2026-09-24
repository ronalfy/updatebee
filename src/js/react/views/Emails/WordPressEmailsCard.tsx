/**
 * WordPress native automatic update email toggle.
 */
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
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
					{ __(
						'When disabled, UpdateBee suppresses WordPress default notification emails for core, plugin, theme, and debug summary updates.',
						'updatebee'
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
						<p className="description">
							{ __(
								'Separate multiple addresses with commas.',
								'updatebee'
							) }
						</p>
						<FormTokenField
							label={ __( 'Recipients', 'updatebee' ) }
							value={ recipients }
							onChange={ ( tokens ) => {
								setRecipients(
									formTokensToStringArray( tokens )
								);
							} }
							tokenizeOnSpace={ true }
							tokenizeOnBlur={ true }
							__experimentalExpandOnFocus
							__experimentalValidateInput={ ( token ) => {
								const isValidEmail = isEmail( token );
								if ( ! isValidEmail ) {
									onNotify(
										__(
											'One or more email addresses are invalid.',
											'updatebee'
										),
										'error'
									);
									return false;
								}
								return true;
							} }
						/>
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
