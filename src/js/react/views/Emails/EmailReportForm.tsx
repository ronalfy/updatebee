/**
 * Shared success/failure email report form.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	FormTokenField,
	RadioControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { isEmail } from '@wordpress/url';
import { FormProvider, useForm, useFormContext } from 'react-hook-form';
import sendCommand from '../../../utils/SendCommand';
import validateEmail from '../../../utils/validateEmail';
import type {
	EmailReportFormValues,
	EmailReportKind,
	SendSchedule,
} from './email-types';
import type { ReportAjaxConfig } from './email-report-config';
import {
	formTokensToStringArray,
	mapResetPayloadToFormValues,
} from './email-form-utils';
import EmailScheduleFields from './EmailScheduleFields';

type EmailReportFormProps = {
	config: ReportAjaxConfig;
	defaultValues: EmailReportFormValues;
	previewUrl: string;
	onSaved: ( sendSchedule: SendSchedule ) => void;
	onNotify: ( message: string, type: 'success' | 'error' ) => void;
	deliveryOptions: { label: string; value: SendSchedule }[] | null;
	reportKind: EmailReportKind;
};

/**
 * Validate recipient tokens when delivery is enabled.
 *
 * @param values     Form values.
 * @param reportKind
 * @return Error message or empty string.
 */
const validateRecipients = (
	values: EmailReportFormValues,
	reportKind: EmailReportKind
): string => {
	if ( 'none' === values.sendSchedule ) {
		return '';
	}

	if (
		'updates-available' === reportKind &&
		'custom' === values.sendSchedule &&
		values.notifySlack
	) {
		return '';
	}

	const allEmails = [ ...values.toField, ...values.bccField ];

	if ( 0 === allEmails.length ) {
		return __(
			'Enter at least one recipient when delivery is enabled.',
			'updatebee'
		);
	}

	const invalid = allEmails.find(
		( email ) => ! validateEmail( email.trim() )
	);
	if ( invalid ) {
		return __( 'One or more email addresses are invalid.', 'updatebee' );
	}

	return '';
};

/**
 * Validate recipients for an immediate available-updates send.
 *
 * @param values Form values.
 * @return Error message or empty string.
 */
const validateSendNowRecipients = ( values: EmailReportFormValues ): string => {
	if ( values.notifySlack ) {
		return '';
	}

	const allEmails = [ ...values.toField, ...values.bccField ];

	if ( 0 === allEmails.length ) {
		return __(
			'Enter at least one recipient or enable Slack notifications.',
			'updatebee'
		);
	}

	const invalid = allEmails.find(
		( email ) => ! validateEmail( email.trim() )
	);
	if ( invalid ) {
		return __( 'One or more email addresses are invalid.', 'updatebee' );
	}

	return '';
};

type EmailReportFormFieldsProps = {
	config: ReportAjaxConfig;
	previewUrl: string;
	onSaved: ( sendSchedule: SendSchedule ) => void;
	onNotify: ( message: string, type: 'success' | 'error' ) => void;
	deliveryOptions: { label: string; value: SendSchedule }[] | null;
	reportKind: EmailReportKind;
};

/**
 * Form fields using react-hook-form context.
 *
 * @param props Handlers and ajax config.
 */
const EmailReportFormFields = ( props: EmailReportFormFieldsProps ) => {
	const {
		config,
		previewUrl,
		onSaved,
		onNotify,
		deliveryOptions,
		reportKind,
	} = props;
	const [ isSaving, setIsSaving ] = useState( false );
	const [ isResetting, setIsResetting ] = useState( false );
	const [ isSendingNow, setIsSendingNow ] = useState( false );

	const { watch, setValue, reset, handleSubmit } =
		useFormContext< EmailReportFormValues >();

	const sendSchedule = watch( 'sendSchedule' );
	const scheduleOptions = watch( 'scheduleOptions' );
	const toField = watch( 'toField' );
	const bccField = watch( 'bccField' );

	const onSubmit = useCallback(
		async ( values: EmailReportFormValues ) => {
			const recipientError = validateRecipients( values, reportKind );
			if ( recipientError ) {
				onNotify( recipientError, 'error' );
				return;
			}

			setIsSaving( true );
			try {
				await sendCommand( config.saveAction, {
					nonce: config.saveNonce,
					toField: values.toField,
					bccField: values.bccField,
					subject: values.subject,
					includeCore: values.includeCore,
					includePlugins: values.includePlugins,
					includeThemes: values.includeThemes,
					includeTranslations: values.includeTranslations,
					sendSchedule: values.sendSchedule,
					scheduleOptions: values.scheduleOptions,
					notifySlack: values.notifySlack,
					logCutoffDays: values.logCutoffDays,
				} );
				onSaved( values.sendSchedule );
				onNotify(
					__( 'Email report settings saved.', 'updatebee' ),
					'success'
				);
			} catch {
				onNotify(
					__( 'Could not save email report settings.', 'updatebee' ),
					'error'
				);
			} finally {
				setIsSaving( false );
			}
		},
		[ config, onNotify, onSaved, reportKind ]
	);

	const handleReset = useCallback( async () => {
		setIsResetting( true );
		try {
			const response = await sendCommand< Record< string, unknown > >(
				config.resetAction,
				{
					nonce: config.resetNonce,
				}
			);
			const nextValues = mapResetPayloadToFormValues(
				response.data.data as Record< string, unknown >
			);
			reset( nextValues );
			onSaved( nextValues.sendSchedule );
			onNotify(
				__( 'Email report settings reset.', 'updatebee' ),
				'success'
			);
		} catch {
			onNotify(
				__( 'Could not reset email report settings.', 'updatebee' ),
				'error'
			);
		} finally {
			setIsResetting( false );
		}
	}, [ config, onNotify, onSaved, reset ] );

	const handlePreview = useCallback( () => {
		if ( previewUrl ) {
			window.open( previewUrl, '_blank', 'noopener,noreferrer' );
		}
	}, [ previewUrl ] );

	const handleSendNow = useCallback( async () => {
		if (
			'updates-available' !== reportKind ||
			! config.sendNowAction ||
			! config.sendNowNonce
		) {
			return;
		}

		const values = watch();
		const recipientError = validateSendNowRecipients( values );
		if ( recipientError ) {
			onNotify( recipientError, 'error' );
			return;
		}

		setIsSendingNow( true );
		try {
			const response = await sendCommand< { message?: string } >(
				config.sendNowAction,
				{
					nonce: config.sendNowNonce,
					toField: values.toField,
					bccField: values.bccField,
					subject: values.subject,
					includeCore: values.includeCore,
					includePlugins: values.includePlugins,
					includeThemes: values.includeThemes,
					includeTranslations: values.includeTranslations,
					notifySlack: values.notifySlack,
				}
			);
			const payload = response.data.data;
			onNotify(
				payload.message ||
					__(
						'Available updates report has been sent.',
						'updatebee'
					),
				'success'
			);
		} catch ( error ) {
			const fallback = __(
				'Could not send the available updates report.',
				'updatebee'
			);
			const message =
				error instanceof Error && error.message
					? error.message
					: fallback;
			onNotify( message, 'error' );
		} finally {
			setIsSendingNow( false );
		}
	}, [ config, onNotify, reportKind, watch ] );

	const updateDeliveryOptions = [
		{
			label: __( 'Off', 'updatebee' ),
			value: 'none',
		},
		{
			label: __( 'Send after each automatic update run', 'updatebee' ),
			value: 'immediately',
		},
		{
			label: __( 'Send on a schedule', 'updatebee' ),
			value: 'custom',
		},
	];

	return (
		<form
			className="updatebee-emails-card-content"
			onSubmit={ handleSubmit( onSubmit ) }
		>
			<p className="description">
				{ __(
					'Separate multiple addresses with commas.',
					'updatebee'
				) }
			</p>
			<FormTokenField
				label={ __( 'To', 'updatebee' ) }
				value={ toField }
				onChange={ ( tokens ) => {
					setValue( 'toField', formTokensToStringArray( tokens ), {
						shouldDirty: true,
					} );
				} }
				tokenizeOnSpace={ true }
				tokenizeOnBlur={ true }
				__experimentalValidateInput={ ( token ) => {
					if ( ! isEmail( token ) ) {
						onNotify(
							__(
								'One or more email addresses are invalid.',
								'updatebee'
							),
							'error'
						);
						return false;
					}
					return true;
				} }
				help={ __(
					'Separate multiple addresses with commas, spaces, or the Enter key.',
					'updatebee'
				) }
			/>
			<FormTokenField
				label={ __( 'Bcc', 'updatebee' ) }
				value={ bccField }
				onChange={ ( tokens ) => {
					setValue( 'bccField', formTokensToStringArray( tokens ), {
						shouldDirty: true,
					} );
				} }
				tokenizeOnSpace={ true }
				tokenizeOnBlur={ true }
				__experimentalValidateInput={ ( token ) => {
					if ( ! isEmail( token ) ) {
						onNotify(
							__(
								'One or more email addresses are invalid.',
								'updatebee'
							),
							'error'
						);
						return false;
					}
					return true;
				} }
				help={ __(
					'Separate multiple addresses with commas, spaces, or the Enter key.',
					'updatebee'
				) }
			/>
			<TextControl
				label={ __( 'Subject', 'updatebee' ) }
				help={ __(
					'Leave blank to use the default subject line.',
					'updatebee'
				) }
				value={ watch( 'subject' ) }
				onChange={ ( value ) => {
					setValue( 'subject', value, { shouldDirty: true } );
				} }
			/>
			<fieldset className="updatebee-emails-include-row">
				<legend>{ __( 'Include update types', 'updatebee' ) }</legend>
				<CheckboxControl
					label={ __( 'Core', 'updatebee' ) }
					checked={ watch( 'includeCore' ) }
					onChange={ ( checked ) => {
						setValue( 'includeCore', checked, {
							shouldDirty: true,
						} );
					} }
				/>
				<CheckboxControl
					label={ __( 'Plugins', 'updatebee' ) }
					checked={ watch( 'includePlugins' ) }
					onChange={ ( checked ) => {
						setValue( 'includePlugins', checked, {
							shouldDirty: true,
						} );
					} }
				/>
				<CheckboxControl
					label={ __( 'Themes', 'updatebee' ) }
					checked={ watch( 'includeThemes' ) }
					onChange={ ( checked ) => {
						setValue( 'includeThemes', checked, {
							shouldDirty: true,
						} );
					} }
				/>
				<CheckboxControl
					label={ __( 'Translations', 'updatebee' ) }
					checked={ watch( 'includeTranslations' ) }
					onChange={ ( checked ) => {
						setValue( 'includeTranslations', checked, {
							shouldDirty: true,
						} );
					} }
				/>
			</fieldset>
			{ 'updates-available' !== reportKind && (
				<TextControl
					label={ __( 'Log lookback (days)', 'updatebee' ) }
					help={ __(
						'Maximum number of days of logs to include. The actual range starts from the last sent email.',
						'updatebee'
					) }
					type="number"
					min={ 1 }
					max={ 365 }
					value={ watch( 'logCutoffDays' ) }
					onChange={ ( value ) => {
						const num = parseInt( value, 10 );
						setValue(
							'logCutoffDays',
							isNaN( num ) || num < 1 ? 7 : num,
							{ shouldDirty: true }
						);
					} }
				/>
			) }
			<RadioControl
				label={ __( 'Delivery', 'updatebee' ) }
				selected={ sendSchedule }
				options={ deliveryOptions || updateDeliveryOptions }
				onChange={ ( value ) => {
					setValue( 'sendSchedule', value as SendSchedule, {
						shouldDirty: true,
					} );
				} }
			/>
			{ 'updates-available' === reportKind && (
				<CheckboxControl
					label={ __(
						'Notify Slack when this report runs',
						'updatebee'
					) }
					checked={ watch( 'notifySlack' ) }
					disabled={ ! updatebeeEmails.slackWebhookConfigured }
					onChange={ ( checked ) => {
						setValue( 'notifySlack', checked, {
							shouldDirty: true,
						} );
					} }
					help={
						updatebeeEmails.slackWebhookConfigured
							? __(
									'Uses the Slack webhook saved under Advanced settings. Applies to scheduled runs and Send now.',
									'updatebee'
							  )
							: __(
									'Add a Slack webhook URL in Advanced settings to enable this.',
									'updatebee'
							  )
					}
				/>
			) }
			{ 'custom' === sendSchedule && (
				<EmailScheduleFields
					value={ scheduleOptions }
					onChange={ ( next ) => {
						setValue( 'scheduleOptions', next, {
							shouldDirty: true,
						} );
					} }
				/>
			) }
			<div className="updatebee-emails-actions">
				<div className="updatebee-emails-actions-start">
					<Button
						variant="secondary"
						onClick={ handlePreview }
						disabled={ ! previewUrl }
					>
						{ __( 'Preview email', 'updatebee' ) }
					</Button>
					{ 'updates-available' === reportKind &&
						config.sendNowAction &&
						config.sendNowNonce && (
							<Button
								variant="secondary"
								onClick={ () => {
									void handleSendNow();
								} }
								disabled={
									isSaving || isResetting || isSendingNow
								}
								isBusy={ isSendingNow }
							>
								{ __( 'Send now', 'updatebee' ) }
							</Button>
						) }
				</div>
				<div className="updatebee-emails-actions-start">
					<Button
						variant="secondary"
						onClick={ () => {
							void handleReset();
						} }
						disabled={ isSaving || isResetting }
						isBusy={ isResetting }
					>
						{ __( 'Reset', 'updatebee' ) }
					</Button>
					<Button
						variant="primary"
						type="submit"
						disabled={ isSaving || isResetting || isSendingNow }
						isBusy={ isSaving }
					>
						{ __( 'Save', 'updatebee' ) }
					</Button>
				</div>
			</div>
		</form>
	);
};

/**
 * Report form with provider boundary.
 *
 * @param props Form props including default values.
 */
const EmailReportForm = ( props: EmailReportFormProps ) => {
	const { defaultValues, config, previewUrl, onSaved, onNotify, reportKind } =
		props;
	const form = useForm< EmailReportFormValues >( {
		defaultValues,
	} );
	const { reset } = form;

	useEffect( () => {
		reset( defaultValues );
	}, [ defaultValues, reset ] );

	return (
		<FormProvider { ...form }>
			<EmailReportFormFields
				config={ config }
				previewUrl={ previewUrl }
				onSaved={ onSaved }
				onNotify={ onNotify }
				deliveryOptions={ props.deliveryOptions || null }
				reportKind={ reportKind }
			/>
		</FormProvider>
	);
};

export default EmailReportForm;

export type EmailReportFormLoadingProps = {
	isLoading: boolean;
};

/**
 * Loading placeholder for report cards.
 *
 * @param props Loading flag.
 */
export const EmailReportFormLoading = (
	props: EmailReportFormLoadingProps
) => {
	if ( ! props.isLoading ) {
		return null;
	}

	return (
		<div className="updatebee-emails-card-content">
			<Spinner />
		</div>
	);
};
