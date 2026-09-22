/**
 * Types for update cron schedule forms.
 */

export type CronContextSlug =
	| 'plugin_updates'
	| 'theme_updates'
	| 'core_updates';

export type CronFrequency =
	| 'hourly'
	| 'two-hours'
	| 'three-hours'
	| 'six-hours'
	| 'twelve-hours'
	| 'daily'
	| 'weekly'
	| 'two-weeks'
	| 'monthly';

export type CronScheduleFormValues = {
	schedule: CronFrequency;
	day: string;
	week: string;
	startTime: string;
	dayNum: string;
	daysList: number[];
	datetime: string;
	timestamp: number;
};

export type CronSchedulePayload = {
	schedule?: string;
	day?: string;
	week?: string;
	start_time?: string;
	day_num?: string;
	days_list?: number[];
	datetime?: string;
	timestamp?: number;
};
