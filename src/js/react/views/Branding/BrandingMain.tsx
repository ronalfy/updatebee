/**
 * Branding settings for white-label plugin identity.
 */
import { TextareaControl, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, useEnableWpCompatOverlaySlot } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import BrandingFooter, {
	type SnackbarState,
	type SnackbarType,
} from './BrandingFooter';

type BrandingMessagePayload = {
	message?: string;
	name?: string;
	description?: string;
	author?: string;
	url?: string;
};

const BrandingMain = () => {
	useEnableWpCompatOverlaySlot();
	const [ name, setName ] = useState( updatebeeBranding.name ?? '' );
	const [ description, setDescription ] = useState(
		updatebeeBranding.description ?? ''
	);
	const [ author, setAuthor ] = useState( updatebeeBranding.author ?? '' );
	const [ url, setUrl ] = useState( updatebeeBranding.url ?? '' );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ snackbar, setSnackbar ] = useState< SnackbarState >( {
		isVisible: false,
		message: '',
		type: 'success',
	} );

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
		( messageText: string, type: SnackbarType ) => {
			setSnackbar( {
				isVisible: true,
				message: messageText,
				type,
			} );
		},
		[]
	);

	/**
	 * Persist branding fields.
	 */
	const handleSave = useCallback( () => {
		if ( isSaving ) {
			return;
		}

		setIsSaving( true );

		sendCommand< BrandingMessagePayload >(
			'updatebee_save_branding_settings',
			{
				nonce: updatebeeBranding.saveNonce,
				name: name.trim(),
				description: description.trim(),
				author: author.trim(),
				url: url.trim(),
			}
		)
			.then( ( response ) => {
				const payload = response.data.data;
				setName(
					'string' === typeof payload.name ? payload.name : name.trim()
				);
				setDescription(
					'string' === typeof payload.description
						? payload.description
						: description.trim()
				);
				setAuthor(
					'string' === typeof payload.author
						? payload.author
						: author.trim()
				);
				setUrl(
					'string' === typeof payload.url ? payload.url : url.trim()
				);
				showSnackbar(
					payload.message ||
						__( 'Branding settings have been saved.', 'updatebee' ),
					'success'
				);
			} )
			.catch( () => {
				showSnackbar(
					__( 'Branding settings could not be saved.', 'updatebee' ),
					'error'
				);
			} )
			.finally( () => {
				setIsSaving( false );
			} );
	}, [ author, description, isSaving, name, showSnackbar, url ] );

	return (
		<div className="updatebee-branding-main">
			<Card.Root className="updatebee-branding-card">
				<Card.Header>
					<Card.Title>
						{ __( 'Plugin branding', 'updatebee' ) }
					</Card.Title>
				</Card.Header>
				<Card.Content className="updatebee-branding-card-content">
					<p className="updatebee-branding-card-description">
						{ __(
							'Change how this plugin appears on the Plugins screen, in the admin header, and in emails and Slack alerts. Leave a field empty to use the default.',
							'updatebee'
						) }
					</p>
					<TextControl
						label={ __( 'Plugin name', 'updatebee' ) }
						value={ name }
						placeholder={ updatebeeBranding.defaults.name }
						onChange={ setName }
					/>
					<TextareaControl
						label={ __( 'Description', 'updatebee' ) }
						value={ description }
						placeholder={ updatebeeBranding.defaults.description }
						onChange={ setDescription }
						rows={ 4 }
					/>
					<TextControl
						label={ __( 'Plugin author', 'updatebee' ) }
						value={ author }
						placeholder={ updatebeeBranding.defaults.author }
						onChange={ setAuthor }
					/>
					<TextControl
						label={ __( 'Plugin URL', 'updatebee' ) }
						type="url"
						value={ url }
						placeholder={ updatebeeBranding.defaults.url }
						onChange={ setUrl }
					/>
					<div className="updatebee-branding-card-actions">
						<Button
							variant="solid"
							loading={ isSaving }
							loadingAnnouncement={ __(
								'Saving branding settings…',
								'updatebee'
							) }
							disabled={ isSaving }
							onClick={ handleSave }
						>
							{ __( 'Save', 'updatebee' ) }
						</Button>
					</div>
				</Card.Content>
			</Card.Root>
			<BrandingFooter
				snackbar={ snackbar }
				onSnackbarClose={ handleSnackbarClose }
			/>
		</div>
	);
};

export default BrandingMain;
