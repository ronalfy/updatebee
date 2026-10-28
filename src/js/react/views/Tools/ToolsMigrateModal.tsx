/**
 * Confirmation modal for Easy Updates Manager migration.
 */
import { Button, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

type ToolsMigrateModalProps = {
	isOpen: boolean;
	isMigrating: boolean;
	onClose: () => void;
	onMigrate: () => Promise< boolean >;
};

/**
 * Ask the user to confirm importing Easy Updates Manager settings.
 *
 * @param props Modal state and migrate handler.
 */
const ToolsMigrateModal = ( props: ToolsMigrateModalProps ) => {
	const { isOpen, isMigrating, onClose, onMigrate } = props;

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
			title={ __( 'Run migration', 'updatebee' ) }
			onRequestClose={ onClose }
			shouldCloseOnClickOutside={ ! isMigrating }
			shouldCloseOnEsc={ ! isMigrating }
			isDismissible={ ! isMigrating }
		>
			<p>
				{ __(
					'This will overwrite overlapping UpdateBee settings with values from Easy Updates Manager.',
					'updatebee'
				) }
			</p>
			<p>
				{ __(
					'Are you sure you want to continue?',
					'updatebee'
				) }
			</p>
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
