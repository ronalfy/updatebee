/**
 * Cron form defaults and payload mapping.
 */
import { __ } from '@wordpress/i18n';
import type {
	CronCrontabCommand,
	CronFrequency,
	CronScheduleFormValues,
	CronSchedulePayload,
} from './cron-types';

const ALL_WEEKDAYS: number[] = [ 1, 2, 3, 4, 5, 6, 7 ];

/**
 * Normalize crontab_commands from an API payload.
 *
 * @param payload Ajax success data.
 */
export const mapCrontabCommands = (
	payload: CronSchedulePayload
): CronCrontabCommand[] => {
	if ( ! Array.isArray( payload.crontab_commands ) ) {
		return [];
	}

	return payload.crontab_commands
		.map( ( item ) => {
			if (
				! item ||
				typeof item !== 'object' ||
				typeof item.label !== 'string' ||
				typeof item.command !== 'string'
			) {
				return null;
			}

			return {
				label: item.label,
				command: item.command,
			};
		} )
		.filter( ( item ): item is CronCrontabCommand => null !== item );
};

/**
 * Default form values for a cron card.
 */
export const defaultCronScheduleValues = (): CronScheduleFormValues => ( {
	schedule: 'twelve-hours',
	day: 'sunday',
	week: '1',
	startTime: '00:00',
	dayNum: '1',
	daysList: [ ...ALL_WEEKDAYS ],
	datetime: '',
	timestamp: 0,
	crontabEquivalent: '',
	crontabCommands: [],
} );

/**
 * Whether frequency uses weekday multi-select.
 *
 * @param schedule Cron frequency slug.
 */
export const scheduleUsesWeekdays = ( schedule: CronFrequency ): boolean =>
	'hourly' === schedule ||
	'two-hours' === schedule ||
	'three-hours' === schedule ||
	'six-hours' === schedule ||
	'twelve-hours' === schedule ||
	'daily' === schedule;

/**
 * Default days list when frequency changes.
 *
 * @param schedule Cron frequency slug.
 */
export const defaultDaysListForSchedule = (
	schedule: CronFrequency
): number[] => ( 'daily' === schedule ? [] : [ ...ALL_WEEKDAYS ] );

/**
 * Map API payload to form values.
 *
 * @param payload Ajax success data.
 */
export const mapPayloadToFormValues = (
	payload: CronSchedulePayload
): CronScheduleFormValues => {
	const schedule = ( payload.schedule ?? 'twelve-hours' ) as CronFrequency;
	const daysList = Array.isArray( payload.days_list )
		? payload.days_list.map( ( day ) => Number( day ) ).filter( Boolean )
		: defaultDaysListForSchedule( schedule );

	return {
		schedule,
		day: payload.day ?? 'sunday',
		week: payload.week ?? '1',
		startTime: payload.start_time ?? '00:00',
		dayNum: payload.day_num ?? '1',
		daysList,
		datetime: payload.datetime ?? '',
		timestamp:
			typeof payload.timestamp === 'number' ? payload.timestamp : 0,
		crontabEquivalent:
			typeof payload.crontab_equivalent === 'string'
				? payload.crontab_equivalent
				: '',
		crontabCommands: mapCrontabCommands( payload ),
	};
};

/**
 * Map form values to ajax save body.
 *
 * @param values Form state.
 */
export const mapFormValuesToSavePayload = (
	values: CronScheduleFormValues
): Record< string, unknown > => ( {
	schedule: values.schedule,
	day: values.day,
	week: values.week,
	start_time: values.startTime,
	day_num: values.dayNum,
	days_list: values.daysList,
} );

/**
 * Weekday labels keyed by EUM day number (1 = Sunday).
 */
export const WEEKDAY_OPTIONS: { value: number; label: string }[] = [
	{ value: 1, label: __( 'Sunday', 'updatebee' ) },
	{ value: 2, label: __( 'Monday', 'updatebee' ) },
	{ value: 3, label: __( 'Tuesday', 'updatebee' ) },
	{ value: 4, label: __( 'Wednesday', 'updatebee' ) },
	{ value: 5, label: __( 'Thursday', 'updatebee' ) },
	{ value: 6, label: __( 'Friday', 'updatebee' ) },
	{ value: 7, label: __( 'Saturday', 'updatebee' ) },
];
