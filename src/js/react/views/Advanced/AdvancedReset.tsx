/**
 * Advanced reset options card and drawer.
 */
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { cog } from '@wordpress/icons';
import { Button, Card, CheckboxControl, Drawer } from '@wordpress/ui';

export type ResetItem =
	| 'plugin_auto'
	| 'plugin_blocked'
	| 'theme_auto'
	| 'theme_blocked'
	| 'translation_auto'
	| 'translation_blocked'
	| 'importers'
	| 'all';

type ResetSelection = Record< ResetItem, boolean >;

type AdvancedResetProps = {
	disabled: boolean;
	isResetting: boolean;
	onReset: ( items: ResetItem[] ) => Promise< boolean >;
};

const defaultSelection: ResetSelection = {
	plugin_auto: false,
	plugin_blocked: false,
	theme_auto: false,
	theme_blocked: false,
	translation_auto: false,
	translation_blocked: false,
	importers: false,
	all: false,
};

const resetItems: Array< {
	id: ResetItem;
	label: string;
	description?: string;
} > = [
	{
		id: 'plugin_auto',
		label: __( 'Plugin Auto-Updates', 'updatebee' ),
	},
	{
		id: 'plugin_blocked',
		label: __( 'Plugin Blocked Updates', 'updatebee' ),
	},
	{
		id: 'theme_auto',
		label: __( 'Theme Auto-Updates', 'updatebee' ),
	},
	{
		id: 'theme_blocked',
		label: __( 'Theme Blocked Updates', 'updatebee' ),
	},
	{
		id: 'translation_auto',
		label: __( 'Translation Auto-Updates', 'updatebee' ),
	},
	{
		id: 'translation_blocked',
		label: __( 'Translation Blocked Updates', 'updatebee' ),
	},
	{
		id: 'importers',
		label: __( 'Reset importers', 'updatebee' ),
		description: sprintf(
			/* translators: %s: Plugin name. */
			__(
				'Restores hidden importer cards on the Tools screen. This does not reset %s settings.',
				'updatebee'
			),
			updatebeeAdvanced.pluginName
		),
	},
	{
		id: 'all',
		label: __( 'Reset All Options', 'updatebee' ),
	},
];

const AdvancedReset = ( props: AdvancedResetProps ) => {
	const { disabled, isResetting, onReset } = props;
	const [ isOpen, setIsOpen ] = useState( false );
	const [ selection, setSelection ] =
		useState< ResetSelection >( defaultSelection );

	const hasSelection = Object.values( selection ).some( Boolean );
	const canSubmit = hasSelection && ! disabled && ! isResetting;

	/**
	 * Reset checkbox state when the drawer closes.
	 *
	 * @param nextOpen Next open state.
	 */
	const handleOpenChange = useCallback( ( nextOpen: boolean ) => {
		setIsOpen( nextOpen );
		if ( ! nextOpen ) {
			setSelection( defaultSelection );
		}
	}, [] );

	/**
	 * Toggle a reset checkbox.
	 *
	 * @param id      Checkbox item id.
	 * @param checked Next checked state.
	 */
	const handleToggle = useCallback( ( id: ResetItem, checked: boolean ) => {
		setSelection( ( current ) => {
			if ( 'all' === id ) {
				return {
					...defaultSelection,
					all: checked,
				};
			}

			return {
				...current,
				[ id ]: checked,
				all: false,
			};
		} );
	}, [] );

	/**
	 * Submit selected reset items.
	 */
	const handleSubmit = useCallback( () => {
		if ( ! canSubmit ) {
			return;
		}

		const items = selection.all
			? [ 'all' as const ]
			: ( Object.keys( selection ) as ResetItem[] ).filter(
					( key ) => 'all' !== key && selection[ key ]
			  );

		onReset( items ).then( ( didSucceed ) => {
			if ( didSucceed ) {
				handleOpenChange( false );
			}
		} );
	}, [ canSubmit, handleOpenChange, onReset, selection ] );

	return (
		<Drawer.Root
			open={ isOpen }
			onOpenChange={ handleOpenChange }
			swipeDirection="right"
			disablePointerDismissal={ true }
		>
			<Card.Root className="updatebee-advanced-card">
				<Card.Header>
					<Card.Title>
						{ __( 'Reset Options', 'updatebee' ) }
					</Card.Title>
				</Card.Header>
				<Card.Content className="updatebee-advanced-card-content">
					<p className="updatebee-advanced-card-description">
						{ __(
							'If you would like to reset various sections of options, you may do so below.',
							'updatebee'
						) }
					</p>
					<p className="updatebee-advanced-card-actions">
						<Button
							variant="solid"
							disabled={ disabled }
							onClick={ () => {
								setIsOpen( true );
							} }
						>
							<Button.Icon icon={ cog } />
							{ __( 'Reset', 'updatebee' ) }
						</Button>
					</p>
				</Card.Content>
			</Card.Root>

			<Drawer.Popup
				size="medium"
				portal={
					<Drawer.Portal className="updatebee-advanced-reset-drawer-portal" />
				}
			>
				<Drawer.Header>
					<Drawer.Title>
						{ __( 'Reset Options', 'updatebee' ) }
					</Drawer.Title>
				</Drawer.Header>
				<Drawer.Content>
					<Drawer.Description>
						{ __(
							'Select the option groups you want to reset. Nothing is selected by default.',
							'updatebee'
						) }
					</Drawer.Description>
					<ul className="updatebee-advanced-reset-list">
						{ resetItems.map( ( item ) => {
							const isAll = 'all' === item.id;
							const isItemDisabled =
								isResetting || ( ! isAll && selection.all );

							return (
								<li key={ item.id }>
									<CheckboxControl
										name={ item.id }
										label={ item.label }
										checked={ selection[ item.id ] }
										disabled={ isItemDisabled }
										onCheckedChange={ ( checked ) => {
											handleToggle(
												item.id,
												true === checked
											);
										} }
									/>
									{ item.description ? (
										<p className="updatebee-advanced-reset-help">
											{ item.description }
										</p>
									) : null }
								</li>
							);
						} ) }
					</ul>
				</Drawer.Content>
				<Drawer.Footer className="updatebee-advanced-reset-footer">
					<Drawer.Action variant="outline" disabled={ isResetting }>
						{ __( 'Cancel', 'updatebee' ) }
					</Drawer.Action>
					<Button
						variant="solid"
						disabled={ ! canSubmit }
						loading={ isResetting }
						loadingAnnouncement={ __(
							'Resetting selected options…',
							'updatebee'
						) }
						onClick={ handleSubmit }
					>
						{ __( 'Reset', 'updatebee' ) }
					</Button>
				</Drawer.Footer>
			</Drawer.Popup>
		</Drawer.Root>
	);
};

export default AdvancedReset;
