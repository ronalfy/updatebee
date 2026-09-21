/**
 * Bulk select and action buttons for the plugins list.
 */
import { _n, __, sprintf } from '@wordpress/i18n';
import { CheckboxControl, Button, type IconType } from '@wordpress/components';
import { closeSmall as CloseSmallIcon } from '@wordpress/icons';
import { useFormContext, useWatch } from 'react-hook-form';

type PluginRow = {
	File: string;
};

export type Action< TPlugin extends PluginRow = PluginRow > = {
	id: string;
	label: string;
	getLabel?: ( selectedPlugins: TPlugin[] ) => string;
	icon?: IconType | null;
	isEligible: ( plugin: TPlugin ) => boolean;
	isDestructive: boolean;
	callback: ( selectedPlugins: TPlugin[] ) => void;
};

type BulkActionsProps< TPlugin extends PluginRow > = {
	plugins: TPlugin[];
	actions: Action< TPlugin >[];
};

/**
 * Selection checkbox key used by plugin rows and bulk actions.
 *
 * @param plugin Plugin row.
 */
const getPluginSelectedKey = ( plugin: PluginRow ): string =>
	`${ plugin.File }_selected`;

const BulkActions = < TPlugin extends PluginRow >(
	props: BulkActionsProps< TPlugin >
) => {
	const { plugins, actions } = props;
	const { getValues, setValue, control } =
		useFormContext< Record< string, boolean > >();

	// Re-render when form values change so counts and eligibility stay current.
	useWatch( { control } );

	const selectedPlugins = plugins.filter( ( plugin ) =>
		Boolean( getValues( getPluginSelectedKey( plugin ) ) )
	);
	const pluginsSelectedCount = selectedPlugins.length;
	const pluginsCount = plugins.length;

	const getActionButtons = () => {
		return (
			<div className="updatebee-bulk-actions-buttons">
				{ actions.map( ( action ) => {
					const eligiblePlugins = selectedPlugins.filter(
						( plugin ) => action.isEligible( plugin )
					);
					if ( 0 === eligiblePlugins.length ) {
						return null;
					}

					const buttonLabel = action.getLabel
						? action.getLabel( eligiblePlugins )
						: action.label;

					return (
						<Button
							key={ action.id }
							icon={ action.icon ?? undefined }
							label={ buttonLabel }
							isDestructive={ action.isDestructive }
							variant="secondary"
							onClick={ () => {
								action.callback( eligiblePlugins );
							} }
						>
							{ buttonLabel }
						</Button>
					);
				} ) }
				{ 0 < pluginsSelectedCount && (
					<Button
						variant="tertiary"
						icon={ CloseSmallIcon }
						label={ __( 'Cancel', 'updatebee' ) }
						onClick={ () => {
							plugins.forEach( ( plugin ) => {
								setValue(
									getPluginSelectedKey( plugin ),
									false
								);
							} );
						} }
					/>
				) }
			</div>
		);
	};

	const labelCount =
		0 < pluginsSelectedCount ? pluginsSelectedCount : pluginsCount;

	return (
		<div
			className={ [
				'updatebee-bulk-actions-container',
				0 < pluginsSelectedCount ? 'is-selected' : '',
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			<CheckboxControl
				checked={
					0 < pluginsCount && pluginsSelectedCount === pluginsCount
				}
				onChange={ ( boolValue ) => {
					plugins.forEach( ( plugin ) => {
						setValue( getPluginSelectedKey( plugin ), boolValue );
					} );
				} }
				label={ sprintf(
					/* translators: %d: number of plugins selected or visible. */
					_n( '%d Plugin', '%d Plugins', labelCount, 'updatebee' ),
					labelCount
				) }
				indeterminate={
					0 < pluginsSelectedCount &&
					pluginsSelectedCount < pluginsCount
				}
			/>
			{ 0 < pluginsSelectedCount && getActionButtons() }
		</div>
	);
};

export default BulkActions;
