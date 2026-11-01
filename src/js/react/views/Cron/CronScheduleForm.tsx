/**
 * Save/reset form for one update cron context.
 */
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import type { CronAjaxConfig } from './cron-config';
import type { CronScheduleFormValues, CronSchedulePayload } from './cron-types';
import {
	defaultCronScheduleValues,
	mapCrontabCommands,
	mapFormValuesToSavePayload,
	mapPayloadToFormValues,
} from './cron-form-utils';
import CronScheduleFields from './CronScheduleFields';

type CronScheduleFormProps = {
	config: CronAjaxConfig;
	fieldIdPrefix: string;
	initialValues: CronScheduleFormValues;
	onNotify: ( message: string, type: 'success' | 'error' ) => void;
};

/**
 * Next-run helper text.
 *
 * @param timestamp Next event unix time.
 * @param datetime  Formatted datetime string.
 */
const nextRunMessage = (
	timestamp: number,
	datetime: string
): string | null => {
	if ( timestamp <= 0 || ! datetime ) {
		return __(
			'Automatic update events do not appear to have the days configured for them to take place.',
			'updatebee'
		);
	}

	return `${ __( 'Next scheduled event:', 'updatebee' ) } ${ datetime }`;
};

/**
 * Cron schedule form with save and reset.
 *
 * @param props Form configuration.
 */
const CronScheduleForm = ( props: CronScheduleFormProps ) => {
	const { config, fieldIdPrefix, initialValues, onNotify } = props;

	const [ values, setValues ] = useState( initialValues );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ isResetting, setIsResetting ] = useState( false );

	const applyNextRunFromPayload = ( payload: CronSchedulePayload ) => {
		const datetime =
			typeof payload.datetime === 'string' ? payload.datetime : '';
		const timestamp =
			typeof payload.timestamp === 'number' ? payload.timestamp : 0;
		const crontabEquivalent =
			typeof payload.crontab_equivalent === 'string'
				? payload.crontab_equivalent
				: '';
		const crontabCommands = mapCrontabCommands( payload );
		setValues( ( current ) => ( {
			...current,
			datetime,
			timestamp,
			crontabEquivalent,
			crontabCommands,
		} ) );
	};

	const handleSave = useCallback( async () => {
		setIsSaving( true );
		try {
			const response = await sendCommand< CronSchedulePayload >(
				config.saveAction,
				{
					nonce: updatebeeCron.nonce,
					...mapFormValuesToSavePayload( values ),
				}
			);
			applyNextRunFromPayload( response.data.data );
			onNotify( __( 'Cron schedule saved.', 'updatebee' ), 'success' );
		} catch {
			onNotify(
				__( 'Could not save cron schedule.', 'updatebee' ),
				'error'
			);
		} finally {
			setIsSaving( false );
		}
	}, [ config.saveAction, onNotify, values ] );

	const handleReset = useCallback( async () => {
		setIsResetting( true );
		try {
			const response = await sendCommand< CronSchedulePayload >(
				config.resetAction,
				{
					nonce: updatebeeCron.nonce,
				}
			);
			const mapped = mapPayloadToFormValues( response.data.data );
			setValues( mapped );
			applyNextRunFromPayload( response.data.data );
			onNotify( __( 'Cron schedule reset.', 'updatebee' ), 'success' );
		} catch {
			onNotify(
				__( 'Could not reset cron schedule.', 'updatebee' ),
				'error'
			);
		} finally {
			setIsResetting( false );
		}
	}, [ config.resetAction, onNotify ] );

	const nextRun = nextRunMessage( values.timestamp, values.datetime );

	return (
		<div className="updatebee-cron-form">
			<CronScheduleFields
				fieldIdPrefix={ fieldIdPrefix }
				value={ values }
				onChange={ setValues }
			/>
			{ nextRun && (
				<p
					className={
						values.timestamp <= 0
							? 'updatebee-cron-next-run is-warning'
							: 'updatebee-cron-next-run'
					}
				>
					{ nextRun }
				</p>
			) }
			<div className="updatebee-cron-form-actions">
				<Button
					variant="solid"
					disabled={ isSaving || isResetting }
					onClick={ () => {
						void handleSave();
					} }
				>
					{ __( 'Save scheduling', 'updatebee' ) }
				</Button>
				<Button
					variant="outline"
					disabled={ isSaving || isResetting }
					onClick={ () => {
						void handleReset();
					} }
				>
					{ __( 'Reset', 'updatebee' ) }
				</Button>
			</div>
		</div>
	);
};

export default CronScheduleForm;

export type CronScheduleFormLoadingProps = {
	isLoading: boolean;
};

/**
 * Loading placeholder for cron cards.
 *
 * @param props Loading flag.
 */
export const CronScheduleFormLoading = (
	props: CronScheduleFormLoadingProps
) => {
	const { isLoading } = props;
	if ( ! isLoading ) {
		return null;
	}

	return (
		<p className="updatebee-cron-loading">
			{ __( 'Loading schedule…', 'updatebee' ) }
		</p>
	);
};

export { defaultCronScheduleValues };
