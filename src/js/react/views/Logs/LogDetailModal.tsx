/**
 * Modal for log notes and stack trace.
 */
import { Button, Modal, TabPanel } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { LogItem } from './LogsMain';

export type LogDetailPayload = {
	notes: string;
	stacktrace: string;
};

type LogDetailModalProps = {
	log: LogItem | null;
	isOpen: boolean;
	isLoading: boolean;
	error: string;
	detail: LogDetailPayload | null;
	onClose: () => void;
};

/**
 * Render notes body text.
 *
 * @param notes.notes
 * @param notes       Raw notes.
 */
const NotesContent = ( { notes }: { notes: string } ) => (
	<div className="updatebee-log-detail updatebee-log-detail--notes">
		{ notes }
	</div>
);

/**
 * Render stack trace body text.
 *
 * @param stacktrace.stacktrace
 * @param stacktrace            Formatted stack trace.
 */
const StacktraceContent = ( { stacktrace }: { stacktrace: string } ) => (
	<pre className="updatebee-log-detail updatebee-log-detail--stacktrace">
		{ stacktrace }
	</pre>
);

/**
 * Copy the active tab content to the clipboard.
 *
 * @param text Text to copy.
 */
const copyToClipboard = ( text: string ) => {
	if ( '' === text || ! navigator.clipboard ) {
		return;
	}

	void navigator.clipboard.writeText( text );
};

/**
 * Tabbed or single-pane modal for log detail fields.
 *
 * @param props Modal state and content.
 */
const LogDetailModal = ( props: LogDetailModalProps ) => {
	const { log, isOpen, isLoading, error, detail, onClose } = props;

	if ( ! isOpen || ! log ) {
		return null;
	}

	const title = log.Slug
		? sprintf(
				/* translators: 1: Log name, 2: Log slug. */
				__( 'Details — %1$s (%2$s)', 'updatebee' ),
				log.Name,
				log.Slug
		  )
		: sprintf(
				/* translators: %s: Log name. */
				__( 'Details — %s', 'updatebee' ),
				log.Name
		  );

	const hasNotes = Boolean( detail?.notes.trim() );
	const hasStacktrace = Boolean( detail?.stacktrace.trim() );

	let body = null;

	if ( isLoading ) {
		body = (
			<p className="updatebee-log-detail-loading">
				{ __( 'Loading…', 'updatebee' ) }
			</p>
		);
	} else if ( '' !== error ) {
		body = <p className="updatebee-log-detail-error">{ error }</p>;
	} else if ( detail ) {
		if ( hasNotes && hasStacktrace ) {
			body = (
				<TabPanel
					className="updatebee-log-detail-tabs"
					activeClass="is-active"
					initialTabName="notes"
					tabs={ [
						{
							name: 'notes',
							title: __( 'Notes', 'updatebee' ),
						},
						{
							name: 'stacktrace',
							title: __( 'Stack trace', 'updatebee' ),
						},
					] }
				>
					{ ( tab ) => (
						<div className="updatebee-log-detail-tab-panel">
							<div className="updatebee-log-detail-tab-actions">
								<Button
									variant="secondary"
									onClick={ () =>
										copyToClipboard(
											'notes' === tab.name
												? detail.notes
												: detail.stacktrace
										)
									}
								>
									{ __( 'Copy', 'updatebee' ) }
								</Button>
							</div>
							{ 'notes' === tab.name ? (
								<NotesContent notes={ detail.notes } />
							) : (
								<StacktraceContent
									stacktrace={ detail.stacktrace }
								/>
							) }
						</div>
					) }
				</TabPanel>
			);
		} else if ( hasNotes ) {
			body = (
				<div className="updatebee-log-detail-single">
					<div className="updatebee-log-detail-tab-actions">
						<Button
							variant="secondary"
							onClick={ () => copyToClipboard( detail.notes ) }
						>
							{ __( 'Copy', 'updatebee' ) }
						</Button>
					</div>
					<NotesContent notes={ detail.notes } />
				</div>
			);
		} else if ( hasStacktrace ) {
			body = (
				<div className="updatebee-log-detail-single">
					<div className="updatebee-log-detail-tab-actions">
						<Button
							variant="secondary"
							onClick={ () =>
								copyToClipboard( detail.stacktrace )
							}
						>
							{ __( 'Copy', 'updatebee' ) }
						</Button>
					</div>
					<StacktraceContent stacktrace={ detail.stacktrace } />
				</div>
			);
		} else {
			body = (
				<p>
					{ __(
						'No notes or stack trace are available for this entry.',
						'updatebee'
					) }
				</p>
			);
		}
	}

	return (
		<Modal
			className="updatebee-log-detail-modal"
			title={ title }
			onRequestClose={ onClose }
			shouldCloseOnClickOutside
			shouldCloseOnEsc
		>
			{ body }
		</Modal>
	);
};

export default LogDetailModal;
