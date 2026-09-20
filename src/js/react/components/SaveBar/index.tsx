import { useEffect, useRef } from 'react';
import type { MouseEvent } from 'react';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface SaveBarProps {
	onDiscardChanges: () => void;
	onSave: () => void;
	onReset: () => void;
	isSaving: boolean;
	isResetting: boolean;
	isDirtyFields: boolean | undefined;
	hasErrors: boolean | undefined;
}

const SaveBar = ( props: SaveBarProps ) => {
	const {
		onDiscardChanges,
		onSave,
		onReset,
		isSaving = false,
		isResetting = false,
		isDirtyFields = false,
		hasErrors = false,
	} = props;

	const saveBarRef = useRef< HTMLDivElement >( null );

	useEffect( () => {
		const saveBar = saveBarRef.current;
		if ( ! saveBar ) {
			return;
		}

		if ( isDirtyFields && ! hasErrors ) {
			saveBar.style.opacity = '1';
			saveBar.style.minHeight = '1px';
			saveBar.style.height = '100%';
		} else {
			saveBar.style.opacity = '0';
			saveBar.style.height = '0';
		}
	}, [ isDirtyFields, hasErrors, isSaving, isResetting ] );

	const handleDiscard = ( event: MouseEvent< HTMLButtonElement > ) => {
		event.preventDefault();
		// eslint-disable-next-line no-alert
		if ( window.confirm( __( 'Discard unsaved changes?', 'updatebee' ) ) ) {
			onDiscardChanges();
		}
	};

	const handleReset = ( event: MouseEvent< HTMLButtonElement > ) => {
		event.preventDefault();
		// eslint-disable-next-line no-alert
		const shouldReset = window.confirm(
			__( 'Reset all settings to defaults?', 'updatebee' )
		);
		if ( shouldReset ) {
			onReset();
		}
	};

	const handleSave = ( event: MouseEvent< HTMLButtonElement > ) => {
		event.preventDefault();
		onSave();
	};

	return (
		<div
			className="updatebee-admin-save-bar"
			ref={ saveBarRef }
			style={ {
				transition: 'all 0.3s ease-in-out',
				opacity: 0,
				height: 0,
				overflow: 'hidden',
			} }
		>
			<div className="updatebee-admin__tabs--content-actions">
				<div className="updatebee-admin__tabs--content-actions--left">
					<Button
						variant="primary"
						type="button"
						text={
							isSaving
								? __( 'Saving…', 'updatebee' )
								: __( 'Save Settings', 'updatebee' )
						}
						disabled={ isSaving || isResetting || hasErrors }
						onClick={ handleSave }
					/>
					<Button
						variant="secondary"
						type="button"
						text={ __( 'Discard Changes', 'updatebee' ) }
						disabled={ isSaving || isResetting || hasErrors }
						onClick={ handleDiscard }
					/>
				</div>
				<div className="updatebee-admin__tabs--content-actions--right">
					<Button
						variant="secondary"
						type="button"
						className={ isResetting ? 'is-resetting' : undefined }
						isDestructive={ true }
						text={
							isResetting
								? __( 'Resetting…', 'updatebee' )
								: __( 'Reset to Defaults', 'updatebee' )
						}
						disabled={ isSaving || isResetting || hasErrors }
						onClick={ handleReset }
					/>
				</div>
			</div>
		</div>
	);
};

export default SaveBar;
