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

export type CronCrontabCommand = {
	label: string;
	command: string;
};

export type CronScheduleFormValues = {
	schedule: CronFrequency;
	day: string;
	week: string;
	startTime: string;
	dayNum: string;
	daysList: number[];
	datetime: string;
	timestamp: number;
	crontabEquivalent: string;
	crontabCommands: CronCrontabCommand[];
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
	crontab_equivalent?: string;
	crontab_commands?: CronCrontabCommand[];
};
