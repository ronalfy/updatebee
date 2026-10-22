/**
 * Shared types for email report forms.
 */

export type SendSchedule = 'none' | 'immediately' | 'custom';

export type ScheduleOptions = {
	schedule: string;
	day: string;
	week: string;
	startTime: string;
	dayNum: string | number;
};

export type EmailReportFormValues = {
	toField: string[];
	bccField: string[];
	subject: string;
	includeCore: boolean;
	includePlugins: boolean;
	includeThemes: boolean;
	includeTranslations: boolean;
	sendSchedule: SendSchedule;
	scheduleOptions: ScheduleOptions;
};

export type EmailReportOptionsPayload = {
	to_field: string[];
	bcc_field: string[];
	subject: string;
	include_core: boolean;
	include_plugins: boolean;
	include_themes: boolean;
	include_translations: boolean;
	send_schedule: SendSchedule;
	schedule_options: ScheduleOptions;
	previewurl?: string;
};


export type EmailReportKind = 'success' | 'failure' | 'updates-available';
