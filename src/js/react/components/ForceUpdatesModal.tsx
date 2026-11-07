/**
 * Confirmation modal before forcing automatic updates.
 */
import { Button, CheckboxControl, Modal, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

type ForceUpdatesModalProps = {
	isOpen: boolean;
	delayEnabled: boolean;
	onClose: () => void;
	onConfirm: ( skipDelay: boolean ) => void;
};

/**
 * Ask the user to confirm force updates, with an optional delay skip.
 *
 * @param props Modal state and confirm handler.
 */
const ForceUpdatesModal = ( props: ForceUpdatesModalProps ) => {
	const { isOpen, delayEnabled, onClose, onConfirm } = props;
	const [ skipDelay, setSkipDelay ] = useState( false );

	useEffect( () => {
		if ( isOpen ) {
			setSkipDelay( false );
		}
	}, [ isOpen ] );

	if ( ! isOpen ) {
		return null;
	}

	/**
	 * Confirm the run and pass the skip choice.
	 */
	const handleConfirm = () => {
		onConfirm( delayEnabled && skipDelay );
	};

	return (
		<Modal
			className="updatebee-force-updates-modal"
			title={ __( 'Force updates', 'updatebee' ) }
			onRequestClose={ onClose }
		>
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'A backup is recommended before forcing updates.',
					'updatebee'
				) }
			</Notice>
			<p>
				{ __(
					'This runs automatic updates now for core, plugins, themes, and translations that are allowed to update.',
					'updatebee'
				) }
			</p>
			{ delayEnabled && (
				<CheckboxControl
					label={ __( 'Skip the update delay', 'updatebee' ) }
					help={ __(
						'Plugins and themes that are still waiting will update on this run.',
						'updatebee'
					) }
					checked={ skipDelay }
					onChange={ ( checked: boolean ) => {
						setSkipDelay( checked );
					} }
				/>
			) }
			<div className="updatebee-force-updates-modal-footer">
				<Button variant="secondary" onClick={ onClose }>
					{ __( 'Cancel', 'updatebee' ) }
				</Button>
				<Button variant="primary" onClick={ handleConfirm }>
					{ __( 'Force updates', 'updatebee' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default ForceUpdatesModal;
