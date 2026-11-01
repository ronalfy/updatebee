/**
 * Map API payloads to form values.
 */
import type {
	EmailReportFormValues,
	ScheduleOptions,
	SendSchedule,
} from './email-types';

type RetrievePayload = {
	to_field?: string[];
	bcc_field?: string[];
	subject?: string;
	include_core?: boolean;
	include_plugins?: boolean;
	include_themes?: boolean;
	include_translations?: boolean;
	notify_slack?: boolean;
	send_schedule?: SendSchedule;
	schedule_options?: Partial< ScheduleOptions >;
	schedule?: string;
	day?: string;
	week?: string;
	start_time?: string;
	day_num?: string | number;
	previewurl?: string;
	log_cutoff_days?: number;
};

/**
 * Normalize week value for the schedule UI.
 *
 * @param week Stored week value.
 * @return UI week slug.
 */
const normalizeWeekForUi = ( week: string | undefined ): string => {
	if ( '1' === week || 'week-one' === week ) {
		return 'week-one';
	}
	if ( '2' === week || 'week-two' === week ) {
		return 'week-two';
	}

	return week || 'week-one';
};

export const defaultScheduleOptions = (): ScheduleOptions => ( {
	schedule: 'twelve-hours',
	day: 'sunday',
	week: 'week-one',
	startTime: '00:00',
	dayNum: '1',
} );

export const defaultEmailReportValues = (): EmailReportFormValues => ( {
	toField: [],
	bccField: [],
	subject: '',
	includeCore: true,
	includePlugins: true,
	includeThemes: true,
	includeTranslations: true,
	sendSchedule: 'none',
	scheduleOptions: defaultScheduleOptions(),
	notifySlack: false,
	logCutoffDays: 7,
} );

/**
 * Build form defaults from a retrieve or reset payload.
 *
 * @param payload Ajax data object.
 * @return Normalized form values.
 */
export const mapPayloadToFormValues = (
	payload: RetrievePayload
): EmailReportFormValues => {
	const scheduleFromNested = payload.schedule_options;
	const scheduleOptions: ScheduleOptions = {
		schedule:
			scheduleFromNested?.schedule ??
			payload.schedule ??
			defaultScheduleOptions().schedule,
		day:
			scheduleFromNested?.day ??
			payload.day ??
			defaultScheduleOptions().day,
		week: normalizeWeekForUi( scheduleFromNested?.week ?? payload.week ),
		startTime:
			scheduleFromNested?.startTime ??
			payload.start_time ??
			defaultScheduleOptions().startTime,
		dayNum:
			scheduleFromNested?.dayNum ??
			payload.day_num ??
			defaultScheduleOptions().dayNum,
	};

	return {
		toField: Array.isArray( payload.to_field ) ? payload.to_field : [],
		bccField: Array.isArray( payload.bcc_field ) ? payload.bcc_field : [],
		subject: payload.subject ?? '',
		includeCore: false !== payload.include_core,
		includePlugins: false !== payload.include_plugins,
		includeThemes: false !== payload.include_themes,
		includeTranslations: false !== payload.include_translations,
		sendSchedule: payload.send_schedule ?? 'none',
		scheduleOptions,
		notifySlack: true === payload.notify_slack,
		logCutoffDays: payload.log_cutoff_days ?? 7,
	};
};

type ResetCamelPayload = {
	toField?: string[];
	bccField?: string[];
	subject?: string;
	includeCore?: boolean;
	includePlugins?: boolean;
	includeThemes?: boolean;
	includeTranslations?: boolean;
	sendSchedule?: SendSchedule;
	notifySlack?: boolean;
	schedule?: string;
	day?: string;
	week?: string;
	startTime?: string;
	dayNum?: string | number;
	logCutoffDays?: number;
};

/**
 * Map reset ajax camelCase payload to form values.
 *
 * @param payload Reset response data.
 * @return Form values.
 */
export const mapResetPayloadToFormValues = (
	payload: ResetCamelPayload
): EmailReportFormValues => {
	return mapPayloadToFormValues( {
		to_field: payload.toField,
		bcc_field: payload.bccField,
		subject: payload.subject,
		include_core: payload.includeCore,
		include_plugins: payload.includePlugins,
		include_themes: payload.includeThemes,
		include_translations: payload.includeTranslations,
		send_schedule: payload.sendSchedule,
		schedule: payload.schedule,
		day: payload.day,
		week: payload.week,
		start_time: payload.startTime,
		day_num: payload.dayNum,
		log_cutoff_days: payload.logCutoffDays,
	} );
};

/**
 * Human-readable delivery mode label key.
 *
 * @param sendSchedule Current delivery mode.
 * @return Badge label slug.
 */
export const getDeliveryBadgeLabel = (
	sendSchedule: SendSchedule
): 'off' | 'immediate' | 'scheduled' => {
	if ( 'immediately' === sendSchedule ) {
		return 'immediate';
	}
	if ( 'custom' === sendSchedule ) {
		return 'scheduled';
	}

	return 'off';
};

/** FormTokenField token shape (string or object with value). */
export type FormTokenFieldToken = string | { value: string };

/**
 * Normalize FormTokenField values to plain email strings.
 *
 * @param tokens Tokens from FormTokenField onChange.
 * @return String array for form state and Ajax.
 */
export const formTokensToStringArray = (
	tokens: FormTokenFieldToken[]
): string[] => {
	return tokens.map( ( token ) =>
		'string' === typeof token ? token : token.value
	);
};
