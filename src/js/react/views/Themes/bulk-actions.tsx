/**
 * Bulk select and action buttons for the themes list.
 */
import { _n, __, sprintf } from '@wordpress/i18n';
import { CheckboxControl, Button, type IconType } from '@wordpress/components';
import { closeSmall as CloseSmallIcon } from '@wordpress/icons';
import {
	useWatch,
	type Control,
	type UseFormGetValues,
	type UseFormSetValue,
} from 'react-hook-form';
import { getThemeFieldKey } from './theme-fields';

type ThemeRow = {
	Stylesheet: string;
};

export type Action< TTheme extends ThemeRow = ThemeRow > = {
	id: string;
	label: string;
	getLabel?: ( selectedThemes: TTheme[] ) => string;
	icon?: IconType | null;
	isEligible: ( theme: TTheme ) => boolean;
	isDestructive: boolean;
	callback: ( selectedThemes: TTheme[] ) => void;
};

type BulkFormValues = Record< string, boolean >;

type BulkActionsProps< TTheme extends ThemeRow > = {
	themes: TTheme[];
	actions: Action< TTheme >[];
	isBusy?: boolean;
	control: Control< BulkFormValues >;
	getValues: UseFormGetValues< BulkFormValues >;
	setValue: UseFormSetValue< BulkFormValues >;
};

/**
 * Selection checkbox key used by theme rows and bulk actions.
 *
 * @param theme Theme row.
 */
const getThemeSelectedKey = ( theme: ThemeRow ): string =>
	getThemeFieldKey( theme, 'selected' );

const BulkActions = < TTheme extends ThemeRow >(
	props: BulkActionsProps< TTheme >
) => {
	const {
		themes,
		actions,
		isBusy = false,
		control,
		getValues,
		setValue,
	} = props;

	// Re-render when form values change so counts and eligibility stay current.
	useWatch( { control } );

	const selectedThemes = themes.filter( ( theme ) =>
		Boolean( getValues( getThemeSelectedKey( theme ) ) )
	);
	const themesSelectedCount = selectedThemes.length;
	const themesCount = themes.length;

	const getActionButtons = () => {
		return (
			<div className="updatebee-bulk-actions-buttons">
				{ actions.map( ( action ) => {
					const eligibleThemes = selectedThemes.filter( ( theme ) =>
						action.isEligible( theme )
					);
					if ( 0 === eligibleThemes.length ) {
						return null;
					}

					const buttonLabel = action.getLabel
						? action.getLabel( eligibleThemes )
						: action.label;

					return (
						<Button
							key={ action.id }
							icon={ action.icon ?? undefined }
							label={ buttonLabel }
							isDestructive={ action.isDestructive }
							variant="secondary"
							disabled={ isBusy }
							onClick={ () => {
								action.callback( eligibleThemes );
							} }
						>
							{ buttonLabel }
						</Button>
					);
				} ) }
				{ 0 < themesSelectedCount && (
					<Button
						variant="tertiary"
						icon={ CloseSmallIcon }
						label={ __( 'Cancel', 'updatebee' ) }
						disabled={ isBusy }
						onClick={ () => {
							themes.forEach( ( theme ) => {
								setValue(
									getThemeSelectedKey( theme ),
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
		0 < themesSelectedCount ? themesSelectedCount : themesCount;

	return (
		<div
			className={ [
				'updatebee-bulk-actions-container',
				0 < themesSelectedCount ? 'is-selected' : '',
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			<CheckboxControl
				checked={
					0 < themesCount && themesSelectedCount === themesCount
				}
				disabled={ isBusy }
				onChange={ ( boolValue ) => {
					themes.forEach( ( theme ) => {
						setValue( getThemeSelectedKey( theme ), boolValue );
					} );
				} }
				label={ sprintf(
					/* translators: %d: number of themes selected or visible. */
					_n( '%d Theme', '%d Themes', labelCount, 'updatebee' ),
					labelCount
				) }
				indeterminate={
					0 < themesSelectedCount &&
					themesSelectedCount < themesCount
				}
			/>
			{ 0 < themesSelectedCount && getActionButtons() }
		</div>
	);
};

export default BulkActions;
