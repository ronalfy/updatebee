/**
 * Plugins screen: load and populate plugin auto-update rows.
 */
import {
	Button,
	CheckboxControl,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Controller,
	FormProvider,
	useForm,
	useFormContext,
} from 'react-hook-form';
import sendCommand from '../../../utils/SendCommand';
import { ErrorFallback } from '../../components/ErrorBoundary';
import Loader from '../../components/Loader';

type PluginIcons = {
	'1x'?: string;
	'2x'?: string;
};

export type PluginItem = {
	File: string;
	Slug: string;
	Name: string;
	Description?: string;
	Version?: string;
	AuthorName?: string;
	AuthorURI?: string;
	PluginURI?: string;
	Details?: string;
	url?: string;
	id?: string;
	icons?: PluginIcons;
	Active: boolean;
	Autoupdates: boolean;
	Blocked: boolean;
	PatchesOnly: boolean;
	LastUpdated: string;
};

type AutoUpdatesStatus = 'enabled' | 'custom' | 'disabled' | 'blocked';

type PluginsPayload = {
	plugins?: Record< string, PluginItem >;
	auto_updates_enabled?: AutoUpdatesStatus;
};

type PluginsFormValues = Record< string, boolean >;

const noop = (): void => {
	// Save is out of scope for this pass.
};

const isDotOrgPlugin = ( plugin: PluginItem ): boolean => {
	if ( ! plugin.id ) {
		return false;
	}

	return /w\.org/i.test( plugin.id );
};

const getPluginIconPath = ( plugin: PluginItem ): string => {
	if ( ! plugin.icons ) {
		return '';
	}

	return plugin.icons[ '2x' ] || plugin.icons[ '1x' ] || '';
};

const getStatusNotice = (
	status: AutoUpdatesStatus
): { message: string; status: 'success' | 'warning' | 'error' | 'info' } => {
	if ( 'enabled' === status ) {
		return {
			status: 'success',
			message: __(
				'Automatic updates are enabled for all plugins.',
				'updatebee'
			),
		};
	}

	if ( 'custom' === status ) {
		return {
			status: 'info',
			message: __(
				'Automatic updates are enabled, but only for the plugins that are manually enabled.',
				'updatebee'
			),
		};
	}

	if ( 'blocked' === status ) {
		return {
			status: 'error',
			message: __(
				'All updates are blocked. You will no longer receive any plugin updates and may be a security risk.',
				'updatebee'
			),
		};
	}

	return {
		status: 'warning',
		message: __(
			'Automatic updates are disabled for all plugins.',
			'updatebee'
		),
	};
};

const PluginsList = ( props: {
	plugins: PluginItem[];
	autoUpdatesStatus: AutoUpdatesStatus;
} ) => {
	const { plugins, autoUpdatesStatus } = props;
	const { control, watch } = useFormContext< PluginsFormValues >();
	const [ search, setSearch ] = useState( '' );
	const [ filter, setFilter ] = useState<
		| ''
		| 'all'
		| 'activated'
		| 'deactivated'
		| 'auto-updates-enabled'
		| 'auto-updates-disabled'
		| 'blocked'
		| 'unblocked'
		| 'patches-only'
		| 'third-party'
		| 'org-plugin'
	>( '' );
	const [ bulkAction, setBulkAction ] = useState<
		'' | 'enable' | 'disable' | 'block' | 'unblock' | 'patches'
	>( '' );
	const notice = getStatusNotice( autoUpdatesStatus );

	const visiblePlugins = useMemo( () => {
		return plugins.filter( ( plugin ) => {
			if ( filter && 'all' !== filter ) {
				switch ( filter ) {
					case 'activated':
						if ( ! plugin.Active ) {
							return false;
						}
						break;
					case 'deactivated':
						if ( plugin.Active ) {
							return false;
						}
						break;
					case 'auto-updates-enabled':
						if ( ! plugin.Autoupdates ) {
							return false;
						}
						break;
					case 'auto-updates-disabled':
						if ( plugin.Autoupdates ) {
							return false;
						}
						break;
					case 'blocked':
						if ( ! plugin.Blocked ) {
							return false;
						}
						break;
					case 'unblocked':
						if ( plugin.Blocked ) {
							return false;
						}
						break;
					case 'patches-only':
						if ( ! plugin.PatchesOnly ) {
							return false;
						}
						break;
					case 'third-party':
						if ( isDotOrgPlugin( plugin ) ) {
							return false;
						}
						break;
					case 'org-plugin':
						if ( ! isDotOrgPlugin( plugin ) ) {
							return false;
						}
						break;
					default:
						return false;
				}
			}

			if (
				search &&
				! plugin.Name.toLowerCase().includes( search.toLowerCase() )
			) {
				return false;
			}

			return true;
		} );
	}, [ plugins, search, filter ] );

	return (
		<>
			<h1>{ __( 'Plugins', 'updatebee' ) }</h1>
			<Notice
				className="updatebee-plugin-notice"
				status={ notice.status }
				isDismissible={ false }
			>
				{ notice.message }
			</Notice>
			<div className="updatebee-link-row">
				<ul>
					<li>
						<Button variant="link" onClick={ noop }>
							{ __( 'Enable All', 'updatebee' ) }
						</Button>
					</li>
					<li>
						<Button variant="link" onClick={ noop }>
							{ __( 'Disable All', 'updatebee' ) }
						</Button>
					</li>
					<li>
						<Button variant="link" onClick={ noop }>
							{ __( 'Block All', 'updatebee' ) }
						</Button>
					</li>
					<li>
						<Button variant="link" onClick={ noop }>
							{ __( 'Unblock All', 'updatebee' ) }
						</Button>
					</li>
				</ul>
			</div>
			<div className="updatebee-bulk-row">
				<div className="updatebee-bulk-actions">
					<SelectControl
						label={ __( 'Bulk Actions', 'updatebee' ) }
						value={ bulkAction }
						options={ [
							{
								label: __( 'Select an Action', 'updatebee' ),
								value: '',
							},
							{
								label: __(
									'Enable Automatic Updates',
									'updatebee'
								),
								value: 'enable',
							},
							{
								label: __(
									'Disable Automatic Updates',
									'updatebee'
								),
								value: 'disable',
							},
							{
								label: __(
									'Block Selected Plugins',
									'updatebee'
								),
								value: 'block',
							},
							{
								label: __(
									'Unblock Selected Plugins',
									'updatebee'
								),
								value: 'unblock',
							},
							{
								label: __( 'Enable Patches Only', 'updatebee' ),
								value: 'patches',
							},
						] }
						onChange={ ( value ) => {
							setBulkAction( value );
						} }
					/>
					<Button
						variant="secondary"
						onClick={ noop }
						disabled={ '' === bulkAction }
					>
						{ __( 'Apply', 'updatebee' ) }
					</Button>
				</div>
				<div className="updatebee-filter-actions">
					<SelectControl
						label={ __( 'Filter', 'updatebee' ) }
						value={ filter }
						options={ [
							{
								label: __( 'Select a Filter', 'updatebee' ),
								value: '',
							},
							{ label: __( 'All', 'updatebee' ), value: 'all' },
							{
								label: __( 'Activated', 'updatebee' ),
								value: 'activated',
							},
							{
								label: __( 'Deactivated', 'updatebee' ),
								value: 'deactivated',
							},
							{
								label: __(
									'Automatic Updates Enabled',
									'updatebee'
								),
								value: 'auto-updates-enabled',
							},
							{
								label: __(
									'Automatic Updates Disabled',
									'updatebee'
								),
								value: 'auto-updates-disabled',
							},
							{
								label: __( 'Blocked', 'updatebee' ),
								value: 'blocked',
							},
							{
								label: __( 'Unblocked', 'updatebee' ),
								value: 'unblocked',
							},
							{
								label: __( 'Patches Only', 'updatebee' ),
								value: 'patches-only',
							},
							{
								label: __( 'Third-party Plugins', 'updatebee' ),
								value: 'third-party',
							},
							{
								label: __(
									'WordPress.org Plugins',
									'updatebee'
								),
								value: 'org-plugin',
							},
						] }
						onChange={ ( value ) => {
							setFilter( value );
						} }
					/>
				</div>
				<div className="updatebee-plugin-list-search">
					<TextControl
						label={ __( 'Search Plugins', 'updatebee' ) }
						value={ search }
						onChange={ ( value ) => {
							setSearch( value );
						} }
					/>
				</div>
			</div>
			<div className="updatebee-plugin-list-wrapper">
				{ visiblePlugins.map( ( plugin ) => {
					const iconPath = getPluginIconPath( plugin );
					const hasIcon = '' !== iconPath;
					const isDotOrg = isDotOrgPlugin( plugin );
					const autoKey = `${ plugin.File }_auto_update`;
					const patchesKey = `${ plugin.File }_patches_only`;
					const blockKey = `${ plugin.File }_block_updates`;
					const selectedKey = `${ plugin.File }_selected`;
					const isBlocked = Boolean( watch( blockKey ) );
					const autoEnabled = Boolean( watch( autoKey ) );

					return (
						<div
							key={ plugin.File }
							className={ [
								'updatebee-plugin-item',
								hasIcon
									? 'updatebee-plugin-has-icon'
									: 'updatebee-plugin-has-svg-icon',
							].join( ' ' ) }
						>
							<div className="updatebee-plugin-column updatebee-plugin-column-checkbox">
								<Controller
									name={ selectedKey }
									control={ control }
									render={ ( {
										field: { value, onChange },
									} ) => (
										<CheckboxControl
											checked={ Boolean( value ) }
											onChange={ onChange }
											label=""
										/>
									) }
								/>
							</div>
							<div className="updatebee-plugin-column updatebee-plugin-column-icon">
								{ hasIcon && (
									<img
										className="updatebee-plugin-icon"
										src={ iconPath }
										alt={ plugin.Name }
										width={ 128 }
										height={ 128 }
									/>
								) }
								{ ! hasIcon && (
									<svg
										className="updatebee-plugin-icon updatebee-icon-svg"
										aria-hidden="true"
									>
										<use href="#icon-plug" />
									</svg>
								) }
							</div>
							<div className="updatebee-plugin-column updatebee-plugin-column-main">
								<h2 className="updatebee-plugin-title">
									{ plugin.Name }
								</h2>
								<h3 className="updatebee-plugin-slug">
									{ plugin.Slug }
								</h3>
								{ plugin.Description && (
									<p className="updatebee-plugin-description">
										{ plugin.Description }
									</p>
								) }
								<table className="form-table">
										<tbody>
											{ plugin.AuthorName && (
												<tr>
													<th scope="row">
														{ __( 'By:', 'updatebee' ) }
													</th>
													<td>
														{ plugin.AuthorURI ? (
															<a
																href={
																	plugin.AuthorURI
																}
																target="_blank"
																rel="noopener noreferrer"
															>
																{
																	plugin.AuthorName
																}
															</a>
														) : (
															<span>
																{
																	plugin.AuthorName
																}
															</span>
														) }
														{ isDotOrg &&
															plugin.Details && (
																<>
																	<span> | </span>
																	<a
																		href={
																			plugin.Details
																		}
																		className="thickbox open-plugin-details-modal"
																		data-title={
																			plugin.Name
																		}
																		aria-label={ sprintf(
																			/* translators: %s: Plugin name. */
																			__(
																				'More information about %s',
																				'updatebee'
																			),
																			plugin.Name
																		) }
																	>
																		{ __(
																			'Plugin Details',
																			'updatebee'
																		) }
																	</a>
																</>
															) }
														{ plugin.PluginURI && (
															<>
																<span> | </span>
																<a
																	href={
																		plugin.PluginURI
																	}
																	target="_blank"
																	rel="noopener noreferrer"
																>
																	{ __(
																		'Plugin Homepage',
																		'updatebee'
																	) }
																</a>
															</>
														) }
													</td>
												</tr>
											) }
											<tr>
												<th scope="row">
													{ __(
														'Last Updated:',
														'updatebee'
													) }
												</th>
												<td>{ plugin.LastUpdated }</td>
											</tr>
											<tr>
												<th scope="row">
													{ __(
														'Update Method:',
														'updatebee'
													) }
												</th>
												<td>
													{ plugin.Autoupdates &&
														plugin.PatchesOnly &&
														! plugin.Blocked &&
														__(
															'Patches only',
															'updatebee'
														) }
													{ plugin.Autoupdates &&
														! plugin.PatchesOnly &&
														! plugin.Blocked &&
														__(
															'Automatic',
															'updatebee'
														) }
													{ ! plugin.Autoupdates &&
														! plugin.Blocked &&
														__(
															'Manual',
															'updatebee'
														) }
													{ plugin.Blocked &&
														__(
															'Blocked',
															'updatebee'
														) }
												</td>
											</tr>
											<tr>
												<th scope="row">
													{ __( 'Hosted:', 'updatebee' ) }
												</th>
												<td>
													{ isDotOrg && plugin.url ? (
														<a
															href={ plugin.url }
															target="_blank"
															rel="noopener noreferrer"
														>
															{ __(
																'WordPress Plugin Directory',
																'updatebee'
															) }
														</a>
													) : (
														__(
															'Third-party Plugin',
															'updatebee'
														)
													) }
												</td>
											</tr>
											<tr>
												<th scope="row">
													{ __(
														'Activation Status:',
														'updatebee'
													) }
												</th>
												<td>
													{ plugin.Active
														? __(
																'Activated',
																'updatebee'
														)
														: __(
																'Deactivated',
																'updatebee'
														) }
												</td>
											</tr>
											<tr>
												<th scope="row">
													{ __(
														'Version:',
														'updatebee'
													) }
												</th>
												<td>{ plugin.Version }</td>
											</tr>
										</tbody>
								</table>
							</div>
							<div></div>
							<div className="updatebee-plugin-column updatebee-plugin-column-info">
								<div className="updatebee-plugin-actions">
									<Controller
										name={ autoKey }
										control={ control }
										render={ ( {
											field: { value, onChange },
										} ) => (
											<ToggleControl
												label={ __(
													'Enable Automatic Updates',
													'updatebee'
												) }
												checked={ Boolean( value ) }
												onChange={ onChange }
												help={ __(
													'Enable automatic updates for this plugin.',
													'updatebee'
												) }
												disabled={ isBlocked }
											/>
										) }
									/>
									<Controller
										name={ patchesKey }
										control={ control }
										render={ ( {
											field: { value, onChange },
										} ) => (
											<ToggleControl
												label={ __(
													'Patches Only',
													'updatebee'
												) }
												checked={ Boolean( value ) }
												onChange={ onChange }
												help={ __(
													'Limit automatic updates for this plugin to patch releases.',
													'updatebee'
												) }
												disabled={
													isBlocked || ! autoEnabled
												}
											/>
										) }
									/>
									<Controller
										name={ blockKey }
										control={ control }
										render={ ( {
											field: { value, onChange },
										} ) => (
											<ToggleControl
												label={ __(
													'Block All Updates',
													'updatebee'
												) }
												checked={ Boolean( value ) }
												onChange={ onChange }
												help={ __(
													'Blocking plugins helps prevent the plugin from being manually or automatically updated.',
													'updatebee'
												) }
											/>
										) }
									/>
								</div>
							</div>
							
							<div
								className="updatebee-plugin-column updatebee-plugin-column-separator"
								aria-hidden="true"
							></div>
							
						</div>
					);
				} ) }
			</div>
		</>
	);
};

const PluginsMain = () => {
	const methods = useForm< PluginsFormValues >( {
		defaultValues: {},
	} );
	const { reset } = methods;
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState( false );
	const [ loadAttempt, setLoadAttempt ] = useState( 0 );
	const [ plugins, setPlugins ] = useState< PluginItem[] >( [] );
	const [ autoUpdatesStatus, setAutoUpdatesStatus ] =
		useState< AutoUpdatesStatus >( 'disabled' );

	useEffect( () => {
		let cancelled = false;

		setLoading( true );
		setLoadError( false );

		sendCommand< PluginsPayload >( 'updatebee_retrieve_plugins', {
			nonce: updatebeePlugins.retrieveNonce,
		} )
			.then( ( response ) => {
				const data = response.data?.data;
				if ( ! data?.plugins ) {
					throw new Error( 'Invalid response.' );
				}

				if ( cancelled ) {
					return;
				}

				const pluginItems = Object.values( data.plugins );
				const values: PluginsFormValues = {};
				pluginItems.forEach( ( plugin ) => {
					values[ `${ plugin.File }_auto_update` ] = Boolean(
						plugin.Autoupdates
					);
					values[ `${ plugin.File }_patches_only` ] = Boolean(
						plugin.PatchesOnly
					);
					values[ `${ plugin.File }_block_updates` ] = Boolean(
						plugin.Blocked
					);
					values[ `${ plugin.File }_selected` ] = false;
				} );

				setPlugins( pluginItems );
				setAutoUpdatesStatus( data.auto_updates_enabled || 'disabled' );
				reset( values );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setLoadError( true );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ loadAttempt, reset ] );

	if ( loading ) {
		return <Loader />;
	}

	if ( loadError ) {
		return (
			<ErrorFallback
				heading={ __( 'Plugins', 'updatebee' ) }
				message={ __(
					'Plugins could not be loaded. Try again or reload the page.',
					'updatebee'
				) }
				onRetry={ () => {
					setLoadAttempt( ( attempt ) => attempt + 1 );
				} }
			/>
		);
	}

	return (
		<FormProvider { ...methods }>
			<PluginsList
				plugins={ plugins }
				autoUpdatesStatus={ autoUpdatesStatus }
			/>
		</FormProvider>
	);
};

export default PluginsMain;
