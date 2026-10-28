/**
 * Confirmation modal for hiding the Easy Updates Manager importer.
 */
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

type ToolsHideImporterModalProps = {
	isOpen: boolean;
	isHiding: boolean;
	onClose: () => void;
	onHide: () => Promise< boolean >;
};

/**
 * Ask the user to confirm hiding the Easy Updates Manager importer.
 *
 * @param props Modal state and hide handler.
 */
const ToolsHideImporterModal = ( props: ToolsHideImporterModalProps ) => {
	const { isOpen, isHiding, onClose, onHide } = props;

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
			<p>
				{ __(
					'After you hide this, Easy Updates Manager settings can only be imported again if you reset UpdateBee\'s import options.',
					'updatebee'
				) }
			</p>
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
