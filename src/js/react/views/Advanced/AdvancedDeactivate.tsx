/**
 * Advanced deactivate card and confirmation drawer.
 */
import { ToggleControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { cog } from '@wordpress/icons';
import { Button, Card, Drawer } from '@wordpress/ui';

type AdvancedDeactivateProps = {
	disabled: boolean;
	isDeactivating: boolean;
	onDeactivate: ( clearData: boolean ) => Promise< boolean >;
};

const AdvancedDeactivate = ( props: AdvancedDeactivateProps ) => {
	const { disabled, isDeactivating, onDeactivate } = props;
	const [ isOpen, setIsOpen ] = useState( false );
	const [ removeData, setRemoveData ] = useState( false );

	/**
	 * Reset the data toggle when the drawer closes.
	 *
	 * @param nextOpen Next open state.
	 */
	const handleOpenChange = useCallback( ( nextOpen: boolean ) => {
		setIsOpen( nextOpen );
		if ( ! nextOpen ) {
			setRemoveData( false );
		}
	}, [] );

	/**
	 * Deactivate, clearing plugin data when the toggle is on.
	 */
	const handleDeactivate = useCallback( () => {
		if ( disabled || isDeactivating ) {
			return;
		}

		onDeactivate( removeData ).then( ( didSucceed ) => {
			if ( ! didSucceed ) {
				setRemoveData( false );
			}
		} );
	}, [ disabled, isDeactivating, onDeactivate, removeData ] );

	if ( ! Boolean( updatebeeAdvanced.canDeactivate ) ) {
		return null;
	}

	const pluginName = updatebeeAdvanced.pluginName.trim();

	return (
		<Drawer.Root
			open={ isOpen }
			onOpenChange={ handleOpenChange }
			swipeDirection="right"
			disablePointerDismissal={ true }
		>
			<Card.Root className="updatebee-advanced-card">
				<Card.Header>
					<Card.Title>{ __( 'Deactivate', 'updatebee' ) }</Card.Title>
				</Card.Header>
				<Card.Content className="updatebee-advanced-card-content">
					<p className="updatebee-advanced-card-description">
						{ sprintf(
							/* translators: %s: Plugin name. */
							__(
								'Deactivate %s and keep its settings, or clear settings, tables, and schedules before deactivating. The plugin stays installed until you delete it from the Plugins screen.',
								'updatebee'
							),
							pluginName
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
							{ __( 'Deactivate', 'updatebee' ) }
						</Button>
					</p>
				</Card.Content>
			</Card.Root>

			<Drawer.Popup
				size="medium"
				portal={
					<Drawer.Portal className="updatebee-advanced-deactivate-drawer-portal" />
				}
			>
				<Drawer.Header>
					<Drawer.Title>
						{ __( 'Deactivate', 'updatebee' ) }
					</Drawer.Title>
				</Drawer.Header>
				<Drawer.Content>
					<Drawer.Description>
						{ __(
							'Deactivate leaves settings, logs, and schedules in place. You can activate the plugin again and continue with the same setup.',
							'updatebee'
						) }
					</Drawer.Description>
					<div className="updatebee-advanced-card-field">
						<ToggleControl
							label={ __(
								'Check This to Remove All Plugin Data',
								'updatebee'
							) }
							checked={ removeData }
							disabled={ isDeactivating }
							onChange={ ( checked: boolean ) => {
								setRemoveData( checked );
							} }
							help={ __(
								'Removes settings, custom tables, scheduled tasks, and temporary data before deactivating. Leave this off to keep the current setup.',
								'updatebee'
							) }
						/>
					</div>
				</Drawer.Content>
				<Drawer.Footer className="updatebee-advanced-deactivate-footer">
					<Drawer.Action
						variant="outline"
						disabled={ isDeactivating }
					>
						{ __( 'Cancel', 'updatebee' ) }
					</Drawer.Action>
					<Button
						variant="solid"
						disabled={ disabled || isDeactivating }
						loading={ isDeactivating }
						loadingAnnouncement={
							removeData
								? __(
										'Clearing data and deactivating…',
										'updatebee'
								  )
								: __( 'Deactivating…', 'updatebee' )
						}
						onClick={ handleDeactivate }
					>
						{ removeData
							? __( 'Clear Data Then Deactivate', 'updatebee' )
							: __( 'Deactivate', 'updatebee' ) }
					</Button>
				</Drawer.Footer>
			</Drawer.Popup>
		</Drawer.Root>
	);
};

export default AdvancedDeactivate;
