/**
 * WordPress native automatic update email toggle.
 */
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import { Button, Card } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';

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
	const [ isSaving, setIsSaving ] = useState( false );

	const handleSave = useCallback( async () => {
		setIsSaving( true );
		try {
			const response = await sendCommand< {
				enable_core_emails?: boolean;
				message?: string;
			} >( 'updatebee_save_wordpress_email_settings', {
				nonce: updatebeeEmails.wordpressSaveNonce,
				enabled,
			} );
			const saved = response.data.data.enable_core_emails;
			if ( 'boolean' === typeof saved ) {
				setEnabled( saved );
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
	}, [ enabled, onNotify ] );

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
