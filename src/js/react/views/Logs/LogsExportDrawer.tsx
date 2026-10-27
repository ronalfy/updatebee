/**
 * Logs export drawer with CSV filters and date range.
 */
import { SelectControl, TextControl } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Drawer } from '@wordpress/ui';

type LogsExportDrawerProps = {
	disabled: boolean;
};

type UpdateOfferFilter = 'all' | 'automatic' | 'manual';
type UpdateTypeFilter = 'all' | 'core' | 'plugin' | 'theme' | 'translation';
type UpdateStatusFilter = 'all' | 'success' | 'failure' | 'blocked';

type ExportFilters = {
	updateOffer: UpdateOfferFilter;
	updateType: UpdateTypeFilter;
	updateStatus: UpdateStatusFilter;
	dateFrom: string;
	dateTo: string;
};

/**
 * Format a Date as a Y-m-d string for date inputs.
 *
 * @param date Date to format.
 * @return Date string in Y-m-d format.
 */
const formatDateInput = ( date: Date ): string => {
	const year = date.getFullYear();
	const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( date.getDate() ).padStart( 2, '0' );

	return `${ year }-${ month }-${ day }`;
};

const today = new Date();
const dateFromDefault = new Date( today );
dateFromDefault.setDate( dateFromDefault.getDate() - 60 );

const defaultFilters: ExportFilters = {
	updateOffer: 'all',
	updateType: 'all',
	updateStatus: 'all',
	dateFrom: formatDateInput( dateFromDefault ),
	dateTo: formatDateInput( today ),
};

/**
 * Build the admin-ajax CSV download URL.
 *
 * @param filters Selected export filters.
 * @return Download URL.
 */
const getExportUrl = ( filters: ExportFilters ): string => {
	const params = new URLSearchParams( {
		action: 'updatebee_export_logs',
		nonce: updatebeeLogs.exportNonce,
		update_offer: filters.updateOffer,
		update_type: filters.updateType,
		update_status: filters.updateStatus,
		date_from: filters.dateFrom,
		date_to: filters.dateTo,
	} );

	return `${ ajaxurl }?${ params.toString() }`;
};

/**
 * Export logs drawer and trigger button.
 *
 * @param props Drawer availability.
 */
const LogsExportDrawer = ( props: LogsExportDrawerProps ) => {
	const { disabled } = props;
	const [ isOpen, setIsOpen ] = useState( false );
	const [ filters, setFilters ] = useState< ExportFilters >( defaultFilters );
	const [ dateError, setDateError ] = useState( '' );

	/**
	 * Reset filters when the drawer closes.
	 *
	 * @param nextOpen Next open state.
	 */
	const handleOpenChange = useCallback( ( nextOpen: boolean ) => {
		setIsOpen( nextOpen );
		if ( ! nextOpen ) {
			setFilters( defaultFilters );
			setDateError( '' );
		}
	}, [] );

	/**
	 * Update a single export filter.
	 *
	 * @param next Partial filter values.
	 */
	const handleFilterChange = useCallback(
		( next: Partial< ExportFilters > ) => {
			setFilters( ( current ) => ( {
				...current,
				...next,
			} ) );
			setDateError( '' );
		},
		[]
	);

	/**
	 * Validate dates and start the CSV download.
	 */
	const handleExport = useCallback( () => {
		if (
			'' !== filters.dateFrom &&
			'' !== filters.dateTo &&
			filters.dateFrom > filters.dateTo
		) {
			setDateError(
				__(
					'The start date must be on or before the end date.',
					'updatebee'
				)
			);
			return;
		}

		window.location.assign( getExportUrl( filters ) );
		handleOpenChange( false );
	}, [ filters, handleOpenChange ] );

	return (
		<Drawer.Root
			open={ isOpen }
			onOpenChange={ handleOpenChange }
			swipeDirection="right"
			disablePointerDismissal={ false }
		>
			<Button
				variant="outline"
				disabled={ disabled }
				onClick={ () => {
					setIsOpen( true );
				} }
			>
				{ __( 'Export', 'updatebee' ) }
			</Button>
			<Drawer.Popup
				size="medium"
				portal={
					<Drawer.Portal className="updatebee-logs-export-drawer-portal" />
				}
			>
				<Drawer.Header>
					<Drawer.Title>
						{ __( 'Export Logs', 'updatebee' ) }
					</Drawer.Title>
				</Drawer.Header>
				<Drawer.Content>
					<Drawer.Description>
						{ __(
							'Choose filters and an optional date range, then download the matching logs as a CSV file.',
							'updatebee'
						) }
					</Drawer.Description>
					<div className="updatebee-logs-export-fields">
						<SelectControl
							label={ __( 'Update Offer', 'updatebee' ) }
							value={ filters.updateOffer }
							options={ [
								{
									label: __(
										'All Update Offers',
										'updatebee'
									),
									value: 'all',
								},
								{
									label: __( 'Automatic', 'updatebee' ),
									value: 'automatic',
								},
								{
									label: __( 'Manual', 'updatebee' ),
									value: 'manual',
								},
							] }
							onChange={ ( value ) => {
								if ( 'string' === typeof value ) {
									handleFilterChange( {
										updateOffer: value,
									} );
								}
							} }
						/>
						<SelectControl
							label={ __( 'Update Type', 'updatebee' ) }
							value={ filters.updateType }
							options={ [
								{
									label: __(
										'All Update Types',
										'updatebee'
									),
									value: 'all',
								},
								{
									label: __( 'Core', 'updatebee' ),
									value: 'core',
								},
								{
									label: __( 'Plugin', 'updatebee' ),
									value: 'plugin',
								},
								{
									label: __( 'Theme', 'updatebee' ),
									value: 'theme',
								},
								{
									label: __( 'Translation', 'updatebee' ),
									value: 'translation',
								},
							] }
							onChange={ ( value ) => {
								if ( 'string' === typeof value ) {
									handleFilterChange( {
										updateType: value,
									} );
								}
							} }
						/>
						<SelectControl
							label={ __( 'Update Status', 'updatebee' ) }
							value={ filters.updateStatus }
							options={ [
								{
									label: __(
										'All Update Statuses',
										'updatebee'
									),
									value: 'all',
								},
								{
									label: __( 'Success', 'updatebee' ),
									value: 'success',
								},
								{
									label: __( 'Failure', 'updatebee' ),
									value: 'failure',
								},
								{
									label: __( 'Blocked', 'updatebee' ),
									value: 'blocked',
								},
							] }
							onChange={ ( value ) => {
								if ( 'string' === typeof value ) {
									handleFilterChange( {
										updateStatus: value,
									} );
								}
							} }
						/>
						<TextControl
							label={ __( 'Date from', 'updatebee' ) }
							type="date"
							value={ filters.dateFrom }
							onChange={ ( value ) => {
								handleFilterChange( { dateFrom: value } );
							} }
						/>
						<TextControl
							label={ __( 'Date to', 'updatebee' ) }
							type="date"
							value={ filters.dateTo }
							onChange={ ( value ) => {
								handleFilterChange( { dateTo: value } );
							} }
						/>
						<p className="description">
							{ __(
								'Leave dates blank for all dates.',
								'updatebee'
							) }
						</p>
						{ '' !== dateError && (
							<p
								className="updatebee-logs-export-error"
								role="alert"
							>
								{ dateError }
							</p>
						) }
					</div>
				</Drawer.Content>
				<Drawer.Footer className="updatebee-logs-export-footer">
					<Drawer.Action variant="outline">
						{ __( 'Cancel', 'updatebee' ) }
					</Drawer.Action>
					<Button variant="solid" onClick={ handleExport }>
						{ __( 'Export as CSV', 'updatebee' ) }
					</Button>
				</Drawer.Footer>
			</Drawer.Popup>
		</Drawer.Root>
	);
};

export default LogsExportDrawer;
