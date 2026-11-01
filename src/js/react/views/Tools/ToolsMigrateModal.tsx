/**
 * Confirmation modal for settings migration.
 */
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

type ToolsMigrateModalProps = {
	isOpen: boolean;
	isMigrating: boolean;
	title: string;
	description: string;
	onClose: () => void;
	onMigrate: () => Promise< boolean >;
};

/**
 * Ask the user to confirm importing another plugin's settings.
 *
 * @param props Modal state and migrate handler.
 */
const ToolsMigrateModal = ( props: ToolsMigrateModalProps ) => {
	const { isOpen, isMigrating, title, description, onClose, onMigrate } =
		props;

	if ( ! isOpen ) {
		return null;
	}

	/**
	 * Run migration and close the modal on success.
	 */
	const handleConfirm = () => {
		if ( isMigrating ) {
			return;
		}

		onMigrate().then( ( didSucceed ) => {
			if ( didSucceed ) {
				onClose();
			}
		} );
	};

	return (
		<Modal
			className="updatebee-tools-migrate-modal"
			title={ title }
			onRequestClose={ onClose }
			shouldCloseOnClickOutside={ ! isMigrating }
			shouldCloseOnEsc={ ! isMigrating }
			isDismissible={ ! isMigrating }
		>
			<p>{ description }</p>
			<p>{ __( 'Are you sure you want to continue?', 'updatebee' ) }</p>
			<div className="updatebee-tools-migrate-modal-footer">
				<Button
					variant="secondary"
					disabled={ isMigrating }
					onClick={ onClose }
				>
					{ __( 'Cancel', 'updatebee' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive
					isBusy={ isMigrating }
					disabled={ isMigrating }
					onClick={ handleConfirm }
				>
					{ __( 'Run Migration', 'updatebee' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default ToolsMigrateModal;
