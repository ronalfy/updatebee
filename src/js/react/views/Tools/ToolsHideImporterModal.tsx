/**
 * Confirmation modal for hiding a settings importer.
 */
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

type ToolsHideImporterModalProps = {
	isOpen: boolean;
	isHiding: boolean;
	description: string;
	onClose: () => void;
	onHide: () => Promise< boolean >;
};

/**
 * Ask the user to confirm hiding an importer card.
 *
 * @param props Modal state and hide handler.
 */
const ToolsHideImporterModal = ( props: ToolsHideImporterModalProps ) => {
	const { isOpen, isHiding, description, onClose, onHide } = props;

	if ( ! isOpen ) {
		return null;
	}

	/**
	 * Hide the importer and close the modal on success.
	 */
	const handleConfirm = () => {
		if ( isHiding ) {
			return;
		}

		onHide().then( ( didSucceed ) => {
			if ( didSucceed ) {
				onClose();
			}
		} );
	};

	return (
		<Modal
			className="updatebee-tools-hide-importer-modal"
			title={ __( 'Hide importer?', 'updatebee' ) }
			onRequestClose={ onClose }
			shouldCloseOnClickOutside={ ! isHiding }
			shouldCloseOnEsc={ ! isHiding }
			isDismissible={ ! isHiding }
		>
			<p>{ description }</p>
			<div className="updatebee-tools-hide-importer-modal-footer">
				<Button
					variant="secondary"
					disabled={ isHiding }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'updatebee' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive
					isBusy={ isHiding }
					disabled={ isHiding }
					onClick={ handleConfirm }
				>
					{ __( 'Hide importer', 'updatebee' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default ToolsHideImporterModal;
