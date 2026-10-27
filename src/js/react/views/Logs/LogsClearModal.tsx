/**
 * Confirmation modal for clearing all logs.
 */
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

type LogsClearModalProps = {
	isOpen: boolean;
	isClearing: boolean;
	onClose: () => void;
	onClear: () => Promise< boolean >;
};

/**
 * Ask the user to confirm deleting all log records.
 *
 * @param props Modal state and clear handler.
 */
const LogsClearModal = ( props: LogsClearModalProps ) => {
	const { isOpen, isClearing, onClose, onClear } = props;

	if ( ! isOpen ) {
		return null;
	}

	/**
	 * Clear logs and close the modal on success.
	 */
	const handleConfirm = () => {
		if ( isClearing ) {
			return;
		}

		onClear().then( ( didSucceed ) => {
			if ( didSucceed ) {
				onClose();
			}
		} );
	};

	return (
		<Modal
			className="updatebee-logs-clear-modal"
			title={ __( 'Clear logs', 'updatebee' ) }
			onRequestClose={ onClose }
			shouldCloseOnClickOutside={ ! isClearing }
			shouldCloseOnEsc={ ! isClearing }
			isDismissible={ ! isClearing }
		>
			<p>
				{ __(
					'This will permanently delete all log records. This action cannot be undone.',
					'updatebee'
				) }
			</p>
			<p>
				{ __(
					'Are you sure you want to clear the logs?',
					'updatebee'
				) }
			</p>
			<div className="updatebee-logs-clear-modal-footer">
				<Button
					variant="secondary"
					disabled={ isClearing }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'updatebee' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive
					isBusy={ isClearing }
					disabled={ isClearing }
					onClick={ handleConfirm }
				>
					{ __( 'Clear logs', 'updatebee' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default LogsClearModal;
