/**
 * Inline plugin note editor.
 */
import { Button, TextareaControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import sendCommand from '../../../utils/SendCommand';

type PluginNotePlugin = {
	File: string;
	Notes: string;
	NoteNonce: string;
};

type PluginNoteProps = {
	plugin: PluginNotePlugin;
	disabled?: boolean;
	onNotesChange: ( file: string, notes: string ) => void;
	showSnackbar: (
		message: string,
		type: 'success' | 'error' | 'warning' | 'info'
	) => void;
};

type SaveNotePayload = {
	notes?: string;
};

/**
 * Read-only plugin note with inline edit, save, and remove.
 *
 * @param props Component props.
 */
const PluginNote = ( props: PluginNoteProps ) => {
	const { plugin, disabled = false, onNotesChange, showSnackbar } = props;
	const notes = plugin.Notes || '';
	const hasNotes = '' !== notes.trim();
	const [ isEditing, setIsEditing ] = useState( false );
	const [ draft, setDraft ] = useState( notes );
	const [ isBusy, setIsBusy ] = useState( false );
	const isDisabled = disabled || isBusy;

	/**
	 * Open the note editor with the current saved value.
	 */
	const startEditing = () => {
		setDraft( notes );
		setIsEditing( true );
	};

	/**
	 * Close the editor and restore the saved note.
	 */
	const cancelEditing = () => {
		setDraft( notes );
		setIsEditing( false );
	};

	/**
	 * Persist note text. Empty notes remove the saved row.
	 *
	 * @param nextNotes Note text to save.
	 */
	const saveNotes = ( nextNotes: string ) => {
		setIsBusy( true );
		sendCommand< SaveNotePayload >( 'updatebee_save_plugin_note', {
			file: plugin.File,
			nonce: plugin.NoteNonce,
			notes: nextNotes,
		} )
			.then( ( response ) => {
				const savedNotes = response.data?.data?.notes || '';
				onNotesChange( plugin.File, savedNotes );
				setDraft( savedNotes );
				setIsEditing( false );
				showSnackbar(
					'' === savedNotes.trim()
						? __( 'Plugin note removed.', 'updatebee' )
						: __( 'Plugin note saved.', 'updatebee' ),
					'success'
				);
			} )
			.catch( () => {
				showSnackbar(
					__( 'Plugin note could not be saved.', 'updatebee' ),
					'error'
				);
			} )
			.finally( () => {
				setIsBusy( false );
			} );
	};

	if ( isEditing ) {
		return (
			<div className="updatebee-plugin-note">
				<TextareaControl
					label={ __( 'Plugin note', 'updatebee' ) }
					value={ draft }
					onChange={ setDraft }
					disabled={ isDisabled }
					rows={ 4 }
				/>
				<div className="updatebee-plugin-note-actions">
					<Button
						variant="primary"
						onClick={ () => saveNotes( draft ) }
						disabled={ isDisabled }
					>
						{ __( 'Save', 'updatebee' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ cancelEditing }
						disabled={ isDisabled }
					>
						{ __( 'Cancel', 'updatebee' ) }
					</Button>
					{ hasNotes && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => saveNotes( '' ) }
							disabled={ isDisabled }
						>
							{ __( 'Remove', 'updatebee' ) }
						</Button>
					) }
				</div>
			</div>
		);
	}

	return (
		<div className="updatebee-plugin-note">
			{ hasNotes && (
				<>
					<h3>{ __( 'Plugin note', 'updatebee' ) }</h3>
					<p className="updatebee-plugin-note-text">{ notes }</p>
				</>
			) }
			<Button
				variant="secondary"
				onClick={ startEditing }
				disabled={ isDisabled }
			>
				{ hasNotes
					? __( 'Edit note', 'updatebee' )
					: __( 'Add note', 'updatebee' ) }
			</Button>
		</div>
	);
};

export default PluginNote;
