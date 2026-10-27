/**
 * Advanced card and drawer for upgrade-notice keywords.
 */
import { TextareaControl, ToggleControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { cog } from '@wordpress/icons';
import { Button, Card, Drawer, IconButton } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import type { SnackbarType } from './AdvancedFooter';

type UpgradeNoticeKeywordsDrawerProps = {
	disabled: boolean;
	onSavingChange: ( isSaving: boolean ) => void;
	onSnackbar: ( message: string, type: SnackbarType ) => void;
};

type KeywordsPayload = {
	message?: string;
	upgrade_notice_keywords?: string;
	upgrade_notice_keywords_enabled?: boolean;
};

/**
 * Normalize localized or saved keyword text.
 *
 * @param value Incoming keywords.
 * @return Keyword text.
 */
const normalizeKeywords = ( value: unknown ): string => {
	return 'string' === typeof value ? value : '';
};

/**
 * Split keyword text into trimmed, non-empty lines.
 *
 * @param value Keyword text.
 * @return Keywords.
 */
const getKeywordList = ( value: string ): string[] => {
	return value
		.split( /\r\n|\r|\n/ )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => '' !== line );
};

/**
 * Whether any keyword line is present.
 *
 * @param value Keyword text.
 * @return True when at least one non-empty line exists.
 */
export const hasKeywords = ( value: string ): boolean => {
	return getKeywordList( value ).length > 0;
};

/**
 * Format keywords as a comma-separated preview.
 *
 * @param value Keyword text.
 * @return Preview text, truncated at 80 characters.
 */
export const formatKeywordPreview = ( value: string ): string => {
	const preview = getKeywordList( value ).join( ', ' );
	if ( preview.length <= 80 ) {
		return preview;
	}

	return preview.slice( 0, 80 ) + '…';
};

/**
 * Keyword card with a gear that opens the editor drawer.
 *
 * @param props Card availability and save callbacks.
 */
const UpgradeNoticeKeywordsDrawer = (
	props: UpgradeNoticeKeywordsDrawerProps
) => {
	const { disabled, onSavingChange, onSnackbar } = props;
	const [ isOpen, setIsOpen ] = useState( false );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ savedKeywords, setSavedKeywords ] = useState(
		normalizeKeywords( updatebeeAdvanced.upgradeNoticeKeywords )
	);
	const [ savedEnabled, setSavedEnabled ] = useState(
		Boolean( updatebeeAdvanced.upgradeNoticeKeywordsEnabled )
	);
	const [ draftKeywords, setDraftKeywords ] = useState( savedKeywords );
	const [ draftEnabled, setDraftEnabled ] = useState( savedEnabled );

	const keywordPreview = formatKeywordPreview( savedKeywords );
	const keywordPreviewFull = getKeywordList( savedKeywords ).join( ', ' );

	/**
	 * Revert drawer drafts to the last saved values.
	 */
	const resetDraft = useCallback( () => {
		setDraftKeywords( savedKeywords );
		setDraftEnabled( savedEnabled );
	}, [ savedEnabled, savedKeywords ] );

	/**
	 * Open or close the drawer and revert unsaved edits.
	 *
	 * @param nextOpen Next open state.
	 */
	const handleOpenChange = useCallback(
		( nextOpen: boolean ) => {
			if ( isSaving ) {
				return;
			}

			setIsOpen( nextOpen );
			if ( ! nextOpen ) {
				resetDraft();
			}
		},
		[ isSaving, resetDraft ]
	);

	/**
	 * Persist upgrade-notice keywords and enabled state.
	 */
	const handleSave = useCallback( () => {
		if ( isSaving || disabled ) {
			return;
		}

		setIsSaving( true );
		onSavingChange( true );

		sendCommand< KeywordsPayload >( 'updatebee_save_advanced_settings', {
			nonce: updatebeeAdvanced.saveNonce,
			upgrade_notice_keywords: draftKeywords,
			upgrade_notice_keywords_enabled: draftEnabled ? '1' : '0',
		} )
			.then( ( response ) => {
				const payload = response.data.data;
				const nextKeywords = normalizeKeywords(
					payload.upgrade_notice_keywords
				);
				const nextEnabled = Boolean(
					payload.upgrade_notice_keywords_enabled
				);
				setSavedKeywords( nextKeywords );
				setDraftKeywords( nextKeywords );
				setSavedEnabled( nextEnabled );
				setDraftEnabled( nextEnabled );
				onSnackbar(
					payload.message ||
						__(
							'Upgrade notice keywords have been saved.',
							'updatebee'
						),
					'success'
				);
				setIsOpen( false );
			} )
			.catch( () => {
				onSnackbar(
					__(
						'Upgrade notice keywords could not be saved.',
						'updatebee'
					),
					'error'
				);
			} )
			.finally( () => {
				setIsSaving( false );
				onSavingChange( false );
			} );
	}, [
		disabled,
		draftEnabled,
		draftKeywords,
		isSaving,
		onSavingChange,
		onSnackbar,
	] );

	return (
		<Drawer.Root
			open={ isOpen }
			onOpenChange={ handleOpenChange }
			swipeDirection="right"
			disablePointerDismissal={ true }
		>
			<Card.Root className="updatebee-advanced-card">
				<Card.Header>
					<div className="updatebee-advanced-card-header">
						<Card.Title>
							{ __( 'Upgrade Notice Keywords', 'updatebee' ) }
						</Card.Title>
						<div className="updatebee-advanced-keywords-controls">
							<span
								className={
									savedEnabled
										? 'updatebee-advanced-status-badge is-active'
										: 'updatebee-advanced-status-badge'
								}
							>
								{ savedEnabled
									? __( 'Enabled', 'updatebee' )
									: __( 'Disabled', 'updatebee' ) }
							</span>
							<IconButton
								variant="outline"
								icon={ cog }
								label={ __(
									'Upgrade notice keywords',
									'updatebee'
								) }
								disabled={ disabled || isSaving }
								onClick={ () => {
									if ( ! isSaving && ! disabled ) {
										resetDraft();
										setIsOpen( true );
									}
								} }
							/>
						</div>
					</div>
				</Card.Header>
				<Card.Content className="updatebee-advanced-card-content">
					<p className="updatebee-advanced-card-description">
						{ __(
							'If a plugin or theme upgrade notice contains one of these keywords, that item is updated automatically. Matching is case-insensitive.',
							'updatebee'
						) }
					</p>
					{ savedEnabled && hasKeywords( savedKeywords ) && (
						<p
							className="updatebee-advanced-keywords-preview"
							title={ keywordPreviewFull }
						>
							<strong>{ __( 'Keywords:', 'updatebee' ) }</strong>{ ' ' }
							{ keywordPreview }
						</p>
					) }
				</Card.Content>
			</Card.Root>
			<Drawer.Popup
				size="medium"
				portal={
					<Drawer.Portal className="updatebee-advanced-keywords-drawer-portal" />
				}
			>
				<Drawer.Header>
					<Drawer.Title>
						{ __( 'Upgrade Notice Keywords', 'updatebee' ) }
					</Drawer.Title>
				</Drawer.Header>
				<Drawer.Content>
					<Drawer.Description>
						{ __(
							'Enter one keyword per line. If an upgrade notice contains a keyword, that plugin or theme is updated automatically. Matching is case-insensitive.',
							'updatebee'
						) }
					</Drawer.Description>
					<div className="updatebee-advanced-keywords-fields">
						<ToggleControl
							label={ __(
								'Enable upgrade notice keywords',
								'updatebee'
							) }
							checked={ draftEnabled }
							disabled={ isSaving }
							onChange={ ( checked: boolean ) => {
								setDraftEnabled( checked );
							} }
							help={ __(
								'Turn this off to keep your keyword list without matching upgrade notices.',
								'updatebee'
							) }
						/>
						<TextareaControl
							label={ __(
								'Upgrade notice keywords',
								'updatebee'
							) }
							value={ draftKeywords }
							onChange={ setDraftKeywords }
							help={ __( 'One keyword per line.', 'updatebee' ) }
							disabled={ isSaving }
							rows={ 8 }
						/>
					</div>
				</Drawer.Content>
				<Drawer.Footer className="updatebee-advanced-keywords-footer">
					<Drawer.Action variant="outline" disabled={ isSaving }>
						{ __( 'Cancel', 'updatebee' ) }
					</Drawer.Action>
					<Button
						variant="solid"
						disabled={ isSaving || disabled }
						loading={ isSaving }
						loadingAnnouncement={ __(
							'Saving upgrade notice keywords…',
							'updatebee'
						) }
						onClick={ handleSave }
					>
						{ __( 'Save', 'updatebee' ) }
					</Button>
				</Drawer.Footer>
			</Drawer.Popup>
		</Drawer.Root>
	);
};

export default UpgradeNoticeKeywordsDrawer;
